<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfDoc;
use Phpdftk\Pdf\Writer\Table;

/**
 * Table rendering performance across the writer levels.
 *
 * Level 3 (`Pdf::addTable`) handles pagination and font resolution
 * automatically. Level 2 (`Writer\Page::drawTable`) is single-page,
 * positioned rendering with caller-supplied font handles. Both share
 * `TableRenderer` under the hood; the difference shown here is the
 * overhead of the flow-layout engine vs. positioned drawing.
 *
 * Subjects are named `benchLevelN…` so the parser groups them in the
 * Writer Levels Comparison section of the benchmarks report.
 */
#[Bench\Iterations(5)]
#[Bench\Revs(3)]
class TablesBench
{
    private string $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdftk_bench';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /** @return list<list<string>> */
    private function makeRows(int $count): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                sprintf('Row %d', $i),
                'The quick brown fox',
                sprintf('%d', $i * 7),
                sprintf('$%d.00', $i * 12),
            ];
        }
        return $rows;
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3PdfTable10Rows(): void
    {
        $this->level3Table($this->tempDir . '/tables_l3_10.pdf', 10);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3PdfTable100Rows(): void
    {
        $this->level3Table($this->tempDir . '/tables_l3_100.pdf', 100);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3PdfTable500Rows(): void
    {
        $this->level3Table($this->tempDir . '/tables_l3_500.pdf', 500);
    }

    private function level3Table(string $path, int $rows): void
    {
        $pdf = new Pdf();
        $pdf->addTable(
            rows: $this->makeRows($rows),
            columnWidths: [80.0, 200.0, 60.0, 80.0],
            headerRow: ['Row', 'Description', 'Qty', 'Price'],
        );
        $pdf->save($path);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel2PdfDocTable10Rows(): void
    {
        $this->level2Table($this->tempDir . '/tables_l2_10.pdf', 10);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel2PdfDocTable100Rows(): void
    {
        $this->level2Table($this->tempDir . '/tables_l2_100.pdf', 100);
    }

    private function level2Table(string $path, int $rows): void
    {
        $doc = new PdfDoc();
        $body = $doc->writer()->addFont(new Type1Font(StandardFont::Helvetica));
        $bold = $doc->writer()->addFont(new Type1Font(StandardFont::HelveticaBold));

        $table = new Table(
            rows: $this->makeRows($rows),
            columnWidths: [80.0, 200.0, 60.0, 80.0],
            headerRow: ['Row', 'Description', 'Qty', 'Price'],
        );

        $page = $doc->addPage();
        $page->drawTable($table, 72.0, 720.0, $body, $bold);

        $doc->writer()->save($path);
    }
}
