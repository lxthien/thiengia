<?php

namespace App\Controller\Admin;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Media Library Management Controller
 *
 * @Route("/admin/media")
 * @Security("has_role('ROLE_ADMIN')")
 */
class MediaController extends Controller
{
    private $uploadDir = 'uploads/media/';
    private $maxFileSize = 10485760; // 10MB
    private $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    /**
     * Display media library
     *
     * @Route("/", name="admin_media_index")
     * @Method("GET")
     */
    public function indexAction(Request $request)
    {
        $page = $request->query->get('page', 1);
        $folderFilter = trim((string) $request->query->get('folder', ''));
        $uploadDirPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        
        // Get storage info
        $storageInfo = $this->getStorageInfo();
        
        // Get folders list
        $folders = $this->getFoldersList($uploadDirPath);
        
        // Get media files
        $allFiles = $this->getMediaFiles($uploadDirPath, $folderFilter);
        
        // Sort by date
        usort($allFiles, function ($a, $b) {
            return $b['time'] - $a['time'];
        });
        
        // Paginate
        $itemsPerPage = 24;
        $totalFiles = count($allFiles);
        $totalPages = ceil($totalFiles / $itemsPerPage);
        $start = ($page - 1) * $itemsPerPage;
        $files = array_slice($allFiles, $start, $itemsPerPage);

        return $this->render('admin/media/index.html.twig', [
            'files' => $files,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalFiles' => $totalFiles,
            'storageInfo' => $storageInfo,
            'folders' => $folders,
            'currentFolder' => $folderFilter,
        ]);
    }

    /**
     * Media picker — AJAX endpoint for selecting images in forms
     *
     * @Route("/picker", name="admin_media_picker")
     * @Method("GET")
     */
    public function pickerAction(Request $request)
    {
        $uploadDirPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        $folderFilter = trim((string) $request->query->get('folder', ''));

        $folders = $this->getFoldersList($uploadDirPath);
        $allFiles = $this->getMediaFiles($uploadDirPath, $folderFilter);

        usort($allFiles, function ($a, $b) {
            return $b['time'] - $a['time'];
        });

        // Return at most 48 latest images for the picker
        $files = array_slice($allFiles, 0, 48);

        return $this->render('admin/media/_picker.html.twig', [
            'files' => $files,
            'folders' => $folders,
            'currentFolder' => $folderFilter,
        ]);
    }

