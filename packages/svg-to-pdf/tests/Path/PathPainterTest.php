<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests\Path;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * Operator-level tests for the 3L `<path>` painter — each SVG command
 * lowered to its PDF operator equivalent. PDF lacks native quadratic and
 * arc operators, so those go through a documented conversion.
 */
final class PathPainterTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    private function paint(string $d, string $extraAttrs = 'fill="black"'): string
    {
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg"><path d="%s" %s/></svg>',
            $d,
            $extraAttrs,
        );
        $doc = $this->svgParser->parse($svg);
        $stream = new ContentStream();
        $this->translator->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testEmptyPathDataEmitsNothing(): void
    {
        self::assertSame('', $this->paint(''));
    }

    public function testMoveLineCloseEmitsBasicOperators(): void
    {
        $ops = $this->paint('M 10 20 L 30 40 Z');
        self::assertStringContainsString('10 20 m', $ops);
        self::assertStringContainsString('30 40 l', $ops);
        self::assertStringContainsString("\nh", $ops);
        self::assertStringContainsString("\nf", $ops);
    }

    public function testRelativeMoveTreatsFirstAsAbsolute(): void
    {
        // Per SVG 2 §9.3.4 a leading `m` is treated as `M` (no previous
        // point to be relative to). Painter mirrors the parser: it
        // resolves relative-to-(0,0) for a first move when state is at
        // the origin.
        $ops = $this->paint('m 10 20 l 5 5');
        self::assertStringContainsString('10 20 m', $ops);
        // l 5 5 → absolute (15, 25).
        self::assertStringContainsString('15 25 l', $ops);
    }

    public function testHorizontalAndVerticalLineTo(): void
    {
        $ops = $this->paint('M 10 10 H 50 V 100');
        self::assertStringContainsString('50 10 l', $ops);
        self::assertStringContainsString('50 100 l', $ops);
    }

    public function testRelativeHorizontalAndVerticalLineTo(): void
    {
        $ops = $this->paint('M 10 10 h 40 v 90');
        // h 40 from (10,10) → (50,10); v 90 from (50,10) → (50,100).
        self::assertStringContainsString('50 10 l', $ops);
        self::assertStringContainsString('50 100 l', $ops);
    }

    public function testCubicCurveTo(): void
    {
        $ops = $this->paint('M 0 0 C 10 20 30 20 40 0');
        // PDF cubic operator is `c`: x1 y1 x2 y2 x3 y3 c.
        self::assertStringContainsString('10 20 30 20 40 0 c', $ops);
    }

    public function testSmoothCubicReflectsPreviousControlPoint(): void
    {
        // `C 10 0 0 10 10 10` ends at (10,10) with last control (0,10).
        // `S 30 0 40 10` then synthesises first control as
        // reflection-about-(10,10) of (0,10) = (20, 10).
        $ops = $this->paint('M 0 0 C 10 0 0 10 10 10 S 30 0 40 10');
        self::assertStringContainsString('20 10 30 0 40 10 c', $ops);
    }

    public function testSmoothCubicWithoutPriorCubicUsesCurrentPoint(): void
    {
        // No prior C/c — first control point equals the current point.
        $ops = $this->paint('M 5 5 S 10 0 20 5');
        self::assertStringContainsString('5 5 10 0 20 5 c', $ops);
    }

    public function testQuadraticLiftsToCubic(): void
    {
        // Q P0=(0,0) P1=(10,10) P2=(20,0) → C1 = (20/3, 20/3),
        // C2 = (20 + 2/3·(-10), 0 + 2/3·10) = (40/3, 20/3). The
        // ContentStream serialises floats to 6 decimal places.
        $ops = $this->paint('M 0 0 Q 10 10 20 0');
        self::assertStringContainsString('6.666667 6.666667 13.333333 6.666667 20 0 c', $ops);
    }

    public function testSmoothQuadraticReflectsPreviousQuadraticControl(): void
    {
        // Q control = (10, 10) ending at (20, 0). T then reflects about
        // (20, 0) → (30, -10) as the synthesised quadratic control.
        // Lift quadratic (20,0) → (30,-10) → (40,0) to cubic:
        // C1 = (20,0) + 2/3·((30,-10) - (20,0)) = (80/3, -20/3)
        // C2 = (40,0) + 2/3·((30,-10) - (40,0)) = (100/3, -20/3)
        $ops = $this->paint('M 0 0 Q 10 10 20 0 T 40 0');
        self::assertStringContainsString(
            '26.666667 -6.666667 33.333333 -6.666667 40 0 c',
            $ops,
        );
    }

    public function testArcEndpointMatchesAtFullCircle(): void
    {
        // M 100 0 A 100 100 0 1 1 -100 0 → half circle to (-100, 0).
        // The painter splits into ≤90° segments; we just check the
        // final endpoint of the last cubic lands on (-100, 0).
        $ops = $this->paint('M 100 0 A 100 100 0 1 1 -100 0');
        self::assertMatchesRegularExpression('/-100 [0-9.+-eE]+ c/', $ops);
        // Single 180° arc splits into 2 quarter-arc cubics.
        self::assertGreaterThanOrEqual(2, substr_count($ops, ' c'));
    }

    public function testArcWithZeroRadiusFallsBackToStraightLine(): void
    {
        // SVG 2 §9.5.1 — `rx == 0` means render as a line to the
        // endpoint instead of an arc.
        $ops = $this->paint('M 0 0 A 0 50 0 0 0 100 0');
        self::assertStringContainsString('100 0 l', $ops);
        self::assertStringNotContainsString(' c', $ops);
    }

    public function testArcWithZeroLengthIsSkipped(): void
    {
        // Start == end → SVG 2 §9.5.1 says omit the arc entirely.
        $ops = $this->paint('M 50 50 A 25 25 0 0 0 50 50');
        // No cubic, no line.
        self::assertStringNotContainsString(' c', $ops);
        self::assertStringNotContainsString(' l', $ops);
    }

    public function testClosePathResetsCurrentPointToSubpathStart(): void
    {
        // After Z, the current point is the subpath-start, so the
        // following relative `l 5 5` should land at (15, 25).
        $ops = $this->paint('M 10 20 L 30 40 Z l 5 5');
        self::assertStringContainsString("\nh", $ops);
        self::assertStringContainsString('15 25 l', $ops);
    }

    public function testMultipleSubpaths(): void
    {
        $ops = $this->paint('M 0 0 L 10 10 Z M 20 20 L 30 30 Z');
        self::assertSame(2, substr_count($ops, "\nh"));
        self::assertSame(2, substr_count($ops, ' m'));
    }

    public function testPathInheritsFillStrokeDefaults(): void
    {
        $ops = $this->paint('M 0 0 L 10 10 Z', extraAttrs: '');
        // Default: black fill, no stroke → `0 0 0 rg` + `f`.
        self::assertStringContainsString('0 0 0 rg', $ops);
        self::assertStringContainsString("\nf", $ops);
        self::assertStringNotContainsString("\nS", $ops);
    }
}
