<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlGlyphMetrics;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Where the MathML painter's glyphs actually land in PDF user space.
 *
 * The painter tracks an absolute logical cursor
 * (`MathmlPaintContext::$cursorX` / `$baselineY`) and repositions the
 * PDF text pen whenever a construct needs to move off the natural
 * left-to-right flow (operator `lspace`, `<mspace>` advance, script
 * attachment, centred over/underscripts, ...).
 *
 * PDF's `Td` operator is defined (ISO 32000-2 §9.4.2) relative to the
 * *text line matrix* `Tlm`, which `Tj` does NOT advance — only the text
 * matrix `Tm` moves as glyphs are shown. Any repositioning expressed as
 * a delta from the painter's own cursor is therefore short by every
 * glyph advance emitted since the last positioning operator.
 *
 * These tests pin the invariant that fixes it: after the painter says
 * "content sits at cursorX", the pen the next `Tj` draws from must be
 * at that absolute coordinate. They deliberately assert TEXT positions,
 * not `mathbackground` rectangles — rectangles are emitted from
 * `cursorX` in absolute page coordinates and so stay correct even while
 * the glyphs beside them are misplaced.
 */
final class TextPenPositionTest extends TestCase
{
    private const float ORIGIN_X = 100.0;

    private const float FONT_SIZE = 12.0;

    // -----------------------------------------------------------------
    // Failure modes: a glyph run that follows any repositioning op.
    // -----------------------------------------------------------------

    public function testTokenAfterMspaceIsNotShortByThePrecedingTextAdvance(): void
    {
        $glyphs = $this->glyphs($this->render(
            '<mrow><mtext>ab</mtext><mspace width="2em"/><mtext>cd</mtext></mrow>',
        ));
        self::assertCount(2, $glyphs, 'two text runs');
        $abWidth = MathmlGlyphMetrics::measure('ab', self::FONT_SIZE);
        self::assertEqualsWithDelta(self::ORIGIN_X, $glyphs[0][1], 0.01, 'first run x');
        self::assertEqualsWithDelta(
            self::ORIGIN_X + $abWidth + 2.0 * self::FONT_SIZE,
            $glyphs[1][1],
            0.01,
            'run after <mspace> must start past the first run, not at the line origin',
        );
    }

    public function testOperatorLspaceAddsToTheRunningCursorNotTheLineOrigin(): void
    {
        $glyphs = $this->glyphs($this->render(
            '<mrow><mtext>ab</mtext><mo lspace="1em" rspace="0em">+</mo></mrow>',
        ));
        self::assertCount(2, $glyphs, 'base run + operator');
        $abWidth = MathmlGlyphMetrics::measure('ab', self::FONT_SIZE);
        self::assertEqualsWithDelta(
            self::ORIGIN_X + $abWidth + self::FONT_SIZE,
            $glyphs[1][1],
            0.01,
            'lspace shifts the operator right of the preceding token',
        );
    }

    public function testSuperscriptAttachesAtTheBaseRightEdge(): void
    {
        $glyphs = $this->glyphs($this->render('<msup><mtext>ab</mtext><mtext>c</mtext></msup>'));
        self::assertCount(2, $glyphs, 'base + superscript');
        $abWidth = MathmlGlyphMetrics::measure('ab', self::FONT_SIZE);
        self::assertEqualsWithDelta(self::ORIGIN_X, $glyphs[0][1], 0.01, 'base x');
        self::assertEqualsWithDelta(
            self::ORIGIN_X + $abWidth,
            $glyphs[1][1],
            0.01,
            'superscript must attach after the base, not above its start',
        );
        self::assertGreaterThan($glyphs[0][2], $glyphs[1][2], 'superscript sits above the baseline');
    }

    public function testCentredOverscriptLandsInsideTheConstruct(): void
    {
        $glyphs = $this->glyphs($this->render('<mover><mtext>abcdef</mtext><mo>+</mo></mover>'));
        self::assertCount(2, $glyphs, 'base + overscript');
        $baseWidth = MathmlGlyphMetrics::measure('abcdef', self::FONT_SIZE);
        $overWidth = MathmlGlyphMetrics::measure('+', self::FONT_SIZE * 0.7);
        self::assertEqualsWithDelta(
            self::ORIGIN_X + ($baseWidth - $overWidth) / 2.0,
            $glyphs[1][1],
            0.5,
            'overscript is centred over the base',
        );
        self::assertGreaterThanOrEqual(
            self::ORIGIN_X,
            $glyphs[1][1],
            'a centred overscript can never start left of its construct',
        );
    }

