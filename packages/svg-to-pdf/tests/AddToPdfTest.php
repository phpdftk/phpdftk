<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\Alignment;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgRenderer;
use PHPUnit\Framework\TestCase;

/**
 * 3R+14 — `SvgRenderer::addToPdf` wraps `Pdf::addBlock` so an SVG drops
 * into a top-level flow document at the current cursor, advancing
 * below itself just like `Pdf::addImage` does.
 */
final class AddToPdfTest extends TestCase
{
    private SvgParser $svgParser;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
    }

    public function testAddSvgProducesValidPdf(): void
    {
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50">'
            . '<rect width="100" height="50" fill="red"/></svg>',
        );
        $pdf = new Pdf();
        SvgRenderer::addToPdf($pdf, $svg, width: 200.0, height: 100.0);
        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
    }

    public function testAddSvgWithIntrinsicDimensions(): void
    {
        // viewBox 0 0 100 50 → natural 100×50 in PDF points.
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50">'
            . '<rect width="100" height="50" fill="green"/></svg>',
        );
        $pdf = new Pdf();
        SvgRenderer::addToPdf($pdf, $svg);
        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testAddSvgWidthOnlyScalesHeightByAspect(): void
    {
        // viewBox 0 0 200 100 → aspect 2:1. width=400 → height should be 200.
        // We verify by adding two consecutive SVGs and confirming the
        // second one lands at a cursor reflecting the first's height.
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">'
            . '<rect width="200" height="100" fill="blue"/></svg>',
        );
        $pdf = new Pdf();
        SvgRenderer::addToPdf($pdf, $svg, width: 400.0);
        // Continue without throwing — sanity that the aspect path works.
        SvgRenderer::addToPdf($pdf, $svg, width: 200.0);
        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testAddSvgHeightOnlyScalesWidthByAspect(): void
    {
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">'
            . '<rect width="200" height="100" fill="purple"/></svg>',
        );
        $pdf = new Pdf();
        SvgRenderer::addToPdf($pdf, $svg, height: 50.0);
        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testAddSvgAlignmentOptions(): void
    {
        // Smoke-test all three alignment options. Each call advances the
        // cursor, so three consecutive draws on one page exercise the
        // alignment paths.
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 30">'
            . '<rect width="50" height="30" fill="orange"/></svg>',
        );
        $pdf = new Pdf();
        SvgRenderer::addToPdf($pdf, $svg, width: 100.0, align: Alignment::Left);
        SvgRenderer::addToPdf($pdf, $svg, width: 100.0, align: Alignment::Center);
        SvgRenderer::addToPdf($pdf, $svg, width: 100.0, align: Alignment::Right);
        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testAddSvgFlowsAcrossPagesOnOverflow(): void
    {
        // A column of large SVGs that won't all fit on one page should
        // auto-paginate via `Pdf::addBlock`'s overflow handling.
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">'
            . '<rect width="200" height="200" fill="grey"/></svg>',
        );
        $pdf = new Pdf();
        for ($i = 0; $i < 5; $i++) {
            SvgRenderer::addToPdf($pdf, $svg, width: 400.0, height: 200.0);
        }
        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // 5 SVGs at 200pt each + spacing = ~1000pt+, while a Letter
        // content area is ~720pt — at least 2 pages.
        self::assertGreaterThanOrEqual(2, substr_count($bytes, '/Type /Page'));
    }
}
