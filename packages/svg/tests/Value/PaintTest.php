<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests\Value;

use Phpdftk\Color\RgbColor;
use Phpdftk\Svg\Value\Paint;
use Phpdftk\Svg\Value\Paint\CurrentColor;
use Phpdftk\Svg\Value\Paint\None_;
use Phpdftk\Svg\Value\Paint\SolidColor;
use Phpdftk\Svg\Value\Paint\Url;
use PHPUnit\Framework\TestCase;

final class PaintTest extends TestCase
{
    public function testEmptyInputReturnsNull(): void
    {
        self::assertNull(Paint::parse(''));
        self::assertNull(Paint::parse('   '));
    }

    public function testParsesNoneKeyword(): void
    {
        self::assertInstanceOf(None_::class, Paint::parse('none'));
    }

    public function testNoneKeywordIsCaseInsensitive(): void
    {
        // CSS keywords are case-insensitive; SVG follows.
        self::assertInstanceOf(None_::class, Paint::parse('None'));
    }

    public function testParsesCurrentColorKeyword(): void
    {
        self::assertInstanceOf(CurrentColor::class, Paint::parse('currentColor'));
        self::assertInstanceOf(CurrentColor::class, Paint::parse('CURRENTCOLOR'));
    }

    public function testParsesHexAsSolidColor(): void
    {
        $p = Paint::parse('#ff0000');
        self::assertInstanceOf(SolidColor::class, $p);
        self::assertInstanceOf(RgbColor::class, $p->color);
    }

    public function testParsesNamedColorAsSolidColor(): void
    {
        $p = Paint::parse('red');
        self::assertInstanceOf(SolidColor::class, $p);
    }

    public function testParsesUrlReference(): void
    {
        $p = Paint::parse('url(#gradient1)');
        self::assertInstanceOf(Url::class, $p);
        self::assertSame('gradient1', $p->id);
        self::assertNull($p->fallback);
    }

    public function testParsesUrlReferenceWithColorFallback(): void
    {
        $p = Paint::parse('url(#missing) red');
        self::assertInstanceOf(Url::class, $p);
        self::assertSame('missing', $p->id);
        self::assertInstanceOf(SolidColor::class, $p->fallback);
    }

    public function testParsesUrlReferenceWithNoneFallback(): void
    {
        $p = Paint::parse('url(#missing) none');
        self::assertInstanceOf(Url::class, $p);
        self::assertInstanceOf(None_::class, $p->fallback);
    }

    public function testUrlFallbackOfAnotherUrlIsStrippedPerSpec(): void
    {
        // SVG 2 §13.2 grammar: fallback is `none | <color>`, not another
        // url. Real-world strict parsers reject the whole thing; we
        // preserve the leading url and drop the chained fallback.
        $p = Paint::parse('url(#a) url(#b)');
        self::assertInstanceOf(Url::class, $p);
        self::assertSame('a', $p->id);
        self::assertNull($p->fallback);
    }

    public function testMalformedReturnsNull(): void
    {
        self::assertNull(Paint::parse('definitely-not-a-paint'));
    }
}
