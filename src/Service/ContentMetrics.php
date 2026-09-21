<?php

namespace App\Service;

/**
 * Computes editorial metrics from HTML content.
 *
 * PHP's str_word_count() only understands ASCII/Latin words, which made the
 * previous SEO score consider most Vietnamese articles to be empty or thin.
 */
final class ContentMetrics
{
    private const WORDS_PER_MINUTE = 200;

    public function countWords(?string $html): int
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $text) ?? '';

        preg_match_all('/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u', $text, $matches);

        return count($matches[0]);
    }

    public function readingMinutes(?string $html): int
    {
        return $this->readingMinutesFromWordCount($this->countWords($html));
    }

    public function readingMinutesFromWordCount(int $wordCount): int
    {
        if ($wordCount <= 0) {
            return 0;
        }

        return max(1, (int) ceil($wordCount / self::WORDS_PER_MINUTE));
    }
}
