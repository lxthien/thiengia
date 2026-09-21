<?php

// Run without PHPUnit: C:\xampp81\php\php.exe tests/Smoke/media_thumbnail.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Service\MediaThumbnailGenerator;
use Psr\Log\NullLogger;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$directory = sys_get_temp_dir() . '/thiengia-thumbnail-test-' . bin2hex(random_bytes(6));
mkdir($directory);
$generator = new MediaThumbnailGenerator(new NullLogger());
$files = [];

try {
    $source = imagecreatetruecolor(600, 400);
    imagefill($source, 0, 0, imagecolorallocate($source, 210, 110, 50));

    $formats = ['jpeg' => 'jpg', 'png' => 'png'];
    if (function_exists('imageavif') && (gd_info()['AVIF Support'] ?? false)) {
        // Deliberately use a .jpg name for AVIF, reproducing the real upload.
        $formats['avif'] = 'jpg';
    }
    foreach ($formats as $format => $extension) {
        $path = $directory . '/sample-' . $format . '.' . $extension;
        $files[] = $path;
        ('image' . $format)($source, $path);
        $hash = hash_file('sha256', $path);
        check($generator->generate($path), $format . ': thumbnail generated');
        $thumbPath = $directory . '/thumbs/' . basename($path);
        $files[] = $thumbPath;
        $thumb = imagecreatefromjpeg($thumbPath);
        check(imagesx($thumb) === 300 && imagesy($thumb) === 200, $format . ': aspect ratio preserved');
        $color = imagecolorsforindex($thumb, imagecolorat($thumb, 150, 100));
        check($color['red'] > 180 && $color['green'] > 80, $format . ': thumbnail is not black');
        check(hash_file('sha256', $path) === $hash, $format . ': original unchanged');
        check($generator->generate($path), $format . ': existing thumbnail can be refreshed');
        imagedestroy($thumb);
        echo 'PASS ' . $format . PHP_EOL;
    }
    imagedestroy($source);

    $small = imagecreatetruecolor(30, 20);
    $smallPath = $directory . '/small.jpg';
    $files[] = $smallPath;
    imagejpeg($small, $smallPath);
    imagedestroy($small);
    check($generator->generate($smallPath), 'Small thumbnail generated');
    $files[] = $directory . '/thumbs/small.jpg';
    [$width, $height] = getimagesize($directory . '/thumbs/small.jpg');
    check($width === 30 && $height === 20, 'Small images are not enlarged');

    $invalid = $directory . '/invalid.jpg';
    $files[] = $invalid;
    file_put_contents($invalid, 'not an image');
    check(!$generator->generate($invalid), 'Invalid image rejected');
    check(!file_exists($directory . '/thumbs/invalid.jpg'), 'Invalid image leaves no black thumbnail');
    echo 'PASS small/invalid images' . PHP_EOL;
} finally {
    foreach (array_unique($files) as $file) {
        if (is_file($file)) unlink($file);
    }
    if (is_dir($directory . '/thumbs')) rmdir($directory . '/thumbs');
    rmdir($directory);
}
