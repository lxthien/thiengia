<?php

namespace App\Controller\Admin;

use App\Entity\MediaAsset;
use App\Service\MediaThumbnailGenerator;
use App\Repository\MediaAssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Media Library Management Controller
 */
#[Route('/admin/media')]
#[IsGranted('ROLE_AUTHOR')]
class MediaController extends AbstractController
{
    private $uploadDir = 'uploads/media/';
    private $maxFileSize = 10485760; // 10MB
    private $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MediaAssetRepository $mediaAssetRepository,
        private readonly MediaThumbnailGenerator $thumbnailGenerator,
    ) {
    }

    /**
     * Display media library
     */
    #[Route('/', name: 'admin_media_index', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim((string) $request->query->get('q', ''));
        $folderFilter = trim((string) $request->query->get('folder', ''));
        $uploadDirPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        
        // Get storage info
        $storageInfo = $this->getStorageInfo();
        
        // Get folders list
        $folders = $this->getFoldersList($uploadDirPath);
        
        // Get media files
        $allFiles = $this->getMediaFiles($uploadDirPath, $folderFilter);
        if ($search !== '') {
            $allFiles = array_values(array_filter($allFiles, static fn (array $file): bool => mb_stripos($file['filename'], $search) !== false));
        }
        
        // Sort by date
        usort($allFiles, function ($a, $b) {
            return $b['time'] - $a['time'];
        });
        
        // Paginate
        $itemsPerPage = 24;
        $totalFiles = count($allFiles);
        $totalPages = max(1, (int) ceil($totalFiles / $itemsPerPage));
        $page = min($page, $totalPages);
        $start = ($page - 1) * $itemsPerPage;
        $files = array_slice($allFiles, $start, $itemsPerPage);

        return $this->render('admin/media/index.html.twig', [
            'files' => $files,
            'search' => $search,
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
     */
    #[Route('/picker', name: 'admin_media_picker', methods: ['GET'])]
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

        if ($request->query->get('_format') === 'json') {
            return $this->json(['files' => $files, 'folders' => $folders, 'currentFolder' => $folderFilter]);
        }

        return $this->render('admin/media/_picker.html.twig', [
            'files' => $files,
            'folders' => $folders,
            'currentFolder' => $folderFilter,
        ]);
    }

    /**
     * Upload media file
     */
    #[Route('/upload', name: 'admin_media_upload', methods: ['POST'])]
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
     */
    #[Route('/{filename}/delete', name: 'admin_media_delete', requirements: ['filename' => '.+'], methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function deleteAction(Request $request, $filename)
    {
        if (!$this->isCsrfTokenValid('delete-media', $request->request->get('token'))) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid CSRF token']);
        }

        $uploadDirPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        $filepath = $this->resolveMediaFile($filename);
        $thumbpath = dirname($filepath) . '/thumbs/' . basename($filepath);

        try {
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            if (file_exists($thumbpath)) {
                unlink($thumbpath);
            }

            $asset = $this->mediaAssetRepository->findOneBy(['path' => $filename]);
            if ($asset) {
                $this->em->remove($asset);
                $this->em->flush();
            }

            return new JsonResponse(['status' => 'success', 'message' => 'File đã được xóa']);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Lỗi xóa file']);
        }
    }

    /**
     * Set/update alt text for a media file
     */
    #[Route('/{filename}/alt', name: 'admin_media_alt', requirements: ['filename' => '.+'], methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function setAltAction(Request $request, $filename)
    {
        if (!$this->isCsrfTokenValid('delete-media', $request->request->get('token'))) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid CSRF token']);
        }

        $uploadDirPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        if (!file_exists($uploadDirPath . $filename)) {
            return new JsonResponse(['status' => 'error', 'message' => 'File not found']);
        }

        $this->resolveMediaFile($filename);
        $altText = trim((string) $request->request->get('alt', ''));

        if (mb_strlen($altText) > 255) {
            return $this->json(['status' => 'error', 'message' => 'Mô tả ALT tối đa 255 ký tự.'], 422);
        }
        $asset = $this->mediaAssetRepository->findOneBy(['path' => $filename]);
        if (!$asset) {
            $asset = new MediaAsset();
            $asset->setPath($filename);
        }
        $asset->setAltText($altText !== '' ? $altText : null);

        $this->em->persist($asset);
        $this->em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Đã lưu alt text', 'alt' => $altText]);
    }

    /**
     * Move media file to another folder
     */
    #[Route('/{filename}/move', name: 'admin_media_move', requirements: ['filename' => '.+'], methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function moveAction(Request $request, $filename)
    {
        if (!$this->isCsrfTokenValid('delete-media', $request->request->get('token'))) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid CSRF token']);
        }

        $targetFolder = trim((string) $request->request->get('targetFolder', ''));
        $newFolder    = trim((string) $request->request->get('newFolder', ''));

        $baseUploadPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;
        $srcPath  = $this->resolveMediaFile($filename);
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

        if ($uploadSubdir !== '' && !preg_match('~^[a-zA-Z0-9_-]+/$~D', $uploadSubdir)) {
            return $this->json(['status' => 'error', 'message' => 'Tên thư mục không hợp lệ.'], 422);
        }
        $destDir   = $baseUploadPath . $uploadSubdir;
        if (is_link(rtrim($destDir, '/'))) {
            return $this->json(['status' => 'error', 'message' => 'Thư mục không hợp lệ.'], 422);
        }
        $destPath  = $destDir . basename($srcPath);
        $destThumb = $destDir . 'thumbs/' . basename($srcPath);

        if (file_exists($destPath)) {
            return $this->json(['status' => 'error', 'message' => 'Thư mục đích đã có tệp cùng tên. Không ghi đè.'], 409);
        }
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

            $asset = $this->mediaAssetRepository->findOneBy(['path' => $filename]);
            if ($asset) {
                $asset->setPath($newRelativePath);
                $this->em->flush();
            }

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
     */
    #[Route('/{filename}/crop', name: 'admin_media_crop', requirements: ['filename' => '.+'], methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function cropAction(Request $request, $filename)
    {
        return $this->transformImage($request, $filename, true);
    }

    #[Route('/{filename}/resize', name: 'admin_media_resize', requirements: ['filename' => '.+'], methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function resizeAction(Request $request, $filename)
    {
        return $this->transformImage($request, $filename, false);
    }

    private function transformImage(Request $request, string $filename, bool $crop): JsonResponse
    {
        $data = $request->request->all();
        if (!$data) {
            $data = json_decode($request->getContent(), true);
        }
        if (!is_array($data) || !$this->isCsrfTokenValid('delete-media', (string) ($data['token'] ?? ''))) {
            return $this->json(['status' => 'error', 'message' => 'Phiên thao tác không hợp lệ. Hãy tải lại trang.'], 403);
        }
        $path = $this->resolveMediaFile($filename);
        $size = @getimagesize($path);
        if (!$size || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return $this->json(['status' => 'error', 'message' => 'Chỉ chỉnh kích thước/cắt ảnh JPG hoặc PNG. GIF được giữ nguyên để không mất chuyển động.'], 422);
        }
        $width = filter_var($data['width'] ?? null, FILTER_VALIDATE_INT);
        $height = filter_var($data['height'] ?? null, FILTER_VALIDATE_INT);
        $x = filter_var($data['x'] ?? 0, FILTER_VALIDATE_INT);
        $y = filter_var($data['y'] ?? 0, FILTER_VALIDATE_INT);
        if (!$width || !$height || $width < 1 || $height < 1 || $width > 10000 || $height > 10000 || $width * $height > 20000000
            || $size[0] * $size[1] > 20000000
            || ($crop && ($x === false || $y === false || $x < 0 || $y < 0 || $x + $width > $size[0] || $y + $height > $size[1]))) {
            return $this->json(['status' => 'error', 'message' => 'Kích thước hoặc vùng cắt không hợp lệ (giới hạn 20 triệu pixel).'], 422);
        }
        $source = null;
        $result = null;
        $temporary = null;
        try {
            $source = imagecreatefromstring(file_get_contents($path));
            if (!$source) {
                throw new \RuntimeException('Invalid image');
            }
            imagealphablending($source, false);
            imagesavealpha($source, true);
            $result = $crop ? imagecrop($source, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]) : imagescale($source, $width, $height);
            if (!$result) {
                throw new \RuntimeException('Transform failed');
            }
            imagealphablending($result, false);
            imagesavealpha($result, true);
            $temporary = tempnam(dirname($path), '.media-');
            $saved = $size[2] === IMAGETYPE_PNG ? imagepng($result, $temporary) : imagejpeg($result, $temporary, 90);
            if (!$saved || !rename($temporary, $path)) {
                throw new \RuntimeException('Save failed');
            }
            $this->createThumbnail($path);
            return $this->json(['status' => 'success', 'message' => 'Đã cập nhật ảnh.']);
        } catch (\Throwable $e) {
            return $this->json(['status' => 'error', 'message' => 'Không thể cập nhật ảnh. Hãy tải lại thư viện để kiểm tra trước khi thử lại.'], 500);
        } finally {
            if ($source) { imagedestroy($source); }
            if ($result) { imagedestroy($result); }
            if ($temporary && is_file($temporary)) { unlink($temporary); }
        }
    }

    private function resolveMediaFile(string $filename): string
    {
        $base = realpath($this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir);
        $path = $base ? realpath($base . '/' . $filename) : false;
        if (!$base || !$path || !is_file($path) || !str_starts_with(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $base), '/') . '/')
            || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $this->allowedExtensions, true)) {
            throw $this->createNotFoundException('Không tìm thấy ảnh trong thư viện.');
        }
        return $path;
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
        if ($folderFilter !== '' && (!preg_match('~^[a-zA-Z0-9_-]+$~D', $folderFilter) || is_link($uploadDirPath . '/' . $folderFilter))) {
            throw $this->createNotFoundException('Thư mục không hợp lệ.');
        }
        if ($folderFilter === '') {
            $files = $this->scanFilesRecursive($uploadDirPath, $uploadDirPath);
        } else {
            $targetDir = $uploadDirPath . '/' . ltrim($folderFilter, '/');
            $files = $this->scanFilesRecursive($targetDir, $uploadDirPath);
        }

        $altMap = $this->mediaAssetRepository->findAltTextMap(array_column($files, 'filename'));
        foreach ($files as &$file) {
            $file['alt'] = $altMap[$file['filename']] ?? '';
        }

        return $files;
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

        // Đuôi file client gửi lên chỉ là tên gợi ý, không đảm bảo nội dung
        // thật là ảnh — đổi tên 1 file .php thành .jpg vẫn qua được check trên.
        // getimagesize() đọc thật header ảnh (đã dùng sẵn ở createThumbnail()
        // trong file này), false nghĩa là nội dung không phải ảnh hợp lệ.
        if (@getimagesize($file->getPathname()) === false) {
            return [
                'valid' => false,
                'message' => 'File không phải là ảnh hợp lệ.',
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
        $this->thumbnailGenerator->generate($filepath);
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
