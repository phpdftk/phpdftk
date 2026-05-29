<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgRenderer;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §7.10 `preserveAspectRatio` alignment + meet/slice mode.
 *
 * Meet leaves leftover space on the axis whose scale factor was rejected;
 * slice overflows that axis. The `<align>` keyword distributes the
 * leftover (or overflow) — `xMin`/`yMin` push toward the dest top-left,
 * `xMax`/`yMax` toward the dest bottom-right, `xMid`/`yMid` centre.
 *
 * Test fixtures use two different destination rectangles so both axes get
 * non-zero leftover coverage:
 *   - 100×100 source in 200×100 destination → 100-unit X leftover (meet)
 *   - 100×100 source in 100×200 destination → 100-unit Y leftover (meet)
 */
final class PreserveAspectRatioTest extends TestCase
{
    private SvgParser $svgParser;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
    }

    /**
     * Paint a 100×100 source into a (`$dstW` × `$dstH`) destination at
     * (0, 0) with the supplied preserveAspectRatio, returning the
     * content-stream operators.
     */
    private function paint(string $par, float $dstW, float $dstH): string
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $renderer = new SvgRenderer($page, $writer);
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" '
            . sprintf('preserveAspectRatio="%s">', $par)
            . '<rect width="100" height="100" fill="red"/></svg>',
        );
        $renderer->draw($svg, x: 0.0, y: 0.0, width: $dstW, height: $dstH);
        return implode("\n", $page->contentStream()->getOperators());
    }

    // ----- X-leftover cases: source 100×100 in dst 200×100 ---------------
    // meet scale = min(2, 1) = 1; effectiveW = effectiveH = 100;
    // leftover X = 100, leftover Y = 0.

    public function testMeetXMinAlignsContentToDestLeft(): void
    {
        $ops = $this->paint('xMinYMid meet', dstW: 200, dstH: 100);
        // offsetX = 0; offsetY = 0; e = 0; f = 0 + 0 + 100 + 0 = 100.
        self::assertStringContainsString('1 0 0 -1 0 100 cm', $ops);
    }

    public function testMeetXMidAlignsContentToDestCentre(): void
    {
        $ops = $this->paint('xMidYMid meet', dstW: 200, dstH: 100);
        // offsetX = 0.5 × 100 = 50; offsetY = 0; e = 50; f = 100.
        self::assertStringContainsString('1 0 0 -1 50 100 cm', $ops);
    }

    public function testMeetXMaxAlignsContentToDestRight(): void
    {
        $ops = $this->paint('xMaxYMid meet', dstW: 200, dstH: 100);
        // offsetX = 1 × 100 = 100; e = 100; f = 100.
        self::assertStringContainsString('1 0 0 -1 100 100 cm', $ops);
    }

    // ----- Y-leftover cases: source 100×100 in dst 100×200 ---------------
    // meet scale = min(1, 2) = 1; effectiveW = effectiveH = 100;
    // leftover X = 0, leftover Y = 100.

    public function testMeetYMinAlignsContentToDestTop(): void
    {
        $ops = $this->paint('xMidYMin meet', dstW: 100, dstH: 200);
        // offsetY = 1 × 100 = 100 (all leftover at the bottom so the
        // content's top edge sits at the dest's top edge).
        // f = 0 + 100 + 100 + 0 = 200.
        self::assertStringContainsString('1 0 0 -1 0 200 cm', $ops);
    }

    public function testMeetYMidAlignsContentToDestCentre(): void
    {
        $ops = $this->paint('xMidYMid meet', dstW: 100, dstH: 200);
        // offsetY = 0.5 × 100 = 50; f = 0 + 50 + 100 + 0 = 150.
        self::assertStringContainsString('1 0 0 -1 0 150 cm', $ops);
    }

    public function testMeetYMaxAlignsContentToDestBottom(): void
    {
        $ops = $this->paint('xMidYMax meet', dstW: 100, dstH: 200);
        // offsetY = 0; f = 0 + 0 + 100 + 0 = 100.
        self::assertStringContainsString('1 0 0 -1 0 100 cm', $ops);
    }

    // ----- Combined corner cases -----------------------------------------

    public function testMeetCornerAlignmentTopLeft(): void
    {
        // Source 100×100 into 200×200 — same aspect, so no leftover at
        // all. xMinYMin and xMidYMid produce identical cm.
        $ops = $this->paint('xMinYMin meet', dstW: 200, dstH: 200);
        // scale = 2; effectiveH = 200; no leftover. e = 0; f = 0 + 0 + 200 + 0 = 200.
        self::assertStringContainsString('2 0 0 -2 0 200 cm', $ops);
    }

    public function testMeetCornerAlignmentBottomRight(): void
    {
        // Source 100×100 in 300×200: sx=3, sy=2. Meet scale = 2.
        // effective = 200×200. leftover X = 100, Y = 0.
        // xMaxYMax: offsetX = 100, offsetY = 0; e = 100; f = 0 + 0 + 200 + 0 = 200.
        $ops = $this->paint('xMaxYMax meet', dstW: 300, dstH: 200);
        self::assertStringContainsString('2 0 0 -2 100 200 cm', $ops);
    }

    // ----- Slice mode + clipping -----------------------------------------

    public function testSliceXMidYMidEmitsDestRectClipAndOverflowingCm(): void
    {
        // Source 100×100 in 200×100: slice scale = max(2, 1) = 2.
        // effective = 200×200. leftover X = 0, Y = -100.
        // xMidYMid: offsetY = 0.5 × -100 = -50; f = 0 + -50 + 200 + 0 = 150.
        $ops = $this->paint('xMidYMid slice', dstW: 200, dstH: 100);
        $lines = explode("\n", $ops);
        // 1. dest-rect clip: `0 0 200 100 re` → `W` → `n`.
        $reIndex = array_search('0 0 200 100 re', $lines, true);
        $wIndex = array_search('W', $lines, true);
        $nIndex = array_search('n', $lines, true);
        self::assertNotFalse($reIndex);
        self::assertNotFalse($wIndex);
        self::assertNotFalse($nIndex);
        self::assertSame($reIndex + 1, $wIndex);
        self::assertSame($wIndex + 1, $nIndex);
        // 2. Followed by the cm.
        $cmIndex = array_search('2 0 0 -2 0 150 cm', $lines, true);
        self::assertNotFalse($cmIndex);
        self::assertLessThan($cmIndex, $nIndex);
    }

    public function testSliceYMinPushesOverflowDownward(): void
    {
        // Source 100×100 in 200×100: slice scale = 2; effective = 200×200.
        // leftover Y = -100. xMinYMin slice: offsetY = 1 × -100 = -100.
        // f = 0 + -100 + 200 + 0 = 100.
        $ops = $this->paint('xMinYMin slice', dstW: 200, dstH: 100);
        self::assertStringContainsString('2 0 0 -2 0 100 cm', $ops);
    }

    public function testSliceYMaxPushesOverflowUpward(): void
    {
        // YMax slice: offsetY = 0; f = 0 + 0 + 200 + 0 = 200.
        $ops = $this->paint('xMinYMax slice', dstW: 200, dstH: 100);
        self::assertStringContainsString('2 0 0 -2 0 200 cm', $ops);
    }

    public function testMeetModeDoesNotEmitClippingRect(): void
    {
        // Meet leaves leftover space; nothing overflows the destination
        // rectangle so emitting a clip op would waste two operators.
        $ops = $this->paint('xMidYMid meet', dstW: 200, dstH: 100);
        self::assertStringNotContainsString('0 0 200 100 re', $ops);
        self::assertStringNotContainsString("\nW\nn", $ops);
    }

    public function testNoneRemainsUnchanged(): void
    {
        // none = independent axes, no clip, no offset. sx=2, sy=1.
        // effectiveH = 1 × 100 = 100; f = 0 + 0 + 100 + 0 = 100.
        $ops = $this->paint('none', dstW: 200, dstH: 100);
        self::assertStringContainsString('2 0 0 -1 0 100 cm', $ops);
        self::assertStringNotContainsString('0 0 200 100 re', $ops);
    }

    public function testUnknownKeywordDefaultsToXMidYMidMeet(): void
    {
        $ops = $this->paint('weirdValue', dstW: 200, dstH: 100);
        // Same as testMeetXMidAlignsContentToDestCentre.
        self::assertStringContainsString('1 0 0 -1 50 100 cm', $ops);
    }
}
