<?php

namespace App\Twig;

use App\Service\JsonLdNormalizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class JsonLdExtension extends AbstractExtension
{
    public function __construct(private readonly JsonLdNormalizer $normalizer)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('safe_json_ld', [$this, 'normalize'], ['is_safe' => ['html']]),
        ];
    }

    public function normalize(?string $value): string
    {
        return $this->normalizer->normalize($value) ?? '';
    }
}
