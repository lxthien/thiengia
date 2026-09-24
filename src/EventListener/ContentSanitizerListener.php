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
            // Giữ nguyên class/id và các thẻ khối mà CKEditor 5 (GeneralHtmlSupport,
            // xem htmlSupport.allow trong public/assets/js/admin/ckeditor5.js) cố ý
            // cho phép: geo-blocks (div.key-takeaways, div.stat-box, div.definition),
            // mục lục (div.ka-table-of-contents + id trên heading để link "#id" trỏ
            // đúng chỗ), content-block tool (section.cms-block-* kèm data-cms-block/
            // data-cms-payload để mở lại block mà sửa) và Page Builder (ka-builder-*).
            // Trước đây HTML.Allowed chỉ có 'div[style]' nên mọi class/id/section bị
            // xoá sạch khi lưu, dù editor đã giữ đúng.
            $config->set('Attr.EnableID', true);
            $config->set('HTML.Allowed', implode(',', [
                'p[class|id|style]', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
                'h1[class|id]', 'h2[class|id]', 'h3[class|id]', 'h4[class|id]', 'h5[class|id]', 'h6[class|id]',
                'ul[class]', 'ol[class]', 'li[class]',
                'a[href|title|target|rel|class|id|data-fancybox|data-caption]',
                'img[src|alt|title|width|height|class|loading]',
                'blockquote[class]', 'q',
                'table[class]', 'thead', 'tbody', 'tr[class]', 'th[class|colspan|rowspan]', 'td[class|colspan|rowspan]',
                'span[style|class]',
                'div[style|class|id|data-cms-block|data-cms-payload]',
                'section[style|class|id|data-cms-block|data-cms-payload]',
                'figure[class]', 'figcaption[class]',
                'hr',
            ]));
            $config->set('CSS.AllowedProperties', ['text-align', 'color', 'background-color', 'font-weight', 'font-style']);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);

            // Doctype mặc định (HTML 4.01) không biết section/figure/figcaption và
            // không cho attribute data-* — phải khai báo thêm. DefinitionRev PHẢI
            // tăng mỗi khi sửa khối dưới đây, nếu không bản cache cũ vẫn được dùng.
            $config->set('HTML.DefinitionID', 'thiengia-cms-content');
            $config->set('HTML.DefinitionRev', 1);

            // maybeGetRawHTMLDefinition() "finalize" config, nên mọi ->set() phải
            // nằm TRƯỚC dòng này; trả về null khi definition đã có sẵn trong cache.
            if ($def = $config->maybeGetRawHTMLDefinition()) {
                $def->addElement('section', 'Block', 'Flow', 'Common');
                $def->addElement('figure', 'Block', 'Flow', 'Common');
                $def->addElement('figcaption', 'Block', 'Flow', 'Common');

                foreach (['div', 'section'] as $element) {
                    $def->addAttribute($element, 'data-cms-block', 'Text');
                    $def->addAttribute($element, 'data-cms-payload', 'Text');
                }

                $def->addAttribute('a', 'data-fancybox', 'Text');
                $def->addAttribute('a', 'data-caption', 'Text');
                $def->addAttribute('img', 'loading', 'Enum#lazy,eager');
            }

            $this->purifier = new HTMLPurifier($config);
        }

        return $this->purifier;
    }
}
