<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Parser;
use Phpdftk\Svg\Value\Paint\CurrentColor;
use Phpdftk\Svg\Value\Paint\None_;
use Phpdftk\Svg\Value\Paint\SolidColor;
use Phpdftk\Svg\Value\Paint\Url;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the SVG 2 §13 presentation-attribute accessors on
 * `Element` — fill / stroke / opacity / stroke-* / fill-rule etc. These
 * resolve only the attribute on the element itself; CSS-style inheritance
 * lands in 3J.
 */
final class PresentationAttributeTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    /**
     * Convenience: parse a single rect with the given attributes and
     * return the rect for accessor probing.
     */
    private function rectWith(string $attrs): \Phpdftk\Svg\Shape\Rect
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect ' . $attrs . '/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Shape\Rect::class, $rect);
        return $rect;
    }

    public function testFillNoneKeyword(): void
    {
        self::assertInstanceOf(None_::class, $this->rectWith('fill="none"')->fill());
    }

    public function testFillCurrentColorKeyword(): void
    {
        self::assertInstanceOf(CurrentColor::class, $this->rectWith('fill="currentColor"')->fill());
    }

    public function testFillSolidColor(): void
    {
        self::assertInstanceOf(SolidColor::class, $this->rectWith('fill="red"')->fill());
    }

    public function testFillUrlReferenceWithFallback(): void
    {
        $paint = $this->rectWith('fill="url(#g) red"')->fill();
        self::assertInstanceOf(Url::class, $paint);
        self::assertSame('g', $paint->id);
        self::assertInstanceOf(SolidColor::class, $paint->fallback);
    }

    public function testStrokeIndependentOfFill(): void
    {
        $rect = $this->rectWith('fill="red" stroke="blue"');
        self::assertInstanceOf(SolidColor::class, $rect->fill());
        self::assertInstanceOf(SolidColor::class, $rect->stroke());
    }

    public function testFillAndStrokeAbsentReturnsNull(): void
    {
        $rect = $this->rectWith('');
        self::assertNull($rect->fill());
        self::assertNull($rect->stroke());
    }

    public function testFillOpacityClampedAboveOne(): void
    {
        self::assertSame(1.0, $this->rectWith('fill-opacity="1.5"')->fillOpacity());
    }

    public function testFillOpacityClampedBelowZero(): void
    {
        self::assertSame(0.0, $this->rectWith('fill-opacity="-0.5"')->fillOpacity());
    }

    public function testFillOpacityIntermediate(): void
    {
        self::assertSame(0.5, $this->rectWith('fill-opacity="0.5"')->fillOpacity());
    }

    public function testStrokeOpacityAndOpacityAccessorsClamp(): void
    {
        $rect = $this->rectWith('stroke-opacity="2" opacity="-1"');
        self::assertSame(1.0, $rect->strokeOpacity());
        self::assertSame(0.0, $rect->opacity());
    }

    public function testFillRuleRecognisedKeywords(): void
    {
        self::assertSame('nonzero', $this->rectWith('fill-rule="nonzero"')->fillRule());
        self::assertSame('evenodd', $this->rectWith('fill-rule="evenodd"')->fillRule());
    }

    public function testFillRuleUnknownValueIsNull(): void
    {
        self::assertNull($this->rectWith('fill-rule="weird"')->fillRule());
    }

    public function testStrokeWidthAcceptsPositiveAndRejectsNegative(): void
    {
        self::assertSame(2.5, $this->rectWith('stroke-width="2.5"')->strokeWidth());
        self::assertNull($this->rectWith('stroke-width="-1"')->strokeWidth());
    }

    public function testStrokeWidthStripsUnit(): void
    {
        self::assertSame(3.0, $this->rectWith('stroke-width="3px"')->strokeWidth());
    }

    public function testStrokeLinecapKeywords(): void
    {
        self::assertSame('butt', $this->rectWith('stroke-linecap="butt"')->strokeLinecap());
        self::assertSame('round', $this->rectWith('stroke-linecap="round"')->strokeLinecap());
        self::assertSame('square', $this->rectWith('stroke-linecap="square"')->strokeLinecap());
        self::assertNull($this->rectWith('stroke-linecap="diamond"')->strokeLinecap());
    }

    public function testStrokeLinejoinKeywords(): void
    {
        self::assertSame('miter', $this->rectWith('stroke-linejoin="miter"')->strokeLinejoin());
        self::assertSame('round', $this->rectWith('stroke-linejoin="round"')->strokeLinejoin());
        self::assertSame('bevel', $this->rectWith('stroke-linejoin="bevel"')->strokeLinejoin());
        self::assertSame('miter-clip', $this->rectWith('stroke-linejoin="miter-clip"')->strokeLinejoin());
        self::assertSame('arcs', $this->rectWith('stroke-linejoin="arcs"')->strokeLinejoin());
        self::assertNull($this->rectWith('stroke-linejoin="other"')->strokeLinejoin());
    }

    public function testStrokeMiterlimitMustBeAtLeastOne(): void
    {
        self::assertSame(10.0, $this->rectWith('stroke-miterlimit="10"')->strokeMiterlimit());
        self::assertNull($this->rectWith('stroke-miterlimit="0.5"')->strokeMiterlimit());
    }

    public function testStrokeDasharrayCommaAndSpaceSeparated(): void
    {
        self::assertSame(
            [5.0, 2.0, 3.0],
            $this->rectWith('stroke-dasharray="5, 2 3"')->strokeDasharray(),
        );
    }

    public function testStrokeDasharrayNoneAndAbsentReturnEmpty(): void
    {
        self::assertSame([], $this->rectWith('stroke-dasharray="none"')->strokeDasharray());
        self::assertSame([], $this->rectWith('')->strokeDasharray());
    }

    public function testStrokeDasharrayNegativeValueInvalidatesEntireList(): void
    {
        // SVG 2 §13.4: a single negative entry invalidates the whole
        // list — fall back to no dashes.
        self::assertSame([], $this->rectWith('stroke-dasharray="5 -1 3"')->strokeDasharray());
    }

    public function testStrokeDashoffsetAcceptsNegative(): void
    {
        self::assertSame(-2.5, $this->rectWith('stroke-dashoffset="-2.5"')->strokeDashoffset());
    }
}