    /**
     * Upload media file
     *
     * @Route("/upload", name="admin_media_upload")
     * @Method("POST")
     */
    public function uploadAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid request']);
        }

        $file = $request->files->get('file');
        
        if (!$file) {
            return new JsonResponse(['status' => 'error', 'message' => 'Không có file']);
        }

        // Validate file
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            return new JsonResponse(['status' => 'error', 'message' => $validation['message']]);
        }

        // Get folder params
        $folder = trim((string) $request->request->get('folder', ''));
        $newFolder = trim((string) $request->request->get('newFolder', ''));

        if ($newFolder !== '') {
            // Slugify folder name
            $folderName = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($newFolder));
            $folderName = preg_replace('/-+/', '-', $folderName);
            $folderName = trim($folderName, '-');
            $uploadSubdir = $folderName . '/';
        } elseif ($folder !== '') {
            $uploadSubdir = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($folder)) . '/';
        } else {
            $uploadSubdir = '';
        }

        // Create upload directory
        $baseUploadPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        $uploadDirPath = $baseUploadPath . $uploadSubdir;
        if (!is_dir($uploadDirPath)) {
            mkdir($uploadDirPath, 0755, true);
        }

        // Generate unique filename
        $originalName = $file->getClientOriginalName();
        $filename = $this->generateUniqueFilename($originalName);
        $filepath = $uploadDirPath . $filename;

        // Move file
        try {
            $file->move($uploadDirPath, $filename);
            
            // Create thumbnail
            $this->createThumbnail($filepath);
            $thumbpath = $uploadDirPath . 'thumbs/' . $filename;

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Tải file thành công',
                'filename' => $uploadSubdir . $filename,
                'url' => '/' . $this->uploadDir . $uploadSubdir . $filename,
                'thumb' => is_file($thumbpath) ? '/' . $this->uploadDir . $uploadSubdir . 'thumbs/' . $filename : '/' . $this->uploadDir . $uploadSubdir . $filename,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Lỗi tải file: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete media file
     *
     * @Route("/{filename}/delete", name="admin_media_delete", requirements={"filename"=".+"})
     * @Method("POST")
     */
    public function deleteAction(Request $request, $filename)
    {
        if (!$this->isCsrfTokenValid('delete-media', $request->request->get('token'))) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid CSRF token']);
        }

        $uploadDirPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        $filepath = $uploadDirPath . $filename;
        $thumbpath = dirname($filepath) . '/thumbs/' . basename($filepath);

        try {
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            if (file_exists($thumbpath)) {
                unlink($thumbpath);
            }

            return new JsonResponse(['status' => 'success', 'message' => 'File đã được xóa']);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Lỗi xóa file']);
        }
    }

    /**
     * Move media file to another folder
     *
     * @Route("/{filename}/move", name="admin_media_move", requirements={"filename"=".+"})
     * @Method("POST")
     */
    public function moveAction(Request $request, $filename)
    {
        if (!$this->isCsrfTokenValid('delete-media', $request->request->get('token'))) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid CSRF token']);
        }

        $targetFolder = trim((string) $request->request->get('targetFolder', ''));
        $newFolder    = trim((string) $request->request->get('newFolder', ''));

        $baseUploadPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        $srcPath  = $baseUploadPath . $filename;
        $srcThumb = dirname($srcPath) . '/thumbs/' . basename($srcPath);

        if (!file_exists($srcPath)) {
            return new JsonResponse(['status' => 'error', 'message' => 'File không tồn tại']);
        }

        // Resolve destination subfolder
        if ($newFolder !== '') {
            $slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($newFolder));
            $slug = trim(preg_replace('/-+/', '-', $slug), '-');
            $uploadSubdir = $slug !== '' ? $slug . '/' : '';
        } elseif ($targetFolder !== '') {
            $uploadSubdir = rtrim($targetFolder, '/') . '/';
        } else {
            $uploadSubdir = '';
        }

        $destDir   = $baseUploadPath . $uploadSubdir;
        $destPath  = $destDir . basename($srcPath);
        $destThumb = $destDir . 'thumbs/' . basename($srcPath);

        // Cannot move to same location
        if (realpath($srcPath) === realpath($destPath)) {
            return new JsonResponse(['status' => 'error', 'message' => 'File đã ở trong thư mục này']);
        }

        try {
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            rename($srcPath, $destPath);

            // Move thumbnail if exists
            $thumbsDestDir = $destDir . 'thumbs/';
            if (file_exists($srcThumb)) {
                if (!is_dir($thumbsDestDir)) {
                    mkdir($thumbsDestDir, 0755, true);
                }
                rename($srcThumb, $destThumb);
            } else {
                // Regenerate thumbnail at destination
                $this->createThumbnail($destPath);
            }

            $newRelativePath = $uploadSubdir . basename($srcPath);
            return new JsonResponse([
                'status'   => 'success',
                'message'  => 'Di chuyển file thành công',
                'filename' => $newRelativePath,
                'url'      => '/' . $this->uploadDir . $newRelativePath,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Lỗi di chuyển file: ' . $e->getMessage()]);
        }
    }

    /**
     * Crop image
     *
     * @Route("/{filename}/crop", name="admin_media_crop", requirements={"filename"=".+"})
     * @Method("POST")
     */
    public function cropAction(Request $request, $filename)
    {
        $uploadDirPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        $filepath = $uploadDirPath . $filename;

        if (!file_exists($filepath)) {
            return new JsonResponse(['status' => 'error', 'message' => 'File not found']);
        }

        $data = json_decode($request->getContent(), true);
        $x = $data['x'] ?? 0;
        $y = $data['y'] ?? 0;
        $width = $data['width'] ?? 100;
        $height = $data['height'] ?? 100;

        try {
            $image = imagecreatefromstring(file_get_contents($filepath));
            $cropped = imagecrop($image, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
            
            imagejpeg($cropped, $filepath, 90);
            imagedestroy($image);
            imagedestroy($cropped);

            // Refresh thumbnail
            $this->createThumbnail($filepath);

            return new JsonResponse(['status' => 'success', 'message' => 'Cắt ảnh thành công']);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Lỗi cắt ảnh']);
        }
    }

    /**
     * Resize image
     *
     * @Route("/{filename}/resize", name="admin_media_resize", requirements={"filename"=".+"})
     * @Method("POST")
     */
    public function resizeAction(Request $request, $filename)
    {
        $uploadDirPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        $filepath = $uploadDirPath . $filename;

        if (!file_exists($filepath)) {
            return new JsonResponse(['status' => 'error', 'message' => 'File not found']);
        }

        $data = json_decode($request->getContent(), true);
        $newWidth = (int)($data['width'] ?? 800);
        $newHeight = (int)($data['height'] ?? 600);

        try {
            list($origWidth, $origHeight) = getimagesize($filepath);
            
            $image = imagecreatefromstring(file_get_contents($filepath));
            $resized = imagescale($image, $newWidth, $newHeight);
            
            imagejpeg($resized, $filepath, 90);
            imagedestroy($image);
            imagedestroy($resized);

            // Refresh thumbnail
            $this->createThumbnail($filepath);

            return new JsonResponse(['status' => 'success', 'message' => 'Thay đổi kích thước thành công']);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Lỗi thay đổi kích thước']);
        }
    }

    /**
     * Get storage information
     */
    private function getStorageInfo()
    {
        $uploadDirPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        
        if (!is_dir($uploadDirPath)) {
            return [
                'used' => 0,
                'used_formatted' => '0 B',
                'limit' => $this->maxFileSize,
                'limit_formatted' => $this->formatBytes($this->maxFileSize),
                'percent' => 0,
            ];
        }

        $usedSpace = $this->getDirectorySize($uploadDirPath);
        $totalLimit = 104857600; // 100MB
        $percent = round(($usedSpace / $totalLimit) * 100, 1);

        return [
            'used' => $usedSpace,
            'used_formatted' => $this->formatBytes($usedSpace),
            'limit' => $totalLimit,
            'limit_formatted' => $this->formatBytes($totalLimit),
            'percent' => $percent,
        ];
    }

    /**
     * Get all media files
     */
    private function getMediaFiles($uploadDirPath, $folderFilter = '')
    {
        if ($folderFilter === '') {
            return $this->scanFilesRecursive($uploadDirPath, $uploadDirPath);
        } else {
            $targetDir = $uploadDirPath . '/' . ltrim($folderFilter, '/');
            return $this->scanFilesRecursive($targetDir, $uploadDirPath);
        }
    }

    private function scanFilesRecursive($dirPath, $baseDirPath)
    {
        $files = [];
        if (!is_dir($dirPath)) {
            return $files;
        }

        $items = scandir($dirPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'thumbs') {
                continue;
            }

            $filepath = $dirPath . '/' . $item;
            if (is_dir($filepath)) {
                $files = array_merge($files, $this->scanFilesRecursive($filepath, $baseDirPath));
            } elseif (is_file($filepath)) {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (in_array($ext, $this->allowedExtensions)) {
                    $imageSize = @getimagesize($filepath);
                    if ($imageSize === false) {
                        continue;
                    }

                    list($width, $height) = $imageSize;

                    // Normalize slashes for safe replacement
                    $normalizedBase = str_replace('\\', '/', $baseDirPath);
                    $normalizedFile = str_replace('\\', '/', $filepath);
                    $relativePath = ltrim(str_replace($normalizedBase, '', $normalizedFile), '/');

                    $url = '/' . $this->uploadDir . $relativePath;
                    $parentDir = dirname($filepath);
                    $thumbpath = $parentDir . '/thumbs/' . $item;
                    
                    $relativeDir = dirname($relativePath);
                    if ($relativeDir === '.') {
                        $thumbUrl = '/' . $this->uploadDir . 'thumbs/' . $item;
                    } else {
                        $thumbUrl = '/' . $this->uploadDir . $relativeDir . '/thumbs/' . $item;
                    }

                    if (!is_file($thumbpath)) {
                        $this->createThumbnail($filepath);
                    }

                    $files[] = [
                        'filename' => $relativePath,
                        'url' => $url,
                        'thumb' => is_file($thumbpath) ? $thumbUrl : $url,
                        'size' => filesize($filepath),
                        'size_formatted' => $this->formatBytes(filesize($filepath)),
                        'time' => filemtime($filepath),
                        'date' => date('d/m/Y H:i', filemtime($filepath)),
                        'width' => $width,
                        'height' => $height,
                        'ext' => $ext,
                    ];
                }
            }
        }

        return $files;
    }

    private function getFoldersList($baseDirPath)
    {
        $folders = [];
        if (!is_dir($baseDirPath)) {
            return $folders;
        }

        $items = scandir($baseDirPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'thumbs') {
                continue;
            }

            if (is_dir($baseDirPath . '/' . $item)) {
                $folders[] = $item;
            }
        }

        return $folders;
    }

    /**
     * Validate file
     */
    private function validateFile($file)
    {
        $size = $file->getSize();
        $ext = strtolower($file->getClientOriginalExtension());

        if ($size > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => 'File quá lớn. Tối đa ' . $this->formatBytes($this->maxFileSize),
            ];
        }

        if (!in_array($ext, $this->allowedExtensions)) {
            return [
                'valid' => false,
                'message' => 'Định dạng file không hỗ trợ. Chỉ hỗ trợ: ' . implode(', ', $this->allowedExtensions),
            ];
        }

        return ['valid' => true, 'message' => 'OK'];
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFilename($originalName)
    {
        $pathinfo = pathinfo($originalName);
        $name = $pathinfo['filename'];
        $ext = $pathinfo['extension'];
        
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $name = substr($name, 0, 50);
        
        return $name . '_' . time() . '.' . $ext;
    }

    /**
     * Create thumbnail
     */
    private function createThumbnail($filepath)
    {
        $uploadDirPath = dirname($filepath);
        $thumbsDir = $uploadDirPath . '/thumbs';
        
        if (!is_dir($thumbsDir)) {
            mkdir($thumbsDir, 0755, true);
        }

        $filename = basename($filepath);
        $thumbpath = $thumbsDir . '/' . $filename;

        try {
            list($width, $height) = getimagesize($filepath);
            $image = imagecreatefromstring(file_get_contents($filepath));
            
            $thumbWidth = 200;
            $thumbHeight = 200;
            
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
            
            imagejpeg($thumbnail, $thumbpath, 80);
            imagedestroy($image);
            imagedestroy($thumbnail);
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Get directory size
     */
    private function getDirectorySize($dir)
    {
        $size = 0;
        
        if (is_file($dir)) {
            return filesize($dir);
        }

        if (!is_dir($dir)) {
            return 0;
        }

        $items = scandir($dir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $dir . '/' . $item;
            $size += $this->getDirectorySize($path);
        }

        return $size;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
