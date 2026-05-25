<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Painter;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\Layout\BlockLayout;
use Phpdftk\HtmlToPdf\Layout\LayoutContext;
use Phpdftk\HtmlToPdf\Painter\Painter;
use Phpdftk\Html\Parser as HtmlParser;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

final class PainterTest extends TestCase
{
    private CssParser $css;
    private HtmlParser $html;
    private BoxGenerator $generator;
    private BlockLayout $layout;

    protected function setUp(): void
    {
        $this->css = new CssParser();
        $this->html = new HtmlParser();
        $cascade = new Cascade(PropertyRegistry::default());
        $this->generator = new BoxGenerator($cascade);
        $this->layout = new BlockLayout($cascade);
    }

    public function testEmitsFillForBackground(): void
    {
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: red; height: 50px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $ops = $stream->getOperators();
        self::assertNotEmpty($ops, 'painter should emit operators');
        // Look for `rg` (setFillColorRGB) + `re` (rectangle) + `f` (fill).
        $opcodes = $this->operatorTokens($ops);
        self::assertContains('rg', $opcodes, 'emits fill color');
        self::assertContains('re', $opcodes, 'emits rectangle');
        self::assertContains('f', $opcodes, 'emits fill');
    }

    public function testNoOperatorsWhenNoBackgroundOrBorder(): void
    {
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } div { height: 30px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        // No background-color or visible border → empty stream.
        self::assertSame([], $stream->getOperators());
    }

