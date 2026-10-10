<?php

namespace App\Twig;

use App\Service\AuthorBiographySanitizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AuthorProfileExtension extends AbstractExtension
{
    public function __construct(private readonly AuthorBiographySanitizer $sanitizer) {}
    public function getFilters(): array
    {
        return [new TwigFilter('author_biography', [$this->sanitizer, 'sanitize'], ['is_safe' => ['html']])];
    }
}
