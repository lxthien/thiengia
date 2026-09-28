<?php
declare(strict_types=1);

namespace App\Twig;

use App\Service\TitleTokenResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TitleTokenExtension extends AbstractExtension
{
    public function __construct(private readonly TitleTokenResolver $resolver)
    {
    }

    public function getFilters(): array
    {
        // Not marked HTML-safe: normal Twig escaping still applies.
        return [new TwigFilter('title_tokens', [$this->resolver, 'resolve'])];
    }
}
