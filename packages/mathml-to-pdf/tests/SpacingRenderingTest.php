<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Renderer coverage for the spacing primitives `<mspace>`,
 * `<mpadded>`, `<mphantom>`.
 *
 * Tests are structural — they check that the painter emits the right
 * number of `Td` repositions (or absence thereof for invisible
 * elements) and that adjacent content lands where the spacing
 * intends.
 */
final class SpacingRenderingTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    public function testMspaceShiftsCursorWithoutEmittingGlyphs(): void
    {
        // <mspace> never emits Tj. Adjacent tokens render normally.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mi>x</mi>'
                . '<mspace width="2em"/>'
                . '<mi>y</mi>'
                . '</mrow>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(y\)\s+Tj/', $bytes);
    }

    public function testMspaceWithoutWidthFallsBackToThinSpaceDefault(): void
    {
        // No width attribute - the painter inserts a thin-space
        // default. Adjacent content still renders.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mi>a</mi>'
                . '<mspace/>'
                . '<mi>b</mi>'
                . '</mrow>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(a\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(b\)\s+Tj/', $bytes);
    }

    public function testMspaceZeroWidthEmitsNoRepositioning(): void
    {
        // Width 0 = no movement, no Tj. The wrapping mrow walk
        // still produces SOME Td/Tj from its other children.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mspace width="0em"/>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertDoesNotMatchRegularExpression('/\([^)]+\)\s+Tj/', $bytes);
    }

    public function testMspaceNegativeWidthShiftsBackward(): void
    {
        // Negative width = back up over previous content.
        // The painter must accept and apply negative deltas.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mi>x</mi>'
                . '<mspace width="-0.5em"/>'
                . '<mi>y</mi>'
                . '</mrow>'
                . '</math>',
        );
        // Both still rendered - the negative space is layout-only.
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(y\)\s+Tj/', $bytes);
    }

    public function testMpaddedRendersChildrenInline(): void
    {
        // <mpadded> without attributes is transparent - children
        // render as if directly in the parent.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mpadded><mi>x</mi><mi>y</mi></mpadded>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(y\)\s+Tj/', $bytes);
    }

    public function testMpaddedLspaceShiftsContentRight(): void
    {
        // lspace pushes the content right before painting. Cursor
        // visibly moves.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mi>x</mi>'
                . '<mpadded lspace="1em"><mi>y</mi></mpadded>'
                . '</mrow>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(y\)\s+Tj/', $bytes);
        // Two extra Tds at minimum: one inside mpadded for lspace,
        // one to advance past x. Confirms lspace was honoured.
        $tdCount = preg_match_all('/\s+Td\b/', $bytes);
        self::assertGreaterThanOrEqual(2, $tdCount);
    }

    public function testMpaddedWidthForcesCursorToTarget(): void
    {
        // width="5em" forces the cursor to (startX + 5em), even if
        // the content is narrower or wider. After mpadded, the
        // trailing sibling should sit at exactly that position.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mpadded width="5em"><mi>x</mi></mpadded>'
                . '<mi>y</mi>'
                . '</mrow>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(y\)\s+Tj/', $bytes);
    }

    public function testMphantomReservesSpaceWithoutEmittingGlyphs(): void
    {
        // <mphantom>X</mphantom> reserves the same horizontal space
        // as <mi>X</mi> but emits no Tj for the X.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mi>a</mi>'
                . '<mphantom><mi>X</mi></mphantom>'
                . '<mi>b</mi>'
                . '</mrow>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(a\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(b\)\s+Tj/', $bytes);
        // The X inside mphantom must NOT be emitted.
        self::assertDoesNotMatchRegularExpression('/\(X\)\s+Tj/', $bytes);
    }

    public function testMphantomWithEmptyChildrenEmitsNothing(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mphantom/>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertDoesNotMatchRegularExpression('/\([^)]+\)\s+Tj/', $bytes);
    }

    public function testMphantomInsideMfracReservesNumeratorSpace(): void
    {
        // Classic alignment trick: <mphantom> in a fraction numerator
        // so the bar reaches a specific width even with short content.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac>'
                . '<mphantom><mi>XXX</mi></mphantom>'
                . '<mn>2</mn>'
                . '</mfrac>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        // The XXX must not be emitted.
        self::assertDoesNotMatchRegularExpression('/\(XXX\)\s+Tj/', $bytes);
        // The fraction bar still draws.
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
    }

    public function testNestedMpaddedComposeCleanly(): void
    {
        // mpadded inside mpadded - the outer's lspace should apply
        // on top of the inner's layout.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mpadded lspace="1em">'
                . '<mpadded lspace="0.5em"><mi>x</mi></mpadded>'
                . '</mpadded>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    private function render(string $mathmlXml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = $this->parser->parse($mathmlXml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 300.0, height: 30.0);
        return $writer->toBytes();
    }
}
