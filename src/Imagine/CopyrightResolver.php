<?php

namespace App\Imagine;

use App\Utils\ImageCopyright;
use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use Liip\ImagineBundle\Model\Binary;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Ghi metadata bản quyền vào mọi ảnh LiipImagine lưu ra public/media/cache
 * (thumbnail của tất cả filter set + bản .webp đi kèm) — GD xoá metadata của
 * ảnh gốc khi thu nhỏ, mà đây lại là file site thật sự trả cho khách/Google.
 * Bọc resolver thay vì khai báo post_processor ở từng filter set để filter set
 * mới thêm sau này cũng tự có.
 */
#[AsDecorator('liip_imagine.cache.resolver.default')]
final class CopyrightResolver implements ResolverInterface
{
    public function __construct(#[AutowireDecorated] private readonly ResolverInterface $inner)
    {
    }

    public function isStored($path, $filter)
    {
        return $this->inner->isStored($path, $filter);
    }

    public function resolve($path, $filter)
    {
        return $this->inner->resolve($path, $filter);
    }

    public function store(BinaryInterface $binary, $path, $filter)
    {
        $content = ImageCopyright::apply($binary->getContent());
        if ($content !== null) {
            $binary = new Binary($content, $binary->getMimeType(), $binary->getFormat());
        }

        $this->inner->store($binary, $path, $filter);
    }

    public function remove(array $paths, array $filters)
    {
        $this->inner->remove($paths, $filters);
    }
}
