<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\HtmlToPdf\Box\AnonymousBlockBox;
use Phpdftk\HtmlToPdf\Box\AtomicInlineBox;
use Phpdftk\HtmlToPdf\Box\BlockBox;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\Box\FlexBox;
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

    public function testContentVisibilityHiddenKeepsBoxButSkipsContents(): void
    {
        // CSS Containment 2 §4 — `content-visibility: hidden` does NOT drop
        // the box (unlike display:none). The element's own box exists and
        // paints, but its contents are skipped (no child boxes) and it is
        // size-contained (synthesized `contain: strict`). A following
        // sibling is unaffected.
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
        self::assertNotNull($hidden, 'content-visibility:hidden element still generates a box');
        self::assertSame([], $hidden->children, 'its contents (children) are skipped');
        $contain = $hidden->style->get('contain');
        self::assertInstanceOf(Keyword::class, $contain);
        self::assertSame('strict', strtolower($contain->name), 'content-visibility:hidden synthesizes contain:strict');
        // The following sibling <p>shown</p> is untouched.
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

    public function testBlockInInlineWrapperResetsInlineBoxDecoration(): void
    {
        // CSS 2.1 §9.2.1.1 — a <span> carrying border / padding / margin /
        // background that contains a display:block child splits around the
        // block into an anonymous block wrapper. The wrapper must NOT carry
        // the inline's box-decoration (which would paint a full-width border /
        // background band across the block) but MUST preserve position so
        // `position: relative` on the inline still shifts the block half.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body { display: block; }
            span { display: inline; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><span style="border: 5px solid blue; padding: 10px;'
            . ' margin: 4px; background-color: red; position: relative">'
            . '<span style="display: block">x</span></span></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        $wrapper = $body->children[0];
        self::assertInstanceOf(AnonymousBlockBox::class, $wrapper);
        // Box-decoration neutralised on the wrapper.
        $topStyle = $wrapper->style->get('border-top-style');
        self::assertInstanceOf(Keyword::class, $topStyle);
        self::assertSame('none', strtolower($topStyle->name));
        $padTop = $wrapper->style->get('padding-top');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $padTop);
        self::assertSame(0.0, $padTop->value);
        $marginTop = $wrapper->style->get('margin-top');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $marginTop);
        self::assertSame(0.0, $marginTop->value);
        $bg = $wrapper->style->get('background-color');
        self::assertTrue(
            !($bg instanceof \Phpdftk\Css\Value\Color) || $bg->a <= 0.0,
            'wrapper background-color is transparent',
        );
        // position preserved (relpos coupling with the block half).
        $pos = $wrapper->style->get('position');
        self::assertInstanceOf(Keyword::class, $pos);
        self::assertSame('relative', strtolower($pos->name));
    }

    public function testInlineBlockWithBlockChildKeepsBoxDecoration(): void
    {
        // Narrowing guard for the block-in-inline wrapper reset: an atomic
        // `display: inline-block` containing a block child is itself a block
        // CONTAINER (not a split non-atomic inline), so its border / padding /
        // background apply as usual and must NOT be reset (CSS 2.1 §9.2.1.1
        // splitting applies only to non-atomic inlines).
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body { display: block; }
            span { display: inline; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><span style="display: inline-block;'
            . ' border: 5px solid blue"><span style="display: block">x</span>'
            . '</span></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        $wrapper = $body->children[0];
        self::assertInstanceOf(AnonymousBlockBox::class, $wrapper);
        $topStyle = $wrapper->style->get('border-top-style');
        self::assertInstanceOf(Keyword::class, $topStyle);
        self::assertSame('solid', strtolower($topStyle->name), 'inline-block keeps its border');
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

    public function testOutOfFlowPseudoIsBlockified(): void
    {
        // CSS 2.1 §9.7 / CSS Display 3 §2.7 — going out of flow blockifies
        // a pseudo-element the same way it blockifies an element. A
        // `::after { position: absolute }` with no `display` rule computes
        // to `inline`, and an inline out-of-flow box has no layout path at
        // all: it generated an InlineBox nothing ever positioned or
        // painted, so the pseudo silently vanished.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            p::after { content: ''; position: absolute; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p>X</p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        self::assertCount(2, $p->children);
        $pseudo = $p->children[1];
        self::assertInstanceOf(BlockBox::class, $pseudo);
        $display = $pseudo->style->get('display');
        self::assertInstanceOf(Keyword::class, $display);
        self::assertSame('block', $display->name);
    }

    public function testInFlowPseudoIsNotBlockified(): void
    {
        // The companion guard: an in-flow pseudo keeps its `inline`
        // display, so the blockification above cannot leak into ordinary
        // generated content.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, p { display: block; }
            p::after { content: '?'; }
        CSS);
        $doc = $this->html->parseDocument('<html><body><p>X</p></body></html>');
        $box = $this->generator->generate($doc, [$sheet]);
        $p = $this->findFirstByTag($box, 'p');
        self::assertNotNull($p);
        self::assertInstanceOf(InlineBox::class, $p->children[1]);
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

    public function testInsideMarkerBecomesInlineContentOfTheListItem(): void
    {
        // CSS Lists 3 §3.3 — `list-style-position: inside` puts the
        // `::marker` INSIDE the principal box, at the start of its
        // content, where it takes part in line layout.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, ol { display: block; }
            li { display: list-item; list-style-position: inside;
                 list-style-type: decimal; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><ol><li>x</li><li>y</li></ol></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $texts = [];
        $stack = [$box];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node instanceof TextBox) {
                $texts[] = $node->text;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertSame(['1. ', 'x', '2. ', 'y'], $texts);
    }

    public function testOutsideMarkerIsNotMaterialisedAsInlineContent(): void
    {
        // `outside` is the initial value and stays a painter-side box —
        // materialising it here would shift every list item's content.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, ol { display: block; }
            li { display: list-item; list-style-type: decimal; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><ol><li>x</li></ol></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $texts = [];
        $stack = [$box];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node instanceof TextBox) {
                $texts[] = $node->text;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertSame(['x'], $texts);
    }

    public function testInsideMarkerIsSuppressedByListStyleTypeNone(): void
    {
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, ol { display: block; }
            li { display: list-item; list-style-position: inside;
                 list-style-type: none; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><ol><li>x</li></ol></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $texts = [];
        $stack = [$box];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node instanceof TextBox) {
                $texts[] = $node->text;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertSame(['x'], $texts);
    }

    public function testInsideMarkerHonoursLiValueAndOlStart(): void
    {
        // HTML 5 §4.4.5.2/.3 — the inline marker must number identically
        // to the painted `outside` one; both read Box::$listItemOrdinal.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, ol { display: block; }
            li { display: list-item; list-style-position: inside;
                 list-style-type: decimal; }
        CSS);
        $doc = $this->html->parseDocument(
            '<html><body><ol start="-1"><li></li><li value="-5"></li><li></li></ol></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $texts = [];
        $stack = [$box];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node instanceof TextBox) {
                $texts[] = $node->text;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertSame(['-1. ', '-5. ', '-4. '], $texts);
    }

    public function testImgHspaceVspaceAndBorderAttributes(): void
    {
        // HTML §15.3.4 — `hspace` / `vspace` map to the horizontal /
        // vertical margins (dimension values, so a trailing `%` is a
        // percentage), and `border` to a solid border of that many
        // pixels on all four sides.
        $sheet = $this->css->parseStylesheet('html, body { display: block; }');
        $doc = $this->html->parseDocument(
            '<html><body>'
            . '<img id=a src="x.png" hspace="10" vspace="4" border="3">'
            . '<img id=b src="x.png" hspace="10%">'
            . '</body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $imgs = [];
        $stack = [$box];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n->element !== null && strtolower($n->element->localName) === 'img') {
                $imgs[$n->element->getAttribute('id') ?? ''] = $n;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        $a = $imgs['a'] ?? null;
        self::assertNotNull($a);
        foreach (['margin-left' => 10.0, 'margin-right' => 10.0, 'margin-top' => 4.0, 'margin-bottom' => 4.0] as $prop => $want) {
            $v = $a->style->get($prop);
            self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $v, $prop);
            self::assertSame($want, $v->value, $prop);
        }
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $w = $a->style->get("border-$side-width");
            self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $w);
            self::assertSame(3.0, $w->value);
            $st = $a->style->get("border-$side-style");
            self::assertInstanceOf(Keyword::class, $st);
            self::assertSame('solid', $st->name);
        }
        $b = $imgs['b'] ?? null;
        self::assertNotNull($b);
        $ml = $b->style->get('margin-left');
        self::assertInstanceOf(\Phpdftk\Css\Value\Percentage::class, $ml, 'hspace="10%" is a percentage margin');
        self::assertSame(10.0, $ml->value);
    }

    public function testImgBorderPercentUsesTheLeadingIntegerAndZeroDrawsNoBorder(): void
    {
        // The value is parsed with the rules for parsing NON-NEGATIVE
        // INTEGERS, which stop at the first non-digit — so `border="50%"`
        // is 50 pixels, not 50 percent. `border="0%"` is zero, and zero
        // must not force `solid` onto an otherwise borderless image.
        $sheet = $this->css->parseStylesheet('html, body { display: block; }');
        $doc = $this->html->parseDocument(
            '<html><body>'
            . '<img id=a src="x.png" border="0%"><img id=b src="x.png" border="50%">'
            . '</body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $imgs = [];
        $stack = [$box];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n->element !== null && strtolower($n->element->localName) === 'img') {
                $imgs[$n->element->getAttribute('id') ?? ''] = $n;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        $zero = $imgs['a'] ?? null;
        self::assertNotNull($zero);
        $style = $zero->style->get('border-top-style');
        self::assertTrue(
            !$style instanceof Keyword || $style->name !== 'solid',
            'border="0%" must not turn the border solid',
        );
        $fifty = $imgs['b'] ?? null;
        self::assertNotNull($fifty);
        $w = $fifty->style->get('border-top-width');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $w);
        self::assertSame(50.0, $w->value);
    }

    public function testLegacyColourPresentationalHints(): void
    {
        // HTML §15.3.3 — `<font color>` / `<font face>`, `bgcolor` on any
        // of the legacy hosts, and `<body text>`. The colour value goes
        // through HTML §2.4.6's "rules for parsing a legacy colour
        // value", whose tail is TOTAL: only the empty string and
        // `transparent` are errors, everything else is coerced (so
        // `color="x"` really is black).
        $sheet = $this->css->parseStylesheet('html, body { display: block; }');
        $doc = $this->html->parseDocument(
            '<html><body text="blue" bgcolor="yellow">'
            . '<font id=named color="fuchsia">a</font>'
            . '<font id=coerced color="x">b</font>'
            . '<font id=transparent color="transparent">c</font>'
            . '<font id=empty color="">d</font>'
            . '<font id=face face="Courier">e</font>'
            . '</body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $byId = [];
        $stack = [$box];
        while ($stack !== []) {
            $n = array_shift($stack);
            $id = $n->element?->getAttribute('id');
            if ($id !== null && $id !== '') {
                $byId[$id] ??= $n;
            }
            if ($n->element !== null && strtolower($n->element->localName) === 'body') {
                $byId['body'] ??= $n;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        $colorOf = static function (?\Phpdftk\HtmlToPdf\Box\Box $b, string $prop): array {
            self::assertNotNull($b);
            $v = $b->style->get($prop);
            self::assertInstanceOf(\Phpdftk\Css\Value\Color::class, $v);
            return [round($v->r, 3), round($v->g, 3), round($v->b, 3)];
        };
        self::assertSame([1.0, 0.0, 1.0], $colorOf($byId['named'] ?? null, 'color'));
        self::assertSame([0.0, 0.0, 0.0], $colorOf($byId['coerced'] ?? null, 'color'), 'color="x" coerces to black');
        // `transparent` and `""` are the two error cases: the hint is
        // dropped and the inherited `<body text>` blue shows through.
        self::assertSame([0.0, 0.0, 1.0], $colorOf($byId['transparent'] ?? null, 'color'));
        self::assertSame([0.0, 0.0, 1.0], $colorOf($byId['empty'] ?? null, 'color'));
        self::assertSame([1.0, 1.0, 0.0], $colorOf($byId['body'] ?? null, 'background-color'));
        $face = ($byId['face'] ?? null)?->style->get('font-family');
        self::assertInstanceOf(\Phpdftk\Css\Value\StringValue::class, $face);
        self::assertSame('Courier', $face->value);
    }

    public function testAuthorCssBeatsLegacyColourHints(): void
    {
        // Negative: `color` INHERITS, so it is present in every cascade
        // map; the hint has to consult `wasDeclared()` rather than
        // `has()` or it would stomp the author's declaration.
        $sheet = $this->css->parseStylesheet('html, body { display: block; }');
        $doc = $this->html->parseDocument(
            '<html><body><font color="x" style="color:fuchsia">a</font></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $font = $this->findFirstByTag($box, 'font');
        self::assertNotNull($font);
        $c = $font->style->get('color');
        self::assertInstanceOf(\Phpdftk\Css\Value\Color::class, $c);
        self::assertSame(1.0, $c->r);
        self::assertSame(0.0, $c->g);
        self::assertSame(1.0, $c->b);
    }

    public function testMediaElementsDoNotRenderTheirFallbackContent(): void
    {
        // HTML §4.8.9 / §4.8.10 — the children of `<video>` / `<audio>`
        // are fallback content for user agents that do NOT support the
        // element. A user agent that does support them renders the media,
        // never the children.
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; } video, audio { display: inline-block; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body>'
            . '<video><img src="fail.gif"><p>fallback</p></video>'
            . '<audio controls><img src="fail.gif"></audio>'
            . '</body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $stack = [$box];
        $found = 0;
        while ($stack !== []) {
            $n = array_shift($stack);
            $tag = $n->element !== null ? strtolower($n->element->localName) : '';
            if ($tag === 'video' || $tag === 'audio') {
                $found++;
                self::assertSame([], $n->children, "$tag renders no fallback content");
                continue;
            }
            self::assertNotSame('img', $tag, 'the fallback <img> generates no box');
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertSame(2, $found);
    }

    public function testTableWidthAndAlignAttributes(): void
    {
        // HTML §15.3.9 — `<table width>` is a dimension value (so a
        // percentage stays a percentage) and `<table align>` floats the
        // table or centres it with auto inline margins. The attribute
        // value is matched ASCII case-insensitively, which is why the
        // WPT fixture writes `align="LEFT"`.
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; } table { display: table; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body>'
            . '<table id=a width="150%" align="LEFT"></table>'
            . '<table id=b width="220" align="CENTER"></table>'
            . '<table id=c align="right"></table>'
            . '</body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $tables = [];
        $stack = [$box];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n->element !== null && strtolower($n->element->localName) === 'table') {
                $tables[$n->element->getAttribute('id') ?? ''] = $n;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        $a = $tables['a'] ?? null;
        self::assertNotNull($a);
        $w = $a->style->get('width');
        self::assertInstanceOf(\Phpdftk\Css\Value\Percentage::class, $w);
        self::assertSame(150.0, $w->value);
        $float = $a->style->get('float');
        self::assertInstanceOf(Keyword::class, $float);
        self::assertSame('left', $float->name);

        $b = $tables['b'] ?? null;
        self::assertNotNull($b);
        $bw = $b->style->get('width');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $bw);
        self::assertSame(220.0, $bw->value);
        foreach (['margin-left', 'margin-right'] as $side) {
            $m = $b->style->get($side);
            self::assertInstanceOf(Keyword::class, $m, $side);
            self::assertSame('auto', $m->name, $side);
        }

        $c = $tables['c'] ?? null;
        self::assertNotNull($c);
        $cf = $c->style->get('float');
        self::assertInstanceOf(Keyword::class, $cf);
        self::assertSame('right', $cf->name);
    }

    public function testAuthorCssBeatsTableWidthAndAlignAttributes(): void
    {
        // Negative: these are presentational HINTS, so any author
        // declaration outranks them.
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; } table { display: table; }
             table { width: 50px; float: none; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><table width="150%" align="LEFT"></table></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $table = $this->findFirstByTag($box, 'table');
        self::assertNotNull($table);
        $w = $table->style->get('width');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $w);
        self::assertSame(50.0, $w->value);
        $float = $table->style->get('float');
        self::assertInstanceOf(Keyword::class, $float);
        self::assertSame('none', $float->name);
    }

    public function testTdNowrapAttributeSetsWhiteSpaceNowrap(): void
    {
        // HTML §15.3.10 — `<td nowrap>` applies unconditionally, even
        // when the cell also carries a fixed width.
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; } table { display: table; }
             tr { display: table-row; } td { display: table-cell; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><table><tr><td nowrap style="width:10px">x y</td>'
            . '<td>x y</td></tr></table></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $cells = [];
        $seen = new \SplObjectStorage();
        $stack = [$box];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n->element !== null
                && strtolower($n->element->localName) === 'td'
                && !$seen->contains($n->element)
            ) {
                $seen->attach($n->element);
                $cells[] = $n;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertCount(2, $cells);
        $ws = $cells[0]->style->get('white-space');
        self::assertInstanceOf(Keyword::class, $ws);
        self::assertSame('nowrap', $ws->name);
        $plain = $cells[1]->style->get('white-space');
        self::assertTrue(
            !$plain instanceof Keyword || $plain->name !== 'nowrap',
            'a cell without the attribute is unaffected',
        );
    }

    public function testOlTypeAttributeMapsToListStyleType(): void
    {
        // HTML §15.3.9 puts the `type` hints in the UA sheet rather than
        // in a post-cascade patch, so the sheet under test has to carry
        // the rule. The full matrix (both keyword families, both case
        // rules, the wrong-element cases) lives in ListTypeAttributeTest,
        // which runs against the SHIPPED UA stylesheet.
        $sheet = $this->css->parseStylesheet(<<<CSS
            html, body, ol, li { display: block; }
            li { display: list-item; }
            ol[type="A" s], li[type="A" s] { list-style-type: upper-alpha; }
        CSS, Origin::UserAgent);
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
        // `<ol type="A">` attribute, because the hint is UA-origin.
        $ua = $this->css->parseStylesheet(<<<CSS
            html, body, ol, li { display: block; }
            li { display: list-item; }
            ol[type="A" s], li[type="A" s] { list-style-type: upper-alpha; }
        CSS, Origin::UserAgent);
        $sheet = $this->css->parseStylesheet(
            'ol { list-style-type: lower-roman; }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument(
            '<html><body><ol type="A"><li>x</li></ol></body></html>',
        );
        $box = $this->generator->generate($doc, [$ua, $sheet]);
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

    public function testForeignSvgRootIsBlockifiedUnderAbsolutePosition(): void
    {
        // CSS Display 3 §2.7 — an out-of-flow box is blockified, and
        // CSS 2.1 §9.6 / §10.3.7 then place it from its containing
        // block plus `left` / `top`. The painter accepts a replaced
        // BlockBox and still routes <svg> to `paintInlineSvg`, so the
        // element keeps its SVG paint AND gains real abs-pos geometry
        // — which the background, border and `clip-path` painters all
        // read off `Box::$geometry`. (<math> stays atomic-inline; see
        // the sibling test.)
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
        self::assertInstanceOf(BlockBox::class, $svg);
    }

    public function testInFlowForeignSvgRootStaysAtomicInline(): void
    {
        // The blockification is scoped to OUT-OF-FLOW boxes: an
        // ordinary inline <svg> still generates an AtomicInlineBox so
        // it sits on the line with the text around it.
        $doc = $this->html->parseDocument(
            '<html><body><p>a<svg xmlns="http://www.w3.org/2000/svg" '
            . 'width="10" height="10"><rect width="10" height="10"/></svg>b</p>'
            . '</body></html>',
        );
        $box = $this->generator->generate($doc, [$this->uaSheet()]);
        $svg = $this->findFirstByTag($box, 'svg');
        self::assertNotNull($svg);
        self::assertNotInstanceOf(BlockBox::class, $svg);
    }

    public function testInlineFlexEstablishesAFlexFormattingContext(): void
    {
        // CSS Display 3 §2.6 — `inline-flex` and `flex` differ only in
        // their OUTER display type; both format their children as flex
        // items. Routing it to AtomicInlineBox meant flex layout never
        // ran, and because flex items are blockified the §9.2.1.1
        // inline-splits-around-block pass then promoted the atomic to an
        // anonymous BLOCK, stacking the items vertically.
        $sheet = $this->css->parseStylesheet(
            'div { display: inline-flex }',
            Origin::Author,
        );
        $doc = $this->html->parseDocument(
            '<html><body><div><span>a</span><span>b</span></div></body></html>',
        );
        $box = $this->generator->generate($doc, [$this->uaSheet(), $sheet]);
        $div = $this->findFirstByTag($box, 'div');
        self::assertInstanceOf(FlexBox::class, $div);
        // The outer display type is preserved on the cascade so
        // `flexContainerNeedsShrinkToFit` can still see it.
        $display = $div->style->get('display');
        self::assertInstanceOf(Keyword::class, $display);
        self::assertSame('inline-flex', $display->name);
    }

    /**
     * CSS 2.1 §17.2.1 "generate missing parents" — a bare
     * `display: table-cell` inside a plain block gets BOTH an anonymous
     * `table-row` and the anonymous `table` that row requires.
     */
    public function testBareTableCellInBlockGetsAnonymousTableAndRow(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .c { display: table-cell; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div><div class="c">a</div><div class="c">b</div></div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $tables = $this->collect($box, \Phpdftk\HtmlToPdf\Box\TableBox::class);
        self::assertCount(1, $tables, 'exactly one anonymous table is synthesised');
        $table = $tables[0];
        self::assertNull($table->element, 'the synthesised table is anonymous');
        $display = $table->style->get('display');
        self::assertInstanceOf(Keyword::class, $display);
        self::assertSame('table', $display->name);
        self::assertCount(1, $table->children);
        $row = $table->children[0];
        self::assertInstanceOf(\Phpdftk\HtmlToPdf\Box\TableRowBox::class, $row);
        self::assertNull($row->element);
        self::assertCount(2, $row->children, 'both cells land in the one anonymous row');
        foreach ($row->children as $cell) {
            self::assertInstanceOf(\Phpdftk\HtmlToPdf\Box\TableCellBox::class, $cell);
        }
    }

    /**
     * CSS 2.1 §17.2.1 — a misparented `table-row` needs only the table;
     * it must not be buried in an extra anonymous cell.
     */
    public function testBareTableRowInBlockGetsAnonymousTableOnly(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .r { display: table-row; }
             .c { display: table-cell; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div><div class="r"><div class="c">a</div></div></div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $tables = $this->collect($box, \Phpdftk\HtmlToPdf\Box\TableBox::class);
        self::assertCount(1, $tables);
        self::assertCount(1, $tables[0]->children);
        $row = $tables[0]->children[0];
        self::assertInstanceOf(\Phpdftk\HtmlToPdf\Box\TableRowBox::class, $row);
        self::assertNotNull($row->element, 'the authored row is reused, not re-wrapped');
    }

    /**
     * CSS 2.1 §17.2.1 — the run stops at a non-table sibling, so a block
     * between two cell groups divides them into two separate tables.
     */
    public function testBlockSiblingDividesMisparentedCellsIntoTwoTables(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div, p { display: block; } .c { display: table-cell; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div><div class="c">a</div><p>x</p>'
            . '<div class="c">b</div></div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $tables = $this->collect($box, \Phpdftk\HtmlToPdf\Box\TableBox::class);
        self::assertCount(2, $tables, 'the intervening block splits the run');
    }

    /**
     * CSS 2.1 §17.2.1 "remove irrelevant boxes" — whitespace-only text
     * between two internal table boxes is dropped, so it neither breaks
     * the run nor leaves a stray text box behind.
     */
    public function testWhitespaceBetweenMisparentedCellsDoesNotSplitTheTable(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .c { display: table-cell; }',
        );
        $doc = $this->html->parseDocument(
            "<html><body><div><div class=\"c\">a</div>\n  <div class=\"c\">b</div></div></body></html>",
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $tables = $this->collect($box, \Phpdftk\HtmlToPdf\Box\TableBox::class);
        self::assertCount(1, $tables);
        self::assertCount(1, $tables[0]->children);
        self::assertCount(2, $tables[0]->children[0]->children);
    }

    /**
     * Regression guard for the `<tbody>` trap: the UA sheet gives
     * `<tbody>` `display: block`, so a naive "rows in a non-table parent
     * are misparented" test would bury a second anonymous table inside
     * every real `<table>`.
     */
    public function testRealTableWithTbodyGrowsNoAnonymousTable(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; } table { display: table; }
             thead, tbody, tfoot { display: block; }
             tr { display: table-row; } td { display: table-cell; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><table><tbody><tr><td>a</td></tr></tbody></table></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $tables = $this->collect($box, \Phpdftk\HtmlToPdf\Box\TableBox::class);
        self::assertCount(1, $tables, 'only the authored <table> exists');
        self::assertNotNull($tables[0]->element);
    }

    /**
     * A `table-cell` directly inside a real `<table>` still takes the
     * pre-existing "generate missing children" path (one anonymous row),
     * not a second nested table.
     */
    public function testBareCellInsideRealTableStillGetsOneAnonymousRow(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .t { display: table; }
             .c { display: table-cell; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div class="t"><div class="c">a</div></div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $tables = $this->collect($box, \Phpdftk\HtmlToPdf\Box\TableBox::class);
        self::assertCount(1, $tables);
        self::assertNotNull($tables[0]->element);
        self::assertCount(1, $tables[0]->children);
        self::assertInstanceOf(\Phpdftk\HtmlToPdf\Box\TableRowBox::class, $tables[0]->children[0]);
    }

    /**
     * A misparented row group brings its own anonymous table, and the
     * rows inside it are left alone.
     */
    public function testMisparentedRowGroupGetsAnonymousTable(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .g { display: table-row-group; }
             .r { display: table-row; } .c { display: table-cell; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div><div class="g"><div class="r">'
            . '<div class="c">a</div></div></div></div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $tables = $this->collect($box, \Phpdftk\HtmlToPdf\Box\TableBox::class);
        self::assertCount(1, $tables);
        self::assertNull($tables[0]->element);
        $group = $tables[0]->children[0];
        self::assertSame('table-row-group', strtolower(
            ($group->style->get('display') instanceof Keyword)
                ? $group->style->get('display')->name
                : '',
        ));
        self::assertInstanceOf(\Phpdftk\HtmlToPdf\Box\TableRowBox::class, $group->children[0]);
    }

    /** A document with no internal table boxes grows no anonymous table. */
    public function testPlainBlockContentGrowsNoAnonymousTable(): void
    {
        $doc = $this->html->parseDocument(
            '<html><body><div><p>a</p><span>b</span></div></body></html>',
        );
        $box = $this->generator->generate($doc, [$this->uaSheet()]);
        self::assertNotNull($box);
        self::assertCount(0, $this->collect($box, \Phpdftk\HtmlToPdf\Box\TableBox::class));
    }

    /**
     * `display: inline-table` generates an AtomicInlineBox, not a
     * TableBox — but it IS a table object, so its rows are not
     * misparented and must not gain an anonymous table.
     */
    public function testInlineTableRowsGrowNoAnonymousTable(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .t { display: inline-table; }
             .r { display: table-row; } .c { display: table-cell; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div class="t"><div class="r">'
            . '<div class="c">a</div></div></div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        self::assertCount(
            0,
            $this->collect($box, \Phpdftk\HtmlToPdf\Box\TableBox::class),
            'an inline-table is already the table its rows need',
        );
    }

    /**
     * A stray `display: table-caption` does NOT get an anonymous table.
     * §17.2.1 says it should, but the caption would then have to be laid
     * out as that table's caption, which this renderer cannot do yet —
     * see the note on `needsAnonymousTableParent`.
     */
    public function testStrayTableCaptionGrowsNoAnonymousTableYet(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .cap { display: table-caption; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div><div class="cap">a</div></div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        self::assertCount(0, $this->collect($box, \Phpdftk\HtmlToPdf\Box\TableBox::class));
    }

    /**
     * CSS 2.1 §9.2.3 — a `display: run-in` box followed by a block box
     * becomes that block's first inline child.
     */
    public function testRunInRunsIntoTheFollowingBlock(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .r { display: run-in; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div class="r">head</div><div id="t">tail</div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        // The run-in no longer generates a sibling box.
        self::assertCount(1, $body->children, 'run-in left the sibling list');
        $target = $body->children[0];
        self::assertInstanceOf(BlockBox::class, $target);
        self::assertCount(2, $target->children);
        $run = $target->children[0];
        self::assertInstanceOf(InlineBox::class, $run, 'run-in became an inline box');
        self::assertSame('r', $run->element?->getAttribute('class'));
        self::assertSame(
            'inline',
            ($run->style->get('display') instanceof Keyword)
                ? strtolower($run->style->get('display')->name)
                : null,
        );
    }

    /**
     * A REPLACED run-in has no child boxes to carry across, so it has to
     * become an atomic inline — wrapping it in a plain `InlineBox` drops
     * the element's rendering entirely.
     */
    public function testReplacedRunInBecomesAnAtomicInline(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } img { display: inline-block; }'
            . ' .r { display: run-in; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><img class="r" src="' . self::PNG_100X100 . '">'
            . '<div id="t">tail</div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        $target = $body->children[0];
        self::assertInstanceOf(BlockBox::class, $target);
        self::assertInstanceOf(AtomicInlineBox::class, $target->children[0]);
        self::assertSame('img', $target->children[0]->element?->localName);
    }

    /**
     * Collapsible whitespace between the run-in and its block doesn't
     * break the association (CSS 2.1 §9.2.3).
     */
    public function testRunInSkipsCollapsibleWhitespaceSibling(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .r { display: run-in; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div class="r">head</div>   <div>tail</div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        self::assertCount(1, $this->collect($body, InlineBox::class));
    }

    /**
     * `white-space: pre` makes the space between rendered content, so the
     * run-in has something after it and stays a block.
     */
    public function testRunInStaysBlockWhenPreservedWhitespaceFollows(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } body { white-space: pre; }'
            . ' .r { display: run-in; white-space: normal; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div class="r">head</div> <div>tail</div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        self::assertCount(0, $this->collect($body, InlineBox::class));
    }

    /** A run-in containing a block box becomes a block box itself. */
    public function testRunInContainingABlockStaysBlock(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div, p { display: block; } .r { display: run-in; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div class="r">head<p>x</p></div><div>tail</div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        self::assertCount(2, $body->children, 'run-in kept its own box');
        self::assertSame(
            'block',
            strtolower($body->children[0]->style->get('display')->name ?? ''),
        );
    }

    /** A run-in followed by another run-in has no block to join. */
    public function testRunInFollowedByRunInStaysBlock(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .r { display: run-in; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div class="r">a</div><div class="r">b</div><div>tail</div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        // The first stays a block, the second runs into the trailing block.
        self::assertCount(2, $body->children);
        self::assertInstanceOf(BlockBox::class, $body->children[0]);
        self::assertCount(1, $this->collect($body, InlineBox::class));
    }

    /** An inline-level sibling is not a block box — no run-in. */
    public function testRunInBeforeInlineSiblingStaysBlock(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } span { display: inline; }'
            . ' .r { display: run-in; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div class="r">a</div><span>b</span><div>tail</div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        self::assertInstanceOf(BlockBox::class, $body->children[0]);
        self::assertSame(
            'block',
            strtolower($body->children[0]->style->get('display')->name ?? ''),
        );
    }

    /** An out-of-flow sibling between run-in and block is skipped. */
    public function testRunInSkipsFloatedSibling(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .r { display: run-in; }'
            . ' .f { float: left; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div class="r">a</div><div class="f"></div><div>tail</div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        self::assertCount(1, $this->collect($body, InlineBox::class));
    }

    /**
     * A run-in joining a block whose own children are block-level gets an
     * anonymous block of its own — the §3.4 all-inline / all-block
     * invariant must survive the insertion.
     */
    public function testRunInIntoBlockContainerGetsAnonymousWrapper(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div, p { display: block; } .r { display: run-in; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div class="r">head</div><div id="t"><p>tail</p></div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        $target = $body->children[0];
        self::assertCount(2, $target->children);
        self::assertInstanceOf(AnonymousBlockBox::class, $target->children[0]);
        self::assertInstanceOf(InlineBox::class, $target->children[0]->children[0]);
    }

    /** A run-in with nothing after it is just a block. */
    public function testTrailingRunInStaysBlock(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; } .r { display: run-in; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div>head</div><div class="r">tail</div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        self::assertCount(2, $body->children);
        self::assertCount(0, $this->collect($body, InlineBox::class));
    }

    /** An out-of-flow run-in blockifies (CSS Display 3 §2.7). */
    public function testOutOfFlowRunInBlockifies(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }'
            . ' .r { display: run-in; position: absolute; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><div class="r">head</div><div>tail</div></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $body = $this->findFirstByTag($box, 'body');
        self::assertNotNull($body);
        self::assertCount(2, $body->children);
        self::assertSame(
            'block',
            strtolower($body->children[0]->style->get('display')->name ?? ''),
        );
    }

    /** A 100x50 green PNG as a data URL — a 2:1 replaced element. */
    private const PNG_100X50 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAAAy'
        . 'CAMAAACd646MAAAAA1BMVEUAgACc+aWRAAAAHElEQVRYw+3BMQEAAADCoPVPbQ0PoAAAAACAPwMT'
        . 'ugAB3yW6awAAAABJRU5ErkJggg==';

    /** A 100x100 green PNG as a data URL — a 1:1 replaced element. */
    private const PNG_100X100 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABk'
        . 'CAMAAABHPGVmAAAAA1BMVEUAgACc+aWRAAAAIUlEQVRo3u3BgQAAAADDoPlTX+EAVQEAAAAAAAAA'
        . 'AACPASd0AAG4NzwVAAAAAElFTkSuQmCC';

    /**
     * @return array{0: float, 1: float} the cascade's used width / height
     *                                   for the single `<img>` in `$style`
     */
    private function replacedUsedSize(string $src, string $style): array
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; } img { display: inline-block; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><p><img src="' . $src . '" style="' . $style . '"></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $img = $this->findFirstByTag($box, 'img');
        self::assertNotNull($img);
        $w = $img->style->get('width');
        $h = $img->style->get('height');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $w);
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $h);
        return [$w->value, $h->value];
    }

    /**
     * CSS 2.1 §10.4 — a single `max-width` violation scales the other
     * axis through the intrinsic ratio.
     */
    public function testReplacedMaxWidthScalesHeightThroughRatio(): void
    {
        [$w, $h] = $this->replacedUsedSize(self::PNG_100X50, 'max-width: 50px');
        self::assertEqualsWithDelta(50.0, $w, 0.01);
        self::assertEqualsWithDelta(25.0, $h, 0.01);
    }

    /**
     * §10.4 row "w < min-w and h > max-h" — the ratio is ABANDONED and
     * both constraints are honoured exactly. The old sequential
     * max-then-min clamp produced a ratio-preserving box instead.
     */
    public function testReplacedMinWidthWithMaxHeightDropsTheRatio(): void
    {
        [$w, $h] = $this->replacedUsedSize(
            self::PNG_100X100,
            'min-width: 150px; max-height: 40px',
        );
        self::assertEqualsWithDelta(150.0, $w, 0.01);
        self::assertEqualsWithDelta(40.0, $h, 0.01);
    }

    /** §10.4 row "w > max-w and h < min-h" — same, mirrored. */
    public function testReplacedMaxWidthWithMinHeightDropsTheRatio(): void
    {
        [$w, $h] = $this->replacedUsedSize(
            self::PNG_100X100,
            'max-width: 40px; min-height: 150px',
        );
        self::assertEqualsWithDelta(40.0, $w, 0.01);
        self::assertEqualsWithDelta(150.0, $h, 0.01);
    }

    /**
     * §10.4 row "w > max-w and h > max-h" — the axis with the SMALLER
     * ratio wins; the other is floored by its minimum. 100x50 against
     * max 50x40: 50/100 = 0.5 <= 40/50 = 0.8, so width wins and the
     * height follows the ratio to 25.
     */
    public function testReplacedBothMaximaViolatedPicksTheTighterAxis(): void
    {
        [$w, $h] = $this->replacedUsedSize(
            self::PNG_100X50,
            'max-width: 50px; max-height: 40px',
        );
        self::assertEqualsWithDelta(50.0, $w, 0.01);
        self::assertEqualsWithDelta(25.0, $h, 0.01);
    }

    /** §10.4 row "w < min-w and h < min-h" — mirrored for the minima. */
    public function testReplacedBothMinimaViolatedPicksTheTighterAxis(): void
    {
        [$w, $h] = $this->replacedUsedSize(
            self::PNG_100X50,
            'min-width: 200px; min-height: 200px',
        );
        // 200/100 = 2 <= 200/50 = 4, so the HEIGHT is the binding
        // minimum and the width follows the ratio to 400.
        self::assertEqualsWithDelta(400.0, $w, 0.01);
        self::assertEqualsWithDelta(200.0, $h, 0.01);
    }

    /**
     * CSS Sizing 3 §6.2 — under `box-sizing: border-box` the min / max
     * properties describe the BORDER box, so the constraint applies to
     * content + padding and the written-back size is the border box too.
     */
    public function testReplacedMinMaxRespectsBorderBoxSizing(): void
    {
        [$w, $h] = $this->replacedUsedSize(
            self::PNG_100X100,
            'box-sizing: border-box; padding: 10px; max-width: 60px',
        );
        // 60px border box = 40px content; the 1:1 ratio makes the content
        // height 40 too, and the written height adds the padding back.
        self::assertEqualsWithDelta(60.0, $w, 0.01);
        self::assertEqualsWithDelta(60.0, $h, 0.01);
    }

    /**
     * A `<canvas>` is sized from its bitmap attributes, and those go
     * through the same §10.4 table: `max-width: 120px` +
     * `max-height: 100px` on an 8000x8000 canvas is 100x100, not the
     * 120x100 that clamping each axis independently produces.
     */
    public function testCanvasNaturalSizeGoesThroughTheConstraintTable(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; } canvas { display: inline-block; }',
        );
        $doc = $this->html->parseDocument(
            '<html><body><p><canvas width="8000" height="8000"'
            . ' style="max-width: 120px; max-height: 100px"></canvas></p></body></html>',
        );
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        $canvas = $this->findFirstByTag($box, 'canvas');
        self::assertNotNull($canvas);
        $w = $canvas->style->get('width');
        $h = $canvas->style->get('height');
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $w);
        self::assertInstanceOf(\Phpdftk\Css\Value\Length::class, $h);
        self::assertEqualsWithDelta(100.0, $w->value, 0.01);
        self::assertEqualsWithDelta(100.0, $h->value, 0.01);
    }

    /** No violation leaves the natural size untouched. */
    public function testReplacedWithSatisfiedConstraintsKeepsNaturalSize(): void
    {
        [$w, $h] = $this->replacedUsedSize(
            self::PNG_100X50,
            'min-width: 10px; max-width: 500px; min-height: 5px; max-height: 500px',
        );
        self::assertEqualsWithDelta(100.0, $w, 0.01);
        self::assertEqualsWithDelta(50.0, $h, 0.01);
    }

    /**
     * Collect every box of `$class` in the tree, in document order.
     *
     * @template T of \Phpdftk\HtmlToPdf\Box\Box
     * @param  class-string<T> $class
     * @return list<T>
     */
    private function collect(\Phpdftk\HtmlToPdf\Box\Box $root, string $class): array
    {
        $out = [];
        $stack = [$root];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n instanceof $class) {
                $out[] = $n;
            }
            foreach (array_reverse($n->children) as $c) {
                array_unshift($stack, $c);
            }
        }
        return $out;
    }
}
