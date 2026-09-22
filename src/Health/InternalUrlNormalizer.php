<?php

namespace App\Health;

final class InternalUrlNormalizer
{
    public static function normalize(string $url, string $localHost): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || $url[0] === '#' || $url[0] === '?' || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (isset($parts['host'])) {
            if ($localHost === '' || strtolower(rtrim($parts['host'], '.')) !== strtolower(rtrim($localHost, '.'))) {
                return null;
            }
        } elseif ($scheme !== '' || str_starts_with($url, '//')) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        return '/' . ltrim(rawurldecode($path), '/');
    }
}
