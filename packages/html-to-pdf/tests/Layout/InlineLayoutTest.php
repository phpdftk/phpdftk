<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Layout;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\FontParser\OpenTypeData;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\Layout\BlockLayout;
use Phpdftk\HtmlToPdf\Layout\LayoutContext;
use Phpdftk\Html\Parser as HtmlParser;
use PHPUnit\Framework\TestCase;

final class InlineLayoutTest extends TestCase
{
    private const string FONT_PATH = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';

    private CssParser $css;
    private HtmlParser $html;
    private BoxGenerator $generator;
    private BlockLayout $layout;
    private ?OpenTypeData $font;

    protected function setUp(): void
    {
        $this->css = new CssParser();
        $this->html = new HtmlParser();
        $cascade = new Cascade(PropertyRegistry::default());
        $this->generator = new BoxGenerator($cascade);
        $this->layout = new BlockLayout($cascade);
        if (is_file(self::FONT_PATH)) {
            $this->font = (new OpenTypeParser(self::FONT_PATH))->parse();
        } else {
            $this->font = null;
        }
    }

    private function buildTree(string $html, string $css): Box
    {
        $doc = $this->html->parseDocument($html);
        $sheet = $this->css->parseStylesheet($css, Origin::UserAgent);
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        return $box;
    }

    private function defaultContext(float $width = 600.0): LayoutContext
    {
        return new LayoutContext(
            containingBlockWidth: $width,
            containingBlockHeight: 800.0,
            originX: 0.0,
            originY: 0.0,
            lengthContext: new LengthContext(),
            defaultFont: $this->font,
        );
    }

    public function testNoFontProducesZeroHeightInlineContent(): void
    {
        // With defaultFont null, inline layout falls back to zero height
        // so block layout still completes.
        $box = $this->buildTree(
            '<html><body><p>Hello world</p></body></html>',
            'html, body, p { display: block; } span { display: inline; }',
        );
        $ctx = new LayoutContext(600, 800, 0, 0, new LengthContext(), defaultFont: null);
        $this->layout->layout($box, $ctx);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertSame(0.0, $p->geometry->height);
        self::assertSame([], $p->lineBoxes);
    }

