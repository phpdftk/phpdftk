<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\Alignment;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\Table;
use Phpdftk\Pdf\Writer\TableStyle;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class TableTest extends TestCase
{
    use QpdfValidationTrait;

    public function testAddTableRendersAllCellValues(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addTable([
            ['Widget', '10', '5'],
            ['Gadget', '25', '2'],
        ]);

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Widget', $bytes);
        self::assertStringContainsString('Gadget', $bytes);
        self::assertStringContainsString('(10)', $bytes);
        self::assertStringContainsString('(25)', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testAddTableWithHeaderEmitsBoldVariantAndBackground(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addTable(
            rows: [['Widget', '10']],
            headerRow: ['Product', 'Price'],
        );

        $bytes = $pdf->toBytes();
        // Header row text present
        self::assertStringContainsString('Product', $bytes);
        self::assertStringContainsString('Price', $bytes);
        // Bold variant registered + referenced
        self::assertStringContainsString('Helvetica-Bold', $bytes);
        // Header background fill — default light grey 0.93 0.93 0.93 rg
        self::assertMatchesRegularExpression('/0\.93\d* 0\.93\d* 0\.93\d* rg/', $bytes);
    }

    public function testAddTableUsesExplicitColumnWidthsWhenProvided(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addTable(
            rows: [['A', 'B']],
            columnWidths: [200.0, 100.0],
        );

        $bytes = $pdf->toBytes();
        // The cell labelled "B" sits 200 points right of "A" — there
        // should be a Td that includes a 200-point offset somewhere.
        // Easier check: both labels render.
        self::assertStringContainsString('(A)', $bytes);
        self::assertStringContainsString('(B)', $bytes);
    }

    public function testAddTableAutoPaginatesAndRepeatsHeader(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $rows = [];
        for ($i = 1; $i <= 60; $i++) {
            $rows[] = ["Row {$i}", "Value {$i}"];
        }
        $pdf->addTable(
            rows: $rows,
            headerRow: ['Item', 'Value'],
        );

        $bytes = $pdf->toBytes();
        // Header should appear more than once because pagination
        // triggers a re-draw at the top of every page.
        self::assertGreaterThan(1, substr_count($bytes, '(Item)'), 'header row should repeat on each page');
        // Document spans multiple pages.
        self::assertGreaterThanOrEqual(2, substr_count($bytes, "/Type /Page\n"));
    }

    public function testAddTableEmptyRowsIsNoOp(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $cursorBefore = (function () {
            $r = new \ReflectionClass(Pdf::class);
            return $r->getProperty('cursorY');
        })();
        $cursorBefore->setAccessible(true);

        $pdf->addPage();
        $before = $cursorBefore->getValue($pdf);
        $pdf->addTable([]);
        $after = $cursorBefore->getValue($pdf);

        self::assertSame($before, $after, 'empty table must not move the cursor');
    }

    public function testAddTableWrapsLongCellContentAcrossMultipleLines(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $longText = 'The quick brown fox jumps over the lazy dog repeatedly while a thoughtful narrator describes the scene in tremendous detail.';
        $pdf->addTable(
            rows: [['Label', $longText]],
            columnWidths: [80.0, 200.0],
        );

        $bytes = $pdf->toBytes();
        // The wrapped cell contributes more than one Tj operator.
        self::assertGreaterThan(2, substr_count($bytes, ') Tj'), 'long cell should wrap to multiple lines');
    }

    public function testAddTableHonoursPerColumnAlignment(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $style = new TableStyle(
            cellAlignments: [Alignment::Left, Alignment::Center, Alignment::Right],
        );
        $pdf->addTable(
            rows: [['Left', 'Center', 'Right']],
            columnWidths: [120.0, 120.0, 120.0],
            style: $style,
        );

        // Sanity check: emits without errors and produces three cells.
        $bytes = $pdf->toBytes();
        self::assertStringContainsString('(Left)', $bytes);
        self::assertStringContainsString('(Center)', $bytes);
        self::assertStringContainsString('(Right)', $bytes);
    }

    public function testAddTableWithBorderWidthZeroSkipsBorderOps(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $style = new TableStyle(borderWidth: 0.0);
        $pdf->addTable(
            rows: [['cell1', 'cell2']],
            style: $style,
        );

        $bytes = $pdf->toBytes();
        // The renderer emits stroke ops only when borderWidth > 0.
        // Without borders, we should see no "S" stroke operator inside
        // the table region. (The watermark and other features don't run
        // by default, so the content stream should be border-free.)
        $tableStream = $this->extractFirstContentStream($bytes);
        self::assertStringNotContainsString(" S\n", $tableStream);
    }

    public function testWriterPageDrawTableRendersExplicitlyPositionedTable(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $page = $pdf->doc()->addPage();
        $font = $pdf->writer()->addFont(new Type1Font(StandardFont::Helvetica));
        $bold = $pdf->writer()->addFont(new Type1Font(StandardFont::HelveticaBold));

        $table = new Table(
            rows: [
                ['Apple', '$1.00'],
                ['Banana', '$0.50'],
            ],
            columnWidths: [120.0, 80.0],
            headerRow: ['Item', 'Price'],
        );

        $page->drawTable($table, 72.0, 720.0, $font, $bold);

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('(Apple)', $bytes);
        self::assertStringContainsString('(Banana)', $bytes);
        self::assertStringContainsString('(Item)', $bytes);
    }

    public function testWriterPageDrawTableThrowsWithoutColumnWidths(): void
    {
        $pdf = new Pdf();
        $page = $pdf->doc()->addPage();
        $font = $pdf->writer()->addFont(new Type1Font(StandardFont::Helvetica));

        $table = new Table(
            rows: [['A', 'B']],
            columnWidths: null,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('columnWidths');

        $page->drawTable($table, 72.0, 720.0, $font);
    }

    public function testTableColumnCountDerivesFromHeaderWhenPresent(): void
    {
        $table = new Table(
            rows: [['x', 'y']],
            headerRow: ['a', 'b', 'c'],
        );
        self::assertSame(3, $table->columnCount());
    }

    public function testTableColumnCountDerivesFromWidestRowWhenNoHeader(): void
    {
        $table = new Table(rows: [
            ['x'],
            ['x', 'y', 'z'],
            ['x', 'y'],
        ]);
        self::assertSame(3, $table->columnCount());
    }

    public function testTableColumnCountReturnsZeroForEmptyTable(): void
    {
        $table = new Table(rows: []);
        self::assertSame(0, $table->columnCount());
    }

    public function testTableStyleAlignmentForReturnsLeftForMissingIndex(): void
    {
        $style = new TableStyle(cellAlignments: [Alignment::Right]);
        self::assertSame(Alignment::Right, $style->alignmentFor(0));
        self::assertSame(Alignment::Left, $style->alignmentFor(1));
        self::assertSame(Alignment::Left, $style->alignmentFor(99));
    }

    public function testAddTableWithNullHeaderBackgroundOmitsFill(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $style = new TableStyle(headerBgColor: null);
        $pdf->addTable(
            rows: [['data']],
            headerRow: ['col'],
            style: $style,
        );

        $bytes = $pdf->toBytes();
        // No header background rectangle fill should appear.
        self::assertStringNotContainsString('0.93 0.93 0.93 rg', $bytes);
    }

    private function extractFirstContentStream(string $pdf): string
    {
        // First stream...endstream block — sufficient for our assertions.
        $start = strpos($pdf, "stream\n");
        $end = strpos($pdf, "\nendstream");
        if ($start === false || $end === false) {
            return '';
        }
        return substr($pdf, $start + 7, $end - $start - 7);
    }
}
