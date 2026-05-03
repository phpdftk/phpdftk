<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PdfWriter;

/**
 * Memory-focused benchmarks.
 *
 * Measures peak memory usage for generating PDFs with varying page counts.
 * phpbench records peak memory automatically via --report=memory (if configured).
 * Each bench method also manually captures and stores the delta for inspection.
 */
#[Bench\Iterations(3)]
#[Bench\Revs(2)]
class MemoryBench
{
    private string $tempDir;

    /** @var array<string, int> Stores memory deltas across runs for reference */
    private array $memoryDeltas = [];

    #[Bench\BeforeMethods('setUp')]
    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdftk_memory_bench';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    // -----------------------------------------------------------------------
    // phpdftk — varying page counts
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk1Page(): void
    {
        $this->generatePhpdftk(1, 'phpdftk_mem_1page.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk5Pages(): void
    {
        $this->generatePhpdftk(5, 'phpdftk_mem_5pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10Pages(): void
    {
        $this->generatePhpdftk(10, 'phpdftk_mem_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk50Pages(): void
    {
        $this->generatePhpdftk(50, 'phpdftk_mem_50pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk100Pages(): void
    {
        $this->generatePhpdftk(100, 'phpdftk_mem_100pages.pdf');
    }

    // -----------------------------------------------------------------------
    // TCPDF — varying page counts
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf1Page(): void
    {
        $this->generateTcpdf(1, 'tcpdf_mem_1page.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf5Pages(): void
    {
        $this->generateTcpdf(5, 'tcpdf_mem_5pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf10Pages(): void
    {
        $this->generateTcpdf(10, 'tcpdf_mem_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf50Pages(): void
    {
        $this->generateTcpdf(50, 'tcpdf_mem_50pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf100Pages(): void
    {
        $this->generateTcpdf(100, 'tcpdf_mem_100pages.pdf');
    }

    // -----------------------------------------------------------------------
    // FPDF — varying page counts
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf1Page(): void
    {
        $this->generateFpdf(1, 'fpdf_mem_1page.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf5Pages(): void
    {
        $this->generateFpdf(5, 'fpdf_mem_5pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf10Pages(): void
    {
        $this->generateFpdf(10, 'fpdf_mem_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf50Pages(): void
    {
        $this->generateFpdf(50, 'fpdf_mem_50pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf100Pages(): void
    {
        $this->generateFpdf(100, 'fpdf_mem_100pages.pdf');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function generatePhpdftk(int $pages, string $filename): void
    {
        $memBefore = memory_get_peak_usage(true);

        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        for ($i = 1; $i <= $pages; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('phpdftk — Page %d of %d', $i, $pages))
               ->moveTextPosition(0, -18)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->moveTextPosition(0, -18)
               ->showText('Pack my box with five dozen liquor jugs.')
               ->endText();
        }

        $writer->save($this->tempDir . '/' . $filename);

        $memAfter = memory_get_peak_usage(true);
        $this->memoryDeltas[$filename] = $memAfter - $memBefore;
    }

    private function generateTcpdf(int $pages, string $filename): void
    {
        if (!class_exists(\TCPDF::class)) {
            return;
        }

        $memBefore = memory_get_peak_usage(true);

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('phpdftk memory benchmark');
        $pdf->SetFont('helvetica', '', 12);

        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('TCPDF — Page %d of %d', $i, $pages), 0, 1);
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.', 0, 1);
            $pdf->Cell(0, 10, 'Pack my box with five dozen liquor jugs.', 0, 1);
        }

        $pdf->Output($this->tempDir . '/' . $filename, 'F');

        $memAfter = memory_get_peak_usage(true);
        $this->memoryDeltas[$filename] = $memAfter - $memBefore;
    }

    private function generateFpdf(int $pages, string $filename): void
    {
        if (!class_exists(\FPDF::class)) {
            return;
        }

        $memBefore = memory_get_peak_usage(true);

        $pdf = new \FPDF();
        $pdf->SetFont('Helvetica', '', 12);

        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('FPDF — Page %d of %d', $i, $pages));
            $pdf->Ln();
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.');
            $pdf->Ln();
            $pdf->Cell(0, 10, 'Pack my box with five dozen liquor jugs.');
        }

        $pdf->Output('F', $this->tempDir . '/' . $filename);

        $memAfter = memory_get_peak_usage(true);
        $this->memoryDeltas[$filename] = $memAfter - $memBefore;
    }

    /**
     * Return the collected memory deltas (useful for inspection in tests).
     *
     * @return array<string, int>
     */
    public function getMemoryDeltas(): array
    {
        return $this->memoryDeltas;
    }
}
