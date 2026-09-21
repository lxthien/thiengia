<?php

namespace App\Tests\Service;

use App\Service\ContentMetrics;
use PHPUnit\Framework\TestCase;

final class ContentMetricsTest extends TestCase
{
    public function testItCountsVietnameseWordsInHtml(): void
    {
        $metrics = new ContentMetrics();

        self::assertSame(6, $metrics->countWords('<p>Thiết kế <strong>nhà phố</strong> năm 2026.</p>'));
    }

    public function testItCalculatesReadingTimeFromUnicodeWords(): void
    {
        $metrics = new ContentMetrics();

        self::assertSame(2, $metrics->readingMinutes(str_repeat('từ ', 201)));
        self::assertSame(0, $metrics->readingMinutes(''));
    }
}