    public function testSingleLineProducesOneLineBox(): void
    {
        $this->skipIfNoFont();
        // Mongolian text — shaped by the loaded font. Use small input that
        // fits well within 600px.
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->lineBoxes);
        self::assertGreaterThan(0.0, $p->lineBoxes[0]->totalWidth());
    }

    public function testLongTextWrapsToMultipleLines(): void
    {
        $this->skipIfNoFont();
        // 60 Mongolian letter-A glyphs in a narrow box guarantees wrapping.
        $letters = str_repeat("\u{1820} ", 60);
        $box = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertGreaterThan(1, count($p->lineBoxes), 'narrow width should produce multiple lines');
        // Total height equals sum of line-box heights.
        $total = 0.0;
        foreach ($p->lineBoxes as $line) {
            $total += $line->height;
        }
        self::assertEqualsWithDelta($p->geometry->height, $total, 0.001);
    }

    public function testHardBreakSplitsLine(): void
    {
        $this->skipIfNoFont();
        // CSS Text 3 §4.1.1: `\n` is only a hard break under `pre` / `pre-wrap`
        // / `pre-line` — under the default `normal`, it collapses to a space.
        $box = $this->buildTree(
            "<html><body><p>" . "\u{1820}\u{1820}\n\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; } p { white-space: pre-line; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertGreaterThanOrEqual(2, count($p->lineBoxes));
    }

    public function testInlineTextDecorationPropagatesToFragments(): void
    {
        $this->skipIfNoFont();
        // `<a>` carries `text-decoration: underline` via the UA stylesheet.
        // The fragment generated from its child text should carry the
        // underline keyword so the painter draws the line — even when the
        // outer block's text-decoration-line is `none`.
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}" . '<a href="#">' . "\u{1820}\u{1820}" . '</a>' . "\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; } a { display: inline; text-decoration-line: underline; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $fragments = $p->lineBoxes[0]->fragments;
        // Find the inner fragment(s) — those with non-empty decorationLines.
        $underlined = array_filter(
            $fragments,
            static fn($f): bool => in_array('underline', $f->decorationLines, true),
        );
        self::assertNotEmpty($underlined, 'inline `<a>` underline propagates to fragments');
        // Outer fragments (the ones around the `<a>`) should NOT carry the
        // underline since the block sets `none`.
        $bare = array_filter(
            $fragments,
            static fn($f): bool => !in_array('underline', $f->decorationLines, true),
        );
        self::assertNotEmpty($bare, 'fragments outside `<a>` are not underlined');
    }

    public function testTextOverflowEllipsisTruncatesOverflowingLine(): void
    {
        $this->skipIfNoFont();
        // 30 Mongolian letters in a tight nowrap box should overflow; with
        // `text-overflow: ellipsis` the line is truncated and ends with the
        // U+2026 glyph.
        $letters = str_repeat("\u{1820}", 30);
        $box = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; } p { white-space: nowrap; text-overflow: ellipsis; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->lineBoxes, 'nowrap keeps it on one line');
        // The line fits within the available width with the ellipsis added.
        self::assertLessThanOrEqual(80.0, $p->lineBoxes[0]->totalWidth() + 0.001);
        $last = $p->lineBoxes[0]->fragments[array_key_last($p->lineBoxes[0]->fragments)];
        $lastGlyphSource = '';
        foreach ($last->shapedRun->glyphs as $g) {
            $lastGlyphSource .= substr("\u{2026}", $g->sourceOffset, $g->sourceLength);
        }
        self::assertSame("\u{2026}", $lastGlyphSource, 'last fragment is the ellipsis');
    }

    public function testTextOverflowClipDefaultDoesNotTruncate(): void
    {
        $this->skipIfNoFont();
        // Same content + nowrap without text-overflow → content overflows,
        // no truncation applied. Confirm the last fragment is still source
        // content, not the ellipsis.
        $letters = str_repeat("\u{1820}", 30);
        $box = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; } p { white-space: nowrap; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertGreaterThan(80.0, $p->lineBoxes[0]->totalWidth(), 'default clip lets content overflow');
    }

    public function testMixedFontSizeFragmentsShapeAtTheirOwnSize(): void
    {
        $this->skipIfNoFont();
        // A `<span style="font-size: 2em">` child should produce a fragment
        // whose shapedRun.fontSizePt is double the parent's.
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}" . '<span>' . "\u{1820}" . '</span></p></body></html>',
            'html, body, p { display: block; } span { display: inline; font-size: 2em; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $fragments = $p->lineBoxes[0]->fragments;
        self::assertGreaterThanOrEqual(2, count($fragments));
        $small = $fragments[0]->shapedRun->fontSizePt;
        $large = $fragments[count($fragments) - 1]->shapedRun->fontSizePt;
        self::assertEqualsWithDelta($small * 2.0, $large, 0.001, '<span> doubles the font size');
    }

    public function testLargerInlineExpandsLineHeight(): void
    {
        $this->skipIfNoFont();
        // The line height should grow to accommodate a fragment with a
        // larger font size — the line is no longer parent-only.
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}" . '<span>' . "\u{1820}" . '</span></p></body></html>',
            'html, body, p { display: block; font-size: 10px; } span { display: inline; font-size: 30px; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        // 30px × 1.2 = 36 expected line height (well above the 12 the
        // parent's 10px font would produce alone).
        self::assertEqualsWithDelta(36.0, $p->lineBoxes[0]->height, 0.001);
    }

    public function testVerticalAlignSuperLiftsFragment(): void
    {
        $this->skipIfNoFont();
        // `<sup>` defaults to `vertical-align: super` per the UA stylesheet
        // CSS. The span fragment should land with a negative `baselineShift`
        // (lifted in layout-space → higher on the page in PDF-space).
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}" . '<sup>' . "\u{1820}" . '</sup></p></body></html>',
            'html, body, p { display: block; } span, sup { display: inline; } '
                . 'sup { vertical-align: super; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertGreaterThanOrEqual(2, count($p->lineBoxes[0]->fragments));
        $lifted = $p->lineBoxes[0]->fragments[1];
        self::assertLessThan(0.0, $lifted->baselineShift, 'super shifts above baseline');
    }

    public function testVerticalAlignSubDropsFragment(): void
    {
        $this->skipIfNoFont();
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}" . '<sub>' . "\u{1820}" . '</sub></p></body></html>',
            'html, body, p { display: block; } span, sub { display: inline; } '
                . 'sub { vertical-align: sub; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $dropped = $p->lineBoxes[0]->fragments[1];
        self::assertGreaterThan(0.0, $dropped->baselineShift, 'sub shifts below baseline');
    }

    public function testWordBreakBreakAllSplitsAtEveryCodepoint(): void
    {
        $this->skipIfNoFont();
        // 8 Mongolian glyphs in a 30-pt wide column. Default `word-break:
        // normal` keeps them as one segment that overflows; `break-all`
        // splits at every codepoint so the layout wraps into multiple
        // lines.
        $letters = str_repeat("\u{1820}", 8);
        $boxA = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $boxB = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; } p { word-break: break-all; }',
        );
        $this->layout->layout($boxA, $this->defaultContext(30.0));
        $this->layout->layout($boxB, $this->defaultContext(30.0));
        $pA = $this->find($boxA, 'p');
        $pB = $this->find($boxB, 'p');
        self::assertNotNull($pA);
        self::assertNotNull($pB);
        // Without break-all the 8 glyphs stay on one line; with break-all
        // they wrap into multiple lines.
        self::assertGreaterThan(count($pA->lineBoxes), count($pB->lineBoxes));
    }

    public function testWordSpacingWidensWhitespaceSegments(): void
    {
        $this->skipIfNoFont();
        // Two glyphs separated by a single space + `word-spacing: 8px` →
        // line widens by exactly 8 user-space units vs the no-spacing
        // baseline (one whitespace separator in the text).
        $boxA = $this->buildTree(
            '<html><body><p>' . "\u{1820} \u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $boxB = $this->buildTree(
            '<html><body><p>' . "\u{1820} \u{1820}" . '</p></body></html>',
            'html, body, p { display: block; } p { word-spacing: 8px; }',
        );
        $this->layout->layout($boxA, $this->defaultContext());
        $this->layout->layout($boxB, $this->defaultContext());
        $pA = $this->find($boxA, 'p');
        $pB = $this->find($boxB, 'p');
        self::assertNotNull($pA);
        self::assertNotNull($pB);
        $delta = $pB->lineBoxes[0]->totalWidth() - $pA->lineBoxes[0]->totalWidth();
        self::assertEqualsWithDelta(8.0, $delta, 0.001, 'one space + 8px word-spacing');
    }

    public function testLetterSpacingAddsAdvancePerGlyph(): void
    {
        $this->skipIfNoFont();
        // `letter-spacing: 5px` adds 5 user-space units to every glyph's
        // advance. Two glyphs → total width grows by 2 × 5 = 10.
        $boxA = $this->buildTree(
            '<html><body><p>' . "\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $boxB = $this->buildTree(
            '<html><body><p>' . "\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; } p { letter-spacing: 5px; }',
        );
        $this->layout->layout($boxA, $this->defaultContext());
        $this->layout->layout($boxB, $this->defaultContext());
        $pA = $this->find($boxA, 'p');
        $pB = $this->find($boxB, 'p');
        self::assertNotNull($pA);
        self::assertNotNull($pB);
        $delta = $pB->lineBoxes[0]->totalWidth() - $pA->lineBoxes[0]->totalWidth();
        self::assertEqualsWithDelta(10.0, $delta, 0.001, '2 glyphs × 5px letter-spacing');
    }

    public function testLineHeightNumericMultipliesFontSize(): void
    {
        $this->skipIfNoFont();
        // CSS Inline 3 §3: `line-height: 2` produces a line box 2× the
        // font-size tall. Use a deliberately short text to keep wrapping
        // out of the picture.
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; } p { font-size: 20px; line-height: 2; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->lineBoxes);
        self::assertEqualsWithDelta(40.0, $p->lineBoxes[0]->height, 0.001);
    }

    public function testLineHeightAbsoluteLengthOverridesFontSize(): void
    {
        $this->skipIfNoFont();
        // `line-height: 30px` should produce a 30-unit line regardless of
        // font-size.
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; } p { font-size: 16px; line-height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertEqualsWithDelta(30.0, $p->lineBoxes[0]->height, 0.001);
    }

    public function testInlineStyleAttributeFlowsIntoLayout(): void
    {
        $this->skipIfNoFont();
        // End-to-end check that `style="..."` on an HTML element drives
        // layout — `style="text-indent: 25px"` should produce the same first-
        // fragment offset as the same rule declared in the stylesheet.
        $box = $this->buildTree(
            '<html><body><p style="text-indent: 25px">' . "\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $first = $p->lineBoxes[0]->fragments[0];
        self::assertEqualsWithDelta(25.0, $first->x, 0.001);
    }

    public function testTextIndentOffsetsFirstFragment(): void
    {
        $this->skipIfNoFont();
        // CSS Text 3 §3.1: `text-indent` shifts the first inline box of the
        // first formatted line. A 20px indent should push the first
        // fragment's left edge by exactly 20 user-space units; subsequent
        // lines (if any) start at 0.
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; } p { text-indent: 20px; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->lineBoxes);
        $first = $p->lineBoxes[0]->fragments[0];
        self::assertEqualsWithDelta(20.0, $first->x, 0.001, 'first fragment shifts by text-indent');
    }

    public function testTextIndentDoesNotShiftSecondLine(): void
    {
        $this->skipIfNoFont();
        // Wrap a paragraph across two lines and confirm only the first line
        // carries the indent.
        $letters = str_repeat("\u{1820} ", 30);
        $box = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; } p { text-indent: 15px; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertGreaterThan(1, count($p->lineBoxes));
        self::assertEqualsWithDelta(15.0, $p->lineBoxes[0]->fragments[0]->x, 0.001);
        self::assertEqualsWithDelta(0.0, $p->lineBoxes[1]->fragments[0]->x, 0.001);
    }

    public function testTextTransformUppercaseProducesCapitalGlyphs(): void
    {
        $this->skipIfNoFont();
        // `text-transform: uppercase` runs before shaping, so a lowercase
        // source word and the same text manually upper-cased produce
        // identical line widths under the same font.
        $boxLower = $this->buildTree(
            '<html><body><p>abc</p></body></html>',
            'html, body, p { display: block; } p { text-transform: uppercase; }',
        );
        $boxUpper = $this->buildTree(
            '<html><body><p>ABC</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($boxLower, $this->defaultContext());
        $this->layout->layout($boxUpper, $this->defaultContext());
        $pLower = $this->find($boxLower, 'p');
        $pUpper = $this->find($boxUpper, 'p');
        self::assertNotNull($pLower);
        self::assertNotNull($pUpper);
        self::assertEqualsWithDelta(
            $pUpper->lineBoxes[0]->totalWidth(),
            $pLower->lineBoxes[0]->totalWidth(),
            0.001,
            'uppercase transform produces same width as pre-uppercased source',
        );
    }

    public function testBrElementForcesHardBreakUnderNormal(): void
    {
        $this->skipIfNoFont();
        // `<br>` is a mandatory line break that survives `white-space: normal`'s
        // collapsing. Two glyphs on either side of a `<br>` produce two lines
        // even though `normal` would otherwise collapse `\n` into a space.
        $box = $this->buildTree(
            '<html><body><p>'
                . "\u{1820}\u{1820}" . '<br>' . "\u{1820}\u{1820}"
                . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertGreaterThanOrEqual(2, count($p->lineBoxes), '<br> forces a break');
    }

    public function testInlineChildrenPlaceOnSameLine(): void
    {
        $this->skipIfNoFont();
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820} " . '<span>' . "\u{1820}" . '</span></p></body></html>',
            'html, body, p { display: block; } span { display: inline; }',
        );
        $this->layout->layout($box, $this->defaultContext());
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->lineBoxes, 'short content fits on one line');
        self::assertGreaterThan(1, count($p->lineBoxes[0]->fragments), 'multiple fragments share the line');
    }

    public function testNormalCollapsesMultiSpace(): void
    {
        $this->skipIfNoFont();
        // Two glyphs with 5 spaces between → under `normal`, collapsed to
        // one space. So the resulting line's right-edge should match a 3-
        // glyph render (X, space, X), not a 7-glyph one.
        $box5 = $this->buildTree(
            '<html><body><p>' . "\u{1820}     \u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $box1 = $this->buildTree(
            '<html><body><p>' . "\u{1820} \u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box5, $this->defaultContext(600.0));
        $this->layout->layout($box1, $this->defaultContext(600.0));
        $p5 = $this->find($box5, 'p');
        $p1 = $this->find($box1, 'p');
        self::assertNotNull($p5);
        self::assertNotNull($p1);
        self::assertEqualsWithDelta(
            $p1->lineBoxes[0]->totalWidth(),
            $p5->lineBoxes[0]->totalWidth(),
            0.001,
            'multi-space collapses to single space under `normal`',
        );
    }

    public function testPrePreservesLeadingWhitespace(): void
    {
        $this->skipIfNoFont();
        // Normal whitespace handling collapses the leading spaces; `pre`
        // preserves them. The first fragment's width should reflect the
        // shaped leading spaces.
        $box = $this->buildTree(
            '<html><body><p>   ' . "\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; } p { white-space: pre; }',
        );
        $this->layout->layout($box, $this->defaultContext(400.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->lineBoxes);
        $line = $p->lineBoxes[0];
        $first = $line->fragments[0];
        // First fragment should be the leading whitespace (3 spaces).
        self::assertGreaterThan(0.0, $first->width, 'leading spaces preserved as a real fragment');
    }

    public function testNormalCollapsesLeadingWhitespace(): void
    {
        $this->skipIfNoFont();
        // Without `pre`, leading whitespace collapses — the first fragment
        // is the Mongolian letter, not the spaces.
        $box = $this->buildTree(
            '<html><body><p>   ' . "\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultContext(400.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->lineBoxes);
        $first = $p->lineBoxes[0]->fragments[0];
        // First fragment is the letter (single glyph), not leading spaces.
        self::assertCount(1, $first->shapedRun->glyphs);
    }

    public function testNowrapKeepsContentOnOneLine(): void
    {
        $this->skipIfNoFont();
        // Wide enough to wrap many times under normal whitespace handling.
        $letters = str_repeat("\u{1820} ", 60);
        $box = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; } p { white-space: nowrap; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->lineBoxes, 'nowrap suppresses soft wrapping');
    }

    public function testCenterAlignShiftsFragments(): void
    {
        $this->skipIfNoFont();
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; } p { text-align: center; }',
        );
        $this->layout->layout($box, $this->defaultContext(400.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->lineBoxes);
        $line = $p->lineBoxes[0];
        $first = $line->fragments[0];
        $rightEdge = $first->x + $first->width;
        // For a single fragment, center means leftSlack === rightSlack.
        $leftSlack = $first->x;
        $rightSlack = 400.0 - $rightEdge;
        self::assertEqualsWithDelta($leftSlack, $rightSlack, 0.001, 'centered fragment has equal slack each side');
    }

    public function testRightAlignShiftsToRightEdge(): void
    {
        $this->skipIfNoFont();
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; } p { text-align: right; }',
        );
        $this->layout->layout($box, $this->defaultContext(400.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $line = $p->lineBoxes[0];
        $last = $line->fragments[array_key_last($line->fragments)];
        $rightEdge = $last->x + $last->width;
        self::assertEqualsWithDelta(400.0, $rightEdge, 0.001, 'right-aligned line ends at availableWidth');
        self::assertGreaterThan(0.0, $line->fragments[0]->x, 'first fragment is shifted off the left edge');
    }

    public function testLeftAlignNoOpDefault(): void
    {
        $this->skipIfNoFont();
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultContext(400.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $first = $p->lineBoxes[0]->fragments[0];
        self::assertSame(0.0, $first->x);
    }

    public function testTabSizeDefaultExpandsTabToEightSpacesInPre(): void
    {
        // CSS Text 3 §11.2 initial value is 8 — a U+0009 in a
        // `<pre>` should render as 8 spaces of advance.
        $this->skipIfNoFont();
        $box = $this->buildTree(
            "<html><body><pre>a\tb</pre></body></html>",
            'html, body, pre { display: block; white-space: pre; }',
        );
        $this->layout->layout($box, $this->defaultContext(400.0));
        $pre = $this->find($box, 'pre');
        self::assertNotNull($pre);
        // Render the same text with explicit "a" + 8 spaces + "b" to
        // get a baseline width, then compare.
        $reference = $this->buildTree(
            '<html><body><pre>a' . str_repeat(' ', 8) . 'b</pre></body></html>',
            'html, body, pre { display: block; white-space: pre; }',
        );
        $this->layout->layout($reference, $this->defaultContext(400.0));
        $refPre = $this->find($reference, 'pre');
        self::assertNotNull($refPre);
        self::assertEqualsWithDelta(
            $refPre->lineBoxes[0]->totalWidth(),
            $pre->lineBoxes[0]->totalWidth(),
            0.5,
        );
    }

    public function testTabSizeFourShrinksTabWidth(): void
    {
        // Explicit `tab-size: 4` halves the default tab width.
        $this->skipIfNoFont();
        $defaultBox = $this->buildTree(
            "<html><body><pre>a\tb</pre></body></html>",
            'html, body, pre { display: block; white-space: pre; }',
        );
        $this->layout->layout($defaultBox, $this->defaultContext(400.0));
        $defaultPre = $this->find($defaultBox, 'pre');

        $smallerBox = $this->buildTree(
            "<html><body><pre>a\tb</pre></body></html>",
            'html, body, pre { display: block; white-space: pre; tab-size: 4; }',
        );
        $this->layout->layout($smallerBox, $this->defaultContext(400.0));
        $smallerPre = $this->find($smallerBox, 'pre');
        self::assertNotNull($defaultPre);
        self::assertNotNull($smallerPre);
        // Smaller tab → narrower line.
        self::assertLessThan(
            $defaultPre->lineBoxes[0]->totalWidth(),
            $smallerPre->lineBoxes[0]->totalWidth(),
        );
    }

    public function testTabSizeZeroDropsTabsEntirelyInPre(): void
    {
        // `tab-size: 0` — tabs vanish (zero advance). Useful when
        // authors want to align with their own padding.
        $this->skipIfNoFont();
        $box = $this->buildTree(
            "<html><body><pre>a\tb</pre></body></html>",
            'html, body, pre { display: block; white-space: pre; tab-size: 0; }',
        );
        $this->layout->layout($box, $this->defaultContext(400.0));
        $pre = $this->find($box, 'pre');
        $reference = $this->buildTree(
            '<html><body><pre>ab</pre></body></html>',
            'html, body, pre { display: block; white-space: pre; }',
        );
        $this->layout->layout($reference, $this->defaultContext(400.0));
        $refPre = $this->find($reference, 'pre');
        self::assertNotNull($pre);
        self::assertNotNull($refPre);
        self::assertEqualsWithDelta(
            $refPre->lineBoxes[0]->totalWidth(),
            $pre->lineBoxes[0]->totalWidth(),
            0.5,
        );
    }

    public function testTabSizeIgnoredInNormalWhiteSpaceMode(): void
    {
        // Default `white-space: normal` collapses tabs to a single
        // space regardless of tab-size — the existing collapse pass
        // wins. Sanity check that tab-size: 20 doesn't widen lines
        // in normal mode.
        $this->skipIfNoFont();
        $normal = $this->buildTree(
            "<html><body><p>a\tb</p></body></html>",
            'html, body, p { display: block; tab-size: 20; }',
        );
        $this->layout->layout($normal, $this->defaultContext(400.0));
        $normalP = $this->find($normal, 'p');
        $reference = $this->buildTree(
            '<html><body><p>a b</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($reference, $this->defaultContext(400.0));
        $refP = $this->find($reference, 'p');
        self::assertNotNull($normalP);
        self::assertNotNull($refP);
        self::assertEqualsWithDelta(
            $refP->lineBoxes[0]->totalWidth(),
            $normalP->lineBoxes[0]->totalWidth(),
            0.5,
        );
    }

    public function testTabSizeInheritsFromParent(): void
    {
        // `tab-size: 2` on the body should reach the inner `<pre>` via
        // inheritance — `tab-size` is an inheriting property.
        $this->skipIfNoFont();
        $box = $this->buildTree(
            "<html><body><pre>a\tb</pre></body></html>",
            'html, body, pre { display: block; white-space: pre; } body { tab-size: 2; }',
        );
        $this->layout->layout($box, $this->defaultContext(400.0));
        $pre = $this->find($box, 'pre');
        $reference = $this->buildTree(
            '<html><body><pre>a' . str_repeat(' ', 2) . 'b</pre></body></html>',
            'html, body, pre { display: block; white-space: pre; }',
        );
        $this->layout->layout($reference, $this->defaultContext(400.0));
        $refPre = $this->find($reference, 'pre');
        self::assertNotNull($pre);
        self::assertNotNull($refPre);
        self::assertEqualsWithDelta(
            $refPre->lineBoxes[0]->totalWidth(),
            $pre->lineBoxes[0]->totalWidth(),
            0.5,
        );
    }

    public function testTabSizeInvalidValueDefaultsToEight(): void
    {
        // Negative test: invalid keyword `tab-size: nonsense` keeps
        // the initial 8-spaces default.
        $this->skipIfNoFont();
        $box = $this->buildTree(
            "<html><body><pre>a\tb</pre></body></html>",
            'html, body, pre { display: block; white-space: pre; tab-size: nonsense; }',
        );
        $this->layout->layout($box, $this->defaultContext(400.0));
        $pre = $this->find($box, 'pre');
        $reference = $this->buildTree(
            '<html><body><pre>a' . str_repeat(' ', 8) . 'b</pre></body></html>',
            'html, body, pre { display: block; white-space: pre; }',
        );
        $this->layout->layout($reference, $this->defaultContext(400.0));
        $refPre = $this->find($reference, 'pre');
        self::assertNotNull($pre);
        self::assertNotNull($refPre);
        self::assertEqualsWithDelta(
            $refPre->lineBoxes[0]->totalWidth(),
            $pre->lineBoxes[0]->totalWidth(),
            0.5,
        );
    }

    public function testTextAlignLastAutoLeavesJustifyLastStartAligned(): void
    {
        // Default `text-align-last: auto` keeps the last line of a
        // justified block start-aligned per CSS Text 3 §7.4. Verify by
        // checking the last line's first fragment sits at x=0 (no shift
        // applied) while a non-final line has been justified.
        $this->skipIfNoFont();
        $letters = str_repeat("\u{1820} ", 40);
        $box = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; } p { line-height: 20px; text-align: justify; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        // Need at least 2 lines for the test to be meaningful.
        self::assertGreaterThanOrEqual(2, count($p->lineBoxes));
        $last = $p->lineBoxes[count($p->lineBoxes) - 1];
        // Last line's first fragment should sit at x=0.
        self::assertEqualsWithDelta(0.0, $last->fragments[0]->x, 0.001);
    }

    public function testTextAlignLastJustifyAppliesToLastLine(): void
    {
        // With `text-align-last: justify` the last line gets the
        // inter-fragment slack too. Verify by checking the last
        // fragment's right edge is closer to availableWidth than it
        // would be at start-aligned baseline.
        $this->skipIfNoFont();
        // Three short tokens fits comfortably in a narrow box —
        // last line has visible slack to justify.
        $letters = str_repeat("\u{1820} ", 20);
        $box = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; }
             p { line-height: 20px; text-align: justify; text-align-last: justify; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $last = $p->lineBoxes[count($p->lineBoxes) - 1];
        // With multiple fragments, justify shifted the trailing
        // fragment toward the right edge — its right edge sits past
        // the un-justified position. We assert totalWidth ≈ availableWidth.
        if (count($last->fragments) >= 2) {
            self::assertGreaterThanOrEqual(70.0, $last->totalWidth());
        } else {
            self::markTestSkipped('Last line was single-fragment — justify is a no-op');
        }
    }

    public function testTextAlignLastRightAlignsLastLineRight(): void
    {
        // `text-align: justify; text-align-last: right` → middle
        // lines justify, last line shifts to the right edge.
        $this->skipIfNoFont();
        $letters = str_repeat("\u{1820} ", 40);
        $box = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; }
             p { line-height: 20px; text-align: justify; text-align-last: right; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $last = $p->lineBoxes[count($p->lineBoxes) - 1];
        // The last line's RIGHT edge should sit near availableWidth.
        self::assertGreaterThan(40.0, $last->fragments[0]->x);
    }

    public function testTextAlignLastNoOpForSingleLine(): void
    {
        // A single-line paragraph has nothing to compare against the
        // last-line rule — the line acts as both first and last.
        // With text-align: justify text-align-last: auto, last is
        // start-aligned which means no justify on this line.
        $this->skipIfNoFont();
        $box = $this->buildTree(
            '<html><body><p>' . "\u{1820}\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }
             p { line-height: 20px; text-align: justify; }',
        );
        $this->layout->layout($box, $this->defaultContext(400.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertCount(1, $p->lineBoxes);
        $line = $p->lineBoxes[0];
        // First fragment at x=0 (no shift applied because it's the
        // last line and auto defers to start-align for justify).
        self::assertSame(0.0, $line->fragments[0]->x);
    }

    public function testTextAlignLastIgnoredOnNonJustifiedBlock(): void
    {
        // Default `text-align: start` (effectively left). `text-align-last`
        // should match that for any line. Verify no extra shift on
        // either first or last line.
        $this->skipIfNoFont();
        $letters = str_repeat("\u{1820} ", 30);
        $box = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; } p { line-height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        // Every line's first fragment at x=0.
        foreach ($p->lineBoxes as $line) {
            self::assertSame(0.0, $line->fragments[0]->x);
        }
    }

    public function testTextAlignLastCenterAffectsOnlyLastLine(): void
    {
        // `text-align: left; text-align-last: center` — only the last
        // line gets centred. First line stays at x=0.
        $this->skipIfNoFont();
        $letters = str_repeat("\u{1820} ", 40);
        $box = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; }
             p { line-height: 20px; text-align: left; text-align-last: center; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertGreaterThanOrEqual(2, count($p->lineBoxes));
        $first = $p->lineBoxes[0];
        $last = $p->lineBoxes[count($p->lineBoxes) - 1];
        // First line still starts at x=0.
        self::assertSame(0.0, $first->fragments[0]->x);
        // Last line shifted right (centered → first fragment past 0).
        self::assertGreaterThan(0.0, $last->fragments[0]->x);
    }

    public function testTextAlignLastInvalidKeywordDefersToAuto(): void
    {
        // Invalid value should be dropped at parse; the cascade keeps
        // the initial `auto`. The behaviour matches the auto-last
        // case (which for text-align: justify keeps last as start).
        $this->skipIfNoFont();
        $letters = str_repeat("\u{1820} ", 40);
        $box = $this->buildTree(
            '<html><body><p>' . $letters . '</p></body></html>',
            'html, body, p { display: block; }
             p { line-height: 20px; text-align: justify; text-align-last: nonsense; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $last = $p->lineBoxes[count($p->lineBoxes) - 1];
        // Last line stays start-aligned.
        self::assertSame(0.0, $last->fragments[0]->x);
    }

    public function testInlineLinesShortenAroundLeftFloat(): void
    {
        // 60 glyphs in a 200-wide container with a 100×60 left float at
        // the top → the first lines (which sit in the float's Y range)
        // start at x=100 instead of x=0. Lines below the float resume
        // at x=0.
        $this->skipIfNoFont();
        $letters = str_repeat("\u{1820} ", 60);
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: left; width: 100px; height: 60px"></div>'
                . '<p>' . $letters . '</p>'
                . '</body></html>',
            'html, body, p, div { display: block; }
             p { line-height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultContext(200.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertNotSame([], $p->lineBoxes);
        // Paragraph sits at y=0 (float is out-of-flow). First line at
        // y=0..20 overlaps the float's y=0..60 range → first fragment
        // starts at x=100 (the float's right edge).
        self::assertEqualsWithDelta(100.0, $p->lineBoxes[0]->fragments[0]->x, 0.001);
    }

    public function testInlineLinesResumeAtLeftEdgeBelowFloat(): void
    {
        // Same setup but enough text that some lines sit below the
        // 60-tall float (y >= 60). Those lines should restart at x=0.
        $this->skipIfNoFont();
        $letters = str_repeat("\u{1820} ", 60);
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: left; width: 100px; height: 60px"></div>'
                . '<p>' . $letters . '</p>'
                . '</body></html>',
            'html, body, p, div { display: block; }
             p { line-height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultContext(200.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        // Find the first line whose top sits at or past y=60.
        $lineBelow = null;
        foreach ($p->lineBoxes as $line) {
            if ($line->y + 0.001 >= 60.0) {
                $lineBelow = $line;
                break;
            }
        }
        if ($lineBelow === null) {
            self::markTestSkipped('Test fixture produced too few lines to span past the float');
        }
        // Below the float, the first fragment resumes at x=0.
        self::assertEqualsWithDelta(0.0, $lineBelow->fragments[0]->x, 0.001);
    }

    public function testInlineLinesShortenOnRightSideWithRightFloat(): void
    {
        // 100-wide right float in a 200-wide container → line's right
        // edge sits at 100; the first wrap should fit fewer characters
        // than the no-float case.
        $this->skipIfNoFont();
        $letters = str_repeat("\u{1820} ", 60);
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: right; width: 100px; height: 60px"></div>'
                . '<p>' . $letters . '</p>'
                . '</body></html>',
            'html, body, p, div { display: block; }
             p { line-height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultContext(200.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        // First line overlapping the float's y range fits within
        // x = 0..100 (i.e. totalWidth <= 100 + small tolerance).
        $first = $p->lineBoxes[0];
        self::assertLessThanOrEqual(100.0 + 1.0, $first->totalWidth());
    }

    public function testParagraphEntirelyOnOnePageIsNotShifted(): void
    {
        // 4 lines × 20px = 80px paragraph at body-top → fits cleanly inside
        // the 800px page. No line should get pushed.
        $this->skipIfNoFont();
        $body = str_repeat("\u{1820} ", 60);
        $box = $this->buildTree(
            '<html><body><p>' . $body . '</p></body></html>',
            'html, body, p { display: block; } p { line-height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultContext(120.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $first = $p->lineBoxes[0];
        $expectedY = 0.0;
        foreach ($p->lineBoxes as $line) {
            self::assertEqualsWithDelta($expectedY, $line->y, 0.001);
            $expectedY += $line->height;
        }
    }

    public function testZeroPageHeightDoesNotShiftLines(): void
    {
        // pageHeight = 0 in the layout context means no pagination is
        // active — the fragmentation pass must early-out, leaving lines
        // contiguous.
        $this->skipIfNoFont();
        $body = str_repeat("\u{1820} ", 30);
        $box = $this->buildTree(
            '<html><body><p>' . $body . '</p></body></html>',
            'html, body, p { display: block; } p { line-height: 20px; }',
        );
        $ctx = new LayoutContext(
            containingBlockWidth: 80.0,
            containingBlockHeight: 0.0,
            originX: 0.0,
            originY: 0.0,
            lengthContext: new LengthContext(),
            defaultFont: $this->font,
        );
        $this->layout->layout($box, $ctx);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $expectedY = 0.0;
        foreach ($p->lineBoxes as $line) {
            self::assertEqualsWithDelta($expectedY, $line->y, 0.001);
            $expectedY += $line->height;
        }
    }

    public function testStraddlingLinePushedToNextPageBoundary(): void
    {
        // Paragraph at layout-Y=785, line-height=20. Line 0's abs range
        // is 785..805 — straddles the 800 boundary. With orphans/widows=1
        // the algorithm should shift line 0 to start at exactly 800.
        $this->skipIfNoFont();
        $body = str_repeat("\u{1820} ", 60);
        $box = $this->buildTree(
            '<html><body>'
                . '<div style="height: 785px"></div>'
                . '<p style="orphans: 1; widows: 1">' . $body . '</p>'
                . '</body></html>',
            'html, body, p, div { display: block; } p { line-height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultContext(120.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertEqualsWithDelta(785.0, $p->geometry->y, 0.001);
        $firstAbs = $p->geometry->y + $p->lineBoxes[0]->y;
        self::assertEqualsWithDelta(800.0, $firstAbs, 0.001, 'straddler should land on the page boundary');
    }

    public function testEveryLineLandsWithinAPageAfterFragmentationPass(): void
    {
        // The hard invariant — for any paragraph, no line ends up
        // straddling a page boundary. Test with a multi-page-spanning
        // paragraph (50 lines × ~20px = ~1000px, spanning the 800-tall
        // page) to confirm at least one boundary is exercised.
        $this->skipIfNoFont();
        $body = str_repeat("\u{1820} ", 350);
        $box = $this->buildTree(
            '<html><body><p style="orphans: 1; widows: 1">' . $body . '</p></body></html>',
            'html, body, p { display: block; } p { line-height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultContext(80.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $pageHeight = 800.0;
        foreach ($p->lineBoxes as $i => $line) {
            $absTop = $p->geometry->y + $line->y;
            $absBot = $absTop + $line->height;
            $startPage = (int) floor($absTop / $pageHeight);
            $endPage = (int) floor(($absBot - 0.001) / $pageHeight);
            self::assertSame(
                $startPage,
                $endPage,
                sprintf('line %d straddles page boundary (absTop=%.2f, absBot=%.2f)', $i, $absTop, $absBot),
            );
        }
    }

    public function testWidowsHoldsBackTrailingLinesToTheNextPage(): void
    {
        // Paragraph at Y=740, line-height 20, 4 lines. Without widows
        // logic: lines at 740/760/780/800 → 3 lines on page 0, 1 line on
        // page 1 (widow). Default widows=2 pulls one more line forward,
        // leaving 2 on page 0 and 2 on page 1.
        $this->skipIfNoFont();
        // 8 letters @ 60px usually shapes to exactly 4 short lines.
        $body = str_repeat("\u{1820} ", 8);
        $box = $this->buildTree(
            '<html><body>'
                . '<div style="height: 740px"></div>'
                . '<p>' . $body . '</p>'
                . '</body></html>',
            'html, body, p, div { display: block; } p { line-height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultContext(60.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        // Count widows (lines that start on or after the page boundary).
        $widows = 0;
        foreach ($p->lineBoxes as $line) {
            if ($p->geometry->y + $line->y + 0.001 >= 800.0) {
                $widows++;
            }
        }
        self::assertGreaterThanOrEqual(2, $widows, 'default widows=2 must be honoured');
    }

    public function testCustomOrphansForcesShiftWhenViolated(): void
    {
        // 6 lines, paragraph positioned so a normal split lands line 0+1
        // on page 1 and lines 2..5 on page 2. With orphans=3 the engine
        // must instead shift the whole paragraph (orphans require ≥3
        // lines on the previous page; we only have 2 → can't satisfy →
        // shift all).
        $this->skipIfNoFont();
        $body = str_repeat("\u{1820} ", 30);
        $box = $this->buildTree(
            '<html><body>'
                . '<div style="height: 760px"></div>'
                . '<p style="orphans: 3; widows: 1">' . $body . '</p>'
                . '</body></html>',
            'html, body, p, div { display: block; } p { line-height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultContext(60.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        foreach ($p->lineBoxes as $line) {
            self::assertGreaterThanOrEqual(
                800.0 - 0.001,
                $p->geometry->y + $line->y,
                'with orphans=3 and only 2 lines fit on page 1, whole paragraph should shift',
            );
        }
    }

    public function testEmptyParagraphProducesNoLineBoxes(): void
    {
        // The fragmentation pass must early-out cleanly when there are no
        // lines to walk.
        $this->skipIfNoFont();
        $box = $this->buildTree(
            '<html><body><p></p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultContext(120.0));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertSame([], $p->lineBoxes);
        self::assertSame(0.0, $p->geometry->height);
    }

    private function skipIfNoFont(): void
    {
        if ($this->font === null) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
    }

    private function find(Box $root, string $tag): ?Box
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
}
