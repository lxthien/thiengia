<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

final class MediaThumbnailGenerator
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function generate(string $filepath): bool
    {
        $image = null;
        $thumbnail = null;
        $temporaryPath = null;

        try {
            $contents = @file_get_contents($filepath);
            $image = $contents === false ? false : @imagecreatefromstring($contents);
            if (!$image instanceof \GdImage) {
                throw new \RuntimeException('The image could not be decoded.');
            }

            // PHP 8.1 getimagesize() can return 0 x 0 for valid AVIF data,
            // including AVIF uploads named .jpg. Use the decoded GD dimensions.
            $width = imagesx($image);
            $height = imagesy($image);
            if ($width < 1 || $height < 1) {
                throw new \RuntimeException('Invalid decoded image dimensions.');
            }

            $scale = min(360 / $width, 200 / $height, 1);
            $thumbWidth = max(1, (int) round($width * $scale));
            $thumbHeight = max(1, (int) round($height * $scale));
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
            if (!$thumbnail instanceof \GdImage) {
                throw new \RuntimeException('Could not allocate a thumbnail.');
            }

            // Existing media URLs expect JPEG thumbnails. Flatten transparency
            // onto white, rather than the default black true-color canvas.
            imagefill($thumbnail, 0, 0, imagecolorallocate($thumbnail, 255, 255, 255));
            if (!imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height)) {
                throw new \RuntimeException('Could not resample the image.');
            }

            $directory = dirname($filepath) . '/thumbs';
            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException('Could not create the thumbnail directory.');
            }

            // Publish only a fully encoded thumbnail; keep an existing one
            // untouched if decoding or encoding fails.
            $temporaryPath = tempnam($directory, '.thumbnail-');
            if ($temporaryPath === false || !imagejpeg($thumbnail, $temporaryPath, 85)) {
                throw new \RuntimeException('Could not encode the thumbnail.');
            }
            if (!@rename($temporaryPath, $directory . '/' . basename($filepath))) {
                throw new \RuntimeException('Could not publish the thumbnail.');
            }
            $temporaryPath = null;

            return true;
        } catch (\Throwable $exception) {
            $this->logger->warning('Media thumbnail generation failed.', [
                'file' => basename($filepath),
                'exception' => $exception,
            ]);

            return false;
        } finally {
            if ($image instanceof \GdImage) {
                imagedestroy($image);
            }
            if ($thumbnail instanceof \GdImage) {
                imagedestroy($thumbnail);
            }
            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
