<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfDoc;
use Phpdftk\Pdf\Writer\PdfWriter;

/**
 * Side-by-side comparison of the three writer levels generating the
 * same conceptual workload: N pages with a heading and a paragraph of
 * body text. Each level uses its idiomatic API:
 *
 *   - Level 1 (PdfWriter): manual font registration, content stream, raw text operators.
 *   - Level 2 (PdfDoc): explicit page positioning via Writer\Page::drawText with
 *                       a registered Font handle.
 *   - Level 3 (Pdf): flow-layout addHeading / addText.
 *
 * Subjects are named `benchLevelN_<pages>` so the parser groups them
 * into a "Writer Levels" section of the benchmarks table, letting each
 * level be compared at scale and across levels.
 */
#[Bench\Iterations(5)]
#[Bench\Revs(3)]
class WriterLevelsBench
{
    private string $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdftk_bench';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    // ----------------------------------------------------------------------
    // Level 1 — PdfWriter (raw content streams)
    // ----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel1PdfWriter1Page(): void
    {
        $this->level1($this->tempDir . '/level1_1page.pdf', 1);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel1PdfWriter10Pages(): void
    {
        $this->level1($this->tempDir . '/level1_10pages.pdf', 10);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel1PdfWriter100Pages(): void
    {
        $this->level1($this->tempDir . '/level1_100pages.pdf', 100);
    }

    private function level1(string $path, int $pages): void
    {
        $writer = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        for ($i = 1; $i <= $pages; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 18)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Chapter %d', $i))
               ->endText();
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 690)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();
        }

        $writer->save($path);
    }

    // ----------------------------------------------------------------------
    // Level 2 — PdfDoc (friendly catalog wrappers + Writer\Page drawText)
    // ----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel2PdfDoc1Page(): void
    {
        $this->level2($this->tempDir . '/level2_1page.pdf', 1);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel2PdfDoc10Pages(): void
    {
        $this->level2($this->tempDir . '/level2_10pages.pdf', 10);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel2PdfDoc100Pages(): void
    {
        $this->level2($this->tempDir . '/level2_100pages.pdf', 100);
    }

    private function level2(string $path, int $pages): void
    {
        $doc = new PdfDoc();
        $font = $doc->writer()->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= $pages; $i++) {
            $page = $doc->addPage();
            $page->drawText(sprintf('Chapter %d', $i), 72.0, 720.0, $font, 18.0);
            $page->drawText('The quick brown fox jumps over the lazy dog.', 72.0, 690.0, $font, 12.0);
        }

        $doc->writer()->save($path);
    }

    // ----------------------------------------------------------------------
    // Level 3 — Pdf (flow-layout addHeading / addText)
    // ----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3Pdf1Page(): void
    {
        $this->level3($this->tempDir . '/level3_1page.pdf', 1);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3Pdf10Pages(): void
    {
        $this->level3($this->tempDir . '/level3_10pages.pdf', 10);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3Pdf100Pages(): void
    {
        $this->level3($this->tempDir . '/level3_100pages.pdf', 100);
    }

    private function level3(string $path, int $pages): void
    {
        $pdf = new Pdf();
        for ($i = 1; $i <= $pages; $i++) {
            if ($i > 1) {
                $pdf->newPage();
            }
            $pdf->addHeading(sprintf('Chapter %d', $i), 2);
            $pdf->addText('The quick brown fox jumps over the lazy dog.');
        }
        $pdf->save($path);
    }
}
