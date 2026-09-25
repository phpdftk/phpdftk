<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `<mo movablelimits>` + `<math display>` interaction.
 *
 * Per MathML Core §3.3.6.3, when `<munder>` / `<mover>` /
 * `<munderover>` has a base `<mo>` with `movablelimits="true"`:
 *
 *   - In *inline* style (default, or `<math>` without
 *     `display="block"`), limits render as sub/superscripts
 *     attached at the base's right edge.
 *   - In *display* style (`<math display="block">`), limits stay
 *     centred above/below the base — the existing under/over
 *     behaviour.
 *
 * We compare the Td sequences and Tj order across permutations to
 * confirm the painter routes correctly.
 */
final class MovableLimitsTest extends TestCase
{
    public function testMoverWithMovableLimitsInInlineUsesScriptPositioning(): void
    {
        // Default `<math>` is inline. Mover with movablelimits should
        // render the over as a superscript: base then sup at right
        // edge, not centred above. Td sequence should differ from
        // the centred-above case.
        $inline = $this->render(
            '<mover><mo movablelimits="true">' . "\u{2211}" . '</mo><mi>n</mi></mover>',
            displayBlock: false,
        );
        $displayBlock = $this->render(
            '<mover><mo movablelimits="true">' . "\u{2211}" . '</mo><mi>n</mi></mover>',
            displayBlock: true,
        );
        self::assertNotSame(
            $this->extractTds($inline),
            $this->extractTds($displayBlock),
            'inline + movablelimits should produce different Td sequence '
            . 'than display + movablelimits',
        );
    }

    public function testMoverWithoutMovableLimitsUsesOverPositioning(): void
    {
        // Without movablelimits, both modes use the same centred-above
        // placement.
        $inline = $this->render(
            '<mover><mi>x</mi><mo>^</mo></mover>',
            displayBlock: false,
        );
        $displayBlock = $this->render(
            '<mover><mi>x</mi><mo>^</mo></mover>',
            displayBlock: true,
        );
        self::assertSame(
            $this->extractTds($inline),
            $this->extractTds($displayBlock),
        );
    }

    public function testMunderWithMovableLimitsRoutesToSubscriptInInline(): void
    {
        $inline = $this->render(
            '<munder><mo movablelimits="true">' . "\u{2211}" . '</mo><mi>k</mi></munder>',
            displayBlock: false,
        );
        $displayBlock = $this->render(
            '<munder><mo movablelimits="true">' . "\u{2211}" . '</mo><mi>k</mi></munder>',
            displayBlock: true,
        );
        self::assertNotSame(
            $this->extractTds($inline),
            $this->extractTds($displayBlock),
        );
    }

    public function testMunderoverRoutesBothScriptsInInline(): void
    {
        // Both limits route - top as superscript, bottom as
        // subscript. Output differs from display mode.
        $inline = $this->render(
            '<munderover>'
                . '<mo movablelimits="true">' . "\u{2211}" . '</mo>'
                . '<mi>k</mi><mi>n</mi>'
                . '</munderover>',
            displayBlock: false,
        );
        $displayBlock = $this->render(
            '<munderover>'
                . '<mo movablelimits="true">' . "\u{2211}" . '</mo>'
                . '<mi>k</mi><mi>n</mi>'
                . '</munderover>',
            displayBlock: true,
        );
        self::assertNotSame(
            $this->extractTds($inline),
            $this->extractTds($displayBlock),
        );
    }

    public function testUnattributedSummationRoutesViaDictionaryDefault(): void
    {
        // No movablelimits attribute on the <mo> - but ∑ has
        // movablelimits=true as its dictionary default. In inline
        // mode the painter should still route to scripts; the
        // result should match the explicit-attribute path.
        $unattributed = $this->render(
            '<mover><mo>' . "\u{2211}" . '</mo><mi>n</mi></mover>',
            displayBlock: false,
        );
        $explicit = $this->render(
            '<mover><mo movablelimits="true">' . "\u{2211}" . '</mo><mi>n</mi></mover>',
            displayBlock: false,
        );
        self::assertSame(
            $this->extractTds($unattributed),
            $this->extractTds($explicit),
        );
    }

    public function testNonLargeOperatorIgnoresDictionaryFallback(): void
    {
        // A non-largeop operator like '+' has movablelimits=false
        // in the dictionary, so an unmarked <mover> with '+' as
        // base should NOT route to scripts.
        $unattributed = $this->render(
            '<mover><mo>+</mo><mi>n</mi></mover>',
            displayBlock: false,
        );
        $explicit = $this->render(
            '<mover><mo movablelimits="false">+</mo><mi>n</mi></mover>',
            displayBlock: false,
        );
        self::assertSame(
            $this->extractTds($unattributed),
            $this->extractTds($explicit),
        );
    }

