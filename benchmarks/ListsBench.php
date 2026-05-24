<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\ListBlock;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfDoc;

/**
 * List rendering performance across the writer levels.
 *
 * Level 3 (`Pdf::addList`) handles pagination and font resolution
 * automatically. Level 2 (`Writer\Page::drawList`) is single-page,
 * positioned rendering. Both share `ListRenderer` under the hood.
 */
#[Bench\Iterations(5)]
#[Bench\Revs(3)]
class ListsBench
{
    private string $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdftk_bench';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /** @return list<string> */
    private function makeItems(int $count): array
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = sprintf('Item %d — the quick brown fox jumps over the lazy dog.', $i);
        }
        return $items;
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3PdfList10Items(): void
    {
        $this->level3List($this->tempDir . '/lists_l3_10.pdf', 10);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3PdfList100Items(): void
    {
        $this->level3List($this->tempDir . '/lists_l3_100.pdf', 100);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3PdfList500Items(): void
    {
        $this->level3List($this->tempDir . '/lists_l3_500.pdf', 500);
    }

    private function level3List(string $path, int $count): void
    {
        $pdf = new Pdf();
        $pdf->addList($this->makeItems($count));
        $pdf->save($path);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel2PdfDocList10Items(): void
    {
        $this->level2List($this->tempDir . '/lists_l2_10.pdf', 10);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel2PdfDocList100Items(): void
    {
        $this->level2List($this->tempDir . '/lists_l2_100.pdf', 100);
    }

    private function level2List(string $path, int $count): void
    {
        $doc = new PdfDoc();
        $font = $doc->writer()->addFont(new Type1Font(StandardFont::Helvetica));

        $page = $doc->addPage();
        $page->drawList(
            new ListBlock($this->makeItems($count)),
            72.0,
            720.0,
            $font,
            fontSize: 11.0,
            maxWidth: 468.0,
        );
        $doc->writer()->save($path);
    }
}
