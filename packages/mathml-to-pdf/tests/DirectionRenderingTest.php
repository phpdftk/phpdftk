<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Renderer-side coverage for MathML directionality (`dir="rtl"`).
 *
 * Under RTL, the painter iterates element children in reverse source
 * order so the first source child sits at the rightmost visual
 * position. Form detection for `<mo>` still uses source position
 * (an `<mo>` between two operands is infix regardless of direction).
 *
 * Structural assertions only - we confirm that glyphs reach the
 * stream and that the cursor mechanics don't crash on reversed
 * iteration. Exact x-coordinates depend on the running cursor in
 * unpredictable ways that would make the tests brittle.
 */
final class DirectionRenderingTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    public function testRtlOnMathRootReversesElementIteration(): void
    {
        // <math dir="rtl"><mrow>1+2</mrow></math>. Source: 1, +, 2.
        // RTL visual order: 2, +, 1 (reading left-to-right on page).
        // All three glyphs should still reach the stream; we verify
        // by ensuring all are present.
        $bytes = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" dir="rtl">'
                . '<mrow><mn>1</mn><mo>+</mo><mn>2</mn></mrow>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        foreach (['1', '+', '2'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . preg_quote($glyph, '/') . '\)\s+Tj/',
                $bytes,
                "expected '$glyph' Tj under RTL",
            );
        }
    }

    public function testRtlEmissionOrderDiffersFromLtr(): void
    {
        // Confirm the source-order Tj sequence is REVERSED under RTL.
        // We pick distinct unambiguous glyphs that won't collide.
        $ltr = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow><mi>a</mi><mi>b</mi><mi>c</mi></mrow>'
                . '</math>',
        );
        $rtl = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" dir="rtl">'
                . '<mrow><mi>a</mi><mi>b</mi><mi>c</mi></mrow>'
                . '</math>',
        );

        $orderLtr = $this->extractTjOrder($ltr, ['a', 'b', 'c']);
        $orderRtl = $this->extractTjOrder($rtl, ['a', 'b', 'c']);

        self::assertSame(['a', 'b', 'c'], $orderLtr);
        self::assertSame(['c', 'b', 'a'], $orderRtl);
    }

    public function testRtlOnInnerMrowAppliesOnlyToThatSubtree(): void
    {
        // Outer LTR, inner mrow RTL. The mrow's children render
        // reversed; outside content unaffected.
        $bytes = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mi>X</mi>'
                . '<mrow dir="rtl"><mi>a</mi><mi>b</mi><mi>c</mi></mrow>'
                . '<mi>Y</mi>'
                . '</mrow>'
                . '</math>',
        );
        $order = $this->extractTjOrder($bytes, ['X', 'a', 'b', 'c', 'Y']);
        // X first, then the RTL inner reversed (c, b, a), then Y.
        self::assertSame(['X', 'c', 'b', 'a', 'Y'], $order);
    }

    public function testLtrOverrideOnInnerSubtreePinsBackToLtr(): void
    {
        // Outer RTL, inner mrow explicitly LTR - the inner subtree
        // resists the inheritance and renders in source order.
        $bytes = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" dir="rtl">'
                . '<mrow>'
                . '<mi>a</mi>'
                . '<mrow dir="ltr"><mi>p</mi><mi>q</mi><mi>r</mi></mrow>'
                . '<mi>b</mi>'
                . '</mrow>'
                . '</math>',
        );
        $order = $this->extractTjOrder($bytes, ['a', 'p', 'q', 'r', 'b']);
        // Outer reversed: b sits at left, then ltr inner (p, q, r),
        // then a at right.
        self::assertSame(['b', 'p', 'q', 'r', 'a'], $order);
    }

    public function testRtlPreservesOperatorFormDetection(): void
    {
        // `<mrow>x+y</mrow>` under RTL - the + is still infix (its
        // source position is middle of three children) and the
        // dictionary still applies medium spacing. Form is computed
        // BEFORE iteration reverses.
        $bytes = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" dir="rtl">'
                . '<mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(\+\)\s+Tj/', $bytes);
        // Td repositionings for both glyph flow AND operator spacing.
        $tdCount = preg_match_all('/\s(?:Td|Tm)\b/', $bytes);
        self::assertGreaterThanOrEqual(3, $tdCount);
    }

    public function testRtlOnEmptyMrowDoesNotCrash(): void
    {
        $bytes = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" dir="rtl">'
                . '<mrow/>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testRtlSingleChildBehavesLikeLtr(): void
    {
        // Reversing a single-element list is a no-op.
        $bytes = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" dir="rtl">'
                . '<mrow><mi>x</mi></mrow>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    public function testRtlOnNestedConstructsLikeMfracStillRenders(): void
    {
        // RTL outside the fraction; the fraction's internal layout
        // (numerator / denominator stacking) is unaffected because
        // vertical stacking doesn't have a directional component.
        $bytes = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" dir="rtl">'
                . '<mrow>'
                . '<mi>x</mi>'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</mrow>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        // Fraction bar still draws.
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
    }

    // ---------------------------------------------------------------
    // MathML Core §3.2.5.7.3 — `lspace` / `rspace` are the operator's
    // LEADING / TRAILING space along the inline axis, so an RTL
    // formula swaps which side each lands on.
    // ---------------------------------------------------------------

    private const string SPACED_OPERATOR =
        '<mrow><mtext>p</mtext>'
        . '<mo lspace="1em" rspace="2em">X</mo>'
        . '<mtext>q</mtext></mrow>';

    /**
     * Negative case: the LTR path must be untouched by the swap —
     * leading space is still the left one.
     */
    public function testLtrOperatorSpacingIsUnchanged(): void
    {
        $ltr = $this->renderRaw($this->math(self::SPACED_OPERATOR));
        $wider = $this->renderRaw($this->math(
            '<mrow><mtext>p</mtext><mo lspace="4em" rspace="2em">X</mo>'
            . '<mtext>q</mtext></mrow>',
        ));
        // Widening the LEADING space by 3em moves the operator 3em
        // right (36pt at the 12pt default size).
        self::assertEqualsWithDelta(
            36.0,
            $this->glyphX($wider, 'X') - $this->glyphX($ltr, 'X'),
            0.01,
        );
    }

    /**
     * Negative case: an operator whose two spaces are EQUAL must land
     * in the same place either way — a swap that also changed the
     * total would show up here.
     */
    public function testSymmetricOperatorSpacingIsDirectionInvariant(): void
    {
        $symmetric = '<mrow><mtext>p</mtext>'
            . '<mo lspace="2em" rspace="2em">X</mo>'
            . '<mtext>q</mtext></mrow>';
        self::assertEqualsWithDelta(
            $this->glyphX($this->renderRaw($this->math($symmetric)), 'X'),
            $this->glyphX($this->renderRaw($this->math($symmetric, rtl: true)), 'X'),
            0.01,
        );
    }

    public function testRtlSwapsOperatorLeadingAndTrailingSpace(): void
    {
        $ltr = $this->renderRaw($this->math(self::SPACED_OPERATOR));
        $rtl = $this->renderRaw($this->math(self::SPACED_OPERATOR, rtl: true));
        // lspace 1em / rspace 2em: the RTL operator carries the 2em
        // space on its paint-order left, so it sits one em further
        // right than the LTR one (12pt at the default font size).
        self::assertEqualsWithDelta(
            12.0,
            $this->glyphX($rtl, 'X') - $this->glyphX($ltr, 'X'),
            0.01,
        );
    }

    /**
     * Swapping moves the operator WITHIN the row; it must not change
     * how much room the row takes, so the trailing token lands at the
     * same place in both directions. (The two `<mtext>` glyphs have
     * equal advances, so reversal alone does not move it.)
     */
    public function testSwapDoesNotChangeTheRowAdvance(): void
    {
        $ltr = $this->renderRaw($this->math(self::SPACED_OPERATOR));
        $rtl = $this->renderRaw($this->math(self::SPACED_OPERATOR, rtl: true));
        self::assertEqualsWithDelta(
            $this->glyphX($ltr, 'q'),
            $this->glyphX($rtl, 'p'),
            0.01,
            'the last-painted token should end up at the same x',
        );
    }

    public function testRtlOperatorWithNoAuthorSpacingStillUsesTheDictionary(): void
    {
        // `+` is infix here, so the dictionary supplies equal
        // lspace / rspace and the swap is a no-op — but the glyph
        // must still be emitted and still be spaced off its operands.
        $rtl = $this->renderRaw($this->math(
            '<mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow>',
            rtl: true,
        ));
        self::assertMatchesRegularExpression('/\(\+\)\s+Tj/', $rtl);
    }

    private function math(string $body, bool $rtl = false): string
    {
        return '<math xmlns="http://www.w3.org/1998/Math/MathML"'
            . ($rtl ? ' dir="rtl"' : '') . '>' . $body . '</math>';
    }

    /**
     * Absolute x of the text matrix that positions `$glyph`'s Tj.
     */
    private function glyphX(string $bytes, string $glyph): float
    {
        $pattern = '/1 0 0 1 (-?[\d.]+) -?[\d.]+ Tm\s*\('
            . preg_quote($glyph, '/') . '\)\s+Tj/';
        self::assertMatchesRegularExpression($pattern, $bytes);
        preg_match($pattern, $bytes, $m);
        return (float) $m[1];
    }

    /**
     * Pull out Tj emissions for the named glyphs, preserving stream
     * order. Glyphs not in the source list are filtered.
     *
     * @param list<string> $glyphs
     * @return list<string>
     */
    private function extractTjOrder(string $bytes, array $glyphs): array
    {
        $pattern = '/\((' . implode(
            '|',
            array_map(static fn($g): string => preg_quote($g, '/'), $glyphs),
        ) . ')\)\s+Tj/';
        if (!preg_match_all($pattern, $bytes, $matches)) {
            return [];
        }
        return $matches[1];
    }

    private function renderRaw(string $xml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = $this->parser->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 300.0, height: 30.0);
        return $writer->toBytes();
    }
}
