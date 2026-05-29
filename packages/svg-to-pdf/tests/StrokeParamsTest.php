<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * Stroke parameter lowering (SVG 2 §13.4 → PDF graphics-state ops):
 *
 *  - `stroke-width` → `w`
 *  - `stroke-linecap` → `J`
 *  - `stroke-linejoin` → `j`
 *  - `stroke-miterlimit` → `M`
 *  - `stroke-dasharray` / `stroke-dashoffset` → `d`
 *
 * Each scope-leaking parameter triggers a `q`/`Q` wrap so it doesn't
 * contaminate sibling shapes.
 */
final class StrokeParamsTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    private function paint(string $svg): string
    {
        $doc = $this->svgParser->parse($svg);
        $stream = new ContentStream();
        $this->translator->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testStrokeWidthEmitsWop(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" stroke="black" stroke-width="3"/></svg>',
        );
        self::assertStringContainsString('3 w', $ops);
    }

    public function testLineCapMapsToJOperator(): void
    {
        $butt = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<line x1="0" y1="0" x2="10" y2="0" stroke="black" stroke-linecap="butt"/></svg>',
        );
        $round = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<line x1="0" y1="0" x2="10" y2="0" stroke="black" stroke-linecap="round"/></svg>',
        );
        $square = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<line x1="0" y1="0" x2="10" y2="0" stroke="black" stroke-linecap="square"/></svg>',
        );
        self::assertStringContainsString('0 J', $butt);
        self::assertStringContainsString('1 J', $round);
        self::assertStringContainsString('2 J', $square);
    }

    public function testLineJoinMapsToJoperator(): void
    {
        $miter = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" stroke="black" stroke-linejoin="miter"/></svg>',
        );
        $round = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" stroke="black" stroke-linejoin="round"/></svg>',
        );
        $bevel = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" stroke="black" stroke-linejoin="bevel"/></svg>',
        );
        self::assertStringContainsString('0 j', $miter);
        self::assertStringContainsString('1 j', $round);
        self::assertStringContainsString('2 j', $bevel);
    }

    public function testMiterLimitEmitsMOperator(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" stroke="black" stroke-miterlimit="8"/></svg>',
        );
        self::assertStringContainsString('8 M', $ops);
    }

    public function testDasharrayEmitsDOperator(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" stroke="black" stroke-dasharray="5 3"/></svg>',
        );
        // d operator format: `[ <values> ] <phase> d`.
        self::assertStringContainsString('[ 5 3 ] 0 d', $ops);
    }

    public function testStrokeParamsWrapInQQ(): void
    {
        // Confirms the scope-leak guard: a shape with stroke params
        // wraps in `q`/`Q` so the params don't bleed into a following
        // shape that doesn't set them.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" stroke="black" stroke-width="2"/>'
            . '<rect width="10" height="10" stroke="black"/>'
            . '</svg>',
        );
        $lines = explode("\n", $ops);
        $qCount = count(array_filter($lines, static fn(string $l): bool => $l === 'q'));
        $bigQCount = count(array_filter($lines, static fn(string $l): bool => $l === 'Q'));
        self::assertSame(1, $qCount);
        self::assertSame(1, $bigQCount);
    }

    public function testDefaultsDoNotEmitStrokeParams(): void
    {
        // A bare stroked shape with no explicit params shouldn't grow
        // any of the graphics-state ops.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" stroke="black"/></svg>',
        );
        self::assertStringNotContainsString(' w', $ops);
        self::assertStringNotContainsString(' J', $ops);
        self::assertStringNotContainsString(' j', $ops);
        self::assertStringNotContainsString(' M', $ops);
        self::assertStringNotContainsString(' d', $ops);
    }
}
