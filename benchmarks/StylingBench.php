<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\Pdf\Writer\CalloutType;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\TextStyle;

/**
 * Phase 3 styling primitives — text decoration, blockquote, and
 * callouts. Each subject renders N styled paragraphs / blocks on
 * `Pdf` (Level 3) to measure the per-block overhead beyond a plain
 * `addText` baseline.
 *
 * No Level 2 variants are benched here: the addX/drawX split for
 * Phase 3 features is mechanical (same renderer underneath), and the
 * Tables / Lists benches already isolate Level 3 vs. Level 2 cost.
 */
#[Bench\Iterations(5)]
#[Bench\Revs(3)]
class StylingBench
{
    private string $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdftk_bench';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    private const BODY = 'The quick brown fox jumps over the lazy dog repeatedly while a thoughtful narrator describes the scene.';

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3PdfUnderlined50Items(): void
    {
        $pdf = new Pdf();
        for ($i = 0; $i < 50; $i++) {
            $pdf->addText(self::BODY, new TextStyle(underline: true));
        }
        $pdf->save($this->tempDir . '/styling_underline_50.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3PdfBlockquote50Items(): void
    {
        $pdf = new Pdf();
        for ($i = 0; $i < 50; $i++) {
            $pdf->addQuote(self::BODY);
        }
        $pdf->save($this->tempDir . '/styling_quote_50.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchLevel3PdfCallout50Items(): void
    {
        $pdf = new Pdf();
        $types = [CalloutType::Note, CalloutType::Tip, CalloutType::Warning, CalloutType::Danger];
        for ($i = 0; $i < 50; $i++) {
            $pdf->addCallout(self::BODY, $types[$i % count($types)]);
        }
        $pdf->save($this->tempDir . '/styling_callout_50.pdf');
    }
}
