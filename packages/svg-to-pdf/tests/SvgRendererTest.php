<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgRenderer;
use PHPUnit\Framework\TestCase;

/**
 * 3R adapter — `SvgRenderer::draw()` places a parsed SVG onto a PDF
 * page with an optional target rectangle. Wraps everything in `q`/`Q`
 * so siblings aren't affected and emits a `cm` that scales SVG source
 * coords to the destination rect.
 */
final class SvgRendererTest extends TestCase
{
    private SvgParser $svgParser;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
    }

    /**
     * @return array{renderer: SvgRenderer, writer: PdfWriter, stream: \Phpdftk\Pdf\Core\Content\ContentStream}
     */
    private function renderer(): array
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $renderer = new SvgRenderer($page, $writer);
        return [
            'renderer' => $renderer,
            'writer' => $writer,
            'stream' => $page->contentStream(),
        ];
    }

    public function testDrawWithoutDimensionsUsesViewBoxAsSource(): void
    {
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            . '<rect width="100" height="100" fill="red"/></svg>',
        );
        $ctx['renderer']->draw($svg, x: 50, y: 100);
        $ops = implode("\n", $ctx['stream']->getOperators());
        // No scale needed → unit cm with translation at (50, 100).
        self::assertStringContainsString('1 0 0 1 50 100 cm', $ops);
        self::assertStringContainsString('0 0 100 100 re', $ops);
    }

    public function testExplicitDimensionsScaleSource(): void
    {
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            . '<rect width="100" height="100" fill="blue"/></svg>',
        );
        $ctx['renderer']->draw($svg, x: 50, y: 100, width: 200, height: 50);
        $ops = implode("\n", $ctx['stream']->getOperators());
        // sx = 200/100 = 2, sy = 50/100 = 0.5.
        self::assertStringContainsString('2 0 0 0.5 50 100 cm', $ops);
    }

    public function testViewBoxOriginShiftIsHonoured(): void
    {
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-50 -25 100 50">'
            . '<rect x="-50" y="-25" width="100" height="50" fill="green"/>'
            . '</svg>',
        );
        $ctx['renderer']->draw($svg, x: 0, y: 0);
        $ops = implode("\n", $ctx['stream']->getOperators());
        // srcMinX = -50, srcMinY = -25, scale 1 → translation at
        // (0 - (-50 * 1), 0 - (-25 * 1)) = (50, 25).
        self::assertStringContainsString('1 0 0 1 50 25 cm', $ops);
    }

    public function testWidthHeightAttributeFallbackWhenNoViewBox(): void
    {
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="40">'
            . '<rect width="80" height="40" fill="purple"/></svg>',
        );
        $ctx['renderer']->draw($svg, x: 10, y: 10, width: 160, height: 80);
        $ops = implode("\n", $ctx['stream']->getOperators());
        // sx = 160/80 = 2, sy = 80/40 = 2.
        self::assertStringContainsString('2 0 0 2 10 10 cm', $ops);
    }

    public function testWidthAttributeStripsUnits(): void
    {
        // `100pt` falls back to numeric prefix 100 — proper unit
        // resolution lands with CSS Lengths.
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100pt" height="50pt">'
            . '<rect width="100" height="50" fill="orange"/></svg>',
        );
        $ctx['renderer']->draw($svg, x: 0, y: 0, width: 100, height: 50);
        $ops = implode("\n", $ctx['stream']->getOperators());
        self::assertStringContainsString('1 0 0 1 0 0 cm', $ops);
    }

    public function testDrawWrapsInQQ(): void
    {
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
            . '<rect width="10" height="10"/></svg>',
        );
        $ctx['renderer']->draw($svg, x: 0, y: 0);
        $lines = explode("\n", implode("\n", $ctx['stream']->getOperators()));
        $qCount = count(array_filter($lines, static fn(string $l): bool => $l === 'q'));
        $bigQCount = count(array_filter($lines, static fn(string $l): bool => $l === 'Q'));
        // Outer SvgRenderer wrap; the rect itself has no transform so
        // doesn't add its own pair.
        self::assertGreaterThanOrEqual(1, $qCount);
        self::assertSame($qCount, $bigQCount);
    }

    public function testIntegrationProducesValidPdf(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $renderer = new SvgRenderer($page, $writer);

        $svg = $this->svgParser->parse(<<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
              <rect width="100" height="100" fill="#fff"/>
              <circle cx="50" cy="50" r="30" fill="red" stroke="black" stroke-width="2"/>
              <text x="50" y="55" font-size="12" fill="white">Hi</text>
            </svg>
            SVG);

        // Draw twice at different positions / scales — confirms the
        // q/Q wrap actually isolates draws from each other.
        $renderer->draw($svg, x: 72, y: 600, width: 200, height: 200);
        $renderer->draw($svg, x: 320, y: 600, width: 100, height: 100);

        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
        // Uncompressed stream — both outer cm transforms visible.
        self::assertStringContainsString('2 0 0 2 72 600 cm', $bytes);
        self::assertStringContainsString('1 0 0 1 320 600 cm', $bytes);
    }

    public function testSvgWithoutAnyDimensionsFallsBackToUnitSource(): void
    {
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>',
        );
        $ctx['renderer']->draw($svg, x: 0, y: 0, width: 100, height: 100);
        $ops = implode("\n", $ctx['stream']->getOperators());
        // Source 1×1, dest 100×100 → cm 100 0 0 100 0 0.
        self::assertStringContainsString('100 0 0 100 0 0 cm', $ops);
    }
}
