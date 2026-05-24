<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class MultiColumnTest extends TestCase
{
    use QpdfValidationTrait;

    public function testSetColumnsRequiresAtLeastOne(): void
    {
        $pdf = new Pdf();
        $this->expectException(\InvalidArgumentException::class);
        $pdf->setColumns(0);
    }

    public function testSetColumnsRejectsNegativeGutter(): void
    {
        $pdf = new Pdf();
        $this->expectException(\InvalidArgumentException::class);
        $pdf->setColumns(2, -1.0);
    }

    public function testSingleColumnPreservesExistingBehavior(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setColumns(1);
        $pdf->addText('Hello world');
        $bytes = $pdf->toBytes();
        // Default margin = 72 → first text X = 72.
        self::assertMatchesRegularExpression('/\n72 \d+(?:\.\d+)? Td\s+\(Hello world\)/', $bytes);
    }

    public function testTwoColumnLayoutShiftsSecondColumnRightOfFirst(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setColumns(2, 12.0);

        // Letter content width = 612 - 144 = 468.
        // Each column = (468 - 12) / 2 = 228.
        // Column 0 starts at x = 72; column 1 at x = 72 + 228 + 12 = 312.

        $pdf->addText('First column');
        $bytes = $pdf->toBytes();
        // First addText lands at x = 72 (column 0).
        self::assertMatchesRegularExpression('/\n72 \d+(?:\.\d+)? Td\s+\(First column\)/', $bytes);
    }

    public function testColumnAdvancesBeforeNewPage(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setColumns(2, 12.0);

        // Add enough text to fill column 0 first.
        for ($i = 0; $i < 60; $i++) {
            $pdf->addText("Paragraph {$i} — quick brown fox.");
        }

        $bytes = $pdf->toBytes();
        // The content should overflow into column 1 BEFORE creating page 2.
        // Find the first text-position X coordinate after the bulk that's
        // significantly to the right (column 1 X ≈ 312).
        self::assertMatchesRegularExpression('/\n312 \d+(?:\.\d+)? Td/', $bytes);
        // Still one page if the column 1 absorbs the overflow.
        self::assertSame(1, substr_count($bytes, "/Type /Page\n"));
    }

    public function testThreeColumnsFillsAllBeforePaging(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setColumns(3, 12.0);
        for ($i = 0; $i < 80; $i++) {
            $pdf->addText("Item {$i}.");
        }
        $bytes = $pdf->toBytes();
        // Column widths: (468 - 24) / 3 = 148.
        // Col 0 X = 72, col 1 X = 232, col 2 X = 392.
        self::assertMatchesRegularExpression('/\n72 \d+(?:\.\d+)? Td/', $bytes);
        self::assertMatchesRegularExpression('/\n232 \d+(?:\.\d+)? Td/', $bytes);
        self::assertMatchesRegularExpression('/\n392 \d+(?:\.\d+)? Td/', $bytes);
    }

    public function testPageBreakAfterLastColumnResetsToColumnZero(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setColumns(2, 12.0);
        for ($i = 0; $i < 200; $i++) {
            $pdf->addText("Bulk paragraph {$i} — fills the pages quickly.");
        }
        $bytes = $pdf->toBytes();
        // Should produce more than one page.
        self::assertGreaterThanOrEqual(2, substr_count($bytes, "/Type /Page\n"));
        // First column of every page lands at X = 72.
        self::assertGreaterThanOrEqual(2, substr_count($bytes, "\n72 "));
    }

    public function testRuleStaysInsideCurrentColumn(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setColumns(2, 12.0);
        $pdf->addRule();
        $bytes = $pdf->toBytes();
        // Rule spans 72..300 (column 0). Look for the line operator.
        self::assertMatchesRegularExpression('/72 \d+(?:\.\d+)? m\s+300 \d+(?:\.\d+)? l/', $bytes);
    }

    public function testAddPageResetsCurrentColumnToZero(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setColumns(2, 12.0);
        // Fill column 0 so next content lands in column 1.
        for ($i = 0; $i < 60; $i++) {
            $pdf->addText("Filler {$i}.");
        }
        // Explicit page break should reset to column 0.
        $pdf->newPage();
        $pdf->addText('After break');

        $bytes = $pdf->toBytes();
        // The "After break" text should land at the column-0 X (72).
        self::assertMatchesRegularExpression('/\n72 \d+(?:\.\d+)? Td\s+\(After break\)/', $bytes);
    }

    public function testTableInColumnRespectsColumnWidth(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setColumns(2, 12.0);
        $pdf->addTable([
            ['a', 'b'],
            ['c', 'd'],
        ]);
        $bytes = $pdf->toBytes();
        // Cells are rendered; first cell at col 0 X = 72.
        self::assertStringContainsString('(a)', $bytes);
        self::assertStringContainsString('(b)', $bytes);
    }

    public function testSetColumnsBackToOneResumesFullWidth(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setColumns(2, 12.0);
        $pdf->addText('Two-column paragraph.');
        $pdf->setColumns(1);
        $pdf->addRule();
        $bytes = $pdf->toBytes();
        // Rule spans full width after reverting to single column.
        // 72 → 540 (612 - 72).
        self::assertMatchesRegularExpression('/72 \d+(?:\.\d+)? m\s+540 \d+(?:\.\d+)? l/', $bytes);
    }
}
