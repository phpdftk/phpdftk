<?php

declare(strict_types=1);

namespace ApprLabs\Benchmarks;

use PhpBench\Attributes as Bench;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\Document\Outline;
use ApprLabs\Pdf\Core\Document\OutlineItem;
use ApprLabs\Pdf\Core\Document\TransitionDict;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Writer\PdfWriter;

#[Bench\Iterations(5)]
#[Bench\Revs(3)]
class GeneratePdfBench
{
    private string $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdftk_bench';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    // -----------------------------------------------------------------------
    // phpdftk benchmarks
    // -----------------------------------------------------------------------

    /**
     * @BeforeMethods({"setUp"})
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk1Page(): void
    {
        $writer = new PdfWriter();
        $page   = $writer->addPage(612, 792);
        $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $cs = $writer->addContentStream($page);
        $cs->beginText()
           ->setFont('F1', 12)
           ->moveTextPosition(72, 720)
           ->showText('Hello World - phpdftk benchmark 1 page')
           ->endText();

        $writer->save($this->tempDir . '/phpdftk_1page.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk5Pages(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= 5; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Page %d of 5 — phpdftk benchmark', $i))
               ->moveTextPosition(0, -20)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_5pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10Pages(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Page %d of 10 — phpdftk benchmark', $i))
               ->moveTextPosition(0, -20)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk50Pages(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= 50; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Page %d of 50 — phpdftk benchmark', $i))
               ->moveTextPosition(0, -20)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_50pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk100Pages(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= 100; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Page %d of 100 — phpdftk benchmark', $i))
               ->moveTextPosition(0, -20)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_100pages.pdf');
    }

    /**
     * 10-page PDF with bookmarks (Outline + OutlineItems) and page transitions.
     * Exercises Tier 1 & 2 spec additions without competitors.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithBookmarksAndTransitions(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $outline  = $writer->setOutline(new Outline());
        $prevRef  = null;

        for ($i = 1; $i <= 10; $i++) {
            $transition = new TransitionDict();
            $transition->s = new PdfName('Dissolve');
            $transition->d = new PdfNumber(0.5);

            $page = $writer->addPage(612, 792);
            $page->transition = $transition;
            $page->dur        = new PdfNumber(5.0);

            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Chapter %d', $i))
               ->moveTextPosition(0, -20)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();

            $item = new OutlineItem(sprintf('Chapter %d', $i));
            $item->dest = new PdfName('ch' . $i);
            if ($prevRef !== null) {
                $item->prev = $prevRef;
            }
            $ref = $writer->addOutlineItem($item);
            if ($prevRef !== null) {
                // back-patch next on previous item (object already registered; just update property)
            }
            if ($i === 1) {
                $outline->first = $ref;
            }
            $outline->last = $ref;
            $outline->count = $i;
            $prevRef = $ref;
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_bookmarks.pdf');
    }

    // -----------------------------------------------------------------------
    // TCPDF benchmarks (if available)
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf1Page(): void
    {
        if (!class_exists(\TCPDF::class)) {
            return;
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('phpdftk benchmark');
        $pdf->SetAuthor('benchmark');
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Hello World - TCPDF benchmark 1 page', 0, 1, 'L');
        $pdf->Output($this->tempDir . '/tcpdf_1page.pdf', 'F');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf5Pages(): void
    {
        if (!class_exists(\TCPDF::class)) {
            return;
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('phpdftk benchmark');
        $pdf->SetFont('helvetica', '', 12);

        for ($i = 1; $i <= 5; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 5 - TCPDF benchmark', $i), 0, 1, 'L');
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.', 0, 1, 'L');
        }

        $pdf->Output($this->tempDir . '/tcpdf_5pages.pdf', 'F');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf10Pages(): void
    {
        if (!class_exists(\TCPDF::class)) {
            return;
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('phpdftk benchmark');
        $pdf->SetFont('helvetica', '', 12);

        for ($i = 1; $i <= 10; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 10 - TCPDF benchmark', $i), 0, 1, 'L');
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.', 0, 1, 'L');
        }

        $pdf->Output($this->tempDir . '/tcpdf_10pages.pdf', 'F');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf50Pages(): void
    {
        if (!class_exists(\TCPDF::class)) {
            return;
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('phpdftk benchmark');
        $pdf->SetFont('helvetica', '', 12);

        for ($i = 1; $i <= 50; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 50 - TCPDF benchmark', $i), 0, 1, 'L');
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.', 0, 1, 'L');
        }

        $pdf->Output($this->tempDir . '/tcpdf_50pages.pdf', 'F');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf100Pages(): void
    {
        if (!class_exists(\TCPDF::class)) {
            return;
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('phpdftk benchmark');
        $pdf->SetFont('helvetica', '', 12);

        for ($i = 1; $i <= 100; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 100 - TCPDF benchmark', $i), 0, 1, 'L');
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.', 0, 1, 'L');
        }

        $pdf->Output($this->tempDir . '/tcpdf_100pages.pdf', 'F');
    }

    // -----------------------------------------------------------------------
    // FPDF benchmarks (if available)
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf1Page(): void
    {
        if (!class_exists(\FPDF::class)) {
            return;
        }

        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Hello World - FPDF benchmark 1 page');
        $pdf->Output('F', $this->tempDir . '/fpdf_1page.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf5Pages(): void
    {
        if (!class_exists(\FPDF::class)) {
            return;
        }

        $pdf = new \FPDF();
        $pdf->SetFont('Helvetica', '', 12);

        for ($i = 1; $i <= 5; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 5 - FPDF benchmark', $i));
            $pdf->Ln();
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.');
        }

        $pdf->Output('F', $this->tempDir . '/fpdf_5pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf10Pages(): void
    {
        if (!class_exists(\FPDF::class)) {
            return;
        }

        $pdf = new \FPDF();
        $pdf->SetFont('Helvetica', '', 12);

        for ($i = 1; $i <= 10; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 10 - FPDF benchmark', $i));
            $pdf->Ln();
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.');
        }

        $pdf->Output('F', $this->tempDir . '/fpdf_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf50Pages(): void
    {
        if (!class_exists(\FPDF::class)) {
            return;
        }

        $pdf = new \FPDF();
        $pdf->SetFont('Helvetica', '', 12);

        for ($i = 1; $i <= 50; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 50 - FPDF benchmark', $i));
            $pdf->Ln();
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.');
        }

        $pdf->Output('F', $this->tempDir . '/fpdf_50pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf100Pages(): void
    {
        if (!class_exists(\FPDF::class)) {
            return;
        }

        $pdf = new \FPDF();
        $pdf->SetFont('Helvetica', '', 12);

        for ($i = 1; $i <= 100; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 100 - FPDF benchmark', $i));
            $pdf->Ln();
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.');
        }

        $pdf->Output('F', $this->tempDir . '/fpdf_100pages.pdf');
    }

    // -----------------------------------------------------------------------
    // mPDF benchmarks (if available)
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdf1Page(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $mpdf->WriteHTML('<p>Hello World - mPDF benchmark 1 page</p>');
        $mpdf->Output($this->tempDir . '/mpdf_1page.pdf', \Mpdf\Output\Destination::FILE);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdf5Pages(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            $html .= sprintf('<p>Page %d of 5 - mPDF benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 5) {
                $html .= '<pagebreak/>';
            }
        }
        $mpdf->WriteHTML($html);
        $mpdf->Output($this->tempDir . '/mpdf_5pages.pdf', \Mpdf\Output\Destination::FILE);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdf10Pages(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $html = '';
        for ($i = 1; $i <= 10; $i++) {
            $html .= sprintf('<p>Page %d of 10 - mPDF benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 10) {
                $html .= '<pagebreak/>';
            }
        }
        $mpdf->WriteHTML($html);
        $mpdf->Output($this->tempDir . '/mpdf_10pages.pdf', \Mpdf\Output\Destination::FILE);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdf50Pages(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $html = '';
        for ($i = 1; $i <= 50; $i++) {
            $html .= sprintf('<p>Page %d of 50 - mPDF benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 50) {
                $html .= '<pagebreak/>';
            }
        }
        $mpdf->WriteHTML($html);
        $mpdf->Output($this->tempDir . '/mpdf_50pages.pdf', \Mpdf\Output\Destination::FILE);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdf100Pages(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $html = '';
        for ($i = 1; $i <= 100; $i++) {
            $html .= sprintf('<p>Page %d of 100 - mPDF benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 100) {
                $html .= '<pagebreak/>';
            }
        }
        $mpdf->WriteHTML($html);
        $mpdf->Output($this->tempDir . '/mpdf_100pages.pdf', \Mpdf\Output\Destination::FILE);
    }

    // -----------------------------------------------------------------------
    // Dompdf benchmarks (if available)
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdf1Page(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml('<p>Hello World - Dompdf benchmark 1 page</p>');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_1page.pdf', $dompdf->output());
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdf5Pages(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }

        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            $html .= sprintf('<p>Page %d of 5 - Dompdf benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 5) {
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_5pages.pdf', $dompdf->output());
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdf10Pages(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }

        $html = '';
        for ($i = 1; $i <= 10; $i++) {
            $html .= sprintf('<p>Page %d of 10 - Dompdf benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 10) {
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_10pages.pdf', $dompdf->output());
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdf50Pages(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }

        $html = '';
        for ($i = 1; $i <= 50; $i++) {
            $html .= sprintf('<p>Page %d of 50 - Dompdf benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 50) {
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_50pages.pdf', $dompdf->output());
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdf100Pages(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }

        $html = '';
        for ($i = 1; $i <= 100; $i++) {
            $html .= sprintf('<p>Page %d of 100 - Dompdf benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 100) {
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_100pages.pdf', $dompdf->output());
    }
}
