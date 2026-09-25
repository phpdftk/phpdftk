<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Painter-side coverage for `<mtable>` alignment + spacing
 * attributes. The painter resolves the alignment cascade
 * (cell > row > table > center default) and respects
 * `columnspacing` / `rowspacing` lists when computing the grid.
 *
 * We compare the X / Y deltas in the content stream against the
 * default-centered rendering: a wider column gap or a different
 * alignment must shift the cell positions measurably.
 */
final class MtableAlignmentRenderingTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    public function testLeftAlignmentDiffersFromCenter(): void
    {
        // A 2-column row where one cell is much wider than the other:
        // 'X' vs 'XXXXXXXX'. The narrow cell will lead by very
        // different amounts under center vs left alignment.
        $centred = $this->render(
            '<mtable>'
                . '<mtr><mtd><mi>X</mi></mtd><mtd><mn>1</mn></mtd></mtr>'
                . '<mtr><mtd><mi>XXXXXXXX</mi></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '</mtable>',
        );
        $left = $this->render(
            '<mtable columnalign="left">'
                . '<mtr><mtd><mi>X</mi></mtd><mtd><mn>1</mn></mtd></mtr>'
                . '<mtr><mtd><mi>XXXXXXXX</mi></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '</mtable>',
        );
        self::assertNotSame(
            $this->extractTds($centred),
            $this->extractTds($left),
            'left-aligned cells should produce different Td offsets',
        );
    }

    public function testRightAlignmentDiffersFromLeft(): void
    {
        // Each cell should land at the column's right edge instead
        // of the left. Td sequence diverges.
        $left = $this->render(
            '<mtable columnalign="left">'
                . '<mtr><mtd><mi>X</mi></mtd><mtd><mn>1</mn></mtd></mtr>'
                . '<mtr><mtd><mi>XXXXXXXX</mi></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '</mtable>',
        );
        $right = $this->render(
            '<mtable columnalign="right">'
                . '<mtr><mtd><mi>X</mi></mtd><mtd><mn>1</mn></mtd></mtr>'
                . '<mtr><mtd><mi>XXXXXXXX</mi></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '</mtable>',
        );
        self::assertNotSame(
            $this->extractTds($left),
            $this->extractTds($right),
        );
    }

    public function testColumnSpacingChangesTableWidth(): void
    {
        // Default columnspacing is 0.8em; doubling it widens the
        // gap between columns and shifts the second column's cells.
        $defaultGap = $this->render(
            '<mtable>'
                . '<mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '</mtable>',
        );
        $widerGap = $this->render(
            '<mtable columnspacing="2em">'
                . '<mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '</mtable>',
        );
        self::assertNotSame(
            $this->extractTds($defaultGap),
            $this->extractTds($widerGap),
        );
    }

    public function testRowSpacingChangesVerticalLayout(): void
    {
        $defaultRowGap = $this->render(
            '<mtable>'
                . '<mtr><mtd><mn>1</mn></mtd></mtr>'
                . '<mtr><mtd><mn>2</mn></mtd></mtr>'
                . '</mtable>',
        );
        $tallRowGap = $this->render(
            '<mtable rowspacing="3em">'
                . '<mtr><mtd><mn>1</mn></mtd></mtr>'
                . '<mtr><mtd><mn>2</mn></mtd></mtr>'
                . '</mtable>',
        );
        self::assertNotSame(
            $this->extractTds($defaultRowGap),
            $this->extractTds($tallRowGap),
        );
    }

    public function testCellLevelColumnAlignWinsOverTable(): void
    {
        // Table says everything left-aligned, but a single cell
        // overrides to right. Output must differ from the no-
        // override case.
        $tableLeft = $this->render(
            '<mtable columnalign="left">'
                . '<mtr><mtd><mi>X</mi></mtd></mtr>'
                . '<mtr><mtd><mi>XXXXXXXX</mi></mtd></mtr>'
                . '</mtable>',
        );
        $cellOverride = $this->render(
            '<mtable columnalign="left">'
                . '<mtr><mtd columnalign="right"><mi>X</mi></mtd></mtr>'
                . '<mtr><mtd><mi>XXXXXXXX</mi></mtd></mtr>'
                . '</mtable>',
        );
        self::assertNotSame(
            $this->extractTds($tableLeft),
            $this->extractTds($cellOverride),
        );
    }

    public function testRowLevelColumnAlignOverridesTable(): void
    {
        // Table says center; one row overrides to left.
        $tableCenter = $this->render(
            '<mtable>'
                . '<mtr><mtd><mi>X</mi></mtd><mtd><mn>1</mn></mtd></mtr>'
                . '<mtr><mtd><mi>YYYY</mi></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '</mtable>',
        );
        $rowOverride = $this->render(
            '<mtable>'
                . '<mtr columnalign="left left"><mtd><mi>X</mi></mtd><mtd><mn>1</mn></mtd></mtr>'
                . '<mtr><mtd><mi>YYYY</mi></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '</mtable>',
        );
        self::assertNotSame(
            $this->extractTds($tableCenter),
            $this->extractTds($rowOverride),
        );
    }

    public function testUnknownAlignmentDoesNotCrash(): void
    {
        $bytes = $this->render(
            '<mtable columnalign="zebra">'
                . '<mtr><mtd><mn>1</mn></mtd></mtr>'
                . '</mtable>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
    }

    // ---------------------------------------------------------------
    // MathML Core §3.5.1 — an RTL table lays its COLUMNS out
    // right-to-left; rows and cell content keep source order
    // (direction-006).
    // ---------------------------------------------------------------

    private const string TABLE_BODY =
        '<mtable><mtr>'
        . '<mtd><mtext>A</mtext></mtd>'
        . '<mtd><mtext>B</mtext></mtd>'
        . '<mtd><mtext>C</mtext></mtd>'
        . '</mtr></mtable>';

    public function testRtlTableReversesColumnOrder(): void
    {
        $rtl = $this->renderWithDir(self::TABLE_BODY, rtl: true);
        self::assertSame(['C', 'B', 'A'], $this->glyphOrder($rtl, ['A', 'B', 'C']));
    }

    public function testLtrTableKeepsSourceColumnOrder(): void
    {
        $ltr = $this->renderWithDir(self::TABLE_BODY, rtl: false);
        self::assertSame(['A', 'B', 'C'], $this->glyphOrder($ltr, ['A', 'B', 'C']));
    }

    /**
     * An RTL table must match the LTR table whose cells are written
     * in reverse — that identity is exactly what direction-006
     * asserts, and it pins the column POSITIONS, not just the order
     * glyphs happen to be emitted in.
     */
    public function testRtlTableMatchesTheReversedLtrTable(): void
    {
        $rtl = $this->renderWithDir(self::TABLE_BODY, rtl: true);
        $mirrored = $this->renderWithDir(
            '<mtable><mtr>'
            . '<mtd><mtext>C</mtext></mtd>'
            . '<mtd><mtext>B</mtext></mtd>'
            . '<mtd><mtext>A</mtext></mtd>'
            . '</mtr></mtable>',
            rtl: false,
        );
        foreach (['A', 'B', 'C'] as $glyph) {
            self::assertEqualsWithDelta(
                $this->glyphX($mirrored, $glyph),
                $this->glyphX($rtl, $glyph),
                0.01,
                "column holding '$glyph' should be at the mirrored x",
            );
        }
    }

    /**
     * Negative guard: ROWS are a block-axis concern and must NOT be
     * reversed by inline direction.
     */
    public function testRtlTableKeepsRowOrder(): void
    {
        $rtl = $this->renderWithDir(
            '<mtable>'
            . '<mtr><mtd><mtext>A</mtext></mtd></mtr>'
            . '<mtr><mtd><mtext>B</mtext></mtd></mtr>'
            . '</mtable>',
            rtl: true,
        );
        self::assertGreaterThan(
            $this->glyphY($rtl, 'B'),
            $this->glyphY($rtl, 'A'),
            'the first source row stays on top',
        );
    }

    private function renderWithDir(string $innerXml, bool $rtl): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML"'
            . ($rtl ? ' dir="rtl"' : '') . '>' . $innerXml . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $renderer->draw($this->parser->parse($xml), x: 72.0, y: 600.0, width: 400.0, height: 100.0);
        return $writer->toBytes();
    }

    /**
     * @param list<string> $glyphs
     * @return list<string>
     */
    private function glyphOrder(string $bytes, array $glyphs): array
    {
        $pattern = '/\((' . implode('|', array_map(
            static fn(string $g): string => preg_quote($g, '/'),
            $glyphs,
        )) . ')\)\s+Tj/';
        preg_match_all($pattern, $bytes, $matches);
        return $matches[1];
    }

    private function glyphX(string $bytes, string $glyph): float
    {
        return $this->glyphMatrix($bytes, $glyph)[0];
    }

    private function glyphY(string $bytes, string $glyph): float
    {
        return $this->glyphMatrix($bytes, $glyph)[1];
    }

    /** @return array{float, float} */
    private function glyphMatrix(string $bytes, string $glyph): array
    {
        $pattern = '/1 0 0 1 (-?[\d.]+) (-?[\d.]+) Tm\s*(?:\/F\d+ [\d.]+ Tf\s*)*\('
            . preg_quote($glyph, '/') . '\)\s+Tj/';
        self::assertMatchesRegularExpression($pattern, $bytes);
        preg_match($pattern, $bytes, $m);
        return [(float) $m[1], (float) $m[2]];
    }

    private function render(string $innerXml): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $innerXml . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = $this->parser->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 400.0, height: 100.0);
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
