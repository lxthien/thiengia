<?php

namespace App\EventListener;

use App\Entity\News;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use HTMLPurifier;
use HTMLPurifier_Config;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Lọc HTML nội dung bài viết/trang (field "contents", CKEditor) trước khi
// lưu — trước đây HTML thô đi thẳng vào DB không qua bước sanitize nào,
// dù ezyang/htmlpurifier đã là dependency sẵn có (dùng cho việc khác:
// ContentDecayReporter đo độ dài nội dung). Chặn ở tầng lưu (không phải
// tầng hiển thị) để áp dụng cho MỌI đường ghi dữ liệu (form admin, import,
// fixtures...), không chỉ riêng luồng qua NewsType/PageType.
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class ContentSanitizerListener
{
    private ?HTMLPurifier $purifier = null;

    public function __construct(
        #[Autowire(param: 'kernel.cache_dir')]
        private readonly string $cacheDir,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof News) {
            return;
        }

        $contents = $entity->getContents();
        if ($contents === null || $contents === '') {
            return;
        }

        $clean = $this->getPurifier()->purify($contents);
        if ($clean !== $contents) {
            $entity->setContents($clean);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof News) {
            return;
        }

        if (!$args->hasChangedField('contents')) {
            return;
        }

        $contents = $args->getNewValue('contents');
        if ($contents === null || $contents === '') {
            return;
        }

        $clean = $this->getPurifier()->purify($contents);
        if ($clean !== $contents) {
            // preUpdate: changeset đã tính xong, sửa trực tiếp property
            // không đủ — phải dùng setNewValue() để Doctrine ghi giá trị
            // mới vào DB.
            $args->setNewValue('contents', $clean);
        }
    }

    private function getPurifier(): HTMLPurifier
    {
        if ($this->purifier === null) {
            $cacheDir = $this->cacheDir . '/htmlpurifier';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0775, true);
            }

            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', $cacheDir);
            $config->set('HTML.Allowed', implode(',', [
                'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'ul', 'ol', 'li',
                'a[href|title|target|rel]',
                'img[src|alt|title|width|height]',
                'blockquote', 'q',
                'table', 'thead', 'tbody', 'tr', 'th', 'td',
                'span[style]', 'div[style]',
                'hr',
            ]));
            $config->set('CSS.AllowedProperties', ['text-align', 'color', 'background-color', 'font-weight', 'font-style']);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);

            $this->purifier = new HTMLPurifier($config);
        }

        return $this->purifier;
    }
}
