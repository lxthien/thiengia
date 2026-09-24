<?php

namespace App\EventListener;

use App\Entity\HomepageSection;
use App\Service\InlineHtmlPurifier;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Lọc HTML các ô chữ của khối trang chủ trước khi lưu.
 *
 * Chặn ở tầng lưu (giống ContentSanitizerListener) để áp dụng cho mọi đường
 * ghi dữ liệu, không riêng form admin — template in ra bằng |raw nên dữ liệu
 * trong DB buộc phải sạch.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class HomepageSectionSanitizerListener
{
    private const FIELDS = ['label', 'title', 'subtitle'];

    public function __construct(private readonly InlineHtmlPurifier $purifier)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof HomepageSection) {
            return;
        }

        $entity->setLabel($this->purifier->purify($entity->getLabel()));
        $entity->setTitle($this->purifier->purify($entity->getTitle()));
        $entity->setSubtitle($this->purifier->purify($entity->getSubtitle()));
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof HomepageSection) {
            return;
        }

        foreach (self::FIELDS as $field) {
            if (!$args->hasChangedField($field)) {
                continue;
            }

            $clean = $this->purifier->purify($args->getNewValue($field));

            if ($clean !== $args->getNewValue($field)) {
                // preUpdate: changeset đã tính xong nên phải setNewValue(),
                // sửa property trực tiếp sẽ không được ghi xuống DB.
                $args->setNewValue($field, $clean);
            }
        }
    }
}
