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

    public function testSrcsetMultipleUrlsPicksFirst(): void
    {
        // `srcset="u1 1x, u2 2x"` → first URL `u1` wins.
        $doc = $this->html->parseDocument(
            '<html><body><picture>'
            . '<source srcset="lo.png 1x, hi.png 2x">'
            . '<img src="fallback.png" alt="x">'
            . '</picture></body></html>',
        );
        $box = $this->generator->generate($doc, []);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        self::assertSame('lo.png', $img->element->getAttribute('src'));
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
}