    public function testMovableLimitsFalseAlwaysUsesOverUnder(): void
    {
        // Explicit `movablelimits="false"` keeps the limits centred
        // in inline mode - same Td sequence as display mode.
        $inline = $this->render(
            '<mover>'
                . '<mo movablelimits="false">' . "\u{2211}" . '</mo>'
                . '<mi>n</mi>'
                . '</mover>',
            displayBlock: false,
        );
        $displayBlock = $this->render(
            '<mover>'
                . '<mo movablelimits="false">' . "\u{2211}" . '</mo>'
                . '<mi>n</mi>'
                . '</mover>',
            displayBlock: true,
        );
        self::assertSame(
            $this->extractTds($inline),
            $this->extractTds($displayBlock),
        );
    }

    // ---------------------------------------------------------------
    // Core §3.4.3 — an INLINE-style munder/mover/munderover whose
    // base is a `movablelimits` operator renders its limits as
    // scripts, so it MEASURES as scripts too (base + widest script),
    // not as a stack.
    // ---------------------------------------------------------------

    /**
     * Intrinsic inline size of `$innerXml` under the given display
     * mode, which is what an inline `<math>` reserves in its line.
     */
    private function intrinsicWidth(string $innerXml, bool $displayBlock): float
    {
        $displayAttr = $displayBlock ? ' display="block"' : '';
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML"'
            . $displayAttr . '>' . $innerXml . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        [$width] = $renderer->intrinsicSize(
            (new MathmlParser())->parse($xml),
            12.0,
        );
        return $width;
    }

    /** A wide limit over a narrow `movablelimits` base. */
    private const string SUM_WITH_WIDE_LIMIT =
        '<mover><mo movablelimits="true">&#x2211;</mo><mn>1000</mn></mover>';

    public function testInlineMovableLimitsConstructMeasuresAsScripts(): void
    {
        // Stacked, the construct is only as wide as the wider child;
        // as scripts it is base PLUS script, so it must be wider.
        self::assertGreaterThan(
            $this->intrinsicWidth(self::SUM_WITH_WIDE_LIMIT, displayBlock: true),
            $this->intrinsicWidth(self::SUM_WITH_WIDE_LIMIT, displayBlock: false),
            'inline limits sit beside the base, so they add to its width',
        );
    }

    /**
     * Negative case: with `movablelimits="false"` the limits stay
     * stacked in inline mode too, so the measurement must NOT change
     * between display modes.
     */
    public function testMovableLimitsFalseMeasuresAsAStackInBothModes(): void
    {
        $stacked = '<mover><mo movablelimits="false">&#x2211;</mo>'
            . '<mn>1000</mn></mover>';
        self::assertEqualsWithDelta(
            $this->intrinsicWidth($stacked, displayBlock: true),
            $this->intrinsicWidth($stacked, displayBlock: false),
            0.01,
        );
    }

    public function testInlineMunderoverMeasuresByTheWiderOfTheTwoScripts(): void
    {
        $narrowUnder = '<munderover><mo movablelimits="true">&#x2211;</mo>'
            . '<mn>1</mn><mn>1000</mn></munderover>';
        $narrowOver = '<munderover><mo movablelimits="true">&#x2211;</mo>'
            . '<mn>1000</mn><mn>1</mn></munderover>';
        // Both attach at the base's inline end, so the wider one sets
        // the extent whichever slot it occupies.
        self::assertEqualsWithDelta(
            $this->intrinsicWidth($narrowUnder, displayBlock: false),
            $this->intrinsicWidth($narrowOver, displayBlock: false),
            0.01,
        );
    }

    // ---------------------------------------------------------------
    // Core §3.2.4.1 types `lspace` / `rspace` as <length-percentage>,
    // so every absolute CSS unit resolves — not just `em`.
    // ---------------------------------------------------------------

