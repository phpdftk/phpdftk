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
