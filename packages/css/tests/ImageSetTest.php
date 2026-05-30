<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\ImageSet;
use Phpdftk\Css\Value\StringValue;
use Phpdftk\Css\Value\Url;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Images 4 §6 — `image-set()` parsing. Stores the option
 * list as ImageSet + ImageSetOption value objects. Selection
 * algorithm (DPR + format negotiation) runs once the resource
 * loader has the target DPR.
 */
final class ImageSetTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    private function parseSet(string $css): ImageSet
    {
        $value = $this->parser->parseFromString($css);
        self::assertInstanceOf(ImageSet::class, $value, "expected ImageSet, got " . get_debug_type($value));
        return $value;
    }

    // -----------------------------------------------------------------------
    // Basic
    // -----------------------------------------------------------------------

    public function testStringAndResolution(): void
    {
        $set = $this->parseSet('image-set("foo.png" 1x, "foo-2x.png" 2x)');
        self::assertCount(2, $set->options);
        self::assertInstanceOf(StringValue::class, $set->options[0]->image);
        self::assertSame('foo.png', $set->options[0]->image->value);
        self::assertSame(1.0, $set->options[0]->resolutionDppx);
        self::assertSame(2.0, $set->options[1]->resolutionDppx);
    }

    public function testUrlAndResolution(): void
    {
        $set = $this->parseSet('image-set(url(foo.png) 1x, url(foo-2x.png) 2x)');
        self::assertCount(2, $set->options);
        self::assertInstanceOf(Url::class, $set->options[0]->image);
        self::assertSame('foo.png', $set->options[0]->image->url);
    }

    public function testResolutionWithoutSourceIsInvalid(): void
    {
        $value = $this->parser->parseFromString('image-set(1x)');
        self::assertInstanceOf(CssFunction::class, $value);
    }

    public function testWithoutResolutionDefaultsToNull(): void
    {
        $set = $this->parseSet('image-set("foo.png", "bar.png")');
        self::assertNull($set->options[0]->resolutionDppx);
        self::assertNull($set->options[1]->resolutionDppx);
    }

    // -----------------------------------------------------------------------
    // Resolution units (CSS Values 4 §6.6.1)
    // -----------------------------------------------------------------------

    public function testDppxUnit(): void
    {
        $set = $this->parseSet('image-set("foo.png" 1.5dppx)');
        self::assertSame(1.5, $set->options[0]->resolutionDppx);
    }

    public function testDpiUnit(): void
    {
        // 192 dpi = 192 / 96 = 2 dppx
        $set = $this->parseSet('image-set("foo.png" 192dpi)');
        self::assertEqualsWithDelta(2.0, $set->options[0]->resolutionDppx, 1e-9);
    }

    public function testDpcmUnit(): void
    {
        // 1 dpcm = 2.54 dpi → 2.54 / 96 dppx
        $set = $this->parseSet('image-set("foo.png" 96dpcm)');
        self::assertEqualsWithDelta(2.54, $set->options[0]->resolutionDppx, 1e-6);
    }

    // -----------------------------------------------------------------------
    // type(<mime>)
    // -----------------------------------------------------------------------

    public function testTypeMime(): void
    {
        $set = $this->parseSet('image-set("foo.svg" type("image/svg+xml"), "foo.png" type("image/png"))');
        self::assertCount(2, $set->options);
        self::assertSame('image/svg+xml', $set->options[0]->mimeType);
        self::assertSame('image/png', $set->options[1]->mimeType);
    }

    public function testResolutionAndType(): void
    {
        $set = $this->parseSet('image-set("foo.avif" 2x type("image/avif"))');
        self::assertSame(2.0, $set->options[0]->resolutionDppx);
        self::assertSame('image/avif', $set->options[0]->mimeType);
    }

    // -----------------------------------------------------------------------
    // Webkit prefix
    // -----------------------------------------------------------------------

    public function testWebkitPrefixedFormParses(): void
    {
        $set = $this->parseSet('-webkit-image-set("foo.png" 1x, "foo-2x.png" 2x)');
        self::assertCount(2, $set->options);
    }

    // -----------------------------------------------------------------------
    // Malformed input
    // -----------------------------------------------------------------------

    public function testEmptyArgsFallsThrough(): void
    {
        $value = $this->parser->parseFromString('image-set()');
        self::assertInstanceOf(CssFunction::class, $value);
    }

    public function testInvalidResolutionUnitFallsThrough(): void
    {
        // `2px` isn't a resolution.
        $value = $this->parser->parseFromString('image-set("foo.png" 2px)');
        self::assertNotInstanceOf(ImageSet::class, $value);
    }
}
