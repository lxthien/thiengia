<?php

namespace App\Tests\Service;

use App\Service\JsonLdNormalizer;
use PHPUnit\Framework\TestCase;

final class JsonLdNormalizerTest extends TestCase
{
    public function testItCanonicalizesPlainJsonLd(): void
    {
        $normalizer = new JsonLdNormalizer();

        self::assertSame(
            '{"@context":"https://schema.org","@type":"WebPage","name":"Thiện Gia"}',
            $normalizer->normalize('{"@context":"https://schema.org","@type":"WebPage","name":"Thiện Gia"}'),
        );
    }

    public function testItSafelyMigratesLegacyScriptTagMarkup(): void
    {
        $normalizer = new JsonLdNormalizer();

        self::assertSame(
            '{"@context":"https://schema.org","@type":"WebPage"}',
            $normalizer->normalize('<script type="application/ld+json">{"@context":"https://schema.org","@type":"WebPage"}</script>'),
        );
    }

    public function testItRejectsExecutableMarkupAndScalarJson(): void
    {
        $normalizer = new JsonLdNormalizer();

        self::assertNull($normalizer->normalize('<script>alert(1)</script>'));
        self::assertNull($normalizer->normalize('"not a schema object"'));
    }

    public function testItEscapesScriptBreakingCharacters(): void
    {
        $normalizer = new JsonLdNormalizer();
        $normalized = $normalizer->normalize('{"@context":"https://schema.org","name":"</script><img src=x>"}');

        self::assertStringContainsString('\\u003C/script\\u003E', (string) $normalized);
        self::assertStringNotContainsString('</script>', (string) $normalized);
    }
}
