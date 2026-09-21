<?php

namespace App\Form\DataTransformer;

use App\Service\JsonLdNormalizer;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/** @implements DataTransformerInterface<string|null, string> */
final class JsonLdMarkupTransformer implements DataTransformerInterface
{
    public function __construct(private readonly JsonLdNormalizer $normalizer)
    {
    }

    public function transform(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Keep a legacy value visible to an administrator so it can be fixed,
        // while public rendering remains protected by JsonLdNormalizer.
        return $this->normalizer->normalize((string) $value) ?? (string) $value;
    }

    public function reverseTransform(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalized = $this->normalizer->normalize($value);
        if ($normalized === null) {
            throw new TransformationFailedException('Schema Markup phải là JSON-LD hợp lệ (object hoặc mảng JSON), không dán thẻ <script>.');
        }

        return $normalized;
    }
}
