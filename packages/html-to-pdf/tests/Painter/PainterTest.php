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

    public function testClipPathRectAndXywhEmitClip(): void
    {
        // CSS Shapes 2 §4.5/§4.6 — `clip-path: rect(...)` / `xywh(...)` clip the
        // box to a rectangle. Regression: neither shape had a painter case, so
        // applyClipPath returned false and NO clip was emitted (the whole box
        // rendered unclipped). Each must now emit a rectangle + clip (`W`).
        foreach (['rect(25% 50% 75% 12.5%)', 'xywh(10% 10% 50% 50%)'] as $clip) {
            $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
            $sheet = $this->css->parseStylesheet(
                'html, body, div { display: block; }
                 div { background-color: green; width: 100px; height: 100px; clip-path: ' . $clip . '; }',
                Origin::UserAgent,
            );
            $root = $this->generator->generate($doc, [$sheet]);
            self::assertNotNull($root);
            $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

            $writer = new PdfWriter();
            $page = $writer->addPage(612, 792);
            $stream = $writer->addContentStream($page);
            $painter = new Painter(792.0);
            $painter->paint($root, $stream);

            $opcodes = $this->operatorTokens($stream->getOperators());
            self::assertContains('W', $opcodes, "$clip emits a clip (W) operator");
            self::assertContains('re', $opcodes, "$clip emits the clip rectangle");
        }
    }

    public function testOverflowClipMarginExpandsClipRect(): void
    {
        // CSS Overflow 3 §4.2 — `overflow-clip-margin: 10px` on an
        // `overflow: clip` box expands the clip region 10px outward from
        // the padding box on every edge, so a 100px box clips at 120px
        // (x = -10, width = 120), not at the padding box (x = 0, w = 100).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px; overflow: clip;
                   overflow-clip-margin: 10px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $clip = null;
        foreach ($stream->getOperators() as $op) {
            if (preg_match('/^(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?) re$/', trim($op), $m)
                && abs((float) $m[3] - 120.0) < 0.5
            ) {
                $clip = $m;
            }
        }
        self::assertNotNull($clip, 'clip rectangle expanded to 120px wide');
        self::assertEqualsWithDelta(-10.0, (float) $clip[1], 0.5, 'clip x shifted out by the 10px margin');
        self::assertEqualsWithDelta(120.0, (float) $clip[4], 0.5, 'clip height also expanded to 120px');
    }

    public function testClipPathWithGeometryBoxEmitsClip(): void
    {
        // CSS Masking 1 §6 — `clip-path: <basic-shape> <geometry-box>`
        // parses as a ValueList [shape, keyword]. Regression: applyClipPath's
        // `instanceof <Shape>` checks ran against the raw ValueList, so the
        // clip was silently dropped and the box rendered unclipped. The
        // reference box must be unwrapped and the shape still clip (`W`).
        foreach ([
            'polygon(0% 0%, 100% 0%, 100px 100%, 0 100px) padding-box',
            'circle(40px) content-box',
            'inset(10px) margin-box',
            'border-box',
        ] as $clip) {
            $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
            $sheet = $this->css->parseStylesheet(
                'html, body, div { display: block; }
                 div { background-color: green; width: 100px; height: 100px;
                       padding: 5px; border: 3px solid black; margin: 4px;
                       clip-path: ' . $clip . '; }',
                Origin::UserAgent,
            );
            $root = $this->generator->generate($doc, [$sheet]);
            self::assertNotNull($root);
            $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

            $writer = new PdfWriter();
            $page = $writer->addPage(612, 792);
            $stream = $writer->addContentStream($page);
            $painter = new Painter(792.0);
            $painter->paint($root, $stream);

            $opcodes = $this->operatorTokens($stream->getOperators());
            self::assertContains('W', $opcodes, "`$clip` emits a clip (W) operator");
        }
    }

    public function testPaintContainmentClipsDescendantsButLayoutContainmentDoesNot(): void
    {
        // CSS Contain §2.3 — paint containment (`contain: paint | content
        // | strict`) clips descendants to the padding box even under the
        // default `overflow: visible`, so an oversized child is cut off.
        // `contain: layout` alone does NOT clip (only layout is contained).
        $emitsClip = function (string $contain): bool {
            $doc = $this->html->parseDocument(
                '<html><body><div class="c"><div class="child"></div></div></body></html>',
            );
            $sheet = $this->css->parseStylesheet(
                'html, body, div { display: block; }
                 .c { width: 100px; height: 100px; contain: ' . $contain . '; }
                 .child { width: 300px; height: 300px; background-color: red; }',
                Origin::UserAgent,
            );
            $root = $this->generator->generate($doc, [$sheet]);
            self::assertNotNull($root);
            $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
            $writer = new PdfWriter(compressStreams: false);
            $page = $writer->addPage(612, 792);
            $stream = $writer->addContentStream($page);
            (new Painter(792.0))->paint($root, $stream);

            return in_array('W', $this->operatorTokens($stream->getOperators()), true);
        };

        self::assertTrue($emitsClip('paint'), '`contain: paint` clips descendants');
        self::assertTrue($emitsClip('content'), '`contain: content` clips descendants');
        self::assertTrue($emitsClip('strict'), '`contain: strict` clips descendants');
        self::assertFalse($emitsClip('layout'), '`contain: layout` alone does not clip');
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

    public function testBorderColorInitialIsCurrentColor(): void
    {
        // CSS Backgrounds & Borders 3 §4.2 — the initial value of
        // border-*-color is `currentColor`, so `border: solid; color:
        // green` (no explicit border-color) paints a GREEN border, not the
        // old hard-coded black.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { border: 10px solid; color: rgb(0, 255, 0); height: 30px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);
        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        self::assertStringContainsString('0 1 0', $bytes, 'border paints in currentColor (green)');
        self::assertStringNotContainsString('0 0 0 rg', $bytes, 'border is not black');
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

    public function testFilterDropShadowEmitsOffsetRectBehindBackground(): void
    {
        // Filter Effects 1 §16.1 — `filter: drop-shadow(4px 4px red)`
        // emits an offset rect BEFORE the background paint (sits
        // behind the box visually).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 50px;
                   filter: drop-shadow(4px 4px red); }',
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
        // Expect at least one red fill rect (the drop shadow) plus a
        // white background rect. Match the colour ops by their
        // canonical token (1 0 0 rg for red, 1 1 1 rg for white).
        self::assertStringContainsString('1 0 0 rg', $bytes, 'red drop-shadow emitted');
        self::assertStringContainsString('1 1 1 rg', $bytes, 'white background emitted');
        // Drop-shadow comes BEFORE background — the offset (4 4) is
        // applied to the box's outer rect. Confirm ordering: the red
        // fill appears before the white.
        $redPos = strpos($bytes, '1 0 0 rg');
        $whitePos = strpos($bytes, '1 1 1 rg');
        self::assertNotFalse($redPos);
        self::assertNotFalse($whitePos);
        self::assertLessThan($whitePos, $redPos, 'drop-shadow paints below background');
    }

    public function testFilterNoneEmitsNoExtraShadowRect(): void
    {
        // Negative: `filter: none` (initial) must not emit any extra
        // rects. Rect count matches the no-filter baseline.
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
        (new Painter(792.0))->paint($root, $stream);
        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        self::assertStringNotContainsString('1 0 0 rg', $bytes, 'no shadow when filter is omitted');
    }

    public function testNegativeZIndexChildPaintsBehindInFlowSibling(): void
    {
        // CSS 2.1 §9.9.1 / Appendix E — within a stacking context, a
        // positioned child with a negative `z-index` paints at step 2,
        // BEFORE the box's in-flow non-positioned descendants (step 3).
        // Here a `position: absolute; z-index: -1` red box and a normal
        // in-flow green box overlap; the red must paint first (behind),
        // so the green fill appears LATER in the stream.
        $doc = $this->html->parseDocument(
            '<html><body><div class="c">'
                . '<div class="fg"></div><div class="bg"></div>'
                . '</div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             .c { position: relative; }
             .fg { width: 80px; height: 80px; background-color: rgb(0, 255, 0); }
             .bg { position: absolute; top: 0; left: 0; width: 80px;
                   height: 80px; background-color: rgb(255, 0, 0); z-index: -1; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);
        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');

        $redPos = strpos($bytes, '1 0 0 rg');
        $greenPos = strpos($bytes, '0 1 0 rg');
        self::assertNotFalse($redPos, 'negative-z red box emitted');
        self::assertNotFalse($greenPos, 'in-flow green box emitted');
        self::assertLessThan($greenPos, $redPos, 'z-index:-1 box paints behind the in-flow sibling');
    }

    public function testFloatPaintsOnTopOfInFlowBlockSibling(): void
    {
        // CSS 2.1 Appendix E — a non-positioned float (step 4) paints ON
        // TOP of in-flow non-positioned block-level boxes (step 3). The
        // float is DOM-first and overlaps the following in-flow block; its
        // fill must appear LATER in the stream (on top), not behind. Guards
        // the `float-005` / `clear-004` reftests where a float overlaps a
        // following full-size block.
        $doc = $this->html->parseDocument(
            '<html><body>'
                . '<div class="f"></div><div class="b"></div>'
                . '</body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             .f { float: left; width: 80px; height: 80px;
                  background-color: rgb(0, 255, 0); }
             .b { width: 160px; height: 160px;
                  background-color: rgb(255, 0, 0); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);
        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');

        $greenPos = strpos($bytes, '0 1 0 rg');
        $redPos = strpos($bytes, '1 0 0 rg');
        self::assertNotFalse($greenPos, 'float green box emitted');
        self::assertNotFalse($redPos, 'in-flow red block emitted');
        self::assertLessThan($greenPos, $redPos, 'float paints on top of (after) the in-flow block');
    }

    public function testGridItemsPaintInOrderModifiedOrder(): void
    {
        // CSS Grid 1 §4.2 — grid items paint in "order-modified document
        // order": the `order` property reorders PAINT, not just layout.
        // Two items stacked in the same cell: the red one is DOM-first but
        // has the higher `order`, so it must paint LAST (on top) — its red
        // fill appears AFTER the green in the stream, the reverse of raw
        // document order.
        $doc = $this->html->parseDocument(
            '<html><body><div class="g">'
                . '<div class="r"></div><div class="gr"></div>'
                . '</div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             .g { display: flex; width: 200px; }
             .r { order: 2; width: 80px; height: 80px;
                  background-color: rgb(255, 0, 0); }
             .gr { order: 1; width: 80px; height: 80px;
                   background-color: rgb(0, 255, 0); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);
        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');

        $redPos = strpos($bytes, '1 0 0 rg');
        $greenPos = strpos($bytes, '0 1 0 rg');
        self::assertNotFalse($redPos, 'order:2 red grid item emitted');
        self::assertNotFalse($greenPos, 'order:1 green grid item emitted');
        self::assertLessThan($redPos, $greenPos, 'higher-order item paints later (on top) despite being DOM-first');
    }

    public function testFilterUnsupportedPrimitiveIsNoOp(): void
    {
        // Negative: `filter: blur(5px)` parses but the painter must
        // not emit anything for it (raster pre-painting required).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 50px;
                   filter: blur(5px); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);
        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        // White background still present; no extra shadow colour.
        self::assertStringContainsString('1 1 1 rg', $bytes);
    }

    public function testFilterDropShadowMixedWithOtherPrimitivesStillPaints(): void
    {
        // Positive: a value list `filter: blur(2px) drop-shadow(...)`
        // skips the blur (raster Phase-2 deferral) but still paints
        // the drop-shadow.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: white; height: 50px;
                   filter: blur(2px) drop-shadow(3px 3px green); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);
        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        self::assertMatchesRegularExpression('~0 0\.5\d+ 0 rg~', $bytes, 'green drop-shadow rendered');
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

    public function testDottedBorderEmitsRoundCapPointDashPattern(): void
    {
        // CSS Backgrounds 3 §5 — `dotted` renders as a series of round
        // dots. We get the dots by setting the line cap to round (`1 J`)
        // and feeding a zero-length on-segment dash pattern (`[ 0 P ] 0 d`):
        // each "on" boundary draws a half-circle of radius lineWidth/2 at
        // its position, so a 0-length on-segment shows as a full circle.
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
        self::assertStringContainsString('1 J', $bytes, 'round line cap set for dotted dots');
        // Period is rounded to fit the edge in whole cycles; just assert
        // the zero-length on-segment leads the pattern.
        self::assertMatchesRegularExpression('/\[ 0(?:\.0)? \S+ \] 0 d/', $bytes);
    }

    public function testDashedBorderEmitsTwoToOneRatioPattern(): void
    {
        // CSS Backgrounds 3 §5 — `dashed` uses an implementation-defined
        // segment length. We follow the Chromium / WebKit convention of
        // dash:gap = 2:1 (period 3w), perfect-fit so dashes stay
        // symmetric across the corners.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 120px; height: 30px; border: 10px dashed red; }',
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
        // Inspect every dash array. Each should hold a 2:1 dash-to-gap
        // ratio (i.e. dash ≈ 2*gap), with both segments derived from a
        // 3w-targeted period that's been rounded to fit the edge.
        preg_match_all('/\[ ([\d.]+) ([\d.]+) \] 0 d/', $bytes, $matches, PREG_SET_ORDER);
        self::assertNotEmpty($matches, 'dash pattern emitted');
        foreach ($matches as $m) {
            $dash = (float) $m[1];
            $gap = (float) $m[2];
            self::assertGreaterThan(0.0, $gap, 'dash array carries a non-zero gap');
            self::assertEqualsWithDelta(2.0, $dash / $gap, 0.001, 'dash:gap ≈ 2:1');
        }
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
        self::assertStringContainsString('0.7843137255 0 0 rg', $bytes, 'base colour for bottom/right');
        self::assertStringContainsString('0.3921568627 0 0 rg', $bytes, 'darkened colour for top/left');
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
        self::assertStringContainsString('0.7843137255 0 0 rg', $bytes);
        self::assertStringContainsString('0.3921568627 0 0 rg', $bytes);
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
        self::assertStringContainsString('0.2509803922 0.2509803922 0.2509803922 rg', $bytes, 'darker on top/left');
        // Lighter: 0.501961 + (1 - 0.501961) × 0.3 ≈ 0.651373
        self::assertStringContainsString('0.651372549 0.651372549 0.651372549 rg', $bytes, 'lighter on bottom/right');
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
        self::assertStringContainsString('0.2509803922 0.2509803922 0.2509803922 rg', $bytes);
        self::assertStringContainsString('0.651372549 0.651372549 0.651372549 rg', $bytes);
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
        self::assertStringContainsString('0.7843137255 0 0 rg', $bytes, 'base colour');
        self::assertStringNotContainsString('0.3921568627 0 0 rg', $bytes, 'no darkened variant');
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
        self::assertStringContainsString('0 0.5019607843 0 rg', $bytes, 'green shadow color emitted');
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

    public function testBackgroundClipBorderAreaWithColorEmitsRingFill(): void
    {
        // CSS Backgrounds 4 §3.5 — `border-area` with bg-color paints
        // a ring (border-box ∖ padding-box) via even-odd fill: two
        // rect ops chained with `f*`.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-color: red; background-clip: border-area;
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

        $ops = implode("\n", $stream->getOperators());
        // The even-odd fill (`f*`) operator marks the ring paint:
        // border-box rect + padding-box rect chained with f*.
        self::assertStringContainsString('f*', $ops);
    }

    public function testBackgroundClipBorderAreaWithImageWrapsInRingClip(): void
    {
        // CSS Backgrounds 4 §3.5 — `border-area` + bg-image: the
        // image-paint cluster must be wrapped in q ... W* n ... Q so
        // the image is clipped to the border ring (padding-box hole).
        // Sanity: with no border declared, `border-area` collapses
        // to a degenerate paint region (which is the spec-correct
        // behaviour - "no border, no border-area to paint").
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-clip: border-area;
                   background-image: linear-gradient(red, blue);
                   width: 100px; height: 50px;
                   padding: 10px; border: 20px solid black; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $ops = implode("\n", $stream->getOperators());
        // The wrap emits `W*` (even-odd clip) on a path that
        // includes both border-box and padding-box rects.
        self::assertStringContainsString('W*', $ops);
    }

    public function testBackgroundClipBorderBoxWithImageEmitsNoExtraClip(): void
    {
        // Regression guard: the normal (non-border-area) bg-image
        // path must NOT emit a W* clip — that would over-constrain
        // every background-image paint in the entire codebase.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { background-image: linear-gradient(red, blue);
                   width: 100px; height: 50px;
                   padding: 10px; border: 20px solid black; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $ops = implode("\n", $stream->getOperators());
        self::assertStringNotContainsString('W*', $ops);
    }

    public function testBodyOverflowClipOneAxisPropagatesAsBothAxes(): void
    {
        // CSS Overflow 3 §3.3 + §2.1 — `overflow-x: clip` on the body
        // propagates to the viewport, and because a viewport can't mix a
        // clipped axis with a `visible` one, the y axis (visible) computes
        // to `auto` and also clips. So the propagated clip is bounded on
        // BOTH axes — there is no full-page-height clip rect the way a
        // plain one-axis `overflow-x` on a normal box would produce.
        // Backs WPT overflow-body-propagation-007.
        $doc = $this->html->parseDocument(
            '<html><body style="overflow-x: clip; width: 30px; height: 30px">'
            . '<div style="background-color: blue; width: 400px; height: 400px"></div>'
            . '</body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, div { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, pageWidth: 612.0))->paint($root, $stream);

        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('W', $opcodes, 'propagated body overflow emits a clip');
        // Both axes clip → no clip rect spanning the full page height.
        self::assertDoesNotMatchRegularExpression(
            '/[-0-9.]+\s+[-0-9.]+\s+[-0-9.]+\s+792(\.0+)?\s+re/',
            $bytes,
            'propagated visible y axis computes to auto and clips (not full page height)',
        );
    }

    public function testOverflowXHiddenClipsOnlyTheXAxis(): void
    {
        // CSS Overflow 3 §3.1: `overflow-x: hidden` constrains the
        // horizontal axis only. The clip rect uses the box's
        // padding-edge width but extends across the full page
        // height — so `overflow-y` (visible) is honoured. Verified
        // by checking the clip rect's height matches pageWidth/Height.
        $doc = $this->html->parseDocument(
            '<html><body><div style="overflow-x: hidden; width: 100px; height: 50px"><p style="background-color: red; height: 200px"></p></div></body></html>',
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
        $painter = new Painter(792.0, pageWidth: 612.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('W', $opcodes, 'clip path emitted');
        // The clip rect for x-axis-only should have height === pageHeight
        // (full page). Match `<x> <y> <w> 792 re` somewhere.
        self::assertMatchesRegularExpression(
            '/[-0-9.]+\s+[-0-9.]+\s+[-0-9.]+\s+792(\.0+)?\s+re/',
            $bytes,
            'clip rect spans full page height when y axis is visible',
        );
    }

    public function testOverflowYHiddenClipsOnlyTheYAxis(): void
    {
        // Symmetric: `overflow-y: hidden` clips the y axis but
        // leaves x unconstrained — the clip rect extends the full
        // page width.
        $doc = $this->html->parseDocument(
            '<html><body><div style="overflow-y: hidden; width: 100px; height: 50px"><p style="background-color: red; height: 200px"></p></div></body></html>',
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
        $painter = new Painter(792.0, pageWidth: 612.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('W', $opcodes);
        // Clip rect should have width === pageWidth.
        self::assertMatchesRegularExpression(
            '/0(\.0+)?\s+[-0-9.]+\s+612(\.0+)?\s+[-0-9.]+\s+re/',
            $bytes,
            'clip rect spans full page width when x axis is visible',
        );
    }

    public function testOverflowBothAxesHiddenClipsToPaddingRect(): void
    {
        // Negative against the per-axis path: `overflow: hidden`
        // (both axes) should clip to the padding rect on BOTH axes
        // — not extend to page dimensions.
        $doc = $this->html->parseDocument(
            '<html><body><div style="overflow: hidden; width: 100px; height: 50px"><p style="background-color: red; height: 200px"></p></div></body></html>',
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
        $painter = new Painter(792.0, pageWidth: 612.0);
        $painter->paint($root, $stream);

        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        // Clip rect should be 100 wide × 50 tall (the box's padding
        // rect since no padding declared).
        self::assertMatchesRegularExpression(
            '/[-0-9.]+\s+[-0-9.]+\s+100(\.0+)?\s+50(\.0+)?\s+re/',
            $bytes,
            'both-axes clip uses the padding rect',
        );
    }

    public function testOverflowYAutoTriggersClip(): void
    {
        // overflow-y: auto → clip in print (no scroll viewport).
        $doc = $this->html->parseDocument(
            '<html><body><div style="overflow-y: auto; height: 50px"><p style="background-color: red; height: 200px"></p></div></body></html>',
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

    /**
     * Extract the (x, y, w, h) tuple of the first `re` rectangle.
     *
     * @param array<int, string> $ops
     * @return ?array{float, float, float, float}
     */
    private function firstReRect(array $ops): ?array
    {
        foreach ($ops as $op) {
            if (preg_match('/^([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+re$/', (string) $op, $m)) {
                return [(float) $m[1], (float) $m[2], (float) $m[3], (float) $m[4]];
            }
        }
        return null;
    }

    public function testBoxDecorationBreakSliceDefaultUnchanged(): void
    {
        // Default `slice`: the painter emits the box's full extent
        // even when it straddles the page boundary — PDF clipping
        // handles the visual split.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        // Page 1 covers layout-Y [0..100); the box at layout-Y 40..120
        // straddles. Painter constant = 100 (pageHeight).
        $painter = new Painter(100.0, pageRangeStart: 0.0, pageRangeEnd: 100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $rect = $this->firstReRect($stream->getOperators());
        self::assertNotNull($rect);
        [, $y, , $h] = $rect;
        // Slice: full extent → PDF Y of the box's lower-left = 100 -
        // (40 + 80) = -20, height = 80.
        self::assertEqualsWithDelta(-20.0, $y, 0.001);
        self::assertEqualsWithDelta(80.0, $h, 0.001);
    }

    public function testBoxDecorationBreakCloneClampsBottomOnFirstPage(): void
    {
        // Clone-mode box at layout-Y 40..120 straddles the page 1/2
        // boundary at Y=100. On page 1, the bottom clamps to 100 →
        // visible content height is 60 (from y=40 to y=100).
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', 'clone');
        $painter = new Painter(100.0, pageRangeStart: 0.0, pageRangeEnd: 100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $rect = $this->firstReRect($stream->getOperators());
        self::assertNotNull($rect);
        [, $y, , $h] = $rect;
        // Clamped: PDF Y = 100 - (40 + 60) = 0, height = 60.
        self::assertEqualsWithDelta(0.0, $y, 0.001);
        self::assertEqualsWithDelta(60.0, $h, 0.001);
    }

    public function testBoxDecorationBreakCloneClampsTopOnSecondPage(): void
    {
        // Same box on page 2 (layout-Y [100..200)): the top clamps
        // up to 100, content y becomes 100, content height = 20.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', 'clone');
        // Painter for page 2: constant = (1+1)*100 = 200.
        $painter = new Painter(200.0, pageRangeStart: 100.0, pageRangeEnd: 200.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $rect = $this->firstReRect($stream->getOperators());
        self::assertNotNull($rect);
        [, $y, , $h] = $rect;
        // PDF Y = 200 - (100 + 20) = 80, height = 20.
        self::assertEqualsWithDelta(80.0, $y, 0.001);
        self::assertEqualsWithDelta(20.0, $h, 0.001);
    }

    public function testBoxDecorationBreakCloneNoOpWhenFitsOnPage(): void
    {
        // Negative: clone-mode box that fits entirely on one page
        // paints identically to slice — no clamping.
        $root = $this->buildStraddlingBox(20.0, 40.0, 'red', 'clone');
        $painter = new Painter(100.0, pageRangeStart: 0.0, pageRangeEnd: 100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $rect = $this->firstReRect($stream->getOperators());
        self::assertNotNull($rect);
        [, $y, , $h] = $rect;
        // Full extent: PDF Y = 100 - (20 + 40) = 40, height = 40.
        self::assertEqualsWithDelta(40.0, $y, 0.001);
        self::assertEqualsWithDelta(40.0, $h, 0.001);
    }

    public function testBoxDecorationBreakInvalidKeywordTreatedAsSlice(): void
    {
        // Negative: an unrecognised keyword falls back to slice
        // (clone-treatment doesn't fire) — straddling box renders
        // full extent.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', 'nonsense');
        $painter = new Painter(100.0, pageRangeStart: 0.0, pageRangeEnd: 100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $rect = $this->firstReRect($stream->getOperators());
        self::assertNotNull($rect);
        [, , , $h] = $rect;
        self::assertEqualsWithDelta(80.0, $h, 0.001, 'invalid keyword → full extent');
    }

    public function testBoxDecorationBreakCloneNoOpWithoutPageRange(): void
    {
        // Negative: single-page renders construct the painter without
        // pageRangeStart / pageRangeEnd — clone-treatment can't apply
        // (no seams to clamp to).
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', 'clone');
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $rect = $this->firstReRect($stream->getOperators());
        self::assertNotNull($rect);
        [, , , $h] = $rect;
        self::assertEqualsWithDelta(80.0, $h, 0.001, 'no page range → no clamp');
    }

    public function testBoxDecorationBreakSliceExplicitMatchesDefault(): void
    {
        // Negative: explicit `slice` matches the default behaviour.
        $explicitOps = $this->paintAndGetStream(
            $this->buildStraddlingBox(40.0, 80.0, 'red', 'slice'),
            new Painter(100.0, pageRangeStart: 0.0, pageRangeEnd: 100.0),
        )->getOperators();
        $defaultOps = $this->paintAndGetStream(
            $this->buildStraddlingBox(40.0, 80.0, 'red', null),
            new Painter(100.0, pageRangeStart: 0.0, pageRangeEnd: 100.0),
        )->getOperators();
        self::assertSame($this->firstReRect($defaultOps), $this->firstReRect($explicitOps));
    }

    public function testTransformTranslateEmitsCmMatrix(): void
    {
        // `translate(10px, 20px)` should produce a `cm` operator
        // with the translation in PDF coords (Y negated).
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox($root, 'translate(10px, 20px)');
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        // Expect a cm operator with the translation: PDF cm
        // `(1 0 0 1 10 -20)` from the translate(10, 20) → +10 right,
        // -20 in PDF Y (visually down).
        $found = $this->findCmOp($ops, '/^1 0 0 1 10 -20 cm$/');
        self::assertNotNull($found, 'expected translate cm operator in ' . implode(' | ', $ops));
    }

    public function testIndividualTranslatePropertyEmitsCmMatrix(): void
    {
        // CSS Transforms 2 §5 — the `translate` property (no parens) applies
        // like translate(): `translate: 10px 20px` → cm (1 0 0 1 10 -20).
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $div = $this->firstDivBox($root);
        $div->style->set('translate', new \Phpdftk\Css\Value\ValueList(
            [new \Phpdftk\Css\Value\Length(10.0, \Phpdftk\Css\Value\LengthUnit::Px),
                new \Phpdftk\Css\Value\Length(20.0, \Phpdftk\Css\Value\LengthUnit::Px)],
            \Phpdftk\Css\Value\ListSeparator::Space,
        ));
        $stream = $this->paintAndGetStream($root, new Painter(100.0));
        self::assertNotNull(
            $this->findCmOp($stream->getOperators(), '/^1 0 0 1 10 -20 cm$/'),
            'translate property emits a cm matrix',
        );
    }

    public function testIndividualScaleAndTranslateComposeInOrder(): void
    {
        // §5 order: translate THEN scale. scale:2 + translate:10px → the
        // composed matrix scales by 2 with the translate applied first
        // (a=d=2, e=10, f=0 in PDF space; translate is not scaled because
        // it composes ahead of the scale).
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $div = $this->firstDivBox($root);
        $div->style->set('translate', new \Phpdftk\Css\Value\Length(10.0, \Phpdftk\Css\Value\LengthUnit::Px));
        $div->style->set('scale', new \Phpdftk\Css\Value\Number(2.0));
        $stream = $this->paintAndGetStream($root, new Painter(100.0));
        self::assertNotNull(
            $this->findCmOp($stream->getOperators(), '/^2 0 0 2 10 0 cm$/'),
            'translate then scale compose in order',
        );
    }

    public function testTransformRotateEmitsRotationMatrix(): void
    {
        // `rotate(90deg)` → cm matrix [0, -1, 1, 0, 0, 0] (with
        // float rounding to handle cos/sin precision).
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox($root, 'rotate(90deg)');
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        $found = $this->findCmOp($ops, '/^[\d.eE\-+]+ -1 1 [\d.eE\-+]+ 0 0 cm$/');
        self::assertNotNull($found, 'expected rotate cm operator in ' . implode(' | ', $ops));
    }

    public function testOffsetPathRayTranslatesAndRotates(): void
    {
        // CSS Motion Path 1 §2.2. WPT `offset-path-ray-001` asserts this
        // exact equivalence: with `offset-position: auto` (ray starts at
        // the box's own top-left) and `transform-origin: 0 0`,
        //
        //   offset-path: ray(135deg closest-side); offset-distance: 20px
        //
        // renders identically to `transform: rotate(45deg) translate(20px)`.
        //
        // A ray angle is a compass BEARING — 0deg points up, positive turns
        // clockwise — so the tangent trails it by 90deg, and the default
        // `offset-rotate: auto` turns the box to follow it.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $div = $this->firstDivBox($root);
        $div->style->set('offset-path', (new \Phpdftk\Css\ValueParser())
            ->parseFromString('ray(135deg closest-side)'));
        $div->style->set('offset-distance', new \Phpdftk\Css\Value\Length(20.0, \Phpdftk\Css\Value\LengthUnit::Px));
        $div->style->set('offset-position', new \Phpdftk\Css\Value\Keyword('auto'));

        $rayOps = $this->paintAndGetStream($root, new Painter(100.0))->getOperators();

        // The same box under the reference's plain transform.
        $refRoot = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox($refRoot, 'rotate(45deg) translate(20px)');
        $refOps = $this->paintAndGetStream($refRoot, new Painter(100.0))->getOperators();

        $rayCm = $this->findCmOp($rayOps, '/ cm$/');
        $refCm = $this->findCmOp($refOps, '/ cm$/');
        self::assertNotNull($rayCm, 'ray path emits a cm matrix: ' . implode(' | ', $rayOps));
        self::assertSame($refCm, $rayCm, 'ray() matches the equivalent rotate+translate');
    }

    public function testOffsetPathRayRespectsExplicitRotation(): void
    {
        // A bare `<angle>` on `offset-rotate` REPLACES the tangent rather
        // than adding to it, so a 0deg rotation leaves the box upright and
        // the matrix is a pure translation. Bearing 90deg points right.
        //
        // `transform-origin: 0 0` pins the origin to the box's own top-left,
        // which is also where `offset-position: auto` starts the ray — so
        // the translation is exactly the offset distance, with no
        // origin-recentring term folded in.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $div = $this->firstDivBox($root);
        $div->style->set('transform-origin', new \Phpdftk\Css\Value\ValueList(
            [new \Phpdftk\Css\Value\Length(0.0, \Phpdftk\Css\Value\LengthUnit::Px),
                new \Phpdftk\Css\Value\Length(0.0, \Phpdftk\Css\Value\LengthUnit::Px)],
            \Phpdftk\Css\Value\ListSeparator::Space,
        ));
        $div->style->set('offset-path', (new \Phpdftk\Css\ValueParser())
            ->parseFromString('ray(90deg closest-side)'));
        $div->style->set('offset-distance', new \Phpdftk\Css\Value\Length(30.0, \Phpdftk\Css\Value\LengthUnit::Px));
        $div->style->set('offset-position', new \Phpdftk\Css\Value\Keyword('auto'));
        $div->style->set('offset-rotate', new \Phpdftk\Css\Value\Angle(0.0, \Phpdftk\Css\Value\AngleUnit::Deg));

        $ops = $this->paintAndGetStream($root, new Painter(100.0))->getOperators();
        self::assertNotNull(
            $this->findCmOp($ops, '/^1 0 0 1 30 0 cm$/'),
            'bearing 90deg moves +30 in X with no rotation: ' . implode(' | ', $ops),
        );
    }

    public function testTransformScaleEmitsScaleMatrix(): void
    {
        // `scale(2)` → cm [2, 0, 0, 2, 0, 0].
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox($root, 'scale(2)');
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        $found = $this->findCmOp($ops, '/^2 0 0 2 0 0 cm$/');
        self::assertNotNull($found);
    }

    public function testTransformOriginShiftsAppliedAround(): void
    {
        // With `transform: translate(...)` and a non-default origin,
        // there should be THREE cm calls: T(origin), M, T(-origin).
        // For translate alone the origin doesn't change visual
        // result, but the emission proves the wrap-around fired.
        $root = $this->buildStraddlingBoxWithOrigin(40.0, 80.0, 'translate(5px, 5px)', '0 0');
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        $cmCount = 0;
        foreach ($ops as $op) {
            if (preg_match('/cm$/', (string) $op)) {
                $cmCount++;
            }
        }
        // origin = "0 0" (top-left of box) is non-zero in PDF coords
        // because the box sits at y > 0 → cy = pageHeight - boxY ≠ 0.
        // So three cm calls fire: outer-T, M, inner-T.
        self::assertGreaterThanOrEqual(3, $cmCount);
    }

    public function testTransformNoneDoesNotEmitCm(): void
    {
        // Negative: `transform: none` (initial) → no cm operator.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        foreach ($ops as $op) {
            self::assertDoesNotMatchRegularExpression('/cm$/', (string) $op);
        }
    }

    public function testTransformInvalidValueDoesNotEmitCm(): void
    {
        // Negative: an unrecognised function falls back to the raw
        // value (Transform parsing aborts) — no cm.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox($root, 'matrix3d(1, 0, 0, 0)');
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        foreach ($ops as $op) {
            self::assertDoesNotMatchRegularExpression('/cm$/', (string) $op);
        }
    }

    public function testTransformRotateXFlattensToScaleY(): void
    {
        // Phase-2 upgrade (was: flatten to identity in Phase-1).
        // `rotateX(60deg)` collapses to a vertical scale of
        // cos(60°) = 0.5 — visually correct in a print medium with
        // no perspective. The matrix is `1 0 0 0.5 0 0`.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox($root, 'rotateX(60deg)');
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        $found = false;
        foreach ($ops as $op) {
            if (preg_match('/^1 0 0 0\.5 [\-0-9.]+ [\-0-9.]+ cm$/', (string) $op)) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'rotateX(60deg) emits a Y-scale matrix');
    }

    public function testTransformRotateYFlattensToScaleX(): void
    {
        // Phase-2: `rotateY(60deg)` collapses to a horizontal scale
        // of cos(60°) = 0.5 — visually correct for print.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox($root, 'rotateY(60deg)');
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        $found = false;
        foreach ($ops as $op) {
            if (preg_match('/^0\.5 0 0 1 [\-0-9.]+ [\-0-9.]+ cm$/', (string) $op)) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'rotateY(60deg) emits an X-scale matrix');
    }

    public function testTransformRotateZBehavesLikeRotate(): void
    {
        // Negative: `rotateZ(90deg)` is identical to `rotate(90deg)`
        // — emits a 90° planar rotation matrix.
        $rootZ = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox($rootZ, 'rotateZ(90deg)');
        $paintZ = new Painter(100.0);
        $stream = $this->paintAndGetStream($rootZ, $paintZ);
        $ops = $stream->getOperators();
        // 90° → [cos, -sin, sin, cos] ≈ [0, -1, 1, 0]
        $found = $this->findCmOp(
            $ops,
            '/^\d?(\.\d+)?e?[\-+]?\d* -1 1 \d?(\.\d+)?e?[\-+]?\d* /',
        );
        // Easier: look for "0 -1 1 0" allowing scientific notation noise.
        $foundAny = false;
        foreach ($ops as $op) {
            if (preg_match('/-1 1 /', (string) $op)) {
                $foundAny = true;
                break;
            }
        }
        self::assertTrue($foundAny, 'rotateZ(90deg) emits the Z-axis rotation matrix');
    }

    public function testTransformMatrix3dExtractsAffineEntries(): void
    {
        // Positive: `matrix3d(1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0,
        // 10, 20, 0, 1)` is the 3D form of `matrix(1, 0, 0, 1, 10,
        // 20)` — a pure translation. The painter should emit a
        // matrix with the translate entries.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox(
            $root,
            'matrix3d(1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 10, 20, 0, 1)',
        );
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        $found = $this->findCmOp($ops, '/^1 0 0 1 10 [\-0-9.]+ cm$/');
        self::assertNotNull($found, 'matrix3d extracts the 2D translate');
    }

    public function testTransformPerspectiveIsTreatedAsIdentity(): void
    {
        // Negative: `perspective(500px)` accepts syntax but emits an
        // identity matrix at Phase 2 (print has no depth). The cm
        // operator should still appear (for the wrapper) but with
        // the identity matrix.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox($root, 'perspective(500px)');
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        // Identity matrix has form `1 0 0 1 0 0`. Search for it.
        $foundIdentity = false;
        foreach ($ops as $op) {
            if (preg_match('/^1 0 0 1 [\-0-9.]+ [\-0-9.]+ cm$/', (string) $op)) {
                $foundIdentity = true;
                break;
            }
        }
        self::assertTrue($foundIdentity, 'perspective() flattens to identity');
    }

    public function testTransformBackfaceVisibilityHiddenSuppressesPaint(): void
    {
        // Negative: `backface-visibility: hidden` with `rotateY(180deg)`
        // (which flips cos(θ) to -1) means the back face faces us
        // and shouldn't paint. No background rect should appear.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox(
            $root,
            'rotateY(180deg)',
            'backface-visibility: hidden;',
        );
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        // No fill operators (`f` or `rg`) should appear.
        $hasFill = false;
        foreach ($ops as $op) {
            if (preg_match('/(^|\s)(f|rg)(\s|$)/', (string) $op)) {
                $hasFill = true;
                break;
            }
        }
        self::assertFalse($hasFill, 'backface-hidden + 180deg rotateY suppresses paint');
    }

    public function testTransformBackfaceVisibilityVisibleStillPaints(): void
    {
        // Negative against the suppression path: `backface-visibility:
        // visible` (the initial value) keeps the box painted at any
        // rotation.
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox($root, 'rotateY(180deg)');
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        $hasFill = false;
        foreach ($ops as $op) {
            if (preg_match('/(^|\s)f(\s|$)/', (string) $op)) {
                $hasFill = true;
                break;
            }
        }
        self::assertTrue($hasFill, 'visible backface still paints');
    }

    public function testTransformComposesMultipleFunctions(): void
    {
        // Composing translate + scale: matrix [2, 0, 0, 2, 10, -20]
        // (the scaled translation comes from multiplication order).
        $root = $this->buildStraddlingBox(40.0, 80.0, 'red', null);
        $this->applyTransformToFirstBox($root, 'translate(10px, 20px) scale(2)');
        $painter = new Painter(100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        // Expect ONE composed cm op for the actual transform (plus
        // optional origin translation wrappers). Find a cm whose
        // matrix has the [2, 0, 0, 2] scale factor.
        $found = $this->findCmOp($ops, '/^2 0 0 2 [\d.eE\-+]+ [\d.eE\-+]+ cm$/');
        self::assertNotNull($found);
    }

    public function testBoxDecorationBreakCloneClampsBorders(): void
    {
        // Clone-mode box with borders → borders draw at the clamped
        // extent, so the bottom border appears at the page seam (not
        // off-page).
        $root = $this->buildStraddlingBoxWithBorder(40.0, 80.0, 'red', 'clone');
        $painter = new Painter(100.0, pageRangeStart: 0.0, pageRangeEnd: 100.0);
        $stream = $this->paintAndGetStream($root, $painter);
        $ops = $stream->getOperators();
        // Bottom border rect: layout content from y=40+60-2 to y=40+60
        // (bottom border of 2pt at the synthetic seam). One of the
        // border rects should sit with its lower-left at PDF Y > 0
        // (visible on this page).
        $borderRects = [];
        foreach ($ops as $op) {
            if (preg_match('/^([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+re$/', (string) $op, $m)) {
                $borderRects[] = [(float) $m[2], (float) $m[4]];
            }
        }
        // At least one rect should have lower-left y >= 0 (visible
        // on page) — the synthetic bottom border at the seam.
        $hasVisibleBottom = false;
        foreach ($borderRects as [$y, $h]) {
            if ($y >= 0.0 && $h <= 5.0) {
                $hasVisibleBottom = true;
                break;
            }
        }
        self::assertTrue($hasVisibleBottom, 'expected a thin border rect visible on page (the synthetic clamped bottom)');
    }

    /**
     * Build a single block box at layout Y `$y` with `$height` and a
     * background color. `$decorationBreak` sets the
     * `box-decoration-break` property (or null to leave it unset).
     */
    private function buildStraddlingBox(float $y, float $height, string $color, ?string $decorationBreak): \Phpdftk\HtmlToPdf\Box\Box
    {
        $doc = $this->html->parseDocument('<html><body><div class="straddle"></div></body></html>');
        $extra = $decorationBreak !== null ? "; box-decoration-break: {$decorationBreak}" : '';
        // padding-top on body (not margin-top on div) so margin collapse
        // doesn't pull the div back to y=0.
        $css = sprintf(
            'html, body, div { display: block; }
             body { margin: 0; padding-top: %fpx; }
             .straddle { width: 100px; height: %fpx; background-color: %s%s; }',
            $y,
            $height,
            $color,
            $extra,
        );
        $sheet = $this->css->parseStylesheet($css, Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 100, 0, 0, new LengthContext()),
        );
        return $root;
    }

    private function buildStraddlingBoxWithBorder(float $y, float $height, string $color, ?string $decorationBreak): \Phpdftk\HtmlToPdf\Box\Box
    {
        $doc = $this->html->parseDocument('<html><body><div class="straddle"></div></body></html>');
        $extra = $decorationBreak !== null ? "; box-decoration-break: {$decorationBreak}" : '';
        $css = sprintf(
            'html, body, div { display: block; }
             body { margin: 0; padding-top: %fpx; }
             .straddle { width: 100px; height: %fpx; background-color: %s;
                         border: 2px solid blue%s; }',
            $y,
            $height,
            $color,
            $extra,
        );
        $sheet = $this->css->parseStylesheet($css, Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 100, 0, 0, new LengthContext()),
        );
        return $root;
    }

    private function paintAndGetStream(\Phpdftk\HtmlToPdf\Box\Box $root, Painter $painter): \Phpdftk\Pdf\Core\Content\ContentStream
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter->paint($root, $stream);
        return $stream;
    }

    /**
     * Find the first `cm` operator matching the given regex; returns
     * the full operator string or null.
     *
     * @param array<int, string> $ops
     */
    private function findCmOp(array $ops, string $regex): ?string
    {
        foreach ($ops as $op) {
            if (preg_match($regex, (string) $op)) {
                return (string) $op;
            }
        }
        return null;
    }

    private function firstDivBox(\Phpdftk\HtmlToPdf\Box\Box $root): \Phpdftk\HtmlToPdf\Box\Box
    {
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node->element !== null && $node->element->localName === 'div') {
                return $node;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::fail('no div box found');
    }

    private function applyTransformToFirstBox(
        \Phpdftk\HtmlToPdf\Box\Box $root,
        string $transformCss,
        string $extraCss = '',
    ): void {
        $div = null;
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node->element !== null && $node->element->localName === 'div') {
                $div = $node;
                break;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertNotNull($div);
        $parser = new \Phpdftk\Css\ValueParser();
        $value = $parser->parseTransform($transformCss);
        $div->style->set('transform', $value);
        // Optional extra declarations parsed and applied to the same
        // box — used for properties like `backface-visibility` that
        // need to coexist with the transform in tests.
        if ($extraCss !== '') {
            $cssParser = new \Phpdftk\Css\Parser();
            $sheet = $cssParser->parseStylesheet('.x { ' . $extraCss . ' }');
            foreach ($sheet->rules as $rule) {
                if (!$rule instanceof \Phpdftk\Css\Sheet\StyleRule) {
                    continue;
                }
                foreach ($rule->declarations as $d) {
                    $div->style->set($d->property, $d->value);
                }
            }
        }
    }

    private function buildStraddlingBoxWithOrigin(float $y, float $height, string $transform, string $originCss): \Phpdftk\HtmlToPdf\Box\Box
    {
        $doc = $this->html->parseDocument('<html><body><div class="t"></div></body></html>');
        $css = sprintf(
            'html, body, div { display: block; }
             body { margin: 0; padding-top: %fpx; }
             .t { width: 100px; height: %fpx; background-color: red;
                  transform: %s; transform-origin: %s; }',
            $y,
            $height,
            $transform,
            $originCss,
        );
        $sheet = $this->css->parseStylesheet($css, Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 100, 0, 0, new LengthContext()),
        );
        return $root;
    }

    public function testInsideMarkerIsNotAlsoPaintedBesideTheBox(): void
    {
        // CSS Lists 3 §3.3 — an `inside` marker is inline content that
        // BoxGenerator materialises; the painter must not draw a second
        // copy beside the principal box.
        $doc = $this->html->parseDocument(
            '<html><body><ul><li>x</li></ul></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ul { display: block; }
             ul { padding-left: 24pt; }
             li { display: list-item; list-style-type: disc;
                  list-style-position: inside; }',
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
        self::assertSame(0, $curveCount, 'no painter-side disc for an inside marker');
    }

    public function testEmptyListItemsWithInsideMarkersStepDownOneLineEach(): void
    {
        // An empty `<li></li>` still carries a marker, so it still
        // generates a line box — three of them must not collapse onto
        // each other at the same y.
        $fontPath = __DIR__ . '/../../../wpt-harness/resources/fonts/DejaVuSerif.ttf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('DejaVu TrueType fixture font missing');
        }
        $ttf = (new \Phpdftk\FontParser\TrueTypeParser($fontPath))->parse();
        $doc = $this->html->parseDocument(
            '<html><body><ol><li></li><li></li><li></li></ol></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ol { display: block; }
             ol { padding-left: 24pt; margin: 0; }
             li { display: list-item; list-style-type: decimal;
                  list-style-position: inside; margin: 0; padding: 0; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $ttf),
        );

        $ys = [];
        $seen = new \SplObjectStorage();
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node->element !== null
                && strtolower($node->element->localName) === 'li'
                && !$seen->contains($node->element)
            ) {
                $seen->attach($node->element);
                $ys[] = $node->geometry->y;
            }
            foreach ($node->children as $c) {
                array_unshift($stack, $c);
            }
        }
        self::assertCount(3, $ys);
        self::assertGreaterThan($ys[0], $ys[1], 'second empty item sits below the first');
        self::assertGreaterThan($ys[1], $ys[2], 'third empty item sits below the second');
    }

    public function testDecimalMarkerEmitsTextWithTrueTypeFont(): void
    {
        // Regression: the counter-marker painter narrowed the parsed face
        // to `OpenTypeData` (the CFF flavour), so EVERY numbered marker
        // silently vanished whenever the default font was a TrueType
        // (`glyf`) face — which is the common case.
        $fontPath = __DIR__ . '/../../../wpt-harness/resources/fonts/DejaVuSerif.ttf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('DejaVu TrueType fixture font missing');
        }
        $ttf = (new \Phpdftk\FontParser\TrueTypeParser($fontPath))->parse();

        // Empty `<li>`s so the ONLY text the painter can emit is the
        // three markers — a Tj count driven by list content would pass
        // even with the markers dropped.
        $doc = $this->html->parseDocument(
            '<html><body><ol><li></li><li></li><li></li></ol></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, ol { display: block; }
             ol { padding-left: 24pt; }
             li { display: list-item; list-style-type: decimal; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $ttf);
        $this->layout->layout($root, $ctx);

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $codepoints = array_merge(range(ord('0'), ord('9')), [ord('.')]);
        $registered = $writer->addCompositeFont($ttf, $codepoints, $page);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0, $registered);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        $tjCount = count(array_filter($opcodes, static fn($n) => $n === 'Tj'));
        self::assertSame(3, $tjCount, 'one Tj per decimal marker');
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

    public function testTransparentBorderEmitsNoStroke(): void
    {
        // CSS Backgrounds 3 §4.4: a fully transparent border colour
        // contributes nothing visible. Painter must skip the side fill
        // rather than fall through to DeviceRGB (which has no alpha) and
        // render the colour as black.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px;
                   border: 10px solid transparent; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);
        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        self::assertStringNotContainsString('0 0 0 rg', $bytes, 'no spurious black border stroke');
    }

    public function testFloatedReplacedElementRendersItsImage(): void
    {
        // A FLOATED (or block-level) `<img>` is blockified out of the
        // inline flow into a BlockBox; it must still paint its image.
        // Previously paintImage bailed on any non-AtomicInlineBox, so a
        // floated `<img>` rendered nothing (regression across css-images'
        // object-fit / object-position clusters, which float their
        // replaced elements).
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
            . '<rect width="100%" height="100%" fill="lime"/></svg>';
        $dataUri = 'data:image/svg+xml,' . rawurlencode($svg);
        $doc = $this->html->parseDocument(
            '<html><body><img src="' . $dataUri . '"></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             img { display: inline-block; float: left; width: 40px; height: 40px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(pageHeight: 792.0, page: $page, writer: $writer))
            ->paint($root, $stream);

        $bytes = $writer->toBytes();
        self::assertStringContainsString('0 1 0 rg', $bytes, 'floated img SVG lime fill emitted');
    }

    public function testVideoPosterImageRenders(): void
    {
        // CSS Images 3 — a `<video>`'s `poster` frame is a replaced image
        // and renders (with object-fit) like `<img>`. The image comes from
        // the `poster` attribute, not `src`.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
            . '<rect width="100%" height="100%" fill="lime"/></svg>';
        $dataUri = 'data:image/svg+xml,' . rawurlencode($svg);
        $doc = $this->html->parseDocument(
            '<html><body><video poster="' . $dataUri . '"></video></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             video { display: inline-block; width: 40px; height: 30px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(pageHeight: 792.0, page: $page, writer: $writer))
            ->paint($root, $stream);

        self::assertStringContainsString('0 1 0 rg', $writer->toBytes(), 'video poster SVG lime fill emitted');
    }

    public function testImageColorFunctionCurrentColorBackground(): void
    {
        // CSS Images 4 §4 — `background-image: image(currentcolor)` is a
        // solid image of the element's `color`. With `color: lime` the
        // background fills with lime (0 1 0 rg), not the red fallback.
        $doc = $this->html->parseDocument(
            '<html><body><div id="d"></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             #d { display: block; width: 50px; height: 50px;
                  color: lime; background-color: red;
                  background-image: image(currentcolor); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(pageHeight: 792.0, page: $page, writer: $writer))
            ->paint($root, $stream);

        self::assertStringContainsString('0 1 0 rg', $writer->toBytes(), 'image(currentcolor) resolved to lime');
    }

    public function testSvgDataUriBackgroundEmitsSvgPaintOperators(): void
    {
        // Lime-filled SVG passed as a data: URI background. The painter
        // should route through svg-to-pdf rather than the raster XObject
        // path — i.e. emit a fill rule and lime colour operator, not a
        // `Do` XObject reference.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
            . '<rect width="100%" height="100%" fill="lime"/></svg>';
        $dataUri = 'data:image/svg+xml,' . rawurlencode($svg);
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px;
                   background-image: url("' . $dataUri . '"); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(
            pageHeight: 792.0,
            page: $page,
            writer: $writer,
        ))->paint($root, $stream);

        $bytes = $writer->toBytes();
        // Lime in PDF DeviceRGB: 0/255, 255/255, 0/255 → "0 1 0 rg".
        self::assertStringContainsString('0 1 0 rg', $bytes, 'lime fill emitted');
        // Filled rect operator — proves we walked through paintRect.
        self::assertMatchesRegularExpression('/ re\n/', $bytes, 'rect operator emitted');
        self::assertMatchesRegularExpression('/\nf\n/', $bytes, 'fill operator emitted');
    }

    public function testUnresolvableUrlMaskHidesElement(): void
    {
        // CSS Masking 1 §4 — a `mask-image: url(...)` we cannot resolve is
        // an empty (transparent-black) mask that masks the element out, so
        // it paints nothing. A gradient mask is left alone (still painted).
        $hiddenSheet = 'html, body, div { display: block; }
             div { width: 100px; height: 100px; background: green;
                   mask-image: url(non-existent.png); }';
        $shownSheet = 'html, body, div { display: block; }
             div { width: 100px; height: 100px; background: green;
                   mask-image: linear-gradient(black, white); }';
        $counts = [];
        foreach (['url' => $hiddenSheet, 'gradient' => $shownSheet] as $key => $css) {
            $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
            $sheet = $this->css->parseStylesheet($css, Origin::UserAgent);
            $root = $this->generator->generate($doc, [$sheet]);
            self::assertNotNull($root);
            $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
            $writer = new PdfWriter();
            $page = $writer->addPage(612, 792);
            $stream = $writer->addContentStream($page);
            (new Painter(792.0))->paint($root, $stream);
            $counts[$key] = count($stream->getOperators());
        }
        self::assertSame(0, $counts['url'], 'unresolvable url() mask → nothing painted');
        self::assertGreaterThan(0, $counts['gradient'], 'gradient mask → element still painted');
    }

    public function testTranslucentGradientMaskInstallsLuminositySoftMask(): void
    {
        // CSS Masking 1 §4 — `mask-image: linear-gradient(rgba(...,0),
        // rgba(...,1))` in the default `match-source` (alpha) mode masks
        // the box by the gradient's alpha. The painter wraps the box paint
        // in a Luminosity soft mask built from that alpha (`gs` + SMask).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px; background: purple;
                   mask-image: linear-gradient(rgba(0,0,255,0), rgba(0,0,255,1)); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('gs', $opcodes, 'translucent gradient mask installs a soft-mask ExtGState');
        $bytes = $writer->generate();
        self::assertStringContainsString('/SMask', $bytes);
        self::assertStringContainsString('/S /Luminosity', $bytes);
        // The purple background still paints (masked, not hidden) — inside
        // the transparency group the mask is applied to, so the PAGE stream
        // holds the `Do` and the fill lives in the group's own stream.
        self::assertContains('Do', $opcodes, 'masked subtree is drawn as one group');
        self::assertStringContainsString('0.5019607843 0 0.5019607843 rg', $bytes);
    }

    public function testUrlImageMaskInstallsAlphaSoftMask(): void
    {
        // CSS Masking 1 §4 — `mask-image: url(<image>)` in the default
        // `match-source` mode masks the box by the image's ALPHA: an Alpha
        // soft mask (`gs` + `/SMask /S /Alpha`) built from the resolved image
        // is installed over the box paint — the element is NOT blanked.
        $png = 'data:image/png;base64,' . base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px; background: purple;
                   mask-image: url(' . $png . '); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('gs', $opcodes, 'url() image mask installs a soft-mask ExtGState');
        $bytes = $writer->generate();
        self::assertStringContainsString('/SMask', $bytes);
        self::assertStringContainsString('/S /Alpha', $bytes);
        self::assertContains('Do', $opcodes, 'masked subtree is drawn as one group');
        self::assertStringContainsString('0.5019607843 0 0.5019607843 rg', $bytes);
    }

    public function testSvgMaskElementReferenceInstallsALuminositySoftMask(): void
    {
        // CSS Masking 1 §4.1 — `mask-image: url(#id)` pointing at an SVG
        // `<mask>` ELEMENT masks by that element's children. The mask
        // brings its own geometry, so it defaults to `/S /Luminosity`
        // (SVG 2 §14.5's `mask-type: luminance`).
        $doc = $this->html->parseDocument(
            '<html><body>'
            . '<div style="width: 200px; height: 200px; background: green; '
            . 'mask-image: url(#m)"></div>'
            . '<svg><mask id="m">'
            . '<rect x="50" y="50" width="100" height="100" fill="white"/>'
            . '</mask></svg>'
            . '</body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, div { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $bytes = $writer->generate();
        self::assertStringContainsString('/SMask', $bytes);
        self::assertStringContainsString('/S /Luminosity', $bytes);
        // The mask's own rect is emitted in the element's user space.
        self::assertStringContainsString('50 50 100 100 re', $bytes);
    }

    public function testMaskModeOverridesTheMaskElementsMaskType(): void
    {
        // CSS Masking 1 §3.3 — `mask-mode: alpha` wins over the `<mask>`
        // element's own `mask-type`, which is `luminance` by default.
        $doc = $this->html->parseDocument(
            '<html><body>'
            . '<div style="width: 200px; height: 200px; background: green; '
            . 'mask-mode: alpha; mask-image: url(#m)"></div>'
            . '<svg><mask id="m"><rect width="100" height="100" fill="white"/></mask></svg>'
            . '</body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, div { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);
        self::assertStringContainsString('/S /Alpha', $writer->generate());
    }

    public function testMaskImageUrlToAMissingOrNonMaskTargetDoesNotBuildAMaskGroup(): void
    {
        // Negative: `url(#id)` that resolves to nothing, or to something
        // that is not a `<mask>`, must not produce a mask group. CSS
        // Masking 1 §4.1 makes such a layer transparent black, so the
        // element (and its subtree) paints nothing at all — the existing
        // "definitively failed" fallback, not a half-built mask.
        foreach (['url(#nope)', 'url(#notamask)'] as $value) {
            $doc = $this->html->parseDocument(
                '<html><body>'
                . '<div style="width: 100px; height: 100px; background: green; '
                . 'mask-image: ' . $value . '"></div>'
                . '<svg><rect id="notamask" width="10" height="10"/></svg>'
                . '</body></html>',
            );
            $sheet = $this->css->parseStylesheet('html, body, div { display: block; }', Origin::UserAgent);
            $root = $this->generator->generate($doc, [$sheet]);
            $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
            $writer = new PdfWriter(compressStreams: false);
            $page = $writer->addPage(612, 792);
            $stream = $writer->addContentStream($page);
            (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);
            self::assertStringNotContainsString('/SMask', $writer->generate(), $value);
        }
    }

    public function testMaskedSubtreeIsOneDrawUnderTheSoftMask(): void
    {
        // Regression guard: the masked scope must contain exactly ONE
        // marking operation, the `Do` of the group form. Ghostscript drops
        // a soft mask at the first `Q` inside the masked scope, and the
        // painter wraps every paint step in its own `q`/`Q` — so a box
        // painted step-by-step under `gs` kept only its FIRST block masked
        // (a masked div rendered its borders at full opacity).
        $png = 'data:image/png;base64,' . base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px; background: purple;
                   border: 10px solid red;
                   mask-image: url(' . $png . '); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $ops = array_map('trim', $stream->getOperators());
        $gsIndex = null;
        foreach ($ops as $i => $op) {
            if (str_ends_with($op, ' gs')) {
                $gsIndex = $i;
                break;
            }
        }
        self::assertIsInt($gsIndex, 'soft-mask ExtGState installed');
        // Everything up to the closing `Q` of the mask scope.
        $scope = [];
        for ($i = $gsIndex + 1; $i < count($ops); $i++) {
            if ($ops[$i] === 'Q') {
                break;
            }
            $scope[] = $ops[$i];
        }
        self::assertCount(1, $scope, 'exactly one operator inside the mask scope');
        self::assertStringEndsWith(' Do', $scope[0], 'and it is the group draw');
    }

    public function testUrlImageMaskWithNonDefaultGeometryStillInstallsSoftMask(): void
    {
        // CSS Masking 1 §4 — a url() image mask with a non-default box model
        // (mask-position / mask-repeat / mask-clip / mask-size) is drawn via
        // the shared image-tiling machinery into the soft-mask group, NOT
        // blanked. So `gs` + `/SMask` are still installed.
        $png = 'data:image/png;base64,' . base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px; background: purple;
                   mask-image: url(' . $png . ');
                   mask-position: 10px 20px; mask-repeat: no-repeat;
                   mask-clip: content-box; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('gs', $opcodes, 'non-default mask geometry still installs a soft mask');
        $bytes = $writer->generate();
        self::assertStringContainsString('/SMask', $bytes);
        self::assertContains('Do', $opcodes, 'masked subtree is drawn as one group');
        self::assertStringContainsString('0.5019607843 0 0.5019607843 rg', $bytes);
    }

    public function testNonDefaultMaskGeometryLeavesGradientMaskUnapplied(): void
    {
        // The gradient-mask path only models the default full-border-box
        // geometry; an explicit non-default `mask-size` bails to unmasked
        // paint rather than mis-positioning the mask (no `gs`).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px; background: purple;
                   mask-image: linear-gradient(rgba(0,0,255,0), rgba(0,0,255,1));
                   mask-size: 50px 50px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('gs', $opcodes, 'non-default mask-size bails to unmasked paint');
        self::assertContains('rg', $opcodes, 'box still paints its background');
    }

    public function testGridGapRulesPaintStrokes(): void
    {
        // CSS Gaps 1 — a grid with `column-rule` / `row-rule` and gaps
        // paints rule lines (stroke `S`) centred in each gap.
        $doc = $this->html->parseDocument(
            '<html><body><div class="g">'
            . '<div></div><div></div><div></div><div></div>'
            . '</div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             .g { display: grid; grid-template-columns: 40px 40px;
                  grid-template-rows: 40px 40px; column-gap: 10px; row-gap: 10px;
                  column-rule-style: solid; column-rule-width: 4px; column-rule-color: blue;
                  row-rule-style: solid; row-rule-width: 4px; row-rule-color: green; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('S', $opcodes, 'gap rules stroke lines');
        self::assertContains('RG', $opcodes, 'gap rules set a stroke colour');
    }

    public function testGridGapRulesSkippedForVerticalWritingMode(): void
    {
        // CSS Gaps 1 — gap decorations are writing-mode dependent (the
        // logical column/row axes swap physically). The naive painter
        // only handles `horizontal-tb`, so a vertical grid skips rules
        // (no stroke) rather than drawing them on the wrong axis.
        $doc = $this->html->parseDocument(
            '<html><body><div class="g">'
            . '<div></div><div></div><div></div><div></div>'
            . '</div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             .g { display: grid; writing-mode: vertical-rl;
                  grid-template-columns: 40px 40px; grid-template-rows: 40px 40px;
                  column-gap: 10px; row-gap: 10px;
                  column-rule-style: solid; column-rule-width: 4px; column-rule-color: blue; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('S', $opcodes, 'vertical writing-mode → gap rules skipped');
    }

    public function testGridRowRuleSkippedForNonDefaultVisibilityItems(): void
    {
        // CSS Gaps 1 §3.3 — the naive grid painter only strokes a
        // continuous full-track rule, which is correct for the default
        // `spanning-item` break with `visibility-items: all`. A non-default
        // `row-rule-visibility-items` (`around`) segments the rule per item,
        // which we do not model — so the row rule is skipped (painting a
        // wrong continuous rule is worse than none). Only a row rule is set
        // here, so nothing strokes.
        $doc = $this->html->parseDocument(
            '<html><body><div class="g">'
            . '<div></div><div></div><div></div><div></div>'
            . '</div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             .g { display: grid; grid-template-columns: 40px 40px;
                  grid-template-rows: 40px 40px; column-gap: 10px; row-gap: 10px;
                  row-rule: 4px solid green; row-rule-visibility-items: around; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('S', $opcodes, 'non-default row-rule-visibility-items → row rule skipped');
    }

    public function testGridRowRuleFromShorthandPaintsWhenContinuous(): void
    {
        // CSS Gap Decorations 1 §3.2 — the `row-rule` shorthand must expand
        // to row-rule-* longhands (regression guard for the expander) and,
        // with the default break/visibility, paint a continuous rule.
        $doc = $this->html->parseDocument(
            '<html><body><div class="g">'
            . '<div></div><div></div><div></div><div></div>'
            . '</div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             .g { display: grid; grid-template-columns: 40px 40px;
                  grid-template-rows: 40px 40px; column-gap: 10px; row-gap: 10px;
                  row-rule: 4px solid green; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('S', $opcodes, 'row-rule shorthand expands and paints a continuous rule');
    }

    public function testFlexGapRulesPaintStrokes(): void
    {
        // CSS Gaps 1 — a wrapped flex container with `column-rule` /
        // `row-rule` and gaps paints rule lines (stroke `S`) between its
        // items (column-rule) and between its flex lines (row-rule).
        $doc = $this->html->parseDocument(
            '<html><body><div class="f">'
            . '<div></div><div></div><div></div><div></div>'
            . '</div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             .f { display: flex; flex-wrap: wrap; width: 90px;
                  column-gap: 10px; row-gap: 10px;
                  column-rule-style: solid; column-rule-width: 4px; column-rule-color: blue;
                  row-rule-style: solid; row-rule-width: 4px; row-rule-color: green; }
             .f > div { width: 40px; height: 40px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('S', $opcodes, 'flex gap rules stroke lines');
        self::assertContains('RG', $opcodes, 'flex gap rules set a stroke colour');
    }

    public function testFlexGapRulesSkippedForVerticalWritingMode(): void
    {
        // CSS Gaps 1 — flex gap decorations are writing-mode dependent;
        // the guarded painter only handles `horizontal-tb`, so a vertical
        // flex container skips rules rather than drawing them on the wrong
        // axis.
        $doc = $this->html->parseDocument(
            '<html><body><div class="f">'
            . '<div></div><div></div><div></div><div></div>'
            . '</div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             .f { display: flex; flex-wrap: wrap; writing-mode: vertical-rl;
                  width: 90px; column-gap: 10px; row-gap: 10px;
                  column-rule-style: solid; column-rule-width: 4px; column-rule-color: blue;
                  row-rule-style: solid; row-rule-width: 4px; row-rule-color: green; }
             .f > div { width: 40px; height: 40px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('S', $opcodes, 'vertical writing-mode → flex gap rules skipped');
    }

    public function testFlexGapRulesNoneStyleEmitsNoStroke(): void
    {
        // CSS Gaps 1 — the initial `*-rule-style: none` means a flex
        // container with gaps but no authored rule paints nothing.
        $doc = $this->html->parseDocument(
            '<html><body><div class="f">'
            . '<div></div><div></div><div></div><div></div>'
            . '</div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             .f { display: flex; flex-wrap: wrap; width: 90px;
                  column-gap: 10px; row-gap: 10px; }
             .f > div { width: 40px; height: 40px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('S', $opcodes, 'no authored rule → no gap-rule stroke');
    }

    public function testCornerShapeRoundEmitsCurves(): void
    {
        // CSS Borders 4 §5 — the default `corner-shape: round` draws each
        // border-radius corner as a Bézier arc (`c` operator).
        $doc = $this->html->parseDocument('<html><body><div class="b"></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             .b { width: 100px; height: 100px; background-color: green; border-radius: 25px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter();
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('c', $opcodes, 'round corners use Bézier curves');
    }

    public function testCornerShapeBevelEmitsStraightCorners(): void
    {
        // CSS Borders 4 §5 — `corner-shape: bevel` replaces each rounded
        // corner with a straight chamfer, so the background fill path has
        // no curve operator despite a non-zero border-radius.
        $doc = $this->html->parseDocument('<html><body><div class="b"></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             .b { width: 100px; height: 100px; background-color: green;
                  border-radius: 25px; corner-shape: bevel; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter();
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('c', $opcodes, 'bevel corners are straight, no Bézier');
    }

    public function testCornerShapeSquareFillsToBoxCorner(): void
    {
        // CSS Borders 4 §5 — `corner-shape: square` fills the corner out to
        // the box corner (a sharp right angle), so like bevel it emits no
        // curve; `superellipse(<large>)` maps to square too.
        $doc = $this->html->parseDocument('<html><body><div class="b"></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             .b { width: 100px; height: 100px; background-color: green;
                  border-radius: 25px; corner-shape: square superellipse(24) square square; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter();
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('c', $opcodes, 'square + high superellipse corners are sharp, no Bézier');
    }

    public function testSingleStopGradientPaintsSolidFill(): void
    {
        // CSS Images 3 §3.5.1 — a gradient with a single color stop renders
        // as a solid fill of that color. `linear-gradient(green)` must emit a
        // green rectangle fill (`rg` + `re` + `f`), not fall through unpainted.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px; background-image: linear-gradient(green); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $painter = new Painter(792.0);
        $painter->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('rg', $opcodes, 'single-stop gradient sets an RGB fill colour');
        self::assertContains('f', $opcodes, 'single-stop gradient fills a rectangle');
    }

    public function testTranslucentLinearGradientEmitsLuminositySoftMask(): void
    {
        // A `linear-gradient` whose stops carry alpha (`rgba(...,0)` →
        // `rgba(...,1)`) can't be expressed by a PDF colour shading alone
        // (shadings have no alpha), so the painter installs a Luminosity
        // soft mask built from the per-stop alpha and emits `gs` before
        // the fill (ISO 32000-2 §11.6.5.2).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px;
                   background-image: linear-gradient(to right,
                     rgba(128,0,128,0), rgba(128,0,128,1)); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('gs', $opcodes, 'translucent gradient installs a soft-mask ExtGState');
        $bytes = $writer->generate();
        self::assertStringContainsString('/SMask', $bytes);
        self::assertStringContainsString('/S /Luminosity', $bytes);
        self::assertStringContainsString('/DeviceGray', $bytes, 'alpha carried as a DeviceGray shading');
    }

    public function testOpaqueLinearGradientEmitsNoSoftMask(): void
    {
        // The alpha soft mask is only built when a stop is translucent —
        // a fully-opaque gradient keeps the pre-existing byte output with
        // no `gs` / SMask overhead (zero-regression fast path).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px;
                   background-image: linear-gradient(to right, red, blue); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertNotContains('gs', $opcodes, 'opaque gradient installs no soft mask');
    }

    public function testConicGradientPaintsFunctionBasedShading(): void
    {
        // CSS Images 4 §3.5 — `conic-gradient()` paints via a
        // function-based ShadingType-1 pattern (a PostScript calculator
        // maps each point to its sweep angle). The painter fills the box
        // with the pattern (`/Pattern cs` + pattern `scn`).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px;
                   background-image: conic-gradient(red 0 25%, green 25% 50%,
                     blue 50% 75%, black 75% 100%); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('scn', $opcodes, 'conic gradient fills with a shading pattern');
        $bytes = $writer->generate();
        self::assertStringContainsString('/ShadingType 1', $bytes);
        self::assertStringContainsString('/FunctionType 4', $bytes);
    }

    public function testRadialGradientPaintsRadialShadingAtAbsoluteCentre(): void
    {
        // CSS Images 3 §3.5.1 — `radial-gradient()` paints via a
        // ShadingType-3 (concentric circles) pattern. The shading is
        // anchored in ABSOLUTE page coordinates (a PDF shading pattern
        // ignores the CTM), so the Coords array must carry the box's
        // page-space centre, NOT the origin. Box is 100×100 at the top of
        // an 800-tall body on a 792-pt page; its centre x = 50.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px;
                   background-image: radial-gradient(circle 40px at 50px 50px, red, blue); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('scn', $opcodes, 'radial gradient fills with a shading pattern');
        $bytes = $writer->generate();
        self::assertStringContainsString('/ShadingType 3', $bytes);
        // Opaque last stop (blue) → the shading extends past the outer
        // circle to flood the box edge.
        self::assertStringContainsString('/Extend [ true true ]', $bytes);
        // Coords: inner (x, y, 0) then outer (x, y, 40). Centre x = 50 in
        // page space — proves it is not left at the origin.
        self::assertMatchesRegularExpression('#/Coords \[ 50 #', $bytes);
    }

    public function testRadialGradientWithTransparentLastStopDoesNotExtend(): void
    {
        // A translucent final stop (`…, transparent`) must fade out rather
        // than flood the box with the stop's opaque RGB — PDF colour
        // shadings have no alpha, so the painter leaves Extend off there,
        // stopping paint at the outer circle (reads as transparency).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px;
                   background-image: radial-gradient(25px at 50px 50px, green 100%, transparent); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $bytes = $writer->generate();
        self::assertStringContainsString('/ShadingType 3', $bytes);
        self::assertStringNotContainsString('/Extend', $bytes, 'transparent last stop must not extend');
    }

    public function testImageSetRadialGradientUnwrapsToShading(): void
    {
        // CSS Images 4 §6 — `image-set()` wrapping a gradient paints the
        // selected option's image. `image-set(radial-gradient(...) 1x)`
        // must render the same ShadingType-3 as the bare gradient (the 1x
        // option is chosen for the print target).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px;
                   background-image: image-set(radial-gradient(green, blue) 1x); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('scn', $opcodes, 'image-set gradient fills with a shading pattern');
        self::assertStringContainsString('/ShadingType 3', $writer->generate());
    }

    public function testImageSetBareStringUnwrapsToImageXObject(): void
    {
        // CSS Images 4 §6 — a bare <string> option, `image-set("<url>" 1x)`,
        // is equivalent to `url("<url>")`. The selected StringValue must paint
        // the raster image (a `Do` XObject); previously it was dropped by the
        // background-layer instanceof gate and the box rendered blank.
        $png = 'data:image/png;base64,' . base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px;
                   background-image: image-set("' . $png . '" 1x); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('Do', $opcodes, 'image-set bare string paints an image XObject');
    }

    public function testLegacyClipRectWhitespaceFormEmitsClip(): void
    {
        // CSS 2.1 §11.1.2 — the legacy `clip` property accepts a rect() with
        // WHITESPACE-separated edges: `clip: rect(50px 150px 150px 50px)`. That
        // form parses to a RectShape (vs the comma form's CssFunction) and must
        // still clip the absolutely-positioned box — a `W` clip + `re` clip
        // rectangle. Previously the RectShape was dropped (box left unclipped).
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { position: absolute; width: 100px; height: 100px;
                   background-color: green;
                   clip: rect(50px 150px 150px 50px); }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('W', $opcodes, 'legacy whitespace clip rect emits a clip (W)');
        self::assertContains('re', $opcodes, 'legacy whitespace clip rect emits the clip rectangle');
    }

    public function testBackgroundPositionTwoKeywordAxisOrderIsCommutative(): void
    {
        // CSS Backgrounds 3 §3.6 — two-keyword `background-position` binds by
        // axis identity, not order: `top center` (vertical-first) must place
        // the image identically to `center top` (horizontal-first). Guards the
        // positional mis-assignment that put `top center` at the top-LEFT.
        $png = 'data:image/png;base64,' . base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $render = function (string $pos) use ($png): array {
            $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
            $sheet = $this->css->parseStylesheet(
                'html, body, div { display: block; }
                 div { width: 200px; height: 200px; background-repeat: no-repeat;
                       background-image: url("' . $png . '");
                       background-position: ' . $pos . '; }',
                Origin::UserAgent,
            );
            $root = $this->generator->generate($doc, [$sheet]);
            self::assertNotNull($root);
            $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
            $writer = new PdfWriter(compressStreams: false);
            $page = $writer->addPage(612, 792);
            $stream = $writer->addContentStream($page);
            (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);
            return $stream->getOperators();
        };
        self::assertSame(
            $render('center top'),
            $render('top center'),
            '`top center` places the image identically to `center top`',
        );
        // And the swap does not disturb the canonical horizontal-first order.
        self::assertSame(
            $render('left bottom'),
            $render('bottom left'),
            '`bottom left` places the image identically to `left bottom`',
        );
    }

    public function testTransparentInlineBackgroundEmitsNoFillRect(): void
    {
        // The `background-color` initial is `transparent` (rgba(0,0,0,0)),
        // which every inline box computes and propagates to its text
        // fragments — so a nested inline used to fill an opaque BLACK rect
        // over its own glyphs. Reproduces the letter-spacing-nesting bug
        // (needs a real font so the run yields sized fragments); with the
        // alpha guard the transparent fragments paint no rectangle at all.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();
        $g = "\u{1820}";
        $doc = $this->html->parseDocument(
            '<html><body><div>' . $g
            . '<span style="letter-spacing: 10px">' . $g . $g . '</span>'
            . $g . '</div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } div { font-size: 30px; }',
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
        self::assertNotContains('re', $opcodes, 'transparent inline background paints no rectangle');
    }

    public function testOpaqueInlineBackgroundStillFillsRect(): void
    {
        // Positive control for the transparent guard: an inline whose
        // background-color is OPAQUE still fills its rect in its colour.
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();
        $g = "\u{1820}";
        $doc = $this->html->parseDocument(
            '<html><body><div>' . $g
            . '<span style="background-color: red">' . $g . $g . '</span>'
            . $g . '</div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } div { font-size: 30px; }',
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
        self::assertContains('re', $opcodes, 'opaque inline background fills its rect');
        self::assertContains(
            '1 0 0 rg',
            array_map('rtrim', $stream->getOperators()),
            'opaque inline background fills in its own colour (red)',
        );
    }

    public function testGradientStopsPadEndsToSpanFullRange(): void
    {
        // CSS Images 3 §3.5.1 — before the first stop the gradient is the
        // first colour and after the last stop it is the last colour. A
        // gradient whose stops don't reach 0/1 (here a last stop clamped to
        // 70%) must get synthetic flat end stops so the PDF stitching
        // function holds a solid band instead of stretching the final
        // segment. `linear-gradient(yellow, blue 70%, green 0)` → green's 0
        // clamps up to 70%, and a green stop is anchored at 100%.
        $yellow = new \Phpdftk\Css\Value\Color(1.0, 1.0, 0.0, 1.0);
        $blue = new \Phpdftk\Css\Value\Color(0.0, 0.0, 1.0, 1.0);
        $green = new \Phpdftk\Css\Value\Color(0.0, 0.5, 0.0, 1.0);
        $stops = [
            new \Phpdftk\Css\Value\GradientStop($yellow, null),
            new \Phpdftk\Css\Value\GradientStop($blue, new \Phpdftk\Css\Value\Percentage(70.0)),
            new \Phpdftk\Css\Value\GradientStop($green, new \Phpdftk\Css\Value\Length(0.0, \Phpdftk\Css\Value\LengthUnit::Px)),
        ];
        $method = new \ReflectionMethod(Painter::class, 'resolveGradientStops');
        /** @var list<array{offset: float, rgb: array{float, float, float}, alpha: float}> $out */
        $out = $method->invoke(new Painter(792.0), $stops, 200.0);

        // yellow@0, blue@0.7, green@0.7 (clamped up), green@1.0 (padded).
        self::assertCount(4, $out);
        self::assertEqualsWithDelta(0.0, $out[0]['offset'], 0.001);
        self::assertEqualsWithDelta(0.7, $out[1]['offset'], 0.001);
        self::assertEqualsWithDelta(0.7, $out[2]['offset'], 0.001);
        self::assertEqualsWithDelta(1.0, $out[3]['offset'], 0.001);
        // The padded stop holds the last colour (green) flat.
        self::assertSame($out[2]['rgb'], $out[3]['rgb']);
        self::assertEqualsWithDelta(0.5, $out[3]['rgb'][1], 0.001);
    }

    public function testFullSpanGradientStopsAreNotPadded(): void
    {
        // A gradient already spanning [0, 1] (`red, blue`) keeps exactly its
        // two stops — the padding must be a no-op for the common case.
        $stops = [
            new \Phpdftk\Css\Value\GradientStop(new \Phpdftk\Css\Value\Color(1.0, 0.0, 0.0, 1.0), null),
            new \Phpdftk\Css\Value\GradientStop(new \Phpdftk\Css\Value\Color(0.0, 0.0, 1.0, 1.0), null),
        ];
        $method = new \ReflectionMethod(Painter::class, 'resolveGradientStops');
        $out = $method->invoke(new Painter(792.0), $stops, 200.0);
        self::assertCount(2, $out);
        self::assertEqualsWithDelta(0.0, $out[0]['offset'], 0.001);
        self::assertEqualsWithDelta(1.0, $out[1]['offset'], 0.001);
    }

    public function testCurrentColorResolvesToBoxColorOnBackground(): void
    {
        // CSS Color 4 §3.6 — `currentcolor` on `background-color`
        // resolves to the element's own `color` property. A box
        // styled `color: blue; background-color: currentcolor` paints
        // its background blue. Without this resolution the cascade
        // keeps the `currentcolor` Keyword unchanged and the painter
        // treats it as "no colour", emitting no fill.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { color: blue; background-color: currentcolor;
                   width: 100px; height: 100px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // Blue in PDF DeviceRGB is `0 0 1 rg`.
        self::assertStringContainsString('0 0 1 rg', $bytes, 'currentcolor resolves to blue fill');
    }

    public function testBackgroundSizeAutoDerivesFromIntrinsicRatio(): void
    {
        // CSS Backgrounds 3 §3.9 — for `background-size: auto <length>`
        // (or `<length> auto`) and an image with an intrinsic ratio,
        // `auto` derives from the explicit side via the ratio. Here
        // we use a 40×10 SVG (ratio 4:1) and `background-size: auto
        // 20px` — the painter should compute width = 20 * 4 = 80px,
        // not stretch to the full box width.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="10">'
            . '<rect width="100%" height="100%" fill="lime"/></svg>';
        $dataUri = 'data:image/svg+xml,' . rawurlencode($svg);

        // Inspect the resolved tile size directly via the private
        // helper: bg-size: auto 20px on a 40×10 SVG (ratio 4:1) →
        // width = 20 × (40/10) = 80; height = 20. With the old buggy
        // behaviour, width would fall back to the 400px box width.
        $reflectMethod = new \ReflectionMethod(Painter::class, 'resolveAutoSizePair');
        $painter = new Painter(792.0);
        $result = $reflectMethod->invoke($painter, null, 20.0, $dataUri, 400.0, 200.0);
        self::assertIsArray($result);
        self::assertEqualsWithDelta(80.0, $result[0], 0.001, 'width derived from intrinsic ratio (40/10) × 20');
        self::assertEqualsWithDelta(20.0, $result[1], 0.001, 'height = explicit 20px');
    }

    public function testBodyBackgroundPropagatesToCanvasWhenRootTransparent(): void
    {
        // CSS Backgrounds 3 §3.11.2 — for HTML documents, when the root
        // element has a transparent background, the *body*'s background
        // propagates to the canvas, and the body itself paints
        // transparently. Concretely: a small 100×100 body with
        // background-color: yellow should produce a single full-page
        // (612×792) yellow rect — not a 100×100 rect at body's geometry.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             body { background-color: yellow; width: 100px; height: 100px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $ops = $stream->getOperators();
        $rects = array_values(array_filter(
            $ops,
            static fn($op) => str_ends_with(rtrim($op), ' re'),
        ));
        // Exactly one rect (the propagated canvas paint) — not also a
        // body-sized rect.
        self::assertCount(1, $rects, 'one canvas-sized rect, not a body-sized double-paint');
        // Yellow in PDF DeviceRGB: 1 1 0 rg.
        $bytes = (string) array_reduce(
            $ops,
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        self::assertStringContainsString('1 1 0 rg', $bytes, 'yellow fill emitted');
        // Rect covers the whole page (Painter's pageWidth × pageHeight),
        // not the body box (100 × 100). pageWidth defaults to 100000 when
        // not passed; pageHeight is 792 here.
        self::assertStringContainsString(' 792 re', $rects[0], 'canvas rect uses pageHeight not bodyHeight');
        self::assertStringNotContainsString('100 100 re', $rects[0], 'not body-sized');
    }

    public function testPropagatedRootBackgroundAnchorsToElementPaddingBox(): void
    {
        // CSS 2.1 §14.2 — a propagated root background PAINTS over the
        // whole canvas but is POSITIONED / tiled as if painted for the
        // element's own box (its padding box, the default
        // background-origin). So a `repeat-x` image on an `html` with a
        // margin tiles from the margin offset, not the page corner. Lock
        // the positioning-origin computation.
        $doc = $this->html->parseDocument('<html><body></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             html { margin: 40px; padding: 10px; background-color: green; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );
        $html = $this->findByTag($root, 'html');
        self::assertNotNull($html);
        $rect = (new \ReflectionMethod(Painter::class, 'propagatedOriginRect'))
            ->invoke(new Painter(792.0), $html);
        // Padding-box top-left sits at the 40px margin (border 0); the box
        // spans its padding + content.
        self::assertEqualsWithDelta(40.0, $rect['x'], 0.5);
        self::assertEqualsWithDelta(40.0, $rect['top'], 0.5);
        self::assertGreaterThan(0.0, $rect['width']);
    }

    private function findByTag(
        \Phpdftk\HtmlToPdf\Box\Box $root,
        string $tag,
    ): ?\Phpdftk\HtmlToPdf\Box\Box {
        $stack = [$root];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node->element !== null && strtolower($node->element->localName) === $tag) {
                return $node;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        return null;
    }

    public function testBodyOverflowPropagatesAndSuppressesBodyClip(): void
    {
        // CSS Overflow 3 §3.3 — when the root's overflow is `visible`
        // (default) and the body has a non-visible overflow, the body's
        // overflow propagates to the canvas and the body itself paints
        // as if `overflow: visible`. Observable effect in op-stream:
        // no clip rect at the body's geometry (the body's own
        // descendant clip is suppressed); the test/ref divergence in
        // `overflow-body-propagation-007` etc. is closed because the
        // canvas-level clip already lives on the page boundary.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             body { overflow-x: clip; width: 30px; height: 30px;
                    margin: 100px; padding: 10px; }
             div { width: 200px; height: 200px; background: blue; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $ops = implode("\n", $stream->getOperators());
        // The body's geometry padding-box is at (100, 100, 50, 50).
        // Without propagation we would emit `100 ... 50 ... re` as a
        // clip rect at the body. With propagation that clip should
        // be absent — the body paints as if overflow:visible.
        self::assertStringNotContainsString("100 100 50 50 re", $ops);
    }

    public function testRootOverflowSuppressesPropagationFromBody(): void
    {
        // CSS Overflow 3 §3.3 — propagation only happens when the root
        // is `visible`. When the root has its own non-visible overflow,
        // the body's overflow stays on the body (no propagation), so
        // the body's clip rect IS emitted at body's geometry.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             html { overflow-x: hidden; }
             body { overflow-x: clip; width: 30px; height: 30px;
                    margin: 100px; padding: 10px; }
             div { width: 200px; height: 200px; background: blue; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $ops = implode("\n", $stream->getOperators());
        // With root overflow non-visible, body's clip still applies
        // (no propagation). Body's padding-box clip should show up.
        self::assertMatchesRegularExpression(
            '/100 (?:[\d.]+) 50 (?:[\d.]+) re/',
            $ops,
            'body clip rect at body geometry when root is not visible',
        );
    }

    public function testContainPaintOnBodySuppressesPropagation(): void
    {
        // CSS Containment 3 §4.4 + CSS Backgrounds 3 §3.11.2 — a
        // paint-contained body forms a paint boundary and its background
        // does NOT propagate to the canvas. With root transparent and
        // body { background: green; contain: paint }, the canvas paints
        // nothing; the body's bg paints at its own geometry.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             body { background-color: green; contain: paint;
                    width: 100px; height: 100px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $ops = $stream->getOperators();
        $rects = array_values(array_filter(
            $ops,
            static fn($op) => str_ends_with(rtrim($op), ' re'),
        ));
        // The body → canvas background propagation is suppressed by
        // contain: paint, so NO rect is canvas-sized (full-page). Assert
        // on the absence of a full-page rect rather than an exact count:
        // paint containment also clips the body's descendants to its
        // padding box (CSS Contain §2.3), which legitimately emits a
        // body-sized clip rect that is NOT canvas-sized.
        self::assertNotEmpty($rects);
        foreach ($rects as $rect) {
            self::assertStringNotContainsString(' 792 re', $rect, 'no canvas-sized propagation rect');
        }
    }

    public function testContainLayoutOnHtmlSuppressesBodyPropagation(): void
    {
        // CSS Containment 3 §4.1 point 5 — layout containment on the
        // root element blocks `background` (and `overflow`) from
        // propagating from the body to the canvas. With root
        // contain:layout and body background red, the body's
        // background paints at the body's geometry (NOT the canvas)
        // so the canvas stays transparent.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             html { contain: layout; }
             body { background-color: red; width: 100px; height: 100px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $ops = $stream->getOperators();
        $rects = array_values(array_filter(
            $ops,
            static fn($op) => str_ends_with(rtrim($op), ' re'),
        ));
        // Exactly one rect (body's own bg). No canvas-sized propagated rect.
        self::assertCount(1, $rects);
        self::assertStringNotContainsString(' 792 re', $rects[0]);
    }

    public function testContainSizeOnHtmlSuppressesBodyPropagation(): void
    {
        // CSS Containment 3 — size containment on the root also
        // blocks ancestor propagation (the WPT cluster
        // `contain-html-bg-003 / 004` and `contain-body-bg-003 / 004`
        // assert this). The mechanic mirrors layout containment:
        // body's bg paints at body's geometry only.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             html { contain: size; }
             body { background-color: red; width: 100px; height: 100px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $ops = $stream->getOperators();
        $rects = array_values(array_filter(
            $ops,
            static fn($op) => str_ends_with(rtrim($op), ' re'),
        ));
        self::assertCount(1, $rects);
        self::assertStringNotContainsString(' 792 re', $rects[0]);
    }

    public function testRootBackgroundWinsOverBodyBackgroundOnCanvas(): void
    {
        // When the root has its own background, the body's background
        // does NOT propagate to the canvas — root wins (CSS Backgrounds 3
        // §3.11.2 first paragraph). The body's own bg still paints at its
        // geometry, so we should see two rect/fill pairs: one for the
        // canvas (root) and one for the body box.
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             html { background-color: red; }
             body { background-color: yellow; width: 100px; height: 100px; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout(
            $root,
            new LayoutContext(600, 800, 0, 0, new LengthContext()),
        );

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0))->paint($root, $stream);

        $bytes = (string) array_reduce(
            $stream->getOperators(),
            static fn($acc, $op) => $acc . $op . "\n",
            '',
        );
        // Red — the propagated canvas paint (root wins).
        self::assertStringContainsString('1 0 0 rg', $bytes, 'red root paint on canvas');
        // Yellow — the body's own paint at its own geometry.
        self::assertStringContainsString('1 1 0 rg', $bytes, 'body bg still paints at body geometry');
    }

    /**
     * CSS Color 4 — `color: transparent` (alpha 0) text must paint no
     * marks. `setFillColorRGB` drops alpha, so a transparent colour
     * would otherwise reach the glyph fill as opaque black (rgb 0,0,0)
     * and render solid. The painter switches to PDF text rendering mode
     * 3 (invisible) instead, leaving the glyphs emitted (extractable /
     * tagged) but inkless.
     */
    public function testTransparentTextUsesInvisibleRenderingMode(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><p style="color: transparent">' . "\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, p { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [], $page);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, $registered))->paint($root, $stream);

        $ops = $stream->getOperators();
        $bytes = (string) array_reduce($ops, static fn($a, $o) => $a . $o . "\n", '');
        // Invisible text-rendering mode is set.
        self::assertStringContainsString('3 Tr', $bytes, 'transparent text uses Tr mode 3 (invisible)');
        // The glyph run is STILL emitted (Tj) — invisible, not skipped —
        // so the text stays extractable for tagged-PDF / selection.
        self::assertContains('Tj', $this->operatorTokens($ops), 'glyphs still emitted under invisible mode');
    }

    /**
     * Regression guard for the transparent-text suppression: opaque text
     * must NOT use the invisible rendering mode — it paints normally
     * (fill mode 0, with its fill colour emitted).
     */
    public function testOpaqueTextDoesNotUseInvisibleRenderingMode(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $otd = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();

        $doc = $this->html->parseDocument(
            '<html><body><p style="color: black">' . "\u{1820}" . '</p></body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, p { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: $otd);
        $this->layout->layout($root, $ctx);
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $registered = $writer->addOpenTypeFont($otd, [], $page);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, $registered))->paint($root, $stream);

        $bytes = (string) array_reduce($stream->getOperators(), static fn($a, $o) => $a . $o . "\n", '');
        self::assertStringNotContainsString('3 Tr', $bytes, 'opaque text must not be invisible');
        self::assertContains('Tj', $this->operatorTokens($stream->getOperators()), 'opaque text emits glyphs');
    }

    /**
     * CSS 2.1 §11.1.2 — `clip: rect(...)` on an absolutely-positioned box
     * emits a PDF clip path (`W`) around its paint.
     */
    public function testAbsposClipRectEmitsClipPath(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><div style="position: absolute; width: 100px; height: 100px; '
            . 'background: green; clip: rect(0, 50px, 50px, 0)"></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, div { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $writer->addPage(612, 792);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);
        self::assertContains('W', $this->operatorTokens($stream->getOperators()), 'abspos clip emits clip path');
    }

    /**
     * CSS Masking 1 §6 — `clip-path: <basic-shape>` emits a clip path.
     * Polygon uses straight segments; circle/ellipse/inset are covered by
     * the rendered-output WPT suite.
     */
    public function testClipPathPolygonEmitsClipPath(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><div style="width: 100px; height: 100px; background: green; '
            . 'clip-path: polygon(0 0, 100px 0, 50px 100px)"></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, div { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);
        $ops = $this->operatorTokens($stream->getOperators());
        self::assertContains('W', $ops, 'clip-path emits a clip path');
        self::assertContains('l', $ops, 'polygon emits line segments');
    }

    /**
     * CSS Masking 1 §6.1 — `clip-path: url(#id)` clips the box to the
     * referenced `<clipPath>`'s geometry, resolved in the element's own
     * user space (origin = border-box top-left, y down).
     */
    public function testClipPathUrlReferenceEmitsClipFromClipPathElement(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body>'
            . '<div style="width: 100px; height: 100px; background: green; '
            . 'border: 10px solid red; clip-path: url(#c)"></div>'
            . '<svg><clipPath id="c">'
            . '<rect x="10" y="10" width="100" height="100"/>'
            . '</clipPath></svg>'
            . '</body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, div { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);
        $ops = $stream->getOperators();
        self::assertContains('W', $this->operatorTokens($ops), 'url() clip-path emits a clip path');
        // The rect is authored at (10, 10) 100x100 in the element's user
        // space; the border box starts at the body origin, so the clip
        // rectangle reaches the stream verbatim under a y-flip `cm`.
        $rects = array_values(array_filter(
            array_map('trim', $ops),
            static fn(string $op): bool => str_ends_with($op, ' re')
                && str_starts_with($op, '10 10 100 100'),
        ));
        self::assertNotEmpty($rects, 'clipPath child rect emitted in the element user space');
    }

    /**
     * CSS Masking 1 §6 / CSS Backgrounds 3 §3.11.2 — the background the
     * root element propagates to the canvas is painted inside the root's
     * group, so a `clip-path` on the root clips it too. Without this the
     * root's own content was clipped while the propagated background
     * flooded the page.
     */
    public function testRootClipPathAlsoClipsThePropagatedCanvasBackground(): void
    {
        $doc = $this->html->parseDocument(
            '<html style="background: red; height: 200px; '
            . 'clip-path: polygon(0px 0px, 50px 0px, 50px 50px, 0px 50px)">'
            . '<body></body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0, pageWidth: 612.0))->paint($root, $stream);
        $ops = array_map('trim', $stream->getOperators());
        // The full-page canvas fill must sit INSIDE a clip: a `W` has to
        // precede the `1 0 0 rg` that paints the propagated red.
        $fill = array_search('1 0 0 rg', $ops, true);
        self::assertIsInt($fill, 'canvas background painted');
        self::assertContains(
            'W',
            array_slice($ops, 0, $fill),
            'root clip-path pushed before the canvas fill',
        );
        // …and it is the polygon, not a box rectangle.
        self::assertContains('50 792 l', array_slice($ops, 0, $fill));
    }

    /**
     * Negative: a root with no `clip-path` must not push a clip around
     * the canvas background — the page fill stays unclipped.
     */
    public function testRootWithoutClipPathLeavesTheCanvasBackgroundUnclipped(): void
    {
        $doc = $this->html->parseDocument(
            '<html style="background: red"><body></body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0, pageWidth: 612.0))->paint($root, $stream);
        self::assertNotContains('W', $this->operatorTokens($stream->getOperators()));
    }

    /**
     * Negative: a `clip-path: url(#id)` whose target does not exist (or is
     * not a `<clipPath>`) applies NO clip — the element paints whole
     * rather than disappearing.
     */
    public function testClipPathUrlReferenceToMissingTargetEmitsNoClip(): void
    {
        foreach (['url(#nope)', 'url(#notaclip)'] as $value) {
            $doc = $this->html->parseDocument(
                '<html><body>'
                . '<div style="width: 100px; height: 100px; background: green; '
                . 'clip-path: ' . $value . '"></div>'
                . '<svg><rect id="notaclip" width="10" height="10"/></svg>'
                . '</body></html>',
            );
            $sheet = $this->css->parseStylesheet('html, body, div { display: block; }', Origin::UserAgent);
            $root = $this->generator->generate($doc, [$sheet]);
            $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
            $writer = new PdfWriter(compressStreams: false);
            $stream = $writer->addContentStream($writer->addPage(612, 792));
            (new Painter(792.0))->paint($root, $stream);
            self::assertNotContains(
                'W',
                $this->operatorTokens($stream->getOperators()),
                "$value must not clip",
            );
        }
    }

    /**
     * CSS Masking 1 §6.1 — `clipPathUnits="objectBoundingBox"` measures
     * the children against the referencing element's bounding box, so a
     * `width="0.5"` rect clips to its left half. ISO 32000-2 §8.2 also
     * requires the bbox `cm` to be undone AFTER `W`/`n`: a `cm` between
     * the path and its painting operator makes the stream malformed.
     */
    public function testClipPathUrlObjectBoundingBoxUnitsScaleToTheBorderBox(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body>'
            . '<div style="width: 200px; height: 50px; background: green; '
            . 'clip-path: url(#c)"></div>'
            . '<svg><clipPath id="c" clipPathUnits="objectBoundingBox">'
            . '<rect width="0.5" height="1"/>'
            . '</clipPath></svg>'
            . '</body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, div { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);
        $ops = array_map('trim', $stream->getOperators());
        self::assertContains('200 0 0 50 0 0 cm', $ops, 'bbox matrix reifies the [0,1] coords');
        // The path object must run `re` -> `W` -> `n` with nothing between.
        $reIndex = array_search('0 0 0.5 1 re', $ops, true);
        self::assertIsInt($reIndex, 'clipPath child rect emitted in bbox units');
        self::assertSame('W', $ops[$reIndex + 1] ?? null, 'clip operator follows the path directly');
        self::assertSame('n', $ops[$reIndex + 2] ?? null, 'path object ends with `n`');
    }

    /**
     * `clip-path: none` (the initial value) emits no clip path.
     */
    public function testClipPathNoneEmitsNoClip(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><div style="width: 100px; height: 100px; background: green"></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, div { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);
        self::assertNotContains('W', $this->operatorTokens($stream->getOperators()), 'no clip-path → no clip');
    }

    /**
     * Negative: `clip` only applies to absolutely-positioned boxes — a
     * static box with `clip: rect(...)` must NOT emit a clip path.
     */
    public function testClipIgnoredOnStaticBox(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><div style="width: 100px; height: 100px; background: green; '
            . 'clip: rect(0, 50px, 50px, 0)"></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet('html, body, div { display: block; }', Origin::UserAgent);
        $root = $this->generator->generate($doc, [$sheet]);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));
        $writer = new PdfWriter(compressStreams: false);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);
        self::assertNotContains('W', $this->operatorTokens($stream->getOperators()), 'static clip is a no-op');
    }

    /**
     * A replaced `<img>` blockified as a FLEX item must paint its image
     * XObject (`Do`) — its main/cross size is resolved by the normal
     * block-flow measure, so its geometry is correct. The same `<img>` as a
     * GRID item stays gated out (grid-track geometry is not wired for direct
     * raster placement — painting it regressed css-grid).
     */
    public function testImgFlexItemPaintsImageButGridItemDoesNot(): void
    {
        // Minimal 1×1 PNG.
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1'
            . 'HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        foreach (['flex' => true, 'grid' => false] as $display => $expectPaint) {
            $doc = $this->html->parseDocument(
                '<html><body><div class="c"><img src="' . $png . '"></div></body></html>',
            );
            $sheet = $this->css->parseStylesheet(
                'html, body { display: block; }
                 .c { display: ' . $display . '; }
                 img { width: 50px; height: 50px; }',
                Origin::UserAgent,
            );
            $root = $this->generator->generate($doc, [$sheet]);
            self::assertNotNull($root);
            $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

            $writer = new PdfWriter(compressStreams: false);
            $page = $writer->addPage(612, 792);
            $stream = $writer->addContentStream($page);
            (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

            $opcodes = $this->operatorTokens($stream->getOperators());
            if ($expectPaint) {
                self::assertContains('Do', $opcodes, "$display item <img> paints an image XObject");
            } else {
                self::assertNotContains('Do', $opcodes, "$display item <img> stays gated out");
            }
        }
    }

    /**
     * `border-image-slice: 0 fill` paints the middle image region into the
     * content box (CSS Backgrounds 3 §6.2). With a zero slice every edge is
     * empty, so the only image draw (`Do`) is the middle fill — proving the
     * fill path runs instead of bailing on all-zero slices.
     */
    public function testBorderImageSliceFillPaintsMiddleImage(): void
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1'
            . 'HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $doc = $this->html->parseDocument('<html><body><div class="b"></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             .b { width: 50px; height: 50px; border: 10px solid red;
                  border-image-source: url(' . $png . ');
                  border-image-slice: 0 fill; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Painter(792.0, page: $page, writer: $writer))->paint($root, $stream);

        self::assertContains(
            'Do',
            $this->operatorTokens($stream->getOperators()),
            'border-image-slice fill paints the middle image XObject',
        );
    }

    /**
     * A `<percentage>` border-radius on a non-square box yields ELLIPTICAL
     * corners (CSS Backgrounds 3 §5.1): `border-radius: 50%` on 100×60 is
     * rx=50, ry=30. The background fill must emit Bézier corner curves (`c`),
     * not a sharp rectangle — the old scalar path dropped `<percentage>` /
     * two-value radii and rendered a rectangle.
     */
    public function testPercentageBorderRadiusEmitsEllipticalCurves(): void
    {
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 60px; background: green; border-radius: 50%; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('c', $opcodes, 'percentage border-radius emits Bézier corner curves');
    }

    /**
     * An `overflow: hidden` (or `contain: paint`) box with border-radius
     * clips its descendants to the padding box's ROUNDED corners (CSS
     * Backgrounds 3 §5.3), so the overflow clip path emits Bézier curves
     * (`c`), not a plain rectangle.
     */
    public function testRoundedOverflowClipEmitsCurves(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><div class="clip"><div class="child"></div></div></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             .clip { width: 100px; height: 100px; overflow: hidden; border-radius: 30px; }
             .child { width: 200px; height: 200px; background: green; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('W', $opcodes, 'overflow:hidden emits a clip');
        self::assertContains('c', $opcodes, 'rounded overflow clip emits Bézier corner curves');
    }

    /**
     * `transform-origin` position keywords bind by AXIS IDENTITY, not order
     * (CSS Transforms 1 §6): `top`/`bottom` set Y and `left`/`right` set X in
     * any order, and a single keyword leaves the other axis at center.
     */
    public function testTransformOriginKeywordAxisBinding(): void
    {
        $painter = new Painter(792.0);
        $method = new \ReflectionMethod(Painter::class, 'resolveTransformOriginOffsets');
        $method->setAccessible(true);
        $kw = static fn(string $n): \Phpdftk\Css\Value\Keyword => new \Phpdftk\Css\Value\Keyword($n);
        $call = static fn(array $vals): array => $method->invoke($painter, $vals, 100.0, 100.0);

        // `top` alone → X = center (50), Y = top (0).
        self::assertEqualsWithDelta([50.0, 0.0], $call([$kw('top')]), 0.1);
        // `top center` → X = center, Y = top (top binds Y regardless of order).
        self::assertEqualsWithDelta([50.0, 0.0], $call([$kw('top'), $kw('center')]), 0.1);
        // `top right` → X = right (100), Y = top (0).
        self::assertEqualsWithDelta([100.0, 0.0], $call([$kw('top'), $kw('right')]), 0.1);
    }

    /**
     * A `solid` outline paints a FILLED BAND (even-odd `f*`), not a centred
     * stroke, so large widths / negative offsets fill correctly (CSS UI 3 §4).
     */
    public function testSolidOutlineEmitsFilledBand(): void
    {
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }
             div { width: 100px; height: 100px; outline: 20px solid green; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);

        $opcodes = $this->operatorTokens($stream->getOperators());
        self::assertContains('f*', $opcodes, 'solid outline emits an even-odd filled band');
    }

    /**
     * A `border-collapse: collapse` cell's border paints AFTER its content
     * (CSS Tables 3 §4.3), so a descendant can't overpaint the collapsed
     * table border. The cell's lime border fill (`0 1 0 rg`) must appear in
     * the stream after the child's red border fill (`1 0 0 rg`).
     */
    public function testCollapsedBorderCellPaintsBorderAfterContent(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><table><tr><td><div></div></td></tr></table></body></html>',
        );
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }
             table { display: table; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; border: 20px solid lime; padding: 0; }
             td > div { display: block; width: 20px; height: 20px; border: 20px solid red; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($root);
        $this->layout->layout($root, new LayoutContext(600, 800, 0, 0, new LengthContext()));

        $writer = new PdfWriter(compressStreams: false);
        $stream = $writer->addContentStream($writer->addPage(612, 792));
        (new Painter(792.0))->paint($root, $stream);

        $ops = implode("\n", $stream->getOperators());
        $greenPos = strpos($ops, '0 1 0 rg'); // cell's lime (collapsed) border
        $redPos = strpos($ops, '1 0 0 rg');   // child div's red border
        self::assertNotFalse($greenPos, 'cell border painted');
        self::assertNotFalse($redPos, 'child border painted');
        self::assertGreaterThan(
            $redPos,
            $greenPos,
            'collapsed cell border paints after the child content',
        );
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
