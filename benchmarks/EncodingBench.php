<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\Encoding\WinAnsiEncoder;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PdfWriter;

/**
 * Measures the cost of the WinAnsi UTF-8 → byte encoding step that
 * ContentStream now runs on every showText call when a RegisteredFont is
 * active. Catches regressions if the reverse-map lookup ever drops out of
 * O(1) or if the encoder allocates excessively per call.
 */
#[Bench\Iterations(5)]
#[Bench\Revs(50)]
class EncodingBench
{
    /** A representative paragraph of Latin-1 text — the hot path. */
    private const PARAGRAPH = "phpdftk is a pure-PHP toolkit for generating, parsing, and "
        . "validating PDFs against ISO 32000-2:2020. It maps every PDF spec "
        . "object type to a PHP class — café, résumé, naïve, jalapeño, "
        . "København, groß — with no runtime dependencies beyond zlib, "
        . "openssl, and simplexml. Use it for invoices, reports, and "
        . "anything else where a 20×20 cell of text · matters.";

    #[Bench\Subject]
    public function benchEncodeParagraph(): void
    {
        // Bare encoder cost — what every showText pays once.
        $encoder = new WinAnsiEncoder();
        $encoder->encode(self::PARAGRAPH);
    }

    #[Bench\Subject]
    public function benchShowTextThroughContentStream(): void
    {
        // End-to-end: building a content stream with text encoded through
        // the active font's encoder. Mirrors what Pdf::addText pays per line.
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font, 12)
            ->moveTextPosition(72, 720)
            ->showText(self::PARAGRAPH)
            ->endText();
    }
}
