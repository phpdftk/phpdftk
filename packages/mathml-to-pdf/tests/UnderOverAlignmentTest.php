<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Horizontal alignment of `<munder>` / `<mover>` / `<munderover>`
 * per MathML Core §3.4.2.3 – §3.4.2.5.
 *
 * With no italic correction and no top-accent attachment the spec's
 * offset arithmetic reduces to "every child is centred within the
 * construct, whose inline size is the maximum of the three child
 * inline sizes". These tests pin that down with `<mspace>` boxes
 * whose `mathbackground` rectangles give exact, assertable
 * coordinates — the same shape the WPT
 * `munder-mover-align-accent-*` / `munderover-align-accent-*`
 * reftests use.
 */
final class UnderOverAlignmentTest extends TestCase
{
    private const float ORIGIN_X = 100.0;

    // -----------------------------------------------------------------
    // Container-shaped scripts must report their real inline size.
    // -----------------------------------------------------------------

    public function testMrowWrappedOverscriptIsMeasuredAndPainted(): void
    {
        // The overscript is an <mrow> of three spacers. Its text
        // content is empty, so a textContent-only measurement
        // reports zero and the painter drops the script entirely.
        $bytes = $this->render(
            '<mover>'
            . '<mspace height="15px" width="75px" mathbackground="blue"/>'
            . '<mrow>'
            . '<mspace height="15px" width="25px"/>'
            . '<mspace height="15px" width="25px" mathbackground="red"/>'
            . '<mspace height="15px" width="25px"/>'
            . '</mrow>'
            . '</mover>',
        );
        $rects = $this->rectangles($bytes);
        self::assertCount(2, $rects, 'base + overscript rectangles');
        [$baseX, , $baseW] = $rects[0];
        [$overX, , $overW] = $rects[1];
        self::assertEqualsWithDelta(75.0, $baseW, 0.01);
        self::assertEqualsWithDelta(25.0, $overW, 0.01);
        self::assertEqualsWithDelta(self::ORIGIN_X, $baseX, 0.01);
        // mrow inline size is 25+25+25 = 75, equal to the base, so
        // the ink sits 25pt in from the construct's start.
        self::assertEqualsWithDelta(self::ORIGIN_X + 25.0, $overX, 0.01);
    }

    public function testMrowWrappedScriptMatchesUnwrappedEquivalent(): void
    {
        // A 25pt overscript padded out to 75pt with transparent
        // spacers must land exactly where a bare 25pt overscript
        // does — this is the identity the WPT accent reftests
        // assert between test and reference.
        $wrapped = $this->rectangles($this->render(
            '<mover>'
            . '<mspace height="15px" width="75px" mathbackground="blue"/>'
            . '<mrow>'
            . '<mspace height="15px" width="25px"/>'
            . '<mspace height="15px" width="25px" mathbackground="red"/>'
            . '<mspace height="15px" width="25px"/>'
            . '</mrow>'
            . '</mover>',
        ));
        $bare = $this->rectangles($this->render(
            '<mover>'
            . '<mspace height="15px" width="75px" mathbackground="blue"/>'
            . '<mspace height="15px" width="25px" mathbackground="red"/>'
            . '</mover>',
        ));
        self::assertSame(count($bare), count($wrapped));
        foreach ($bare as $i => $rect) {
            self::assertEqualsWithDelta($rect[0], $wrapped[$i][0], 0.01, "rect $i x");
            self::assertEqualsWithDelta($rect[2], $wrapped[$i][2], 0.01, "rect $i width");
        }
    }

    // -----------------------------------------------------------------
    // Every child is centred within the construct.
    // -----------------------------------------------------------------

    public function testWideOverscriptCentresTheNarrowBase(): void
    {
        // base 25pt, overscript 75pt -> construct is 75pt wide.
        // The overscript starts at the construct origin and the
        // base is inset by (75 - 25) / 2 = 25pt.
        $rects = $this->rectangles($this->render(
            '<mover>'
            . '<mspace height="15px" width="25px" mathbackground="blue"/>'
            . '<mspace height="15px" width="75px" mathbackground="red"/>'
            . '</mover>',
        ));
        self::assertCount(2, $rects);
        [$baseX, , $baseW] = $rects[0];
        [$overX, , $overW] = $rects[1];
        self::assertEqualsWithDelta(25.0, $baseW, 0.01);
        self::assertEqualsWithDelta(75.0, $overW, 0.01);
        self::assertEqualsWithDelta(self::ORIGIN_X + 25.0, $baseX, 0.01);
        self::assertEqualsWithDelta(self::ORIGIN_X, $overX, 0.01);
    }