    public function testEmitsBordersOnlyForVisibleStyle(): void
    {
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { border: 3px solid red; height: 30px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        // 4 sides drawn as rectangles, each its own re + f pair within a q/Q.
        $opcodes = $this->operatorTokens($stream->getOperators());
        $rectCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        self::assertSame(4, $rectCount, 'one rect per visible border side');
    }

    public function testBorderHiddenStyleSuppressesPaint(): void
    {
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { border-top-width: 3px; border-top-style: hidden; height: 30px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        self::assertSame([], $stream->getOperators(), 'border-style:hidden paints nothing');
    }

    public function testVisibilityHiddenSuppressesBoxPaint(): void
    {
        // visibility: hidden on the div skips its background; visibility:
        // visible on the nested span restores painting for descendants.
        $doc = $this->html->parseDocument(
            '<html><body><div><span></span></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div, span { display: block; }
             div { background-color: red; height: 100px; visibility: hidden; }
             span { background-color: blue; height: 40px; visibility: visible; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = $writer->toBytes();
        // The hidden div's red (1 0 0 rg) shouldn't paint.
        self::assertStringNotContainsString('1 0 0 rg', $bytes, 'hidden div paints no red');
        // The visible span's blue (0 0 1 rg) should.
        self::assertStringContainsString('0 0 1 rg', $bytes, 'visible span paints blue');
    }

    public function testBoxShadowEmitsRectAtOffset(): void
    {
        // box-shadow: 2px 2px red — painter emits a shadow rect before the
        // background, so the box's background covers the offset rect's
        // upper-left corner.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 50px; box-shadow: 4px 4px red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        // Expect at least 2 rectangles: shadow + background.
        $rectCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        self::assertGreaterThanOrEqual(2, $rectCount, 'box-shadow + background → 2+ rects');
    }

    public function testSolidBorderEmitsOneRectPerSide(): void
    {
        // Regression — 4-sided solid border = 4 rects.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border: 2px solid red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $rectCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        self::assertSame(4, $rectCount, 'one rect per side');
    }

    public function testDoubleBorderEmitsTwoRectsPerSide(): void
    {
        // 9px double border on 4 sides → 2 rects per side × 4 sides = 8.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border: 9px double red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $rectCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        self::assertSame(8, $rectCount, 'double border = 2 thirds per side × 4 sides');
    }

    public function testDoubleBorderTooThinFallsBackToSolid(): void
    {
        // 2px double border can't split into a 3-tier band — falls
        // back to one rect per side.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border: 2px double red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $rectCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        self::assertSame(4, $rectCount, 'hairline double falls back to solid');
    }

    public function testDashedBorderEmitsDashPatternStroke(): void
    {
        // CSS Backgrounds 3 §5 + CSS UI 3 §5: dashed border strokes
        // a centerline with a PDF dash pattern, NOT filled rects.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border: 4px dashed red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        // 4 strokes (one per side) — no filled rects.
        $strokeCount = count(array_filter($opcodes, static fn($n) => $n === 'S'));
        self::assertSame(4, $strokeCount, 'four dashed strokes — one per side');
        $rectCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        self::assertSame(0, $rectCount, 'dashed border does not emit filled rects');
        // PDF dash pattern set via `d` operator.
        self::assertContains('d', $opcodes, 'dash pattern set');
    }

    public function testDottedBorderEmitsDottedDashPattern(): void
    {
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border: 2px dotted blue; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // Dotted pattern is [thickness, thickness] = [2, 2].
        self::assertMatchesRegularExpression('/\[ 2 2 \] 0 d/', $bytes);
    }

    public function testMixedDashedAndSolidSidesIndependent(): void
    {
        // border-top dashed, others solid → mix of strokes (1) and
        // filled rects (3).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px;
                   border-top: 4px dashed red;
                   border-right: 4px solid red;
                   border-bottom: 4px solid red;
                   border-left: 4px solid red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $strokeCount = count(array_filter($opcodes, static fn($n) => $n === 'S'));
        $rectCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        self::assertSame(1, $strokeCount, 'one dashed top');
        self::assertSame(3, $rectCount, 'three solid sides');
    }

    public function testInsetBorderDarkensTopAndLeftSides(): void
    {
        // `border-style: inset` paints top + left with a darkened
        // version of the base colour. Verify by checking that the
        // operator stream contains BOTH the base colour AND a darker
        // variant (channel × 0.5).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border: 4px inset rgb(200, 0, 0); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // Base colour (200/255 ≈ 0.78431) and darkened (× 0.5 ≈ 0.39216).
        self::assertStringContainsString('0.784314 0 0 rg', $bytes, 'base colour for bottom/right');
        self::assertStringContainsString('0.392157 0 0 rg', $bytes, 'darkened colour for top/left');
    }

    public function testOutsetBorderDarkensBottomAndRightSides(): void
    {
        // `border-style: outset` is the inverse: bottom + right
        // darken, top + left use the base colour.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border: 4px outset rgb(200, 0, 0); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // Same pattern as inset, but inverted side assignment — both
        // colours still appear in the stream.
        self::assertStringContainsString('0.784314 0 0 rg', $bytes);
        self::assertStringContainsString('0.392157 0 0 rg', $bytes);
    }

    public function testGrooveBorderProducesLightAndDarkSides(): void
    {
        // `groove` paints top + left darkened, bottom + right
        // lightened. Verify both light and dark variants appear.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border: 4px groove rgb(128, 128, 128); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // Darker: 128/255 × 0.5 ≈ 0.25098.
        self::assertStringContainsString('0.25098 0.25098 0.25098 rg', $bytes, 'darker on top/left');
        // Lighter: 0.501961 + (1 - 0.501961) × 0.3 ≈ 0.651373
        self::assertStringContainsString('0.651373 0.651373 0.651373 rg', $bytes, 'lighter on bottom/right');
    }

    public function testRidgeBorderInvertsGroovePattern(): void
    {
        // `ridge` is the inverse of `groove` — light on top/left,
        // dark on bottom/right.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border: 4px ridge rgb(128, 128, 128); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // Both variants appear regardless of orientation.
        self::assertStringContainsString('0.25098 0.25098 0.25098 rg', $bytes);
        self::assertStringContainsString('0.651373 0.651373 0.651373 rg', $bytes);
    }

    public function testInsetOnSingleSideUsesDarkenedColor(): void
    {
        // Only `border-top: 4px inset blue`. Top is darkened; no
        // other side paints. Verify only the darkened variant appears
        // for the blue (0, 0, 1) channel.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border-top: 4px inset blue; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // Darkened blue: 1 × 0.5 = 0.5.
        self::assertStringContainsString('0 0 0.5 rg', $bytes, 'top side darkened');
        // Base blue should NOT appear (no other side painted).
        self::assertStringNotContainsString('0 0 1 rg', $bytes, 'no base blue without other sides');
    }

    public function testSolidBorderUnaffectedBy3dColorLogic(): void
    {
        // Regression: `solid` borders must continue to use the base
        // colour without any darken/lighten — the 3D logic only
        // applies to inset/outset/groove/ridge.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border: 4px solid rgb(200, 0, 0); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        self::assertStringContainsString('0.784314 0 0 rg', $bytes, 'base colour');
        self::assertStringNotContainsString('0.392157 0 0 rg', $bytes, 'no darkened variant');
    }

    public function testZeroThicknessDashedNoOp(): void
    {
        // Width 0 → don't try to stroke a zero-width line. The
        // border isn't visible anyway.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; border: 0px dashed red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $strokeCount = count(array_filter($opcodes, static fn($n) => $n === 'S'));
        self::assertSame(0, $strokeCount);
    }

    public function testDoubleBorderPerSideIndependence(): void
    {
        // Mixed-style border: top=double, others=solid. Expect 2 rects
        // for the top side + 1 rect each for right/bottom/left = 5.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px;
                   border-top: 9px double red;
                   border-right: 2px solid red;
                   border-bottom: 2px solid red;
                   border-left: 2px solid red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $rectCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        self::assertSame(5, $rectCount, 'top double (2) + 3 solid sides (3) = 5');
    }

    public function testDoubleOutlineEmitsTwoStrokedRects(): void
    {
        // 9px double outline → two stroked concentric rectangles.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; outline: 9px double red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $rectCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        $strokeCount = count(array_filter($opcodes, static fn($n) => $n === 'S'));
        self::assertSame(2, $rectCount, 'two concentric rect paths');
        self::assertSame(2, $strokeCount, 'each rect stroked individually');
    }

    public function testDoubleOutlineTooThinFallsBackToSolid(): void
    {
        // 2px is < 3 — outline double can't split into thirds, fall
        // back to single solid stroke.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { height: 30px; outline: 2px double red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $strokeCount = count(array_filter($opcodes, static fn($n) => $n === 'S'));
        self::assertSame(1, $strokeCount, 'hairline double outline falls back to one stroked rect');
    }

    public function testInsetBoxShadowEmitsEvenOddFill(): void
    {
        // `box-shadow: inset 5px 5px red` paints the shadow INSIDE the
        // padding-box edge using the PDF even-odd fill rule (`f*`) so
        // the inner rect stays clear and only the frame between the
        // padding edge and the inset rect is filled.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 50px; box-shadow: inset 5px 5px red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        self::assertStringContainsString('f*', $bytes, 'inset shadow uses even-odd fill rule');
        // Shadow's red colour emitted as fill colour.
        self::assertStringContainsString('1 0 0 rg', $bytes, 'shadow fills with declared color');
    }

    public function testInsetBoxShadowWithFullSpreadFillsPaddingBox(): void
    {
        // When spread + offsets exceed the padding box's dimensions,
        // the inner rect collapses — the painter falls back to filling
        // the whole padding box solid with the shadow color (no
        // even-odd path needed).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 30px; width: 40px; box-shadow: inset 0 0 0 60px green; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // Spread of 60 on a 40×30 box → inner collapses → solid fill,
        // no even-odd path emitted from the shadow code.
        self::assertStringNotContainsString('f*', $bytes, 'collapsed inner skips even-odd');
        // Green emitted as a fill color.
        self::assertStringContainsString('0 0.501961 0 rg', $bytes, 'green shadow color emitted');
    }

    public function testInsetBoxShadowZeroDimensionsIsNoOp(): void
    {
        // A box whose padding-box dimensions are zero (zero width AND
        // zero height) has nowhere for the shadow to draw — painter
        // early-outs without emitting an even-odd fill.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             body { width: 0; }
             div { width: 0; height: 0; box-shadow: inset 5px 5px red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(0, 0, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        self::assertStringNotContainsString('f*', $bytes, 'zero padding-box skips inset paint');
    }

    public function testInsetBoxShadowDefaultsToCurrentColor(): void
    {
        // `box-shadow: inset 5px 5px` (no color) uses the cascaded
        // `color`. Set color: blue and verify the shadow paints blue.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { color: blue; background-color: white; height: 50px; box-shadow: inset 5px 5px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        self::assertStringContainsString('f*', $bytes, 'inset shadow path emitted');
        self::assertStringContainsString('0 0 1 rg', $bytes, 'shadow uses cascaded blue currentColor');
    }

    public function testOutsetShadowDoesNotUseEvenOddFill(): void
    {
        // Sanity check: the regular (non-inset) shadow path uses the
        // single-rect `f` fill, never `f*`. Confirms the inset code
        // doesn't bleed into outset shadows.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 50px; box-shadow: 4px 4px red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        self::assertStringNotContainsString('f*', $bytes, 'outset shadow must not emit even-odd fill');
    }

    public function testInsetShadowStyleNoneStillSuppresses(): void
    {
        // `box-shadow: none` (the keyword shorthand) on a box that
        // otherwise has visible content should NOT emit any shadow
        // path, even when other shadow-related declarations are set.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 50px; box-shadow: none; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('f*', $opcodes);
        // Only the background rect — exactly 1.
        $rectCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        self::assertSame(1, $rectCount);
    }

    public function testInsetShadowPaintsAboveBackground(): void
    {
        // CSS Backgrounds 3 §6.1.1 — paint order is: outset → bg →
        // inset → border. So a non-transparent background must NOT
        // cover an inset shadow. Verify by checking the operator
        // stream: the inset shadow's even-odd fill (`f*`) must appear
        // AFTER the background's solid fill (`f`).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 50px;
                   box-shadow: inset 5px 5px red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $firstFill = array_search('f', $opcodes, true);
        $insetFill = array_search('f*', $opcodes, true);
        self::assertNotFalse($firstFill, 'background fill must be emitted');
        self::assertNotFalse($insetFill, 'inset shadow fill must be emitted');
        self::assertGreaterThan(
            $firstFill,
            $insetFill,
            'inset shadow must paint above the background',
        );
    }

    public function testOutsetShadowPaintsBelowBackground(): void
    {
        // Sanity check the symmetric case: outset shadow's solid fill
        // (`f`) must come BEFORE the background's `f` in the stream.
        // The first `f` is the outset shadow; the second is the bg.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 50px;
                   box-shadow: 4px 4px red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $ops = $stream->getOperators();
        // Find the first `setFillColorRGB` that sets red (the shadow)
        // and confirm it comes BEFORE the `setFillColorRGB` that sets
        // white (the bg).
        $redIdx = null;
        $whiteIdx = null;
        foreach ($ops as $i => $op) {
            if ($op === '1 0 0 rg' && $redIdx === null) {
                $redIdx = $i;
            }
            if ($op === '1 1 1 rg' && $whiteIdx === null) {
                $whiteIdx = $i;
            }
        }
        self::assertNotNull($redIdx, 'shadow red colour emitted');
        self::assertNotNull($whiteIdx, 'background white colour emitted');
        self::assertLessThan($whiteIdx, $redIdx, 'outset shadow paints before background');
    }

    public function testInsetShadowAcceptsUnitlessZeroOffsets(): void
    {
        // CSS Values 4 §6.2: `0` is a valid zero-length without a
        // unit. The shadow parser must accept this in place of `0px`.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 50px;
                   box-shadow: inset 0 0 0 4px red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        self::assertStringContainsString('f*', $bytes, 'unitless zero offsets still produce inset shadow');
    }

    public function testBoxShadowNoneNoExtraOps(): void
    {
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 50px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $rectCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        self::assertSame(1, $rectCount, 'no shadow → just the background rect');
    }

    public function testListItemPaintsDiscByDefault(): void
    {
        // Default list-style-type is `disc` — a filled circle approximated
        // by 4 cubic Béziers.
        $doc = $this->html->parseDocument(
            '<html><body><ul><li>x</li></ul></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ul { display: block; }
             ul { padding-left: 24pt; }
             li { display: list-item; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $curveCount = count(array_filter($opcodes, static fn($n) => $n === 'c'));
        self::assertGreaterThanOrEqual(4, $curveCount, 'disc marker emits 4 Bézier curves');
        self::assertContains('f', $opcodes, 'disc marker fills');
    }

    public function testListItemSquareEmitsRect(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><ul><li>x</li></ul></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ul { display: block; }
             ul { padding-left: 24pt; }
             li { display: list-item; list-style-type: square; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('re', $opcodes, 'square marker emits a rect');
    }

    public function testListItemNoneSuppressesMarker(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><ul><li>x</li></ul></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ul { display: block; }
             ul { padding-left: 24pt; }
             li { display: list-item; list-style-type: none; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        self::assertSame([], $stream->getOperators(), 'list-style-type:none paints nothing');
    }

    public function testBackgroundClipBorderBoxIsDefault(): void
    {
        // Default `background-clip: border-box` paints to the outer
        // border edge: width = content + padding + border * 2.
        // Verify by extracting the `re` rect's width.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: red; width: 100px; height: 50px;
                   padding: 10px; border: 5px solid black; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        // Find the red bg rect: `x y w h re` where x,y are coords and
        // the colour set just before is `1 0 0 rg`.
        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        // Width = 100 + 10*2 + 5*2 = 130.
        self::assertMatchesRegularExpression('/1 0 0 rg\n[-0-9.]+ [-0-9.]+ 130(\.0+)? \d/', $bytes);
    }

    public function testBackgroundClipPaddingBoxStopsAtBorderInnerEdge(): void
    {
        // `background-clip: padding-box` paints to inner border edge:
        // width = content + padding * 2 = 100 + 20 = 120.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: red; background-clip: padding-box;
                   width: 100px; height: 50px;
                   padding: 10px; border: 5px solid black; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        self::assertMatchesRegularExpression('/1 0 0 rg\n[-0-9.]+ [-0-9.]+ 120(\.0+)? \d/', $bytes);
    }

    public function testBackgroundClipContentBoxStopsAtPaddingInnerEdge(): void
    {
        // `background-clip: content-box`: width = content only = 100.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: red; background-clip: content-box;
                   width: 100px; height: 50px;
                   padding: 10px; border: 5px solid black; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        self::assertMatchesRegularExpression('/1 0 0 rg\n[-0-9.]+ [-0-9.]+ 100(\.0+)? \d/', $bytes);
    }

    public function testBackgroundClipInvalidValueFallsBackToBorderBox(): void
    {
        // Negative: an unrecognised keyword falls back to the
        // initial `border-box`.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: red; background-clip: nonsense;
                   width: 100px; height: 50px;
                   padding: 10px; border: 5px solid black; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        // Width = 130 (border-box).
        self::assertMatchesRegularExpression('/1 0 0 rg\n[-0-9.]+ [-0-9.]+ 130(\.0+)? \d/', $bytes);
    }

    public function testOverflowHiddenEmitsClipPath(): void
    {
        // `overflow: hidden` on a box should add a `re` rect + `W` /
        // `W*` clip + `n` end-path before the children paint. Pin
        // by looking for a `W` op in the stream.
        $doc = $this->html->parseDocument(
            '<html><body><div style="overflow: hidden; height: 50px"><p style="background-color: red; height: 200px"></p></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('W', $opcodes, 'overflow: hidden emits clip path');
    }

    public function testOverflowVisibleDoesNotClip(): void
    {
        // Default `overflow: visible` — no clip path emitted.
        $doc = $this->html->parseDocument(
            '<html><body><div style="height: 50px"><p style="background-color: red; height: 200px"></p></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('W', $opcodes, 'overflow: visible does not clip');
    }

    public function testOverflowScrollClipsLikeHiddenInPrint(): void
    {
        // CSS Overflow 3 §3 — print has no scroll viewport so
        // `scroll` collapses onto `hidden` for our purposes.
        $doc = $this->html->parseDocument(
            '<html><body><div style="overflow: scroll; height: 50px"><p style="background-color: red; height: 200px"></p></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('W', $opcodes);
    }

    public function testOverflowAutoClipsLikeHiddenInPrint(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><div style="overflow: auto; height: 50px"><p style="background-color: red; height: 200px"></p></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('W', $opcodes);
    }

    public function testOverflowSiblingsUnaffectedByClip(): void
    {
        // A clipped box's siblings paint normally — the clip should
        // pop after the children, not bleed onto siblings.
        $doc = $this->html->parseDocument(
            '<html><body>'
                . '<div style="overflow: hidden; height: 30px; width: 50px"></div>'
                . '<p style="background-color: blue; height: 50px"></p>'
                . '</body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // The blue paragraph after the clipped div should still emit
        // its `0 0 1 rg` fill (no clip cutting it off).
        self::assertStringContainsString('0 0 1 rg', $bytes);
    }

    public function testOverflowInvalidKeywordIsNoClip(): void
    {
        // `overflow: nonsense` falls back to the initial `visible`
        // (cascade-level) → no clip.
        $doc = $this->html->parseDocument(
            '<html><body><div style="overflow: nonsense; height: 50px"></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('W', $opcodes);
    }

    public function testWavyDecorationStrokesCubicBezierPath(): void
    {
        // CSS Text Decoration 4 §3 `text-decoration-style: wavy` —
        // the painter strokes a sine-wave path (cubic Beziers + S),
        // never fills like solid / dashed / dotted.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><p style="text-decoration: underline; text-decoration-style: wavy">'
            . "\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [], $page);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, $registered))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        // Wavy emits curveTo (`c`) operators and a stroke (`S`).
        self::assertContains('c', $opcodes, 'wavy decoration uses cubic Beziers');
        self::assertContains('S', $opcodes, 'wavy decoration strokes the path');
    }

    public function testWavyDecorationDoesNotFillRect(): void
    {
        // Negative: wavy must NOT emit the solid-style filled rect
        // path used by dashed/dotted/solid/double.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><p style="text-decoration: underline; text-decoration-style: wavy">'
            . "\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [], $page);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, $registered))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        // No `re` from the wavy path. (The fixture has no background
        // either, so a `re` op would have to come from the wavy
        // codepath.)
        $reCount = count(array_filter($opcodes, static fn($n) => $n === 're'));
        self::assertSame(0, $reCount);
    }

    public function testSolidDecorationUnaffectedByWavyPath(): void
    {
        // Regression: solid still uses the fill-rect path; no curveTo
        // emitted for non-wavy decorations.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><p style="text-decoration: underline">'
            . "\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [], $page);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, $registered))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('c', $opcodes, 'no Bezier in solid decoration');
    }

    public function testDecorationThicknessExplicitOverridesFontMetric(): void
    {
        // `text-decoration-thickness: 3px` should produce an underline
        // rect taller than the font-metric default. Compare two
        // renderings: with and without the override.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><p style="text-decoration: underline; text-decoration-thickness: 3px">'
            . "\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [], $page);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, $registered))->paint($root, $stream);

        $ops = $stream->getOperators();
        // The underline rect has shape `X Y W H re` with H = thickness.
        // Find a 're' op preceded by a coordinate ending in `3`.
        $found = false;
        foreach ($ops as $op) {
            if (preg_match('/\s3(\.0+)?\s+re$/', $op)) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'underline rect emitted with 3px thickness');
    }

    public function testDecorationThicknessAutoLeavesFontMetric(): void
    {
        // Without an explicit thickness, the rect uses the font's
        // OS/2 underlineThickness — for NotoSansMongolian at 16px
        // this is ~0.78px. Negative-ish test: NO `3 re` op appears
        // (since 3 isn't the auto value).
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><p style="text-decoration: underline">'
            . "\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [], $page);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, $registered))->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // No 3px-thick rect from this path.
        self::assertDoesNotMatchRegularExpression('/\s3(\.0+)?\s+re/', $bytes);
    }

    public function testUnderlineOffsetOnlyAppliesToUnderline(): void
    {
        // `text-underline-offset: 5px` shifts the underline rect's Y
        // by 5 from the default. line-through should NOT shift —
        // the offset only applies to underlines.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        // Render the same text twice: once with underline+offset, once
        // with line-through+offset. The line-through Y should be the
        // same as the no-offset case; the underline Y should differ.
        $renderOps = function (string $line) use ($otd): array {
            $doc = $this->html->parseDocument(
                '<html><body><p style="text-decoration-line: ' . $line . '; text-underline-offset: 5px">'
                . "\u{1820}" . '</p></body></html>',
            );
            $sheet = $this->css->parseStylesheet(
                'html, body, p { display: block; }',
                Origin::UserAgent,
            );
            $root = $this->generator->generate($doc, [$sheet]);
            $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
            $this->layout->layout($root, $ctx);
            $writer = new PdfWriter(compressStreams: false);
            $page = $writer->addPage(612, 792);
            $registered = $writer->addOpenTypeFont($otd, [], $page);
            $stream = $writer->addContentStream($page);
            (new Painter(792.0, $registered))->paint($root, $stream);
            return $stream->getOperators();
        };
        $ulOps = $renderOps('underline');
        $ltOps = $renderOps('line-through');

        // Extract the rect Y for each. The text-decoration rect is
        // `x y w h re` — pick the one preceded by a fill/stroke setup
        // for the decoration colour.
        $underlineRectY = $this->firstReRectY($ulOps);
        $lineThroughRectY = $this->firstReRectY($ltOps);
        self::assertNotNull($underlineRectY);
        self::assertNotNull($lineThroughRectY);
        // The offset shifts the underline rect Y (in PDF coords, Y
        // is inverted, so an underline pushed FURTHER down in layout
        // corresponds to a LOWER PDF Y). Just assert they differ.
        self::assertNotEquals($underlineRectY, $lineThroughRectY);
    }

    public function testDecorationThicknessPercentageRelativeToFontSize(): void
    {
        // `text-decoration-thickness: 25%` of a 16px font-size = 4px.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><p style="text-decoration: underline; text-decoration-thickness: 25%">'
            . "\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [], $page);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, $registered))->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // 25% × 16px = 4px thickness — expect a `re` with H=4.
        self::assertMatchesRegularExpression('/\s4(\.0+)?\s+re/', $bytes);
    }

    public function testDecorationThicknessInvalidKeywordTreatedAsAuto(): void
    {
        // Negative: a non-Length/non-Percentage value falls back to
        // the font metric. No 3px-thick rect should appear.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><p style="text-decoration: underline; text-decoration-thickness: auto">'
            . "\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [], $page);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, $registered))->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        self::assertDoesNotMatchRegularExpression('/\s3(\.0+)?\s+re/', $bytes);
    }

    public function testUnderlineOffsetAutoUsesFontMetric(): void
    {
        // Sanity: with `text-underline-offset: auto`, the underline
        // sits at the font's underlinePosition. No extra shift.
        // This is a no-op check that the resolver returns null for
        // auto — the regression target is just that the bytes match
        // an underline-only render without the explicit offset.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $renderOps = function (string $extra) use ($otd): array {
            $doc = $this->html->parseDocument(
                '<html><body><p style="text-decoration: underline; ' . $extra . '">'
                . "\u{1820}" . '</p></body></html>',
            );
            $sheet = $this->css->parseStylesheet(
                'html, body, p { display: block; }',
                Origin::UserAgent,
            );
            $root = $this->generator->generate($doc, [$sheet]);
            $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
            $this->layout->layout($root, $ctx);
            $writer = new PdfWriter(compressStreams: false);
            $page = $writer->addPage(612, 792);
            $registered = $writer->addOpenTypeFont($otd, [], $page);
            $stream = $writer->addContentStream($page);
            (new Painter(792.0, $registered))->paint($root, $stream);
            return $stream->getOperators();
        };
        $defaultOps = $renderOps('');
        $autoOps = $renderOps('text-underline-offset: auto');
        self::assertSame($this->firstReRectY($defaultOps), $this->firstReRectY($autoOps));
    }

    /** Extract the Y coordinate of the first `x y w h re` rect op. */
    private function firstReRectY(array $ops): ?float
    {
        foreach ($ops as $op) {
            if (preg_match('/^([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+re$/', (string) $op, $m)) {
                return (float) $m[2];
            }
        }
        return null;
    }

    public function testDecimalMarkerEmitsText(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><ol><li>x</li><li>y</li><li>z</li></ol></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ol { display: block; }
             ol { padding-left: 24pt; }
             li { display: list-item; list-style-type: decimal; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $codepoints = [];
        foreach (range(ord('0'), ord('9')) as $cp) {
            $codepoints[] = $cp;
        }
        $codepoints[] = ord('.');
        $registered = $writer->addOpenTypeFont($otd, $codepoints, $page);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0, $registered);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        // Each <li> emits a Tj for its decimal marker — at least 3.
        $tjCount = count(array_filter($opcodes, static fn($n) => $n === 'Tj'));
        self::assertGreaterThanOrEqual(3, $tjCount, 'one Tj per decimal marker');
    }

    public function testLiValueAttributeSetsExplicitOrdinal(): void
    {
        // HTML 5 §4.4.5.2: `<li value="5">` sets the explicit count and
        // subsequent siblings continue from there.
        $doc = $this->html->parseDocument(
            '<html><body><ol>'
            . '<li>a</li><li value="5">b</li><li>c</li>'
            . '</ol></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ol { display: block; }
             li { display: list-item; list-style-type: decimal; }',
            \Phpdftk\Css\Sheet\Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext());
        $this->layout->layout($root, $ctx);

        $painter = new Painter(792.0);
        $reflMethod = new \ReflectionMethod(Painter::class, 'listItemIndex');
        // Walk to find the <ol> and iterate its <li> children.
        $items = [];
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node->element !== null
                && strtolower($node->element->localName) === 'li'
            ) {
                $items[] = $reflMethod->invoke($painter, $node);
                continue;
            }
            foreach ($node->children as $c) {
                array_unshift($stack, $c);
            }
        }
        self::assertSame([1, 5, 6], $items, 'second `<li value="5">` snaps to 5, third continues to 6');
    }

    public function testOlStartAttributeShiftsOrdinals(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><ol start="5"><li>a</li><li>b</li><li>c</li></ol></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ol { display: block; } li { display: list-item; list-style-type: decimal; }',
            \Phpdftk\Css\Sheet\Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext());
        $this->layout->layout($root, $ctx);

        $painter = new Painter(792.0);
        $reflMethod = new \ReflectionMethod(Painter::class, 'listItemIndex');
        $items = [];
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node->element !== null && strtolower($node->element->localName) === 'li') {
                $items[] = $reflMethod->invoke($painter, $node);
                continue;
            }
            foreach ($node->children as $c) {
                array_unshift($stack, $c);
            }
        }
        self::assertSame([5, 6, 7], $items);
    }

    public function testOlReversedAttributeCountsDown(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><ol reversed><li>a</li><li>b</li><li>c</li></ol></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ol { display: block; } li { display: list-item; list-style-type: decimal; }',
            \Phpdftk\Css\Sheet\Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext());
        $this->layout->layout($root, $ctx);

        $painter = new Painter(792.0);
        $reflMethod = new \ReflectionMethod(Painter::class, 'listItemIndex');
        $items = [];
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node->element !== null && strtolower($node->element->localName) === 'li') {
                $items[] = $reflMethod->invoke($painter, $node);
                continue;
            }
            foreach ($node->children as $c) {
                array_unshift($stack, $c);
            }
        }
        self::assertSame([3, 2, 1], $items, 'reversed counts down from li count');
    }

    public function testDecimalMarkerFallsBackToDiscWithoutFont(): void
    {
        // No defaultFont — counter-style markers can't render text, so
        // the painter falls back to the geometric disc marker.
        $doc = $this->html->parseDocument(
            '<html><body><ol><li>x</li></ol></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ol { display: block; }
             ol { padding-left: 24pt; }
             li { display: list-item; list-style-type: decimal; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $curveCount = count(array_filter($opcodes, static fn($n) => $n === 'c'));
        self::assertGreaterThanOrEqual(4, $curveCount, 'falls back to disc (4 curves)');
    }

    public function testListItemCircleOutline(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><ul><li>x</li></ul></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ul { display: block; }
             ul { padding-left: 24pt; }
             li { display: list-item; list-style-type: circle; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('S', $opcodes, 'circle marker strokes the path');
        self::assertNotContains('f', $opcodes, 'circle marker does not fill');
    }

    public function testTextShadowEmitsShadowPass(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><p>' . "\u{1820}\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; }
             p { color: black; text-shadow: 3px 3px red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [0x1820], $page);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0, $registered);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        // Expect 2 BT (shadow pass + main pass).
        $btCount = count(array_filter($opcodes, static fn($n) => $n === 'BT'));
        self::assertGreaterThanOrEqual(2, $btCount, 'shadow pass + main pass each open BT');

        $bytes = $writer->toBytes();
        self::assertStringContainsString('1 0 0 rg', $bytes, 'shadow color emitted');
    }

    public function testKerningPathExposed(): void
    {
        // The painter chooses between Tj (no kerning) and TJ (per-glyph
        // kern array) depending on whether the shaper's advance diverges
        // from the font's natural hmtx width. This regression test
        // ensures both code paths produce *some* text-show operator —
        // before the kerning wiring landed there was only Tj.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();
        $doc = $this->html->parseDocument(
            '<html><body><p>' . "\u{1820}\u{1820}\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, p { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [0x1820], $page);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0, $registered);
        $painter->paint($root, $stream);

        $hasTextOp = false;
        foreach ($stream->getOperators() as $op) {
            $trim = rtrim($op);
            if (str_ends_with($trim, ' Tj') || str_ends_with($trim, ' TJ')) {
                $hasTextOp = true;
                break;
            }
        }
        self::assertTrue($hasTextOp, 'painter emits either Tj or TJ for shaped text');
    }

    public function testTranslatesShaperGidsThroughSubsetMap(): void
    {
        // The painter emits hex GIDs that match the FONT'S POST-SUBSET
        // glyph numbering, not the original-font GIDs the shaper produces.
        // Without this translation, PDF viewers would render the wrong
        // glyphs (or `.notdef`) because the embedded subset is renumbered.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();
        $doc = $this->html->parseDocument(
            '<html><body><p>' . "\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, p { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [0x1820], $page);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0, $registered);
        $painter->paint($root, $stream);

        $originalGid = $otd->fullUnicodeToGid[0x1820];
        $subsetGid = $registered->getUnicodeToGidMap()[0x1820];
        self::assertNotSame(
            $originalGid,
            $subsetGid,
            'sanity: subset should renumber the glyph away from its full-font GID',
        );

        $hexLine = null;
        foreach ($stream->getOperators() as $op) {
            if (str_ends_with(rtrim($op), 'Tj') && str_contains($op, '<')) {
                $hexLine = $op;
                break;
            }
        }
        self::assertNotNull($hexLine, 'painter should emit a Tj hex literal');
        self::assertStringContainsString(
            sprintf('%04X', $subsetGid),
            (string) $hexLine,
            'emitted hex should reference the subset GID',
        );
    }

    public function testEmitsUnderlineRectForTextDecorationUnderline(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();
        $doc = $this->html->parseDocument(
            '<html><body><p>' . "\u{1820}\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; }
             p { text-decoration: underline; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [0x1820], $page);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0, $registered);
        $painter->paint($root, $stream);

        $ops = $stream->getOperators();
        // Look for an `re` op followed by `f` (underline is drawn as a filled rect).
        $hasRect = false;
        $hasFill = false;
        foreach ($ops as $op) {
            $trim = rtrim($op);
            if (str_ends_with($trim, ' re')) {
                $hasRect = true;
            }
            if ($trim === 'f') {
                $hasFill = true;
            }
        }
        self::assertTrue($hasRect, 'underline emits a rectangle');
        self::assertTrue($hasFill, 'underline emits a fill');
    }

    public function testEmitsTextGlyphsWhenFontProvided(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><p>' . "\u{1820}\u{1820}\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [0x1820], $page);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0, $registered);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('BT', $opcodes, 'opens a text object');
        self::assertContains('Tf', $opcodes, 'sets the font');
        self::assertContains('Tj', $opcodes, 'shows glyphs');
        self::assertContains('ET', $opcodes, 'closes the text object');

        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testProducesValidPdf(): void
    {
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             body { background-color: blue; }
             div { background-color: red; height: 100px; border: 2px solid black; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
    }

    /**
     * Pull the last whitespace-separated token out of each operator line —
     * that's the PDF operator code (e.g. `re`, `f`, `rg`).
     *
     * @param array<int, string> $ops
     * @return list<string>
     */
    private function operatorTokens(array $ops): array
    {
        $out = [];
        foreach ($ops as $op) {
            $trim = rtrim($op);
            $parts = preg_split('/\s+/', $trim) ?: [];
            if ($parts === []) {
                continue;
            }
            $out[] = $parts[count($parts) - 1];
        }
        return $out;
    }
}
