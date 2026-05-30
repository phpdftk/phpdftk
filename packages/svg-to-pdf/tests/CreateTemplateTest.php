<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Writer\PdfDoc;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgRenderer;
use PHPUnit\Framework\TestCase;

/**
 * 3R+15 — `SvgRenderer::createTemplate` wraps `PdfDoc::createTemplate`
 * so callers can build a reusable Form XObject from an SVG and stamp
 * it onto multiple pages via `Page::drawTemplate` without re-emitting
 * the underlying operators.
 */
final class CreateTemplateTest extends TestCase
{
    private SvgParser $svgParser;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
    }

    public function testCreateTemplateReturnsFormXObject(): void
    {
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50">'
            . '<rect width="100" height="50" fill="red"/></svg>',
        );
        $doc = new PdfDoc();
        $page = $doc->addPage();

        $tpl = SvgRenderer::createTemplate($doc, $page, $svg);

        self::assertInstanceOf(FormXObject::class, $tpl);
        self::assertGreaterThan(0, $tpl->objectNumber);
    }

    public function testTemplateBBoxMatchesIntrinsicDimensions(): void
    {
        // viewBox 0 0 200 100 → BBox should be [0, 0, 200, 100]
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">'
            . '<rect width="200" height="100" fill="blue"/></svg>',
        );
        $doc = new PdfDoc();
        $page = $doc->addPage();

        $tpl = SvgRenderer::createTemplate($doc, $page, $svg);

        $bbox = $tpl->bBox->items;
        self::assertCount(4, $bbox);
        self::assertInstanceOf(\Phpdftk\Pdf\Core\PdfNumber::class, $bbox[2]);
        self::assertInstanceOf(\Phpdftk\Pdf\Core\PdfNumber::class, $bbox[3]);
        self::assertSame(200.0, (float) $bbox[2]->value);
        self::assertSame(100.0, (float) $bbox[3]->value);
    }

    public function testTemplateBBoxUsesExplicitWidthAndHeight(): void
    {
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">'
            . '<rect width="200" height="100" fill="green"/></svg>',
        );
        $doc = new PdfDoc();
        $page = $doc->addPage();

        $tpl = SvgRenderer::createTemplate($doc, $page, $svg, width: 50.0, height: 25.0);

        $bbox = $tpl->bBox->items;
        self::assertInstanceOf(\Phpdftk\Pdf\Core\PdfNumber::class, $bbox[2]);
        self::assertInstanceOf(\Phpdftk\Pdf\Core\PdfNumber::class, $bbox[3]);
        self::assertSame(50.0, (float) $bbox[2]->value);
        self::assertSame(25.0, (float) $bbox[3]->value);
    }

    public function testTemplateWidthOnlyKeepsAspectRatio(): void
    {
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">'
            . '<rect width="200" height="100" fill="purple"/></svg>',
        );
        $doc = new PdfDoc();
        $page = $doc->addPage();

        // width=400 with aspect 2:1 → height=200
        $tpl = SvgRenderer::createTemplate($doc, $page, $svg, width: 400.0);

        $bbox = $tpl->bBox->items;
        self::assertInstanceOf(\Phpdftk\Pdf\Core\PdfNumber::class, $bbox[2]);
        self::assertInstanceOf(\Phpdftk\Pdf\Core\PdfNumber::class, $bbox[3]);
        self::assertSame(400.0, (float) $bbox[2]->value);
        self::assertSame(200.0, (float) $bbox[3]->value);
    }

    public function testTemplateRoundTripsThroughDrawTemplate(): void
    {
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            . '<circle cx="50" cy="50" r="40" fill="orange"/></svg>',
        );
        // Uncompressed streams so we can assert on raw operator bytes.
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();

        $tpl = SvgRenderer::createTemplate($doc, $page, $svg, width: 50.0, height: 50.0);
        $page->drawTemplate($tpl, x: 100.0, y: 500.0);
        $page->drawTemplate($tpl, x: 200.0, y: 500.0);
        $page->drawTemplate($tpl, x: 300.0, y: 500.0);

        $bytes = $doc->writer()->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
        // The template's XObject reference is added to the page's
        // resource dict once; drawTemplate is invoked three times so
        // the `Do` operator should appear at least three times.
        self::assertGreaterThanOrEqual(3, substr_count($bytes, ' Do'));
    }

    public function testTemplateWithGradientPaintsValidPdf(): void
    {
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="0">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</linearGradient></defs>'
            . '<rect width="100" height="100" fill="url(#g)"/></svg>',
        );
        $doc = new PdfDoc();
        $page = $doc->addPage();

        $tpl = SvgRenderer::createTemplate($doc, $page, $svg, width: 120.0, height: 120.0);
        $page->drawTemplate($tpl, x: 72.0, y: 600.0);

        $bytes = $doc->writer()->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
    }
}
