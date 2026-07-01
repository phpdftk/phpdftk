<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\HtmlToPdf\Box\AnonymousBlockBox;
use Phpdftk\HtmlToPdf\Box\AtomicInlineBox;
use Phpdftk\HtmlToPdf\Box\BlockBox;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\Box\InlineBox;
use Phpdftk\HtmlToPdf\Box\TextBox;
use Phpdftk\Html\Parser as HtmlParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Phase-1E box generator. Each scenario parses a tiny HTML
 * snippet + a UA-style sheet that nails down the `display` values, runs
 * the generator, and asserts the resulting box tree.
 *
 * The default `PropertyRegistry` makes every element `display: inline`, so
 * the UA sheet supplied here is the minimum needed to get realistic block
 * structure — exactly what the html-to-pdf renderer will ship in its own
 * built-in UA stylesheet later in Phase 1.
 */
final class BoxGeneratorTest extends TestCase
{
    private BoxGenerator $generator;
    private CssParser $css;
    private HtmlParser $html;

    protected function setUp(): void
    {
        $this->css = new CssParser();
        $this->html = new HtmlParser();
        $this->generator = new BoxGenerator(new Cascade(PropertyRegistry::default()));
    }

    /** Bake-in UA defaults for the test scenarios. */
    private function uaSheet(): \Phpdftk\Css\Sheet\Stylesheet
    {
        return $this->css->parseStylesheet(<<<CSS
            html, body, div, p, section, article, h1, h2, ul, li {
                display: block;
            }
            span, a, em, strong {
                display: inline;
            }
            img {
                display: inline-block;
            }
        CSS, Origin::UserAgent);
    }

