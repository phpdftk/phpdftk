<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests\Gradient;

use Phpdftk\Color\RgbColor;
use Phpdftk\Svg\Gradient\LinearGradient;
use Phpdftk\Svg\Gradient\RadialGradient;
use Phpdftk\Svg\Gradient\Stop;
use Phpdftk\Svg\Parser;
use PHPUnit\Framework\TestCase;

final class GradientTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testLinearGradientDefaultsAndEndpoints(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<linearGradient x1="0" y1="0" x2="100" y2="100"/></svg>',
        );
        $grad = $doc->children[0];
        self::assertInstanceOf(LinearGradient::class, $grad);
        self::assertSame(0.0, $grad->x1());
        self::assertSame(0.0, $grad->y1());
        self::assertSame(100.0, $grad->x2());
        self::assertSame(100.0, $grad->y2());
        self::assertSame('objectBoundingBox', $grad->gradientUnits());
        self::assertSame('pad', $grad->spreadMethod());
        self::assertNull($grad->gradientTransform());
    }

    public function testLinearGradientEndpointsNullWhenAbsent(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><linearGradient/></svg>',
        );
        $grad = $doc->children[0];
        self::assertInstanceOf(LinearGradient::class, $grad);
        self::assertNull($grad->x1());
        self::assertNull($grad->y1());
        self::assertNull($grad->x2());
        self::assertNull($grad->y2());
    }

    public function testGradientUnitsExplicit(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<linearGradient gradientUnits="userSpaceOnUse"/></svg>',
        );
        $grad = $doc->children[0];
        self::assertInstanceOf(LinearGradient::class, $grad);
        self::assertSame('userSpaceOnUse', $grad->gradientUnits());
    }

    public function testGradientUnitsUnknownFallsBackToDefault(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<linearGradient gradientUnits="weird"/></svg>',
        );
        $grad = $doc->children[0];
        self::assertInstanceOf(LinearGradient::class, $grad);
        self::assertSame('objectBoundingBox', $grad->gradientUnits());
    }

    public function testSpreadMethodAcceptsAllThree(): void
    {
        foreach (['pad', 'reflect', 'repeat'] as $method) {
            $doc = $this->parser->parse(
                '<svg xmlns="http://www.w3.org/2000/svg">'
                . sprintf('<linearGradient spreadMethod="%s"/></svg>', $method),
            );
            $grad = $doc->children[0];
            self::assertInstanceOf(LinearGradient::class, $grad);
            self::assertSame($method, $grad->spreadMethod());
        }
    }

    public function testGradientTransformParsesViaTransformAst(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<linearGradient gradientTransform="rotate(45) translate(10, 20)"/></svg>',
        );
        $grad = $doc->children[0];
        self::assertInstanceOf(LinearGradient::class, $grad);
        $t = $grad->gradientTransform();
        self::assertNotNull($t);
        self::assertCount(2, $t->functions);
    }

    public function testRadialGradientFullSurface(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<radialGradient cx="50" cy="50" r="40" fx="30" fy="30" fr="5"/></svg>',
        );
        $grad = $doc->children[0];
        self::assertInstanceOf(RadialGradient::class, $grad);
        self::assertSame(50.0, $grad->cx());
        self::assertSame(50.0, $grad->cy());
        self::assertSame(40.0, $grad->r());
        self::assertSame(30.0, $grad->fx());
        self::assertSame(30.0, $grad->fy());
        self::assertSame(5.0, $grad->fr());
    }

    public function testRadialGradientNegativeRadiiRejected(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<radialGradient r="-1" fr="-1"/></svg>',
        );
        $grad = $doc->children[0];
        self::assertInstanceOf(RadialGradient::class, $grad);
        self::assertNull($grad->r());
        self::assertNull($grad->fr());
    }

    public function testStopOffsetNumericAndPercentage(): void
    {
        $stop1 = new Stop();
        $stop1->setAttribute('offset', '0.5');
        $stop2 = new Stop();
        $stop2->setAttribute('offset', '50%');
        self::assertSame(0.5, $stop1->offset());
        self::assertSame(0.5, $stop2->offset());
    }

    public function testStopOffsetClampedToZeroOneRange(): void
    {
        $stop = new Stop();
        $stop->setAttribute('offset', '-0.5');
        self::assertSame(0.0, $stop->offset());
        $stop->setAttribute('offset', '150%');
        self::assertSame(1.0, $stop->offset());
    }

    public function testStopOffsetMissingDefaultsToZero(): void
    {
        $stop = new Stop();
        self::assertSame(0.0, $stop->offset());
    }

    public function testStopColorParsesViaColorAst(): void
    {
        $stop = new Stop();
        $stop->setAttribute('stop-color', '#ff0000');
        $color = $stop->stopColor();
        self::assertInstanceOf(RgbColor::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testStopColorAbsentReturnsNull(): void
    {
        $stop = new Stop();
        self::assertNull($stop->stopColor());
    }

    public function testStopOpacityClampedAndNullable(): void
    {
        $stop = new Stop();
        self::assertNull($stop->stopOpacity());
        $stop->setAttribute('stop-opacity', '0.25');
        self::assertSame(0.25, $stop->stopOpacity());
        $stop->setAttribute('stop-opacity', '2');
        self::assertSame(1.0, $stop->stopOpacity());
        $stop->setAttribute('stop-opacity', '-1');
        self::assertSame(0.0, $stop->stopOpacity());
    }

    public function testGradientStopsReturnsOwnChildren(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><linearGradient id="g">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</linearGradient></svg>',
        );
        $grad = $doc->children[0];
        self::assertInstanceOf(LinearGradient::class, $grad);
        $stops = $grad->stops($doc);
        self::assertCount(2, $stops);
        self::assertSame(0.0, $stops[0]->offset());
        self::assertSame(1.0, $stops[1]->offset());
    }

    public function testGradientStopsInheritsViaHrefChain(): void
    {
        // `b` has no stops; it inherits from `a` via href. SVG 2 §13.4.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<linearGradient id="a">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</linearGradient>'
            . '<linearGradient id="b" href="#a"/>'
            . '</svg>',
        );
        $b = $doc->children[1];
        self::assertInstanceOf(LinearGradient::class, $b);
        $stops = $b->stops($doc);
        self::assertCount(2, $stops);
    }

    public function testGradientStopsHrefChainStopsAtCycle(): void
    {
        // `#a` → `#b` → `#a` — neither has its own stops; the visited
        // set breaks the loop, we return [].
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<linearGradient id="a" href="#b"/>'
            . '<linearGradient id="b" href="#a"/>'
            . '</svg>',
        );
        $a = $doc->children[0];
        self::assertInstanceOf(LinearGradient::class, $a);
        self::assertSame([], $a->stops($doc));
    }

    public function testGradientStopsExternalHrefIgnored(): void
    {
        // No implicit cross-document loads — `external.svg#a` ignored.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<linearGradient href="external.svg#a"/>'
            . '</svg>',
        );
        $grad = $doc->children[0];
        self::assertInstanceOf(LinearGradient::class, $grad);
        self::assertNull($grad->href());
        self::assertSame([], $grad->stops($doc));
    }

    public function testGradientFallsBackToXlinkHref(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<linearGradient id="a"><stop offset="0" stop-color="red"/></linearGradient>'
            . '<linearGradient id="b" xlink:href="#a"/>'
            . '</svg>',
        );
        $b = $doc->children[1];
        self::assertInstanceOf(LinearGradient::class, $b);
        self::assertCount(1, $b->stops($doc));
    }
}
