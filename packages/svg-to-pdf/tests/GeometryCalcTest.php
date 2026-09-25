<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §6.7 / §10.1 — a geometry presentation attribute is parsed as
 * a CSS value, so the CSS math functions are available in it:
 * `<rect x="calc(80px + 10% - 2em)">` is legal.
 *
 * `geometryLength()` only matched a bare `number + unit`, so any
 * `calc()` returned null, every coordinate fell back to its initial 0,
 * and the shape vanished entirely.
 */
final class GeometryCalcTest extends TestCase
{
    private function paint(string $body, int $w = 300, int $h = 200): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '">'
            . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testCalcResolvesPixelsPercentagesAndEmInARect(): void
    {
        // 80 + 10 % of 300 − 2 × 40 = 30; 80 + 30 % of 200 − 80 = 60;
        // width 8 × 5 % of 300 = 120; height 10 × 5 % of 200 = 100.
        $ops = $this->paint(
            '<style>rect { fill: blue; font-size: 40px; }</style>'
            . '<rect x="calc(80px + 10% - 2em)" y="calc(80px + 30% - 2em)"'
            . ' width="calc(8 * 5%)" height="calc(10 * 5%)"/>',
        );
        self::assertStringContainsString('30 60 120 100 re', $ops);
    }

    public function testPercentagesInsideCalcUseTheSamePerAxisBasis(): void
    {
        // x against the viewport WIDTH, y against its HEIGHT.
        $ops = $this->paint(
            '<rect x="calc(50%)" y="calc(50%)" width="10" height="10" fill="blue"/>',
        );
        self::assertStringContainsString('150 100 10 10 re', $ops);
    }

    public function testCircleRadiusCalcResolvesAgainstTheNormalizedDiagonal(): void
    {
        // §10.1 — a percentage `r` resolves against the normalized
        // diagonal, not either axis: 25 % of sqrt(340²+140²)/sqrt(2) = 65.
        $ops = $this->paint(
            '<circle cx="100" cy="70" r="calc(5 * 5%)" fill="blue"/>',
            340,
            140,
        );
        // The painter approximates the circle with four Béziers starting
        // at (cx + r, cy).
        self::assertStringContainsString('165 70 m', $ops);
    }

    public function testMinMaxAndClampAreAcceptedToo(): void
    {
        $ops = $this->paint(
            '<rect x="max(10px, 20px)" y="min(30px, 40px)"'
            . ' width="clamp(5px, 1000px, 50px)" height="10" fill="blue"/>',
        );
        self::assertStringContainsString('20 30 50 10 re', $ops);
    }

    public function testAPlainLengthStillTakesTheFastPath(): void
    {
        // Guard: the overwhelmingly common case must not start paying
        // for a full CSS value parse.
        $ops = $this->paint('<rect x="10" y="20" width="30" height="40" fill="blue"/>');
        self::assertStringContainsString('10 20 30 40 re', $ops);
    }

    public function testAnUnresolvableMathFunctionLeavesTheInitialValue(): void
    {
        // Guard: `sin()` is out of the evaluator's scope, so it must
        // read as an invalid declaration (initial value 0) rather than
        // as some half-evaluated number.
        $ops = $this->paint(
            '<rect x="sin(45deg)" y="0" width="30" height="40" fill="blue"/>',
        );
        self::assertStringContainsString('0 0 30 40 re', $ops);
    }

    public function testCalcOnAnEllipseRadiusResolvesPerAxis(): void
    {
        $ops = $this->paint(
            '<ellipse cx="0" cy="0" rx="calc(10%)" ry="calc(10%)" fill="blue"/>',
        );
        // rx = 10 % of 300 = 30, ry = 10 % of 200 = 20: the first point
        // of the Bézier ellipse is (cx + rx, cy).
        self::assertStringContainsString('30 0 m', $ops);
        self::assertStringContainsString('30 11.04', $ops);
    }
}
