<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Parser;
use Phpdftk\Css\Value\FontFeatureSettings;
use Phpdftk\Css\Value\Keyword;
use PHPUnit\Framework\TestCase;

/**
 * CSS Fonts 4 §6.4 — `font-feature-settings` post-process at
 * declaration time, lifting the generic `<string> <int|on|off>`
 * list into typed FontFeatureSettings + FontFeatureValue.
 */
final class FontFeatureSettingsTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    private function valueOf(string $css): \Phpdftk\Css\Value\Value
    {
        $sheet = $this->parser->parseStylesheet("p { font-feature-settings: $css; }");
        $rule = $sheet->rules[0];
        assert($rule instanceof \Phpdftk\Css\Sheet\StyleRule);
        return $rule->declarations[0]->value;
    }

    public function testBareTagDefaultsToOne(): void
    {
        $v = $this->valueOf('"tnum"');
        self::assertInstanceOf(FontFeatureSettings::class, $v);
        self::assertCount(1, $v->features);
        self::assertSame('tnum', $v->features[0]->tag);
        self::assertSame(1, $v->features[0]->value);
    }

    public function testTagWithIntegerValue(): void
    {
        $v = $this->valueOf('"ss01" 1');
        self::assertInstanceOf(FontFeatureSettings::class, $v);
        self::assertSame('ss01', $v->features[0]->tag);
        self::assertSame(1, $v->features[0]->value);
    }

    public function testTagWithOnKeyword(): void
    {
        $v = $this->valueOf('"liga" on');
        self::assertInstanceOf(FontFeatureSettings::class, $v);
        self::assertSame(1, $v->features[0]->value);
    }

    public function testTagWithOffKeyword(): void
    {
        $v = $this->valueOf('"liga" off');
        self::assertInstanceOf(FontFeatureSettings::class, $v);
        self::assertSame(0, $v->features[0]->value);
    }

    public function testCommaSeparatedList(): void
    {
        $v = $this->valueOf('"tnum", "liga" off, "ss01" 1');
        self::assertInstanceOf(FontFeatureSettings::class, $v);
        self::assertCount(3, $v->features);
        self::assertSame('tnum', $v->features[0]->tag);
        self::assertSame(1, $v->features[0]->value);
        self::assertSame('liga', $v->features[1]->tag);
        self::assertSame(0, $v->features[1]->value);
        self::assertSame('ss01', $v->features[2]->tag);
        self::assertSame(1, $v->features[2]->value);
    }

    public function testNormalPassesThroughUntouched(): void
    {
        $v = $this->valueOf('normal');
        self::assertInstanceOf(Keyword::class, $v);
        self::assertSame('normal', $v->name);
    }

    public function testRoundTrip(): void
    {
        $v = $this->valueOf('"tnum", "liga" off');
        self::assertInstanceOf(FontFeatureSettings::class, $v);
        self::assertSame('"tnum" 1, "liga" 0', $v->toCss());
    }

    private function variationValueOf(string $css): \Phpdftk\Css\Value\Value
    {
        $sheet = $this->parser->parseStylesheet("p { font-variation-settings: $css; }");
        $rule = $sheet->rules[0];
        assert($rule instanceof \Phpdftk\Css\Sheet\StyleRule);
        return $rule->declarations[0]->value;
    }

    public function testVariationSettingsSingleAxis(): void
    {
        $v = $this->variationValueOf('"wght" 600');
        self::assertInstanceOf(\Phpdftk\Css\Value\FontVariationSettings::class, $v);
        self::assertCount(1, $v->axes);
        self::assertSame('wght', $v->axes[0]->tag);
        self::assertSame(600.0, $v->axes[0]->value);
    }

    public function testVariationSettingsDecimalValue(): void
    {
        $v = $this->variationValueOf('"wdth" 95.5');
        self::assertInstanceOf(\Phpdftk\Css\Value\FontVariationSettings::class, $v);
        self::assertSame(95.5, $v->axes[0]->value);
    }

    public function testVariationSettingsMultipleAxes(): void
    {
        $v = $this->variationValueOf('"wght" 600, "wdth" 95.5, "slnt" -10');
        self::assertInstanceOf(\Phpdftk\Css\Value\FontVariationSettings::class, $v);
        self::assertCount(3, $v->axes);
        self::assertSame('slnt', $v->axes[2]->tag);
        self::assertSame(-10.0, $v->axes[2]->value);
    }

    public function testVariationSettingsNormalPassesThrough(): void
    {
        $v = $this->variationValueOf('normal');
        self::assertInstanceOf(Keyword::class, $v);
    }

    public function testVariationSettingsRoundTrip(): void
    {
        $v = $this->variationValueOf('"wght" 600, "wdth" 95.5');
        self::assertInstanceOf(\Phpdftk\Css\Value\FontVariationSettings::class, $v);
        self::assertSame('"wght" 600, "wdth" 95.5', $v->toCss());
    }
}
