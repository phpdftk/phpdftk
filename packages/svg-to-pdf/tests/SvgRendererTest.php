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
        // No scale needed → unit cm with y-flip and translation that
        // lands the SVG's top edge at PDF y = y + height = 100 + 100 = 200.
        self::assertStringContainsString('1 0 0 -1 50 200 cm', $ops);
        self::assertStringContainsString('0 0 100 100 re', $ops);
    }

    public function testExplicitDimensionsScaleSourceWithPreserveAspectRatioNone(): void
    {
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" '
            . 'preserveAspectRatio="none">'
            . '<rect width="100" height="100" fill="blue"/></svg>',
        );
        $ctx['renderer']->draw($svg, x: 50, y: 100, width: 200, height: 50);
        $ops = implode("\n", $ctx['stream']->getOperators());
        // preserveAspectRatio=none → independent axes. sx = 200/100 = 2,
        // sy = 50/100 = 0.5; effectiveH = 0.5 * 100 = 50;
        // f = 100 + 0 + 50 + 0 = 150.
        self::assertStringContainsString('2 0 0 -0.5 50 150 cm', $ops);
    }

    public function testViewBoxOriginShiftIsHonoured(): void
    {
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-50 -25 100 50" '
            . 'preserveAspectRatio="none">'
            . '<rect x="-50" y="-25" width="100" height="50" fill="green"/>'
            . '</svg>',
        );
        $ctx['renderer']->draw($svg, x: 0, y: 0);
        $ops = implode("\n", $ctx['stream']->getOperators());
        // srcMin = (-50, -25), scale 1, effectiveH = 50.
        // e = 0 - (-50 * 1) = 50.
        // f = 0 + 0 + 50 + (-25 * 1) = 25.
        self::assertStringContainsString('1 0 0 -1 50 25 cm', $ops);
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
        // sx = sy = 2 (uniform fits exactly); effectiveH = 2 * 40 = 80;
        // f = 10 + 0 + 80 + 0 = 90.
        self::assertStringContainsString('2 0 0 -2 10 90 cm', $ops);
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
        // No scale change, effectiveH = 50; f = 0 + 50 = 50.
        self::assertStringContainsString('1 0 0 -1 0 50 cm', $ops);
    }

    public function testPreserveAspectRatioDefaultIsUniformScaleAndCentre(): void
    {
        // SVG 2 §7.10 default = `xMidYMid meet`. Source 100×100 into
        // 200×50 → uniform scale 0.5 (smallest axis), centred:
        //   effectiveW = 50, effectiveH = 50
        //   offsetX = (200 - 50) / 2 = 75
        //   offsetY = (50 - 50) / 2 = 0
        // cm e = 50 + 75 = 125; f = 100 + 0 + 50 + 0 = 150.
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            . '<rect width="100" height="100" fill="grey"/></svg>',
        );
        $ctx['renderer']->draw($svg, x: 50, y: 100, width: 200, height: 50);
        $ops = implode("\n", $ctx['stream']->getOperators());
        self::assertStringContainsString('0.5 0 0 -0.5 125 150 cm', $ops);
    }

    public function testTextUnderSvgRendererEmitsTmFlipNotTd(): void
    {
        // Y-flip CTM under SvgRenderer flips glyphs upside-down unless
        // the text painter compensates with `Tm 1 0 0 -1 x y` instead
        // of the default `Td`. The flip restores upright glyph
        // rendering under the outer CTM.
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">'
            . '<text x="10" y="50">Hi</text></svg>',
        );
        $ctx['renderer']->draw($svg, x: 0, y: 0);
        $ops = implode("\n", $ctx['stream']->getOperators());
        self::assertStringContainsString('1 0 0 -1 10 50 Tm', $ops);
        self::assertStringNotContainsString(' Td', $ops);
    }

    public function testTranslatorWithoutFlipFlagStillEmitsTd(): void
    {
        // Direct Translator usage (without SvgRenderer) keeps the
        // pre-fix behaviour: no outer Y-flip, no Tm flip — text
        // renders at the SVG-stated baseline using `Td`.
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10" y="20">Hi</text></svg>',
        );
        (new \Phpdftk\SvgToPdf\Translator())->paint($svg, $stream, $page, $writer);
        $ops = implode("\n", $stream->getOperators());
        self::assertStringContainsString('10 20 Td', $ops);
        self::assertStringNotContainsString(' Tm', $ops);
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
        // Uncompressed stream — both outer cm transforms with y-flip
        // visible. First draw: dst 200×200 over src 100×100 →
        // uniform scale 2, effectiveH = 200, f = 600 + 200 = 800.
        // Second draw: dst 100×100 → scale 1, effectiveH = 100, f =
        // 600 + 100 = 700.
        self::assertStringContainsString('2 0 0 -2 72 800 cm', $bytes);
        self::assertStringContainsString('1 0 0 -1 320 700 cm', $bytes);
    }

    public function testSvgWithoutAnyDimensionsFallsBackToUnitSource(): void
    {
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>',
        );
        $ctx['renderer']->draw($svg, x: 0, y: 0, width: 100, height: 100);
        $ops = implode("\n", $ctx['stream']->getOperators());
        // Source 1×1, dest 100×100 → uniform scale 100, effectiveH 100,
        // f = 0 + 100 = 100.
        self::assertStringContainsString('100 0 0 -100 0 100 cm', $ops);
    }

    public function testTitleAndDescDoNotEmitOperators(): void
    {
        // SVG 2 §15.3 — `<title>` and `<desc>` are pure accessibility
        // metadata. Their text content must NOT leak into the rendered
        // PDF content stream as a Tj or any draw op.
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
            . '<title>secret text in title</title>'
            . '<desc>secret text in desc</desc>'
            . '<rect width="10" height="10"/>'
            . '</svg>',
        );
        $ctx['renderer']->draw($svg, x: 0, y: 0);
        $ops = implode("\n", $ctx['stream']->getOperators());
        self::assertStringNotContainsString('secret text in title', $ops);
        self::assertStringNotContainsString('secret text in desc', $ops);
        // The rect still renders.
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }

    public function testAnchorChildrenStillPaint(): void
    {
        // SVG 2 §12.1.1 — `<a>` is a transparent paint wrapper. Its
        // children render exactly as if the `<a>` weren't there
        // (the link annotation itself is a separate concern from the
        // visual content).
        $ctx = $this->renderer();
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
            . '<a href="https://example.com">'
            . '<rect width="10" height="10"/>'
            . '</a></svg>',
        );
        $ctx['renderer']->draw($svg, x: 0, y: 0);
        $ops = implode("\n", $ctx['stream']->getOperators());
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }
}