    public function testGeneratesBlockForBodyAndDiv(): void
    {
        $doc = $this->html->parseDocument('<html><body><div></div></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet()]);
        self::assertInstanceOf(BlockBox::class, $box, 'html → block');
        $body = $this->findFirstByTag($box, 'body');
        self::assertInstanceOf(BlockBox::class, $body, 'body → block');
        $div = $this->findFirstByTag($box, 'div');
        self::assertInstanceOf(BlockBox::class, $div, 'div → block');
    }

    public function testInlineElementProducesInlineBox(): void
    {
        $doc = $this->html->parseDocument('<html><body><p><span>hi</span></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet()]);
        self::assertNotNull($box);
        // Find the <p> box.
        $p = $this->findFirstByTag($box, 'p');
        self::assertInstanceOf(BlockBox::class, $p);
        self::assertCount(1, $p->children);
        $span = $p->children[0];
        self::assertInstanceOf(InlineBox::class, $span);
        self::assertCount(1, $span->children);
        $text = $span->children[0];
        self::assertInstanceOf(TextBox::class, $text);
        self::assertSame('hi', $text->text);
    }

    public function testDisplayNoneSkipsSubtree(): void
    {
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, div, p { display: block; }
            .hidden { display: none; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><div class="hidden"><p>gone</p></div><p>shown</p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        // The hidden div should not appear; the second <p> should.
        $hidden = $this->findFirstByClass($box, 'hidden');
        self::assertNull($hidden, 'display:none element is omitted');
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
    }

    public function testDisplayContentsFlattensChildrenIntoParent(): void
    {
        // CSS Display 3 §3.2 — the element styled display:contents
        // generates no box of its own; its children become direct
        // children of this element's parent in the box tree.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, div, p { display: block; }
            .contents { display: contents; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><div class="contents"><p>inner</p></div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        // The display:contents div should NOT appear in the tree;
        // the <p> should be a direct child of <body>.
        $contentsDiv = $this->findFirstByClass($box, 'contents');
        self::assertNull($contentsDiv, 'display:contents element produces no box');
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        $p = null;
        foreach ($body->children as $child) {
            if ($child->element !== null && $child->element->localName === 'p') {
                $p = $child;
                break;
            }
        }
        self::assertNotNull($p, '<p> is a direct child of <body>, not wrapped by the contents div');
    }

    public function testDisplayContentsOnRootBlockifies(): void
    {
        // CSS Display 3 §3.2.1 — `display: contents` on the root
        // element is treated as `block` so the root still generates
        // a box and its background can still propagate to the canvas.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html { display: contents; }
            body { display: block; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p>x</p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box, 'root display:contents blockifies to block — root box still exists');
        self::assertSame('html', $box->element?->localName);
    }

    public function testContentVisibilityHiddenSkipsSubtree(): void
    {
        // CSS Containment 2 §4 — `content-visibility: hidden` is
        // equivalent to display: none for static print.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, div, p { display: block; }
            .cv-hidden { content-visibility: hidden; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><div class="cv-hidden"><p>gone</p></div><p>shown</p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $hidden = $this->findFirstByClass($box, 'cv-hidden');
        self::assertNull($hidden, 'content-visibility:hidden element is omitted');
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
    }

    public function testImgPresentationalAttributesSetWidth(): void
    {
        // <img width="120" height="60"> should set the cascade's width/height.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            img { display: inline-block; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p><img src="x.png" width="120" height="60"></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        $widthValue = $img->style->get('width');
        $heightValue = $img->style->get('height');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $widthValue);
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $heightValue);
        self::assertSame(120.0, $widthValue->value);
        self::assertSame(60.0, $heightValue->value);
    }

    public function testImgPercentageWidthAttributeMapsToPercentage(): void
    {
        // <img width="100%"> maps to a CSS percentage (browsers' legacy
        // HTML dimension handling), not intrinsic size or a px length. The
        // explicit px height stays a Length and isn't overridden.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            img { display: inline-block; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p><img src="x.png" width="100%" height="15"></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        $widthValue = $img->style->get('width');
        self::assertInstanceOf(\Phpdftk\Css\Value\Percentage::class, $widthValue);
        self::assertSame(100.0, $widthValue->value);
        $heightValue = $img->style->get('height');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $heightValue);
        self::assertSame(15.0, $heightValue->value);
    }

    public function testImgAttributesDoNotOverrideAuthorCss(): void
    {
        // Author CSS wins over presentational attributes — author width
        // declaration overrides the attribute.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            img { display: inline-block; width: 80px; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p><img src="x.png" width="120"></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        $widthValue = $img->style->get('width');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $widthValue);
        self::assertSame(80.0, $widthValue->value, 'author CSS overrides HTML attribute');
    }

    public function testAtomicInlineForInlineBlock(): void
    {
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            img { display: inline-block; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p><img></p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        self::assertInstanceOf(AtomicInlineBox::class, $p->children[0]);
    }

    public function testAnonymousBlockWrapsInlineRunsAlongsideBlock(): void
    {
        // <body> has a text node + an <h1> + another text node. The text
        // nodes get wrapped in anonymous block boxes around the <h1>.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, h1 { display: block; }
        CSS);
        $doc = $this->html->parseDocument('<html><body>hello<h1>title</h1>world</body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        // Expect 3 children: AnonymousBlockBox(hello), BlockBox(h1), AnonymousBlockBox(world)
        self::assertCount(3, $body->children);
        self::assertInstanceOf(AnonymousBlockBox::class, $body->children[0]);
        self::assertInstanceOf(BlockBox::class, $body->children[1]);
        self::assertInstanceOf(AnonymousBlockBox::class, $body->children[2]);
        // The anonymous boxes carry the text.
        $firstText = $body->children[0]->children[0];
        self::assertInstanceOf(TextBox::class, $firstText);
        self::assertSame('hello', $firstText->text);
    }

    public function testPureInlineParentSkipsAnonymousWrapping(): void
    {
        // <p> with only inline children should NOT generate anonymous block
        // boxes — its IFC is already homogeneous.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            span { display: inline; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p>hello <span>world</span>!</p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        foreach ($p->children as $c) {
            self::assertNotInstanceOf(
                AnonymousBlockBox::class,
                $c,
                'no anonymous wrapping when parent is pure-inline',
            );
        }
    }

    public function testBeforePseudoInjectsContent(): void
    {
        // `p::before { content: '!' }` should prepend an inline box with a
        // synthetic TextBox carrying `!`.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            p::before { content: '!'; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p>X</p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        // The pseudo is prepended, so children = [pseudoInline, TextBox(X)].
        self::assertCount(2, $p->children);
        $pseudo = $p->children[0];
        self::assertInstanceOf(InlineBox::class, $pseudo);
        self::assertCount(1, $pseudo->children);
        $text = $pseudo->children[0];
        self::assertInstanceOf(TextBox::class, $text);
        self::assertSame('!', $text->text);
    }

    public function testAfterPseudoAppendsContent(): void
    {
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            p::after { content: '?'; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p>X</p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        // Pseudo appended → children = [TextBox(X), pseudoInline].
        self::assertCount(2, $p->children);
        $pseudo = $p->children[1];
        self::assertInstanceOf(InlineBox::class, $pseudo);
        $text = $pseudo->children[0];
        self::assertInstanceOf(TextBox::class, $text);
        self::assertSame('?', $text->text);
    }

    public function testPseudoAttrReadsHostAttribute(): void
    {
        // `a[href]::after { content: ' (' attr(href) ')' }` — print-style
        // disclosure of link targets, common in print stylesheets.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            a { display: inline; }
            a::after { content: ' (' attr(href) ')'; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p><a href="https://example.com">link</a></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $a = $this->findFirstByTag($box, 'a');
        self::assertNotNull($a);
        // Children: [TextBox(link), pseudoInline]
        self::assertCount(2, $a->children);
        $pseudo = $a->children[1];
        self::assertInstanceOf(InlineBox::class, $pseudo);
        $text = $pseudo->children[0];
        self::assertInstanceOf(TextBox::class, $text);
        self::assertSame(' (https://example.com)', $text->text);
    }

    public function testImgWithAltRendersAsInlineFallback(): void
    {
        // Until image painting lands in Phase 1L, `<img alt="...">` should
        // surface the alt text as an inline fallback so layout can show
        // something for missing images.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            img { display: inline-block; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p>before <img src="x.png" alt="Logo"></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertInstanceOf(InlineBox::class, $img, 'alt-bearing <img> is inline (not atomic)');
        self::assertCount(1, $img->children);
        $text = $img->children[0];
        self::assertInstanceOf(TextBox::class, $text);
        self::assertSame('Logo', $text->text);
    }

    public function testImgWithoutDimensionsFallsBackToNatural(): void
    {
        // 4x4 PNG with no width/height attribute or CSS — natural size
        // should be picked up via ImageParser::parseString.
        $pngBase64 = base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            img { display: inline-block; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p><img src="data:image/png;base64,' . $pngBase64 . '"></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        $w = $img->style->get('width');
        $h = $img->style->get('height');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $w);
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $h);
        self::assertSame(4.0, $w->value, 'natural width from PNG');
        self::assertSame(4.0, $h->value, 'natural height from PNG');
    }

    public function testImgWidthOnlyDerivesProportionalHeight(): void
    {
        // The fixture PNG is 4×4 (square). With `width="40"` and no height,
        // the cascade should compute height = 40 × (4/4) = 40.
        $pngBase64 = base64_encode(hex2bin(
            '89504E470D0A1A0A0000000D49484452000000040000000408060000'
            . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
            . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
            . '49454E44AE426082',
        ));
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            img { display: inline-block; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p><img src="data:image/png;base64,' . $pngBase64 . '" width="40"></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $img = $this->findFirstByTag($box, 'img');
        $h = $img->style->get('height');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $h);
        self::assertSame(40.0, $h->value, 'height proportional to width on square image');
    }

    public function testWbrLowersToZeroWidthSpace(): void
    {
        // `<wbr>` should produce an InlineBox carrying a single
        // U+200B TextBox so UAX #14 has a soft-break opportunity even
        // when surrounding text doesn't.
        $sheet = $this->css->parseStylesheet('html, body, p { display: block; }');
        $doc = $this->html->parseDocument(
            '<html><body><p>foo<wbr>bar</p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $wbr = $this->findFirstByTag($box, 'wbr');
        self::assertNotNull($wbr);
        self::assertInstanceOf(InlineBox::class, $wbr);
        self::assertCount(1, $wbr->children);
        $text = $wbr->children[0];
        self::assertInstanceOf(TextBox::class, $text);
        self::assertSame("\u{200B}", $text->text);
    }

    public function testInputTextRendersValueAsInline(): void
    {
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            input { display: inline-block; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p>name: <input type="text" value="Alice"></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        self::assertInstanceOf(InlineBox::class, $input);
        self::assertCount(1, $input->children);
        $text = $input->children[0];
        self::assertInstanceOf(TextBox::class, $text);
        self::assertSame('Alice', $text->text);
    }

    public function testInputWithoutValueRendersEmpty(): void
    {
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p><input type="text"></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        self::assertInstanceOf(InlineBox::class, $input);
        self::assertCount(0, $input->children, 'empty value → no TextBox child');
    }

    public function testInputHiddenIsOmitted(): void
    {
        $sheet = $this->css->parseStylesheet('html, body, p { display: block; }');
        $doc = $this->html->parseDocument(
            '<html><body><p><input type="hidden" name="csrf" value="abc">visible</p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNull($this->findFirstByTag($box, 'input'));
    }

    public function testInputPasswordRendersBullets(): void
    {
        $sheet = $this->css->parseStylesheet('html, body, p { display: block; }');
        $doc = $this->html->parseDocument(
            '<html><body><p><input type="password" value="hello"></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        $text = $input->children[0] ?? null;
        self::assertInstanceOf(TextBox::class, $text);
        self::assertSame(str_repeat("\u{2022}", 5), $text->text);
    }

    public function testInputFileShowsPlaceholderLabel(): void
    {
        $sheet = $this->css->parseStylesheet('html, body, p { display: block; }');
        $doc = $this->html->parseDocument(
            '<html><body><p><input type="file"></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        $text = $input->children[0] ?? null;
        self::assertInstanceOf(TextBox::class, $text);
        self::assertSame('No file chosen', $text->text);
    }

    public function testImgWithoutAltStaysAtomicInline(): void
    {
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            img { display: inline-block; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p><img src="x.png"></p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertInstanceOf(AtomicInlineBox::class, $img);
    }

    public function testOlTypeAttributeMapsToListStyleType(): void
    {
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, ol, li { display: block; }
            li { display: list-item; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><ol type="A"><li>x</li></ol></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $ol = $this->findFirstByTag($box, 'ol');
        self::assertNotNull($ol);
        $kw = $ol->style->get('list-style-type');
        self::assertInstanceOf(\Phpdftk\Css\Value\Keyword::class, $kw);
        self::assertSame('upper-alpha', $kw->name);
    }

    public function testOlTypeIsOverriddenByAuthorCss(): void
    {
        // `ol { list-style-type: lower-roman }` author rule beats the
        // `<ol type="A">` attribute.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, ol, li { display: block; }
            li { display: list-item; }
            ol { list-style-type: lower-roman; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><ol type="A"><li>x</li></ol></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $ol = $this->findFirstByTag($box, 'ol');
        self::assertNotNull($ol);
        $kw = $ol->style->get('list-style-type');
        self::assertInstanceOf(\Phpdftk\Css\Value\Keyword::class, $kw);
        self::assertSame('lower-roman', $kw->name);
    }

    public function testCounterIncrementsAndResolvesInContent(): void
    {
        // CSS counters: section starts at 0 on body, each h2 bumps it, the
        // pseudo content reads counter(section). The three h2s should
        // generate "1.", "2.", "3." prefixes.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, h2 { display: block; }
            body { counter-reset: section; }
            h2 { counter-increment: section; }
            h2::before { content: counter(section) '. '; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><h2>A</h2><h2>B</h2><h2>C</h2></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        $texts = [];
        foreach ($body->children as $h2) {
            // Each h2: [pseudoBefore(InlineBox > TextBox(content)), TextBox(label)]
            $pseudo = $h2->children[0] ?? null;
            self::assertInstanceOf(InlineBox::class, $pseudo);
            $text = $pseudo->children[0] ?? null;
            self::assertInstanceOf(TextBox::class, $text);
            $texts[] = $text->text;
        }
        self::assertSame(['1. ', '2. ', '3. '], $texts);
    }

    public function testCounterRomanStyle(): void
    {
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            body { counter-reset: chap; }
            p { counter-increment: chap; }
            p::before { content: counter(chap, upper-roman) '. '; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p>a</p><p>b</p><p>c</p><p>d</p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        $texts = [];
        foreach ($body->children as $p) {
            $pseudo = $p->children[0] ?? null;
            self::assertInstanceOf(InlineBox::class, $pseudo);
            $texts[] = $pseudo->children[0]->text;
        }
        self::assertSame(['I. ', 'II. ', 'III. ', 'IV. '], $texts);
    }

    public function testCountersPluralWithSeparatorEmitsCurrentValue(): void
    {
        // CSS Generated Content 3 §2.3 — `counters(name, ".")`
        // formats the current counter value. Phase-2 falls back to a
        // single-scope rendering (no nested chain) — equivalent to
        // `counter(name)`.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            body { counter-reset: section; }
            p { counter-increment: section; }
            p::before { content: counters(section, ".") '. '; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p>a</p><p>b</p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        $first = $body->children[0]->children[0];
        $second = $body->children[1]->children[0];
        self::assertSame('1. ', $first->children[0]->text);
        self::assertSame('2. ', $second->children[0]->text);
    }

    public function testCounterSetOverridesValue(): void
    {
        // CSS Lists 3 §6 — counter-set sets the counter to an explicit
        // value at this element (vs counter-reset which creates a new
        // scope). Applied after counter-reset but before
        // counter-increment, so increment-bump compositions work.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            body { counter-reset: chap; }
            p { counter-set: chap 10; counter-increment: chap; }
            p::before { content: counter(chap) '. '; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p>a</p><p>b</p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        // counter-set: chap 10 fires on each p, then counter-increment
        // bumps to 11. So both p's emit '11. '.
        $first = $body->children[0]->children[0];
        $second = $body->children[1]->children[0];
        self::assertSame('11. ', $first->children[0]->text);
        self::assertSame('11. ', $second->children[0]->text);
    }

    public function testCountersAcceptsExplicitStyleArg(): void
    {
        // `counters(name, sep, style)` — third arg is the counter style.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            body { counter-reset: chap; }
            p { counter-increment: chap; }
            p::before { content: counters(chap, ".", upper-roman) '. '; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><p>a</p><p>b</p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $body = $this->findFirstByTag($box, 'body');
        $first = $body->children[0]->children[0];
        self::assertSame('I. ', $first->children[0]->text);
    }

    public function testCountersRequiresSeparatorArgument(): void
    {
        // Negative: `counters(name)` (missing separator) — bad
        // grammar, the function resolves to null content so no
        // pseudo box generates and the `<p>` only has its own text.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            body { counter-reset: section; }
            p { counter-increment: section; }
            p::before { content: counters(section); }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p>x</p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        // No counter digit should appear anywhere in the subtree.
        $text = '';
        $stack = [$p];
        while ($stack !== []) {
            $n = array_pop($stack);
            if ($n instanceof TextBox) {
                $text .= $n->text;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertSame('x', $text, 'no counter value rendered from bad grammar');
    }

    public function testContentUrlAcceptedButProducesNoText(): void
    {
        // Phase-2 accepts `content: url(...)` syntactically — the
        // pseudo box still generates so layout / cascade behaviour
        // around author CSS is preserved, but no text content is
        // emitted (image insertion through generated content is
        // a follow-up).
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            p::before { content: url('badge.png'); }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p>x</p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        $pseudo = $p->children[0];
        self::assertInstanceOf(InlineBox::class, $pseudo);
        self::assertCount(0, $pseudo->children, 'url() content produces no TextBox');
    }

    public function testSubmitInputRendersValueAsLabel(): void
    {
        // `<input type="submit" value="Send">` renders the label
        // inline.
        $doc = $this->html->parseDocument('<html><body><input type="submit" value="Send"></body></html>');
        $box = $this->generator->generate($doc, []);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        self::assertCount(1, $input->children);
        self::assertSame('Send', $input->children[0]->text);
    }

    public function testSubmitInputWithoutValueUsesSubmitDefault(): void
    {
        // HTML 5 default for `type="submit"` without value: "Submit".
        $doc = $this->html->parseDocument('<html><body><input type="submit"></body></html>');
        $box = $this->generator->generate($doc, []);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        self::assertCount(1, $input->children);
        self::assertSame('Submit', $input->children[0]->text);
    }

    public function testResetInputDefaultLabel(): void
    {
        $doc = $this->html->parseDocument('<html><body><input type="reset"></body></html>');
        $box = $this->generator->generate($doc, []);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        self::assertSame('Reset', $input->children[0]->text);
    }

    public function testButtonInputWithoutValueProducesEmptyLabel(): void
    {
        // Negative: `type="button"` has no spec default label
        // (unlike submit/reset). Empty value → no TextBox child.
        $doc = $this->html->parseDocument('<html><body><input type="button"></body></html>');
        $box = $this->generator->generate($doc, []);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        self::assertCount(0, $input->children);
    }

    public function testCheckboxRendersAsciiIndicator(): void
    {
        $doc = $this->html->parseDocument('<html><body><input type="checkbox"></body></html>');
        $box = $this->generator->generate($doc, []);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        self::assertSame('[ ] ', $input->children[0]->text);
    }

    public function testCheckedCheckboxRendersXIndicator(): void
    {
        $doc = $this->html->parseDocument('<html><body><input type="checkbox" checked></body></html>');
        $box = $this->generator->generate($doc, []);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        self::assertSame('[x] ', $input->children[0]->text);
    }

    public function testRadioRendersDifferentIndicator(): void
    {
        // Radio uses parens; verify uncheckedstate.
        $doc = $this->html->parseDocument('<html><body><input type="radio"></body></html>');
        $box = $this->generator->generate($doc, []);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        self::assertSame('( ) ', $input->children[0]->text);
    }

    public function testCheckedRadioRendersFilledIndicator(): void
    {
        $doc = $this->html->parseDocument('<html><body><input type="radio" checked></body></html>');
        $box = $this->generator->generate($doc, []);
        $input = $this->findFirstByTag($box, 'input');
        self::assertNotNull($input);
        self::assertSame('(o) ', $input->children[0]->text);
    }

    public function testSelectRendersSelectedOptionOnly(): void
    {
        // `<select>` with an explicit selected attribute renders that
        // option's text and skips the others.
        $doc = $this->html->parseDocument(
            '<html><body><select>'
            . '<option>Apple</option>'
            . '<option selected>Banana</option>'
            . '<option>Cherry</option>'
            . '</select></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $select = $this->findFirstByTag($box, 'select');
        self::assertNotNull($select);
        self::assertCount(1, $select->children);
        self::assertSame('Banana', $select->children[0]->text);
    }

    public function testSelectFallsBackToFirstOption(): void
    {
        // When no option has `selected`, HTML 5 §4.10.7 says the first
        // option is implicitly selected.
        $doc = $this->html->parseDocument(
            '<html><body><select>'
            . '<option>Alpha</option>'
            . '<option>Beta</option>'
            . '</select></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $select = $this->findFirstByTag($box, 'select');
        self::assertNotNull($select);
        self::assertCount(1, $select->children);
        self::assertSame('Alpha', $select->children[0]->text);
    }

    public function testSelectEmptyRendersNoText(): void
    {
        // Negative: empty <select> has no options → nothing to render
        // (the InlineBox itself is still emitted as the cascade may
        // give it a border, but no text child appears).
        $doc = $this->html->parseDocument('<html><body><select></select></body></html>');
        $box = $this->generator->generate($doc, []);
        $select = $this->findFirstByTag($box, 'select');
        self::assertNotNull($select);
        self::assertSame([], $select->children);
    }

    public function testSelectSkipsNonOptionChildren(): void
    {
        // Negative: non-<option> children (rare) are ignored by the
        // selection picker — the first option still wins.
        $doc = $this->html->parseDocument(
            '<html><body><select>'
            . 'stray text'
            . '<option>Real Option</option>'
            . '</select></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $select = $this->findFirstByTag($box, 'select');
        self::assertNotNull($select);
        self::assertCount(1, $select->children);
        self::assertSame('Real Option', $select->children[0]->text);
    }

    public function testSelectFirstSelectedWinsWhenMultiple(): void
    {
        // Negative: if multiple options have `selected` (HTML 5 spec
        // says only the LAST one counts at parse time for non-multiple
        // selects, but our simpler Phase-1 picker takes the first
        // encountered — document the behaviour).
        $doc = $this->html->parseDocument(
            '<html><body><select>'
            . '<option>A</option>'
            . '<option selected>B</option>'
            . '<option selected>C</option>'
            . '</select></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $select = $this->findFirstByTag($box, 'select');
        self::assertNotNull($select);
        self::assertSame('B', $select->children[0]->text);
    }

    public function testOptionElementDoesNotRenderStandalone(): void
    {
        // Negative: a stray <option> outside a <select> has
        // `display: none` via UA → no box generated.
        $doc = $this->html->parseDocument(
            '<html><body><div><option>stray</option></div></body></html>',
        );
        $opts = new \Phpdftk\HtmlToPdf\RendererOptions();
        $ua = $this->css->parseStylesheet(
            $opts->effectiveUserAgentStylesheet(),
            \Phpdftk\Css\Sheet\Origin::UserAgent,
        );
        $box = $this->generator->generate($doc, [$ua]);
        self::assertNotNull($box);
        // The <div> exists, but no <option> child should be present.
        $stray = $this->findFirstByTag($box, 'option');
        self::assertNull($stray);
    }

    public function testSelectMultipleRendersEverySelectedOption(): void
    {
        // HTML 5 §4.10.7: `<select multiple>` shows each option that
        // carries the `selected` attribute. They stack via "\n" so
        // line-breaking puts them on separate lines.
        $doc = $this->html->parseDocument(
            '<html><body><select multiple>'
            . '<option selected>Alpha</option>'
            . '<option>Beta</option>'
            . '<option selected>Gamma</option>'
            . '</select></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $select = $this->findFirstByTag($box, 'select');
        self::assertNotNull($select);
        $text = '';
        foreach ($select->children as $child) {
            if ($child instanceof TextBox) {
                $text .= $child->text;
            }
        }
        self::assertStringContainsString('Alpha', $text);
        self::assertStringContainsString('Gamma', $text);
        self::assertStringNotContainsString('Beta', $text, 'unselected option suppressed');
    }

    public function testSelectMultipleWithNoSelectionsRendersEmpty(): void
    {
        // Negative: `<select multiple>` with NO selected options
        // produces no rendered text (unlike single-select which
        // implicitly picks the first).
        $doc = $this->html->parseDocument(
            '<html><body><select multiple>'
            . '<option>Alpha</option>'
            . '<option>Beta</option>'
            . '</select></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $select = $this->findFirstByTag($box, 'select');
        self::assertNotNull($select);
        $text = '';
        foreach ($select->children as $child) {
            if ($child instanceof TextBox) {
                $text .= $child->text;
            }
        }
        self::assertSame('', $text, 'multi-select with no selections renders nothing');
    }

    public function testSelectOptgroupLabelsAppearBeforeOptions(): void
    {
        // HTML 5 §4.10.10: `<optgroup label>` groups its options. The
        // label is rendered as a "label: " inline prefix so the print
        // form keeps the grouping visible.
        $doc = $this->html->parseDocument(
            '<html><body><select>'
            . '<optgroup label="Fruits"><option selected>Apple</option></optgroup>'
            . '</select></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $select = $this->findFirstByTag($box, 'select');
        self::assertNotNull($select);
        $text = '';
        foreach ($select->children as $child) {
            if ($child instanceof TextBox) {
                $text .= $child->text;
            }
        }
        self::assertStringContainsString('Fruits:', $text, 'optgroup label rendered');
        self::assertStringContainsString('Apple', $text);
    }

    public function testSelectFallsBackToFirstOptgroupedOption(): void
    {
        // Negative: when no option is selected, single-select picks
        // the first option — including options nested inside an
        // optgroup. The optgroup's label still renders.
        $doc = $this->html->parseDocument(
            '<html><body><select>'
            . '<optgroup label="A"><option>x</option></optgroup>'
            . '<optgroup label="B"><option>y</option></optgroup>'
            . '</select></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $select = $this->findFirstByTag($box, 'select');
        self::assertNotNull($select);
        $text = '';
        foreach ($select->children as $child) {
            if ($child instanceof TextBox) {
                $text .= $child->text;
            }
        }
        self::assertStringContainsString('A:', $text);
        self::assertStringContainsString('x', $text);
        self::assertStringNotContainsString('B:', $text, 'only first option rendered');
        self::assertStringNotContainsString('y', $text);
    }

    public function testWbrEmitsZeroWidthSpaceCharacter(): void
    {
        // HTML 5 §4.5.27: `<wbr>` is a Word Break Opportunity — a void
        // inline element that just permits a line break at its position.
        // BoxGenerator emits a U+200B zero-width space so the line
        // breaker sees the opportunity.
        $doc = $this->html->parseDocument('<html><body><wbr></body></html>');
        $box = $this->generator->generate($doc, []);
        $wbr = $this->findFirstByTag($box, 'wbr');
        self::assertNotNull($wbr);
        self::assertCount(1, $wbr->children);
        self::assertSame("\u{200B}", $wbr->children[0]->text);
    }

    public function testAreaElementHidesViaUaStylesheet(): void
    {
        // HTML 5 §4.8.14: `<area>` defines image-map hotspots. Static
        // print has no interactive areas, so the UA rule
        // `area { display: none }` suppresses any box for it.
        $doc = $this->html->parseDocument(
            '<html><body><map><area shape="rect" alt="x"></map></body></html>',
        );
        $opts = new \Phpdftk\HtmlToPdf\RendererOptions();
        $ua = $this->css->parseStylesheet(
            $opts->effectiveUserAgentStylesheet(),
            \Phpdftk\Css\Sheet\Origin::UserAgent,
        );
        $box = $this->generator->generate($doc, [$ua]);
        self::assertNotNull($box);
        $area = $this->findFirstByTag($box, 'area');
        self::assertNull($area);
    }

    public function testMapElementStaysInlineFlow(): void
    {
        // Negative: `<map>` itself should still produce an inline box
        // even though its `<area>` children are hidden.
        $doc = $this->html->parseDocument(
            '<html><body><map name="m"></map></body></html>',
        );
        $opts = new \Phpdftk\HtmlToPdf\RendererOptions();
        $ua = $this->css->parseStylesheet(
            $opts->effectiveUserAgentStylesheet(),
            \Phpdftk\Css\Sheet\Origin::UserAgent,
        );
        $box = $this->generator->generate($doc, [$ua]);
        self::assertNotNull($box);
        $map = $this->findFirstByTag($box, 'map');
        self::assertNotNull($map);
    }

    public function testWbrWithoutUaStylesheetStillEmitsZwsp(): void
    {
        // Negative: the BoxGenerator's `<wbr>` handling runs in code,
        // not via the UA stylesheet — so even without UA rules the
        // ZWSP child appears.
        $doc = $this->html->parseDocument('<html><body>x<wbr>y</body></html>');
        $box = $this->generator->generate($doc, []);
        $wbr = $this->findFirstByTag($box, 'wbr');
        self::assertNotNull($wbr);
        self::assertSame("\u{200B}", $wbr->children[0]->text);
    }

    public function testWbrInsideTextHasZeroWidth(): void
    {
        // Negative: a `<wbr>` between two text runs doesn't visibly
        // widen the inline content — its only effect is a break
        // opportunity.
        $doc = $this->html->parseDocument(
            '<html><body><p>foo<wbr>bar</p></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        // The `<wbr>` produces its own box, but child[0]=foo,
        // child[1]=<wbr>, child[2]=bar.
        self::assertCount(3, $p->children);
        $wbr = $p->children[1];
        self::assertSame('wbr', $wbr->element->localName);
        self::assertSame("\u{200B}", $wbr->children[0]->text);
    }

    public function testPictureSourcePrintOverridesImgSrc(): void
    {
        // HTML 5 §4.8.4.2 — when `<img>` is inside `<picture>` and
        // a `<source media="print" srcset="...">` exists, the source's
        // URL replaces the img's src for print rendering.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source media="print" srcset="print.png">'
            . '<img src="screen.png" alt="fallback">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('print.png', $img->element->getAttribute('src'));
    }

    public function testPictureSourceAllMediaAlsoOverrides(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source media="all" srcset="all.png">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('all.png', $img->element->getAttribute('src'));
    }

    public function testPictureSourceWithoutMediaAttributeOverrides(): void
    {
        // `<source>` without `media` is treated as `media="all"`.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source srcset="any.png">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('any.png', $img->element->getAttribute('src'));
    }

    public function testPictureSourceScreenOnlyIsIgnored(): void
    {
        // Negative: `media="screen"` doesn't match print → fallback
        // img's original src wins.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source media="screen" srcset="screen.png">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('fallback.png', $img->element->getAttribute('src'));
    }

    public function testPictureFirstMatchingSourceWins(): void
    {
        // When multiple sources match, the FIRST one wins (document
        // order). Browsers walk top-to-bottom and pick the first
        // match.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source media="print" srcset="first.png">'
            . '<source media="print" srcset="second.png">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('first.png', $img->element->getAttribute('src'));
    }

    public function testStandaloneImgUnaffected(): void
    {
        // Negative: img NOT inside picture — src stays untouched.
        $doc = $this->html->parseDocument(
            '<html><body><img src="original.png" alt="x"></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('original.png', $img->element->getAttribute('src'));
    }

    public function testPictureSourceTypeFiltersUnsupportedFormats(): void
    {
        // HTML 5 §4.8.4.2.4 — `<source type="image/avif">` declares a
        // format the painter can't decode. Skip it; pick the next
        // source (image/png) instead.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source type="image/avif" srcset="modern.avif">'
            . '<source type="image/png" srcset="legacy.png">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('legacy.png', $img->element->getAttribute('src'));
    }

    public function testPictureSourceWithSupportedTypeWins(): void
    {
        // Positive: a `<source type="image/png">` matches and wins
        // over a later untyped source.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source type="image/png" srcset="typed.png">'
            . '<source srcset="untyped.png">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('typed.png', $img->element->getAttribute('src'));
    }

    public function testPictureSourceAllUnsupportedTypesFallsBackToImg(): void
    {
        // Negative: every source's `type` is unsupported → walk falls
        // through and the inner `<img src>` stays the effective src.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source type="image/avif" srcset="a.avif">'
            . '<source type="image/heif" srcset="b.heif">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('fallback.png', $img->element->getAttribute('src'));
    }

    public function testSrcsetDensityDescriptorPicksHighestDpr(): void
    {
        // `srcset="lo.png 1x, hi.png 2x"` — print is high-DPI so the
        // 2x candidate wins.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source srcset="lo.png 1x, hi.png 2x">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('hi.png', $img->element->getAttribute('src'));
    }

    public function testSrcsetWidthDescriptorPicksLargestWidth(): void
    {
        // `Nw` descriptors are width hints. Treated as density via
        // N/100, so 800w > 400w > 200w. Largest wins.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source srcset="small.png 200w, medium.png 400w, large.png 800w">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('large.png', $img->element->getAttribute('src'));
    }

    public function testSrcsetUnrecognisedDescriptorIsDropped(): void
    {
        // Negative: a candidate with an unknown descriptor is
        // dropped. Here `bogus.png 5q` is invalid, so the algorithm
        // picks among the remaining valid ones.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source srcset="bogus.png 5q, normal.png 1x">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('normal.png', $img->element->getAttribute('src'));
    }

    public function testSrcsetBareUrlDefaultsToOneX(): void
    {
        // Negative: a candidate with no descriptor counts as 1x.
        // 2x wins over the bare candidate.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source srcset="default.png, hi.png 2x">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('hi.png', $img->element->getAttribute('src'));
    }

    public function testSrcsetTieAmongCandidatesPicksFirst(): void
    {
        // Negative: ties on density resolve to the first declared
        // candidate (stable preservation of authored order).
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source srcset="first.png 2x, second.png 2x">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('first.png', $img->element->getAttribute('src'));
    }

    public function testQElementWrapsWithQuotes(): void
    {
        // The UA stylesheet's `q::before/after { content: open-quote /
        // close-quote }` should wrap the `<q>`'s text in straight double
        // quotes — verified by the synthetic pseudo TextBoxes.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            q { display: inline; }
            q::before { content: open-quote; }
            q::after { content: close-quote; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p><q>hi</q></p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $q = $this->findFirstByTag($box, 'q');
        self::assertNotNull($q);
        // CSS Generated Content 3 §3.1 default `quotes: auto` produces
        // the typographic pair U+201C / U+201D.
        // q's children: [pseudoBefore(InlineBox > TextBox(open)), TextBox('hi'), pseudoAfter(InlineBox > TextBox(close))]
        self::assertCount(3, $q->children);
        $before = $q->children[0];
        $after = $q->children[2];
        self::assertInstanceOf(InlineBox::class, $before);
        self::assertInstanceOf(InlineBox::class, $after);
        self::assertSame("\u{201C}", $before->children[0]->text);
        self::assertSame("\u{201D}", $after->children[0]->text);
    }

    public function testQuotesPropertyOverridesDefaultPair(): void
    {
        // Author override: `quotes: '«' '»'` should swap in French
        // guillemets instead of the typographic English defaults.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            q { display: inline; quotes: "\u{00AB}" "\u{00BB}"; }
            q::before { content: open-quote; }
            q::after { content: close-quote; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p><q>hi</q></p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $q = $this->findFirstByTag($box, 'q');
        self::assertNotNull($q);
        $before = $q->children[0];
        $after = $q->children[2];
        self::assertSame("\u{00AB}", $before->children[0]->text);
        self::assertSame("\u{00BB}", $after->children[0]->text);
    }

    public function testQuotesAutoLeavesCurlyDefaults(): void
    {
        // Explicit `quotes: auto` is the same as no declaration —
        // the typographic default pair wins.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            q { display: inline; quotes: auto; }
            q::before { content: open-quote; }
            q::after { content: close-quote; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p><q>hi</q></p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $q = $this->findFirstByTag($box, 'q');
        self::assertNotNull($q);
        $before = $q->children[0];
        self::assertSame("\u{201C}", $before->children[0]->text);
    }

    public function testQuotesValueWithSingleStringDefaultsBack(): void
    {
        // CSS Generated Content 3 §3.1 requires PAIRS of strings; a
        // single string is malformed at Phase 1, so we fall back to
        // the typographic default instead of using the half-pair.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            q { display: inline; quotes: "X"; }
            q::before { content: open-quote; }
            q::after { content: close-quote; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p><q>hi</q></p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $q = $this->findFirstByTag($box, 'q');
        self::assertNotNull($q);
        $before = $q->children[0];
        self::assertSame("\u{201C}", $before->children[0]->text);
    }

    public function testNoOpenQuoteProducesEmptyString(): void
    {
        // `no-open-quote` / `no-close-quote` produce empty strings
        // (the pseudo box still generates).
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            q { display: inline; }
            q::before { content: no-open-quote; }
            q::after { content: no-close-quote; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p><q>hi</q></p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $q = $this->findFirstByTag($box, 'q');
        self::assertNotNull($q);
        // Pseudo box still exists but with empty text — no TextBox child.
        $before = $q->children[0];
        self::assertInstanceOf(InlineBox::class, $before);
        self::assertCount(0, $before->children);
    }

    public function testQuotesNoneSuppressesQuoteGlyphs(): void
    {
        // CSS Generated Content 3 §3.1 — `quotes: none` makes both
        // `open-quote` and `close-quote` evaluate to the empty
        // string. The pseudo boxes still generate (so author CSS
        // can target them) but they hold no text.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            q { display: inline; quotes: none; }
            q::before { content: open-quote; }
            q::after { content: close-quote; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p><q>hi</q></p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $q = $this->findFirstByTag($box, 'q');
        self::assertNotNull($q);
        $before = $q->children[0];
        $after = $q->children[2];
        self::assertInstanceOf(InlineBox::class, $before);
        self::assertCount(0, $before->children, 'open-quote suppressed by quotes: none');
        self::assertCount(0, $after->children, 'close-quote suppressed by quotes: none');
    }

    public function testQuotesNestedDepthPicksSecondPair(): void
    {
        // Nested `<q>` chains advance the depth — the inner `<q>`
        // picks the SECOND pair from `quotes` (single-quote glyphs)
        // while the outer keeps the first (double-quote glyphs).
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            q { display: inline; quotes: '"' '"' "'" "'"; }
            q::before { content: open-quote; }
            q::after { content: close-quote; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p><q>outer <q>inner</q> trail</q></p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        // Collect unique <q> boxes in document order — dedupe by
        // element identity to skip anonymous wrappers reusing the
        // element pointer.
        $qs = [];
        $seen = [];
        $stack = [$box];
        while ($stack !== []) {
            $n = array_pop($stack);
            $el = $n->element;
            if ($el !== null && strtolower($el->localName) === 'q' && $n instanceof InlineBox) {
                $id = spl_object_id($el);
                if (!isset($seen[$id])) {
                    $seen[$id] = true;
                    $qs[] = $n;
                }
            }
            foreach (array_reverse($n->children) as $c) {
                $stack[] = $c;
            }
        }
        self::assertCount(2, $qs);
        // outer q (depth 0) uses pair 0 = double quote
        $outerBefore = $qs[0]->children[0];
        self::assertSame('"', $outerBefore->children[0]->text);
        // inner q (depth 1) uses pair 1 = single quote
        $innerBefore = $qs[1]->children[0];
        self::assertSame("'", $innerBefore->children[0]->text);
    }

    public function testQuotesNestedClampsToLastPairWhenTooDeep(): void
    {
        // Negative: when nesting exceeds the declared pair count,
        // the depth clamps to the LAST pair (spec behaviour). Two
        // nested `<q>`s with only one pair → both use the same pair.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            q { display: inline; quotes: "X" "Y"; }
            q::before { content: open-quote; }
            q::after { content: close-quote; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p><q><q>inner</q></q></p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $qs = [];
        $seen = [];
        $stack = [$box];
        while ($stack !== []) {
            $n = array_pop($stack);
            $el = $n->element;
            if ($el !== null && strtolower($el->localName) === 'q' && $n instanceof InlineBox) {
                $id = spl_object_id($el);
                if (!isset($seen[$id])) {
                    $seen[$id] = true;
                    $qs[] = $n;
                }
            }
            foreach (array_reverse($n->children) as $c) {
                $stack[] = $c;
            }
        }
        self::assertCount(2, $qs);
        self::assertSame('X', $qs[0]->children[0]->children[0]->text);
        self::assertSame('X', $qs[1]->children[0]->children[0]->text, 'over-nesting clamps to last pair');
    }


    public function testPseudoAttrMissingProducesEmptyString(): void
    {
        // `attr(missing)` on an element that doesn't carry the attribute
        // resolves to an empty string — still generates the pseudo box.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            p::before { content: attr(title); }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p>X</p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        $pseudo = $p->children[0];
        self::assertInstanceOf(InlineBox::class, $pseudo);
        // No TextBox child because the empty-string `attr()` doesn't add one.
        self::assertCount(0, $pseudo->children);
    }

    public function testNormalContentSuppressesPseudo(): void
    {
        // `content: normal` (the initial) doesn't generate a pseudo box.
        $sheet = $this->css->parseStylesheet('html, body, p { display: block; }');
        $doc = $this->html->parseDocument('<html><body><p>X</p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->children, 'no pseudo when content is normal');
    }

    public function testTextNodeBecomesTextBox(): void
    {
        $sheet = $this->css->parseStylesheet('html, body, p { display: block; }');
        $doc = $this->html->parseDocument('<html><body><p>Hello, world!</p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->children);
        $text = $p->children[0];
        self::assertInstanceOf(TextBox::class, $text);
        self::assertSame('Hello, world!', $text->text);
    }

    private function findFirstByTag(\Phpdftk\HtmlToPdf\Box\Box $root, string $tag): ?\Phpdftk\HtmlToPdf\Box\Box
    {
        $stack = [$root];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node->element !== null && $node->element->localName === $tag) {
                return $node;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        return null;
    }

    private function findFirstByClass(\Phpdftk\HtmlToPdf\Box\Box $root, string $class): ?\Phpdftk\HtmlToPdf\Box\Box
    {
        $stack = [$root];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node->element !== null && in_array($class, $node->element->classes(), true)) {
                return $node;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        return null;
    }

    // ------------------------------------------------------------
    // Out-of-flow blockification (CSS 2.1 §9.7 / CSS Display §2.7)
    // -- #21
    // ------------------------------------------------------------

    public function testInlineImgWithoutPositionStaysInline(): void
    {
        // Guard: blockification must NOT fire for in-flow inline
        // elements. Plain `<img>` keeps its UA display: inline-block.
        $doc = $this->html->parseDocument('<html><body><p><img src="x.png"></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet()]);
        self::assertNotNull($box);
        $img = $this->findFirstByTag($box, 'img');
        self::assertInstanceOf(AtomicInlineBox::class, $img);
    }

    public function testInlineImgWithStaticPositionStaysInline(): void
    {
        // `position: static` is the initial value; explicit `static`
        // must not trigger blockification.
        $sheet = $this->css->parseStylesheet(
            'img { position: static }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument('<html><body><p><img src="x.png"></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertInstanceOf(AtomicInlineBox::class, $img);
    }

    public function testInlineImgWithRelativePositionStaysInline(): void
    {
        // `position: relative` is NOT out-of-flow — element stays
        // in normal flow with an offset. Must NOT blockify.
        $sheet = $this->css->parseStylesheet(
            'img { position: relative; left: 10px }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument('<html><body><p><img src="x.png"></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertInstanceOf(AtomicInlineBox::class, $img);
    }

    public function testInlineImgWithStickyPositionStaysInline(): void
    {
        // `position: sticky` — also not out-of-flow per spec
        // (the element participates in normal flow with a stuck
        // offset within its containing block).
        $sheet = $this->css->parseStylesheet(
            'img { position: sticky; top: 0 }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument('<html><body><p><img src="x.png"></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertInstanceOf(AtomicInlineBox::class, $img);
    }

    public function testInlineImgWithFloatNoneStaysInline(): void
    {
        // Float `none` is the initial value; explicit `none` must
        // not trigger blockification.
        $sheet = $this->css->parseStylesheet(
            'img { float: none }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument('<html><body><p><img src="x.png"></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertInstanceOf(AtomicInlineBox::class, $img);
    }

    public function testInlineImgWithPositionAbsoluteBlockifies(): void
    {
        // Core fix: out-of-flow `position: absolute` blockifies the
        // inline-level `<img>` into a `BlockBox` so abs-pos layout
        // honours the corner anchors.
        $sheet = $this->css->parseStylesheet(
            'img { position: absolute; left: 7.5px; top: 8px }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument('<html><body><p><img src="x.png"></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertInstanceOf(BlockBox::class, $img);
    }

    public function testInlineImgWithPositionFixedBlockifies(): void
    {
        $sheet = $this->css->parseStylesheet(
            'img { position: fixed; right: 0; bottom: 0 }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument('<html><body><p><img src="x.png"></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertInstanceOf(BlockBox::class, $img);
    }

    public function testInlineImgWithFloatLeftBlockifies(): void
    {
        $sheet = $this->css->parseStylesheet(
            'img { float: left }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument('<html><body><p><img src="x.png"></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertInstanceOf(BlockBox::class, $img);
    }

    public function testInlineImgWithFloatRightBlockifies(): void
    {
        $sheet = $this->css->parseStylesheet(
            'img { float: right }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument('<html><body><p><img src="x.png"></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertInstanceOf(BlockBox::class, $img);
    }

    public function testInlineSpanWithAbsolutePositionBlockifies(): void
    {
        // Blockification applies to ANY inline-level element, not
        // just <img>. A `position: absolute` `<span>` becomes a
        // block-level box.
        $sheet = $this->css->parseStylesheet(
            'span { position: absolute; left: 0; top: 0 }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument('<html><body><p><span>hi</span></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $span = $this->findFirstByTag($box, 'span');
        self::assertInstanceOf(BlockBox::class, $span);
    }

    public function testBlockImgWithAbsolutePositionStaysBlock(): void
    {
        // Already-block elements remain block (no double-blockify).
        $sheet = $this->css->parseStylesheet(
            'img { display: block; position: absolute }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument('<html><body><p><img src="x.png"></p></body></html>');
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $img = $this->findFirstByTag($box, 'img');
        self::assertInstanceOf(BlockBox::class, $img);
    }

    public function testForeignMathRootStaysAtomicInlineUnderAbsolutePosition(): void
    {
        // Regression guard for mathml/spaces/space-3: root <math>
        // is foreign content and must NOT be blockified by the
        // out-of-flow rule. The inline-math painter
        // (`paintInlineMath`) resolves its own abs-pos via
        // `resolveInlineAbsoluteOrigin`; blockifying breaks that
        // path entirely.
        $sheet = $this->css->parseStylesheet(
            'math { position: absolute; top: 0; left: 0 }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument(
            '<html><body><math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<mi>x</mi></math></body></html>',
        );
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $math = $this->findFirstByTag($box, 'math');
        self::assertNotNull($math);
        self::assertNotInstanceOf(BlockBox::class, $math);
    }

    public function testForeignSvgRootStaysAtomicInlineUnderAbsolutePosition(): void
    {
        // Same posture for inline <svg> — paintInlineSvg owns
        // its own positioning.
        $sheet = $this->css->parseStylesheet(
            'svg { position: absolute; top: 0; left: 0 }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument(
            '<html><body><svg xmlns="http://www.w3.org/2000/svg" '
            . 'width="10" height="10"><rect width="10" height="10"/></svg>'
            . '</body></html>',
        );
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $svg = $this->findFirstByTag($box, 'svg');
        self::assertNotNull($svg);
        self::assertNotInstanceOf(BlockBox::class, $svg);
    }
}