    public function testZeroLspaceInPixelsSuppressesTheDictionarySpacing(): void
    {
        // The dictionary supplies non-zero spacing for an infix `+`,
        // so it repositions the pen before the glyph. `lspace="0px"`
        // must beat that rather than being discarded as an unknown
        // unit — with no space to add, the operator rides the
        // preceding operand's own advance and no `Tm` is emitted
        // between the two glyphs at all.
        self::assertMatchesRegularExpression(
            '/\(1\)\s+Tj\s*\(\+\)\s+Tj/',
            $this->render(
                '<mrow><mn>1</mn><mo lspace="0px" rspace="0px">+</mo></mrow>',
                false,
            ),
        );
        self::assertDoesNotMatchRegularExpression(
            '/\(1\)\s+Tj\s*\(\+\)\s+Tj/',
            $this->render('<mrow><mn>1</mn><mo>+</mo></mrow>', false),
            'the dictionary default must still reposition',
        );
    }

    public function testPixelLspaceResolvesAgainstTheFontSize(): void
    {
        // 12px at the 12pt default font size is exactly 1em.
        self::assertEqualsWithDelta(
            $this->operatorX('<mo lspace="1em" rspace="0em">+</mo>'),
            $this->operatorX('<mo lspace="12px" rspace="0em">+</mo>'),
            0.01,
        );
    }

    public function testAbsoluteUnitsOtherThanPixelsAlsoResolve(): void
    {
        // 1pc == 16px; 0.25in == 24px == 2em at 12pt.
        self::assertEqualsWithDelta(
            $this->operatorX('<mo lspace="16px" rspace="0em">+</mo>'),
            $this->operatorX('<mo lspace="1pc" rspace="0em">+</mo>'),
            0.01,
        );
        self::assertEqualsWithDelta(
            $this->operatorX('<mo lspace="2em" rspace="0em">+</mo>'),
            $this->operatorX('<mo lspace="0.25in" rspace="0em">+</mo>'),
            0.01,
        );
    }

    /**
     * Negative case: a unit the painter cannot resolve must fall back
     * to the dictionary rather than being read as a bare number.
     */
    public function testUnresolvableUnitsFallBackToTheDictionary(): void
    {
        $dictionary = $this->operatorX('<mo>+</mo>');
        foreach (['50%', '3zz', 'wide', '-4px'] as $bad) {
            self::assertEqualsWithDelta(
                $dictionary,
                $this->operatorX('<mo lspace="' . $bad . '">+</mo>'),
                0.01,
                "lspace=\"$bad\" should fall back to the dictionary",
            );
        }
    }

    /**
     * Absolute x the operator glyph is painted at.
     *
     * The operator leads the row, so nothing has advanced the pen
     * before it and the last text matrix emitted ahead of its `Tj`
     * IS its position. That matters because spacing which resolves
     * to ZERO emits no repositioning at all — consecutive tokens
     * ride the font's own advances — so an assertion that demands a
     * `Tm` immediately before the glyph cannot see the zero case,
     * which is exactly the case under test.
     */
    private function operatorX(string $operatorXml): float
    {
        $bytes = $this->render('<mrow>' . $operatorXml . '<mn>1</mn></mrow>', false);
        $head = substr($bytes, 0, (int) strpos($bytes, '(+) Tj'));
        self::assertMatchesRegularExpression('/1 0 0 1 (-?[\d.]+) -?[\d.]+ Tm/', $head);
        preg_match_all('/1 0 0 1 (-?[\d.]+) -?[\d.]+ Tm/', $head, $matches);
        return (float) end($matches[1]);
    }

    private function render(string $innerXml, bool $displayBlock): string
    {
        $displayAttr = $displayBlock ? ' display="block"' : '';
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML"'
            . $displayAttr . '>' . $innerXml . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }

    /**
     * @return list<array{float, float}>
     */
    private function extractTds(string $bytes): array
    {
        // Td states a delta from the text LINE matrix; Tm states the
        // matrix outright. The painter emits Tm so a reposition can't
        // be skewed by the glyph advances since the last one
        // (Translator::moveTextTo), so both forms are collected here:
        // what these comparisons care about is that the positioning
        // stream differs between the two renders, not which operator
        // carried it.
        $number = '-?\d+(?:\.\d+)?';
        $matched = preg_match_all(
            '/(' . $number . ')\s+(' . $number . ')\s+Td\b'
            . '|(?:' . $number . '\s+){4}(' . $number . ')\s+(' . $number . ')\s+Tm\b/',
            $bytes,
            $matches,
            PREG_SET_ORDER,
        );
        if ($matched === false || $matched === 0) {
            return [];
        }
        $out = [];
        foreach ($matches as $m) {
            $out[] = str_ends_with($m[0], 'Tm')
                ? [(float) $m[3], (float) $m[4]]
                : [(float) $m[1], (float) $m[2]];
        }
        return $out;
    }
}
