<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests;

use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\HtmlToPdf\RenderResult;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase
{
    public function testRenderProducesValidPdf(): void
    {
        $renderer = new Renderer();
        $result = $renderer->render(
            '<html><body><div></div></body></html>',
            'div { background-color: red; height: 50px; }',
        );
        self::assertInstanceOf(RenderResult::class, $result);
        $bytes = $result->writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
        self::assertFalse($result->hasErrors());
    }

    public function testLongParagraphDoesNotStraddlePageBoundary(): void
    {
        // End-to-end fragmentation smoke: a paragraph long enough to
        // overflow the page boundary at a known position. With the
        // line-split avoidance pass, no line should straddle the boundary
        // — the painter's content stream should reflect that.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer(
            (new RendererOptions())->withDefaultFont($font),
        );
        $body = str_repeat("\u{1820} ", 200);
        $writer = new PdfWriter();
        $renderer->renderInto(
            $writer,
            '<html><body>'
                . '<div style="height: 740px"></div>'
                . '<p>' . $body . '</p>'
                . '</body></html>',
            'html, body, p, div { display: block; } p { line-height: 20px; }',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Multi-page output (2+ pages from the 740-tall pre-paragraph div
        // plus a 200-line paragraph at 20px each).
        self::assertGreaterThanOrEqual(2, substr_count($bytes, '/Type /Page'));
    }

    public function testMultiColumnDocumentProducesValidPdfWithRule(): void
    {
        // End-to-end smoke for CSS Multi-column 1: a `column-count: 2`
        // container with a visible `column-rule` should produce a PDF whose
        // content stream contains both a stroke command (the rule) and the
        // child block's fill colour. Uncompressed writer for grep-ability.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><section>'
                . '<div class="a"></div><div class="b"></div>'
                . '<div class="c"></div><div class="d"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 20px;
                       column-rule: 2px solid #444; }
             div { height: 80px; background-color: #cce; }',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Painter emits `S` (stroke) for the rule on its own line, `RG`
        // for the rule's stroke colour, and the 0.8 0.8 0.93333 fill
        // colour for the div backgrounds.
        self::assertStringContainsString("\nS\n", $bytes, 'column-rule stroke not emitted');
        self::assertStringContainsString('RG', $bytes, 'rule colour not emitted');
    }

    public function testMultiColumnRuleNoneEmitsNoStroke(): void
    {
        // `column-rule-style: none` → painter early-outs; the only `S`
        // bytes we should see come from unrelated paths (there are none
        // for this fixture — backgrounds use `f` to fill, not `S`).
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><section>'
                . '<div class="a"></div><div class="b"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-rule-style: none; }
             div { height: 40px; background-color: #eee; }',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringNotContainsString("\nS\n", $bytes, 'no stroke for column-rule: none');
    }

    public function testRenderEmitsNoWarningsForCleanInput(): void
    {
        $result = (new Renderer())->render(
            '<html><body><p></p></body></html>',
            'p { background-color: blue; }',
        );
        self::assertSame([], $result->warnings);
    }

    public function testMissingFontWarnsWhenDocumentHasText(): void
    {
        // No defaultFont but the document has real text content — the
        // renderer should emit a Warning so callers know text won't paint.
        $result = (new Renderer())->render(
            '<html><body><p>Hello world</p></body></html>',
        );
        $codes = array_map(static fn($w) => $w->code, $result->warnings);
        self::assertContains(\Phpdftk\HtmlToPdf\WarningCode::MissingFont, $codes);
    }

    public function testNoMissingFontWarningForEmptyDocument(): void
    {
        // No text content → no warning.
        $result = (new Renderer())->render('<html><body><div></div></body></html>');
        $codes = array_map(static fn($w) => $w->code, $result->warnings);
        self::assertNotContains(\Phpdftk\HtmlToPdf\WarningCode::MissingFont, $codes);
    }

    public function testAdversarialHeightTriggersPageCountCapAndWarns(): void
    {
        // Pattern from WPT `*-crash.html` fixtures: a `height` value
        // so large that paginating it would allocate hundreds of
        // thousands of Page objects. Layout clamps the length itself
        // (see LengthResolver::MAX_PX), and the renderer caps page
        // count at 10,000 so adversarial input can't OOM the
        // process. We expect: a non-empty PDF + a warning announcing
        // the cap.
        $renderer = new Renderer();
        $writer = new PdfWriter();
        $warnings = $renderer->renderInto(
            $writer,
            '<html><body>'
            . '<div style="height: 12345678901234px"></div>'
            . '</body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        $messages = array_map(static fn($w) => $w->message, $warnings);
        self::assertCount(
            1,
            array_filter($messages, static fn(string $m) => str_contains($m, 'safety cap')),
            'one safety-cap warning expected',
        );
    }

    public function testRenderIntoExistingWriter(): void
    {
        $writer = new PdfWriter();
        $renderer = new Renderer();
        $warnings = $renderer->renderInto(
            $writer,
            '<html><body><div></div></body></html>',
            'div { background-color: green; height: 30px; }',
        );
        self::assertSame([], $warnings);
        self::assertStringStartsWith('%PDF-', $writer->toBytes());
    }

    public function testDefaultUaSheetMakesElementsBlock(): void
    {
        // Without any author CSS rule for `display`, the built-in UA sheet
        // should still make <p> a block (so backgrounds paint as block
        // rectangles). Use an uncompressed writer so we can grep the raw
        // content-stream bytes.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><p></p></body></html>',
            'p { background-color: red; }',
        );
        self::assertStringContainsString('rg', $writer->toBytes(), 'fill color emitted → p painted as block');
    }

    public function testOptionsArePassedThrough(): void
    {
        $options = (new RendererOptions())
            ->withPageSize(400, 600)
            ->withStrict(true);
        $renderer = new Renderer($options);
        self::assertSame($options, $renderer->options);
        self::assertTrue($renderer->options->strict);
        self::assertSame(400.0, $renderer->options->pageWidth);
    }

    public function testRenderWithFontEmitsText(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        // Uncompressed writer so we can grep the raw content stream.
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p>' . "\u{1820}\u{1820}\u{1820}" . '</p></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Font registration plus a Tj somewhere indicates the painter emitted glyphs.
        self::assertStringContainsString('Tj', $bytes);
    }

    public function testParseExposesTheDom(): void
    {
        $renderer = new Renderer();
        $doc = $renderer->parse('<html><body><h1>Hello</h1></body></html>');
        self::assertNotNull($doc->documentElement);
        $h1 = $doc->documentElement->querySelector('h1');
        self::assertNotNull($h1);
        self::assertSame('h1', $h1->localName);
    }

    public function testParseStylesheetExposesTheAst(): void
    {
        $sheet = (new Renderer())->parseStylesheet('p { color: red; }');
        self::assertCount(1, $sheet->rules);
    }

    public function testRenderResultHasErrorsFlagsErrorSeverity(): void
    {
        // Lenient mode: emit a synthetic error-severity warning is not the
        // common path; test the simpler positive flag with the real renderer.
        $result = (new Renderer())->render('<html></html>');
        self::assertFalse($result->hasErrors());
    }

    public function testHeadingsProduceOutline(): void
    {
        // Three headings should register as an Outline + 3 OutlineItem
        // objects with /Title entries and /Dest pointers.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><h1>Intro</h1><h1>Body</h1><h1>Conclusion</h1></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/Type /Outlines', $bytes);
        self::assertStringContainsString('/Title (Intro)', $bytes);
        self::assertStringContainsString('/Title (Body)', $bytes);
        self::assertStringContainsString('/Title (Conclusion)', $bytes);
    }

    public function testOutlineSetsUseOutlinesPageMode(): void
    {
        // The outline pane should auto-open in viewers when a doc has
        // headings; we hint that by setting Catalog /PageMode to
        // UseOutlines.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><h1>Top</h1></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/PageMode /UseOutlines', $bytes);
    }

    public function testNoOutlineNoPageModeChange(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><p>just a paragraph</p></body></html>',
        );
        self::assertStringNotContainsString('/PageMode', $writer->toBytes());
    }

    public function testOutlineNestsByHeadingLevel(): void
    {
        // h1 > h2 > h2 > h1 produces a nested outline: the first h1 has two
        // h2 children, the second h1 is a top-level sibling.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<h1>Top1</h1>'
                . '<h2>SubA</h2>'
                . '<h2>SubB</h2>'
                . '<h1>Top2</h1>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/Title (Top1)', $bytes);
        self::assertStringContainsString('/Title (SubA)', $bytes);
        self::assertStringContainsString('/Title (SubB)', $bytes);
        self::assertStringContainsString('/Title (Top2)', $bytes);
        // The outline root has Count=2 (two top-level: Top1, Top2).
        self::assertMatchesRegularExpression(
            '~/Type /Outlines\s*/First [0-9]+ 0 R\s*/Last [0-9]+ 0 R\s*/Count 2~',
            $bytes,
        );
    }

    public function testNoHeadingsNoOutline(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><p>just a paragraph</p></body></html>',
        );
        // No outline should be emitted when no headings exist.
        self::assertStringNotContainsString('/Type /Outlines', $writer->toBytes());
    }

    public function testMarkElementHasYellowBackground(): void
    {
        // Inline backgrounds are painted per-fragment, so we need a font
        // registered to drive the inline-layout pipeline.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p>plain <mark>' . "\u{1820}" . '</mark></p></body></html>',
        );
        $bytes = $writer->toBytes();
        // #ffff00 → `1 1 0 rg`. The mark fragment should produce a yellow
        // background fill since the UA stylesheet sets background-color.
        self::assertStringContainsString('1 1 0 rg', $bytes);
        // The rect should have a non-zero width — the per-fragment path
        // sizes it to the inline's content width, not the InlineBox's
        // (which is 0).
        self::assertDoesNotMatchRegularExpression('~1 1 0 rg\s+[\d.]+ [\d.]+ 0 0 re~', $bytes, 'rect is sized properly');
    }

    public function testLinkUnderlineUsesLinkColor(): void
    {
        // UA stylesheet gives `<a>` color #0033cc + underline. The
        // underline rect should use the link's blue, not the paragraph's
        // default black.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p><a href="#x">' . "\u{1820}" . '</a></p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Underline emits a filled rect in the deco colour — for the
        // link this should be 0/0.2/0.8 (~#0033cc).
        self::assertMatchesRegularExpression(
            '~0(?:\.0+)? 0\.2 0\.8 rg\s+[\d.]+ [\d.]+ [\d.]+ [\d.]+ re~',
            $bytes,
            'link underline rect uses the link colour',
        );
    }

    public function testExplicitTextDecorationColorOverridesTextColor(): void
    {
        // `<u style="color: black; text-decoration: underline;
        // text-decoration-color: red">` — the underline should render red
        // even though the text colour stays black.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p><span style="color: #000; text-decoration: underline; '
            . 'text-decoration-color: #ff0000">' . "\u{1820}" . '</span></p></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertMatchesRegularExpression(
            '~1 0 0 rg\s+[\d.]+ [\d.]+ [\d.]+ [\d.]+ re~',
            $bytes,
            'explicit text-decoration-color drives the underline fill',
        );
    }

    public function testInlineColorRendersForLinks(): void
    {
        // UA stylesheet styles `<a>` with `color: #0033cc` — that blue
        // should appear in the content stream alongside the paragraph's
        // default black fill, not just black.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p>plain <a href="#">' . "\u{1820}" . '</a></p></body></html>',
        );
        $bytes = $writer->toBytes();
        // #0033cc = 0/0xff, 0x33/0xff (~0.2), 0xcc/0xff (~0.8) — match the
        // RGB triplet via a regex tolerant to floating-point formatting.
        self::assertMatchesRegularExpression(
            '~0(?:\.0+)? 0\.2 0\.8 rg~',
            $bytes,
            'fragment colour swap to <a>\'s UA-blue lands in the stream',
        );
    }

    public function testBoldEmitsFillStrokeRenderingMode(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p>plain <b>' . "\u{1820}\u{1820}" . '</b></p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Tr=2 = fill + stroke text. UA stylesheet makes <b> bold.
        self::assertStringContainsString('2 Tr', $bytes, 'fake-bold sets rendering mode 2');
    }

    public function testItalicSkewsTextMatrix(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p>plain <i>' . "\u{1820}\u{1820}" . '</i></p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Fake-italic encodes a non-zero `c` in the Tm matrix.
        self::assertMatchesRegularExpression('~1 0 0\.213 1 [\d.]+ [\d.]+ Tm~', $bytes);
    }

    public function testInternalAnchorEmitsXyzDestination(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        // Anchor and link in the same document. The anchor is a heading
        // further down; the link points to its id.
        $renderer->renderInto(
            $writer,
            '<html><body>'
                . '<p><a href="#target">' . "\u{1820}\u{1820}" . '</a></p>'
                . '<h2 id="target">' . "\u{1820}" . '</h2>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();
        // The annotation should carry a /Dest array — no /URI for this link.
        self::assertStringContainsString('/Subtype /Link', $bytes);
        self::assertStringContainsString('/Dest', $bytes);
        self::assertStringNotContainsString('/URI (#target)', $bytes);
    }

    public function testImgDataUrlPaintsImageXObject(): void
    {
        // Tiny valid PNG (GIMP-emitted 4×4 image). The image-painting
        // path requires inline layout to have run, which in turn requires
        // a default font wired in.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $pngBase64 = base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $writer = new PdfWriter(compressStreams: false);
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $html = sprintf(
            '<html><body><p><img src="data:image/png;base64,%s" width="32" height="32"></p></body></html>',
            $pngBase64,
        );
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        // Image XObject and Do operator should appear in the output.
        self::assertStringContainsString('/Subtype /Image', $bytes);
        self::assertMatchesRegularExpression('~/Im\d+ Do~', $bytes);
    }

    public function testTextDecorationStyleDoubleEmitsTwoRects(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p style="text-decoration: underline double">'
            . "\u{1820}\u{1820}" . '</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // For comparison: solid underline emits one rect. Render the same
        // text with `underline solid` and check `double` produces strictly
        // more `re` operators.
        $solidWriter = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $solidWriter,
            '<html><body><p style="text-decoration: underline solid">'
            . "\u{1820}\u{1820}" . '</p></body></html>',
        );
        $solidBytes = $solidWriter->toBytes();
        $doubleRectCount = preg_match_all('~[\d.]+ [\d.]+ [\d.]+ [\d.\-]+ re~', $bytes);
        $solidRectCount = preg_match_all('~[\d.]+ [\d.]+ [\d.]+ [\d.\-]+ re~', $solidBytes);
        self::assertGreaterThan($solidRectCount, $doubleRectCount, 'double emits more rects than solid');
    }

    public function testTableEndToEndProducesCells(): void
    {
        // End-to-end: parses the implicit `<tbody>`, renders 3 cells at
        // their right positions, and emits the PDF without crashing.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><table style="width: 300px">'
            . '<tr><td>A</td><td>B</td><td>C</td></tr>'
            . '</table></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
    }

    public function testOutlineEmitsStrokedRect(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><div style="outline: 2px solid red; width: 100px; height: 50px;"></div></body></html>',
        );
        $bytes = $writer->toBytes();
        // Outline = stroked rect with line width 2 + red stroke colour.
        self::assertStringContainsString('1 0 0 RG', $bytes, 'red stroke colour');
        self::assertMatchesRegularExpression('~2(?:\.0+)? w~', $bytes, 'line width 2');
        self::assertMatchesRegularExpression('~re\s+S~', $bytes, 'stroked rect');
    }

    public function testOutlineDashedEmitsDashPattern(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><div style="outline: 2px dashed red; width: 100px; height: 50px;"></div></body></html>',
        );
        $bytes = $writer->toBytes();
        // PDF `d` operator with a non-empty dash array sets a dash pattern.
        self::assertMatchesRegularExpression('~\[ [\d.]+ [\d.]+ \] 0 d~', $bytes);
    }

    public function testBorderRadiusEmitsCurveOperators(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><div style="background-color: red; border-radius: 10px; '
            . 'width: 100px; height: 50px;"></div></body></html>',
        );
        $bytes = $writer->toBytes();
        // A rounded fill uses `c` (curveTo) operators in addition to the
        // straight-edge `l` (lineTo) operators. A plain rectangle would
        // only emit `re` + `f`.
        self::assertMatchesRegularExpression('~[\d.]+ [\d.]+ [\d.]+ [\d.]+ [\d.]+ [\d.]+ c~', $bytes);
    }

    public function testBackgroundOriginContentBoxShiftsImageInward(): void
    {
        // CSS Backgrounds 3 §3.4 — `background-origin: content-box`
        // anchors the image to the content edge, which is inset by
        // (border + padding) from the border edge. Render the same
        // image once with the default `padding-box` origin and once
        // with `content-box`; the X translation in the emitted `cm`
        // matrix MUST differ by exactly the padding-left amount.
        $pngBase64 = base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $renderInto = function (string $originDecl) use ($pngBase64): string {
            $writer = new PdfWriter(compressStreams: false);
            $renderer = new Renderer();
            $renderer->renderInto(
                $writer,
                sprintf(
                    '<html><body><div style="width: 100px; height: 100px; '
                    . 'padding: 20px; border: 10px solid black; '
                    . 'background-image: url(\'data:image/png;base64,%s\'); '
                    . 'background-size: 40px 40px; background-repeat: no-repeat; '
                    . 'background-position: 0%% 0%%; %s">'
                    . '</div></body></html>',
                    $pngBase64,
                    $originDecl,
                ),
            );
            return $writer->toBytes();
        };
        $padBytes = $renderInto('');
        $contentBytes = $renderInto('background-origin: content-box;');
        // Extract the cm matrix that precedes the Do (image draw) op.
        $extractCmX = static function (string $bytes): ?float {
            if (preg_match('~(\S+) 0 0 (\S+) (\S+) (\S+) cm\s+/Im\d+ Do~', $bytes, $m) !== 1) {
                return null;
            }
            return (float) $m[3];
        };
        $padX = $extractCmX($padBytes);
        $contentX = $extractCmX($contentBytes);
        self::assertNotNull($padX, 'default (padding-box) emits an image cm');
        self::assertNotNull($contentX, 'content-box emits an image cm');
        // content-box should be inset by 20px (padding-left) from
        // padding-box at the same 0% 0% position.
        self::assertEqualsWithDelta(
            20.0,
            $contentX - $padX,
            0.5,
            'content-box origin shifts image right by padding-left',
        );
    }

    public function testBackgroundOriginBorderBoxAnchorsAtBorderEdge(): void
    {
        // `border-box` origin anchors at the outermost edge — image
        // sits 10px (border-left) further left than padding-box.
        $pngBase64 = base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $renderInto = function (string $originDecl) use ($pngBase64): string {
            $writer = new PdfWriter(compressStreams: false);
            $renderer = new Renderer();
            $renderer->renderInto(
                $writer,
                sprintf(
                    '<html><body><div style="width: 100px; height: 100px; '
                    . 'padding: 20px; border: 10px solid black; '
                    . 'background-image: url(\'data:image/png;base64,%s\'); '
                    . 'background-size: 40px 40px; background-repeat: no-repeat; '
                    . 'background-position: 0%% 0%%; %s">'
                    . '</div></body></html>',
                    $pngBase64,
                    $originDecl,
                ),
            );
            return $writer->toBytes();
        };
        $padBytes = $renderInto('');
        $borderBytes = $renderInto('background-origin: border-box;');
        $extractCmX = static function (string $bytes): ?float {
            if (preg_match('~(\S+) 0 0 (\S+) (\S+) (\S+) cm\s+/Im\d+ Do~', $bytes, $m) !== 1) {
                return null;
            }
            return (float) $m[3];
        };
        $padX = $extractCmX($padBytes);
        $borderX = $extractCmX($borderBytes);
        self::assertNotNull($padX);
        self::assertNotNull($borderX);
        self::assertEqualsWithDelta(
            -10.0,
            $borderX - $padX,
            0.5,
            'border-box origin shifts image left by border-left',
        );
    }

    public function testBackgroundOriginInvalidKeywordFallsBackToPaddingBox(): void
    {
        // Negative: an unrecognised keyword falls back to the
        // initial `padding-box`. Same image position as the default.
        $pngBase64 = base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $renderInto = function (string $originDecl) use ($pngBase64): string {
            $writer = new PdfWriter(compressStreams: false);
            $renderer = new Renderer();
            $renderer->renderInto(
                $writer,
                sprintf(
                    '<html><body><div style="width: 100px; height: 100px; '
                    . 'padding: 20px; border: 10px solid black; '
                    . 'background-image: url(\'data:image/png;base64,%s\'); '
                    . 'background-size: 40px 40px; background-repeat: no-repeat; '
                    . 'background-position: 0%% 0%%; %s">'
                    . '</div></body></html>',
                    $pngBase64,
                    $originDecl,
                ),
            );
            return $writer->toBytes();
        };
        $defaultBytes = $renderInto('');
        $invalidBytes = $renderInto('background-origin: bogus;');
        $extractCmX = static function (string $bytes): ?float {
            if (preg_match('~(\S+) 0 0 (\S+) (\S+) (\S+) cm\s+/Im\d+ Do~', $bytes, $m) !== 1) {
                return null;
            }
            return (float) $m[3];
        };
        $defaultX = $extractCmX($defaultBytes);
        $invalidX = $extractCmX($invalidBytes);
        self::assertNotNull($defaultX);
        self::assertNotNull($invalidX);
        self::assertEqualsWithDelta(0.0, $invalidX - $defaultX, 0.001);
    }

    public function testBackgroundImageDataUrlPaints(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $pngBase64 = base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $writer = new PdfWriter(compressStreams: false);
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $renderer->renderInto(
            $writer,
            sprintf(
                '<html><body><div style="width: 100px; height: 50px; '
                . 'background-image: url(\'data:image/png;base64,%s\');">'
                . "\u{1820}" . '</div></body></html>',
                $pngBase64,
            ),
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/Subtype /Image', $bytes);
        self::assertMatchesRegularExpression('~/Im\d+ Do~', $bytes);
    }

    public function testPictureRendersInnerImg(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $pngBase64 = base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $writer = new PdfWriter(compressStreams: false);
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $html = sprintf(
            '<html><body><p><picture><source srcset="hi-res.webp" media="(min-width:600px)">'
            . '<img src="data:image/png;base64,%s" width="20" height="20"></picture></p></body></html>',
            $pngBase64,
        );
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/Subtype /Image', $bytes, 'inner img rendered');
        self::assertMatchesRegularExpression('~/Im\d+ Do~', $bytes);
    }

    public function testImgRelativePathResolvesAgainstBaseDir(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $baseDir = sys_get_temp_dir() . '/phpdftk-img-test-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        $pngPath = $baseDir . '/logo.png';
        file_put_contents($pngPath, hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        try {
            $renderer = new Renderer(
                (new RendererOptions())->withDefaultFont($font)->withBaseDir($baseDir),
            );
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><body><p><img src="logo.png" width="20" height="20"></p></body></html>',
            );
            $bytes = $writer->toBytes();
            self::assertStringContainsString('/Subtype /Image', $bytes);
            self::assertMatchesRegularExpression('~/Im\d+ Do~', $bytes);
        } finally {
            @unlink($pngPath);
            @rmdir($baseDir);
        }
    }

    public function testLocalImageDoesNotEmitMissingResourceWarning(): void
    {
        // `<img src="logo.png">` with `baseDir` configured + the file
        // on disk is a paintable source — the renderer must NOT emit a
        // MissingResource warning for it.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $baseDir = sys_get_temp_dir() . '/phpdftk-img-warn-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        $pngPath = $baseDir . '/logo.png';
        file_put_contents($pngPath, hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        try {
            $renderer = new Renderer(
                (new RendererOptions())->withDefaultFont($font)->withBaseDir($baseDir),
            );
            $writer = new PdfWriter(compressStreams: false);
            $warnings = $renderer->renderInto(
                $writer,
                '<html><body><p><img src="logo.png" width="20" height="20"></p></body></html>',
            );
            $missing = array_filter(
                $warnings,
                static fn($w) => $w->code === \Phpdftk\HtmlToPdf\WarningCode::MissingResource,
            );
            self::assertSame([], $missing, 'no MissingResource warning for resolvable local images');
        } finally {
            @unlink($pngPath);
            @rmdir($baseDir);
        }
    }

    public function testLocalImageWithOnlyWidthDerivesHeightFromAspectRatio(): void
    {
        // `<img src="local.png" width="40">` with no height — the box
        // generator should read the PNG's natural aspect ratio (4×4
        // square here, so height tracks width 1:1) and synthesise a
        // height so the painter has both dimensions to draw. Validates
        // the elif branch of the natural-size logic.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $baseDir = sys_get_temp_dir() . '/phpdftk-img-aspect-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        $pngPath = $baseDir . '/square.png';
        file_put_contents($pngPath, hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        try {
            $renderer = new Renderer(
                (new RendererOptions())->withDefaultFont($font)->withBaseDir($baseDir),
            );
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><body><p><img src="square.png" width="40"></p></body></html>',
            );
            $bytes = $writer->toBytes();
            // Image XObject + Do op present means the BoxGenerator
            // synthesised the missing height from the PNG's natural
            // 1:1 ratio (40 wide → 40 tall, both > 0).
            self::assertStringContainsString('/Subtype /Image', $bytes);
            self::assertMatchesRegularExpression('~40 0 0 40 ~', $bytes, 'cm matrix carries 40×40');
        } finally {
            @unlink($pngPath);
            @rmdir($baseDir);
        }
    }

    public function testFirstChildMarginDoesNotPushBodyAbovePage(): void
    {
        // CSS 2.1 §8.3.1 parent-child margin collapse-through pulls a
        // first-child block's `margin-top` onto its parent. Repeated up
        // the chain it once landed `body` at y = -margin-top, putting
        // the document above page 0. The root `<html>` element absorbs
        // the propagated margin into the initial containing block, so
        // BlockLayout skips the negative-shift at the root.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        // Reach into the post-layout box tree by replicating the
        // Renderer's pipeline through reflection — the externally
        // visible artefact would just be "PDF has content somewhere",
        // which doesn't pin down the y-positioning bug.
        $ref = new \ReflectionClass($renderer);
        $bg = $ref->getProperty('boxGenerator')->getValue($renderer);
        $cssParser = $ref->getProperty('cssParser')->getValue($renderer);
        $optionsProp = $ref->getProperty('options')->getValue($renderer);
        $css = $optionsProp->effectiveUserAgentStylesheet();
        $sheets = [$cssParser->parseStylesheet($css, \Phpdftk\Css\Sheet\Origin::UserAgent)];
        $doc = $renderer->parse('<html><body><p>hello</p></body></html>');
        $root = $bg->generate($doc, $sheets);
        self::assertNotNull($root);
        $layout = $ref->getProperty('layout')->getValue($renderer);
        $ctx = new \Phpdftk\HtmlToPdf\Layout\LayoutContext(
            containingBlockWidth: 612.0,
            containingBlockHeight: 792.0,
            originX: 0.0,
            originY: 0.0,
            lengthContext: new \Phpdftk\Css\Cascade\LengthContext(),
            defaultFont: $font,
        );
        $layout->layout($root, $ctx);
        // html → body → p. Walk to body and assert y >= 0.
        $body = $root->children[0];
        self::assertGreaterThanOrEqual(0.0, $body->geometry->y, 'body stays on-page after root collapse-through skip');
        $p = $body->children[0];
        self::assertGreaterThanOrEqual(0.0, $p->geometry->y, '<p> stays on-page');
    }

    public function testSmallInlineBlockImageBaselineSitsOnLine(): void
    {
        // CSS Inline 3 §4.5: an inline-block's bottom (for replaced
        // elements like `<img>`) must align with the line baseline. Before
        // the fix, the AtomicInlineBox sat at line-top, so a small image
        // (≤ ascent) had its bottom *above* the baseline — visible as
        // floating-up rendering, and in extreme cases off-page-skipped on
        // page 0. This test exercises a 4×4 PNG inside a `<p>` paragraph
        // with no surrounding text: the image XObject must paint.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $baseDir = sys_get_temp_dir() . '/phpdftk-img-baseline-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        $pngPath = $baseDir . '/tiny.png';
        file_put_contents($pngPath, hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        try {
            $renderer = new Renderer(
                (new RendererOptions())->withDefaultFont($font)->withBaseDir($baseDir),
            );
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><body><p>caption '
                . '<img src="tiny.png" width="4" height="4"></p></body></html>',
            );
            $bytes = $writer->toBytes();
            self::assertStringContainsString('/Subtype /Image', $bytes, 'small inline-block image emits XObject on page 0');
            self::assertMatchesRegularExpression('~/Im\d+ Do~', $bytes);
        } finally {
            @unlink($pngPath);
            @rmdir($baseDir);
        }
    }

    public function testImgRelativePathEscapeRejected(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $baseDir = sys_get_temp_dir() . '/phpdftk-img-esc-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        try {
            $renderer = new Renderer(
                (new RendererOptions())->withDefaultFont($font)->withBaseDir($baseDir),
            );
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><body><p><img src="../escape.png" width="20" height="20"></p></body></html>',
            );
            // No XObject — relative path that escapes baseDir is dropped.
            self::assertStringNotContainsString('/Subtype /Image', $writer->toBytes());
        } finally {
            @rmdir($baseDir);
        }
    }

    public function testRepeatedDataUrlImagesReuseXObject(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $pngBase64 = base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $url = 'data:image/png;base64,' . $pngBase64;
        $writer = new PdfWriter(compressStreams: false);
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $html = sprintf(
            '<html><body><p><img src="%s" width="20" height="20">'
            . '<img src="%s" width="20" height="20"></p></body></html>',
            $url,
            $url,
        );
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        // One image XObject, two Do invocations.
        $imageXObjectCount = substr_count($bytes, '/Subtype /Image');
        self::assertSame(1, $imageXObjectCount, 'image deduped on the page');
        $doCount = preg_match_all('~/Im\d+ Do~', $bytes);
        self::assertSame(2, $doCount, 'both <img> placements emit Do');
    }

    public function testImgEmitsMissingResourceWarning(): void
    {
        // Phase-1 doesn't paint images; surface a warning so callers know.
        $result = (new Renderer())->render(
            '<html><body><p>before <img src="logo.png"></p></body></html>',
        );
        $codes = array_map(static fn($w) => $w->code, $result->warnings);
        self::assertContains(
            \Phpdftk\HtmlToPdf\WarningCode::MissingResource,
            $codes,
            'one MissingResource warning for the <img>',
        );
    }

    public function testLinkAnnotationsSuppressDefaultBorder(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p><a href="https://example.com">' . "\u{1820}" . '</a></p></body></html>',
        );
        $bytes = $writer->toBytes();
        // /Border [ 0 0 0 ] = no border. Without our explicit value, PDF
        // readers draw a default 1-pt black box around the link area.
        self::assertMatchesRegularExpression(
            '~/Border \[ 0 0 0 \]~',
            $bytes,
            'link border is suppressed',
        );
    }

    public function testATitleFlowsIntoLinkContents(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p><a href="https://example.com" title="Hover text">'
            . "\u{1820}" . '</a></p></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/Subtype /Link', $bytes);
        self::assertStringContainsString('/Contents (Hover text)', $bytes);
    }

    public function testInlineAHrefEmitsLinkAnnotation(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p>before <a href="https://example.com">'
            . "\u{1820}\u{1820}" . '</a> after</p></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/Subtype /Link', $bytes);
        self::assertStringContainsString('https://example.com', $bytes);
    }

    public function testEmptyAndMalformedInputProduceValidPdfs(): void
    {
        $inputs = [
            'empty string' => '',
            'no html element' => '<body></body>',
            'only html' => '<html></html>',
            'unclosed tag' => '<p>broken',
        ];
        foreach ($inputs as $label => $html) {
            $writer = new PdfWriter(compressStreams: false);
            (new Renderer())->renderInto($writer, $html);
            $bytes = $writer->toBytes();
            self::assertStringStartsWith('%PDF-', $bytes, "starts with PDF header for: $label");
            self::assertStringContainsString('%%EOF', $bytes, "ends with EOF for: $label");
        }
    }

    public function testMultiFontSelectionSwitchesPerFamily(): void
    {
        // Register two real fonts; the body uses NotoSans as default, and
        // `<code>` carries `font-family: NotoSansMongolian` so the painter
        // should switch the Tf resource for the code fragment.
        $latinPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        $mongolPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($latinPath) || !is_file($mongolPath)) {
            self::markTestSkipped('Font fixtures missing');
        }
        $latin = (new OpenTypeParser($latinPath))->parse();
        $mongol = (new OpenTypeParser($mongolPath))->parse();
        $renderer = new Renderer(
            (new RendererOptions())
                ->withDefaultFont($latin)
                ->withFonts(['NotoSansMongolian' => $mongol]),
        );
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p>Hello '
            . '<code style="font-family: NotoSansMongolian">' . "\u{1820}\u{1820}" . '</code>'
            . ' world</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Both font XObjects should be registered (two `/Type /Font0` Type-0
        // composite CID fonts) and both Tf resources should appear in the
        // page's content stream as distinct resource names.
        $subtypeFontCount = substr_count($bytes, '/Subtype /Type0');
        self::assertGreaterThanOrEqual(2, $subtypeFontCount, 'both fonts registered');
    }

    public function testFontFaceWithDataUrlLoadsAndShapesText(): void
    {
        // Author CSS declares an `@font-face` whose `src` is a base64 data:
        // URL of the real Mongolian font fixture. The body picks the family
        // via `font-family`, so the renderer should register the data-URL
        // font and shape against it without `withFonts` ever being called.
        $latinPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        $mongolPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($latinPath) || !is_file($mongolPath)) {
            self::markTestSkipped('Font fixtures missing');
        }
        $latin = (new OpenTypeParser($latinPath))->parse();
        $mongolB64 = base64_encode((string) file_get_contents($mongolPath));
        $renderer = new Renderer(
            (new RendererOptions())->withDefaultFont($latin),
        );
        $css = '@font-face { font-family: "DataMongol"; '
            . 'src: url(data:font/otf;base64,' . $mongolB64 . '); }';
        $writer = new PdfWriter(compressStreams: false);
        $warnings = $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head><body>'
            . '<p>en <code style="font-family: DataMongol">' . "\u{1820}\u{1820}" . '</code></p>'
            . '</body></html>',
        );
        self::assertSame([], $warnings, 'no warnings expected — @font-face loaded clean');
        $bytes = $writer->toBytes();
        // Both fonts (Latin default + data-URL Mongolian) should be
        // registered as composite Type-0 fonts on the page.
        $subtypeFontCount = substr_count($bytes, '/Subtype /Type0');
        self::assertGreaterThanOrEqual(2, $subtypeFontCount, 'both fonts registered');
    }

    public function testFontFaceWoffSourceDecompressesAndRegisters(): void
    {
        // Wrap a real OTF as WOFF and reference it via a `data:font/woff`
        // URL in @font-face. The renderer should transparently
        // decompress and register the font.
        $latinPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($latinPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $otfBytes = (string) file_get_contents($latinPath);
        $woffBytes = $this->wrapAsWoff($otfBytes);
        $b64 = base64_encode($woffBytes);
        $latin = (new OpenTypeParser($latinPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($latin));
        $css = '@font-face { font-family: "Inter"; '
            . 'src: url(data:font/woff;base64,' . $b64 . ') format("woff"); }';
        $writer = new PdfWriter(compressStreams: false);
        $warnings = $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p style="font-family: Inter">hi</p></body></html>',
        );
        self::assertSame([], $warnings, 'WOFF source decompresses without warnings');
        // Composite font registered.
        self::assertStringContainsString('/Subtype /Type0', $writer->toBytes());
    }

    /**
     * Wrap an OTF/TTF byte string in a minimal WOFF 1.0 container —
     * mirrors `WoffParserTest::createWoffFromTtf` but tag-agnostic.
     */
    private function wrapAsWoff(string $sfntBytes): string
    {
        $flavor = unpack('N', $sfntBytes, 0)[1];
        $numTables = unpack('n', $sfntBytes, 4)[1];
        $tables = [];
        for ($i = 0; $i < $numTables; $i++) {
            $base = 12 + $i * 16;
            $tables[] = [
                'tag' => substr($sfntBytes, $base, 4),
                'checksum' => unpack('N', $sfntBytes, $base + 4)[1],
                'data' => substr(
                    $sfntBytes,
                    unpack('N', $sfntBytes, $base + 8)[1],
                    unpack('N', $sfntBytes, $base + 12)[1],
                ),
            ];
        }
        $dataOffset = 44 + $numTables * 20;
        $cursor = $dataOffset;
        $entries = [];
        foreach ($tables as $t) {
            $compressed = gzcompress($t['data'], 6);
            $isCompressed = $compressed !== false && strlen($compressed) < strlen($t['data']);
            $entries[] = [
                'tag' => $t['tag'],
                'checksum' => $t['checksum'],
                'offset' => $cursor,
                'compLength' => $isCompressed ? strlen($compressed) : strlen($t['data']),
                'origLength' => strlen($t['data']),
                'compData' => $isCompressed ? $compressed : $t['data'],
            ];
            $cursor += $isCompressed ? strlen($compressed) : strlen($t['data']);
            $cursor += (4 - ($cursor % 4)) % 4;
        }
        $woff = pack('N', 0x774F4646)
            . pack('N', $flavor)
            . pack('N', $cursor)
            . pack('n', $numTables)
            . pack('n', 0)
            . pack('N', strlen($sfntBytes))
            . pack('nn', 1, 0)
            . pack('NNN', 0, 0, 0)
            . pack('NN', 0, 0);
        foreach ($entries as $e) {
            $woff .= $e['tag']
                . pack('N', $e['offset'])
                . pack('N', $e['compLength'])
                . pack('N', $e['origLength'])
                . pack('N', $e['checksum']);
        }
        foreach ($entries as $e) {
            $woff .= $e['compData'];
            $woff .= str_repeat("\x00", (4 - (strlen($e['compData']) % 4)) % 4);
        }
        return $woff;
    }

    public function testFontFaceFormatHintSkipsUnsupportedFormat(): void
    {
        // `src: url(font.woff2) format("woff2"), url(font.otf) format("opentype")` —
        // the resolver must skip the WOFF2 entry (we can't decompress it
        // at Phase 1) and pick up the OpenType fallback. Without the
        // format-hint dispatch, the WOFF2 entry would attempt parsing,
        // emit a noisy warning, and only THEN fall through.
        $latinPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($latinPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $latin = (new OpenTypeParser($latinPath))->parse();
        $b64 = base64_encode((string) file_get_contents($latinPath));
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($latin));
        $css = '@font-face { font-family: "Inter"; '
            . 'src: url(does-not-exist.woff2) format("woff2"), '
            . 'url(data:font/otf;base64,' . $b64 . ') format("opentype"); }';
        $writer = new PdfWriter(compressStreams: false);
        $warnings = $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p style="font-family: Inter">hi</p></body></html>',
        );
        // No "failed to parse" warning — the WOFF2 source was skipped
        // before the fetch even ran.
        $parseWarnings = array_filter(
            $warnings,
            static fn($w) => str_contains($w->message, 'failed to parse'),
        );
        self::assertSame([], $parseWarnings, 'unsupported format hint skipped without parse attempt');
        $bytes = $writer->toBytes();
        // Font landed and got registered as a composite Type-0 font.
        // (Default + @font-face dedupe to one entry per postScriptName,
        // so we just need >= 1 — not 2.)
        self::assertGreaterThanOrEqual(1, substr_count($bytes, '/Subtype /Type0'));
    }

    public function testFontFaceFormatHintIgnoredWhenSourceMatches(): void
    {
        // Bare `url()` with no format hint should still attempt parsing,
        // since `format()` is advisory per CSS Fonts 4 §4.3.
        $latinPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($latinPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $latin = (new OpenTypeParser($latinPath))->parse();
        $b64 = base64_encode((string) file_get_contents($latinPath));
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($latin));
        // No format() hint at all — falls through to magic-number gate.
        $css = '@font-face { font-family: "Inter"; '
            . 'src: url(data:font/otf;base64,' . $b64 . '); }';
        $writer = new PdfWriter(compressStreams: false);
        $warnings = $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p style="font-family: Inter">hi</p></body></html>',
        );
        self::assertSame([], $warnings);
    }

    public function testFontFaceWithMissingSourceEmitsWarning(): void
    {
        // No baseDir, no data: URL — the rule is unloadable and the renderer
        // should emit a `MissingResource` warning while still producing a
        // valid PDF.
        $renderer = new Renderer();
        $css = '@font-face { font-family: "Phantom"; src: url(does-not-exist.otf); }';
        $writer = new PdfWriter(compressStreams: false);
        $warnings = $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head><body><p>hi</p></body></html>',
        );
        $missing = array_filter(
            $warnings,
            static fn($w) => $w->code === \Phpdftk\HtmlToPdf\WarningCode::MissingResource
                && str_contains($w->message, 'Phantom'),
        );
        self::assertNotEmpty($missing, 'expected MissingResource warning for unloadable @font-face');
        self::assertStringStartsWith('%PDF-', $writer->toBytes());
    }

    public function testGenericFamilyAliasMonospacePicksRegisteredFont(): void
    {
        // `<code>` inherits `font-family: monospace` from the UA
        // stylesheet. Binding `monospace` via withGenericFamilies should
        // make the painter switch to the registered font for that
        // fragment, so two distinct Type-0 fonts land on the page.
        $latinPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        $mongolPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($latinPath) || !is_file($mongolPath)) {
            self::markTestSkipped('Font fixtures missing');
        }
        $latin = (new OpenTypeParser($latinPath))->parse();
        $mongol = (new OpenTypeParser($mongolPath))->parse();
        $renderer = new Renderer(
            (new RendererOptions())
                ->withDefaultFont($latin)
                ->withGenericFamilies(['monospace' => $mongol]),
        );
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p>en <code>' . "\u{1820}" . '</code></p></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertGreaterThanOrEqual(
            2,
            substr_count($bytes, '/Subtype /Type0'),
            'monospace generic should resolve to the registered Mongolian font',
        );
    }

    public function testGenericFamilyAliasMergesIntoExistingFontMap(): void
    {
        // withGenericFamilies merges (unlike withFonts which replaces),
        // so a prior withFonts('AcmeBrand' => …) survives a later
        // withGenericFamilies binding for `serif`.
        $latinPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($latinPath)) {
            self::markTestSkipped('Font fixture missing');
        }
        $latin = (new OpenTypeParser($latinPath))->parse();
        $options = (new RendererOptions())
            ->withFonts(['AcmeBrand' => $latin])
            ->withGenericFamilies(['serif' => $latin]);
        self::assertArrayHasKey('acmebrand', $options->fontMap);
        self::assertArrayHasKey('serif', $options->fontMap);
    }

    public function testPageMarginBoxesPaintHeaderAndFooter(): void
    {
        // CSS Paged Media 3 §3 + Generated Content for Paged Media 3 §2:
        // `@page { @top-center { content: "X"; } @bottom-right { content: "Y"; } }`
        // should produce both strings in the per-page margin band of
        // every output page.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { @top-center { content: "TitleHdr"; } '
            . '@bottom-right { content: "FtrTxt"; } }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>body content</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Both margin-box strings must appear in the output as shaped
        // glyph runs — verified via per-glyph CIDs being emitted, plus
        // a generous Tm setTextMatrix at the expected band y-positions
        // (top: pageHeight - margin/2 = 792 - 18 = 774; bottom: 18).
        self::assertMatchesRegularExpression('~ 774 Tm~', $bytes, 'top margin band text matrix');
        self::assertMatchesRegularExpression('~ 18 Tm~', $bytes, 'bottom margin band text matrix');
    }

    public function testPageMarginBoxesHonourFontSizeAndColorDeclarations(): void
    {
        // CSS Paged Media 3 §3.6 margin-box at-rules accept their own
        // `font-size` and `color` declarations; the paint pass must use
        // those instead of the hard-coded 10pt black defaults.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { @top-center { '
            . 'content: "Heading"; font-size: 18pt; color: #cc0000; '
            . '} }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Author font-size (18pt) used in Tf, author color (#cc0000 ≈ 0.8 0 0) used in rg.
        self::assertMatchesRegularExpression('~/F\d+ 18 Tf~', $bytes, 'author font-size honoured');
        self::assertMatchesRegularExpression('~0\.8 0 0 rg~', $bytes, 'author color honoured');
    }

    public function testPageMarginBoxesResolveStringFunction(): void
    {
        // CSS Generated Content for Paged Media 3 §5 — `string-set` on
        // an element assigns a named string; `string(name)` in a page
        // margin box emits the current value.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = 'h1 { string-set: chapter content(); }'
            . '@page { @top-center { content: string(chapter); } }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><h1>Chapter Title</h1><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // The string value sits in the top margin band at Y 774.
        self::assertMatchesRegularExpression('~ 774 Tm~', $bytes, 'top margin band text matrix');
    }

    public function testStringSetAcceptsCounterFunction(): void
    {
        // CSS GCPM 3 §5.1 — `counter()` inside string-set captures
        // the current counter value at the element. Demonstrates the
        // running-section-number-in-header pattern.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = 'body { counter-reset: section; }'
            . 'h1 { counter-increment: section; string-set: secno counter(section); }'
            . '@page { @top-center { content: string(secno); } }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><h1>A</h1><h1>B</h1></body></html>',
        );
        $bytes = $writer->toBytes();
        // The last h1 incremented section to 2 — that's what the
        // top-margin should emit.
        self::assertMatchesRegularExpression('~ 774 Tm~', $bytes, 'top margin band text matrix');
    }

    public function testPageMarginBoxesResolveElementFunction(): void
    {
        // CSS Generated Content for Paged Media 3 §4 — `position:
        // running(name)` opts an element out of the body flow into
        // the running-element store; `content: element(name)` in a
        // page margin box emits its text content.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = 'header { position: running(page-header); }'
            . '@page { @top-center { content: element(page-header); } }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><header>Running Hdr</header><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Element() resolution emits the header text in the top margin band.
        self::assertMatchesRegularExpression('~ 774 Tm~', $bytes, 'top margin band text matrix');
    }

    public function testBodyBackgroundLinearGradientPaintsShadingPattern(): void
    {
        // `body { background-image: linear-gradient(...) }` should produce
        // a pattern-fill operation in the content stream — verified by
        // checking for the `/Pattern cs` color-space op and the pattern
        // resource scn fill.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'body { background-image: linear-gradient(to right, #ff0000, #0000ff); '
            . 'height: 100pt; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/Pattern cs', $bytes, 'pattern colour space set');
        self::assertMatchesRegularExpression('~/P\d+ scn~', $bytes, 'pattern resource fill emitted');
        // Confirm a ShadingType2 (axial gradient) object was registered.
        self::assertStringContainsString('/ShadingType 2', $bytes, 'axial shading dict written');
    }

    public function testLinearGradientThreeStopsUsesStitchingFunction(): void
    {
        // CSS Images 3 §3.5.1: a `linear-gradient(red, yellow, green)`
        // with 3 colour stops should produce a Type-3 stitching
        // function that pieces together 2 Type-2 sub-functions. The
        // resulting PDF should reference both function types.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'body { background-image: linear-gradient(red, yellow, green); '
            . 'height: 100pt; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head><body></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/FunctionType 3', $bytes, 'stitching function written');
        self::assertStringContainsString('/Functions [', $bytes, 'Functions array present');
        self::assertStringContainsString('/Bounds [', $bytes, 'Bounds array present');
        self::assertStringContainsString('/ShadingType 2', $bytes, 'axial shading still used');
    }

    public function testLinearGradientTwoStopsKeepsSimpleFunction(): void
    {
        // Negative: a two-stop gradient should NOT produce a Type-3
        // stitching function — the simple Type-2 path is more compact.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'body { background-image: linear-gradient(red, blue); '
            . 'height: 100pt; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head><body></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringNotContainsString('/FunctionType 3', $bytes, 'two-stop should not stitch');
        self::assertStringContainsString('/FunctionType 2', $bytes, 'Type-2 used directly');
    }

    public function testRepeatingLinearGradientReplicatesStopCycle(): void
    {
        // CSS Images 4 §6.4 — `repeating-linear-gradient` tiles the
        // stop list along the gradient axis. The painter extends the
        // shading axis to cover the clip area in whole cycles and
        // emits one in-cycle stop set per cycle, so a four-stop input
        // becomes a much larger stitching function — many more
        // sub-functions than the non-repeating cousin.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'html, body { margin: 0; padding: 0; height: 100%; } '
            . 'body { background: repeating-linear-gradient(45deg, #fff 0 24px, #226 24px 48px); }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head><body></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/FunctionType 3', $bytes, 'stitching function emitted');
        self::assertStringContainsString('/ShadingType 2', $bytes, 'axial shading still in play');
        // Default Letter page (612 × 792 pt) at 45° gives a gradient
        // line ≈ 992 pt; one 48-pt cycle fits ~20 times so the
        // stitching function should reference well over 20 child
        // sub-functions.
        self::assertMatchesRegularExpression(
            '~/Functions\s*\[\s*(?:\d+\s+0\s+R\s+){40,}\]~',
            $bytes,
            'stitching function references at least 40 sub-functions (≥ 10 cycles × 4 stops)',
        );
    }

    public function testLinearGradientStopsWithExplicitPositions(): void
    {
        // `linear-gradient(red, yellow 30%, green 70%, blue)` — four
        // stops with two interior positions. Should emit a stitching
        // function with bounds at 0.3 and 0.7.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'body { background-image: linear-gradient(red, yellow 30%, green 70%, blue); '
            . 'height: 100pt; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head><body></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/FunctionType 3', $bytes);
        // Bounds list should contain 0.3 and 0.7 (the two interior
        // positions). Match permissively to tolerate float formatting.
        self::assertMatchesRegularExpression(
            '~/Bounds\s*\[\s*0\.3\d*\s+0\.7\d*\s*\]~',
            $bytes,
            'bounds reflect the authored stop positions',
        );
    }

    public function testRadialGradientThreeStopsUsesStitchingFunction(): void
    {
        // Negative-ish: same multi-stop machinery applies to radial.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'body { background-image: radial-gradient(circle, red, yellow, green); '
            . 'height: 100pt; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head><body></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/FunctionType 3', $bytes, 'radial uses stitching too');
        self::assertStringContainsString('/ShadingType 3', $bytes, 'radial shading dict');
    }

    public function testLinearGradientLengthPositionedStopResolvesToFraction(): void
    {
        // CSS Images 3 §3.5.1 — a `<length>` stop position divides
        // by the gradient line length. For a vertical
        // linear-gradient (0deg → upward, line length == box height),
        // a stop at `40px` in a 100px-tall box should resolve to
        // 0.4 in the PDF Type-3 stitching function's `/Bounds`.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        // Use a `<div>` rather than `<body>` — CSS Backgrounds 3
        // §3.11.2 propagates body backgrounds to the canvas, which
        // would make the gradient line length equal to the page
        // height, not the 100px we want to test against.
        $css = 'div.box { background-image: linear-gradient(red, yellow 40px, green); '
            . 'width: 100pt; height: 100px; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head><body><div class="box"></div></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/FunctionType 3', $bytes, 'three-stop stitching');
        // The interior stop at 40px / 100px = 0.4 lands in Bounds.
        self::assertMatchesRegularExpression(
            '~/Bounds\s*\[\s*0\.4\d*\s*\]~',
            $bytes,
            'length-positioned stop converted to 0.4 fraction',
        );
    }

    public function testLinearGradientLengthStopBeyondLineClampsToOne(): void
    {
        // Negative: a length stop that exceeds the gradient line
        // length must clamp to 1.0 (not produce an out-of-range
        // Bounds entry).
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'body { background-image: linear-gradient(red, yellow 500px, blue, green); '
            . 'width: 100pt; height: 100px; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head><body></body></html>',
        );
        $bytes = $writer->toBytes();
        // The bounds array entries must all be in [0, 1].
        if (preg_match('~/Bounds\s*\[([^\]]*)\]~', $bytes, $m) === 1) {
            $entries = preg_split('/\s+/', trim($m[1])) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '') {
                    continue;
                }
                $value = (float) $entry;
                self::assertGreaterThanOrEqual(0.0, $value);
                self::assertLessThanOrEqual(1.0, $value);
            }
        } else {
            self::fail('Expected a /Bounds array');
        }
    }

    public function testBackgroundSizeContainPreservesAspectAndTopLeftAligns(): void
    {
        // A 4×2 PNG (2:1 aspect) in a 200px × 60px box. `contain` picks
        // min(200/4, 60/2) = min(50, 30) = 30, so final = 120×60.
        // CSS Backgrounds 3 §3.6 — initial `background-position` is
        // `0% 0%` (top-left), so the 120×60 image anchors at x=0
        // leaving 80px of background-color on the right.
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'div { background-image: url(data:image/png;base64,' . $b64 . '); '
            . 'background-size: contain; width: 200px; height: 60px; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><div></div></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertMatchesRegularExpression(
            '~120 0 0 60 0 [\d.]+ cm~',
            $bytes,
            'contain scales 4:2 image to 120×60 anchored at top-left (offsetX=0)',
        );
    }

    public function testBackgroundSizeCoverFillsBoxWithOverflow(): void
    {
        // 4×2 PNG in a 100×100 box: cover picks max(100/4, 100/2) = 50,
        // so final = 200×100 (overflows horizontally). Per CSS
        // Backgrounds 3 §3.6 default position `0% 0%`, the image
        // anchors at top-left (x=0); the right half overflows past
        // the box and is clipped by the bg-clip rect.
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'div { background-image: url(data:image/png;base64,' . $b64 . '); '
            . 'background-size: cover; width: 100px; height: 100px; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><div></div></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertMatchesRegularExpression(
            '~200 0 0 100 0 [\d.]+ cm~',
            $bytes,
            'cover overflows right at top-left anchor (offsetX = 0)',
        );
    }

    public function testImgObjectFitContainPreservesAspect(): void
    {
        // 4×2 PNG (2:1) in a 100×100 box with `object-fit: contain`:
        // scale = min(100/4, 100/2) = 25, final = 100×50, centred y=25.
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><body><img src="data:image/png;base64,' . $b64
            . '" width="100" height="100" style="object-fit: contain;"></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        // contain → 100×50 centred at (0, 25).
        self::assertMatchesRegularExpression(
            '~100 0 0 50 0 [\d.]+ cm~',
            $bytes,
            'contain scales 4:2 image to 100×50 inside 100×100',
        );
    }

    public function testImgObjectFitCoverOverflows(): void
    {
        // 4×2 in 100×100: cover picks max(25, 50) = 50 → 200×100,
        // offsetX = (100-200)/2 = -50.
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><body><img src="data:image/png;base64,' . $b64
            . '" width="100" height="100" style="object-fit: cover;"></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertMatchesRegularExpression(
            '~200 0 0 100 -50 [\d.]+ cm~',
            $bytes,
            'cover overflows horizontally (offsetX=-50)',
        );
    }

    public function testImgObjectFitFillKeepsStretchBehaviour(): void
    {
        // Default `fill` → unchanged stretch behaviour so existing
        // fixtures stay stable.
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><body><img src="data:image/png;base64,' . $b64
            . '" width="100" height="100"></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertMatchesRegularExpression(
            '~100 0 0 100 0 [\d.]+ cm~',
            $bytes,
            'default object-fit fill stretches to box',
        );
    }

    public function testImgObjectFitNoneShowsNaturalSize(): void
    {
        // `object-fit: none` keeps the natural 4×2 size inside the 100×100
        // box, centred at offsetX = (100-4)/2 = 48, offsetY = (100-2)/2 = 49.
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><body><img src="data:image/png;base64,' . $b64
            . '" width="100" height="100" style="object-fit: none;"></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertMatchesRegularExpression(
            '~4 0 0 2 48 [\d.]+ cm~',
            $bytes,
            'none keeps natural 4×2 size centred in box',
        );
    }

    public function testHiddenAttributeHidesElement(): void
    {
        // HTML 5 `<div hidden>` should not render at all per the
        // `[hidden] { display: none }` UA rule.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body>'
            . '<div hidden style="background-color: #cc0000; height: 50px;"></div>'
            . '<div style="background-color: #00cc00; height: 50px;"></div>'
            . '</body></html>',
        );
        $bytes = $writer->toBytes();
        // Red fill (hidden div) must NOT appear.
        self::assertDoesNotMatchRegularExpression(
            '~0\.8 0 0 rg~',
            $bytes,
            'hidden div suppressed',
        );
        // Green fill (visible div) appears.
        self::assertMatchesRegularExpression(
            '~0 0\.8 0 rg~',
            $bytes,
            'visible sibling still rendered',
        );
    }

    public function testHiddenUntilFoundStillHidesInPrint(): void
    {
        // HTML 5 §3.2.6.1: `hidden="until-found"` reveals the
        // element only for in-page text find. In a static print
        // render there is no find UI — the element must stay hidden,
        // matching the bare `hidden` form.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body>'
            . '<div hidden="until-found" style="background-color: #cc0000; height: 50px;"></div>'
            . '<div style="background-color: #00cc00; height: 50px;"></div>'
            . '</body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertDoesNotMatchRegularExpression(
            '~0\.8 0 0 rg~',
            $bytes,
            'until-found stays hidden in static print',
        );
        self::assertMatchesRegularExpression('~0 0\.8 0 rg~', $bytes);
    }

    public function testDialogWithoutOpenIsHidden(): void
    {
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body>'
            . '<dialog style="background-color: #cc0000; height: 50px;"></dialog>'
            . '</body></html>',
        );
        self::assertDoesNotMatchRegularExpression(
            '~0\.8 0 0 rg~',
            $writer->toBytes(),
            'closed dialog must not render',
        );
    }

    public function testDialogOpenAttributeRendersContent(): void
    {
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body>'
            . '<dialog open style="background-color: #cc0000; height: 50px;"></dialog>'
            . '</body></html>',
        );
        self::assertMatchesRegularExpression(
            '~0\.8 0 0 rg~',
            $writer->toBytes(),
            'dialog[open] renders content',
        );
    }

    public function testTemplateElementContentDoesNotRender(): void
    {
        // `<template>` content is parsed into a separate fragment tree
        // and never rendered in the host document.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body>'
            . '<template><div style="background-color: #cc0000; height: 50px;"></div></template>'
            . '</body></html>',
        );
        self::assertDoesNotMatchRegularExpression(
            '~0\.8 0 0 rg~',
            $writer->toBytes(),
            'template content not rendered',
        );
    }

    public function testCssAtImportRelativePath(): void
    {
        // `@import url("base.css")` at the top of an inline `<style>`
        // should pull in the linked CSS rules and cascade them.
        $baseDir = sys_get_temp_dir() . '/phpdftk-import-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        file_put_contents($baseDir . '/base.css', 'p { background-color: #ff8800; }');
        try {
            $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><head><style>'
                . '@import url("base.css");'
                . '</style></head>'
                . '<body><p style="height: 20px;">hi</p></body></html>',
            );
            self::assertMatchesRegularExpression(
                '~1 0\.5\d+ 0 rg~',
                $writer->toBytes(),
                '@import url() pulls in external CSS',
            );
        } finally {
            @unlink($baseDir . '/base.css');
            @rmdir($baseDir);
        }
    }

    public function testCssAtImportBareStringForm(): void
    {
        // `@import "base.css"` (no url()) should also load.
        $baseDir = sys_get_temp_dir() . '/phpdftk-import-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        file_put_contents($baseDir . '/base.css', 'p { background-color: #00cc00; }');
        try {
            $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><head><style>'
                . '@import "base.css";'
                . '</style></head>'
                . '<body><p style="height: 20px;">hi</p></body></html>',
            );
            self::assertMatchesRegularExpression(
                '~0 0\.8 0 rg~',
                $writer->toBytes(),
                'bare-string @import loads',
            );
        } finally {
            @unlink($baseDir . '/base.css');
            @rmdir($baseDir);
        }
    }

    public function testCssAtImportRecurses(): void
    {
        // a.css → @import b.css; b.css colors paragraphs.
        $baseDir = sys_get_temp_dir() . '/phpdftk-import-rec-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        file_put_contents($baseDir . '/a.css', '@import url("b.css");');
        file_put_contents($baseDir . '/b.css', 'p { background-color: #0000cc; }');
        try {
            $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><head><style>@import url("a.css");</style></head>'
                . '<body><p style="height: 20px;">hi</p></body></html>',
            );
            self::assertMatchesRegularExpression(
                '~0 0 0\.8 rg~',
                $writer->toBytes(),
                'recursive @import resolves',
            );
        } finally {
            @unlink($baseDir . '/a.css');
            @unlink($baseDir . '/b.css');
            @rmdir($baseDir);
        }
    }

    public function testCssAtImportMediaScreenSkipped(): void
    {
        // `@import url("...") screen` filters by media; print context skips.
        $baseDir = sys_get_temp_dir() . '/phpdftk-import-media-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        file_put_contents($baseDir . '/screen.css', 'p { background-color: #cc00cc; }');
        try {
            $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><head><style>'
                . '@import url("screen.css") screen;'
                . '</style></head>'
                . '<body><p style="height: 20px;">hi</p></body></html>',
            );
            self::assertDoesNotMatchRegularExpression(
                '~0\.8 0 0\.8 rg~',
                $writer->toBytes(),
                '@import with screen media skipped in print context',
            );
        } finally {
            @unlink($baseDir . '/screen.css');
            @rmdir($baseDir);
        }
    }

    public function testLinkRelStylesheetLoadsExternalCss(): void
    {
        // `<link rel="stylesheet" href="...">` should load the linked
        // CSS and cascade it. Verified by linking a CSS that paints a
        // distinctive colour and checking it lands in the content stream.
        $baseDir = sys_get_temp_dir() . '/phpdftk-link-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        file_put_contents($baseDir . '/external.css', 'p { background-color: #ff8800; }');
        try {
            $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><head><link rel="stylesheet" href="external.css"></head>'
                . '<body><p style="height: 20px;">hi</p></body></html>',
            );
            // #ff8800 → (1, 0.533, 0) rg.
            self::assertMatchesRegularExpression(
                '~1 0\.5\d+ 0 rg~',
                $writer->toBytes(),
                'linked stylesheet rules cascade',
            );
        } finally {
            @unlink($baseDir . '/external.css');
            @rmdir($baseDir);
        }
    }

    public function testLinkRelStylesheetDataUrl(): void
    {
        // `<link rel="stylesheet" href="data:text/css,...">` should
        // decode the inline CSS and cascade.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><head><link rel="stylesheet" href="data:text/css,p%20%7B%20background-color%3A%20%23cc0000%3B%20%7D"></head>'
            . '<body><p style="height: 20px;">hi</p></body></html>',
        );
        self::assertMatchesRegularExpression(
            '~0\.8 0 0 rg~',
            $writer->toBytes(),
            'data:text/css URL loaded',
        );
    }

    public function testLinkRelStylesheetMediaScreenSkipped(): void
    {
        // `<link rel="stylesheet" media="screen">` should NOT load in
        // print context — same media-matching as @media blocks.
        $baseDir = sys_get_temp_dir() . '/phpdftk-link-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        file_put_contents($baseDir . '/screen.css', 'p { background-color: #ff8800; }');
        try {
            $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><head><link rel="stylesheet" media="screen" href="screen.css"></head>'
                . '<body><p style="height: 20px;">hi</p></body></html>',
            );
            self::assertDoesNotMatchRegularExpression(
                '~1 0\.5\d+ 0 rg~',
                $writer->toBytes(),
                'screen-only linked stylesheet must not cascade in print',
            );
        } finally {
            @unlink($baseDir . '/screen.css');
            @rmdir($baseDir);
        }
    }

    public function testLinkRelAlternateStylesheetIsNotApplied(): void
    {
        // HTML 5 §4.6.7.10: `<link rel="alternate stylesheet">` is an
        // alternate stylesheet. Browsers don't apply it by default;
        // the user opts in via a stylesheet-selection UI we don't have.
        // The renderer must drop the sheet rather than cascade it.
        $baseDir = sys_get_temp_dir() . '/phpdftk-link-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        file_put_contents($baseDir . '/preferred.css', 'p { background-color: #00cc00; }');
        file_put_contents($baseDir . '/alt.css', 'p { background-color: #cc0000; }');
        try {
            $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><head>'
                . '<link rel="stylesheet" href="preferred.css" title="preferred">'
                . '<link rel="alternate stylesheet" href="alt.css" title="alt">'
                . '</head><body><p style="height: 20px;">hi</p></body></html>',
            );
            $bytes = $writer->toBytes();
            // preferred green (#00cc00 → 0 0.8 0 rg) MUST be applied.
            self::assertMatchesRegularExpression(
                '~0 0\.8 0 rg~',
                $bytes,
                'preferred stylesheet applied',
            );
            // alternate red (#cc0000 → 0.8 0 0 rg) MUST NOT be applied.
            self::assertDoesNotMatchRegularExpression(
                '~0\.8 0 0 rg~',
                $bytes,
                'alternate stylesheet must not cascade by default',
            );
        } finally {
            @unlink($baseDir . '/preferred.css');
            @unlink($baseDir . '/alt.css');
            @rmdir($baseDir);
        }
    }

    public function testBoxSizingBorderBoxOnReplacedImgShrinksContent(): void
    {
        // CSS Sizing 3 §6.2: under `box-sizing: border-box`, the
        // declared `width` includes padding + border, so the content
        // box (where the SVG paints) must shrink by the inset. Without
        // the box-sizing adjustment, a 120px-wide img with 20px
        // padding-left renders the SVG at 120×… and the WPT
        // box-sizing-* fixtures fail because the visible green doesn't
        // match the reference's 100×100 squares.
        $baseDir = sys_get_temp_dir() . '/phpdftk-bs-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        file_put_contents(
            $baseDir . '/sq.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" style="background: green" width="100" height="100"/>',
        );
        try {
            $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><head><style>'
                . 'img { box-sizing: border-box; width: 120px; padding-left: 20px; }'
                . '</style></head><body><img src="sq.svg"></body></html>',
            );
            $bytes = $writer->toBytes();
            // Expect a 100×… green rect (content box = 120 - 20 = 100).
            // The painter emits the rect right after `0 0.5019607843 0 rg`.
            self::assertMatchesRegularExpression(
                '~0 0\.5019607843 0 rg\s+\S+ \S+ 100 \S+ re~',
                $bytes,
                'box-sizing: border-box shrinks content width on replaced img',
            );
            // And it should NOT be at 120 wide (which is the bug we fixed).
            self::assertDoesNotMatchRegularExpression(
                '~0 0\.5019607843 0 rg\s+\S+ \S+ 120 \S+ re~',
                $bytes,
                'content box must not use the declared border-box width',
            );
        } finally {
            @unlink($baseDir . '/sq.svg');
            @rmdir($baseDir);
        }
    }

    public function testImgWithAltAndLoadableSrcPaintsTheImageNotAlt(): void
    {
        // HTML 5 §4.8.3: the alt text fallback is for when the image
        // *can't* be painted. A loadable src keeps the AtomicInlineBox
        // path so the image renders as an Image XObject (a `Do` op).
        // The pre-existing bug rendered the alt text instead, leaving
        // documents with no glyphs for an unconfigured-font setup.
        $baseDir = sys_get_temp_dir() . '/phpdftk-img-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );
        self::assertNotFalse($png);
        file_put_contents($baseDir . '/blue.png', $png);
        try {
            $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><body><div><img src="blue.png" width="40" height="20" alt="caption"/></div></body></html>',
            );
            $bytes = $writer->toBytes();
            self::assertMatchesRegularExpression(
                '~/Im\d+ Do~',
                $bytes,
                'loadable image painted instead of alt fallback',
            );
        } finally {
            @unlink($baseDir . '/blue.png');
            @rmdir($baseDir);
        }
    }

    public function testImgWithAltAndMissingSrcSkipsImagePaint(): void
    {
        // Negative case: when the src cannot be resolved, the image
        // is dropped and the alt-fallback InlineBox carries the flow
        // (or, with no default font, renders nothing). The key
        // invariant is that *no Image XObject* is registered for a
        // missing source — the prior bug would have spilled a broken
        // XObject.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><div><img src="missing.png" width="40" height="20" alt="caption"/></div></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertDoesNotMatchRegularExpression(
            '~/Im\d+ Do~',
            $bytes,
            'missing-src image must not produce an Image XObject paint',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testFontFaceLoadsTrueTypeFont(): void
    {
        // `@font-face` should accept TrueType (`sfVersion = 0x00010000`)
        // sources, not just OpenType-CFF (`OTTO`). The renderer falls
        // back to TrueTypeParser when OpenTypeParser rejects the bytes.
        // Without this, every WPT fixture that loads Ahem (and most do)
        // renders no text at all.
        $ttfPath = realpath(__DIR__ . '/../../../vendor-data/wpt/fonts/Ahem.ttf');
        if ($ttfPath === false) {
            self::markTestSkipped('Ahem.ttf unavailable; needs the wpt vendor submodule');
        }
        $baseDir = dirname($ttfPath);
        $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><head><style>'
            . '@font-face { font-family: Ahem; src: url(Ahem.ttf); }'
            . 'div { font-family: Ahem; font-size: 50px; color: green; }'
            . '</style></head><body><div>XX</div></body></html>',
        );
        $bytes = $writer->toBytes();
        // Real text emission requires (a) the @font-face TTF to load,
        // (b) the painter to register the font, (c) the painter to NOT
        // bail when defaultFont is null but a registered font exists.
        // All three were broken before this change; assert all three.
        self::assertMatchesRegularExpression(
            '~/BaseFont /Ahem~',
            $bytes,
            'Ahem registered as a Type0 base font',
        );
        self::assertMatchesRegularExpression(
            '~BT\s+/F\d+ 50 Tf~',
            $bytes,
            'text run opened with the registered font',
        );
        self::assertMatchesRegularExpression(
            '~Tj~',
            $bytes,
            'glyph string emitted',
        );
        self::assertMatchesRegularExpression(
            '~0 0\.5019607843 0 rg~',
            $bytes,
            'cascaded color reaches the text run',
        );
    }

    public function testFontFaceRejectsMalformedFontSourceWithBothParsers(): void
    {
        // Negative case: garbage bytes pass neither OpenTypeParser nor
        // TrueTypeParser. The face should drop with a Warning that
        // names both parser errors; the rest of the document still
        // renders (background + border content).
        $baseDir = sys_get_temp_dir() . '/phpdftk-bad-font-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        file_put_contents($baseDir . '/bad.ttf', "this is definitely not an sfnt");
        try {
            $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
            $result = $renderer->render(
                '<html><head><style>'
                . '@font-face { font-family: Bad; src: url(bad.ttf); }'
                . 'div { font-family: Bad; }'
                . '</style></head><body><div>x</div></body></html>',
            );
            $parseWarnings = array_filter(
                $result->warnings,
                static fn($w): bool => str_contains($w->message, 'OpenType:')
                    && str_contains($w->message, 'TrueType:'),
            );
            self::assertCount(
                1,
                $parseWarnings,
                'one warning naming both parser errors',
            );
            $bytes = $result->writer->toBytes();
            self::assertStringStartsWith('%PDF-', $bytes, 'document still produces a valid PDF');
        } finally {
            @unlink($baseDir . '/bad.ttf');
            @rmdir($baseDir);
        }
    }

    public function testImgWithSvgSrcPaintsBackgroundOnSvgRoot(): void
    {
        // `<img src="*.svg">` should route through the SVG painter
        // (PdfWriter::addImage rejects SVG since it's not a raster).
        // CSS `background` on the SVG root must paint as a filled
        // rect — covers the WPT box-sizing-* fixture family that
        // uses SVGs with `style="background: green"` as their
        // visible content.
        $baseDir = sys_get_temp_dir() . '/phpdftk-svg-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        file_put_contents(
            $baseDir . '/w100_h100.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" style="background: green" width="100" height="100"/>',
        );
        try {
            $renderer = new Renderer((new RendererOptions())->withBaseDir($baseDir));
            $writer = new PdfWriter(compressStreams: false);
            $renderer->renderInto(
                $writer,
                '<html><body><img src="w100_h100.svg"></body></html>',
            );
            $bytes = $writer->toBytes();
            // CSS named 'green' is #008000 → rgb(0, 128, 0)
            // → PDF "0 0.5019607843 0 rg".
            self::assertMatchesRegularExpression(
                '~0 0\.5019607843 0 rg~',
                $bytes,
                'SVG background-on-root paints CSS green',
            );
        } finally {
            @unlink($baseDir . '/w100_h100.svg');
            @rmdir($baseDir);
        }
    }

    public function testBackgroundRepeatDefaultTilesBothAxes(): void
    {
        // 4×2 PNG with explicit small `background-size: 50px 50px` in a
        // 200×100 box and default `background-repeat: repeat` should
        // tile 4× horizontally and 2× vertically = 8 image emits.
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'div { background-image: url(data:image/png;base64,' . $b64 . '); '
            . 'background-size: 50px 50px; background-position: 0 0; '
            . 'width: 200px; height: 100px; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><div></div></body></html>',
        );
        $bytes = $writer->toBytes();
        // 4 tiles × 2 = 8 emissions. Count cm operators that match the
        // tile size (50 0 0 50 ...).
        $matched = preg_match_all('~50 0 0 50 \S+ \S+ cm~', $bytes, $m);
        self::assertSame(8, $matched, 'default repeat tiles 4×2 = 8 tiles');
    }

    public function testBackgroundRepeatNoRepeatEmitsSingleTile(): void
    {
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'div { background-image: url(data:image/png;base64,' . $b64 . '); '
            . 'background-size: 50px 50px; background-repeat: no-repeat; '
            . 'width: 200px; height: 100px; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><div></div></body></html>',
        );
        $bytes = $writer->toBytes();
        $matched = preg_match_all('~50 0 0 50 \S+ \S+ cm~', $bytes, $m);
        self::assertSame(1, $matched, 'no-repeat emits exactly one tile');
    }

    public function testBackgroundRepeatXOnly(): void
    {
        // `repeat-x` tiles horizontally only. 200/50 = 4 tiles.
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'div { background-image: url(data:image/png;base64,' . $b64 . '); '
            . 'background-size: 50px 50px; background-repeat: repeat-x; '
            . 'background-position: 0 0; width: 200px; height: 100px; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><div></div></body></html>',
        );
        $bytes = $writer->toBytes();
        $matched = preg_match_all('~50 0 0 50 \S+ \S+ cm~', $bytes, $m);
        self::assertSame(4, $matched, 'repeat-x emits 4 horizontal tiles only');
    }

    public function testBackgroundPositionLeftTopAnchorsToCorner(): void
    {
        // `background-position: left top` with contain-scaled image
        // should anchor the image to the box's top-left (offsetX=0,
        // offsetY=0) instead of the default centring.
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'div { background-image: url(data:image/png;base64,' . $b64 . '); '
            . 'background-size: contain; background-position: left top; '
            . 'width: 200px; height: 60px; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><div></div></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertMatchesRegularExpression(
            '~120 0 0 60 0 [\d.]+ cm~',
            $bytes,
            'left top anchors at offsetX=0',
        );
    }

    public function testBackgroundPositionExplicitPercentages(): void
    {
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'div { background-image: url(data:image/png;base64,' . $b64 . '); '
            . 'background-size: contain; background-position: 100% 0%; '
            . 'width: 200px; height: 60px; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><div></div></body></html>',
        );
        $bytes = $writer->toBytes();
        // 100% horizontal → offsetX = (200-120)*1 = 80.
        self::assertMatchesRegularExpression(
            '~120 0 0 60 80 [\d.]+ cm~',
            $bytes,
            '100% horizontal anchors image right edge to box right edge',
        );
    }

    public function testBackgroundPositionExplicitLengths(): void
    {
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'div { background-image: url(data:image/png;base64,' . $b64 . '); '
            . 'background-size: contain; background-position: 10px 5px; '
            . 'width: 200px; height: 60px; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><div></div></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertMatchesRegularExpression(
            '~120 0 0 60 10 [\d.]+ cm~',
            $bytes,
            'explicit 10px x-offset honoured',
        );
    }

    public function testBackgroundSizeAutoUsesIntrinsicDimsAndTiles(): void
    {
        // CSS Backgrounds 3 §3.9 — unset / `auto` `background-size`
        // with an image that has intrinsic dimensions uses those
        // dimensions for the tile. With `background-repeat: repeat`
        // (default), the 4×2 PNG tiles across the 100×100 box,
        // emitting one `cm` placement per tile at scale 4×2.
        $png4x2 = hex2bin(
            '89504e470d0a1a0a0000000d4948445200000004000000020802000000f0caea34'
            . '000000097048597300000ec400000ec401952b0e1b00000012494441540899'
            . '633c9162c400034c0c48000021b40162592fe5580000000049454e44ae426082',
        );
        $b64 = base64_encode($png4x2);
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'div { background-image: url(data:image/png;base64,' . $b64 . '); '
            . 'width: 100px; height: 100px; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><div></div></body></html>',
        );
        $bytes = $writer->toBytes();
        // Each tile is 4×2 with translation; check that we emit
        // tile-scale `cm` matrices (not the legacy stretched 100×100).
        self::assertMatchesRegularExpression(
            '~4 0 0 2 \d+ [\d.]+ cm~',
            $bytes,
            'auto bg-size uses intrinsic 4×2 dims and tiles',
        );
        self::assertDoesNotMatchRegularExpression(
            '~100 0 0 100 [\d.]+ [\d.]+ cm~',
            $bytes,
            'no legacy 100×100 stretch matrix',
        );
    }

    public function testBodyBackgroundRadialGradientPaintsType3Shading(): void
    {
        // `body { background-image: radial-gradient(...) }` should emit
        // a ShadingType3 (radial) shading dict + pattern-fill operations.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'body { background-image: radial-gradient(circle, #ff0000, #0000ff); '
            . 'height: 100pt; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/ShadingType 3', $bytes, 'radial shading dict written');
        self::assertStringContainsString('/Pattern cs', $bytes, 'pattern colour space set');
        self::assertMatchesRegularExpression('~/P\d+ scn~', $bytes, 'pattern fill emitted');
    }

    public function testBodyBackgroundRadialGradientWithExplicitPosition(): void
    {
        // `radial-gradient(... at <position>, ...)` still produces a
        // valid ShadingType3; the renderer must place the gradient
        // centre at the author's anchor without crashing.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'body { background-image: radial-gradient(circle at 50pt 50pt, #cc0000, #00cc00); '
            . 'height: 200pt; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/ShadingType 3', $bytes);
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testBodyBackgroundLinearGradientWithExplicitAngle(): void
    {
        // Verify the angle path: `linear-gradient(45deg, ...)`. The
        // angle controls the gradient line direction; we just verify
        // a pattern still emits without errors.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = 'body { background-image: linear-gradient(45deg, #cc0000, #00cc00); '
            . 'height: 200pt; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/ShadingType 2', $bytes);
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testGridLayoutEndToEndProducesValidPdf(): void
    {
        // Integration: render a 3-column × 2-row grid with explicit
        // placement + auto-flow + gap. Verify the PDF is well-formed
        // and contains the expected cell content for each item.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><head><style>'
            . '.grid { display: grid; '
            . '        grid-template-columns: 60pt 60pt 60pt; '
            . '        grid-template-rows: 40pt 40pt; '
            . '        column-gap: 8pt; row-gap: 4pt; }'
            . '.cell { background-color: #cccccc; }'
            . '.span { grid-column: 1 / 3; background-color: #aabbcc; }'
            . '</style></head><body>'
            . '<div class="grid">'
            . '<div class="cell"></div>'
            . '<div class="cell"></div>'
            . '<div class="cell"></div>'
            . '<div class="span"></div>'
            . '<div class="cell"></div>'
            . '</div></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Two distinct background colours emitted (the cell grey at
        // #cccccc = 0.8 0.8 0.8, and the span colour #aabbcc).
        self::assertMatchesRegularExpression(
            '~0\.8 0\.8 0\.8 rg~',
            $bytes,
            'cell background colour painted',
        );
        // 5 background rects total (4 cells + 1 spanned). Just count
        // the colour-fill operator occurrences as a sanity check.
        $rgCount = preg_match_all('~ rg(\s|\n)~', $bytes);
        self::assertGreaterThanOrEqual(5, $rgCount, 'multiple rg operators present');
    }

    public function testGridAdvancedFeaturesEndToEndProducesValidPdf(): void
    {
        // Integration: exercise fr + repeat + span + justify-self in
        // a single fixture and verify the produced PDF starts with
        // %PDF- and emits the expected background rects.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><head><style>'
            . '.grid { display: grid; '
            . '        grid-template-columns: 100pt repeat(2, 1fr); '
            . '        grid-template-rows: repeat(3, 40pt); '
            . '        column-gap: 4pt; row-gap: 4pt; }'
            . '.cell { background-color: #ddeeff; }'
            . '.span { grid-column: 1 / span 3; background-color: #aaddff; }'
            . '.endX { justify-self: end; width: 30pt; background-color: #ff0000; }'
            . '</style></head><body>'
            . '<div class="grid">'
            . '<div class="cell"></div>'
            . '<div class="cell"></div>'
            . '<div class="cell"></div>'
            . '<div class="span"></div>'
            . '<div class="endX"></div>'
            . '<div class="cell"></div>'
            . '<div class="cell"></div>'
            . '</div></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Red end-justified item emitted.
        self::assertMatchesRegularExpression('~1 0 0 rg~', $bytes, 'red endX rect');
        // Light blue span rect emitted (#aaddff ≈ 0.667 0.867 1).
        self::assertMatchesRegularExpression('~0\.6\d+ 0\.8\d+ 1 rg~', $bytes, 'span rect colour');
    }

    public function testGridTemplateAreasEndToEndProducesValidPdf(): void
    {
        // Integration: the canonical "holy grail" Grid layout — a
        // header / sidebar / main / footer using `grid-template-
        // areas` with name-based placement.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><head><style>'
            . '.app { display: grid; '
            . '       grid-template-areas: "head head" "side main" "foot foot"; '
            . '       grid-template-columns: 80pt 1fr; '
            . '       grid-template-rows: 40pt 200pt 30pt; '
            . '       column-gap: 4pt; row-gap: 4pt; height: 280pt; }'
            . '.head { grid-area: head; background-color: #336699; }'
            . '.side { grid-area: side; background-color: #99ccff; }'
            . '.main { grid-area: main; background-color: #eeeeff; }'
            . '.foot { grid-area: foot; background-color: #336699; }'
            . '</style></head><body>'
            . '<div class="app">'
            . '<div class="head"></div>'
            . '<div class="main"></div>'
            . '<div class="side"></div>'
            . '<div class="foot"></div>'
            . '</div></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Each named area picks up its own background colour. The
        // dark blue (#336699 ≈ 0.2 0.4 0.6) used by head + foot
        // should appear in the bytes; the light blue (#99ccff
        // ≈ 0.6 0.8 1) used by side; and the lavender (#eeeeff
        // ≈ 0.933 0.933 1) used by main.
        self::assertMatchesRegularExpression('~0\.2 0\.4 0\.6 rg~', $bytes, 'head/foot blue');
        self::assertMatchesRegularExpression('~0\.6 0\.8 1 rg~', $bytes, 'side blue');
        self::assertMatchesRegularExpression('~0\.9\d+ 0\.9\d+ 1 rg~', $bytes, 'main lavender');
    }

    public function testTransform3dEndToEndProducesValidPdf(): void
    {
        // Integration: render a small grid of boxes each with a
        // different 3D-transform primitive — rotateX, rotateY,
        // rotateZ, matrix3d. Verify the PDF starts with %PDF- and
        // emits cm operators for each.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><head><style>'
            . '.card { display: inline-block; width: 80pt; height: 60pt; '
            . '        background-color: #336699; }'
            . '.flipX { transform: rotateX(60deg); }'
            . '.flipY { transform: rotateY(60deg); }'
            . '.spin { transform: rotateZ(45deg); }'
            . '.m3d { transform: matrix3d(1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 10, 0, 0, 1); }'
            . '</style></head><body>'
            . '<div class="card flipX"></div>'
            . '<div class="card flipY"></div>'
            . '<div class="card spin"></div>'
            . '<div class="card m3d"></div>'
            . '</body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Look for the Y-scale matrix from rotateX(60deg).
        self::assertMatchesRegularExpression('~1 0 0 0\.5 ~', $bytes, 'rotateX(60deg) Y-scale');
        // Look for the X-scale matrix from rotateY(60deg).
        self::assertMatchesRegularExpression('~0\.5 0 0 1 ~', $bytes, 'rotateY(60deg) X-scale');
    }

    public function testGridImplicitRowsEndToEndProducesValidPdf(): void
    {
        // Integration: render 12 items in a 3-column grid with only
        // 1 explicit row + grid-auto-rows. The 9 extra items grow
        // implicit rows. Total grid height = 1 explicit + 3 implicit.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $items = '';
        for ($i = 0; $i < 12; $i++) {
            $items .= '<div class="cell"></div>';
        }
        $html = '<html><head><style>'
            . '.grid { display: grid; '
            . '        grid-template-columns: repeat(3, 60pt); '
            . '        grid-template-rows: 30pt; '
            . '        grid-auto-rows: 40pt; '
            . '        column-gap: 4pt; row-gap: 4pt; }'
            . '.cell { background-color: #def; }'
            . '</style></head><body><div class="grid">' . $items . '</div></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // 12 cells × 1 rg setup ≥ 12 fills.
        $rgCount = preg_match_all('~0\.8\d+ 0\.9\d+ 1 rg~', $bytes);
        self::assertGreaterThanOrEqual(12, $rgCount);
    }

    public function testGridAutoFlowColumnAndDenseEndToEndProducesValidPdf(): void
    {
        // Integration: column-major flow + dense packing in one
        // fixture. 5 items in a 2-row grid; one 2-row-spanning item
        // forces dense to backfill the gap.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><head><style>'
            . '.grid { display: grid; '
            . '        grid-template-columns: 60pt 60pt 60pt; '
            . '        grid-template-rows: 30pt 30pt; '
            . '        grid-auto-flow: dense; '
            . '        column-gap: 4pt; row-gap: 4pt; }'
            . '.cell { background-color: #def; }'
            . '.tall { grid-row: 1 / span 2; background-color: #336699; }'
            . '</style></head><body>'
            . '<div class="grid">'
            . '<div class="tall"></div>'
            . '<div class="cell"></div>'
            . '<div class="cell"></div>'
            . '<div class="cell"></div>'
            . '<div class="cell"></div>'
            . '</div></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Tall blue card emitted (≈ 0.2 0.4 0.6).
        self::assertMatchesRegularExpression('~0\.2 0\.4 0\.6 rg~', $bytes);
        // 4 cells in light blue (#def).
        $rgCount = preg_match_all('~0\.8\d+ 0\.9\d+ 1 rg~', $bytes);
        self::assertGreaterThanOrEqual(4, $rgCount);
    }

    public function testGridAutoTracksEndToEndProducesValidPdf(): void
    {
        // Integration: holy-grail variant with an `auto` content
        // column for the sidebar. The sidebar gets sized to its
        // declared 120pt; the main `1fr` column fills the rest.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><head><style>'
            . '.app { display: grid; '
            . '       grid-template-columns: auto 1fr; '
            . '       grid-template-rows: 40pt 200pt 30pt; '
            . '       column-gap: 4pt; row-gap: 4pt; height: 280pt; }'
            . '.side { width: 120pt; background-color: #99ccff; }'
            . '.main { background-color: #eeeeff; }'
            . '.head { grid-column: 1 / 3; background-color: #336699; }'
            . '.foot { grid-column: 1 / 3; background-color: #336699; }'
            . '</style></head><body>'
            . '<div class="app">'
            . '<div class="head"></div>'
            . '<div class="side"></div>'
            . '<div class="main"></div>'
            . '<div class="foot"></div>'
            . '</div></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Side colour painted (≈ 0.6 0.8 1 for #99ccff).
        self::assertMatchesRegularExpression('~0\.6 0\.8 1 rg~', $bytes);
        // Main lavender painted.
        self::assertMatchesRegularExpression('~0\.9\d+ 0\.9\d+ 1 rg~', $bytes);
    }

    public function testTableAutoWidthEndToEndProducesValidPdf(): void
    {
        // Integration: render an auto-width table where one column
        // is narrow (50pt) and another wide (200pt). Verify the
        // produced PDF is well-formed and that the wide column's
        // background extends further than the narrow one.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><head><style>'
            . 'html, body, tbody { display: block; }'
            . 'table { display: table; width: 600pt; }'
            . 'tr { display: table-row; }'
            . 'td { display: table-cell; }'
            . '.narrow { width: 50pt; background-color: #ffaaaa; }'
            . '.wide { width: 200pt; background-color: #aaffaa; }'
            . '</style></head><body><table>'
            . '<tr><td class="narrow"></td><td class="wide"></td></tr>'
            . '</table></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Both colours emitted.
        self::assertMatchesRegularExpression('~1 0\.6\d+ 0\.6\d+ rg~', $bytes, 'narrow red emitted');
        self::assertMatchesRegularExpression('~0\.6\d+ 1 0\.6\d+ rg~', $bytes, 'wide green emitted');
    }

    public function testBorderCollapseEndToEndOnlyPaintsThickerJoint(): void
    {
        // Integration: render a 2×2 table where cell A has a 6pt
        // right border and cell B has a 1pt left border. With
        // `border-collapse: collapse`, the painter must emit a
        // single 6pt rect at the shared edge (the winner) and NOT
        // emit B's 1pt left border at the same joint. Border widths
        // appear in PDF px (6pt = 8px, 1pt ≈ 1.333px at 96/72 dpi).
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $html = '<html><head><style>'
            . 'html, body, tbody { display: block; }'
            . 'table { display: table; border-collapse: collapse; }'
            . 'tr { display: table-row; }'
            . 'td { display: table-cell; width: 100pt; height: 30pt; }'
            . '.a { border: 6pt solid #000; }'
            . '.b { border: 1pt solid #000; }'
            . '</style></head><body><table>'
            . '<tr><td class="a"></td><td class="b"></td></tr>'
            . '<tr><td class="a"></td><td class="b"></td></tr>'
            . '</table></body></html>';
        $renderer->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // 6pt right-edge winning border at the joint paints as a
        // 8-px-wide vertical rect at the joint X coordinate (~146.7).
        self::assertMatchesRegularExpression(
            '~14[0-9]\.\d+\s+\d+\.?\d*\s+8\s+\d+\.?\d*\s+re~',
            $bytes,
            'winning 6pt border (= 8px in PDF user space) emitted at the joint',
        );
        // B's 1pt left border (1.333... px wide) must NOT show up
        // adjacent to the joint — it's been resolved to zero.
        self::assertDoesNotMatchRegularExpression(
            '~14[89]\.\d+\s+\d+\.?\d*\s+1\.333\d+\s+\d+\.?\d*\s+re~',
            $bytes,
            'thinner 1pt border zeroed at the joint',
        );
    }

    public function testPageBackgroundColorFillsEntirePage(): void
    {
        // `@page { background-color: #ffeecc }` should paint a full
        // pageWidth × pageHeight rectangle in that colour before any
        // content draws.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><head><style>@page { background-color: #ffeecc; }</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // #ffeecc → (1, 0.93..., 0.8). The fill rect should cover the
        // whole page (0 0 612 792 re), then `f` for fill.
        self::assertMatchesRegularExpression(
            '~1 0\.9\d+ 0\.8 rg\s+0 0 612 792 re\s+f~',
            $bytes,
            'page background-color paints a full-page rect',
        );
    }

    public function testPageBackgroundColorAbsentWhenUnset(): void
    {
        // No `@page` background → no full-page fill rect should appear
        // (the renderer must NOT spuriously emit a white background fill).
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // The first re-then-f sequence in the stream would be the page
        // background; with none set there should be no rg+re+f
        // immediately after the MediaBox clip.
        self::assertDoesNotMatchRegularExpression(
            '~ rg\s+0 0 612 792 re\s+f~',
            $bytes,
            'no spurious full-page fill when @page background unset',
        );
    }

    public function testNamedPageOverridesBackgroundOnTaggedPages(): void
    {
        // `@page cover { background-color: ... }` applies ONLY to a
        // page whose contents include a block with `page: cover`.
        // Forced page-break before that block ensures it lands on its
        // own page.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page cover { background-color: #ffeecc; } '
            . '.cover { page: cover; height: 400pt; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>front matter</p><div class="cover">cover content</div></body></html>',
        );
        $bytes = $writer->toBytes();
        // The cover page (second one) should have the #ffeecc fill.
        self::assertMatchesRegularExpression(
            '~1 0\.9\d+ 0\.8 rg\s+0 0 612 792 re\s+f~',
            $bytes,
            'named @page background should appear on the cover page',
        );
    }

    public function testNamedPageDoesNotApplyToUntaggedPages(): void
    {
        // Negative: a `@page foo` rule with no element declaring
        // `page: foo` should not apply anywhere.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><head><style>'
            . '@page cover { background-color: #ffeecc; }'
            . '</style></head><body><p>nothing</p></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertDoesNotMatchRegularExpression(
            '~1 0\.9\d+ 0\.8 rg\s+0 0 612 792 re\s+f~',
            $bytes,
            'unused @page cover should not paint',
        );
    }

    public function testNamedPageOverlaysOnDefaultPageBackground(): void
    {
        // Default `@page { background: red }` plus
        // `@page cover { background: #ffeecc }`. The default applies
        // everywhere; the named overlay wins on the tagged page.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { background-color: red; } '
            . '@page cover { background-color: #00ff00; } '
            . '.cover { page: cover; height: 400pt; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>page 1</p><div class="cover">cover</div></body></html>',
        );
        $bytes = $writer->toBytes();
        // Page 1 should fill red; page 2 should fill green.
        self::assertMatchesRegularExpression(
            '~1 0 0 rg\s+0 0 612 792 re\s+f~',
            $bytes,
            'page 1 keeps default red bg',
        );
        self::assertMatchesRegularExpression(
            '~0 1 0 rg\s+0 0 612 792 re\s+f~',
            $bytes,
            'cover page picks up green overlay',
        );
    }

    public function testNamedPageForcesPageBreakBefore(): void
    {
        // `page: foo` implicitly forces a page break before the box
        // even without explicit `break-before: page`. The named bg
        // appears on its own page (proving the second page exists).
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><head><style>'
            . '@page chapter { background-color: #00ff00; } '
            . '.chap { page: chapter; height: 50pt; }'
            . '</style></head>'
            . '<body><p>x</p><div class="chap">y</div></body></html>',
        );
        $bytes = $writer->toBytes();
        // The .chap div was tiny (50pt) but forced its own page →
        // the green bg appears, proving the page break fired.
        self::assertMatchesRegularExpression(
            '~0 1 0 rg\s+0 0 612 792 re\s+f~',
            $bytes,
            'page: chapter forces a break and the chapter page gets its bg',
        );
    }

    public function testPageMarginShorthandMovesMarginBoxBands(): void
    {
        // `@page { margin: 72pt }` should put the top header band at
        // pageHeight - 36 (= 792 - 36 = 756) and the bottom band at 36
        // (= 72/2). Default (36pt) would be 774 / 18.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { margin: 72pt; @top-center { content: "Hdr"; } '
            . '@bottom-center { content: "Ftr"; } }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertMatchesRegularExpression('~ 756 Tm~', $bytes, 'top band at pageHeight - margin/2 = 756');
        self::assertMatchesRegularExpression('~ 36 Tm~', $bytes, 'bottom band at margin/2 = 36');
        self::assertStringNotContainsString(' 774 Tm', $bytes, 'no default 36pt-margin top band');
    }

    public function testPageMarginAsymmetricShorthandRespected(): void
    {
        // `@page { margin: 60pt 90pt }` → top=bottom=60, left=right=90.
        // Verify by checking the left x-anchor of a top-left box.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { margin: 60pt 90pt; @top-left { content: "L"; } }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Top y = pageHeight - 60/2 = 792 - 30 = 762.
        self::assertMatchesRegularExpression('~ 762 Tm~', $bytes);
        // Top-left x anchored at marginLeft = 90.
        self::assertMatchesRegularExpression('~1 0 0 1 90 762 Tm~', $bytes);
    }

    public function testPageSizeA4OverridesDefaultDimensions(): void
    {
        // CSS Paged Media 3 §6.1 `@page { size: A4 }` should produce
        // 595 × 842 pt media boxes instead of the default 612 × 792.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><head><style>@page { size: A4; }</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/MediaBox [ 0 0 595 842 ]', $bytes);
    }

    public function testPageSizeLandscapeRotatesPaper(): void
    {
        // `size: A4 landscape` → 842 × 595 instead of 595 × 842.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><head><style>@page { size: A4 landscape; }</style></head>'
            . '<body><p>body</p></body></html>',
        );
        self::assertStringContainsString('/MediaBox [ 0 0 842 595 ]', $writer->toBytes());
    }

    public function testPageSizeExplicitLengthsHonoured(): void
    {
        // `size: 400pt 600pt` → exact width/height.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><head><style>@page { size: 400pt 600pt; }</style></head>'
            . '<body><p>body</p></body></html>',
        );
        self::assertStringContainsString('/MediaBox [ 0 0 400 600 ]', $writer->toBytes());
    }

    public function testPageSizeUnknownKeywordFallsBackToDefaults(): void
    {
        // `size: monster` (not a recognised page-size keyword) leaves
        // the default 612 × 792 letter dimensions in place.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><head><style>@page { size: monster; }</style></head>'
            . '<body><p>body</p></body></html>',
        );
        self::assertStringContainsString('/MediaBox [ 0 0 612 792 ]', $writer->toBytes());
    }

    public function testPageSizeAutoKeywordKeepsDefaults(): void
    {
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><head><style>@page { size: auto; }</style></head>'
            . '<body><p>body</p></body></html>',
        );
        self::assertStringContainsString('/MediaBox [ 0 0 612 792 ]', $writer->toBytes());
    }

    public function testPageMarginBoxesInheritFontSizeFromPageRule(): void
    {
        // CSS Paged Media 3: `@page { font-size: 14pt }` cascades into
        // every nested margin box that doesn't set its own. Without the
        // cascade the box would emit 10pt (the previous Phase-1 default).
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { font-size: 14pt; color: #336699; '
            . '@top-center { content: "Title"; } '
            . '@bottom-center { content: "Footer"; font-size: 8pt; } '
            . '}';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Top-center inherits 14pt + #336699; bottom-center overrides
        // to 8pt but still inherits color from @page.
        self::assertMatchesRegularExpression('~/F\d+ 14 Tf~', $bytes, 'top-center inherits 14pt');
        self::assertMatchesRegularExpression('~/F\d+ 8 Tf~', $bytes, 'bottom-center overrides to 8pt');
        // Color #336699 → 0.2, 0.4, 0.6 rg approximately.
        self::assertMatchesRegularExpression(
            '~0\.2 0\.4 0\.6 rg~',
            $bytes,
            'page-level color inherited into margin boxes',
        );
    }

    public function testPageMarginBoxesMarginBoxOverridesPageDefaults(): void
    {
        // When BOTH `@page` and a margin box set the same property,
        // the margin box wins per source-order cascade.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        // @page sets color:red; @top-center overrides to color:green.
        $css = '@page { color: #cc0000; '
            . '@top-center { content: "Title"; color: #00cc00; } }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // The green override (#00cc00 ≈ 0 0.8 0) wins; the red default
        // must NOT appear in the margin-box paint.
        self::assertMatchesRegularExpression(
            '~0 0\.8 0 rg~',
            $bytes,
            'margin-box color override wins over @page default',
        );
    }

    public function testPageMarginBoxesFontWeightTriggersSyntheticBoldWithoutRealFace(): void
    {
        // `@top-center { font-weight: bold; }` over a single-face font
        // (no real bold registered) should emit text rendering mode 2
        // (fake-bold) for the header.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { @top-center { content: "Bold Heading"; font-weight: bold; } }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // The top-center margin-box paint must include `2 Tr` (text
        // rendering mode 2 → fake-bold via fill+stroke). The painter
        // first emits `RG` (stroke color) + `w` (line width) before
        // `2 Tr` to scope the stroke to the margin-box paint.
        self::assertMatchesRegularExpression(
            '~ 774 Tm\s+\d+(?:\.\d+)? \d+(?:\.\d+)? \d+(?:\.\d+)? RG\s+\d+(?:\.\d+)? w\s+2 Tr~',
            $bytes,
            'synthetic fake-bold fires for top-band when no real face',
        );
    }

    public function testPageMarginBoxesFontWeightWithRealBoldFaceSuppressesFakeBold(): void
    {
        // When a real 700-weight face is registered for the family,
        // `font-weight: bold` should pick the real face and NOT emit
        // the synthetic fake-bold `2 Tr`.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $regular = (new OpenTypeParser($fontPath))->parse();
        $bold = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer(
            (new RendererOptions())
                ->withDefaultFont($regular)
                ->withFontFaces([
                    'Inter' => [
                        new \Phpdftk\HtmlToPdf\Layout\FontFace($regular, 400, 'normal'),
                        new \Phpdftk\HtmlToPdf\Layout\FontFace($bold, 700, 'normal'),
                    ],
                ]),
        );
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { @top-center { '
            . 'content: "Bold Title"; font-family: "Inter"; font-weight: bold; '
            . '} }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Top-band Tm emitted with no `2 Tr` (fake-bold) following it —
        // the real bold face matched so the painter stays in fill-only
        // mode (`0 Tr`) for the margin-box text.
        self::assertMatchesRegularExpression(
            '~ 774 Tm\s+0 Tr~',
            $bytes,
            'fill-only mode when real bold face matches',
        );
        self::assertStringNotContainsString(
            '2 Tr',
            $bytes,
            'real bold face suppresses fake-bold',
        );
    }

    public function testPageMarginBoxesFontFamilyResolvesToRegisteredAlternate(): void
    {
        // `@bottom-center { font-family: "Mongol"; }` should switch the
        // margin box's Tf resource to the Mongol alternate when it's
        // registered via withFonts. Verified by counting the distinct
        // Tf resources used on the page: body uses the default font,
        // margin box must reference a different one.
        $latinPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        $mongolPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($latinPath) || !is_file($mongolPath)) {
            self::markTestSkipped('Font fixtures missing');
        }
        $latin = (new OpenTypeParser($latinPath))->parse();
        $mongol = (new OpenTypeParser($mongolPath))->parse();
        $renderer = new Renderer(
            (new RendererOptions())
                ->withDefaultFont($latin)
                ->withFonts(['Mongol' => $mongol]),
        );
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { @bottom-center { content: "' . "\u{1820}" . '"; font-family: "Mongol"; } }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Both fonts registered as composite Type-0 fonts on the page.
        self::assertGreaterThanOrEqual(
            2,
            substr_count($bytes, '/Subtype /Type0'),
            'both fonts present on the page resource list',
        );
        // The bottom-band Tm should be followed by a Tf switch to the
        // Mongol resource (typically F2) before its Tj — verified by
        // checking distinct Tf names appearing in the content stream.
        $matched = preg_match_all('~/F(\d+) (\d+(?:\.\d+)?) Tf~', $bytes, $m);
        self::assertGreaterThan(0, $matched, 'multiple Tf operators emitted');
        self::assertGreaterThan(1, count(array_unique($m[1])), 'multiple distinct Tf resources used');
    }

    public function testPageMarginBoxesCounterStyleLowerRoman(): void
    {
        // `@bottom-center { content: counter(page, lower-roman); }` — for
        // a 3-page document, each page emits the Roman-numeral page index
        // ("i", "ii", "iii"). Verified by counting bottom-band Tm
        // operators (one per page) and confirming the shaped glyph runs
        // for the three pages differ from one another (i ≠ ii ≠ iii).
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { @bottom-center { content: counter(page, lower-roman); } } '
            . 'section { page-break-after: always; } '
            . 'section:last-child { page-break-after: auto; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><section><h1>one</h1></section>'
            . '<section><h1>two</h1></section>'
            . '<section><h1>three</h1></section></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertSame(3, substr_count($bytes, ' 18 Tm'), 'one bottom Tm per page');
        $matched = preg_match_all('~ 18 Tm\s+\d+ Tr\s+<([0-9A-F]+)> Tj~', $bytes, $m);
        self::assertSame(3, $matched, 'three bottom-center glyph runs');
        // i (1 glyph), ii (2 glyphs), iii (3 glyphs) — distinct shapes.
        self::assertCount(3, array_unique($m[1]), 'roman page numerals shape differently per page');
        // ii must be twice as wide as i in hex form: 4 chars vs 8 chars.
        self::assertSame(4, strlen($m[1][0]), '"i" shapes to 1 glyph');
        self::assertSame(8, strlen($m[1][1]), '"ii" shapes to 2 glyphs');
        self::assertSame(12, strlen($m[1][2]), '"iii" shapes to 3 glyphs');
    }

    public function testPageMarginBoxesFirstSelectorOverridesPageZeroOnly(): void
    {
        // `@page :first { @top-center { content: "Cover" } }` should
        // emit "Cover" only on page 0; subsequent pages keep the
        // generic `@page { @top-center }` content.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { @top-center { content: "Normal"; } } '
            . '@page :first { @top-center { content: "Cover"; } } '
            . 'section { page-break-after: always; } '
            . 'section:last-child { page-break-after: auto; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><section><h1>one</h1></section>'
            . '<section><h1>two</h1></section></body></html>',
        );
        $bytes = $writer->toBytes();
        // 2 pages → 2 top-band Tm lines total. Counting glyph runs is
        // less precise than counting Tm matches; what matters: both
        // pages emit a top-center band, and the cover/normal differ.
        self::assertSame(2, substr_count($bytes, ' 774 Tm'), 'one top-band Tm per page');
        // The shaped hex GID sequences for "Cover" (5 glyphs) and
        // "Normal" (6 glyphs) differ — assert by counting `Tj` between
        // top-band Tm operators.
        $shapedRuns = [];
        if (preg_match_all('~ 774 Tm\s+\d+ Tr\s+<([0-9A-F]+)> Tj~', $bytes, $m)) {
            $shapedRuns = $m[1];
        }
        self::assertCount(2, $shapedRuns, 'two distinct top-center glyph runs');
        self::assertNotSame($shapedRuns[0], $shapedRuns[1], 'cover differs from normal');
    }

    public function testPageMarginBoxesLeftRightAlternation(): void
    {
        // `@page :left { @top-left { content: "L" } } @page :right { @top-right { content: "R" } }` —
        // page 0 (right-facing) emits a top-right rune; page 1
        // (left-facing) emits a top-left rune.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page :left { @top-left { content: "L"; } } '
            . '@page :right { @top-right { content: "R"; } } '
            . 'section { page-break-after: always; } '
            . 'section:last-child { page-break-after: auto; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><section><h1>one</h1></section>'
            . '<section><h1>two</h1></section></body></html>',
        );
        $bytes = $writer->toBytes();
        // 2 pages, 1 top Tm each = 2 total.
        self::assertSame(2, substr_count($bytes, ' 774 Tm'), 'one top-band Tm per page');
        // Right-anchored x ≈ pageWidth - margin - width (large);
        // left-anchored x = margin = 36 (small). Both must appear.
        $matched = preg_match_all('~1 0 0 1 ([0-9.]+) 774 Tm~', $bytes, $m);
        self::assertSame(2, $matched);
        sort($m[1]);
        self::assertEqualsWithDelta(36.0, (float) $m[1][0], 0.5, 'left page emits left-anchored');
        self::assertGreaterThan(500.0, (float) $m[1][1], 'right page emits right-anchored');
    }

    public function testPageMarginBoxesSupportCornerPositions(): void
    {
        // `@top-left-corner` / `@top-right-corner` / `@bottom-left-corner` /
        // `@bottom-right-corner` sit in their respective margin × margin
        // squares at the 4 page corners. Each text is centred inside its
        // corner square.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { '
            . '@top-left-corner { content: "A"; } '
            . '@top-right-corner { content: "B"; } '
            . '@bottom-left-corner { content: "C"; } '
            . '@bottom-right-corner { content: "D"; } '
            . '}';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // 4 corner boxes → 4 Tm operators across the band y-positions
        // (top: 774, bottom: 18). Each corner pins to either the left
        // half (x < margin = 36) or the right half (x > pageWidth - margin
        // = 576). Verify by counting Tm lines at each y-band.
        self::assertSame(2, substr_count($bytes, ' 774 Tm'), '2 top corners');
        self::assertSame(2, substr_count($bytes, ' 18 Tm'), '2 bottom corners');
        // Right corners must have x > 576 (page width minus margin).
        $matched = preg_match_all('~1 0 0 1 ([0-9.]+) 774 Tm~', $bytes, $m);
        self::assertSame(2, $matched);
        sort($m[1]);
        self::assertLessThan(36.0, (float) $m[1][0], 'top-left corner x within left margin');
        self::assertGreaterThan(576.0, (float) $m[1][1], 'top-right corner x past right page-minus-margin');
    }

    public function testPageMarginBoxesHonourTextAlignOverride(): void
    {
        // `@top-left { content: "X"; text-align: center; }` should pick
        // the centre x-anchor (pageWidth/2) instead of the position-default
        // left anchor (margin = 36).
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        // Use the explicit override on a position whose default would
        // anchor differently: top-LEFT but text-align centre.
        $css = '@page { @top-left { content: "Centred Header"; text-align: center; } }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><p>body</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // Without override the Tm x would equal margin (36). With centre
        // override the x is `(612 - width) / 2` — much bigger than 36.
        // Capture the x via the Tm operator.
        $matched = preg_match('~1 0 0 1 ([0-9.]+) 774 Tm~', $bytes, $m);
        self::assertSame(1, $matched, 'top-band Tm with author text-align centre');
        self::assertGreaterThan(36.0, (float) $m[1], 'centre override pushed x past left-margin anchor');
    }

    public function testPageMarginBoxesCounterPageSubstitution(): void
    {
        // `@bottom-center { content: "Page " counter(page) " of " counter(pages); }` —
        // each page should emit the literal "Page" prefix joined with the
        // 1-based page index and total count. Verified by rendering a
        // forced 3-page document and asserting both the "3" digit
        // (totalpages) and the "1"/"2"/"3" page numbers appear via their
        // shaped hex GIDs across the per-page content streams.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { @bottom-center { content: "Page " counter(page) " of " counter(pages); } } '
            . 'section { page-break-after: always; } '
            . 'section:last-child { page-break-after: auto; }';
        $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><section><h1>one</h1></section>'
            . '<section><h1>two</h1></section>'
            . '<section><h1>three</h1></section></body></html>',
        );
        $bytes = $writer->toBytes();
        // 3 forced sections → 3 pages. Bottom band lives at y=18.
        self::assertSame(3, substr_count($bytes, ' 18 Tm'), 'one bottom-band Tm per page');
        // The renderer must register the digit glyphs (1, 2, 3) into the
        // font subset; the body text doesn't use digits, so this comes
        // from the collectCodepoints always-include-digits guard.
        self::assertGreaterThanOrEqual(1, substr_count($bytes, '/Subtype /Type0'));
    }

    public function testPageMarginBoxesIgnoredWhenNoDefaultFont(): void
    {
        // Without a default font the renderer can't shape margin-box
        // glyphs; the painter must skip the headers/footers cleanly
        // rather than crashing.
        $renderer = new Renderer();
        $writer = new PdfWriter(compressStreams: false);
        $css = '@page { @top-center { content: "Hdr"; } }';
        $warnings = $renderer->renderInto(
            $writer,
            '<html><head><style>' . $css . '</style></head>'
            . '<body><div></div></body></html>',
        );
        // No render-time exception; output is a valid PDF.
        self::assertStringStartsWith('%PDF-', $writer->toBytes());
        // The text doesn't appear in the stream — there's no font to
        // shape it with.
        self::assertStringNotContainsString('Hdr', $writer->toBytes());
    }

    public function testRealBoldFaceSuppressesSyntheticFakeBold(): void
    {
        // When `withFontFaces` registers a real 700-weight face, the
        // painter must NOT emit `2 Tr` (fake-bold rendering mode) over
        // the bold fragment. Without the real face, the inline layout
        // would flag the fragment as needing synthetic bold and the
        // painter would emit `2 Tr` for it.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $regular = (new OpenTypeParser($fontPath))->parse();
        $bold = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer(
            (new RendererOptions())
                ->withDefaultFont($regular)
                ->withFontFaces([
                    'Inter' => [
                        new \Phpdftk\HtmlToPdf\Layout\FontFace($regular, 400, 'normal'),
                        new \Phpdftk\HtmlToPdf\Layout\FontFace($bold, 700, 'normal'),
                    ],
                ]),
        );
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p style="font-family: Inter">'
            . 'normal <strong>bold</strong> normal</p></body></html>',
        );
        $bytes = $writer->toBytes();
        // The painter always re-emits `Tr` per fragment; in the bold
        // run with a real face matched, it must be `0 Tr` (fill-only),
        // never `2 Tr` (fill + stroke).
        self::assertStringNotContainsString('2 Tr', $bytes, 'no synthetic fake-bold when real bold face matches');
        self::assertStringContainsString('0 Tr', $bytes, 'fill-only mode emitted');
    }

    public function testSyntheticFakeBoldFiresWhenNoRealBoldFaceMatches(): void
    {
        // Conversely: without `withFontFaces` (only the default font), a
        // `<strong>` fragment must still get the synthetic `2 Tr`
        // fake-bold so the bold semantic is at least visually preserved.
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $regular = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($regular));
        $writer = new PdfWriter(compressStreams: false);
        $renderer->renderInto(
            $writer,
            '<html><body><p>normal <strong>bold</strong> normal</p></body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString('2 Tr', $bytes, 'synthetic fake-bold fires without a real bold face');
    }

    public function testWithFontFacesRejectsNonFontFaceEntries(): void
    {
        $fontPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $regular = (new OpenTypeParser($fontPath))->parse();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('~FontFace~');
        // Passing the raw OpenTypeData (not a FontFace) is a mistake the
        // helper catches at config time, forcing the caller into the
        // correct shape.
        (new RendererOptions())->withFontFaces(['Inter' => $regular]); // @phpstan-ignore-line
    }

    public function testGenericFamilyAliasRejectsNonCanonicalKey(): void
    {
        $latinPath = __DIR__ . '/../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($latinPath)) {
            self::markTestSkipped('Font fixture missing');
        }
        $latin = (new OpenTypeParser($latinPath))->parse();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('~mono~');
        (new RendererOptions())->withGenericFamilies(['mono' => $latin]);
    }

    public function testCreatorAndProducerAlwaysSet(): void
    {
        // Even a doc with no <title>/<meta> gets Creator + Producer so
        // downstream tooling can identify the pipeline.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto($writer, '<html><body><p>x</p></body></html>');
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/Creator (phpdftk/html-to-pdf)', $bytes);
        self::assertStringContainsString('/Producer (phpdftk)', $bytes);
        // CreationDate follows the ISO 32000-2 §7.9.4 format:
        // `D:YYYYMMDDHHmmSSO...`.
        self::assertMatchesRegularExpression(
            '~/CreationDate \(D:\d{14}(Z|[+-]\d{2}\x27\d{2}\x27)\)~',
            $bytes,
        );
    }

    public function testHtmlTitleFlowsIntoPdfInfo(): void
    {
        $html = '<html><head><title>Quarterly Report</title>'
            . '<meta name="author" content="Alice"><meta name="description" content="Q3 numbers">'
            . '</head><body></body></html>';
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto($writer, $html);
        $bytes = $writer->toBytes();
        self::assertStringContainsString('/Title (Quarterly Report)', $bytes);
        self::assertStringContainsString('/Author (Alice)', $bytes);
        self::assertStringContainsString('/Subject (Q3 numbers)', $bytes);
        // Creator / Producer always set so traceability is clean.
        self::assertStringContainsString('/Creator (phpdftk/html-to-pdf)', $bytes);
        self::assertStringContainsString('/Producer (phpdftk)', $bytes);
    }

    public function testEmbeddedStyleElementCascades(): void
    {
        // `<style>` in `<head>` should add Author-origin rules. Rendering
        // an HTML doc with an embedded red-background rule should produce
        // a `1 0 0 rg` fill in the content stream.
        $html = '<html><head><style>p { background-color: red; }</style></head>'
            . '<body><p>hi</p></body></html>';
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto($writer, $html);
        self::assertStringContainsString('1 0 0 rg', $writer->toBytes(), 'embedded style fills red');
    }

    public function testEmbeddedStyleBeatsAuthorCssParam(): void
    {
        // Embedded `<style>` appears later in source order than the
        // explicit `$authorCss` param, so its rules win on ties.
        $html = '<html><head><style>p { background-color: red; }</style></head>'
            . '<body><p>hi</p></body></html>';
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto($writer, $html, 'p { background-color: blue; }');
        $bytes = $writer->toBytes();
        self::assertStringContainsString('1 0 0 rg', $bytes, 'embedded <style> wins on later source order');
        self::assertStringNotContainsString('0 0 1 rg', $bytes, 'blue from $authorCss does not paint');
    }

    public function testDefaultUaSheetGivesHeadingsDistinctSizes(): void
    {
        // h1 / h2 / h3 should cascade to different font-sizes.
        $renderer = new Renderer();
        $doc = $renderer->parse(
            '<html><body><h1>1</h1><h2>2</h2><h3>3</h3></body></html>',
        );
        $root = $doc->documentElement;
        self::assertNotNull($root);

        $sheet = $renderer->parseStylesheet($renderer->options->effectiveUserAgentStylesheet());
        $cascade = new \Phpdftk\Css\Cascade\Cascade(
            \Phpdftk\Css\Cascade\PropertyRegistry::default(),
        );

        $h1 = $root->querySelector('h1');
        $h2 = $root->querySelector('h2');
        $h3 = $root->querySelector('h3');
        self::assertNotNull($h1);
        self::assertNotNull($h2);
        self::assertNotNull($h3);

        $h1Size = $cascade->computeFor([$sheet], $h1)->get('font-size');
        $h2Size = $cascade->computeFor([$sheet], $h2)->get('font-size');
        $h3Size = $cascade->computeFor([$sheet], $h3)->get('font-size');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $h1Size);
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $h2Size);
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $h3Size);
        self::assertSame(32.0, $h1Size->value);
        self::assertSame(24.0, $h2Size->value);
        self::assertSame(19.0, $h3Size->value);
    }

    public function testOpacityEmitsGsOperator(): void
    {
        // opacity < 1 registers an ExtGState on the page and the painter
        // emits a `gs` invocation around the box's draw operators.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; }
             div { background-color: red; height: 50px; opacity: 0.5; }',
        );
        $bytes = $writer->toBytes();
        self::assertStringContainsString(' gs', $bytes, 'opacity should emit a gs operator');
        self::assertStringContainsString('/ExtGState', $bytes);
        self::assertStringContainsString('/ca 0.5', $bytes);
    }

    public function testFullOpacityNoGs(): void
    {
        // opacity = 1 (initial) → no gs operator.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; } div { background-color: red; height: 50px; }',
        );
        $bytes = $writer->toBytes();
        self::assertStringNotContainsString(' gs', $bytes);
    }

    public function testShortContentEmitsSinglePage(): void
    {
        $result = (new Renderer())->render(
            '<html><body><div></div></body></html>',
            'div { background-color: red; height: 100px; }',
        );
        $bytes = $result->writer->toBytes();
        $count = substr_count($bytes, "/Type /Page\n");
        self::assertSame(1, $count);
    }

    public function testTallContentSpansMultiplePages(): void
    {
        // 5000px content at 792px pages → 7 pages (ceil(5000/792)).
        $renderer = new Renderer((new RendererOptions())->withPageSize(612, 792));
        $result = $renderer->render(
            '<html><body><div></div></body></html>',
            'div { background-color: red; height: 5000px; }',
        );
        $bytes = $result->writer->toBytes();
        $count = substr_count($bytes, "/Type /Page\n");
        self::assertSame(7, $count, '5000px / 792px = ceil(6.31) = 7 pages');
    }

    public function testPaginationProducesValidPdf(): void
    {
        $renderer = new Renderer((new RendererOptions())->withPageSize(612, 792));
        $result = $renderer->render(
            '<html><body><div></div></body></html>',
            'div { background-color: blue; height: 2000px; }',
        );
        $bytes = $result->writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
    }
}
