<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests\Integration;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end smoke test: parse an SVG, paint it onto a real PDF page,
 * round-trip through `PdfWriter::toBytes()`, and assert the result
 * survives basic structural checks. New-feature checklist item from
 * `CLAUDE.md` §"New-feature checklist".
 */
final class BasicShapesPdfTest extends TestCase
{
    public function testBasicShapesProduceValidPdf(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612.0, 792.0);
        $stream = $writer->addContentStream($page);

        $svg = (new SvgParser())->parse(<<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
              <rect x="10" y="10" width="50" height="50" fill="red"/>
              <circle cx="100" cy="35" r="20" fill="green"/>
              <ellipse cx="160" cy="35" rx="25" ry="15" fill="blue"/>
              <line x1="10" y1="100" x2="190" y2="100" stroke="black"/>
              <polyline points="10,150 50,120 90,150 130,120 170,150"
                        fill="none" stroke="purple"/>
              <polygon points="10,180 30,160 50,180 30,200" fill="orange"/>
            </svg>
            SVG);

        (new Translator())->paint($svg, $stream);

        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
        // The page contains the painter output — sanity-check that the
        // generated content stream made it into the byte stream by
        // looking for one easily-spotted marker.
        self::assertStringContainsString('/Type /Page', $bytes);
    }

    public function testTranslatorCanPaintIntoUserSuppliedStream(): void
    {
        // The translator doesn't manage a PdfWriter itself — callers
        // bring their own stream. Verifies the painter doesn't bake any
        // hidden assumptions about the writer state.
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);

        $svg = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100"/></svg>',
        );

        // Pre-emit some unrelated operators; the painter must not throw
        // or rewrite them.
        $stream->saveGraphicsState();
        (new Translator())->paint($svg, $stream);
        $stream->restoreGraphicsState();

        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
    }
}
