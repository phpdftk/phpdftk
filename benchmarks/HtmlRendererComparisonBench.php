<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Pdf\Writer\PdfWriter;

/**
 * Head-to-head HTML-to-PDF benchmark across phpdftk / dompdf / mpdf.
 *
 * Every subject feeds the SAME HTML+CSS string to one renderer. The
 * three fixtures grow in size so the slope is visible, and they use
 * only features that all three renderers can render reasonably (basic
 * HTML, simple CSS, tables, lists, fonts) so the comparison isn't
 * skewed by feature parity.
 *
 * Phase-2 features that only phpdftk supports (Grid, flex, transforms,
 * advanced gradients) are intentionally absent from these fixtures.
 * `RendererBench` measures those in isolation against the project's
 * own regression baseline.
 *
 * Run with: `vendor/bin/phpbench run benchmarks/HtmlRendererComparisonBench.php --report=default`
 */
#[Bench\Iterations(3)]
#[Bench\Revs(3)]
class HtmlRendererComparisonBench
{
    private string $tempDir = '';
    private Renderer $renderer;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdftk_html_cmp_bench';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0o755, true);
        }
        $this->renderer = new Renderer(new RendererOptions());
    }

    // -----------------------------------------------------------------------
    // Fixtures — all features used here render in all three renderers.
    // -----------------------------------------------------------------------

    private function smallInvoice(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html><head><style>
body { font-family: sans-serif; font-size: 11pt; color: #222; margin: 36pt; }
h1 { font-size: 22pt; margin: 0 0 12pt 0; color: #336699; }
h2 { font-size: 14pt; margin: 18pt 0 6pt 0; }
table { width: 100%; border-collapse: collapse; margin-top: 12pt; }
th, td { border: 1px solid #ccc; padding: 6pt 8pt; text-align: left; }
th { background-color: #f0f0f5; font-weight: bold; }
.right { text-align: right; }
.total { font-weight: bold; background-color: #fafafa; }
.address { color: #555; line-height: 1.4; }
</style></head><body>
<h1>Invoice #INV-2026-0042</h1>
<p class="address">Acme Corporation<br>123 Main Street<br>Anytown, CA 94000</p>
<h2>Bill To</h2>
<p class="address">Globex Industries<br>456 Market Avenue<br>Metro City, NY 10001</p>
<h2>Line Items</h2>
<table>
<tr><th>Description</th><th class="right">Quantity</th><th class="right">Unit Price</th><th class="right">Total</th></tr>
<tr><td>Widget Assembly Kit</td><td class="right">10</td><td class="right">$45.00</td><td class="right">$450.00</td></tr>
<tr><td>Premium Service Plan</td><td class="right">1</td><td class="right">$200.00</td><td class="right">$200.00</td></tr>
<tr><td>Installation Labor</td><td class="right">4</td><td class="right">$85.00</td><td class="right">$340.00</td></tr>
<tr><td>Extended Warranty (3yr)</td><td class="right">1</td><td class="right">$120.00</td><td class="right">$120.00</td></tr>
<tr class="total"><td colspan="3" class="right">Subtotal</td><td class="right">$1,110.00</td></tr>
<tr class="total"><td colspan="3" class="right">Tax (8%)</td><td class="right">$88.80</td></tr>
<tr class="total"><td colspan="3" class="right">Total Due</td><td class="right">$1,198.80</td></tr>
</table>
<h2>Payment Terms</h2>
<p>Net 30 days. Late payments subject to 1.5% monthly finance charge.</p>
</body></html>
HTML;
    }

    private function mediumArticle(): string
    {
        $sections = '';
        for ($i = 1; $i <= 12; $i++) {
            $sections .= sprintf(
                '<h2>Section %d</h2>'
                . '<p>The %s species exhibits a notable preference for arboreal habitats. '
                . 'Field observations from <strong>2024–2026</strong> indicate that <em>habitat fragmentation</em> '
                . 'has measurably reduced breeding success across the surveyed range. '
                . 'Detailed counts appear in the accompanying table.</p>'
                . '<ul>'
                . '<li>Western lowland population: stable to increasing</li>'
                . '<li>Eastern upland population: declining (year-over-year)</li>'
                . '<li>Coastal subpopulation: data-deficient</li>'
                . '</ul>'
                . '<p>Continued monitoring is recommended through the 2027 nesting season.</p>',
                $i,
                ['adaptable', 'reclusive', 'territorial', 'migratory'][$i % 4],
            );
        }
        return <<<HTML
<!DOCTYPE html>
<html><head><style>
body { font-family: serif; font-size: 11pt; color: #1a1a1a; margin: 48pt; line-height: 1.5; }
h1 { font-size: 24pt; margin-bottom: 6pt; }
h2 { font-size: 14pt; margin: 18pt 0 6pt 0; color: #444; }
p { margin: 0 0 8pt 0; }
ul { margin: 0 0 12pt 24pt; }
strong { color: #336699; }
em { color: #883366; }
.subtitle { font-size: 13pt; color: #555; margin-bottom: 24pt; }
</style></head><body>
<h1>Field Survey Report</h1>
<p class="subtitle">Comparative population dynamics across surveyed regions, 2024–2026.</p>
{$sections}
</body></html>
HTML;
    }

    private function longReport(): string
    {
        $paragraphs = '';
        for ($i = 1; $i <= 60; $i++) {
            $paragraphs .= sprintf(
                '<h3>%d. Observation Detail</h3>'
                . '<p>Sampling at point <strong>P%03d</strong> recorded %d individuals across '
                . 'a %d-hour window. Activity peaked during the dawn period (05:30–07:00 local). '
                . 'Wind conditions were calm with intermittent precipitation. Habitat composition: '
                . '<em>mixed deciduous (60%%), conifer (30%%), open meadow (10%%)</em>.</p>'
                . '<table style="width: 70%%; margin-bottom: 12pt;">'
                . '<tr><th>Hour</th><th>Count</th><th>Notes</th></tr>'
                . '<tr><td>05:30</td><td>%d</td><td>Initial group sighting</td></tr>'
                . '<tr><td>06:00</td><td>%d</td><td>Joined by second cohort</td></tr>'
                . '<tr><td>06:30</td><td>%d</td><td>Peak activity period</td></tr>'
                . '<tr><td>07:00</td><td>%d</td><td>Dispersal begins</td></tr>'
                . '</table>',
                $i,
                $i,
                12 + ($i % 7),
                4 + ($i % 3),
                3 + ($i % 4),
                5 + ($i % 5),
                8 + ($i % 6),
                4 + ($i % 4),
            );
        }
        return <<<HTML
<!DOCTYPE html>
<html><head><style>
body { font-family: sans-serif; font-size: 10pt; color: #222; margin: 36pt; }
h1 { font-size: 22pt; margin-bottom: 4pt; color: #336699; }
h3 { font-size: 12pt; margin: 14pt 0 4pt 0; color: #555; }
p { margin: 0 0 6pt 0; line-height: 1.4; }
table { border-collapse: collapse; }
th, td { border: 1px solid #bbb; padding: 4pt 6pt; font-size: 9pt; }
th { background-color: #eef; }
</style></head><body>
<h1>Quarterly Field Observations</h1>
<p>Sixty sampling-point summaries from the spring 2026 monitoring period.</p>
{$paragraphs}
</body></html>
HTML;
    }

    // -----------------------------------------------------------------------
    // phpdftk subjects
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkSmall(): void
    {
        $writer = new PdfWriter();
        $this->renderer->renderInto($writer, $this->smallInvoice());
        $writer->save($this->tempDir . '/phpdftk_small.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkMedium(): void
    {
        $writer = new PdfWriter();
        $this->renderer->renderInto($writer, $this->mediumArticle());
        $writer->save($this->tempDir . '/phpdftk_medium.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkLong(): void
    {
        $writer = new PdfWriter();
        $this->renderer->renderInto($writer, $this->longReport());
        $writer->save($this->tempDir . '/phpdftk_long.pdf');
    }

    // -----------------------------------------------------------------------
    // dompdf subjects
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdfSmall(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->smallInvoice());
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_small.pdf', $dompdf->output());
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdfMedium(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->mediumArticle());
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_medium.pdf', $dompdf->output());
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdfLong(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->longReport());
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_long.pdf', $dompdf->output());
    }

    // -----------------------------------------------------------------------
    // mpdf subjects
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdfSmall(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }
        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $mpdf->WriteHTML($this->smallInvoice());
        $mpdf->Output($this->tempDir . '/mpdf_small.pdf', \Mpdf\Output\Destination::FILE);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdfMedium(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }
        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $mpdf->WriteHTML($this->mediumArticle());
        $mpdf->Output($this->tempDir . '/mpdf_medium.pdf', \Mpdf\Output\Destination::FILE);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdfLong(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }
        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $mpdf->WriteHTML($this->longReport());
        $mpdf->Output($this->tempDir . '/mpdf_long.pdf', \Mpdf\Output\Destination::FILE);
    }
}
