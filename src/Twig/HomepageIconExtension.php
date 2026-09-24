<?php

namespace App\Twig;

use App\Service\Homepage\SectionIcons;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * section_icon('shield') => markup bên trong <svg> của icon đã chọn.
 *
 * Đánh dấu is_safe html vì markup là hằng số trong code (xem SectionIcons),
 * không phải dữ liệu người dùng nhập.
 */
final class HomepageIconExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('section_icon', [SectionIcons::class, 'markup'], ['is_safe' => ['html']]),
        ];
    }
}