    public function testWideUnderscriptCentresTheNarrowBase(): void
    {
        $rects = $this->rectangles($this->render(
            '<munder>'
            . '<mspace height="15px" width="25px" mathbackground="blue"/>'
            . '<mspace height="15px" width="75px" mathbackground="red"/>'
            . '</munder>',
        ));
        self::assertCount(2, $rects);
        [$baseX] = $rects[0];
        [$underX] = $rects[1];
        self::assertEqualsWithDelta(self::ORIGIN_X + 25.0, $baseX, 0.01);
        self::assertEqualsWithDelta(self::ORIGIN_X, $underX, 0.01);
    }

    public function testMunderoverCentresBaseAgainstTheWidestScript(): void
    {
        // base 50pt, underscript 75pt, overscript 25pt -> the
        // construct is 75pt wide. Offsets: base (75-50)/2 = 12.5,
        // underscript 0, overscript (75-25)/2 = 25.
        // Rectangles come out in paint order: base, over, under.
        $rects = $this->rectangles($this->render(
            '<munderover>'
            . '<mspace height="15px" width="50px" mathbackground="blue"/>'
            . '<mspace height="15px" width="75px" mathbackground="red"/>'
            . '<mspace height="15px" width="25px" mathbackground="green"/>'
            . '</munderover>',
        ));
        self::assertCount(3, $rects);
        [$baseX, , $baseW] = $rects[0];
        [$overX, , $overW] = $rects[1];
        [$underX, , $underW] = $rects[2];
        self::assertEqualsWithDelta(50.0, $baseW, 0.01, 'base width');
        self::assertEqualsWithDelta(25.0, $overW, 0.01, 'overscript width');
        self::assertEqualsWithDelta(75.0, $underW, 0.01, 'underscript width');
        self::assertEqualsWithDelta(self::ORIGIN_X + 12.5, $baseX, 0.01, 'base x');
        self::assertEqualsWithDelta(self::ORIGIN_X + 25.0, $overX, 0.01, 'overscript x');
        self::assertEqualsWithDelta(self::ORIGIN_X, $underX, 0.01, 'underscript x');
    }

    public function testNarrowBaseConstructMatchesItsPaddedEquivalent(): void
    {
        // The identity the WPT accent reftests assert: a bare 25pt
        // base under a 75pt script must land exactly where a base
        // padded out to 75pt with transparent spacers does.
        $bare = $this->rectangles($this->render(
            '<mover>'
            . '<mspace height="15px" width="25px" mathbackground="blue"/>'
            . '<mspace height="15px" width="75px" mathbackground="red"/>'
            . '</mover>',
        ));
        $padded = $this->rectangles($this->render(
            '<mover>'
            . '<mrow>'
            . '<mspace height="15px" width="25px"/>'
            . '<mspace height="15px" width="25px" mathbackground="blue"/>'
            . '<mspace height="15px" width="25px"/>'
            . '</mrow>'
            . '<mspace height="15px" width="75px" mathbackground="red"/>'
            . '</mover>',
        ));
        self::assertSame(count($bare), count($padded));
        foreach ($bare as $i => $rect) {
            self::assertEqualsWithDelta($rect[0], $padded[$i][0], 0.01, "rect $i x");
            self::assertEqualsWithDelta($rect[2], $padded[$i][2], 0.01, "rect $i width");
        }
    }

    public function testWideBaseKeepsConstructOriginAndCentresScripts(): void
    {
        // base 75pt is the widest child, so it starts at the
        // construct origin and both 25pt scripts are inset 25pt.
        $rects = $this->rectangles($this->render(
            '<munderover>'
            . '<mspace height="15px" width="75px" mathbackground="blue"/>'
            . '<mspace height="15px" width="25px" mathbackground="red"/>'
            . '<mspace height="15px" width="25px" mathbackground="green"/>'
            . '</munderover>',
        ));
        self::assertCount(3, $rects);
        self::assertEqualsWithDelta(self::ORIGIN_X, $rects[0][0], 0.01, 'base x');
        foreach ([1, 2] as $i) {
            self::assertEqualsWithDelta(
                self::ORIGIN_X + 25.0,
                $rects[$i][0],
                0.01,
                "script $i x",
            );
        }
    }

    public function testConstructAdvancesByItsFullInlineSize(): void
    {
        // A trailing spacer after the construct starts at the
        // construct's inline end — origin + max(child widths).
        $rects = $this->rectangles($this->render(
            '<mrow>'
            . '<mover>'
            . '<mspace height="15px" width="25px" mathbackground="blue"/>'
            . '<mspace height="15px" width="75px" mathbackground="red"/>'
            . '</mover>'
            . '<mspace height="15px" width="10px" mathbackground="green"/>'
            . '</mrow>',
        ));
        self::assertCount(3, $rects);
        self::assertEqualsWithDelta(self::ORIGIN_X + 75.0, $rects[2][0], 0.01, 'trailing x');
    }

    /**
     * Background rectangles emitted as `x y w h re`, in stream order.
     *
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

    private function render(string $innerXml): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $innerXml . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, self::ORIGIN_X, 600.0, 300.0, 120.0);
        return $writer->toBytes();
    }
}