    public function testTokenAfterAFractionResumesAtTheFractionRightEdge(): void
    {
        // The fraction is the one construct that already expressed its
        // moves relative to the line origin, so this is the guard that
        // the conversion to absolute positioning did not shift it.
        $glyphs = $this->glyphs($this->render(
            '<mrow><mfrac><mtext>ab</mtext><mtext>cd</mtext></mfrac><mtext>z</mtext></mrow>',
        ));
        self::assertCount(3, $glyphs, 'numerator + denominator + trailing run');
        $fracWidth = max(
            MathmlGlyphMetrics::measure('ab', self::FONT_SIZE * 0.7),
            MathmlGlyphMetrics::measure('cd', self::FONT_SIZE * 0.7),
        );
        self::assertEqualsWithDelta(self::ORIGIN_X, $glyphs[0][1], 0.01, 'numerator x');
        self::assertEqualsWithDelta(self::ORIGIN_X, $glyphs[1][1], 0.01, 'denominator x');
        self::assertEqualsWithDelta(
            self::ORIGIN_X + $fracWidth,
            $glyphs[2][1],
            0.01,
            'trailing token starts at the fraction right edge',
        );
    }

    // -----------------------------------------------------------------
    // Guards: plain flow must not move, and repeated moves must not
    // accumulate.
    // -----------------------------------------------------------------

    public function testPlainRunStartsExactlyAtTheRequestedOrigin(): void
    {
        $glyphs = $this->glyphs($this->render('<mtext>ab</mtext>'));
        self::assertCount(1, $glyphs);
        self::assertEqualsWithDelta(self::ORIGIN_X, $glyphs[0][1], 0.01);
    }

    public function testConsecutiveTokensFlowFromTheSingleInitialPenPlacement(): void
    {
        // Two adjacent tokens emit no positioning op between them — the
        // PDF font's own advance carries the pen. Asserting the second
        // run's recorded pen X equals the first proves the painter did
        // NOT emit a spurious reposition that would double-count the
        // advance once positioning became absolute.
        $glyphs = $this->glyphs($this->render('<mrow><mtext>ab</mtext><mtext>cd</mtext></mrow>'));
        self::assertCount(2, $glyphs);
        self::assertEqualsWithDelta($glyphs[0][1], $glyphs[1][1], 0.01);
    }

    public function testRepeatedMspacesAccumulateExactlyOnce(): void
    {
        $glyphs = $this->glyphs($this->render(
            '<mrow><mspace width="1em"/><mspace width="1em"/><mtext>z</mtext></mrow>',
        ));
        self::assertCount(1, $glyphs);
        self::assertEqualsWithDelta(
            self::ORIGIN_X + 2.0 * self::FONT_SIZE,
            $glyphs[0][1],
            0.01,
        );
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Replay the PDF text operators in `$bytes` and report the absolute
     * user-space pen position each show-text operator drew from.
     *
     * Implements the ISO 32000-2 §9.4.2 text-matrix rules the painter
     * has to respect: `BT` resets `Tm = Tlm = identity`; `Td` / `TD`
     * translate `Tlm` and copy it to `Tm`; `Tm` sets both; `T*` moves
     * down by the leading; show-text operators advance `Tm` only (we do
     * not model the advance — every assertion here is about where a run
     * STARTS).
     *
     * @return list<array{0: string, 1: float, 2: float}> text, x, y
     */
    private function glyphs(string $bytes): array
    {
        $num = '-?\d*\.?\d+';
        preg_match_all(
            '/\bBT\b'
            . "|($num)\\s+($num)\\s+(?:Td|TD)\\b"
            . "|($num)\\s+($num)\\s+($num)\\s+($num)\\s+($num)\\s+($num)\\s+Tm\\b"
            . '|\((?<lit>(?:\\\\.|[^()\\\\])*)\)\s*Tj\b'
            . '|<(?<hex>[0-9A-Fa-f]+)>\s*Tj\b/',
            $bytes,
            $matches,
            PREG_SET_ORDER,
        );
        $lineX = 0.0;
        $lineY = 0.0;
        $out = [];
        foreach ($matches as $m) {
            $token = $m[0];
            if ($token === 'BT') {
                $lineX = 0.0;
                $lineY = 0.0;
                continue;
            }
            if (str_ends_with($token, 'Tm')) {
                $lineX = (float) $m[7];
                $lineY = (float) $m[8];
                continue;
            }
            if (str_ends_with($token, 'Td') || str_ends_with($token, 'TD')) {
                $lineX += (float) $m[1];
                $lineY += (float) $m[2];
                continue;
            }
            $text = ($m['lit'] ?? '') !== ''
                ? stripslashes($m['lit'])
                : ('hex:' . ($m['hex'] ?? ''));
            $out[] = [$text, $lineX, $lineY];
        }

        return $out;
    }

    private function render(string $innerXml): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $innerXml . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw(
            $doc,
            self::ORIGIN_X,
            600.0,
            300.0,
            120.0,
            fontSize: self::FONT_SIZE,
        );

        return $writer->toBytes();
    }
}
