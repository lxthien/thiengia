<?php

namespace CKSource\CKFinder\Plugin\ImageCopyright;

use App\Utils\ImageCopyright as Copyright;
use CKSource\CKFinder\CKFinder;
use CKSource\CKFinder\Event\CKFinderEvent;
use CKSource\CKFinder\Event\EditFileEvent;
use CKSource\CKFinder\Event\FileUploadEvent;
use CKSource\CKFinder\Event\ResizeImageEvent;
use CKSource\CKFinder\Plugin\PluginInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// Chỉ nạp đúng 1 file thuần PHP của app, không nạp autoloader của app — connector
// CKFinder có bản Symfony riêng (xem ghi chú phần authentication trong config.php).
// __DIR__ = <project>/public/assets/cksourceckfinder/ckfinder/plugins/ImageCopyright
if (!class_exists(Copyright::class, false)) {
    require_once dirname(__DIR__, 6) . '/src/Utils/ImageCopyright.php';
}

/**
 * Ghi metadata bản quyền vào ảnh tải lên qua CKFinder (gồm QuickUpload khi kéo-
 * thả/dán ảnh trong CKEditor), ảnh sửa bằng trình chỉnh ảnh và ảnh resize.
 * FILE_UPLOAD chạy SAU bước thu nhỏ theo images.maxWidth (GD xoá metadata) và
 * TRƯỚC khi ghi file, nên metadata còn nguyên trong file lưu trên đĩa.
 */
class ImageCopyright implements PluginInterface, EventSubscriberInterface
{
    public function setContainer(CKFinder $app)
    {
    }

    public function getDefaultConfig()
    {
        return [];
    }

    public static function getSubscribedEvents()
    {
        return [
            CKFinderEvent::FILE_UPLOAD => 'onFileUpload',
            CKFinderEvent::EDIT_IMAGE => 'onEditImage',
            CKFinderEvent::SAVE_IMAGE => 'onEditImage',
            CKFinderEvent::CREATE_RESIZED_IMAGE => 'onResizeImage',
        ];
    }

    public function onFileUpload(FileUploadEvent $event)
    {
        $file = $event->getFile();
        $data = Copyright::apply($file->getContents());
        if ($data !== null) {
            $file->save($data);
        }
    }

    public function onEditImage(EditFileEvent $event)
    {
        $data = Copyright::apply((string) $event->getNewContents());
        if ($data !== null) {
            $event->setNewContents($data);
        }
    }

    public function onResizeImage(ResizeImageEvent $event)
    {
        $image = $event->getResizedImage();
        $data = Copyright::apply((string) $image->getImageData());
        if ($data !== null) {
            $image->setImageData($data);
        }
    }
}
