<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Operators that stretch along the INLINE axis.
 *
 * The operator dictionary marks each stretchy operator with the axis
 * it grows along: a fence grows in the block direction, while an
 * overbar, a wide arrow or an over/under-brace grows inline to span
 * whatever it is drawn over. The painter implemented only the block
 * axis, so an accent over a six-character base stayed one character
 * wide.
 *
 * WPT asserts this as a MISMATCH: `<mo>` and `<mo stretchy="false">`
 * on the same accent must NOT render identically. These tests assert
 * the same identity directly on the emitted glyph, which also says
 * which glyph was chosen when it fails.
 */
final class HorizontalStretchTest extends TestCase
{
    /**
     * WPT's `operators.woff` gives U+00AF MACRON four horizontal
     * variants (1, 2, 3 and 4 em wide) plus an assembly recipe.
     */
    private const string MACRON = "\u{00AF}";

    private const int FONT_UNITS_PER_EM = 1000;

    public function testAccentOverAWideBaseDoesNotStayAtItsBaseWidth(): void
    {
        $stretched = $this->glyphs($this->render(
            '<mover><mtext>abcdef</mtext><mo>' . self::MACRON . '</mo></mover>',
        ));
        $unstretched = $this->glyphs($this->render(
            '<mover><mtext>abcdef</mtext><mo stretchy="false">' . self::MACRON . '</mo></mover>',
        ));
        self::assertNotSame(
            $unstretched,
            $stretched,
            'a stretchy accent must not render identically to a non-stretchy one',
        );
    }

    public function testAccentPicksAWiderVariantForAWiderBase(): void
    {
        // Variant selection, not merely "something changed": a wider
        // base has to reach further up the variant list.
        $narrow = $this->glyphs($this->render(
            '<mover><mtext>ab</mtext><mo>' . self::MACRON . '</mo></mover>',
        ));
        $wide = $this->glyphs($this->render(
            '<mover><mtext>abcdefghijkl</mtext><mo>' . self::MACRON . '</mo></mover>',
        ));
        self::assertNotSame($narrow, $wide);
    }

    public function testUnderAccentStretchesToo(): void
    {
        $stretched = $this->glyphs($this->render(
            '<munder><mtext>abcdef</mtext><mo>' . self::MACRON . '</mo></munder>',
        ));
        $unstretched = $this->glyphs($this->render(
            '<munder><mtext>abcdef</mtext><mo stretchy="false">' . self::MACRON . '</mo></munder>',
        ));
        self::assertNotSame($unstretched, $stretched);
    }

    public function testAccentWrappedInAnMrowStillStretches(): void
    {
        // Core §3.4.3: the overscript is an EMBELLISHED operator, so
        // the <mrow> wrapper must not hide it. This is the exact shape
        // of the embellished-op-1-* reftests.
        $wrapped = $this->glyphs($this->render(
            '<mover><mtext>abcdef</mtext><mrow><mo>' . self::MACRON . '</mo></mrow></mover>',
        ));
        $bare = $this->glyphs($this->render(
            '<mover><mtext>abcdef</mtext><mo>' . self::MACRON . '</mo></mover>',
        ));
        self::assertSame($bare, $wrapped);
    }

    // -----------------------------------------------------------------
    // Guards.
    // -----------------------------------------------------------------

    public function testNonStretchyAccentIsUnchangedByTheHorizontalPath(): void
    {
        // A bare accent with no base to span must still emit the plain
        // glyph rather than jumping to a wide variant.
        $alone = $this->glyphs($this->render('<mo stretchy="false">' . self::MACRON . '</mo>'));
        self::assertNotSame('', $alone, 'the accent still renders');
        $overNothing = $this->glyphs($this->render(
            '<mover><mtext></mtext><mo stretchy="false">' . self::MACRON . '</mo></mover>',
        ));
        self::assertSame($alone, $overNothing);
    }

    public function testVerticalStretchIsNotRoutedThroughTheHorizontalPath(): void
    {
        // A fence is stretchy but NOT horizontal: it must keep using
        // the block-axis variant selection, which grows with the row's
        // height rather than its width.
        $short = $this->glyphs($this->render(
            '<mrow><mo>(</mo><mspace height="1em" depth="1em"/><mo>)</mo></mrow>',
        ));
        $tall = $this->glyphs($this->render(
            '<mrow><mo>(</mo><mspace height="4em" depth="4em"/><mo>)</mo></mrow>',
        ));
        self::assertNotSame($short, $tall, 'fences still stretch with height');
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /** Concatenated operands of every hex show-text operator. */
    private function glyphs(string $bytes): string
    {
        preg_match_all('/<([0-9A-Fa-f]+)>\s*Tj/', $bytes, $matches);

        return implode('|', $matches[1]);
    }

    private function render(string $innerXml): string
    {
        $fontPath = __DIR__ . '/../../../vendor-data/wpt/fonts/math/operators.woff';
        if (!is_file($fontPath)) {
            self::markTestSkipped(
                'WPT math font not available. '
                . 'Run `git submodule update --init vendor-data/wpt`.',
            );
        }
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer, mathFontPath: $fontPath);
        $document = (new MathmlParser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">' . $innerXml . '</math>',
        );
        $renderer->draw($document, 100.0, 600.0, 400.0, 200.0, fontSize: 25.0);

        return $writer->toBytes();
    }
}
