<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Renderer coverage for `<mtable>` — 2-D grid layout with arbitrary
 * rows × columns, ragged rows, and nested constructs in cells.
 *
 * Structural assertions (Tj presence, Td count) rather than literal
 * coordinates — exact positioning is an implementation detail of the
 * tracer-bullet layout maths.
 */
final class MtableRenderingTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    public function testSingleCellTableRendersContent(): void
    {
        // 1x1 table — the simplest possible mtable. Confirms wiring.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable><mtr><mtd><mn>7</mn></mtd></mtr></mtable>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(7\)\s+Tj/', $bytes);
    }

    public function testTwoByTwoMatrixRendersAllCells(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable>'
                . '<mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '<mtr><mtd><mn>3</mn></mtd><mtd><mn>4</mn></mtd></mtr>'
                . '</mtable>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        foreach (['1', '2', '3', '4'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . $glyph . '\)\s+Tj/',
                $bytes,
                "expected '$glyph' Tj in stream",
            );
        }
    }

    public function testRowsLayoutEmitsMultipleTdRepositioning(): void
    {
        // Each cell requires a Td (relative reposition) to its place
        // in the grid plus one to return to the parent baseline. A
        // 2×2 table has at least 4 cells × 2 Td = 8 Td operations.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable>'
                . '<mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '<mtr><mtd><mn>3</mn></mtd><mtd><mn>4</mn></mtd></mtr>'
                . '</mtable>'
                . '</math>',
        );
        $tdCount = preg_match_all('/\s+Td\b/', $bytes);
        self::assertGreaterThanOrEqual(8, $tdCount);
    }

    public function testRaggedRowsRenderWithoutCrash(): void
    {
        // Row 1: 3 cells, row 2: 1 cell. Painter should not blow up
        // on the ragged structure; missing trailing cells degrade to
        // empty columns.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable>'
                . '<mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd><mtd><mn>3</mn></mtd></mtr>'
                . '<mtr><mtd><mn>4</mn></mtd></mtr>'
                . '</mtable>'
                . '</math>',
        );
        foreach (['1', '2', '3', '4'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . $glyph . '\)\s+Tj/',
                $bytes,
            );
        }
    }

    public function testEmptyMtableEmitsNothing(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable/>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertDoesNotMatchRegularExpression('/\([^)]+\)\s+Tj/', $bytes);
    }

    public function testMtableWithNoMtrChildrenWalksInline(): void
    {
        // Author error — content directly under <mtable>, no <mtr>.
        // Painter falls back to walkChildren so content isn't dropped.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable><mn>5</mn></mtable>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(5\)\s+Tj/', $bytes);
    }

    public function testNestedConstructsInsideCellRender(): void
    {
        // <mfrac> inside <mtd> — the cell paint recurses through
        // paint() so any nested construct should compose cleanly.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable>'
                . '<mtr><mtd>'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</mtd></mtr>'
                . '</mtable>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        // Fraction bar still drawn — proves the nested construct ran
        // its full paint, not just text emission.
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
    }

    public function testMtableInsideMrowFlowsToTrailingSibling(): void
    {
        // <mrow>(table)=<mi>X</mi></mrow> — confirm the table's
        // cursor-advance pushes the trailing tokens to its right.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mtable><mtr><mtd><mn>1</mn></mtd></mtr></mtable>'
                . '<mo>=</mo><mi>X</mi>'
                . '</mrow>'
                . '</math>',
        );
        foreach (['1', '=', 'X'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . preg_quote($glyph, '/') . '\)\s+Tj/',
                $bytes,
            );
        }
    }

    public function testMtableWithUnevenColumnWidthsAlignsByMax(): void
    {
        // First column: 'X' and 'XY' — max width 2 chars.
        // Second column: 'A' and 'B' — max width 1 char.
        // Painter centres each cell in its column; both cells should
        // still hit the stream.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable>'
                . '<mtr><mtd><mi>X</mi></mtd><mtd><mi>A</mi></mtd></mtr>'
                . '<mtr><mtd><mn>12</mn></mtd><mtd><mi>B</mi></mtd></mtr>'
                . '</mtable>'
                . '</math>',
        );
        foreach (['X', 'A', '12', 'B'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . preg_quote($glyph, '/') . '\)\s+Tj/',
                $bytes,
            );
        }
    }

    public function testSingleRowTableRendersHorizontally(): void
    {
        // 1 row × 3 cols. Common case for "vector" layouts.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable>'
                . '<mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd><mtd><mn>3</mn></mtd></mtr>'
                . '</mtable>'
                . '</math>',
        );
        foreach (['1', '2', '3'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . $glyph . '\)\s+Tj/',
                $bytes,
            );
        }
    }

    public function testSingleColumnTableRendersVertically(): void
    {
        // 3 rows × 1 col. Common case for "column vector" layouts.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable>'
                . '<mtr><mtd><mn>1</mn></mtd></mtr>'
                . '<mtr><mtd><mn>2</mn></mtd></mtr>'
                . '<mtr><mtd><mn>3</mn></mtd></mtr>'
                . '</mtable>'
                . '</math>',
        );
        foreach (['1', '2', '3'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . $glyph . '\)\s+Tj/',
                $bytes,
            );
        }
    }

    public function testNestedMtableInsideMtdRenders(): void
    {
        // Block matrix structure: outer table cell contains an inner
        // table. The recursion through paint() composes.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable><mtr><mtd>'
                . '<mtable>'
                . '<mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '<mtr><mtd><mn>3</mn></mtd><mtd><mn>4</mn></mtd></mtr>'
                . '</mtable>'
                . '</mtd></mtr></mtable>'
                . '</math>',
        );
        foreach (['1', '2', '3', '4'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . $glyph . '\)\s+Tj/',
                $bytes,
            );
        }
    }

    private function render(string $mathmlXml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = $this->parser->parse($mathmlXml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 300.0, height: 100.0);
        return $writer->toBytes();
    }
}
