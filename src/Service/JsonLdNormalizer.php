<?php

namespace App\Service;

/**
 * Turns editor supplied structured data into safe, canonical JSON-LD.
 *
 * A schema override must never be indistinguishable from arbitrary executable
 * JavaScript. Legacy script-tag values are accepted on read, but only their
 * JSON payload is retained and emitted.
 */
final class JsonLdNormalizer
{
    public function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('~^<script\\b[^>]*>(.*)</script>$~is', $value, $matches)) {
            $value = trim($matches[1]);
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        try {
            return json_encode(
                $decoded,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT,
            );
        } catch (\JsonException) {
            return null;
        }
    }
}
