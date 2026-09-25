<?php

namespace App\Utils;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ConvertImages
{
    private string $publicDir;

    /**
     * Mọi đường dẫn nhận/trả về đều tương đối theo thư mục public/ (vd.
     * "uploads/a.jpg.webp"), nhưng thao tác file luôn dùng đường dẫn tuyệt
     * đối — không phụ thuộc thư mục làm việc (cwd) của PHP. Với
     * "php -S ... public/router.php", cwd là gốc dự án chứ không phải public/,
     * nên đường dẫn tương đối trước đây không tìm thấy file.
     */
    public function __construct(#[Autowire('%kernel.project_dir%/public')] ?string $publicDir = null)
    {
        $this->publicDir = rtrim($publicDir ?? dirname(__DIR__, 2).'/public', '/\\');
    }

    public function webpConvert2($file, $prifexPath, $filter = false, $compression_quality = 75)
    {
        if (!$file) {
            return false;
        }

        if ($filter) {
            $strpos = strpos($file, 'media/cache');
            $file = mb_substr($file, $strpos, strlen($file), 'UTF-8');
        } else {
            $file = substr($file, 1);
        }

        return $this->convert($file, $prifexPath, $compression_quality);
    }

    public function webpConvert($file, $prifexPath, $filter = false, $compression_quality = 75)
    {
        if (!$file) {
            return false;
        }

        if ($filter) {
            $strpos = strpos($file, 'media/cache');
            $file = str_replace('/resolve' ,'', mb_substr($file, $strpos, strlen($file), 'UTF-8'));
        } else {
            $file = substr($file, 1);
        }

        return $this->convert($file, $prifexPath, $compression_quality);
    }

    public function webpFileExists($file, $filterSets)
    {
        return @file_exists($this->path($filterSets . $file . '.webp'));
    }

    public function fileExists($file)
    {
        if (!$file) {
            return false;
        }

        return @file_exists($this->path(substr($file, 1)));
    }

    /**
     * Đường dẫn tuyệt đối trên đĩa của một đường dẫn tương đối theo public/.
     */
    public function path(string $relative): string
    {
        return $this->publicDir . '/' . ltrim(rawurldecode($relative), '/');
    }

    /**
     * @return string|false đường dẫn .webp tương đối theo public/, hoặc false nếu không chuyển được
     */
    private function convert(string $file, $prifexPath, $compression_quality)
    {
        $source = $this->path($file);

        // check if file exists
        if (!@is_file($source)) {
            return false;
        }

        $output_file = $prifexPath . $file . '.webp';
        $output = $this->path($output_file);

        if (@file_exists($output)) {
            return $output_file;
        }

        //https://www.php.net/manual/en/function.exif-imagetype.php
        // 1    IMAGETYPE_GIF
        // 2    IMAGETYPE_JPEG
        // 3    IMAGETYPE_PNG
        // 6    IMAGETYPE_BMP
        // 15   IMAGETYPE_WBMP
        // 16   IMAGETYPE_XBM
        $file_type = @exif_imagetype($source);

        if (function_exists('imagewebp')) {
            switch ($file_type) {
                case '1': //IMAGETYPE_GIF
                    $image = imagecreatefromgif($source);
                    break;
                case '2': //IMAGETYPE_JPEG
                    $image = imagecreatefromjpeg($source);
                    break;
                case '3': //IMAGETYPE_PNG
                    $image = imagecreatefrompng($source);
                    if ($image) {
                        imagepalettetotruecolor($image);
                        imagealphablending($image, true);
                        imagesavealpha($image, true);
                    }
                    break;
                case '6': // IMAGETYPE_BMP
                    $image = imagecreatefrombmp($source);
                    break;
                case '16': //IMAGETYPE_XBM
                    $image = imagecreatefromxbm($source);
                    break;
                default: // 15 IMAGETYPE_Webp, hoặc không phải ảnh
                    return false;
            }

            if (!$image) {
                return false;
            }

            // Save the image
            $result = imagewebp($image, $output, $compression_quality);

            // Free up memory
            imagedestroy($image);

            return false === $result ? false : $output_file;
        } elseif (class_exists('Imagick')) {
            $image = new \Imagick();
            $image->readImage($source);

            if ($file_type === "3") {
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality($compression_quality);
                $image->setOption('webp:lossless', 'true');
            }

            $image->writeImage($output);
            return $output_file;
        }
        return false;
    }
}
