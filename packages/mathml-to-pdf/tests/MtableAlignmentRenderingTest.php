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
        if (!preg_match_all('/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+Td\b/', $bytes, $matches)) {
            return [];
        }
        $out = [];
        foreach ($matches[1] as $i => $dx) {
            $out[] = [(float) $dx, (float) $matches[2][$i]];
        }
        return $out;
    }
}
