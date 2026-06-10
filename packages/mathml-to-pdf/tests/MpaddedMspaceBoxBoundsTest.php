<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Verify that explicit `width` / `height` / `depth` attributes on
 * `<mpadded>` and `<mspace>` flow into:
 *
 *   1. The mathbackground rectangle's size and Y position.
 *   2. The voffset shift on nested children's mathbackground
 *      position.
 *
 * The MathML WPT mpadded-010 / 011 / 012 and spaces/space-2 / 3
 * tests build their fixtures out of coloured boxes with these
 * exact attributes - the rect math here lines up with what
 * those tests expect to see.
 */
final class MpaddedMspaceBoxBoundsTest extends TestCase
{
    public function testMpaddedExplicitWidthHeightDepthSetsRect(): void
    {
        // 100px × 100px (50 ascent + 50 descent), blue bg.
        // px → pt at 1:1 to line up with html-to-pdf's CSS
        // convention, so 100px = 100pt.
        $bytes = $this->render(
            '<mpadded mathbackground="blue" width="100px" '
            . 'height="50px" depth="50px"></mpadded>',
            x: 100.0,
            y: 600.0,
            boxWidth: 200.0,
            boxHeight: 100.0,
        );
        $rects = $this->rectangles($bytes);
        self::assertCount(1, $rects, 'one bg rectangle');
        [$x, $y, $w, $h] = $rects[0];
        self::assertEqualsWithDelta(100.0, $w, 0.01, 'width 100px -> 100pt');
        self::assertEqualsWithDelta(100.0, $h, 0.01, 'height+depth 100px -> 100pt');
        // x should match cursor (renderer's x parameter).
        self::assertEqualsWithDelta(100.0, $x, 0.01);
    }

    public function testMspaceExplicitDimensionsSetRect(): void
    {
        // mspace width=50px, height=3em, depth=3em, red bg.
        // 50px -> 50pt at the new 1:1 convention; height+depth=6em
        // -> 72pt.
        $bytes = $this->render(
            '<mspace width="50px" height="3em" depth="3em" '
            . 'mathbackground="red"/>',
        );
        $rects = $this->rectangles($bytes);
        self::assertCount(1, $rects);
        [, , $w, $h] = $rects[0];
        self::assertEqualsWithDelta(50.0, $w, 0.01);
        self::assertEqualsWithDelta(72.0, $h, 0.01);
    }

    public function testVoffsetShiftsNestedMathbackgroundRect(): void
    {
        // Outer mpadded: 100×100 blue.
        // Inner mpadded: 20×20 red, raised by voffset=30px.
        // px=pt now: 100px=100pt, 20px=20pt, voffset 30px=30pt.
        $bytes = $this->render(
            '<mpadded mathbackground="blue" width="100px" '
            . 'height="50px" depth="50px" voffset="30px">'
            . '<mpadded mathbackground="red" width="20px" '
            . 'height="10px" depth="10px"></mpadded>'
            . '</mpadded>',
            x: 100.0,
            y: 600.0,
            boxWidth: 200.0,
            boxHeight: 100.0,
        );
        $rects = $this->rectangles($bytes);
        self::assertCount(2, $rects, 'outer + inner rect');
        [, $outerY, ,] = $rects[0];
        [, $innerY, ,] = $rects[1];
        // Inner Y should be HIGHER than outer (positive voffset
        // = raise) by the voffset amount + box-extent difference.
        self::assertGreaterThan(
            $outerY,
            $innerY,
            'inner rect should be raised relative to outer',
        );
        // Delta is voffset (30pt) + outer-descent (50pt)
        // - inner-descent (10pt) = 70pt above outer bottom.
        self::assertEqualsWithDelta(
            70.0,
            $innerY - $outerY,
            0.5,
        );
    }

    public function testNegativeVoffsetLowersNestedRect(): void
    {
        // voffset=-20px lowers the inner content.
        $bytes = $this->render(
            '<mpadded mathbackground="blue" width="100px" '
            . 'height="50px" depth="50px" voffset="-20px">'
            . '<mpadded mathbackground="red" width="20px" '
            . 'height="10px" depth="10px"></mpadded>'
            . '</mpadded>',
            x: 100.0,
            y: 600.0,
            boxWidth: 200.0,
            boxHeight: 100.0,
        );
        $rects = $this->rectangles($bytes);
        self::assertCount(2, $rects);
        [, $outerY, ,] = $rects[0];
        [, $innerY, ,] = $rects[1];
        // -20px voffset = -20pt shift down. outer-descent 50pt,
        // inner-descent 10pt -> inner above outer bottom by
        // (50 - 20 - 10) = 20pt.
        self::assertEqualsWithDelta(20.0, $innerY - $outerY, 0.5);
    }

    public function testMpaddedAbsentDimensionsFallsBackToHeuristic(): void
    {
        // No explicit dimensions -> bg rect uses the 1.2em line-
        // height heuristic; still emits a rect since lspace is
        // present.
        $bytes = $this->render(
            '<mpadded mathbackground="green" lspace="0px">'
            . '<mi>x</mi>'
            . '</mpadded>',
        );
        $rects = $this->rectangles($bytes);
        self::assertGreaterThanOrEqual(1, count($rects));
    }

    /**
     * @return list<array{0: float, 1: float, 2: float, 3: float}>
     */
    private function rectangles(string $bytes): array
    {
        preg_match_all(
            '/(-?\d+\.?\d*)\s+(-?\d+\.?\d*)\s+(-?\d+\.?\d*)\s+(-?\d+\.?\d*)\s+re\b/',
            $bytes,
            $m,
        );
        $rects = [];
        foreach ($m[0] as $i => $_) {
            $rects[] = [
                (float) $m[1][$i],
                (float) $m[2][$i],
                (float) $m[3][$i],
                (float) $m[4][$i],
            ];
        }
        return $rects;
    }

    private function render(
        string $innerXml,
        float $x = 72.0,
        float $y = 600.0,
        float $boxWidth = 200.0,
        float $boxHeight = 30.0,
    ): string {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $innerXml . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, $x, $y, $boxWidth, $boxHeight);
        return $writer->toBytes();
    }
}
