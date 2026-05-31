<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\DeviceCmyk;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Color 5 §6 — `device-cmyk()` typed parser. Preserves
 * the original CMYK quartet so the PDF writer can emit it via
 * /DeviceCMYK rather than round-trip through sRGB.
 */
final class DeviceCmykTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    public function testNumericComponents(): void
    {
        $v = $this->parser->parseFromString('device-cmyk(0 1 1 0)');
        self::assertInstanceOf(DeviceCmyk::class, $v);
        self::assertSame(0.0, $v->c);
        self::assertSame(1.0, $v->m);
        self::assertSame(1.0, $v->y);
        self::assertSame(0.0, $v->k);
        self::assertSame(1.0, $v->alpha);
        self::assertNull($v->fallback);
    }

    public function testPercentageComponents(): void
    {
        $v = $this->parser->parseFromString('device-cmyk(20% 0% 100% 10%)');
        self::assertInstanceOf(DeviceCmyk::class, $v);
        self::assertEqualsWithDelta(0.2, $v->c, 1e-6);
        self::assertSame(0.0, $v->m);
        self::assertSame(1.0, $v->y);
        self::assertEqualsWithDelta(0.1, $v->k, 1e-6);
    }

    public function testAlphaSlash(): void
    {
        $v = $this->parser->parseFromString('device-cmyk(0 1 1 0 / 50%)');
        self::assertInstanceOf(DeviceCmyk::class, $v);
        self::assertSame(0.5, $v->alpha);
    }

    public function testAlphaSlashNumber(): void
    {
        $v = $this->parser->parseFromString('device-cmyk(0 1 1 0 / 0.25)');
        self::assertInstanceOf(DeviceCmyk::class, $v);
        self::assertSame(0.25, $v->alpha);
    }

    public function testRgbFallback(): void
    {
        $v = $this->parser->parseFromString('device-cmyk(0 1 1 0, #ff0000)');
        self::assertInstanceOf(DeviceCmyk::class, $v);
        self::assertInstanceOf(Color::class, $v->fallback);
        self::assertSame(1.0, $v->fallback->r);
    }

    public function testTooFewComponentsRejected(): void
    {
        $v = $this->parser->parseFromString('device-cmyk(0 1 1)');
        self::assertNotInstanceOf(DeviceCmyk::class, $v);
        self::assertInstanceOf(CssFunction::class, $v);
    }

    public function testTooManyComponentsRejected(): void
    {
        $v = $this->parser->parseFromString('device-cmyk(0 1 1 0 0)');
        self::assertNotInstanceOf(DeviceCmyk::class, $v);
    }

    public function testNonNumericComponentRejected(): void
    {
        $v = $this->parser->parseFromString('device-cmyk(0 1 1 foo)');
        self::assertNotInstanceOf(DeviceCmyk::class, $v);
    }

    public function testNegativeComponentClampedToZero(): void
    {
        $v = $this->parser->parseFromString('device-cmyk(-0.5 1 1 0)');
        self::assertInstanceOf(DeviceCmyk::class, $v);
        self::assertSame(0.0, $v->c);
    }

    public function testRoundTrip(): void
    {
        $v = $this->parser->parseFromString('device-cmyk(0 1 1 0)');
        self::assertInstanceOf(DeviceCmyk::class, $v);
        self::assertSame('device-cmyk(0 1 1 0)', $v->toCss());
    }

    public function testRoundTripWithAlpha(): void
    {
        $v = $this->parser->parseFromString('device-cmyk(0 1 1 0 / 0.5)');
        self::assertInstanceOf(DeviceCmyk::class, $v);
        self::assertSame('device-cmyk(0 1 1 0 / 0.5)', $v->toCss());
    }
}
