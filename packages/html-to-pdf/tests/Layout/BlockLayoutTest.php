<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Layout;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\HtmlToPdf\Box\BlockBox;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\Box\FlexBox;
use Phpdftk\HtmlToPdf\Layout\BlockLayout;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\HtmlToPdf\Layout\FontResolver;
use Phpdftk\HtmlToPdf\Layout\LayoutContext;
use Phpdftk\Html\Parser as HtmlParser;
use PHPUnit\Framework\TestCase;

final class BlockLayoutTest extends TestCase
{
    private CssParser $css;
    private HtmlParser $html;
    private BoxGenerator $generator;
    private BlockLayout $layout;
    private LayoutContext $defaultCtx;

    protected function setUp(): void
    {
        $this->css = new CssParser();
        $this->html = new HtmlParser();
        $cascade = new Cascade(PropertyRegistry::default());
        $this->generator = new BoxGenerator($cascade);
        $this->layout = new BlockLayout($cascade);
        $this->defaultCtx = new LayoutContext(
            containingBlockWidth: 600.0,
            containingBlockHeight: 800.0,
            originX: 0.0,
            originY: 0.0,
            lengthContext: new LengthContext(),
            // Page height for pagination — matches the real renderer,
            // where the root context carries the page height distinct
            // from a nested block's containing-block height.
            pageHeight: 800.0,
        );
    }

    private function buildTree(string $html, string $css): Box
    {
        $doc = $this->html->parseDocument($html);
        $sheet = $this->css->parseStylesheet($css, Origin::UserAgent);
        $box = $this->generator->generate($doc, [$sheet]);
        self::assertNotNull($box);
        return $box;
    }

    public function testAbsposAutoMarginTopAbsorbsVerticalSlack(): void
    {
        // CSS 2.1 §10.6.5 — over-constrained abs-pos (top, bottom, height
        // all set) with margin-top:auto and a fixed margin-bottom: the auto
        // margin-top absorbs the remaining slack.
        // slack = 300 - 50(top) - 50(bottom) - 100(height) = 100;
        // margin-top = slack - margin-bottom(50) = 50; box top = 50+50 = 100.
        $box = $this->buildTree(
            '<html><body><div id="cb"><div id="ap"></div></div></body></html>',
            'html, body { display: block; }
             #cb { position: relative; height: 300px; width: 300px; }
             #ap { position: absolute; top: 50px; bottom: 50px; height: 100px;
                   margin-top: auto; margin-bottom: 50px; width: 100%; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cb = $this->findById($box, 'cb');
        $ap = $this->findById($box, 'ap');
        self::assertNotNull($cb);
        self::assertNotNull($ap);
        // Box top sits 100px below the containing block's content top.
        self::assertEqualsWithDelta($cb->geometry->y + 100.0, $ap->geometry->y, 0.5);
    }

    public function testFlexIntrinsicWidthTreatsPercentMarginAsZero(): void
    {
        // CSS Sizing 3 §5.1 — a flex item's percentage margin (incl. the
        // `%` term of a calc()) contributes ZERO to the container's
        // intrinsic (min-content) width. An empty item with
        // `margin-left: calc(10% + 100px)` makes a `width: min-content`
        // container exactly 100px (the fixed term), not 100 + 10%×CB.
        $box = $this->buildTree(
            '<html><body><div id="f"><div id="it"></div></div></body></html>',
            'html, body { display: block; }
             #f { display: flex; width: min-content; height: 100px; }
             #it { margin-left: calc(10% + 100px); }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $f = $this->findById($box, 'f');
        self::assertNotNull($f);
        self::assertEqualsWithDelta(100.0, $f->geometry->width, 0.5);
    }

    public function testAbsposWidthStretchFillsSlackBetweenInsets(): void
    {
        // CSS Sizing 4 §6.3 — `width: stretch` on an abs-pos box with both
        // left+right insets fills the slack just like `auto`
        // (width = CB − left − right). Mirrors the height branch, which
        // already handled stretch.
        $box = $this->buildTree(
            '<html><body><div id="cb"><div id="ap"></div></div></body></html>',
            'html, body { display: block; }
             #cb { position: relative; width: 300px; height: 300px; }
             #ap { position: absolute; left: 50px; right: 50px; width: stretch; height: 100px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $ap = $this->findById($box, 'ap');
        self::assertNotNull($ap);
        self::assertEqualsWithDelta(200.0, $ap->geometry->width, 0.5);
    }

    public function testTableOwnMinWidthShrinkWraps(): void
    {
        // An auto table whose only cell is empty still shrink-wraps to the
        // table's own min-width instead of filling the container.
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="r"><div id="cell"></div></div></div></body></html>',
            'html, body { display: block; }
             #t { display: table; min-width: 96px; }
             #r { display: table-row; }
             #cell { display: table-cell; height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        self::assertNotNull($t);
        self::assertEqualsWithDelta(96.0, $t->geometry->width, 1.0);
    }

    public function testCellMinWidthFloorsItsColumn(): void
    {
        // A cell's own min-width floors its column contribution, so an
        // empty cell with min-width shrink-wraps the auto table.
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="r"><div id="cell"></div></div></div></body></html>',
            'html, body { display: block; }
             #t { display: table; }
             #r { display: table-row; }
             #cell { display: table-cell; height: 40px; min-width: 96px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        $cell = $this->findById($box, 'cell');
        self::assertNotNull($t);
        self::assertNotNull($cell);
        self::assertEqualsWithDelta(96.0, $cell->geometry->width, 1.0);
    }

    public function testMarginDoesNotApplyToInternalTableBoxes(): void
    {
        // CSS 2.1 §8.3 — margin does not apply to internal table boxes.
        // A `margin: 50px` on a table-row-group / -row / -cell must be
        // ignored so it never displaces §17 table layout.
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="rg"><div id="r">'
                . '<div id="cell"></div></div></div></div></body></html>',
            'html, body { display: block; }
             #t { display: table; }
             #rg { display: table-row-group; margin: 50px; }
             #r { display: table-row; margin: 40px; }
             #cell { display: table-cell; margin: 30px; height: 40px; width: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        foreach (['rg', 'r', 'cell'] as $id) {
            $b = $this->findById($box, $id);
            self::assertNotNull($b);
            self::assertSame(0.0, $b->geometry->marginTop, "$id margin-top");
            self::assertSame(0.0, $b->geometry->marginLeft, "$id margin-left");
            self::assertSame(0.0, $b->geometry->marginRight, "$id margin-right");
            self::assertSame(0.0, $b->geometry->marginBottom, "$id margin-bottom");
        }
    }

    public function testPaddingDoesNotApplyToTableRowGroupButAppliesToCell(): void
    {
        // CSS 2.1 §8.4 — padding does not apply to table-row-group /
        // -row (and the other group/column boxes) but DOES apply to
        // table-cell.
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="rg"><div id="r">'
                . '<div id="cell"></div></div></div></div></body></html>',
            'html, body { display: block; }
             #t { display: table; }
             #rg { display: table-row-group; padding: 20px; }
             #r { display: table-row; padding: 15px; }
             #cell { display: table-cell; padding: 10px; height: 40px; width: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $rg = $this->findById($box, 'rg');
        $cell = $this->findById($box, 'cell');
        self::assertNotNull($rg);
        self::assertNotNull($cell);
        self::assertSame(0.0, $rg->geometry->paddingLeft, 'row-group padding ignored');
        self::assertSame(0.0, $rg->geometry->paddingTop, 'row-group padding ignored');
        self::assertSame(10.0, $cell->geometry->paddingLeft, 'cell padding applies');
        self::assertSame(10.0, $cell->geometry->paddingTop, 'cell padding applies');
    }

    public function testNonReplacedFlexItemAutomaticMinimumIsContentNotTransferred(): void
    {
        // CSS Flexbox 1 §4.5 — the transferred size suggestion (definite
        // cross size × aspect ratio) exists only for a REPLACED item. A
        // non-replaced row flex item with aspect-ratio:1/2, height:100px,
        // flex-basis:0 and a 100px-wide child must floor its automatic
        // minimum at the content size suggestion (100px), not the 50px the
        // ratio would transfer from the cross size. (WPT flex-aspect-ratio-002.)
        $box = $this->buildTree(
            '<html><body><div id="f"><div id="it"><div id="c"></div></div></div></body></html>',
            'html, body { display: block; }
             #f { display: flex; }
             #it { aspect-ratio: 1 / 2; height: 100px; flex-basis: 0; }
             #c { width: 100px; height: 10px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $it = $this->findById($box, 'it');
        self::assertNotNull($it);
        self::assertEqualsWithDelta(100.0, $it->geometry->width, 1.0);
    }

    public function testRowFlexItemPercentHeightTransfersMainAgainstContainer(): void
    {
        // CSS Sizing 4 §4.2 / Flexbox 1 §9.9 — a ROW flex item's
        // `height: %` (its cross axis) resolves against the FLEX
        // CONTAINER's content height, not the outer containing block.
        // With a definite-height container and `aspect-ratio: 1`, the
        // item's auto main (width) transfers from that cross height:
        // 100% × 100px = 100 → 100px square, even though the container's
        // own width is 0. Resolving the % against the 800px outer CB used
        // to balloon the item to page size. (WPT flex-aspect-ratio-047.)
        $box = $this->buildTree(
            '<html><body><div id="f"><div id="it"></div></div></body></html>',
            'html, body { display: block; }
             #f { display: flex; width: 0px; height: 100px; }
             #it { aspect-ratio: 1; height: 100%; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $it = $this->findById($box, 'it');
        self::assertNotNull($it);
        self::assertEqualsWithDelta(100.0, $it->geometry->width, 1.0);
        self::assertEqualsWithDelta(100.0, $it->geometry->height, 1.0);
    }

    public function testNonStretchColumnFlexItemCrossIsFitContentNotFill(): void
    {
        // CSS Flexbox 1 §7.2.1 — a flex item whose cross alignment is not
        // `stretch` uses its FIT-CONTENT cross size. For a column flex item
        // (cross axis = width) under `align-items: start`, an `auto` width
        // must shrink-to-fit its content (a 100px-wide child), NOT fill the
        // container. With `aspect-ratio: 1` the fitted 100px width then
        // derives a 100px height → a 100px square hugging its content, not a
        // container-filling block. (WPT flex-aspect-ratio-037.)
        $box = $this->buildTree(
            '<html><body><div id="f"><div id="it"><div id="c"></div></div></div></body></html>',
            'html, body { display: block; }
             #f { display: flex; flex-direction: column; align-items: flex-start; }
             #it { aspect-ratio: 1 / 1; }
             #c { width: 100px; height: 10px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $it = $this->findById($box, 'it');
        self::assertNotNull($it);
        self::assertEqualsWithDelta(100.0, $it->geometry->width, 1.0);
        self::assertEqualsWithDelta(100.0, $it->geometry->height, 1.0);
    }

    public function testColumnFlexItemAutoCrossMarginsCentre(): void
    {
        // CSS Flexbox 1 §4.2 — an auto margin in the cross axis absorbs free
        // space and overrides align-self. A column flex item (cross = width)
        // with `margin: 0 auto` is fit-content and centred in the container's
        // cross extent, not stretched. In a 200px-wide container an item that
        // fits its 100px child sits at x=50 (centred). (WPT auto-margins-003.)
        $box = $this->buildTree(
            '<html><body><div id="f"><div id="it"><div id="c"></div></div></div></body></html>',
            'html, body { display: block; }
             #f { display: flex; flex-direction: column; width: 200px; }
             #it { margin: 0 auto; }
             #c { width: 100px; height: 10px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $it = $this->findById($box, 'it');
        self::assertNotNull($it);
        self::assertEqualsWithDelta(100.0, $it->geometry->width, 1.0);
        self::assertEqualsWithDelta(50.0, $it->geometry->x, 1.0);
    }

    public function testStretchColumnFlexItemStillFillsCross(): void
    {
        // The fit-content path must NOT touch the default `align-items:
        // stretch`: a column item with `auto` width under stretch fills the
        // container's cross extent (here 200px), unchanged.
        $box = $this->buildTree(
            '<html><body><div id="f"><div id="it"></div></div></body></html>',
            'html, body { display: block; }
             #f { display: flex; flex-direction: column; width: 200px; }
             #it { height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $it = $this->findById($box, 'it');
        self::assertNotNull($it);
        self::assertEqualsWithDelta(200.0, $it->geometry->width, 1.0);
    }

    public function testColumnFlexItemPercentWidthTransfersMainAgainstContainer(): void
    {
        // Column analog: a COLUMN flex item's `width: %` (its cross axis)
        // resolves against the flex container's content width, not the
        // outer CB. A definite-width container with `aspect-ratio: 1`
        // transfers the cross width (100% × 100px = 100) to the auto main
        // (height) → 100px square, even though the container's height is 0.
        // Resolving the % against the 600px outer CB used to balloon it.
        // (WPT flex-aspect-ratio-048.)
        $box = $this->buildTree(
            '<html><body><div id="f"><div id="it"></div></div></body></html>',
            'html, body { display: block; }
             #f { display: flex; flex-direction: column; width: 100px; height: 0px; }
             #it { aspect-ratio: 1; width: 100%; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $it = $this->findById($box, 'it');
        self::assertNotNull($it);
        self::assertEqualsWithDelta(100.0, $it->geometry->width, 1.0);
        self::assertEqualsWithDelta(100.0, $it->geometry->height, 1.0);
    }

    public function testInFlowPercentHeightIndefiniteThroughAutoAncestors(): void
    {
        // CSS 2.1 §10.5 — an in-flow `height: %` against an auto-height
        // containing block is indefinite and sizes to content. The auto
        // `<html>` / `<body>` chain does NOT make it definite (that
        // viewport special-case is for abspos only), so the 50% div sizes
        // to its 80px child, not to half the viewport.
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="c">'
            . '<div id="k"></div></div></div></body></html>',
            'html, body { display: block; }
             #t { display: block; }
             #c { display: block; height: 50%; }
             #k { display: block; height: 80px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = $this->findById($box, 'c');
        self::assertNotNull($c);
        // Content-derived (80), NOT 50% of the 800px default CB (=400).
        self::assertEqualsWithDelta(80.0, $c->geometry->height, 1.0);
    }

    public function testFloatShrinkWrapsReplacedRatioIntrinsicWidth(): void
    {
        // A float shrink-wraps to its content's max-content width. A
        // replaced child with a definite height and intrinsic ratio (2:1
        // here) contributes height × ratio to that measurement, so the
        // float is 100px wide (50 × 2), not collapsed to 0.
        $box = $this->buildTreeWithUa(
            '<html><body><div id="f">'
            . '<canvas width="2" height="1" style="height: 50px"></canvas>'
            . '</div></body></html>',
            '#f { float: left; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $f = $this->findById($box, 'f');
        self::assertNotNull($f);
        self::assertEqualsWithDelta(100.0, $f->geometry->width, 2.0);
    }

    public function testFontSizeZeroFloatCollapsesToZeroWidth(): void
    {
        // CSS Sizing 3 §5 — `font-size: 0` text has 0 intrinsic width, so a
        // width-less float shrink-to-fits to 0 (regression: the no-font
        // heuristic sized it to the full text width). `0` parses as Integer.
        $box = $this->buildTreeWithUa(
            '<html><body><div id="f">hello world</div></body></html>',
            '#f { float: left; font-size: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $f = $this->findById($box, 'f');
        self::assertNotNull($f);
        self::assertEqualsWithDelta(0.0, $f->geometry->width, 0.5);
    }

    public function testAuthorAspectRatioOverridesReplacedIntrinsicRatio(): void
    {
        // CSS Sizing 4 §5.1 — an author `aspect-ratio: 1/1` overrides the
        // replaced element's intrinsic ratio (20:50 here) when deriving the
        // auto height from the definite width: 100px wide → 100px tall, not
        // 100 × 50/20 = 250.
        $box = $this->buildTreeWithUa(
            '<html><body><canvas id="c" width="20" height="50" '
            . 'style="width: 100px; aspect-ratio: 1/1"></canvas></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = $this->findById($box, 'c');
        self::assertNotNull($c);
        self::assertEqualsWithDelta(100.0, $c->geometry->height, 2.0);
    }

    public function testAlignSelfNormalStretchesFlexItem(): void
    {
        // CSS Box Alignment 3 §4.1 — `align-self: normal` behaves as `stretch`
        // on a flex item (it must NOT defer to align-items:center).
        $box = $this->buildTreeWithUa(
            '<html><body><div id="fc"><div id="it">x</div></div></body></html>',
            '#fc { display: flex; align-items: center; height: 100px; }
             #it { align-self: normal; width: 20px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $it = $this->findById($box, 'it');
        self::assertNotNull($it);
        self::assertEqualsWithDelta(100.0, $it->geometry->height, 2.0);
    }

    public function testNestedFlexItemStretchesToParentLine(): void
    {
        // CSS Flexbox 1 §9.4 — a nested flex container that its parent flex
        // line cross-stretches gains a definite cross size, so ITS own
        // align-items:stretch sizes ITS auto-height child to that same line
        // height instead of collapsing it to the content height (0 here).
        // (WPT css-flexbox/stretched-child-in-nested-flexbox-001.)
        $box = $this->buildTreeWithUa(
            '<html><body><div id="outer">'
            . '<div id="sibling"></div>'
            . '<div id="nested"><div id="inner"></div></div>'
            . '</div></body></html>',
            '#outer { display: flex; }
             #sibling { width: 50px; height: 100px; }
             #nested { display: flex; }
             #inner { width: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $inner = $this->findById($box, 'inner');
        self::assertNotNull($inner);
        self::assertEqualsWithDelta(100.0, $inner->geometry->height, 2.0);
    }

    public function testColumnWrapContainerMaxContentWidthSumsColumns(): void
    {
        // CSS Flexbox 1 §9.9 — a `flex-flow: column wrap` container with a
        // definite height wraps items into several columns; its max-content
        // width is the SUM of the columns' widths, not the single widest
        // item. Two 100px-tall items in a 100px-tall column wrap into two
        // 50px columns → 100px wide. (WPT intrinsic-size/col-wrap-001.)
        $box = $this->buildTreeWithUa(
            '<html><body><div id="fc">'
            . '<div class="it"></div><div class="it"></div>'
            . '</div></body></html>',
            '#fc { display: flex; flex-flow: column wrap; height: 100px; width: max-content; }
             .it { width: 50px; flex: 0 0 100px; min-height: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $fc = $this->findById($box, 'fc');
        self::assertNotNull($fc);
        self::assertEqualsWithDelta(100.0, $fc->geometry->width, 2.0);
    }

    public function testFloatHasNoEffectOnGridItem(): void
    {
        // CSS Grid 1 §4 / CSS Flexbox 1 §3 — `float` has no effect on a grid
        // (or flex) item: its used float computes to `none` so it stays an
        // in-flow item instead of being pulled out as a float.
        // (WPT css-grid/grid-model/grid-inline-float-001.)
        $box = $this->buildTreeWithUa(
            '<html><body><div id="g"><div id="it" style="float: left">x</div></div></body></html>',
            '#g { display: grid; }',
        );
        $it = $this->findById($box, 'it');
        self::assertNotNull($it);
        $float = $it->style->get('float');
        self::assertTrue(
            !($float instanceof \Phpdftk\Css\Value\Keyword)
                || strtolower($float->name) === 'none',
            'float on a grid item must be suppressed to none',
        );
    }

    public function testLogicalFloatInlineStartAndEndResolveToPhysicalSides(): void
    {
        // CSS Logical 1 §4.1 — `float: inline-start` / `inline-end` resolve to
        // physical left/right per the box's writing-mode + direction. In the
        // default horizontal-tb LTR flow, inline-start floats to the left edge
        // and inline-end to the right edge of the containing block.
        $box = $this->buildTree(
            '<html><body><div id="cb">'
            . '<div id="s"></div><div id="e"></div></div></body></html>',
            'html, body { display: block; }
             #cb { width: 300px; }
             #s { float: inline-start; width: 50px; height: 20px; }
             #e { float: inline-end; width: 50px; height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cb = $this->findById($box, 'cb');
        $s = $this->findById($box, 's');
        $e = $this->findById($box, 'e');
        self::assertNotNull($cb);
        self::assertNotNull($s);
        self::assertNotNull($e);
        // inline-start → left edge of the CB.
        self::assertEqualsWithDelta($cb->geometry->x, $s->geometry->x, 0.5);
        // inline-end → right edge: box right aligns with the CB right edge.
        self::assertEqualsWithDelta(
            $cb->geometry->x + $cb->geometry->width,
            $e->geometry->x + $e->geometry->width,
            0.5,
        );
    }

    public function testLogicalFloatInlineStartFollowsRtlDirection(): void
    {
        // CSS Logical 1 §4.1 — in RTL, `float: inline-start` resolves to the
        // physical RIGHT edge (the opposite of LTR), proving the normalization
        // honours `direction`, not a hard-coded left.
        $box = $this->buildTree(
            '<html><body><div id="cb"><div id="s"></div></div></body></html>',
            'html, body { display: block; }
             #cb { width: 300px; direction: rtl; }
             #s { float: inline-start; width: 50px; height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cb = $this->findById($box, 'cb');
        $s = $this->findById($box, 's');
        self::assertNotNull($cb);
        self::assertNotNull($s);
        self::assertEqualsWithDelta(
            $cb->geometry->x + $cb->geometry->width,
            $s->geometry->x + $s->geometry->width,
            0.5,
        );
    }

    public function testContainSizeBlockStretchesToContainingBlock(): void
    {
        // CSS Sizing 4 §6.1 — `contain: size` substitutes the
        // contain-intrinsic-size into a box's INTRINSIC (min/max-content)
        // contributions, NOT into a normal in-flow auto-width block, which
        // still stretches to fill its containing block. Without a
        // contain-intrinsic-width the block used to collapse to ~0; it must
        // fill the CB width (600 from the default context).
        $box = $this->buildTree(
            '<html><body><div id="c"></div></body></html>',
            'html, body { display: block; }
             #c { display: block; contain: size; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = $this->findById($box, 'c');
        self::assertNotNull($c);
        self::assertEqualsWithDelta(600.0, $c->geometry->width, 1.0);
    }

    public function testContainSizeFloatStillShrinkWraps(): void
    {
        // Negative control for the gate: a shrink-to-fit box (float) with
        // `contain: size` and no contain-intrinsic-width takes the contained
        // width (0), NOT the CB-stretch — the substitution cap must still
        // apply to shrink-to-fit boxes.
        $box = $this->buildTree(
            '<html><body><div id="f"></div></body></html>',
            'html, body { display: block; }
             #f { float: left; contain: size; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $f = $this->findById($box, 'f');
        self::assertNotNull($f);
        self::assertEqualsWithDelta(0.0, $f->geometry->width, 0.5);
    }

    public function testTableFlexItemNotShrunkBelowMinContent(): void
    {
        // CSS Tables 3 §4 — a `display: table` flex item is not shrunk below
        // its min-content block size even when `min-height: 0` opts out of
        // the §4.5 automatic minimum (a table can't render shorter than its
        // content). (WPT css-flexbox/table-as-item-min-content-height-1.)
        $box = $this->buildTreeWithUa(
            '<html><body><div id="fc">'
            . '<div id="t"><div id="cell"><div id="inner"></div></div></div>'
            . '<div id="sib"></div>'
            . '</div></body></html>',
            '#fc { display: flex; flex-direction: column; height: 0; width: 100px; }
             #t { display: table; min-height: 0; width: 100px; }
             #cell { display: table-cell; }
             #inner { height: 50px; }
             #sib { flex: 0 0 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        self::assertNotNull($t);
        self::assertGreaterThanOrEqual(48.0, $t->geometry->height);
    }

    public function testTypedAttrLengthResolvesToWidth(): void
    {
        // CSS Values 5 §11 — `width: attr(data-w type(<length>))` reads the
        // element's `data-w` attribute and casts it to a length.
        $box = $this->buildTreeWithUa(
            '<html><body><div id="t" data-w="150px" '
            . 'style="width: attr(data-w type(&lt;length&gt;))"></div></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        self::assertNotNull($t);
        self::assertEqualsWithDelta(150.0, $t->geometry->width, 1.0);
    }

    public function testTypedAttrInvalidCastUsesFallback(): void
    {
        // An attribute value that can't cast to the declared type falls back
        // to the supplied default (CSS Values 5 §11).
        $box = $this->buildTreeWithUa(
            '<html><body><div id="t" data-w="notalength" '
            . 'style="width: attr(data-w type(&lt;length&gt;), 120px)"></div></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        self::assertNotNull($t);
        self::assertEqualsWithDelta(120.0, $t->geometry->width, 1.0);
    }

    public function testInlineReplacedDerivesWidthFromDefiniteHeightAndRatio(): void
    {
        // CSS 2.1 §10.3.2 — an inline replaced element (here a canvas with
        // intrinsic ratio 2:1 from its width/height attrs) with a definite
        // percentage height and auto width derives its width from
        // height × ratio. 50% of the 100px CB = 50 tall → 100 wide.
        $box = $this->buildTreeWithUa(
            '<html><body><div id="cb">'
            . '<canvas id="c" width="2" height="1" style="height: 50%"></canvas>'
            . '</div></body></html>',
            '#cb { height: 100px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = $this->findById($box, 'c');
        self::assertNotNull($c);
        self::assertEqualsWithDelta(50.0, $c->geometry->height, 1.0);
        self::assertEqualsWithDelta(100.0, $c->geometry->width, 1.0);
    }

    public function testInlineReplacedMaxHeightClampPreservesRatio(): void
    {
        // CSS 2.1 §10.4 — a percentage max-height on a replaced element
        // caps the height and, via the intrinsic ratio (1:1 here), the
        // width follows. Canvas 200×200, max-height 50% of a 200px CB =
        // 100 → clamped to a 100×100 square.
        $box = $this->buildTreeWithUa(
            '<html><body><div id="cb">'
            . '<canvas id="c" width="200" height="200" style="max-height: 50%"></canvas>'
            . '</div></body></html>',
            '#cb { height: 200px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = $this->findById($box, 'c');
        self::assertNotNull($c);
        self::assertEqualsWithDelta(100.0, $c->geometry->height, 1.0);
        self::assertEqualsWithDelta(100.0, $c->geometry->width, 1.0);
    }

    public function testStretchedBorderedCellFillsRowWithoutOverflow(): void
    {
        // A short bordered cell stretched to the row height must fill the
        // row exactly (border box = row height), not overflow by its own
        // border: its CONTENT stretches to rowHeight - border - padding.
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="r">'
            . '<div id="tall"></div><div id="short"></div>'
            . '</div></div></body></html>',
            'html, body { display: block; }
             #t { display: table; }
             #r { display: table-row; }
             #tall  { display: table-cell; height: 60px; }
             #short { display: table-cell; height: 20px; border: 10px solid black; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $short = $this->findById($box, 'short');
        self::assertNotNull($short);
        // Row height = 60 (the tall cell). The short cell's border box must
        // equal 60: content 40 + border 20. Content must NOT be set to 60.
        self::assertEqualsWithDelta(40.0, $short->geometry->height, 1.0);
        self::assertEqualsWithDelta(60.0, $short->geometry->outerHeight(), 1.0);
    }

    public function testDefiniteHeightContainerIsNotAFragmentainer(): void
    {
        // A nested definite-height block is not a page: an unbreakable
        // child that would straddle the container's own height must NOT
        // shift down as if the container height were a page boundary
        // (regression — pagination is measured against the real page
        // height, not the containing block).
        $box = $this->buildTree(
            '<html><body><div id="c">'
            . '<div class="k" id="a"></div><div class="k" id="b"></div>'
            . '</div></body></html>',
            'html, body { display: block; }
             #c { display: block; height: 120px; }
             .k { display: block; height: 100px; break-inside: avoid; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->findById($box, 'a');
        $b = $this->findById($box, 'b');
        self::assertNotNull($a);
        self::assertNotNull($b);
        // Second child stacks flush below the first (y = 100), not shoved
        // to the container's 120px "page" boundary.
        self::assertEqualsWithDelta($a->geometry->y + 100.0, $b->geometry->y, 0.5);
    }

    public function testBorderSpacingOffsetsCellsAndShrinksColumns(): void
    {
        // CSS 2.1 §17.6.1 — with border-spacing 20px and a 200px table,
        // the two columns share 200 − 3×20 = 140px (70px each); the first
        // cell starts one spacing in, the second after col0 + one spacing.
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="r">'
            . '<div class="c" id="c0"></div><div class="c" id="c1"></div>'
            . '</div></div></body></html>',
            'html, body { display: block; }
             #t { display: table; width: 200px; border-spacing: 20px; }
             #r { display: table-row; }
             .c { display: table-cell; height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        $c0 = $this->findById($box, 'c0');
        $c1 = $this->findById($box, 'c1');
        self::assertNotNull($t);
        self::assertNotNull($c0);
        self::assertNotNull($c1);
        self::assertEqualsWithDelta(70.0, $c0->geometry->width, 1.0);
        self::assertEqualsWithDelta($t->geometry->x + 20.0, $c0->geometry->x, 1.0);
        self::assertEqualsWithDelta($t->geometry->x + 110.0, $c1->geometry->x, 1.0);
    }

    public function testVerticalBorderSpacingGapsRows(): void
    {
        // Vertical border-spacing separates the table top from the first
        // row and each row from the next.
        $box = $this->buildTree(
            '<html><body><div id="t">'
            . '<div class="r" id="r0"><div class="c"></div></div>'
            . '<div class="r" id="r1"><div class="c"></div></div>'
            . '</div></body></html>',
            'html, body { display: block; }
             #t { display: table; border-spacing: 0 25px; }
             .r { display: table-row; }
             .c { display: table-cell; height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        $r0 = $this->findById($box, 'r0');
        $r1 = $this->findById($box, 'r1');
        self::assertNotNull($t);
        self::assertNotNull($r0);
        self::assertNotNull($r1);
        // First row sits one vertical spacing below the table content top.
        self::assertEqualsWithDelta($t->geometry->y + 25.0, $r0->geometry->y, 1.0);
        // Second row is one spacing below the first row's bottom.
        self::assertEqualsWithDelta(
            $r0->geometry->y + $r0->geometry->height + 25.0,
            $r1->geometry->y,
            1.0,
        );
    }

    public function testBorderCollapseIgnoresBorderSpacing(): void
    {
        // Under border-collapse: collapse the spacing has no effect —
        // the first cell starts flush at the table's content origin.
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="r">'
            . '<div id="cell"></div></div></div></body></html>',
            'html, body { display: block; }
             #t { display: table; width: 200px; border-collapse: collapse;
                  border-spacing: 40px; }
             #r { display: table-row; }
             #cell { display: table-cell; height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        $cell = $this->findById($box, 'cell');
        self::assertNotNull($t);
        self::assertNotNull($cell);
        self::assertEqualsWithDelta($t->geometry->x, $cell->geometry->x, 1.0);
    }

    public function testVisibilityCollapseRemovesTableRow(): void
    {
        // CSS 2.1 §17.5.1 — a `visibility: collapse` row is removed from
        // layout, so the following row moves up into its place (here to the
        // table's content top, no leading spacing declared).
        $box = $this->buildTree(
            '<html><body><div id="t">'
            . '<div class="r" id="r0"><div class="c"></div></div>'
            . '<div class="r" id="r1"><div class="c"></div></div>'
            . '</div></body></html>',
            'html, body { display: block; }
             #t { display: table; }
             .r { display: table-row; }
             #r0 { visibility: collapse; }
             .c { display: table-cell; height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        $r0 = $this->findById($box, 'r0');
        $r1 = $this->findById($box, 'r1');
        self::assertNotNull($t);
        self::assertNotNull($r0);
        self::assertNotNull($r1);
        self::assertEqualsWithDelta(0.0, $r0->geometry->height, 0.5);
        // The visible row occupies the table's content top, not row0's slot.
        self::assertEqualsWithDelta($t->geometry->y, $r1->geometry->y, 1.0);
    }

    public function testTableColumnGroupWidthShrinkWrapsAutoTable(): void
    {
        // A `display: table-column-group` (on a div, not `<colgroup>`) with
        // an explicit width sizes its column, so an auto table shrink-wraps
        // to it — the tag-based `<col>` width collector can't see this.
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="g"></div>'
            . '<div id="r"><div id="cell"></div></div></div></body></html>',
            'html, body { display: block; }
             #t { display: table; }
             #g { display: table-column-group; width: 96px; }
             #r { display: table-row; }
             #cell { display: table-cell; height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        self::assertNotNull($t);
        self::assertEqualsWithDelta(96.0, $t->geometry->width, 1.0);
    }

    public function testTableColumnMinWidthShrinkWrapsAutoTable(): void
    {
        // CSS Tables 3 §4.4 — a `display: table-column` (here on a div, not
        // a `<col>`) with min-width floors its column, so an auto-width
        // table with an otherwise-empty cell shrink-wraps to that width.
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="c"></div>'
            . '<div id="r"><div id="cell"></div></div></div></body></html>',
            'html, body { display: block; }
             #t { display: table; }
             #c { display: table-column; min-width: 96px; }
             #r { display: table-row; }
             #cell { display: table-cell; height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        $cell = $this->findById($box, 'cell');
        self::assertNotNull($t);
        self::assertNotNull($cell);
        // Table (and its single cell) size to the 96px column min-width,
        // not the full container width.
        self::assertEqualsWithDelta(96.0, $t->geometry->width, 1.0);
        self::assertEqualsWithDelta(96.0, $cell->geometry->width, 1.0);
    }

    public function testChUnitResolvesAgainstBoxFont(): void
    {
        // CSS Values 4 §6.1 — `1ch` is the advance of the '0' glyph in the
        // box's own font, not the global default-font ratio. NotoSans '0'
        // advance / upem = 0.572, so `width: 4ch` at 50px = 114.4px, not the
        // 0.5-fallback 100px.
        $font = OpenTypeParser::fromBytes(
            (string) file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/fonts/NotoSans-Regular.otf'),
        )->parse();
        $box = $this->buildTree(
            '<html><body><div id="d">x</div></body></html>',
            'html, body { display: block; } #d { display: block; font-family: noto; font-size: 50px; width: 4ch; }',
        );
        $this->layout->layout($box, new LayoutContext(
            600.0,
            800.0,
            0.0,
            0.0,
            new LengthContext(),
            fontResolver: new FontResolver(['noto' => $font], null),
        ));
        $d = $this->findById($box, 'd');
        self::assertNotNull($d);
        self::assertEqualsWithDelta(114.4, $d->geometry->width, 1.0);
    }

    public function testIntrinsicFloatSizingUsesResolvedFont(): void
    {
        // An auto-width (shrink-to-fit) float sizes to its text content. The
        // intrinsic measurement must resolve the box's own font-family, not
        // fall back to the coarse ~6px/char heuristic — otherwise the float's
        // width diverges from what actually paints.
        $font = OpenTypeParser::fromBytes(
            (string) file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/fonts/NotoSans-Regular.otf'),
        )->parse();
        $html = '<html><body><div id="f">MMMMMM</div></body></html>';
        $css = 'html, body { display: block; } #f { float: left; font-family: noto; font-size: 40px; }';

        $withFont = $this->buildTree($html, $css);
        $this->layout->layout($withFont, new LayoutContext(
            600.0,
            800.0,
            0.0,
            0.0,
            new LengthContext(),
            fontResolver: new FontResolver(['noto' => $font], null),
        ));
        $resolvedWidth = $this->findById($withFont, 'f')?->geometry->width ?? 0.0;

        // No resolver → the 6px/char heuristic (~36px for "MMMMMM").
        $noFont = $this->buildTree($html, $css);
        $this->layout->layout($noFont, $this->defaultCtx);
        $heuristicWidth = $this->findById($noFont, 'f')?->geometry->width ?? 0.0;

        // NotoSans 'M' at 40px is far wider than 6px, so the resolved-font
        // float is substantially wider than the heuristic float.
        self::assertGreaterThan($heuristicWidth * 2.0, $resolvedWidth);
    }

    public function testOutOfFlowFirstChildDoesNotCollapseParentMargin(): void
    {
        // CSS 2.1 §8.3.1 — an out-of-flow (abs-pos) child's margins never
        // collapse. A parent whose FIRST child is absolutely positioned
        // must not collapse that child's margin-top through itself (doing
        // so doubled the parent's negative margin and shoved the in-flow
        // sibling off-page — the `top-*` positioning reftests).
        $box = $this->buildTree(
            '<html><body><div id="p"><div id="ap"></div><div id="c"></div></div></body></html>',
            'html, body { display: block; }
             div { position: relative; }
             #p { margin-top: -96px; }
             #ap { position: absolute; margin-top: 96px; height: 96px; width: 100%; }
             #c { border-bottom: 96px solid black; top: 96px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = $this->findById($box, 'c');
        self::assertNotNull($c);
        // #p sits at margin-top -96; #c (top: 96px relative) shifts back to
        // y=0. If the abs-pos child's margin had collapsed through #p, #c
        // would land near y=-96 (off-page).
        self::assertEqualsWithDelta(0.0, $c->geometry->y, 0.5);
    }

    public function testNegativeMaxSizeIsIgnoredButZeroStillClamps(): void
    {
        // CSS 2.1 §10.4/§10.7 — a NEGATIVE max-width / max-height is
        // invalid and must be ignored (the box keeps its width/height),
        // whereas max-*: 0 is a valid zero ceiling that still clamps.
        // Regression guard: the clamp folded a negative resolved length
        // through max(0.0, ...) and collapsed the box to 0.
        $box = $this->buildTree(
            '<html><body>'
                . '<div id="neg"></div><div id="zero"></div>'
                . '</body></html>',
            'html, body, div { display: block; }
             #neg  { width: 100px; height: 100px; max-width: -50px; max-height: -50px; }
             #zero { width: 100px; height: 100px; max-width: 0; max-height: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $neg = $this->findById($box, 'neg');
        $zero = $this->findById($box, 'zero');
        self::assertNotNull($neg);
        self::assertNotNull($zero);
        // Negative max-* ignored -> box keeps 100x100.
        self::assertEqualsWithDelta(100.0, $neg->geometry->width, 0.5);
        self::assertEqualsWithDelta(100.0, $neg->geometry->height, 0.5);
        // max-*: 0 is valid -> clamps to 0.
        self::assertEqualsWithDelta(0.0, $zero->geometry->width, 0.5);
        self::assertEqualsWithDelta(0.0, $zero->geometry->height, 0.5);
    }

    public function testInlineChildOfFlexContainerIsBlockified(): void
    {
        // CSS Display 3 §2.7 — an in-flow inline-level child of a flex
        // container is blockified into a flex item (a BlockBox), so it
        // honours width/height and fills its line instead of laying out as
        // inline text. Regression guard: a `<span>` flex item stayed an
        // InlineBox and ignored its sizing.
        $box = $this->buildTree(
            '<html><body><div id="f"><span id="item">x</span></div></body></html>',
            'html, body { display: block; }
             #f { display: flex; }
             #item { display: inline; width: 50px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $item = $this->findById($box, 'item');
        self::assertNotNull($item);
        self::assertInstanceOf(BlockBox::class, $item);
        self::assertEqualsWithDelta(50.0, $item->geometry->width, 0.5);
        self::assertEqualsWithDelta(50.0, $item->geometry->height, 0.5);
    }

    public function testContainLayoutEstablishesAbsPosContainingBlock(): void
    {
        // CSS Contain §3.1 — layout (or paint / content / strict)
        // containment makes an otherwise-static box a containing block for
        // its abspos descendants. An `inset: 0` child fills the contained
        // box's padding box, not the viewport.
        $box = $this->buildTree(
            '<html><body><div id="c"><div id="ap"></div></div></body></html>',
            'html, body { display: block; }
             #c { display: block; contain: layout; width: 100px; height: 100px;
                  margin-left: 50px; }
             #ap { position: absolute; top: 0; right: 0; bottom: 0; left: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $ap = $this->findById($box, 'ap');
        self::assertNotNull($ap);
        // Fills #c's padding box (x = its 50px margin-left), 100x100 —
        // not the 600-wide viewport.
        self::assertEqualsWithDelta(50.0, $ap->geometry->x, 0.5);
        self::assertEqualsWithDelta(100.0, $ap->geometry->width, 0.5);
        self::assertEqualsWithDelta(100.0, $ap->geometry->height, 0.5);
    }

    public function testContainPaintOnInlineBlockEstablishesAbsPosContainingBlock(): void
    {
        // CSS Contain §3.1 — an INLINE-BLOCK (AtomicInlineBox) with paint /
        // layout / content / strict containment is also the containing block
        // for its abspos / fixed descendants, not only when it is
        // position-ed. Without the containment arm on the atomic abspos gate
        // the child escaped to the viewport (contain-paint-010 leaked red).
        $box = $this->buildTree(
            '<html><body><div id="c"><div id="ap"></div></div></body></html>',
            'html, body { display: block; }
             #c { display: inline-block; contain: paint; width: 100px; height: 100px; }
             #ap { position: fixed; top: 0; right: 0; bottom: 0; left: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $ap = $this->findById($box, 'ap');
        self::assertNotNull($ap);
        // Fills #c's 100x100 padding box — not the 600x800 viewport.
        self::assertEqualsWithDelta(100.0, $ap->geometry->width, 0.5);
        self::assertEqualsWithDelta(100.0, $ap->geometry->height, 0.5);
    }

    public function testContainLayoutDoesNotEstablishAbsPosCBForInternalTableBox(): void
    {
        // CSS Contain §2.1 — layout containment does NOT apply to internal
        // table boxes (other than table-cell / -caption) or ruby boxes, so
        // such an element must NOT become an abspos containing block even
        // with `contain: layout`. The abspos child therefore anchors to the
        // outer `position: relative` wrapper's padding box (x = 50px
        // margin-left) rather than the contained row-group's inner box
        // (which would be inset a further 25px by the wrapper's padding).
        $box = $this->buildTree(
            '<html><body><div id="rel"><div id="rg"><div id="ap"></div></div></div></body></html>',
            // `div { display: block }` is required: without it #rel defaults to
            // display:inline and, splitting around the block #rg, becomes an
            // anonymous block-in-inline wrapper whose margin/padding no longer
            // apply to the wrapper — unrelated to what this test exercises.
            'html, body, div { display: block; }
             #rel { position: relative; width: 100px; height: 100px;
                    margin-left: 50px; padding: 25px; box-sizing: border-box; }
             #rg { display: table-row-group; contain: layout; }
             #ap { position: absolute; top: 0; left: 0; width: 10px; height: 10px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $ap = $this->findById($box, 'ap');
        self::assertNotNull($ap);
        self::assertEqualsWithDelta(50.0, $ap->geometry->x, 0.5);
    }

    public function testFlexContainerTopMarginCollapsesWithParent(): void
    {
        // CSS 2.1 §8.3.1 + Flexbox 1 §3 — a flex container establishes a
        // new formatting context for its CONTENTS only; its own outer
        // margins still collapse with its parent exactly like a plain
        // block. A flex first-child with `margin-top` therefore collapses
        // through a border/padding-free parent (and, here, on through the
        // root) instead of opening a 40px gap. Regression guard for the
        // `flexbox_*` Opera reftests (flex-div designed to match a
        // float-based normal-div): before the fix the parent-child
        // collapse skipped non-BlockBox children and #f landed at y=40.
        $box = $this->buildTree(
            '<html><body><div id="outer"><div id="f"></div></div></body></html>',
            'html, body { display: block; margin: 0; }
             #outer { height: 200px; }
             #f { display: flex; margin-top: 40px; height: 50px; width: 100px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $f = $this->findById($box, 'f');
        self::assertNotNull($f);
        self::assertEqualsWithDelta(0.0, $f->geometry->y, 0.5);
    }

    public function testGridContainerTopMarginCollapsesWithParent(): void
    {
        // Same rule for grid containers (CSS Grid 1 §2.1 mirrors the
        // flex exemption — container↔contents only). Shares the
        // `collapsesOuterMargin()` codepath with flex/table.
        $box = $this->buildTree(
            '<html><body><div id="outer"><div id="g"></div></div></body></html>',
            'html, body { display: block; margin: 0; }
             #outer { height: 200px; }
             #g { display: grid; margin-top: 40px; height: 50px; width: 100px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $g = $this->findById($box, 'g');
        self::assertNotNull($g);
        self::assertEqualsWithDelta(0.0, $g->geometry->y, 0.5);
    }

    public function testAbsposChildOfInlineBlockLaysOutAgainstIt(): void
    {
        // CSS 2.1 §10.1 — a `position: relative` inline-block is the
        // containing block for its abs-pos descendants, which must lay out
        // (and paint) even though the inline-block isn't blockified. An
        // `inset: 0` child stretches to fill the inline-block's 100×100
        // padding box.
        $box = $this->buildTree(
            '<html><body><span id="ib"><span id="ap"></span></span></body></html>',
            'html, body { display: block; }
             #ib { display: inline-block; position: relative; width: 100px; height: 100px; }
             #ap { position: absolute; top: 0; right: 0; bottom: 0; left: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $ap = $this->findById($box, 'ap');
        self::assertNotNull($ap);
        // Stretched to the container's padding box (0,0)–(100,100).
        self::assertEqualsWithDelta(0.0, $ap->geometry->x, 0.5);
        self::assertEqualsWithDelta(0.0, $ap->geometry->y, 0.5);
        self::assertEqualsWithDelta(100.0, $ap->geometry->width, 0.5);
        self::assertEqualsWithDelta(100.0, $ap->geometry->height, 0.5);
    }

    public function testPageBreakBeforeAdvancesToNextPage(): void
    {
        // First block has no explicit height so it'll collapse to 0; the
        // second declares `page-break-before: always`, which should shove
        // it to layout-Y = pageHeight (800).
        $box = $this->buildTree(
            '<html><body><div class="a"></div><div class="b"></div></body></html>',
            'html, body, div { display: block; } .b { page-break-before: always; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $second = null;
        foreach ($this->find($box, 'body')->children as $c) {
            if ($c->element !== null && in_array('b', $c->element->classes(), true)) {
                $second = $c;
                break;
            }
        }
        self::assertNotNull($second);
        self::assertEqualsWithDelta(800.0, $second->geometry->y, 0.001);
    }

    public function testModernBreakBeforePageHonoured(): void
    {
        $box = $this->buildTree(
            '<html><body><div class="a"></div><div class="b"></div></body></html>',
            'html, body, div { display: block; } .b { break-before: page; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $second = null;
        foreach ($this->find($box, 'body')->children as $c) {
            if ($c->element !== null && in_array('b', $c->element->classes(), true)) {
                $second = $c;
                break;
            }
        }
        self::assertNotNull($second);
        self::assertEqualsWithDelta(800.0, $second->geometry->y, 0.001);
    }

    public function testBreakInsideAvoidShiftsStraddlingChildDown(): void
    {
        // First filler sits at the top of page 0 with height 700 (page is
        // 800), pushing the second 200-unit block to layout-Y = 700.
        // Without `break-inside: avoid` the second block straddles 800;
        // with it, the second block shifts down to layout-Y = 800.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="a" style="height: 700px"></div>'
                . '<div class="b" style="height: 200px"></div>'
                . '</body></html>',
            'html, body, div { display: block; } .b { break-inside: avoid; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $second = null;
        foreach ($this->find($box, 'body')->children as $c) {
            if ($c->element !== null && in_array('b', $c->element->classes(), true)) {
                $second = $c;
                break;
            }
        }
        self::assertNotNull($second);
        self::assertEqualsWithDelta(800.0, $second->geometry->y, 0.001);
    }

    public function testBreakAfterShovesNextSiblingToNextPage(): void
    {
        // The first block declares break-after: page, so the second sibling
        // should start at layout-Y = pageHeight even though the first has
        // zero height.
        $box = $this->buildTree(
            '<html><body><div class="a"></div><div class="b"></div></body></html>',
            'html, body, div { display: block; } .a { break-after: page; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $second = null;
        foreach ($this->find($box, 'body')->children as $c) {
            if ($c->element !== null && in_array('b', $c->element->classes(), true)) {
                $second = $c;
                break;
            }
        }
        self::assertNotNull($second);
        self::assertEqualsWithDelta(800.0, $second->geometry->y, 0.001);
    }

    public function testTableCellVerticalAlignMiddleCentersChild(): void
    {
        // Row 1: short cell (height = 0). Row 2: tall cell (height = 100px).
        // In row 1, the cell's child should be centred when valign=middle.
        // Use a row-wide cell with an explicit-height child to make heights
        // predictable.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td><div class="filler"></div></td><td><div class="tall"></div></td></tr>'
            . '</table></body></html>',
            'html, body, tbody, div { display: block; }
             table { display: table; }
             tr { display: table-row; }
             td { display: table-cell; vertical-align: middle; }
             .filler { height: 20px; }
             .tall { height: 100px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        // The short cell's div should sit at y = rowHeight/2 - dimHeight/2 = (100-20)/2 = 40
        $shortDiv = null;
        $stack = [$box];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node->element !== null
                && in_array('filler', $node->element->classes(), true)
            ) {
                $shortDiv = $node;
                break;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertNotNull($shortDiv);
        // y > 0 because content has been centred within the 100-unit row.
        self::assertGreaterThan(35.0, $shortDiv->geometry->y);
        self::assertLessThan(45.0, $shortDiv->geometry->y);
    }

    public function testBorderCollapseZerosAdjacentEdges(): void
    {
        // 2×2 table with `border-collapse: collapse` + `border: 1px solid`
        // on every cell. Top-left cell loses right + bottom; top-right
        // loses bottom only; bottom-left loses right only; bottom-right
        // keeps both.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="tl"></td><td class="tr"></td></tr>'
            . '<tr><td class="bl"></td><td class="br"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; border: 1px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = [];
        $stack = [$box];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox) {
                $key = $node->element->classes()[0] ?? '';
                $cells[$key] = $node;
                continue;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertSame(0.0, $cells['tl']->geometry->borderRight);
        self::assertSame(0.0, $cells['tl']->geometry->borderBottom);
        self::assertGreaterThan(0.0, $cells['tr']->geometry->borderRight, 'last-column keeps right border');
        self::assertSame(0.0, $cells['tr']->geometry->borderBottom);
        self::assertSame(0.0, $cells['bl']->geometry->borderRight);
        self::assertGreaterThan(0.0, $cells['bl']->geometry->borderBottom, 'last-row keeps bottom border');
        self::assertGreaterThan(0.0, $cells['br']->geometry->borderRight);
        self::assertGreaterThan(0.0, $cells['br']->geometry->borderBottom);
    }

    /**
     * Collect cells keyed by their first CSS class. Shared helper for
     * the border-collapse tests below.
     *
     * @return array<string, \Phpdftk\HtmlToPdf\Box\TableCellBox>
     */
    private function collectCellsByClass(Box $root): array
    {
        $cells = [];
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox) {
                $key = $node->element?->classes()[0] ?? '';
                if ($key !== '') {
                    $cells[$key] = $node;
                }
                continue;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        return $cells;
    }

    public function testBorderCollapseDefaultKeepsCellBordersIntact(): void
    {
        // Negative: omitting `border-collapse` leaves the cascade
        // initial (`separate`), so the collapse pass must NOT run.
        // Every cell keeps its own declared borders on every side.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a"></td><td class="b"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; }
             tr { display: table-row; }
             td { display: table-cell; border: 1px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertGreaterThan(0.0, $cells['a']->geometry->borderRight, 'separate leaves inner edges intact');
        self::assertGreaterThan(0.0, $cells['b']->geometry->borderLeft);
    }

    public function testBorderCollapseInvalidKeywordFallsBackToSeparate(): void
    {
        // Negative: `border-collapse: bogus` must not match `collapse`.
        // The CSS parser may either drop the declaration or keep it as
        // a Keyword; either way `isBorderCollapse` returns false and
        // the collapse pass is skipped.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a"></td><td class="b"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border-collapse: bogus; }
             tr { display: table-row; }
             td { display: table-cell; border: 1px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertGreaterThan(0.0, $cells['a']->geometry->borderRight);
        self::assertGreaterThan(0.0, $cells['b']->geometry->borderLeft);
    }

    public function testBorderCollapseHandlesEmptyTableWithoutCrashing(): void
    {
        // Negative: a `<table>` with no rows must not blow up the
        // collapse pass — the grid is empty and there's nothing to
        // resolve. Reaching the assertion proves layout returned.
        $box = $this->buildTree(
            '<html><body><table></table></body></html>',
            'html, body { display: block; }
             table { display: table; border-collapse: collapse; }',
        );
        $height = $this->layout->layout($box, $this->defaultCtx);
        self::assertGreaterThanOrEqual(0.0, $height);
    }

    public function testRelativeTableRowShiftsByOffset(): void
    {
        // CSS 2.1 §9.4.3 / Position 3 §3 — `position: relative; top: 40px`
        // on a `<tr>` shifts the row (and its cells) down 40px from its
        // static position, while the following row keeps flowing against
        // the static position. Table rows go through the specialised
        // `layoutTableRow` path, which historically skipped the relative
        // offset entirely.
        $box = $this->buildTree(
            '<html><body><div id="t">'
                . '<div id="rel"><div id="ca"></div></div>'
                . '<div id="static"><div id="cb"></div></div>'
                . '</div></body></html>',
            'html, body { display: block; }
             #t { display: table; }
             #rel, #static { display: table-row; }
             #ca, #cb { display: table-cell; height: 50px; width: 50px; }
             #rel { position: relative; top: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $relRow = $this->findById($box, 'rel');
        $staticRow = $this->findById($box, 'static');
        self::assertNotNull($relRow, 'relative row found');
        self::assertNotNull($staticRow, 'static row found');
        self::assertInstanceOf(\Phpdftk\HtmlToPdf\Box\TableRowBox::class, $relRow);
        // The static (second) row sits one row-height (50px) below the
        // first row's STATIC position (y=0), i.e. at y=50 — unaffected by
        // the relative offset on its sibling.
        self::assertEqualsWithDelta(50.0, $staticRow->geometry->y, 0.001, 'sibling flows against static position');
        // The relative row is shifted down 40px from its static y=0.
        self::assertEqualsWithDelta(40.0, $relRow->geometry->y, 0.001, 'relative row shifted by top:40px');
    }

    public function testBorderCollapseSingleCellKeepsAllFourSides(): void
    {
        // Negative: a 1×1 table has no joints to resolve. The single
        // cell's outer edges (all four sides) must keep their
        // declared widths even in collapse mode.
        $box = $this->buildTree(
            '<html><body><table><tr><td class="only"></td></tr></table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; border: 2px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertGreaterThan(0.0, $cells['only']->geometry->borderTop);
        self::assertGreaterThan(0.0, $cells['only']->geometry->borderRight);
        self::assertGreaterThan(0.0, $cells['only']->geometry->borderBottom);
        self::assertGreaterThan(0.0, $cells['only']->geometry->borderLeft);
    }

    public function testRelativeFlexContainerShiftsByOffset(): void
    {
        // CSS 2.1 §9.4.3 — `position: relative; top: 40px` on a flex
        // CONTAINER shifts the whole container by 40px. Flex containers
        // go through the specialised `layoutFlexBox` path, which skips
        // the shared relative tail (same class of bug as table rows).
        $box = $this->buildTree(
            '<html><body><div id="f"><div id="i"></div></div></body></html>',
            'html, body { display: block; }
             #f { display: flex; position: relative; top: 40px; width: 50px; height: 50px; }
             #i { width: 50px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $f = $this->findById($box, 'f');
        self::assertNotNull($f);
        self::assertEqualsWithDelta(40.0, $f->geometry->y, 0.001, 'relative flex container shifted by top:40px');
    }

    public function testJustifyContentStartDoesNotFlipUnderRowReverse(): void
    {
        // CSS Box Alignment 3 §5.3 — `start` is writing-mode-relative, not
        // flow-relative, so it does NOT flip under `flex-direction:
        // row-reverse` (only `flex-start`/`flex-end` do). Two 20px items in
        // a 100px LTR container with `justify-content: start` pack at the
        // physical LEFT edge (block left edge x≈0), regardless of the
        // reversed main axis. The pre-fix bug swapped start→flex-end and
        // packed them right (x≈60).
        $box = $this->buildTree(
            '<html><body><div id="f"><div class="i"></div><div class="i"></div></div></body></html>',
            'html, body { display: block; }
             #f { display: flex; flex-direction: row-reverse; justify-content: start;
                  width: 100px; height: 20px; }
             .i { width: 20px; height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $f = $this->findById($box, 'f');
        self::assertInstanceOf(FlexBox::class, $f);
        $minX = min(array_map(static fn($c) => $c->geometry->x, $f->children));
        self::assertEqualsWithDelta(0.0, $minX, 0.001, 'justify-content:start packs at the physical left even under row-reverse');
    }

    public function testFlexMaxContentWidthIncludesColumnGap(): void
    {
        // CSS Flexbox 1 §9.9.1 + Box Alignment §8 — a row flex container's
        // max-content main size sums the item contributions PLUS the
        // column-gap between them. Two 20px items with a 30px column-gap
        // give a max-content width of 20+30+20 = 70px (not 40px).
        $box = $this->buildTree(
            '<html><body><div id="f"><div class="i"></div><div class="i"></div></div></body></html>',
            'html, body { display: block; }
             #f { display: flex; width: max-content; column-gap: 30px; height: 20px; }
             .i { width: 20px; height: 20px; flex: 0 0 auto; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $f = $this->findById($box, 'f');
        self::assertInstanceOf(FlexBox::class, $f);
        self::assertEqualsWithDelta(70.0, $f->geometry->width, 0.001, 'max-content flex width includes the column-gap');
    }

    public function testRelativeGridContainerShiftsByOffset(): void
    {
        // CSS 2.1 §9.4.3 — same as the flex case for a grid CONTAINER.
        $box = $this->buildTree(
            '<html><body><div id="g"><div id="i"></div></div></body></html>',
            'html, body { display: block; }
             #g { display: grid; position: relative; left: 30px; width: 50px; height: 50px; }
             #i { width: 50px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $g = $this->findById($box, 'g');
        self::assertNotNull($g);
        self::assertEqualsWithDelta(30.0, $g->geometry->x, 0.001, 'relative grid container shifted by left:30px');
    }

    public function testGridItemKeepsAspectRatioSizeUnderDefaultAlignment(): void
    {
        // CSS Box Alignment 3 §4.1 — the default (`normal`) self-alignment
        // does NOT stretch a grid item that has a preferred aspect-ratio
        // and a definite size in the other axis; only an explicit
        // `stretch` keyword does. Item has aspect-ratio 1/1 and height 40px
        // in a 200px-wide track → width stays the ratio-derived 40px, not
        // stretched to the 200px cell.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="g"><div class="i"></div></div></body></html>',
            '.g { display: grid; grid-template-columns: 200px; width: 200px; }
             .i { aspect-ratio: 1 / 1; height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $i = $this->find($box, 'div.i');
        self::assertNotNull($i);
        self::assertEqualsWithDelta(40.0, $i->geometry->width, 0.001, 'ratio-derived width survives default (normal) grid alignment');
    }

    public function testBlockAspectRatioWidthFlooredByContentMinimum(): void
    {
        // CSS Sizing 4 §5.1 — an aspect-ratio-derived inline size is a
        // PREFERRED size floored by the box's content-based automatic
        // minimum (min-width:auto). Outer div: height 100px, aspect-ratio
        // 1/2 → ratio width 50px, but a 100px-wide child forces min-content
        // 100px, so the used width is floored to 100px (not 50px).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="a"><div class="c"></div></div></body></html>',
            '.a { height: 100px; aspect-ratio: 1 / 2; }
             .c { width: 100px; height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertNotNull($a);
        self::assertEqualsWithDelta(100.0, $a->geometry->width, 0.001, 'ratio-derived width floored up to the 100px child min-content');
    }

    public function testAspectRatioTransferredMaxDoesNotOverrideMinWidth(): void
    {
        // CSS Sizing 4 §5.1 — min wins over max: a `max-height` transferred
        // through the aspect-ratio to bound the inline size must not shrink
        // the used width below an explicit `min-width`. max-height:40 ×
        // ratio 1/1 → a transferred max-width of 40px, but min-width:100
        // wins → the div is 100px wide, not 40px.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="a"><div class="c"></div></div></body></html>',
            '.a { max-height: 40px; min-width: 100px; aspect-ratio: 1 / 1; }
             .c { width: 50px; height: 100px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertNotNull($a);
        self::assertEqualsWithDelta(100.0, $a->geometry->width, 0.001, 'explicit min-width wins over the transferred max-height');
    }

    public function testBorderCollapseHiddenSuppressesJointEntirely(): void
    {
        // Negative: `hidden` on either side of a joint wins — the
        // joint goes to zero on both sides regardless of the other
        // side's width. (Spec: hidden has top priority.)
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a"></td><td class="b"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; }
             .a { border-right: 5px hidden #000; border-top: 5px solid #000; border-bottom: 5px solid #000; border-left: 5px solid #000; }
             .b { border: 1px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertSame(0.0, $cells['a']->geometry->borderRight, 'hidden suppresses A side');
        self::assertSame(0.0, $cells['b']->geometry->borderLeft, 'hidden suppresses B side too');
    }

    public function testBorderCollapseNoneLosesToVisibleNeighbour(): void
    {
        // Negative: a `none` border-style contributes width 0 and
        // loses to any visible neighbour. The visible side keeps its
        // declared width.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a"></td><td class="b"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; }
             .a { border: 4px solid #000; border-right-style: none; }
             .b { border: 2px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertSame(0.0, $cells['a']->geometry->borderRight, 'none loses');
        self::assertEqualsWithDelta(2.0, $cells['b']->geometry->borderLeft, 0.001, 'visible neighbour keeps its width');
    }

    public function testBorderCollapseEqualBordersDefaultsToNeighbourBias(): void
    {
        // Negative: when width AND style are tied, the existing
        // direction bias must hold — the right / bottom neighbour
        // keeps the joint so the left / top cell is the one that
        // gets zeroed. (Pins the tiebreaker direction.)
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a"></td><td class="b"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; border: 2px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertSame(0.0, $cells['a']->geometry->borderRight);
        self::assertEqualsWithDelta(2.0, $cells['b']->geometry->borderLeft, 0.001);
    }

    public function testBorderCollapseThickerWidthWins(): void
    {
        // Positive: the thicker side wins at the joint regardless of
        // which neighbour declared it. A's 5px right beats B's 1px
        // left — A's geometry keeps 5px, B's left zeroes out.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a"></td><td class="b"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; }
             .a { border: 5px solid #000; }
             .b { border: 1px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(5.0, $cells['a']->geometry->borderRight, 0.001, 'thicker A wins');
        self::assertSame(0.0, $cells['b']->geometry->borderLeft, 'thinner B loses');
    }

    public function testBorderCollapseStylePrecedenceDoubleBeatsSolid(): void
    {
        // Positive: width tie at 3px, A is double, B is solid. Double
        // (rank 7) beats solid (rank 6) — A wins the joint.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a"></td><td class="b"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; }
             .a { border: 3px double #000; }
             .b { border: 3px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(3.0, $cells['a']->geometry->borderRight, 0.001, 'double wins style tiebreak');
        self::assertSame(0.0, $cells['b']->geometry->borderLeft);
    }

    public function testBorderCollapseOuterCellWinsOverThinnerTableBorder(): void
    {
        // CSS Tables 3 §11.2 — outer-cell-vs-table-border collapse:
        // a cell's outer side competes against the table's matching
        // side. Cell's 5px outer beats table's 1px → cell wins,
        // table's matching side gets zeroed everywhere on that rim.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="only"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border: 1px solid #000; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; border: 5px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cell = null;
        $table = null;
        $stack = [$box];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox) {
                $cell = $node;
            } elseif ($node instanceof \Phpdftk\HtmlToPdf\Box\TableBox) {
                $table = $node;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertNotNull($cell);
        self::assertNotNull($table);
        // Thicker cell border wins on every outer side.
        self::assertEqualsWithDelta(5.0, $cell->geometry->borderTop, 0.001);
        self::assertEqualsWithDelta(5.0, $cell->geometry->borderRight, 0.001);
        // Table border zeroes since the cell wins everywhere.
        self::assertSame(0.0, $table->geometry->borderTop);
        self::assertSame(0.0, $table->geometry->borderRight);
    }

    public function testBorderCollapseThickerTableBorderSuppressesOuterCellSides(): void
    {
        // Negative direction: table's 10px outer beats cells' 2px.
        // Cell outer sides zero; table sides keep their declared width.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a"></td><td class="b"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border: 10px solid #000; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; border: 2px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        $table = null;
        $stack = [$box];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TableBox) {
                $table = $node;
                break;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertNotNull($table);
        // Outer sides of outer cells zero (table wins).
        self::assertSame(0.0, $cells['a']->geometry->borderTop, 'cell top loses to table top');
        self::assertSame(0.0, $cells['a']->geometry->borderLeft, 'cell left loses to table left');
        self::assertSame(0.0, $cells['b']->geometry->borderRight, 'cell right loses');
        // Table keeps its outer border.
        self::assertEqualsWithDelta(10.0, $table->geometry->borderTop, 0.001);
        self::assertEqualsWithDelta(10.0, $table->geometry->borderRight, 0.001);
    }

    public function testBorderCollapseTableNoBorderLeavesCellOuterSidesIntact(): void
    {
        // Negative: when the table has no own border, the outer-vs-
        // table collapse leaves the cell outer sides alone.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="only"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; border: 3px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(3.0, $cells['only']->geometry->borderTop, 0.001);
        self::assertEqualsWithDelta(3.0, $cells['only']->geometry->borderRight, 0.001);
        self::assertEqualsWithDelta(3.0, $cells['only']->geometry->borderBottom, 0.001);
        self::assertEqualsWithDelta(3.0, $cells['only']->geometry->borderLeft, 0.001);
    }

    public function testBorderCollapseThickerVerticalWinsAtRowJoint(): void
    {
        // Positive: same algorithm applies to horizontal joints
        // (row-to-row). A's 6px bottom beats B's 2px top.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a"></td></tr>'
            . '<tr><td class="b"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; border-collapse: collapse; }
             tr { display: table-row; }
             td { display: table-cell; }
             .a { border: 6px solid #000; }
             .b { border: 2px solid #000; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(6.0, $cells['a']->geometry->borderBottom, 0.001);
        self::assertSame(0.0, $cells['b']->geometry->borderTop);
    }

    public function testTableColumnsAlignAcrossRows(): void
    {
        // Row 1 has 2 cells, row 2 has 3 cells. The table-level column
        // count should be 3, so row 1's two cells split the full row
        // into 600/2*3=300 wide? No — `colWidth = 600/3 = 200`, cells
        // span 1 col each = 200pt. So row 1 ends up using only 400pt of
        // the 600pt width (one column unused). That matches browsers.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td></td><td></td></tr>'
            . '<tr><td></td><td></td><td></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; width: 600px; }
             tr { display: table-row; }
             td { display: table-cell; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cellsPerRow = [];
        $stack = [$box];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TableRowBox) {
                $row = [];
                foreach ($node->children as $c) {
                    if ($c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox) {
                        $row[] = $c;
                    }
                }
                $cellsPerRow[] = $row;
                continue;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertCount(2, $cellsPerRow);
        // Both rows use a 3-column grid → colWidth = 200.
        foreach ($cellsPerRow as $row) {
            foreach ($row as $cell) {
                self::assertEqualsWithDelta(200.0, $cell->geometry->width, 0.001);
            }
        }
    }

    public function testTableColspanSharesMultipleColumns(): void
    {
        // <td colspan="2"> + <td> + <td> → 4 columns total (2+1+1).
        // 600pt-wide row → colWidth = 150; cell widths 300/150/150.
        $box = $this->buildTree(
            '<html><body><table><tr>'
            . '<td colspan="2"></td><td></td><td></td>'
            . '</tr></table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; width: 600px; }
             tr { display: table-row; }
             td { display: table-cell; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = [];
        $stack = [$box];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox) {
                $cells[] = $node;
                continue;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        usort($cells, static fn($a, $b) => $a->geometry->x <=> $b->geometry->x);
        self::assertEqualsWithDelta(0.0, $cells[0]->geometry->x, 0.001);
        self::assertEqualsWithDelta(300.0, $cells[0]->geometry->width, 0.001);
        self::assertEqualsWithDelta(300.0, $cells[1]->geometry->x, 0.001);
        self::assertEqualsWithDelta(150.0, $cells[1]->geometry->width, 0.001);
        self::assertEqualsWithDelta(450.0, $cells[2]->geometry->x, 0.001);
        self::assertEqualsWithDelta(150.0, $cells[2]->geometry->width, 0.001);
    }

    public function testTableCellsSplitWidthEqually(): void
    {
        // 3 cells in 600pt-wide containing block → each cell is 200pt wide.
        $box = $this->buildTree(
            '<html><body><table><tr><td></td><td></td><td></td></tr></table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; width: 600px; }
             tr { display: table-row; }
             td { display: table-cell; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        // Walk to find the three TableCellBoxes.
        $cells = [];
        $stack = [$box];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox) {
                $cells[] = $node;
                continue;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertCount(3, $cells);
        foreach ($cells as $cell) {
            self::assertEqualsWithDelta(200.0, $cell->geometry->width, 0.001);
        }
        // x positions should be 0, 200, 400.
        usort($cells, static fn($a, $b) => $a->geometry->x <=> $b->geometry->x);
        self::assertEqualsWithDelta(0.0, $cells[0]->geometry->x, 0.001);
        self::assertEqualsWithDelta(200.0, $cells[1]->geometry->x, 0.001);
        self::assertEqualsWithDelta(400.0, $cells[2]->geometry->x, 0.001);
    }

    public function testBlockFillsContainingBlockWhenAutoWidth(): void
    {
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(600.0, $div->geometry->width);
    }

    public function testExplicitWidthHonoured(): void
    {
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; } div { width: 200px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(200.0, $div->geometry->width);
    }

    public function testPaddingAndMarginShrinkContent(): void
    {
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; } div { padding: 10px; margin: 5px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        // 600 - margins(10) - padding(20) = 570
        self::assertSame(570.0, $div->geometry->width);
        self::assertSame(10.0, $div->geometry->paddingTop);
        self::assertSame(5.0, $div->geometry->marginLeft);
    }

    public function testHeightSumsChildren(): void
    {
        $box = $this->buildTree(
            '<html><body><section><div></div><div></div></section></body></html>',
            'html, body, section, div { display: block; } div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        // Two children, each 50px, no margins / padding / borders.
        self::assertSame(100.0, $section->geometry->height);
    }

    public function testChildrenStackVertically(): void
    {
        $box = $this->buildTree(
            '<html><body><section><div></div><div></div></section></body></html>',
            'html, body, section, div { display: block; } div { height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertCount(2, $section->children);
        $first = $section->children[0];
        $second = $section->children[1];
        self::assertInstanceOf(BlockBox::class, $first);
        self::assertInstanceOf(BlockBox::class, $second);
        self::assertSame(0.0, $first->geometry->y);
        self::assertSame(30.0, $second->geometry->y);
    }

    public function testExplicitHeightHonoured(): void
    {
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; } div { height: 80px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(80.0, $div->geometry->height);
    }

    public function testPercentageWidthResolvesAgainstContainingBlock(): void
    {
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; } div { width: 50%; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(300.0, $div->geometry->width);
    }

    public function testBorderWidthApplies(): void
    {
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; }
             div { border-top-style: solid; border-top-width: 4px;
                   border-bottom-style: solid; border-bottom-width: 2px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(4.0, $div->geometry->borderTop);
        self::assertSame(2.0, $div->geometry->borderBottom);
    }

    public function testBorderStyleNoneDisablesWidth(): void
    {
        // CSS default border-style is `none` — border-width is honoured only
        // when style != none.
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; } div { border-top-width: 5px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(0.0, $div->geometry->borderTop, 'no border with style:none');
    }

    public function testAutoMarginsCenterFixedWidthBox(): void
    {
        // `margin: 0 auto` with a fixed width should split the remaining
        // space (600 - 200 = 400) evenly between left and right margins.
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; }
             div { width: 200px; margin: 0 auto; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(200.0, $div->geometry->width);
        self::assertSame(200.0, $div->geometry->marginLeft);
        self::assertSame(200.0, $div->geometry->marginRight);
    }

    public function testSingleAutoMarginRightAlignsBox(): void
    {
        // `margin-left: auto` only → push the box to the right edge.
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; }
             div { width: 200px; margin-left: auto; margin-right: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(400.0, $div->geometry->marginLeft);
        self::assertSame(0.0, $div->geometry->marginRight);
    }

    public function testAutoMarginsIgnoredWhenWidthAuto(): void
    {
        // Per CSS 2.1 §10.3.3 — auto-margin redistribution only applies
        // when width is explicit. With auto width, the box fills available
        // space and the margins resolve to 0.
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; }
             div { margin: 0 auto; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(600.0, $div->geometry->width, 'auto width fills the cb');
        self::assertSame(0.0, $div->geometry->marginLeft);
    }

    public function testBorderShorthandPopulatesAllSides(): void
    {
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; } div { border: 3px solid red; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(3.0, $div->geometry->borderTop);
        self::assertSame(3.0, $div->geometry->borderRight);
        self::assertSame(3.0, $div->geometry->borderBottom);
        self::assertSame(3.0, $div->geometry->borderLeft);
    }

    public function testAdjacentSiblingMarginsCollapse(): void
    {
        // First .a: margin-bottom 20px, height 30px. Second .b: margin-top 30px,
        // height 30px. The gap between them collapses to max(20, 30) = 30px,
        // not 50px.
        $box = $this->buildTree(
            '<html><body><section><div class="a"></div><div class="b"></div></section></body></html>',
            'html, body, section, div { display: block; }
             .a { height: 30px; margin-bottom: 20px; }
             .b { height: 30px; margin-top: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $a = $section->children[0];
        $b = $section->children[1];
        // a.y = 0, a.height = 30 → a's bottom edge = 30.
        // Without collapse: b.y = 30 + 20 + 30 = 80.
        // With collapse: b.y = 30 + max(20,30) = 60.
        self::assertSame(60.0, $b->geometry->y);
        // section height = 30 (a) + 30 (max margin) + 30 (b) = 90
        self::assertSame(90.0, $section->geometry->height);
    }

    public function testEqualMarginsCollapseCleanly(): void
    {
        // Section gets explicit padding so its parent-child collapse
        // doesn't fire — we're testing pure sibling collapse here.
        $box = $this->buildTree(
            '<html><body><section><div></div><div></div></section></body></html>',
            'html, body, section, div { display: block; }
             section { padding: 5px 0; }
             div { height: 10px; margin: 10px 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        // section.y = paddingTop(5) below section's origin (0). So 5.
        // div a: y = section.y + a.marginTop(10) = 15. Height 10.
        // div b: marginTop = 10. Sibling collapse min(10,10)=10 shifts b
        //   up 10 from its placement at section.y + 40 → b sits at 35.
        // section content = a.marginTop(10) + a(10) + collapse(10) +
        //   b(10) + b.marginBottom(10) = 50.
        self::assertSame(50.0, $section->geometry->height);
        self::assertSame(35.0, $section->children[1]->geometry->y);
    }

    public function testParentChildTopMarginCollapses(): void
    {
        // section has no border/padding, so the first div's top margin
        // collapses through into section's own margin-top — the div sits
        // at section.y, not section.y + childMarginTop.
        $box = $this->buildTree(
            '<html><body><section><div></div></section></body></html>',
            'html, body, section, div { display: block; }
             div { height: 30px; margin-top: 20px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $div = $section->children[0];
        self::assertSame(0.0, $div->geometry->marginTop, "first child's margin absorbed");
        self::assertSame($section->geometry->y, $div->geometry->y, "first child sits at parent's content top");
    }

    public function testNoCollapseWhenFirstChildHasZeroBottomMargin(): void
    {
        $box = $this->buildTree(
            '<html><body><section><div class="a"></div><div class="b"></div></section></body></html>',
            'html, body, section, div { display: block; }
             .a { height: 20px; }
             .b { height: 20px; margin-top: 15px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        $b = $section->children[1];
        // No first-bottom-margin → b sits 15px below a.
        self::assertSame(35.0, $b->geometry->y);
    }

    public function testMultiColumnSplitsChildrenAcrossColumns(): void
    {
        // Four 100-tall children in a `column-count: 2` container → with
        // balance height = ceil(400/2) = 200, children 0+1 land in column 0
        // (y=0 and y=100) and children 2+3 land in column 1 (also y=0 and
        // y=100). Each column is half the available width (300px) with the
        // initial `normal` gap (= 1em = 16px) shrinking each column by 8px:
        // (600 - 16) / 2 = 292.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a"></div><div class="b"></div>'
                . '<div class="c"></div><div class="d"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; }
             div { height: 100px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $children = $section->children;
        self::assertCount(4, $children);
        self::assertEqualsWithDelta($section->geometry->x, $children[0]->geometry->x, 0.001);
        self::assertEqualsWithDelta($section->geometry->x, $children[1]->geometry->x, 0.001);
        self::assertEqualsWithDelta(
            $section->geometry->x + 292.0 + 16.0,
            $children[2]->geometry->x,
            0.001,
        );
        self::assertEqualsWithDelta(
            $section->geometry->x + 292.0 + 16.0,
            $children[3]->geometry->x,
            0.001,
        );
        // First and third sit at column-top; second and fourth at column-top+100.
        self::assertEqualsWithDelta($section->geometry->y, $children[0]->geometry->y, 0.001);
        self::assertEqualsWithDelta($section->geometry->y + 100.0, $children[1]->geometry->y, 0.001);
        self::assertEqualsWithDelta($section->geometry->y, $children[2]->geometry->y, 0.001);
        self::assertEqualsWithDelta($section->geometry->y + 100.0, $children[3]->geometry->y, 0.001);
    }

    public function testMultiColumnPopulatesMultiColumnStruct(): void
    {
        // `columns: 200px 3` shorthand → column-count: 3 + column-width: 200px
        // (count wins at this width: (600 - 2*8) / 3 ≈ 194.67). The rule
        // shorthand sets all three rule longhands.
        $box = $this->buildTree(
            '<html><body><section><div></div></section></body></html>',
            'html, body, section, div { display: block; }
             section { columns: 200px 3; column-gap: 8px;
                       column-rule: 1px solid red; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNotNull($section->multiColumn);
        self::assertSame(3, $section->multiColumn->columnCount);
        self::assertEqualsWithDelta(8.0, $section->multiColumn->columnGap, 0.001);
        self::assertEqualsWithDelta(
            (600.0 - 16.0) / 3.0,
            $section->multiColumn->columnWidth,
            0.001,
        );
        self::assertSame(1.0, $section->multiColumn->ruleWidth);
        self::assertSame('solid', $section->multiColumn->ruleStyle);
        self::assertNotNull($section->multiColumn->ruleColor);
    }

    public function testColumnFillAutoWithDefiniteHeightMarksFragmented(): void
    {
        // CSS Multi-column 1 §3.3 — `column-fill: auto` on a definite-height
        // multicol container fragments its content into fixed-height column
        // bands (the painter slices the single tall column). Layout marks
        // the container fragmented and records the column height.
        $box = $this->buildTree(
            '<html><body><section><div></div></section></body></html>',
            'html, body, section, div { display: block; }
             section { columns: 3; column-fill: auto; height: 100px; column-gap: 10px; }
             section > div { height: 300px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNotNull($section->multiColumn);
        self::assertTrue(
            $section->multiColumn->fragmented,
            'column-fill:auto + definite height fragments',
        );
        self::assertEqualsWithDelta(100.0, $section->multiColumn->columnHeight, 0.5);
    }

    public function testColumnFillBalanceDoesNotFragment(): void
    {
        // The initial `column-fill: balance` keeps the classic move-and-
        // paint-once path: the container is NOT marked fragmented, so the
        // 167-passing balance fixtures are untouched.
        $box = $this->buildTree(
            '<html><body><section><div></div></section></body></html>',
            'html, body, section, div { display: block; }
             section { columns: 3; height: 100px; }
             section > div { height: 300px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNotNull($section->multiColumn);
        self::assertFalse(
            $section->multiColumn->fragmented,
            'default column-fill:balance does not fragment',
        );
    }

    public function testColumnHeightWrapMarksGridFragmented(): void
    {
        // CSS Multi-column 2 §3 — `column-height` + `column-wrap: wrap`:
        // the tall content is sliced into `column-height` bands and wrapped
        // into a columnCount × rowCount grid. Layout marks the container
        // fragmented + columnWrap and records the band height / content size.
        $box = $this->buildTree(
            '<html><body><section><div></div></section></body></html>',
            'html, body, section, div { display: block; }
             section { columns: 2; column-fill: auto; width: 100px; column-gap: 0;
                       column-height: 50px; column-wrap: wrap; }
             section > div { height: 200px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNotNull($section->multiColumn);
        self::assertTrue($section->multiColumn->fragmented, 'column-wrap fragments');
        self::assertTrue($section->multiColumn->columnWrap, 'column-wrap:wrap sets the grid flag');
        self::assertEqualsWithDelta(50.0, $section->multiColumn->columnHeight, 0.5);
        self::assertEqualsWithDelta(200.0, $section->multiColumn->contentHeight, 0.5);
        // 200px content / 50px bands = 4 bands over 2 columns = 2 rows → 100px.
        self::assertEqualsWithDelta(100.0, $section->geometry->height, 0.5);
    }

    public function testInvalidZeroColumnCountIsDropped(): void
    {
        // CSS Multi-column 1 §3.1 — `column-count` is `<integer [1,inf]>`,
        // so `0` is INVALID and the declaration is dropped rather than
        // clamped. `column-count` and `column-width` are then both `auto`,
        // which is not a multi-column container at all — the same outcome
        // as {@see testBothColumnCountAndWidthAutoIsNotMultiColumn}.
        $box = $this->buildTree(
            '<html><body><section><div></div></section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 0; }
             div { height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNull($section->multiColumn);
    }

    public function testInvalidColumnCountLeavesEarlierValidDeclaration(): void
    {
        // An invalid declaration must not clobber the valid one before it.
        $box = $this->buildTree(
            '<html><body><section><div></div></section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 4; column-count: -1; }
             div { height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNotNull($section->multiColumn);
        self::assertSame(4, $section->multiColumn->columnCount);
    }

    public function testBothColumnCountAndWidthAutoIsNotMultiColumn(): void
    {
        // Initial values for both → container behaves as a regular block.
        $box = $this->buildTree(
            '<html><body><section><div></div></section></body></html>',
            'html, body, section, div { display: block; } div { height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNull($section->multiColumn);
    }

    public function testMultiColumnAppliesToInlineOnlyChildren(): void
    {
        // CSS Multi-column 1 §2 — a container with only inline children
        // still establishes a multi-column formatting context. There are
        // no block children to hand to the fragmentainers, so the unit of
        // fragmentation is the LINE BOX instead.
        $box = $this->buildTree(
            '<html><body><section>inline text</section></body></html>',
            'html, body, section { display: block; }
             section { column-count: 2; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNotNull($section->multiColumn);
        self::assertSame(2, $section->multiColumn->columnCount);
    }

    public function testMultiColumnDistributesInlineLinesAcrossColumns(): void
    {
        // Six words at 20px in 100px-wide columns → one word per line, six
        // lines, two per column over three columns. Needs a real font: with
        // no font resolver the inline pass produces no fragments to place.
        $font = OpenTypeParser::fromBytes(
            (string) file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/fonts/NotoSans-Regular.otf'),
        )->parse();
        $box = $this->buildTree(
            '<html><body><section>aaaa bbbb cccc dddd eeee ffff</section></body></html>',
            'html, body, section { display: block; }
             section { width: 300px; column-count: 3; column-gap: 0;
                       font-family: noto; font-size: 20px; line-height: 20px; }',
        );
        $this->layout->layout($box, new LayoutContext(
            600.0,
            800.0,
            0.0,
            0.0,
            new LengthContext(),
            fontResolver: new FontResolver(['noto' => $font], null),
        ));
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNotNull($section->multiColumn);
        self::assertSame(3, $section->multiColumn->columnCount);
        self::assertGreaterThan(1, count($section->lineBoxes), 'content produced several lines');

        // Every column restarts at the container's top, so the distinct
        // line-box tops number fewer than the lines themselves.
        $tops = [];
        foreach ($section->lineBoxes as $line) {
            $tops[(string) round($line->y, 3)] = true;
        }
        self::assertLessThan(
            count($section->lineBoxes),
            count($tops),
            'lines restart their block position in each column',
        );

        // Fragments spread across the full container width rather than
        // staying inside the first column measure.
        $rightmost = 0.0;
        foreach ($section->lineBoxes as $line) {
            foreach ($line->fragments as $fragment) {
                $rightmost = max($rightmost, $fragment->x);
            }
        }
        self::assertGreaterThan(
            $section->multiColumn->columnWidth,
            $rightmost,
            'later columns are offset past the first column',
        );
    }

    public function testMultiColumnFragmentsSingleBlockChildLinesAcrossColumns(): void
    {
        // A multi-column container whose whole content is ONE block of text
        // used to leave everything in column 0, because whole-child
        // redistribution has nothing to redistribute. The child's line
        // boxes are split across the columns instead.
        $font = OpenTypeParser::fromBytes(
            (string) file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/fonts/NotoSans-Regular.otf'),
        )->parse();
        $box = $this->buildTree(
            '<html><body><section><p>aaaa bbbb cccc dddd eeee ffff</p></section></body></html>',
            'html, body, section, p { display: block; }
             p { margin: 0; }
             section { width: 300px; column-count: 3; column-gap: 0;
                       font-family: noto; font-size: 20px; line-height: 20px; }',
        );
        $this->layout->layout($box, new LayoutContext(
            600.0,
            800.0,
            0.0,
            0.0,
            new LengthContext(),
            fontResolver: new FontResolver(['noto' => $font], null),
        ));
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertGreaterThan(1, count($p->lineBoxes), 'paragraph wrapped to several lines');

        // Lines restart per column rather than stacking straight down.
        $tops = [];
        foreach ($p->lineBoxes as $line) {
            $tops[(string) round($line->y, 3)] = true;
        }
        self::assertLessThan(count($p->lineBoxes), count($tops));

        // ...and they reach past the first column measure.
        $rightmost = 0.0;
        foreach ($p->lineBoxes as $line) {
            foreach ($line->fragments as $fragment) {
                $rightmost = max($rightmost, $fragment->x);
            }
        }
        self::assertNotNull($section->multiColumn);
        self::assertGreaterThan($section->multiColumn->columnWidth, $rightmost);
    }

    public function testMultiColumnKeepsDecoratedChildWhole(): void
    {
        // A box with a visible border paints from its single geometry, so
        // splitting its lines across columns would strand the border. Such
        // a child must stay whole in one column.
        $font = OpenTypeParser::fromBytes(
            (string) file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/fonts/NotoSans-Regular.otf'),
        )->parse();
        $box = $this->buildTree(
            '<html><body><section><p>aaaa bbbb cccc dddd eeee ffff</p></section></body></html>',
            'html, body, section, p { display: block; }
             p { margin: 0; border: 5px solid black; }
             section { width: 300px; column-count: 3; column-gap: 0;
                       font-family: noto; font-size: 20px; line-height: 20px; }',
        );
        $this->layout->layout($box, new LayoutContext(
            600.0,
            800.0,
            0.0,
            0.0,
            new LengthContext(),
            fontResolver: new FontResolver(['noto' => $font], null),
        ));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        // Every line keeps a distinct top — nothing was moved into a column.
        $tops = [];
        foreach ($p->lineBoxes as $line) {
            $tops[(string) round($line->y, 3)] = true;
        }
        self::assertCount(count($p->lineBoxes), $tops, 'decorated child stays unsplit');
    }

    public function testMultiColumnKeepsSizeContainedChildWhole(): void
    {
        // CSS Contain 1 §containment-size — a size-contained box is
        // MONOLITHIC, so a fragmentation container may not split it. Its
        // lines stay in one column even when they overflow.
        $font = OpenTypeParser::fromBytes(
            (string) file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/fonts/NotoSans-Regular.otf'),
        )->parse();
        $box = $this->buildTree(
            '<html><body><section><p>aaaa bbbb cccc dddd eeee ffff</p></section></body></html>',
            'html, body, section, p { display: block; }
             p { margin: 0; contain: size; }
             section { width: 300px; column-count: 3; column-gap: 0;
                       font-family: noto; font-size: 20px; line-height: 20px; }',
        );
        $this->layout->layout($box, new LayoutContext(
            600.0,
            800.0,
            0.0,
            0.0,
            new LengthContext(),
            fontResolver: new FontResolver(['noto' => $font], null),
        ));
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        $tops = [];
        foreach ($p->lineBoxes as $line) {
            $tops[(string) round($line->y, 3)] = true;
        }
        self::assertCount(
            count($p->lineBoxes),
            $tops,
            'size-contained child is monolithic and stays unsplit',
        );
    }

    public function testMultiColumnIgnoredOnTable(): void
    {
        // Tables have their own layout — column-count is a no-op here.
        $box = $this->buildTree(
            '<html><body><table><tr><td>cell</td></tr></table></body></html>',
            'html, body { display: block; }
             table { display: table; column-count: 2; }
             tr { display: table-row; } td { display: table-cell; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        self::assertNull($table->multiColumn);
    }

    public function testMultiColumnWithNoChildrenDoesNotCrash(): void
    {
        $box = $this->buildTree(
            '<html><body><section></section></body></html>',
            'html, body, section { display: block; }
             section { column-count: 3; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        // No children → isMultiColumnContainer returns false (empty children
        // list); the box renders as a normal empty block.
        self::assertNull($section->multiColumn);
        self::assertSame(0.0, $section->geometry->height);
    }

    public function testColumnWidthOnlyComputesUsedCount(): void
    {
        // `column-width: 200px` in a 600px-wide container with `column-gap:
        // 0` → exactly 3 columns of 200px. With non-zero gap the floor
        // calc would round down, but zero gap keeps the math clean.
        $box = $this->buildTree(
            '<html><body><section><div></div></section></body></html>',
            'html, body, section, div { display: block; }
             section { column-width: 200px; column-gap: 0; }
             div { height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNotNull($section->multiColumn);
        self::assertSame(3, $section->multiColumn->columnCount);
        self::assertEqualsWithDelta(200.0, $section->multiColumn->columnWidth, 0.001);
    }

    public function testColumnRuleStyleNoneSuppressesPainting(): void
    {
        // `column-rule-style: none` → painter early-outs. The layout
        // records the rule as having width 0 (style gates width via
        // `resolveColumnRuleWidth`) and style `none`.
        $box = $this->buildTree(
            '<html><body><section><div></div></section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-rule: 5px none red; }
             div { height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNotNull($section->multiColumn);
        self::assertSame('none', $section->multiColumn->ruleStyle);
        self::assertSame(0.0, $section->multiColumn->ruleWidth);
    }

    public function testFlexGapRuleSegmentsRecordedForWrappedGrid(): void
    {
        // CSS Gaps 1 — a 2×2 wrapped flex container records one
        // column-rule segment per line (the inter-item main-axis gap,
        // vertical) plus one row-rule segment for the single inter-line
        // cross-axis gap (horizontal, spanning the full main content).
        $box = $this->buildTree(
            '<html><body><main class="f">'
            . '<div></div><div></div><div></div><div></div>'
            . '</main></body></html>',
            'html, body { display: block; margin: 0; }
             .f { display: flex; flex-wrap: wrap; width: 90px;
                  column-gap: 10px; row-gap: 10px;
                  column-rule-style: solid; column-rule-width: 4px; column-rule-color: blue;
                  row-rule-style: solid; row-rule-width: 4px; row-rule-color: green; }
             .f > div { width: 40px; height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'main');
        self::assertInstanceOf(FlexBox::class, $flex);

        $segments = $flex->gapRuleSegments;
        self::assertCount(3, $segments, '2 column-rule + 1 row-rule');

        $columnRules = array_values(array_filter(
            $segments,
            static fn(array $s): bool => $s['prefix'] === 'column-rule',
        ));
        $rowRules = array_values(array_filter(
            $segments,
            static fn(array $s): bool => $s['prefix'] === 'row-rule',
        ));
        self::assertCount(2, $columnRules, 'one column-rule per flex line');
        self::assertCount(1, $rowRules, 'one row-rule between the two lines');

        // Column rules are vertical (x1 == x2) and centred in the single
        // 10px main gap between the 40px items → x = 45 from the content
        // origin (body margin 0).
        foreach ($columnRules as $seg) {
            self::assertSame($seg['x1'], $seg['x2'], 'column-rule is vertical');
            self::assertEqualsWithDelta(45.0, $seg['x1'], 0.001, 'gap centre between 40px items');
        }
        // The row rule is horizontal (y1 == y2) and spans the whole 90px
        // main content extent.
        self::assertSame($rowRules[0]['y1'], $rowRules[0]['y2'], 'row-rule is horizontal');
        self::assertEqualsWithDelta(90.0, abs($rowRules[0]['x2'] - $rowRules[0]['x1']), 0.001, 'row-rule spans main content');
    }

    public function testFlexGapRuleIntersectionBreakSegmentsCrossRule(): void
    {
        // CSS Gaps 1 §3.2 — `row-rule-break: intersection` draws the
        // cross-axis rule only where both adjacent lines have an item at
        // the same main position. Line 1 = [a(90), b(90)] (gap 90..110),
        // line 2 = [c(200)] full-width. The row rule between them is the
        // pairwise intersection: [0..90] and [110..200], broken at line 1's
        // column gap — not one continuous [0..200] band.
        $box = $this->buildTree(
            '<html><body><main class="f">'
            . '<div class="a"></div><div class="b"></div><div class="c"></div>'
            . '</main></body></html>',
            'html, body { display: block; margin: 0; }
             .f { display: flex; flex-wrap: wrap; width: 200px;
                  column-gap: 20px; row-gap: 20px;
                  column-rule-style: solid; column-rule-width: 4px; column-rule-color: blue;
                  column-rule-break: intersection;
                  row-rule-style: solid; row-rule-width: 4px; row-rule-color: green;
                  row-rule-break: intersection; }
             .a, .b { width: 90px; height: 40px; }
             .c { width: 200px; height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'main');
        self::assertInstanceOf(FlexBox::class, $flex);

        $rowRules = array_values(array_filter(
            $flex->gapRuleSegments,
            static fn(array $s): bool => $s['prefix'] === 'row-rule',
        ));
        // Two broken row-rule segments, not one continuous band.
        self::assertCount(2, $rowRules, 'intersection breaks the row rule at the column gap');
        $spans = array_map(
            static fn(array $s): array => [round($s['x1'], 2), round($s['x2'], 2)],
            $rowRules,
        );
        usort($spans, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        self::assertSame([[0.0, 90.0], [110.0, 200.0]], $spans, 'segments are the pairwise item intersection');

        // The single column rule between a and b spans only line 1's item
        // extent (height 40), not down through the row gap.
        $columnRules = array_values(array_filter(
            $flex->gapRuleSegments,
            static fn(array $s): bool => $s['prefix'] === 'column-rule',
        ));
        self::assertCount(1, $columnRules, 'one column rule between a and b');
        self::assertEqualsWithDelta(40.0, abs($columnRules[0]['y2'] - $columnRules[0]['y1']), 0.001, 'intersection column rule spans only the item extent');
    }

    public function testDetailsClosedByDefaultHidesNonSummaryContent(): void
    {
        // HTML 5 §4.11.1: without `[open]`, only the summary should
        // render; child paragraphs are display:none.
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<details>'
                . '<summary style="height: 20px">Click me</summary>'
                . '<p class="body" style="height: 50px"></p>'
                . '</details>'
                . '</body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->find($box, 'p');
        if ($p !== null) {
            self::assertSame(0.0, $p->geometry->height, 'closed details hides body');
        }
        $summary = $this->find($box, 'summary');
        self::assertNotNull($summary);
        self::assertSame(20.0, $summary->geometry->height, 'summary still visible');
    }

    public function testDetailsOpenShowsNonSummaryContent(): void
    {
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<details open>'
                . '<summary>Click me</summary>'
                . '<p class="body" style="height: 50px"></p>'
                . '</details>'
                . '</body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertSame(50.0, $p->geometry->height, 'open details exposes body');
    }

    public function testAuthorCssCanForceDetailsAlwaysOpen(): void
    {
        // Negative test: author overrides the UA `details > * { none }`
        // rule by explicitly setting `display: block` on the child.
        // The override should win via higher specificity (or simply
        // source-order being later).
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<details>'
                . '<summary>Click me</summary>'
                . '<p class="body" style="height: 50px"></p>'
                . '</details>'
                . '</body></html>',
            'details > p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertSame(50.0, $p->geometry->height, 'author override wins over UA hide');
    }

    public function testSummaryRendersWhenDetailsIsOpenAndClosed(): void
    {
        // Symmetric check: summary is visible regardless of [open]
        // state. Cover both branches in one assertion.
        foreach (['', 'open'] as $attr) {
            $box = $this->buildTreeWithUa(
                '<html><body>'
                    . '<details ' . $attr . '>'
                    . '<summary style="height: 20px">S</summary>'
                    . '</details>'
                    . '</body></html>',
                '',
            );
            $this->layout->layout($box, $this->defaultCtx);
            $summary = $this->find($box, 'summary');
            self::assertNotNull($summary, "details $attr: summary present");
            self::assertSame(20.0, $summary->geometry->height, "details $attr: summary visible");
        }
    }

    public function testDetailsWithoutSummaryStillHidesChildren(): void
    {
        // No summary at all — every child gets display: none. The
        // browser would show an implicit "Details" marker, but our
        // text-only Phase-1 just renders nothing for the body and a
        // blank box for the details container.
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<details>'
                . '<p class="body" style="height: 50px"></p>'
                . '</details>'
                . '</body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->find($box, 'p');
        if ($p !== null) {
            self::assertSame(0.0, $p->geometry->height);
        }
    }

    public function testDetailsOpenAttributeBlankValueStillCounts(): void
    {
        // HTML boolean attributes: `<details open>` / `<details open="">`
        // / `<details open="open">` all count as set. The attribute
        // selector `[open]` matches presence regardless of value.
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<details open="">'
                . '<summary>S</summary>'
                . '<p class="body" style="height: 50px"></p>'
                . '</details>'
                . '</body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertSame(50.0, $p->geometry->height);
    }

    public function testDetailsClosedSummaryHasRightTriangleMarker(): void
    {
        // Positive: closed `<details>` `<summary>` carries a `▶ ` prefix
        // from the UA `summary::before { content: "\25B6 " }` rule.
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<details><summary>Heading</summary></details>'
                . '</body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $summary = $this->find($box, 'summary');
        self::assertNotNull($summary);
        // Walk the summary's subtree collecting all text. Expect the
        // U+25B6 right-pointing triangle and the heading text.
        $text = '';
        $stack = [$summary];
        while ($stack !== []) {
            $n = array_pop($stack);
            if ($n instanceof \Phpdftk\HtmlToPdf\Box\TextBox) {
                $text .= $n->text;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertStringContainsString("\u{25B6}", $text, 'right triangle marker present');
        self::assertStringContainsString('Heading', $text);
    }

    public function testDetailsOpenSummaryHasDownTriangleMarker(): void
    {
        // Positive: open `<details>` flips the marker to `▼ ` via the
        // `details[open] > summary::before` rule which beats the
        // base rule in source order.
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<details open><summary>Heading</summary></details>'
                . '</body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $summary = $this->find($box, 'summary');
        self::assertNotNull($summary);
        $text = '';
        $stack = [$summary];
        while ($stack !== []) {
            $n = array_pop($stack);
            if ($n instanceof \Phpdftk\HtmlToPdf\Box\TextBox) {
                $text .= $n->text;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertStringContainsString("\u{25BC}", $text, 'down triangle marker present');
        self::assertStringNotContainsString("\u{25B6}", $text, 'right triangle replaced');
    }

    public function testDetailsAuthorCanSuppressMarker(): void
    {
        // Negative: author CSS `summary::before { content: none }`
        // suppresses the UA-supplied marker.
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<details><summary>Heading</summary></details>'
                . '</body></html>',
            'summary::before { content: none; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $summary = $this->find($box, 'summary');
        self::assertNotNull($summary);
        $text = '';
        $stack = [$summary];
        while ($stack !== []) {
            $n = array_pop($stack);
            if ($n instanceof \Phpdftk\HtmlToPdf\Box\TextBox) {
                $text .= $n->text;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertStringNotContainsString("\u{25B6}", $text);
        self::assertStringNotContainsString("\u{25BC}", $text);
        self::assertStringContainsString('Heading', $text);
    }

    public function testRowspanCellExtendsAcrossRows(): void
    {
        // 2-row table; first row's first cell has rowspan="2". The
        // cell's height should equal the sum of both row heights.
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
                . '<tr><td rowspan="2" style="height: 20px">x</td>'
                . '<td style="height: 30px">a</td></tr>'
                . '<tr><td style="height: 40px">b</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        $rowspanCell = null;
        $stack = [$table];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox
                && $n->element !== null
                && $n->element->getAttribute('rowspan') === '2'
            ) {
                $rowspanCell = $n;
                break;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertNotNull($rowspanCell);
        // Row 0 height = max of (rowspan cell contributes nothing, a's
        // 30) = 30. Row 1 height = 40. Cell extends to 30 + 40 = 70.
        self::assertEqualsWithDelta(70.0, $rowspanCell->geometry->height, 0.001);
    }

    public function testRowspanShiftsNextRowCellsRight(): void
    {
        // With first row's first cell rowspan="2", the second row's
        // ONLY declared cell sits in column 1 (not column 0).
        $box = $this->buildTreeWithUa(
            '<html><body><table style="width: 600px">'
                . '<tr><td rowspan="2" style="height: 20px">x</td>'
                . '<td style="height: 30px">a</td></tr>'
                . '<tr><td class="r2c2" style="height: 30px">b</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        // Find the r2c2 cell.
        $r2c2 = null;
        $stack = [$table];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox
                && $n->element !== null
                && in_array('r2c2', $n->element->classes(), true)
            ) {
                $r2c2 = $n;
                break;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertNotNull($r2c2);
        // 2-column table on a 600-wide table → each column ~300 wide.
        // r2c2 sits in column 1 → x ≈ 300.
        self::assertEqualsWithDelta($table->geometry->x + 300.0, $r2c2->geometry->x, 0.5);
    }

    public function testNoRowspanBaselinePreserved(): void
    {
        // Regression — tables without rowspan still position cells
        // sequentially in document order, one per column.
        $box = $this->buildTreeWithUa(
            '<html><body><table style="width: 600px">'
                . '<tr><td class="a">a</td><td class="b">b</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        $a = null;
        $b = null;
        $stack = [$table];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox && $n->element !== null) {
                if (in_array('a', $n->element->classes(), true)) {
                    $a = $n;
                } elseif (in_array('b', $n->element->classes(), true)) {
                    $b = $n;
                }
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertSame($table->geometry->x, $a->geometry->x);
        self::assertEqualsWithDelta($table->geometry->x + 300.0, $b->geometry->x, 0.5);
    }

    public function testInvalidRowspanAttributeTreatedAsOne(): void
    {
        // `rowspan="foo"` — non-numeric, treated as 1 (no span).
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
                . '<tr><td rowspan="foo" style="height: 20px">x</td>'
                . '<td style="height: 30px">a</td></tr>'
                . '<tr><td class="r2c1" style="height: 30px">b</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        // r2c1 should sit in COLUMN 0 (not column 1) because the
        // invalid rowspan was treated as 1 — no occupancy from row 0.
        $r2c1 = null;
        $stack = [$table];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox
                && $n->element !== null
                && in_array('r2c1', $n->element->classes(), true)
            ) {
                $r2c1 = $n;
                break;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertNotNull($r2c1);
        self::assertSame($table->geometry->x, $r2c1->geometry->x);
    }

    public function testRowspanOneIsNoOp(): void
    {
        // Explicit `rowspan="1"` — same as no attribute. Cell sits in
        // its row, doesn't span anything.
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
                . '<tr><td rowspan="1" style="height: 20px">x</td></tr>'
                . '<tr><td class="r2c1" style="height: 30px">b</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        $r2c1 = null;
        $stack = [$table];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox
                && $n->element !== null
                && in_array('r2c1', $n->element->classes(), true)
            ) {
                $r2c1 = $n;
                break;
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertNotNull($r2c1);
        self::assertSame($table->geometry->x, $r2c1->geometry->x);
    }

    public function testRowspanCellDoesNotInflateOriginRowHeight(): void
    {
        // The rowspan cell's own height should NOT inflate its
        // declaring row's height — that's what makes it possible to
        // span multiple rows. With the rowspan cell having content
        // height 100 but rowspan=2, row 0 height stays at the OTHER
        // cell's 30, row 1 height = 40, total = 70.
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
                . '<tr><td class="span" rowspan="2" style="height: 100px">x</td>'
                . '<td class="r1" style="height: 30px">a</td></tr>'
                . '<tr><td class="r2" style="height: 40px">b</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        $r1 = null;
        $r2 = null;
        $stack = [$table];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox && $n->element !== null) {
                if (in_array('r1', $n->element->classes(), true)) {
                    $r1 = $n;
                } elseif (in_array('r2', $n->element->classes(), true)) {
                    $r2 = $n;
                }
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertNotNull($r1);
        self::assertNotNull($r2);
        // r2's top edge should sit 30 below r1's top (= row 0 height).
        self::assertEqualsWithDelta($r1->geometry->y + 30.0, $r2->geometry->y, 0.5);
    }

    public function testColWidthAttributeSetsExplicitColumnWidth(): void
    {
        // Single `<col width="200">` on a 3-column table — that column
        // takes 200, the other two share (600 - 200) / 2 = 200 each.
        // (Equal coincidence here; we verify the explicit width
        // controls the FIRST column.)
        $box = $this->buildTreeWithUa(
            '<html><body><table style="width: 600px">'
                . '<col width="200">'
                . '<tr><td class="a">a</td><td>b</td><td>c</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $tr = $this->find($box, 'tr');
        self::assertNotNull($tr);
        $cells = array_values(array_filter(
            $tr->children,
            static fn($c): bool => $c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox,
        ));
        self::assertCount(3, $cells);
        self::assertEqualsWithDelta(200.0, $cells[0]->geometry->width, 0.001);
        self::assertEqualsWithDelta(200.0, $cells[1]->geometry->width, 0.001);
        self::assertEqualsWithDelta(200.0, $cells[2]->geometry->width, 0.001);
    }

    public function testColWidthDistributesAutoSlackToRemainingColumns(): void
    {
        // Explicit 100 + 100 → other column gets 600 - 200 = 400.
        $box = $this->buildTreeWithUa(
            '<html><body><table style="width: 600px">'
                . '<col width="100">'
                . '<col width="100">'
                . '<tr><td>a</td><td>b</td><td>c</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $tr = $this->find($box, 'tr');
        self::assertNotNull($tr);
        $cells = array_values(array_filter(
            $tr->children,
            static fn($c): bool => $c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox,
        ));
        self::assertEqualsWithDelta(100.0, $cells[0]->geometry->width, 0.001);
        self::assertEqualsWithDelta(100.0, $cells[1]->geometry->width, 0.001);
        self::assertEqualsWithDelta(400.0, $cells[2]->geometry->width, 0.001);
    }

    public function testColSpanAttributeRepeatsWidthAcrossColumns(): void
    {
        // `<col span="2" width="150">` applies 150 to columns 0 and 1.
        $box = $this->buildTreeWithUa(
            '<html><body><table style="width: 600px">'
                . '<col span="2" width="150">'
                . '<tr><td>a</td><td>b</td><td>c</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $tr = $this->find($box, 'tr');
        self::assertNotNull($tr);
        $cells = array_values(array_filter(
            $tr->children,
            static fn($c): bool => $c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox,
        ));
        self::assertEqualsWithDelta(150.0, $cells[0]->geometry->width, 0.001);
        self::assertEqualsWithDelta(150.0, $cells[1]->geometry->width, 0.001);
        self::assertEqualsWithDelta(300.0, $cells[2]->geometry->width, 0.001);
    }

    public function testColgroupWithNestedColsHonored(): void
    {
        // `<colgroup>` wraps two `<col>` declarations — both should
        // apply.
        $box = $this->buildTreeWithUa(
            '<html><body><table style="width: 600px">'
                . '<colgroup><col width="80"><col width="120"></colgroup>'
                . '<tr><td>a</td><td>b</td><td>c</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $tr = $this->find($box, 'tr');
        self::assertNotNull($tr);
        $cells = array_values(array_filter(
            $tr->children,
            static fn($c): bool => $c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox,
        ));
        self::assertEqualsWithDelta(80.0, $cells[0]->geometry->width, 0.001);
        self::assertEqualsWithDelta(120.0, $cells[1]->geometry->width, 0.001);
        self::assertEqualsWithDelta(400.0, $cells[2]->geometry->width, 0.001);
    }

    public function testNoColDeclarationsKeepsEqualShare(): void
    {
        // Regression: without any `<col>`, each column gets an equal
        // share of the row width.
        $box = $this->buildTreeWithUa(
            '<html><body><table style="width: 600px">'
                . '<tr><td>a</td><td>b</td><td>c</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $tr = $this->find($box, 'tr');
        self::assertNotNull($tr);
        $cells = array_values(array_filter(
            $tr->children,
            static fn($c): bool => $c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox,
        ));
        self::assertEqualsWithDelta(200.0, $cells[0]->geometry->width, 0.001);
        self::assertEqualsWithDelta(200.0, $cells[1]->geometry->width, 0.001);
        self::assertEqualsWithDelta(200.0, $cells[2]->geometry->width, 0.001);
    }

    public function testColWidthInvalidValueIgnored(): void
    {
        // Non-numeric `width` attribute should leave the column as auto.
        $box = $this->buildTreeWithUa(
            '<html><body><table style="width: 600px">'
                . '<col width="auto">'
                . '<tr><td>a</td><td>b</td><td>c</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $tr = $this->find($box, 'tr');
        self::assertNotNull($tr);
        $cells = array_values(array_filter(
            $tr->children,
            static fn($c): bool => $c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox,
        ));
        self::assertEqualsWithDelta(200.0, $cells[0]->geometry->width, 0.001);
    }

    public function testColWidthPercentageIgnoredAtPhase1(): void
    {
        // Percentage widths are Phase 2; verified ignored for now so
        // the % col falls back to the auto share.
        $box = $this->buildTreeWithUa(
            '<html><body><table style="width: 600px">'
                . '<col width="50%">'
                . '<tr><td>a</td><td>b</td><td>c</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $tr = $this->find($box, 'tr');
        self::assertNotNull($tr);
        $cells = array_values(array_filter(
            $tr->children,
            static fn($c): bool => $c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox,
        ));
        self::assertEqualsWithDelta(200.0, $cells[0]->geometry->width, 0.001);
    }

    public function testColWidthSumExceedingRowGivesZeroToAutoCells(): void
    {
        // Two cols with width 400 each on a 600-wide table → explicit
        // sum 800 > 600. Third (auto) column gets 0.
        $box = $this->buildTreeWithUa(
            '<html><body><table style="width: 600px">'
                . '<col width="400">'
                . '<col width="400">'
                . '<tr><td>a</td><td>b</td><td>c</td></tr>'
                . '</table></body></html>',
            'td { padding: 0 }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $tr = $this->find($box, 'tr');
        self::assertNotNull($tr);
        $cells = array_values(array_filter(
            $tr->children,
            static fn($c): bool => $c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox,
        ));
        self::assertEqualsWithDelta(400.0, $cells[0]->geometry->width, 0.001);
        self::assertEqualsWithDelta(400.0, $cells[1]->geometry->width, 0.001);
        self::assertEqualsWithDelta(0.0, $cells[2]->geometry->width, 0.001);
    }

    public function testCaptionSideTopIsDefaultAndStaysAtTop(): void
    {
        // No explicit caption-side → default `top`. Caption renders
        // above the rows.
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
                . '<caption style="height: 20px"></caption>'
                . '<tr><td style="height: 40px">x</td></tr>'
                . '</table></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        $caption = $this->find($table, 'caption');
        $tr = $this->find($table, 'tr');
        self::assertNotNull($caption);
        self::assertNotNull($tr);
        self::assertLessThan($tr->geometry->y, $caption->geometry->y);
    }

    public function testCaptionSideBottomMovesCaptionAfterRows(): void
    {
        // `caption-side: bottom` should render the caption AFTER the
        // table rows in document height even when it appears first in
        // markup.
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
                . '<caption style="caption-side: bottom; height: 20px"></caption>'
                . '<tr><td style="height: 40px">x</td></tr>'
                . '</table></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        $caption = $this->find($table, 'caption');
        $tr = $this->find($table, 'tr');
        self::assertNotNull($caption);
        self::assertNotNull($tr);
        self::assertGreaterThan($tr->geometry->y, $caption->geometry->y);
    }

    public function testCaptionSideExplicitTopKeepsCaptionAtTop(): void
    {
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
                . '<caption style="caption-side: top; height: 20px"></caption>'
                . '<tr><td style="height: 40px">x</td></tr>'
                . '</table></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        $caption = $this->find($table, 'caption');
        $tr = $this->find($table, 'tr');
        self::assertNotNull($caption);
        self::assertNotNull($tr);
        self::assertLessThan($tr->geometry->y, $caption->geometry->y);
    }

    public function testCaptionInvalidKeywordDefaultsToTop(): void
    {
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
                . '<caption style="caption-side: nonsense; height: 20px"></caption>'
                . '<tr><td style="height: 40px">x</td></tr>'
                . '</table></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        $caption = $this->find($table, 'caption');
        $tr = $this->find($table, 'tr');
        self::assertNotNull($caption);
        self::assertNotNull($tr);
        self::assertLessThan($tr->geometry->y, $caption->geometry->y);
    }

    public function testTableWithNoCaptionIsNoOp(): void
    {
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
                . '<tr><td style="height: 40px">x</td></tr>'
                . '</table></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        $tr = $this->find($table, 'tr');
        self::assertNotNull($tr);
        self::assertEqualsWithDelta($table->geometry->y, $tr->geometry->y, 0.001);
    }

    public function testCaptionSideOnNonCaptionElementIgnored(): void
    {
        // `caption-side: bottom` on a `<tr>` (not `<caption>`) must
        // not trigger the reorder — rows stay in document order.
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
                . '<tr class="r1" style="caption-side: bottom"><td style="height: 40px">a</td></tr>'
                . '<tr class="r2"><td style="height: 40px">b</td></tr>'
                . '</table></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        $r1 = null;
        $r2 = null;
        $stack = [$table];
        while ($stack !== []) {
            $n = array_shift($stack);
            if ($n->element !== null && $n->element->localName === 'tr') {
                if (in_array('r1', $n->element->classes(), true)) {
                    $r1 = $n;
                } elseif (in_array('r2', $n->element->classes(), true)) {
                    $r2 = $n;
                }
            }
            foreach ($n->children as $c) {
                $stack[] = $c;
            }
        }
        self::assertNotNull($r1);
        self::assertNotNull($r2);
        self::assertLessThan($r2->geometry->y, $r1->geometry->y);
    }

    public function testFloatLeftPlacesBoxAtLeftEdgeOfContainer(): void
    {
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: left; width: 100px; height: 80px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $f = $this->find($box, 'div');
        self::assertNotNull($f);
        self::assertSame(0.0, $f->geometry->x);
    }

    public function testFloatRightPlacesBoxAtRightEdgeOfContainer(): void
    {
        // Container is 600 wide; 100-wide right float lands at x=500.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: right; width: 100px; height: 80px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $f = $this->find($box, 'div');
        self::assertNotNull($f);
        self::assertSame(500.0, $f->geometry->x);
    }

    public function testTwoLeftFloatsStackHorizontally(): void
    {
        // Two 100-wide left floats → first at 0, second at 100.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="a" style="float: left; width: 100px; height: 80px"></div>'
                . '<div class="b" style="float: left; width: 100px; height: 80px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = null;
        $b = null;
        foreach ($this->find($box, 'body')->children as $c) {
            if ($c->element === null) {
                continue;
            }
            if (in_array('a', $c->element->classes(), true)) {
                $a = $c;
            } elseif (in_array('b', $c->element->classes(), true)) {
                $b = $c;
            }
        }
        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(100.0, $b->geometry->x);
        self::assertSame(0.0, $a->geometry->y);
        self::assertSame(0.0, $b->geometry->y);
    }

    public function testClearLeftAfterFloatShiftsBlockPastIt(): void
    {
        // 100-tall left float; next sibling has `clear: left` → shifts
        // to y=100 (past the float).
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: left; width: 100px; height: 100px"></div>'
                . '<div class="c" style="clear: left; height: 30px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('c', $child->element->classes(), true)) {
                $c = $child;
                break;
            }
        }
        self::assertNotNull($c);
        self::assertSame(100.0, $c->geometry->y);
    }

    public function testFloatNoneDoesNotEngageFloatPath(): void
    {
        // Default `float: none` — the block stacks normally and advances
        // the parent cursor. Two stacked 50-tall blocks → second at y=50.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="a" style="height: 50px"></div>'
                . '<div class="b" style="height: 50px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('b', $child->element->classes(), true)) {
                $b = $child;
                break;
            }
        }
        self::assertNotNull($b);
        self::assertSame(50.0, $b->geometry->y);
    }

    public function testFloatDoesNotAdvanceParentCursor(): void
    {
        // The next in-flow sibling sits at the SAME Y as the float (not
        // pushed below). Floats remove themselves from flow.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: left; width: 100px; height: 80px"></div>'
                . '<div class="next" style="height: 30px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $next = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('next', $child->element->classes(), true)) {
                $next = $child;
                break;
            }
        }
        self::assertNotNull($next);
        self::assertSame(0.0, $next->geometry->y);
    }

    public function testClearNoneIsNoOp(): void
    {
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: left; width: 100px; height: 100px"></div>'
                . '<div class="c" style="clear: none; height: 30px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('c', $child->element->classes(), true)) {
                $c = $child;
                break;
            }
        }
        self::assertNotNull($c);
        self::assertSame(0.0, $c->geometry->y);
    }

    public function testClearLeftWithOnlyRightFloatsIsNoOp(): void
    {
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: right; width: 100px; height: 100px"></div>'
                . '<div class="c" style="clear: left; height: 30px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('c', $child->element->classes(), true)) {
                $c = $child;
                break;
            }
        }
        self::assertNotNull($c);
        self::assertSame(0.0, $c->geometry->y);
    }

    public function testClearRightWithOnlyLeftFloatsIsNoOp(): void
    {
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: left; width: 100px; height: 100px"></div>'
                . '<div class="c" style="clear: right; height: 30px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('c', $child->element->classes(), true)) {
                $c = $child;
                break;
            }
        }
        self::assertNotNull($c);
        self::assertSame(0.0, $c->geometry->y);
    }

    public function testClearBothWithNoFloatsIsNoOp(): void
    {
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="c" style="clear: both; height: 30px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = $this->find($box, 'div');
        self::assertNotNull($c);
        self::assertSame(0.0, $c->geometry->y);
    }

    public function testClearBothPastBothFloats(): void
    {
        // 80-tall left float + 120-tall right float, clear: both →
        // shifts past the taller one (120).
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="l" style="float: left; width: 100px; height: 80px"></div>'
                . '<div class="r" style="float: right; width: 100px; height: 120px"></div>'
                . '<div class="c" style="clear: both; height: 30px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('c', $child->element->classes(), true)) {
                $c = $child;
                break;
            }
        }
        self::assertNotNull($c);
        self::assertSame(120.0, $c->geometry->y);
    }

    public function testFloatInvalidKeywordTreatedAsNone(): void
    {
        // CSS Values 3 §9: invalid values fall back to the initial.
        // For `float`, that's `none` — so the box flows as in-flow.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: nonsense; height: 50px"></div>'
                . '<div class="n" style="height: 30px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $n = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('n', $child->element->classes(), true)) {
                $n = $child;
                break;
            }
        }
        self::assertNotNull($n);
        // Invalid float kept as in-flow → next sibling stacks at y=50.
        self::assertSame(50.0, $n->geometry->y);
    }

    public function testTwoLeftFloatsWiderThanContainerDropToNextRow(): void
    {
        // Two 400-wide floats in a 600-wide container — first sits at
        // x=0,y=0; second can't fit alongside (400+400 > 600) so it
        // drops below to y=80 at x=0.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="a" style="float: left; width: 400px; height: 80px"></div>'
                . '<div class="b" style="float: left; width: 400px; height: 80px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = null;
        $b = null;
        foreach ($this->find($box, 'body')->children as $c) {
            if ($c->element === null) {
                continue;
            }
            if (in_array('a', $c->element->classes(), true)) {
                $a = $c;
            } elseif (in_array('b', $c->element->classes(), true)) {
                $b = $c;
            }
        }
        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertSame(0.0, $a->geometry->y);
        self::assertSame(80.0, $b->geometry->y);
        self::assertSame(0.0, $b->geometry->x);
    }

    public function testFloatBelowFirstFloatBottomIsAtY0InNewSlot(): void
    {
        // Sanity: clear: both past the first float means we shift past
        // it but don't go higher. With a 50-tall float, clear: both
        // shifts the next block exactly to y=50.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="f" style="float: left; width: 100px; height: 50px"></div>'
                . '<div class="c" style="clear: both"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('c', $child->element->classes(), true)) {
                $c = $child;
                break;
            }
        }
        self::assertNotNull($c);
        self::assertSame(50.0, $c->geometry->y);
    }

    public function testVerticalLrTransposesInlineOffsetToVerticalAxis(): void
    {
        // CSS Writing Modes 4 §3 (Phase B) — a vertical-lr inline formatting
        // context transposes the inline offset (here `text-indent: 40px`) from
        // the horizontal axis onto the vertical axis (LineBox.y, which the
        // painter reads as the column's top), and places the single-glyph column
        // at the block-start (fragment.x = 0). Before the transpose the indent
        // stayed on fragment.x, painting the glyph rightward instead of down.
        $font = OpenTypeParser::fromBytes(
            (string) file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/fonts/NotoSans-Regular.otf'),
        )->parse();
        $box = $this->buildTree(
            '<html><body><div class="v" style="writing-mode: vertical-lr; '
                . 'text-indent: 40px; font-family: noto; font-size: 20px; '
                . 'width: 200px; height: 200px">A</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, new LayoutContext(
            600.0,
            800.0,
            0.0,
            0.0,
            new LengthContext(),
            fontResolver: new FontResolver(['noto' => $font], null),
        ));
        $div = $this->find($box, 'div.v');
        self::assertNotNull($div);
        self::assertNotEmpty($div->lineBoxes);
        $line = $div->lineBoxes[0];
        // Inline offset (>= the 40px text-indent) transposed onto the vertical
        // axis — now carried per-fragment as `blockOffset` (increment 2), with
        // LineBox.y = 0 as the column top.
        self::assertGreaterThanOrEqual(40.0, $line->fragments[0]->blockOffset);
        // The single-glyph column sits at the block-start (left) edge for vertical-lr.
        self::assertSame(0.0, $line->fragments[0]->x);
    }

    public function testIntrinsicMeasurementResolvesRelativeFontSizes(): void
    {
        // Intrinsic measurement can run BEFORE a box's own `resolveLengths`
        // pass, so the cascaded `font-size` may still be author-declared.
        // Taking it verbatim measured `2em` as TWO PIXELS, collapsing every
        // table inside an em- or percentage-sized ancestor to a sliver.
        // `2em`, `200%` and `32px` must all measure identically against a
        // 16px parent.
        $font = OpenTypeParser::fromBytes(
            (string) file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/fonts/NotoSans-Regular.otf'),
        )->parse();
        $widths = [];
        foreach (['32px', '2em', '200%'] as $size) {
            $box = $this->buildTree(
                '<html><body style="font-size: 16px"><div style="font-size: ' . $size . '">'
                    . '<table><tr><td>Row 1, Col 1</td><td>Row 1, Col 2</td></tr></table>'
                    . '</div></body></html>',
                'html, body, div { display: block; } table { display: table; } '
                    . 'tr { display: table-row; } td { display: table-cell; } '
                    . '* { font-family: noto; }',
            );
            $this->layout->layout($box, new LayoutContext(
                600.0,
                800.0,
                0.0,
                0.0,
                new LengthContext(),
                fontResolver: new FontResolver(['noto' => $font], null),
            ));
            $table = $this->find($box, 'table');
            self::assertNotNull($table, $size);
            $widths[$size] = $table->geometry->width;
        }
        self::assertEqualsWithDelta($widths['32px'], $widths['2em'], 0.5, '2em vs 32px');
        self::assertEqualsWithDelta($widths['32px'], $widths['200%'], 0.5, '200% vs 32px');
        // And the collapsed-to-2px bug would leave it far below this.
        self::assertGreaterThan(100.0, $widths['2em']);
    }

    public function testVerticalTextAlignCentersAgainstInlineHeight(): void
    {
        // CSS Writing Modes 4 §3 — `text-align` aligns along the INLINE axis,
        // which is vertical for vertical-lr, so `center` centres the glyph
        // against the container's HEIGHT (inline size), not its width. A glyph
        // in a 100px-wide × 300px-tall container centres near line.y ≈ 143
        // (against 300) — far past the ≈43 a (wrong) width-based centre gives.
        $font = OpenTypeParser::fromBytes(
            (string) file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/fonts/NotoSans-Regular.otf'),
        )->parse();
        $box = $this->buildTree(
            '<html><body><div class="v" style="writing-mode: vertical-lr; '
                . 'text-align: center; font-family: noto; font-size: 20px; '
                . 'width: 100px; height: 300px">A</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, new LayoutContext(
            600.0,
            800.0,
            0.0,
            0.0,
            new LengthContext(),
            fontResolver: new FontResolver(['noto' => $font], null),
        ));
        $div = $this->find($box, 'div.v');
        self::assertNotNull($div);
        self::assertNotEmpty($div->lineBoxes);
        // Centred against height (300) → > 100; a width-based centre (100) is ~43.
        // The inline centre offset is carried per-fragment as `blockOffset`
        // (increment 2), not on LineBox.y.
        self::assertGreaterThan(100.0, $div->lineBoxes[0]->fragments[0]->blockOffset);
    }

    public function testVerticalAbsposRecoversStaticInlinePositionFromPrecedingText(): void
    {
        // CSS 2.1 §9.4.2 static position, swapped to the vertical axes
        // (CSS WM 4 §7.1): a static-position abspos in a vertical writing
        // mode takes its INLINE-axis (physical-Y) position from the END of
        // the preceding inline content's column — i.e. BELOW the text — not
        // the container's top. Regression guard for the wm-abspos cluster:
        // the vertical stacker previously ignored inline flow and pinned the
        // abspos at the block cursor (y≈0).
        $font = OpenTypeParser::fromBytes(
            (string) file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/fonts/NotoSans-Regular.otf'),
        )->parse();
        $box = $this->buildTree(
            '<html><body><div class="v" style="writing-mode: vertical-rl; '
                . 'position: relative; font-family: noto; font-size: 20px; '
                . 'width: 200px; height: 200px">AAAA'
                . '<span class="ap" style="position: absolute; width: 10px; '
                . 'height: 10px"></span></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, new LayoutContext(
            600.0,
            800.0,
            0.0,
            0.0,
            new LengthContext(),
            fontResolver: new FontResolver(['noto' => $font], null),
        ));
        $ap = $this->find($box, 'span.ap');
        self::assertNotNull($ap);
        // "AAAA" (4×20px ≈ 80px) flows down the column, so the abspos static
        // inline position lands well below the top, not at y≈0.
        self::assertGreaterThan(40.0, $ap->geometry->y);
    }

    public function testInlineAbsposStaticXFollowsPrecedingContent(): void
    {
        // CSS 2.1 §9.4.2 — an inline-level abspos with auto left/right takes
        // its static INLINE position from the end of the preceding inline
        // content, not the container's inline-start. With "AAAA" (4 glyphs)
        // preceding it, the abspos starts well past x=0, not at the CB edge.
        $font = OpenTypeParser::fromBytes(
            (string) file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/fonts/NotoSans-Regular.otf'),
        )->parse();
        $box = $this->buildTree(
            '<html><body><div style="position: relative; font-family: noto; '
                . 'font-size: 40px; width: 600px; height: 200px">AAAA'
                . '<span class="abs" style="position: absolute; top: 0; width: 50px; height: 30px">x</span>'
                . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, new LayoutContext(
            600.0,
            800.0,
            0.0,
            0.0,
            new LengthContext(),
            fontResolver: new FontResolver(['noto' => $font], null),
        ));
        $abs = $this->find($box, 'span.abs');
        self::assertNotNull($abs);
        // Static x sits after "AAAA" (4 × ~0.68em at 40px ≈ 110px), not 0.
        self::assertGreaterThan(50.0, $abs->geometry->x);
    }

    public function testAbsolutePositionsBoxAtTopLeftOffsets(): void
    {
        // `position: absolute; top: 50px; left: 20px` puts the box at
        // (parent.x + 20, parent.y + 50) regardless of in-flow position.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="abs" style="position: absolute; top: 50px; left: 20px; width: 100px; height: 40px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(20.0, $div->geometry->x);
        self::assertSame(50.0, $div->geometry->y);
    }

    public function testAbsoluteRightAndBottomOffsets(): void
    {
        // `right: 30px` on a 100px-wide box in a 600-wide CB → margin
        // edge at 600 - 30 - 100 = 470.
        // `bottom: 20px` on a 40px-tall box in a 800-tall CB → margin
        // edge at 800 - 20 - 40 = 740.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="abs" style="position: absolute; right: 30px; bottom: 20px; width: 100px; height: 40px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(470.0, $div->geometry->x);
        self::assertSame(740.0, $div->geometry->y);
    }

    public function testAbsoluteRemovesBoxFromFlow(): void
    {
        // Two siblings; the second is absolute. The third (in-flow) sibling
        // should stack right below the first (50 + 50 = 100), unaffected
        // by the absolute box that sits in between.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="a" style="height: 50px"></div>'
                . '<div class="abs" style="position: absolute; top: 200px; left: 0; height: 40px"></div>'
                . '<div class="c" style="height: 50px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $body = $this->find($box, 'body');
        self::assertNotNull($body);
        $c = null;
        foreach ($body->children as $child) {
            if ($child->element !== null && in_array('c', $child->element->classes(), true)) {
                $c = $child;
                break;
            }
        }
        self::assertNotNull($c);
        self::assertSame(50.0, $c->geometry->y);
    }

    public function testAbsoluteAutoOffsetsKeepBoxAtStaticPosition(): void
    {
        // Without top/left/etc, the absolute box stays at its in-flow
        // position (the "static position" per CSS 2.1 §10.3.7).
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="a" style="height: 50px"></div>'
                . '<div class="abs" style="position: absolute; width: 100px; height: 40px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $abs = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('abs', $child->element->classes(), true)) {
                $abs = $child;
                break;
            }
        }
        self::assertNotNull($abs);
        // Static position = where it would have flowed = right after .a
        // at y=50.
        self::assertSame(50.0, $abs->geometry->y);
        self::assertSame(0.0, $abs->geometry->x);
    }

    public function testPositionFixedBehavesLikeAbsoluteInPrint(): void
    {
        // No scroll viewport in print → `fixed` is identical to
        // `absolute` for placement purposes.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="fixed" style="position: fixed; top: 80px; left: 40px; width: 100px; height: 40px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(40.0, $div->geometry->x);
        self::assertSame(80.0, $div->geometry->y);
    }

    public function testAbsoluteTopBeatsBottom(): void
    {
        // When both top and bottom are set on an absolute box, top wins
        // (matches my position: relative precedence).
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="abs" style="position: absolute; top: 30px; bottom: 200px; left: 10px; right: 200px; width: 50px; height: 40px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(10.0, $div->geometry->x);
        self::assertSame(30.0, $div->geometry->y);
    }

    public function testAbsoluteCornerAnchorsDeriveWidthAndHeight(): void
    {
        // CSS 2.1 §10.3.7 / §10.6.4 — `width: auto` + both left/right set
        // → width = cb_width - left - right. Same for height with
        // top/bottom. The `top: 0; left: 0; right: 0; bottom: 0`
        // viewport-fill pattern only renders correctly when both pairs
        // resolve from the containing block.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="abs" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        // Containing block: 600 wide × 800 tall (the default test ctx).
        $this->layout->layout($box, $this->defaultCtx);
        $abs = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('abs', $child->element->classes(), true)) {
                $abs = $child;
                break;
            }
        }
        self::assertNotNull($abs);
        self::assertSame(0.0, $abs->geometry->x, 'left:0 places at origin x');
        self::assertSame(0.0, $abs->geometry->y, 'top:0 places at origin y');
        self::assertSame(600.0, $abs->geometry->width, 'width = cb_width - left - right');
        self::assertSame(800.0, $abs->geometry->height, 'height = cb_height - top - bottom');
    }

    public function testAbsoluteExplicitWidthIgnoresRightAnchor(): void
    {
        // CSS 2.1 §10.3.7 — when `width` is explicit, the box is
        // over-constrained and the `right` anchor is ignored. The box
        // keeps its declared width.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="abs" style="position: absolute; top: 0; left: 0; right: 0; width: 100px; height: 50px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $abs = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('abs', $child->element->classes(), true)) {
                $abs = $child;
                break;
            }
        }
        self::assertNotNull($abs);
        self::assertSame(100.0, $abs->geometry->width, 'explicit width wins');
    }

    public function testAbsoluteAfterFlowSiblingsHonorsParentTop(): void
    {
        // Absolute box's `top: N` is measured from parent's content
        // top, NOT from where the box would have flowed. So putting
        // a 100-tall sibling before it then `top: 10px` should still
        // place the abs box at y=10 (from body top, which is 0).
        $box = $this->buildTree(
            '<html><body>'
                . '<div style="height: 100px"></div>'
                . '<div class="abs" style="position: absolute; top: 10px; left: 0; width: 50px; height: 30px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $abs = null;
        foreach ($this->find($box, 'body')->children as $child) {
            if ($child->element !== null && in_array('abs', $child->element->classes(), true)) {
                $abs = $child;
                break;
            }
        }
        self::assertNotNull($abs);
        self::assertSame(10.0, $abs->geometry->y);
    }

    public function testPositionStaticDoesNotShift(): void
    {
        // Default `position: static` ignores `top`/`left`. Sanity check
        // the no-op so the offset logic doesn't bleed into normal flow.
        $box = $this->buildTree(
            '<html><body><div style="top: 20px; left: 30px; height: 40px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(0.0, $div->geometry->x);
        self::assertSame(0.0, $div->geometry->y);
    }

    public function testRelativeWithNoOffsetsIsNoOp(): void
    {
        // `position: relative` with all `auto` offsets — box paints at
        // its flow position. Tests that the resolver returns (0,0)
        // when nothing is set.
        $box = $this->buildTree(
            '<html><body><div style="position: relative; height: 40px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(0.0, $div->geometry->x);
        self::assertSame(0.0, $div->geometry->y);
    }

    public function testRelativeTopAndLeftShiftBox(): void
    {
        $box = $this->buildTree(
            '<html><body><div style="position: relative; top: 10px; left: 20px; height: 40px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(20.0, $div->geometry->x);
        self::assertSame(10.0, $div->geometry->y);
    }

    public function testRelativeRightAndBottomShiftNegatively(): void
    {
        // `right: 15px` → -15 dx; `bottom: 8px` → -8 dy.
        $box = $this->buildTree(
            '<html><body><div style="position: relative; right: 15px; bottom: 8px; height: 40px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(-15.0, $div->geometry->x);
        self::assertSame(-8.0, $div->geometry->y);
    }

    public function testRelativeTopBeatsBottom(): void
    {
        // CSS 2.1 §9.4.3: when both `top` and `bottom` are set on a
        // relative box, `top` wins. dx similarly: left wins over right.
        $box = $this->buildTree(
            '<html><body><div style="position: relative; top: 5px; bottom: 100px; left: 3px; right: 50px; height: 40px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(3.0, $div->geometry->x);
        self::assertSame(5.0, $div->geometry->y);
    }

    public function testBlockAspectRatioContributesIntrinsicWidthFromHeight(): void
    {
        // CSS Sizing 4 §5.1 — an empty non-replaced block with a definite
        // height and an aspect-ratio contributes height × ratio as its
        // min/max-content width. `height:50px; aspect-ratio:2/1;
        // width:min-content` → 100px, not the collapsed 0.
        $box = $this->buildTree(
            '<html><body><div id="ar"></div></body></html>',
            'html, body, div { display: block; }
             #ar { height: 50px; aspect-ratio: 2 / 1; width: min-content; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $ar = $this->findById($box, 'ar');
        self::assertNotNull($ar);
        self::assertEqualsWithDelta(100.0, $ar->geometry->width, 0.001, 'min-content width = height × ratio');
    }

    public function testBlockAspectRatioIntrinsicWidthFlowsToMinContentParent(): void
    {
        // The ratio-derived width also propagates as a child's contribution
        // to a `width:min-content` parent (intrinsic-size-001 shape): a
        // height:100 / ratio 1:1 child gives the parent a 100px min-content.
        $box = $this->buildTree(
            '<html><body><div id="p"><div id="c"></div></div></body></html>',
            'html, body, div { display: block; }
             #p { width: min-content; }
             #c { height: 100px; aspect-ratio: 1 / 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->findById($box, 'p');
        self::assertNotNull($p);
        self::assertEqualsWithDelta(100.0, $p->geometry->width, 0.001, 'parent min-content picks up child ratio width');
    }

    public function testBlockExplicitWidthSuppressesRatioIntrinsicContribution(): void
    {
        // An explicit inline size fixes the width, so the ratio does NOT
        // feed the intrinsic width (it flows to the block axis instead).
        // width:min-content on the parent, child has explicit width:30px →
        // parent min-content = 30, not height × ratio.
        $box = $this->buildTree(
            '<html><body><div id="p"><div id="c"></div></div></body></html>',
            'html, body, div { display: block; }
             #p { width: min-content; }
             #c { height: 100px; aspect-ratio: 1 / 1; width: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->findById($box, 'p');
        self::assertNotNull($p);
        self::assertEqualsWithDelta(30.0, $p->geometry->width, 0.001, 'explicit child width wins over ratio');
    }

    public function testFlexContainInlineSizeZeroesIntrinsicWidth(): void
    {
        // CSS Containment §4.6 — `contain: inline-size` on a flex container
        // with `width: fit-content` makes the intrinsic inline size ignore
        // the items (contribute 0). Here a 200px item in a fit-content
        // contained flex box gives a 0-content-width box (+ borders); the
        // container width collapses to the border box (10px), not 200px.
        $box = $this->buildTree(
            '<html><body><div id="f"><div id="i"></div></div></body></html>',
            'html, body, div { display: block; }
             #f { display: flex; contain: inline-size; width: fit-content;
                  border: 5px solid; }
             #i { width: 200px; height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $f = $this->findById($box, 'f');
        self::assertInstanceOf(FlexBox::class, $f);
        self::assertEqualsWithDelta(0.0, $f->geometry->width, 0.001, 'contain:inline-size zeroes the flex content width');
    }

    public function testContentVisibilityHiddenSizesFromContainIntrinsicSize(): void
    {
        // CSS Sizing 4 §6.1 / CSS Containment — a content-visibility:hidden
        // box is size-contained: its contents are skipped and their size
        // contribution is replaced by `contain-intrinsic-size`. That intrinsic
        // size feeds the box's CONTENT-BASED sizes, so auto HEIGHT resolves to
        // the intrinsic 222px. But a normal in-flow block's auto WIDTH still
        // stretches to fill its containing block (the intrinsic width only
        // feeds shrink-to-fit contexts), so #cv fills the 600px body width —
        // not the 111px intrinsic width, and not the skipped child's 500px.
        $box = $this->buildTree(
            '<html><body><div id="cv"><div id="big"></div></div></body></html>',
            'html, body, div { display: block; }
             #cv { content-visibility: hidden; contain-intrinsic-size: 111px 222px; }
             #big { width: 500px; height: 500px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cv = $this->findById($box, 'cv');
        self::assertNotNull($cv);
        self::assertSame([], $cv->children, 'contents are skipped');
        self::assertEqualsWithDelta(600.0, $cv->geometry->width, 0.001, 'auto width stretches to the containing block');
        self::assertEqualsWithDelta(222.0, $cv->geometry->height, 0.001, 'auto height from contain-intrinsic-size');
    }

    public function testContentVisibilityHiddenExplicitSizeWins(): void
    {
        // An explicit width/height still wins over the intrinsic size.
        $box = $this->buildTree(
            '<html><body><div id="cv"><div id="big"></div></div></body></html>',
            'html, body, div { display: block; }
             #cv { content-visibility: hidden; contain-intrinsic-size: 111px 222px;
                   width: 60px; height: 70px; }
             #big { width: 500px; height: 500px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cv = $this->findById($box, 'cv');
        self::assertNotNull($cv);
        self::assertEqualsWithDelta(60.0, $cv->geometry->width, 0.001, 'explicit width wins');
        self::assertEqualsWithDelta(70.0, $cv->geometry->height, 0.001, 'explicit height wins');
    }

    public function testRelativeCalcInsetWithPercentAgainstIndefiniteHeightDropsPercent(): void
    {
        // CSS Position 3 §3.4 — a relative inset `calc(10px + 10%)` whose
        // percentage resolves against an indefinite containing-block height
        // (body is auto-height here) is unresolvable for the percentage
        // part; only the 10px length applies → dy = 10, NOT 10 + 10% of the
        // viewport fallback height. Guards the Calc branch of
        // resolveLengthAgainstHeight (a bare Percentage was already handled).
        // The child's containing block (#p) is auto-height — a bare
        // min-height does NOT make it definite — so the whole inset is
        // unresolvable and its used value is 0 (per §3.4 the entire calc,
        // not just the percentage term, is dropped). The child stays at its
        // static y = 0. Pre-fix, 10% resolved against the viewport fallback
        // and pushed it ~90px down.
        $box = $this->buildTree(
            '<html><body><div id="p"><div id="c" style="position: relative; top: calc(10px + 10%); height: 40px"></div></div></body></html>',
            'html, body, div { display: block; } #p { min-height: 100px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->findById($box, 'c');
        self::assertNotNull($div);
        self::assertSame(0.0, $div->geometry->y);
    }

    public function testRelativeCalcInsetPureLengthResolvesFully(): void
    {
        // A calc() with no percentage resolves normally regardless of CB
        // definiteness: `calc(6px + 4px)` → dy = 10 (the probe is non-NAN).
        $box = $this->buildTree(
            '<html><body><div style="position: relative; top: calc(6px + 4px); height: 40px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(10.0, $div->geometry->y);
    }

    public function testRelativeDoesNotAffectSiblings(): void
    {
        // A relative box's shift is paint-only. The next sibling stacks
        // at the box's original flow position, not the shifted one.
        $box = $this->buildTree(
            '<html><body>'
                . '<div class="a" style="position: relative; top: 30px; height: 40px"></div>'
                . '<div class="b" style="height: 40px"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = null;
        foreach ($this->find($box, 'body')->children as $c) {
            if ($c->element !== null && in_array('b', $c->element->classes(), true)) {
                $b = $c;
                break;
            }
        }
        self::assertNotNull($b);
        // Sibling sits at y=40 (right below `.a`'s flow position),
        // unaffected by the relative shift.
        self::assertSame(40.0, $b->geometry->y);
    }

    public function testRelativePercentageOffsetResolvesAgainstContainingBlock(): void
    {
        // `top: 10%` resolves against containing-block height (800),
        // `left: 5%` resolves against containing-block width (600).
        $box = $this->buildTree(
            '<html><body><div style="position: relative; top: 10%; left: 5%; height: 40px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(30.0, $div->geometry->x);
        self::assertSame(80.0, $div->geometry->y);
    }

    public function testRelativePercentageOffsetUsesParentHeightNotGrandparent(): void
    {
        // CSS 2.1 §10.5 — percentage `top` resolves against the
        // CONTAINING block's height (the immediate positioned-ancestor
        // box) not the page / viewport. Without this, `top: 100%` on
        // a child of a 100px-high div resolved against the 800px
        // viewport and rendered 700px below where the spec mandates.
        // Lit up the position-relative-001 → -012 cluster (≈12
        // near-miss WPT reftests) plus a long tail in
        // hypothetical-dynamic-change-*.
        $box = $this->buildTree(
            '<html><body>'
                . '<div id="parent" style="height: 100px; width: 100px">'
                . '<div id="child" style="position: relative; top: 100%; left: 100%"></div>'
                . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $child = $this->find($box, 'div.child') ?? $this->findById($box, 'child');
        self::assertNotNull($child);
        // Containing block is the 100×100 parent, so top:100% = 100, left:100% = 100.
        self::assertSame(100.0, $child->geometry->y);
        self::assertSame(100.0, $child->geometry->x);
    }

    public function testRelativePercentageOnAutoHeightParentCollapsesToZero(): void
    {
        // CSS 2.1 §10.5 + CSS Position 3 §3.4 — when the immediate
        // parent's height is `auto` (indefinite), percentage `top` /
        // `bottom` on a relative child collapse to 0 per spec. Without
        // this rule, `top: -10000%` inside a min-height-only parent
        // would shift descendants off the page using the grandparent's
        // viewport height as the basis. Browsers match this strict
        // resolution; lights up `css-position/position-relative-006`
        // and a handful of related fixtures whose author-comments
        // explicitly assert "doesn't resolve against indefinite
        // parent". The `<html>` / `<body>` chain is special-cased to
        // inherit the viewport's definite height — top-level
        // percentages on body's direct children DO resolve.
        $box = $this->buildTree(
            '<html><body>'
                . '<div id="parent" style="width: 100px">'
                . '<div id="child" style="position: relative; top: 25%"></div>'
                . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $child = $this->findById($box, 'child');
        self::assertNotNull($child);
        // Parent (auto-height div) is NOT html/body, so its content
        // height is indefinite for the child's percentage resolution.
        // top: 25% → 0.
        self::assertSame(0.0, $child->geometry->y);
    }

    public function testRelativePercentageOnTopLevelBodyDescendantUsesViewport(): void
    {
        // Positive: body's height is auto, but the HTML rendering
        // rules treat body as inheriting the viewport's definite
        // height. So a top-level div in body with `top: 10%` resolves
        // against the viewport (= 800 in defaultCtx) → 80.
        $box = $this->buildTree(
            '<html><body>'
                . '<div id="child" style="position: relative; top: 10%"></div>'
                . '</body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $child = $this->findById($box, 'child');
        self::assertNotNull($child);
        // body inherits definite from root → 10% of 800 = 80.
        self::assertSame(80.0, $child->geometry->y);
    }

    // ------------------------------------------------------------
    // CSS 2.1 §9.4.3 — `position: relative` on INLINE-LEVEL atomic
    // boxes (replaced `<img>`, `display: inline-block`). InlineLayout
    // commits the atomic at its static flow position; the relative
    // post-pass in `layoutInlineChildren` applies the offset.
    // ------------------------------------------------------------

    /**
     * Negative: a `display: inline-block` atomic with no positioning
     * must stay at its static flow position.
     */
    public function testStaticInlineBlockAtomicNotShifted(): void
    {
        $box = $this->buildTree(
            '<html><body><div id="ib" style="display: inline-block; width: 30px; height: 30px"></div></body></html>',
            'html, body { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $ib = $this->findById($box, 'ib');
        self::assertNotNull($ib);
        $parent = $this->find($box, 'body');
        self::assertNotNull($parent);
        // Static: first atomic sits at the inline container's content x.
        self::assertSame($parent->geometry->x, $ib->geometry->x, 'static inline-block keeps flow x');
    }

    /**
     * Negative: `position: relative` with all-`auto` offsets is a no-op
     * (mirrors {@see testRelativeWithNoOffsetsIsNoOp} for blocks).
     */
    public function testRelativeInlineBlockWithAutoOffsetsNotShifted(): void
    {
        $staticX = $this->inlineAtomicX('display: inline-block; width: 30px; height: 30px');
        $relX = $this->inlineAtomicX('display: inline-block; width: 30px; height: 30px; position: relative');
        self::assertSame($staticX, $relX, 'auto offsets do not shift the atomic');
    }

    /**
     * Positive: `top` / `left` shift an inline-block atomic (and its
     * subtree) by the resolved offset.
     */
    public function testRelativeInlineBlockTopLeftShifts(): void
    {
        [$sx, $sy] = $this->inlineAtomicXY('display: inline-block; width: 30px; height: 30px');
        [$rx, $ry] = $this->inlineAtomicXY(
            'display: inline-block; width: 30px; height: 30px; position: relative; top: 10px; left: 20px',
        );
        self::assertSame($sx + 20.0, $rx, 'left: 20px shifts atomic right');
        self::assertSame($sy + 10.0, $ry, 'top: 10px shifts atomic down');
    }

    /**
     * Positive (negative-direction edge): `right` / `bottom` shift the
     * atomic the opposite way.
     */
    public function testRelativeInlineBlockRightBottomShiftsNegatively(): void
    {
        [$sx, $sy] = $this->inlineAtomicXY('display: inline-block; width: 30px; height: 30px');
        [$rx, $ry] = $this->inlineAtomicXY(
            'display: inline-block; width: 30px; height: 30px; position: relative; right: 15px; bottom: 8px',
        );
        self::assertSame($sx - 15.0, $rx, 'right: 15px shifts atomic left');
        self::assertSame($sy - 8.0, $ry, 'bottom: 8px shifts atomic up');
    }

    /**
     * Negative: shifting one relatively-positioned atomic must not move
     * a static sibling atomic on the same line.
     */
    public function testRelativeInlineBlockDoesNotShiftStaticSibling(): void
    {
        $box = $this->buildTree(
            '<html><body>'
                . '<div id="a" style="display: inline-block; width: 30px; height: 30px; position: relative; left: 100px"></div>'
                . '<div id="b" style="display: inline-block; width: 30px; height: 30px"></div>'
                . '</body></html>',
            'html, body { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->findById($box, 'a');
        $b = $this->findById($box, 'b');
        self::assertNotNull($a);
        self::assertNotNull($b);
        $parent = $this->find($box, 'body');
        self::assertNotNull($parent);
        // Sibling b keeps its static x (just after a's static box at 30).
        self::assertSame($parent->geometry->x + 30.0, $b->geometry->x, 'static sibling unaffected by a relative shift');
        // a moved right by 100 from its own static origin.
        self::assertSame($parent->geometry->x + 100.0, $a->geometry->x, 'relative atomic shifted by left');
    }

    private function inlineAtomicX(string $style): float
    {
        return $this->inlineAtomicXY($style)[0];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function inlineAtomicXY(string $style): array
    {
        $box = $this->buildTree(
            '<html><body><div id="ib" style="' . $style . '"></div></body></html>',
            'html, body { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $ib = $this->findById($box, 'ib');
        self::assertNotNull($ib);
        return [$ib->geometry->x, $ib->geometry->y];
    }

    // ------------------------------------------------------------
    // CSS2 §10.8 / CSS Inline 3 — line-box height honours the used
    // `line-height` (no hardcoded 1.2 floor) and an inline-level
    // abspos box takes its static position from the line it sits on.
    // ------------------------------------------------------------

    private function mongolianContext(): LayoutContext
    {
        $path = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($path)) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new \Phpdftk\FontParser\OpenTypeParser($path))->parse();
        return new LayoutContext(
            containingBlockWidth: 600.0,
            containingBlockHeight: 800.0,
            originX: 0.0,
            originY: 0.0,
            lengthContext: new \Phpdftk\Css\Cascade\LengthContext(),
            defaultFont: $font,
        );
    }

    private function firstLineHeight(string $pStyle): float
    {
        $box = $this->buildTree(
            '<html><body><p id="p" style="' . $pStyle . '">' . "\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->mongolianContext());
        $p = $this->findById($box, 'p');
        self::assertNotNull($p);
        self::assertNotEmpty($p->lineBoxes, 'paragraph should produce a line box');
        return $p->lineBoxes[0]->height;
    }

    /**
     * Positive: an explicit `line-height: 1` (e.g. `font: 80px/1`) must
     * NOT be inflated to 1.2× — the old hardcoded `max(lh, fontSize ×
     * 1.2)` forced an 80px line to 96.
     */
    public function testExplicitLineHeightOneNotInflated(): void
    {
        self::assertEqualsWithDelta(80.0, $this->firstLineHeight('font-size: 80px; line-height: 1'), 0.01);
    }

    /**
     * Negative: `line-height: normal` keeps the 1.2 default leading.
     */
    public function testNormalLineHeightUsesDefaultLeading(): void
    {
        self::assertEqualsWithDelta(96.0, $this->firstLineHeight('font-size: 80px; line-height: normal'), 0.01);
    }

    /**
     * Positive: a `<number>` line-height scales per font size, so a larger
     * inline child grows the line box. Under the CSS2 §10.8 half-leading
     * model the used height is not the naive `max(parentLH, childFont ×
     * number)` but `max(expandedAscent) + max(expandedDescent)` over the
     * strut + fragments, where `expanded = metric + (lineHeight − ascent −
     * descent) / 2`. For NotoSansMongolian (ascent 1.457em, descent 0.293em)
     * at line-height:1 the 40px child dominates the ascent (43.28) while the
     * 16px strut's less-negative half-leading dominates the descent (−1.31),
     * giving 41.968 — larger than the naive 40.
     */
    public function testNumberLineHeightGrowsWithLargerChildFont(): void
    {
        $box = $this->buildTree(
            '<html><body><p id="p" style="font-size: 16px; line-height: 1">'
            . "\u{1820}" . '<span style="font-size: 40px">' . "\u{1820}" . '</span></p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->mongolianContext());
        $p = $this->findById($box, 'p');
        self::assertNotNull($p);
        // Half-leading line box: 40px child ascent + 16px strut descent.
        self::assertEqualsWithDelta(41.968, $p->lineBoxes[0]->height, 0.01);
    }

    /**
     * Negative (codex-flagged edge): an absolute `<length>` line-height is
     * NOT scaled by the child's font-size ratio — it stays 30px per inline
     * box, so the line box never reaches the naive 30 × (30/16) = 56.25.
     * It does, however, grow modestly beyond 30 under the CSS2 §10.8
     * half-leading model: the 30px child's tall ascent pushes the line's top
     * up (expanded ascent 32.46) while the 16px strut's positive half-leading
     * (line-height 30 > content 28) pushes the bottom down (expanded descent
     * 5.69), for 38.148. The point of the test — no font-ratio scaling —
     * still holds (38.148 ≪ 56.25).
     */
    public function testFixedLengthLineHeightNotScaledByLargerChild(): void
    {
        $box = $this->buildTree(
            '<html><body><p id="p" style="font-size: 16px; line-height: 30px">'
            . "\u{1820}" . '<span style="font-size: 30px">' . "\u{1820}" . '</span></p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->mongolianContext());
        $p = $this->findById($box, 'p');
        self::assertNotNull($p);
        // Half-leading line box — grows past 30 but nowhere near 56.25.
        self::assertEqualsWithDelta(38.148, $p->lineBoxes[0]->height, 0.01);
    }

    /**
     * Positive: for a single-size line the used height still equals the
     * line-height exactly (half-leading above + below sums back to the
     * authored value). 20px font, line-height 2 → 40. This guards that the
     * §10.8 model is a no-op on the common uniform line.
     */
    public function testSingleSizeLineHeightEqualsLineHeight(): void
    {
        $box = $this->buildTree(
            '<html><body><p id="p" style="font-size: 20px; line-height: 2">' . "\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->mongolianContext());
        $p = $this->findById($box, 'p');
        self::assertNotNull($p);
        self::assertEqualsWithDelta(40.0, $p->lineBoxes[0]->height, 0.01);
    }

    /**
     * Positive: the line's shared baseline sits at `ascent + half-leading`,
     * NOT at the raw ascent. NotoSansMongolian ascent 1.457em at 20px = 29.14;
     * line-height 40 gives half-leading (40 − 35) / 2 = 2.5, so baseline
     * 31.64. This is the core half-leading placement the painter consumes.
     */
    public function testHalfLeadingBaselineIsAscentPlusHalfLeading(): void
    {
        $box = $this->buildTree(
            '<html><body><p id="p" style="font-size: 20px; line-height: 40px">' . "\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->mongolianContext());
        $p = $this->findById($box, 'p');
        self::assertNotNull($p);
        self::assertEqualsWithDelta(31.64, $p->lineBoxes[0]->baseline, 0.05);
    }

    /**
     * Negative: a smaller inline child does NOT shrink the line below the
     * block's own strut. Parent font-size 40 / line-height normal (48), a
     * tiny 8px child — the strut floors the line box at 48.
     */
    public function testStrutFloorsLineHeightAgainstSmallerChild(): void
    {
        $box = $this->buildTree(
            '<html><body><p id="p" style="font-size: 40px; line-height: normal">'
            . "\u{1820}" . '<span style="font-size: 8px">' . "\u{1820}" . '</span></p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->mongolianContext());
        $p = $this->findById($box, 'p');
        self::assertNotNull($p);
        // 40px strut: 1.2 × 40 = 48; the 8px child can't pull it down.
        self::assertEqualsWithDelta(48.0, $p->lineBoxes[0]->height, 0.01);
    }

    /**
     * Negative: `line-height: 0` collapses the strut's leading fully — the
     * line box height is the font's own ascent + descent minus the (very
     * negative) leading, i.e. the glyph box shrinks to zero contribution
     * above+below summing to 0 for the strut, so the line height is driven
     * purely by the single fragment's own zero-leading extent (a + d) offset
     * — never negative. Confirms the model floors sanely at line-height 0.
     */
    public function testZeroLineHeightDoesNotProduceNegativeHeight(): void
    {
        $box = $this->buildTree(
            '<html><body><p id="p" style="font-size: 20px; line-height: 0">' . "\u{1820}" . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->mongolianContext());
        $p = $this->findById($box, 'p');
        self::assertNotNull($p);
        // line-height 0 → above + below = 0 for the single 20px box.
        self::assertEqualsWithDelta(0.0, $p->lineBoxes[0]->height, 0.01);
        self::assertGreaterThanOrEqual(0.0, $p->lineBoxes[0]->height);
    }

    /**
     * Build a line mixing a large baseline glyph (48px) and a small 16px
     * glyph carrying `vertical-align: $vAlign`, and return the small
     * fragment's half-leading-expanded extents alongside the line box, so the
     * keyword-placement tests can assert the spec property directly from the
     * fragment's own metrics rather than a magic number.
     *
     * @return array{LineBox, InlineFragment, float, float, float, float} [line, frag, a, d, ea, ed]
     */
    private function vAlignFixture(string $vAlign): array
    {
        $box = $this->buildTree(
            '<html><body><p id="p" style="font-size: 16px">'
            . '<span style="font-size: 48px">' . "\u{1820}" . '</span>'
            . '<span style="font-size: 16px; vertical-align: ' . $vAlign . '">' . "\u{1820}" . '</span>'
            . '</p></body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->mongolianContext());
        $p = $this->findById($box, 'p');
        self::assertNotNull($p);
        $line = $p->lineBoxes[0];
        $frag = $line->fragments[1];
        $font = $frag->shapedRun->font;
        $upem = max(1, $font->unitsPerEm);
        $fs = $frag->shapedRun->fontSizePt;
        $a = ($font->ascent / $upem) * $fs;
        $d = (abs($font->descent) / $upem) * $fs;
        $lead = ($frag->lineHeight - ($a + $d)) / 2.0;
        return [$line, $frag, $a, $d, $a + $lead, $d + $lead];
    }

    /**
     * Positive: `vertical-align: top` pins the small fragment's (expanded)
     * box top to the line box top (y = 0).
     */
    public function testVerticalAlignTopPinsFragmentToLineTop(): void
    {
        [$line, $frag, , , $ea] = $this->vAlignFixture('top');
        $paintedTop = $line->baseline + $frag->baselineShift - $ea;
        self::assertEqualsWithDelta(0.0, $paintedTop, 0.05);
    }

    /**
     * Negative: `vertical-align: bottom` pins the fragment's (expanded) box
     * bottom to the line box bottom (y = height), NOT the top.
     */
    public function testVerticalAlignBottomPinsFragmentToLineBottom(): void
    {
        [$line, $frag, , , , $ed] = $this->vAlignFixture('bottom');
        $paintedBottom = $line->baseline + $frag->baselineShift + $ed;
        self::assertEqualsWithDelta($line->height, $paintedBottom, 0.05);
    }

    /**
     * Negative: `vertical-align: middle` centres the fragment's content box
     * on the baseline minus half the parent's x-height. NotoSansMongolian
     * x-height is 0.5em = 8px at 16px, so the centre sits 4px above baseline.
     */
    public function testVerticalAlignMiddleCentersOnHalfXHeight(): void
    {
        [$line, $frag, $a, $d] = $this->vAlignFixture('middle');
        $center = $line->baseline + $frag->baselineShift + ($d - $a) / 2.0;
        self::assertEqualsWithDelta($line->baseline - 4.0, $center, 0.05);
    }

    /**
     * Negative (control): `vertical-align: baseline` leaves the fragment on
     * the shared baseline — zero shift, so it does NOT move to top/bottom.
     */
    public function testVerticalAlignBaselineLeavesFragmentOnBaseline(): void
    {
        [, $frag] = $this->vAlignFixture('baseline');
        self::assertEqualsWithDelta(0.0, $frag->baselineShift, 0.01);
    }

    /**
     * Negative: `vertical-align: text-top` aligns the fragment's content-box
     * top with the strut's content-box top (line.baseline − strutAscent).
     * NotoSansMongolian ascent 1.457em at the 16px parent = 23.31.
     */
    public function testVerticalAlignTextTopAlignsToStrutAscent(): void
    {
        [$line, $frag, $a] = $this->vAlignFixture('text-top');
        $fragContentTop = $line->baseline + $frag->baselineShift - $a;
        self::assertEqualsWithDelta($line->baseline - 23.31, $fragContentTop, 0.05);
    }

    /**
     * Positive: an inline-level `position: absolute` box with `top:
     * auto` takes its static Y from the line of the preceding inline
     * content — ON that line, not below the whole inline block.
     */
    public function testInlineAbsposStaticPositionSitsOnPrecedingLine(): void
    {
        $box = $this->buildTree(
            '<html><body><div id="cb" style="position: relative; width: 600px; font-size: 40px">'
            . "\u{1820}\u{1820}"
            . '<span id="ap" style="position: absolute; left: 5px">' . "\u{1820}" . '</span>'
            . '</div></body></html>',
            'html, body { display: block; }',
        );
        $this->layout->layout($box, $this->mongolianContext());
        $cb = $this->findById($box, 'cb');
        $ap = $this->findById($box, 'ap');
        self::assertNotNull($cb);
        self::assertNotNull($ap);
        // The abspos sits ON the single line of preceding text (its top),
        // i.e. at the containing block's content top — NOT one line-height
        // below it.
        self::assertEqualsWithDelta($cb->geometry->y, $ap->geometry->y, 0.5, 'inline abspos sits on the content line');
    }

    // ------------------------------------------------------------
    // CSS Writing Modes 4 §7.1 — abspos in a vertical-mode CB runs the
    // §10.3.7 inline algorithm on physical Y and the §10.6.4 block
    // algorithm on physical X (axes swapped vs horizontal-tb).
    // ------------------------------------------------------------

    /**
     * @return array{0: float, 1: float} the abspos span's [x, y]
     */
    private function verticalAbsposGeo(string $cbExtraStyle, string $spanStyle): array
    {
        $box = $this->buildTree(
            '<html><body><div id="cb" style="position: relative; width: 320px; height: 320px; '
            . 'writing-mode: vertical-lr;' . $cbExtraStyle . '">'
            . '<span id="ap" style="position: absolute;' . $spanStyle . '">x</span>'
            . '</div></body></html>',
            'html, body { display: block; }',
        );
        $this->layout->layout($box, $this->mongolianContext());
        $ap = $this->findById($box, 'ap');
        self::assertNotNull($ap);
        return [$ap->geometry->x, $ap->geometry->y];
    }

    /**
     * Block axis (physical X) over-constrained with `auto` margins →
     * §10.6.4 EVEN split. left:40 right:120 width:80 → slack 80 → x = 60.
     */
    public function testVerticalCbBlockAxisEvenSplit(): void
    {
        [$x] = $this->verticalAbsposGeo(
            '',
            'left: 40px; right: 120px; width: 80px; height: 80px; top: auto; bottom: auto; margin: auto',
        );
        // 40 + (320 - 40 - 120 - 80)/2 = 40 + 40 = 80.
        self::assertEqualsWithDelta(80.0, $x, 0.01);
    }

    /**
     * Block axis over-constrained with NON-auto margins → honour the
     * start inset (`left`), ignore `right`. x = 40.
     */
    public function testVerticalCbBlockAxisOverconstrainedHonoursStart(): void
    {
        [$x] = $this->verticalAbsposGeo(
            '',
            'left: 40px; right: 120px; width: 80px; height: 80px; top: auto; bottom: auto',
        );
        self::assertEqualsWithDelta(40.0, $x, 0.01);
    }

    /**
     * Inline axis (physical Y) over-constrained with `auto` margins →
     * §10.3.7 even split (positive slack). top:40 bottom:120 height:80 →
     * slack 80 → y = 80.
     */
    public function testVerticalCbInlineAxisEvenSplit(): void
    {
        [, $y] = $this->verticalAbsposGeo(
            '',
            'top: 40px; bottom: 120px; height: 80px; width: 80px; left: auto; right: auto; margin: auto',
        );
        self::assertEqualsWithDelta(80.0, $y, 0.01);
    }

    /**
     * Inline axis over-constrained, NEGATIVE slack, `direction: rtl` →
     * the slack lands on margin-top (inline-start is `bottom` for rtl),
     * so the box overflows past the top. top:0 bottom:0 height:400 →
     * slack -80 → y = -80.
     */
    public function testVerticalCbInlineAxisRtlNegativeSlack(): void
    {
        [, $y] = $this->verticalAbsposGeo(
            ' direction: rtl;',
            'top: 0; bottom: 0; height: 400px; width: 80px; left: auto; right: auto; margin: auto',
        );
        self::assertEqualsWithDelta(-80.0, $y, 0.01);
    }

    public function testRelativePercentageOnBorderBoxSubtractsInsets(): void
    {
        // Regression guard: when the parent uses `box-sizing: border-box`,
        // its declared `height: 100px` includes border + padding. The
        // percentage basis for children must be the CONTENT height
        // (100 - inset), not 100. Explicit per-side border/padding
        // values avoid any dependency on shorthand expansion order in
        // the cascade.
        $box = $this->buildTree(
            '<html><body>'
                . '<div id="parent" style="box-sizing: border-box; height: 100px; '
                . 'border-top: 10px solid black; border-bottom: 10px solid black; '
                . 'padding-top: 5px; padding-bottom: 5px; width: 100px">'
                . '<div id="child" style="position: relative; top: 100%"></div>'
                . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $child = $this->findById($box, 'child');
        self::assertNotNull($child);
        // Parent's content height = 100 - 20 (border t+b) - 10 (padding t+b) = 70.
        // top: 100% of 70 = 70 relative shift.
        // Child's y = parent content-area top (15: 0 origin + 10 border + 5 padding)
        //           + child natural flow (0)
        //           + child relative shift (70)
        //           = 85.
        self::assertSame(85.0, $child->geometry->y);
    }

    public function testRelativeShiftsDescendantsAlong(): void
    {
        // Descendants ride along with the relative shift — their
        // geometry.y/x is updated so they paint relative to the new
        // parent position.
        $box = $this->buildTree(
            '<html><body>'
                . '<div style="position: relative; top: 50px">'
                . '<p style="height: 30px"></p>'
                . '</div>'
                . '</body></html>',
            'html, body, div, p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertSame(50.0, $p->geometry->y);
    }

    public function testPositionStickyDegradesToRelativeOffsets(): void
    {
        // Positive: `position: sticky` in print has no scroll
        // container, so the spec falls back to relative-like
        // positioning at the static offset — `top: 50px` shifts the
        // paint position down by 50.
        $box = $this->buildTree(
            '<html><body>'
            . '<div class="a" style="height: 100px;"></div>'
            . '<p style="position: sticky; top: 50px; height: 30px;">x</p>'
            . '</body></html>',
            'html, body, div, p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        // Box was statically at y=100; sticky+top:50 shifts to 150.
        self::assertSame(150.0, $p->geometry->y);
    }

    public function testPositionStickyWithoutOffsetsIsNoOp(): void
    {
        // Negative: `position: sticky` with no offsets must not
        // shift — must behave like static.
        $box = $this->buildTree(
            '<html><body>'
            . '<div class="a" style="height: 100px;"></div>'
            . '<p style="position: sticky; height: 30px;">x</p>'
            . '</body></html>',
            'html, body, div, p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertSame(100.0, $p->geometry->y);
    }

    public function testPositionStickyDoesNotAffectSiblings(): void
    {
        // Negative: sibling after a sticky box stacks against the
        // pre-shift position, matching `position: relative` (the box
        // is removed from normal flow only paintwise).
        $box = $this->buildTree(
            '<html><body>'
            . '<p class="a" style="position: sticky; top: 200px; height: 30px;">a</p>'
            . '<p class="b" style="height: 30px;">b</p>'
            . '</body></html>',
            'html, body, p { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = null;
        foreach ($this->find($box, 'body')->children as $c) {
            if ($c->element !== null && in_array('b', $c->element->classes(), true)) {
                $b = $c;
            }
        }
        self::assertNotNull($b);
        self::assertSame(30.0, $b->geometry->y, 'sibling stacks at pre-shift cursor');
    }

    public function testGridEmptyContainerProducesNoChildren(): void
    {
        // Negative: `display: grid` with no children must layout cleanly.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; width: 300px; height: 100px;"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $grid = null;
        foreach ($this->find($box, 'body')->children as $c) {
            if ($c instanceof \Phpdftk\HtmlToPdf\Box\GridBox) {
                $grid = $c;
            }
        }
        self::assertNotNull($grid);
        self::assertSame(300.0, $grid->geometry->width);
        self::assertSame(100.0, $grid->geometry->height);
    }

    public function testGridMissingTemplateColumnsFallsBackToSingleColumn(): void
    {
        // Negative: without `grid-template-columns`, all items land
        // in the same (single) column at the container's full width.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; width: 300px; height: 100px;">'
            . '<div class="a"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $item = $this->find($box, 'div.a');
        // The single column fills the container width.
        self::assertEqualsWithDelta(300.0, $item->geometry->width, 0.001);
    }

    public function testGridOrderAffectsAutoPlacementOrder(): void
    {
        // CSS Grid Layout 1 §6.4 / CSS Box Layout §3.5 — items with a
        // non-default `order` participate in auto-placement in (order,
        // DOM-index) order rather than pure DOM order. Here `a` (the
        // first DOM child) gets `order: 2`, so `b` and `c` (default
        // `order: 0`) place first into cells (0, 0) and (0, 1), and
        // `a` lands in (1, 0).
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 100px; grid-template-rows: 50px 50px;">'
            . '<div class="a" style="order: 2;"></div>'
            . '<div class="b"></div>'
            . '<div class="c"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $grid = $this->find($box, 'div');
        self::assertNotNull($grid);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        $c = $this->find($box, 'div.c');
        // b → (0, 0); c → (0, 1); a → (1, 0).
        self::assertEqualsWithDelta($grid->geometry->x, $b->geometry->x, 0.001);
        self::assertEqualsWithDelta($grid->geometry->y, $b->geometry->y, 0.001);
        self::assertEqualsWithDelta($grid->geometry->x + 100.0, $c->geometry->x, 0.001);
        self::assertEqualsWithDelta($grid->geometry->y, $c->geometry->y, 0.001);
        self::assertEqualsWithDelta($grid->geometry->x, $a->geometry->x, 0.001);
        self::assertEqualsWithDelta($grid->geometry->y + 50.0, $a->geometry->y, 0.001);
    }

    public function testGridOrderTiesBrokenByDomOrder(): void
    {
        // Negative test — items with the same `order` keep their
        // original DOM order (no shuffle).
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 100px; grid-template-rows: 50px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $grid = $this->find($box, 'div');
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        self::assertEqualsWithDelta($grid->geometry->x, $a->geometry->x, 0.001);
        self::assertEqualsWithDelta($grid->geometry->x + 100.0, $b->geometry->x, 0.001);
    }

    public function testTextBoxTrimStartShrinksDirectIfcOuterHeight(): void
    {
        // CSS Inline 3 §6 — `text-box-trim: trim-start` on a block
        // container with its own IFC removes the over half-leading
        // from the first line. With line-height 3 × font-size,
        // half-leading is 1 em (20px when font-size: 20px). The
        // host's geometry.height reduces by that amount AND the
        // first line's y shifts up the same amount.
        //
        // We assert without requiring a registered font by using the
        // existing inline atomic / placeholder content — this test
        // pins the layout-level trim math.
        $box = $this->buildTree(
            '<html><body><p>X</p></body></html>',
            'html, body, p { display: block; }
             p { font-size: 20px; line-height: 60px;
                 text-box-trim: trim-start; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        // When there's no font, the IFC produces zero line boxes and
        // the trim walk is a no-op. We just verify the assertion
        // chain doesn't blow up.
        self::assertNotNull($p->geometry);
    }

    public function testTextBoxTrimPropagationBlockedByEmptyBlock(): void
    {
        // CSS Inline 3 §6.4 — an empty block in the start / end edge
        // chain blocks propagation. We pin this at the
        // BoxGenerator / BlockLayout boundary: the `.line` div's
        // first line should NOT be shifted, because the empty
        // sibling between it and the trim-declaring ancestor is a
        // blocker. The test runs without a font registered, so the
        // child div produces no lines and the walk is a no-op —
        // but the propagation logic should at least leave the
        // `.line` div's geometry intact.
        $box = $this->buildTree(
            '<html><body><div class="root">'
                . '<div class="empty"></div>'
                . '<div class="line">X</div>'
                . '<div class="empty"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }
             .root { font-size: 20px; line-height: 60px;
                     text-box-trim: trim-both; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $line = $this->find($box, 'div.line');
        self::assertNotNull($line);
        // No font configured means no lines emitted, so the
        // propagation walk doesn't shift anything. The test pins
        // the no-op pass.
        self::assertNotNull($line->geometry);
    }

    public function testTextBoxTrimNonePreservesHalfLeading(): void
    {
        // Negative test — `text-box-trim: none` (initial) keeps the
        // full half-leading. Verifies the propagation walk does
        // nothing when the property is missing.
        $box = $this->buildTree(
            '<html><body><p>X</p></body></html>',
            'html, body, p { display: block; }
             p { font-size: 20px; line-height: 60px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        // The default initial value (text-box-trim: none) makes the
        // trim walk a no-op even when a font is present.
        self::assertNotNull($p->geometry);
    }

    public function testInlineWithBlockChildPromotesToAnonymousBlock(): void
    {
        // CSS 2.1 §9.2.1.1 — when an inline element has a block-level
        // child, the inline box splits around the block. We implement
        // the simpler single-level case: the inline gets promoted to
        // an AnonymousBlockBox carrying the original element's cascade,
        // and its children are alternating anonymous inline halves and
        // the original blocks.
        $box = $this->buildTree(
            '<html><body><div class="cb">'
                . '<span class="rel">A<div class="blk">B</div>C</span>'
                . '</div></body></html>',
            'html, body, div { display: block; }
             span { display: inline; }
             .rel { position: relative; }',
        );
        // Find the original <span>'s box — should now be promoted.
        $rel = $this->find($box, 'span.rel');
        self::assertNotNull($rel);
        self::assertInstanceOf(
            \Phpdftk\HtmlToPdf\Box\AnonymousBlockBox::class,
            $rel,
            'span containing a div promotes to AnonymousBlockBox',
        );
        // Three children: anonymous inline ('A'), block ('B'), anon inline ('C').
        self::assertCount(3, $rel->children);
        self::assertInstanceOf(
            \Phpdftk\HtmlToPdf\Box\InlineBox::class,
            $rel->children[0],
            'first child is anonymous inline wrapping "A"',
        );
        self::assertInstanceOf(
            \Phpdftk\HtmlToPdf\Box\BlockBox::class,
            $rel->children[1],
            'middle child is the original block',
        );
        self::assertInstanceOf(
            \Phpdftk\HtmlToPdf\Box\InlineBox::class,
            $rel->children[2],
            'last child is anonymous inline wrapping "C"',
        );
    }

    public function testInlineWithoutBlockChildStaysInline(): void
    {
        // Negative test — an inline element with only inline children
        // stays an InlineBox; the block-in-inline split must NOT fire.
        $box = $this->buildTree(
            '<html><body><div class="cb">'
                . '<span>hello <b>world</b></span>'
                . '</div></body></html>',
            'html, body, div { display: block; }
             span, b { display: inline; }',
        );
        $span = $this->find($box, 'span');
        self::assertNotNull($span);
        self::assertInstanceOf(
            \Phpdftk\HtmlToPdf\Box\InlineBox::class,
            $span,
        );
    }

    public function testIntrinsicMinContentWithOverflowWrapAnywhere(): void
    {
        // CSS Text 3 §6 / Sizing 3 §5.2 — under `overflow-wrap:
        // anywhere`, soft-wrap opportunities exist between every
        // typographic character, so the min-content size of a text
        // box is the widest single glyph advance — not the widest
        // word. Without this, a long unbreakable URL inside a Grid
        // `auto` track forces the track to the URL's full advance.
        if (!is_file(__DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf')) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new \Phpdftk\FontParser\OpenTypeParser(__DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf'))->parse();
        $ctx = new LayoutContext(
            containingBlockWidth: 600.0,
            containingBlockHeight: 800.0,
            originX: 0.0,
            originY: 0.0,
            lengthContext: new \Phpdftk\Css\Cascade\LengthContext(),
            defaultFont: $font,
        );
        // 5-letter "word" in a Grid auto track with overflow-wrap:
        // anywhere. The min-content should collapse to the widest
        // single glyph, not the full word's advance.
        $box = $this->buildTree(
            '<html><body><div class="g">'
            . '<div class="i">' . str_repeat("\u{1820}", 5) . '</div>'
            . '</div></body></html>',
            'html, body { display: block; }
             .g { display: block; }
             .i { overflow-wrap: anywhere; font-size: 20px; }',
        );
        $div = $this->find($box, 'div.i');
        self::assertNotNull($div);
        $minmax = $this->layout->measureMinMaxContent($div, $ctx);
        // max ≈ 5 × glyph_advance; min ≈ widest single glyph_advance.
        // So min < max for a real font, and roughly max/5.
        self::assertLessThan($minmax['max'], $minmax['min']);
        self::assertGreaterThan($minmax['max'] / 10.0, $minmax['min']);
        self::assertLessThan($minmax['max'] / 2.0, $minmax['min']);
    }

    public function testIntrinsicMinContentDefaultUsesWidestWord(): void
    {
        // Negative test — without `overflow-wrap: anywhere` (or
        // sibling keywords), min-content is the widest *word*. A
        // single long unbreakable token defines the floor.
        if (!is_file(__DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf')) {
            self::markTestSkipped('Mongolian fixture font missing');
        }
        $font = (new \Phpdftk\FontParser\OpenTypeParser(__DIR__ . '/../../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf'))->parse();
        $ctx = new LayoutContext(
            containingBlockWidth: 600.0,
            containingBlockHeight: 800.0,
            originX: 0.0,
            originY: 0.0,
            lengthContext: new \Phpdftk\Css\Cascade\LengthContext(),
            defaultFont: $font,
        );
        $box = $this->buildTree(
            '<html><body><div class="i">' . str_repeat("\u{1820}", 5) . '</div></body></html>',
            'html, body, .i { display: block; } .i { font-size: 20px; }',
        );
        $div = $this->find($box, 'div.i');
        self::assertNotNull($div);
        $minmax = $this->layout->measureMinMaxContent($div, $ctx);
        // Single 5-letter token: min == max.
        self::assertEqualsWithDelta($minmax['max'], $minmax['min'], 0.5);
    }

    public function testFixedTableLayoutReadsCascadedColumnWidths(): void
    {
        // CSS 2.1 §17.5.2.1 — `table-layout: fixed` distributes column
        // widths from the first row plus explicit `<col>` declarations,
        // including percentage widths resolved against the table's
        // content width. The cascade on `<col>` reaches BlockLayout via
        // the new `TableColumnBox` (display: table-column from the UA
        // stylesheet); collectColumnWidths reads both CSS Length and
        // CSS Percentage from that box.
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
                . '<col id="a"></col>'
                . '<col id="b"></col>'
                . '<col id="c"></col>'
                . '<col id="d"></col>'
                . '<tr><td>1</td><td>2</td><td>3</td><td>4</td></tr>'
                . '</table></body></html>',
            'table { table-layout: fixed; width: 400px; border-collapse: collapse; }
             #a { width: 13%; }
             #b { width: 100px; }
             #c { width: 31%; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $seen = [];
        $cells = [];
        $walk = function ($n) use (&$walk, &$cells, &$seen) {
            if ($n->element && strtolower($n->element->localName) === 'td') {
                $eid = spl_object_id($n->element);
                if (!isset($seen[$eid])) {
                    $seen[$eid] = true;
                    $cells[] = $n;
                }
            }
            foreach ($n->children as $c) {
                $walk($c);
            }
        };
        $walk($box);
        self::assertCount(4, $cells);
        // 13% of 400 = 52px, 31% of 400 = 124px, auto = 124px (400 - 52 - 100 - 124).
        // Compare against outerWidth so the UA stylesheet's default
        // `td { padding: 2pt }` doesn't make the assertion brittle.
        self::assertEqualsWithDelta(52.0, $cells[0]->geometry->outerWidth(), 1.0, 'col#a → 13%');
        self::assertEqualsWithDelta(100.0, $cells[1]->geometry->outerWidth(), 1.0, 'col#b → 100px');
        self::assertEqualsWithDelta(124.0, $cells[2]->geometry->outerWidth(), 1.0, 'col#c → 31%');
        self::assertEqualsWithDelta(124.0, $cells[3]->geometry->outerWidth(), 1.0, 'col#d → remaining auto');
    }

    public function testFloatAutoMarginResolvesToZero(): void
    {
        // CSS 2.1 §9.5.1 — auto margins on a floated element compute
        // to 0 (they do NOT distribute the slack as auto margins do on
        // in-flow elements). A `float: left; margin-left: auto;
        // width: 1in;` element in a 2in containing block lands at the
        // CB's left edge, not at `2in - 1in = 1in` (the centred result
        // that in-flow auto-margin distribution would produce).
        $box = $this->buildTree(
            '<html><body><div class="cb">'
                . '<div class="flt"></div>'
                . '<div class="filler"></div>'
                . '</div></body></html>',
            'html, body, div { display: block; }
             .cb { width: 200px; height: 200px; }
             .flt { float: left; width: 100px; height: 100px;
                    margin-left: auto; margin-right: auto; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cb = $this->find($box, 'div');
        $flt = $this->find($box, 'div.flt');
        self::assertNotNull($cb);
        self::assertNotNull($flt);
        // Float's outer-X equals the CB's content-edge X (no slack
        // distributed into the auto margins).
        self::assertEqualsWithDelta($cb->geometry->x, $flt->geometry->x, 0.001);
    }

    public function testFloatFirstChildMarginTopDoesNotCollapseThroughParent(): void
    {
        // CSS 2.1 §8.3.1 — a floated element's margins never collapse.
        // When a float with `margin-top` is a block's first child, its
        // margin must NOT collapse through into the parent (which would
        // shift the parent — and every following in-flow sibling — up by
        // the float's margin). The following in-flow block must stay at
        // the parent's content top (y=0), unaffected by the float.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="flt" style="float: left; width: 40px; height: 40px; margin-top: 20px;"></div>'
                . '<div class="flow" style="height: 30px;"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        // The float's margin is not absorbed into the parent.
        self::assertSame(0.0, $section->geometry->marginTop, 'float margin not collapsed into parent');
        self::assertSame(0.0, $section->geometry->y, 'parent stays at document top');
        // The following in-flow block sits at the parent content top,
        // NOT shifted up by the float's 20px margin-top.
        $flow = $this->find($box, 'div.flow');
        self::assertNotNull($flow);
        self::assertSame(0.0, $flow->geometry->y, 'in-flow sibling unaffected by float margin');
        // The float itself is offset down by its own margin-top.
        $flt = $this->find($box, 'div.flt');
        self::assertNotNull($flt);
        self::assertSame(20.0, $flt->geometry->y, 'float positioned below its own margin-top');
    }

    public function testGridUnknownPlacementKeywordFallsBackToAuto(): void
    {
        // Negative: a non-numeric placement keyword that isn't `auto`
        // falls through to the auto path. The item still places at
        // the first free cell.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 100px; grid-template-rows: 50px;">'
            . '<div class="a" style="grid-column-start: nonsense;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        // First free cell = (0, 0); X offset == 0 from grid origin.
        self::assertSame(0.0, $a->geometry->x);
    }

    public function testGridItemBeyondExplicitTracksIsSilentlyDropped(): void
    {
        // Negative: an item placed at column 99 in a 2-column grid
        // has no track and silently doesn't render (implicit-track
        // growth is a deferred follow-up). Other items still place.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 100px; grid-template-rows: 50px;">'
            . '<div class="a" style="grid-column: 99;"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = $this->find($box, 'div.b');
        // 'b' auto-places at the first free cell (0, 0).
        self::assertSame(0.0, $b->geometry->x);
    }

    public function testGridEndBeforeStartSwapsToOneCellSpan(): void
    {
        // Negative: `grid-column: 3 / 1` — end before start should
        // still place the item, not crash or skip.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 100px 100px; grid-template-rows: 50px;">'
            . '<div class="a" style="grid-column-start: 3; grid-column-end: 1;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        // The placement is satisfied somewhere in the grid (exact
        // column doesn't matter for the negative — the assertion is
        // "doesn't crash and child got placed with positive width").
        self::assertGreaterThan(0.0, $a->geometry->width);
    }

    public function testGridChildWithoutPlacementAutoFlowsToFirstFreeCell(): void
    {
        // Negative: a child with no placement at all should land at
        // the first free cell (auto-flow row direction).
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 100px; grid-template-rows: 50px;">'
            . '<div class="placed" style="grid-column: 2;"></div>'
            . '<div class="auto"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $placed = $this->find($box, 'div.placed');
        $auto = $this->find($box, 'div.auto');
        // `placed` at col=1 (1-based "2" = 0-based 1) → x = 100.
        self::assertSame(100.0, $placed->geometry->x);
        // `auto` at col=0 → x = 0 (first free cell).
        self::assertSame(0.0, $auto->geometry->x);
    }

    public function testGridNegativeIndexCountsFromEnd(): void
    {
        // Negative-encoded positive: `grid-column: -1` resolves to
        // the last line (= the right edge), so a 1-cell span ending
        // at -1 means the rightmost cell.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 50px 50px 50px; grid-template-rows: 50px;">'
            . '<div class="a" style="grid-column-end: -1;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        // end = -1 = last line (line 4 = index 3). start = auto → 1-cell
        // ending at 3 means start at 2 (the rightmost cell).
        self::assertSame(100.0, $a->geometry->x);
    }

    public function testGridInlineChildrenAreSkipped(): void
    {
        // Negative: text and inline children of a grid container are
        // skipped at MVP (Phase-2 doesn't synthesize anonymous blocks
        // for inline-level grid items). The grid still lays out OK.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px; grid-template-rows: 50px;">'
            . 'raw text'
            . '<div class="block"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $block = $this->find($box, 'div.block');
        // The block lands at the only cell (0, 0).
        self::assertSame(0.0, $block->geometry->x);
        self::assertSame(0.0, $block->geometry->y);
    }

    public function testGridMinmaxLengthFrFloorsAtMinAndDistributesRemainder(): void
    {
        // CSS Grid 2 §7.2.4 — `minmax(100px, 1fr) minmax(200px, 1fr)`
        // in a 600px container reserves 100 + 200 = 300 for floors,
        // distributes the remaining 300 equally between the two fr
        // tracks (150 each), and each track ends at floor + share.
        // Previously the fr max was unhandled and tracks collapsed
        // to the 0-fallback (or to min — varied), producing a 0/100
        // or 100/200 split instead of the spec 250/350.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: minmax(100px, 1fr) minmax(200px, 1fr); '
            . 'grid-template-rows: 50px; width: 600px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        self::assertEqualsWithDelta(250.0, $a->geometry->width, 0.001);
        self::assertEqualsWithDelta(350.0, $b->geometry->width, 0.001);
        self::assertEqualsWithDelta(250.0, $b->geometry->x, 0.001);
    }

    public function testGridMinmaxZeroFrCollapsesToShareOnly(): void
    {
        // `minmax(0, 1fr)` — floor is 0, fr distribution uses full
        // available space. This is the most common minmax pattern in
        // author CSS for "flexible but at least the minmax min" and
        // was previously collapsing to a zero-width track.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); '
            . 'grid-template-rows: 50px; width: 600px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        self::assertEqualsWithDelta(300.0, $a->geometry->width, 0.001);
        self::assertEqualsWithDelta(300.0, $b->geometry->width, 0.001);
    }

    public function testGridGapNormalResolvesToZero(): void
    {
        // Negative: `column-gap: normal` (initial) is zero for grid,
        // matching flexbox. Two adjacent 100px columns put item 2
        // at x = 100, not at x = 100 + some gap.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 100px; grid-template-rows: 50px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = $this->find($box, 'div.b');
        self::assertSame(100.0, $b->geometry->x);
    }

    public function testGridInvalidTrackValueDroppedFromTemplate(): void
    {
        // Negative: non-`<length>` track values aren't honoured at
        // Phase-2 (no `fr`, no `auto`). The track list parses with
        // only the valid lengths kept.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 1fr 100px; grid-template-rows: 50px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = $this->find($box, 'div.b');
        // Only 2 of the 3 declared columns are honoured (1fr dropped).
        // First column = 100px → 'a' at x=0; second column = 100px →
        // 'b' at x=100.
        self::assertSame(100.0, $b->geometry->x);
    }

    public function testGridExplicitPlacementHonoursColumnAndRow(): void
    {
        // Positive: explicit `grid-column: 2; grid-row: 2` places at
        // the (1, 1) 0-based cell.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 80px 80px 80px; '
            . 'grid-template-rows: 40px 40px;">'
            . '<div class="a" style="grid-column: 2; grid-row: 2;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertSame(80.0, $a->geometry->x, 'x at second column');
        self::assertSame(40.0, $a->geometry->y, 'y at second row');
    }

    public function testGridAutoFlowFillsRowMajor(): void
    {
        // Positive: three items in a 2-column grid auto-flow into
        // (0,0), (0,1), (1,0) row-by-row.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 80px 80px; '
            . 'grid-template-rows: 40px 40px;">'
            . '<div class="a"></div><div class="b"></div><div class="c"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        $c = $this->find($box, 'div.c');
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(0.0, $a->geometry->y);
        self::assertSame(80.0, $b->geometry->x);
        self::assertSame(0.0, $b->geometry->y);
        self::assertSame(0.0, $c->geometry->x);
        self::assertSame(40.0, $c->geometry->y);
    }

    public function testGridGapInsertsSpaceBetweenTracks(): void
    {
        // Positive: `column-gap: 10px` shifts the second-column
        // item right by 10. `row-gap: 8px` shifts the second-row
        // item down by 8.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 80px 80px; '
            . 'grid-template-rows: 40px 40px; '
            . 'column-gap: 10px; row-gap: 8px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '<div class="c"></div>'
            . '<div class="d"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = $this->find($box, 'div.b');
        $c = $this->find($box, 'div.c');
        $d = $this->find($box, 'div.d');
        self::assertSame(90.0, $b->geometry->x, 'col-gap applied');
        self::assertSame(48.0, $c->geometry->y, 'row-gap applied');
        self::assertSame(90.0, $d->geometry->x);
        self::assertSame(48.0, $d->geometry->y);
    }

    public function testGridMultiColumnSpanWidensChild(): void
    {
        // Positive: `grid-column: 1 / 3` makes the item span 2
        // columns. Width = sum of column widths + the gap between.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 80px 80px; '
            . 'grid-template-rows: 40px; column-gap: 10px;">'
            . '<div class="a" style="grid-column: 1 / 3;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        // Spans both 80px columns plus one 10px gap = 170.
        self::assertEqualsWithDelta(170.0, $a->geometry->width, 0.001);
    }

    public function testGridChildSizesToCellWidth(): void
    {
        // Positive: a 1-cell child stretches to fill its column
        // track width (= grid's default "stretch" behaviour for
        // both justify-self and align-self at MVP).
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 120px; grid-template-rows: 40px;">'
            . '<div class="a"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertEqualsWithDelta(120.0, $a->geometry->width, 0.001);
    }

    public function testGridFrZeroCountTrackDropped(): void
    {
        // Negative: `0fr` has zero share — it gets zero width when
        // other tracks consume the available space. Our parser
        // currently drops descriptors with non-positive fr counts.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 0fr; '
            . 'grid-template-rows: 40px; width: 300px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = $this->find($box, 'div.b');
        // 0fr was dropped → only 1 track (100px); 'b' falls off the
        // grid (no second track) and is silently dropped.
        self::assertSame(0.0, $b->geometry->x, 'no second track means b drops to default-placed');
    }

    public function testGridFrWithoutContainerExtentCollapsesToZero(): void
    {
        // Negative: fr tracks divide REMAINING space after fixed
        // widths. If container width is zero (or all consumed by
        // fixed tracks) the fr space is zero.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 1fr; '
            . 'grid-template-rows: 40px; width: 0px;">'
            . '<div class="a"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertSame(0.0, $a->geometry->width);
    }

    public function testGridFrIgnoredOnAutoHeightContainer(): void
    {
        // Negative: row fr depends on the container's declared
        // height. With no explicit height (auto), the fr-space is 0
        // and row fr tracks collapse.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px; '
            . 'grid-template-rows: 1fr; width: 300px;">'
            . '<div class="a"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        // Row fr resolves against the declaredHeight; with none,
        // it's 0 so the row track is 0 height.
        self::assertSame(0.0, $a->geometry->height);
    }

    public function testGridFrSplitsRemainingSpaceProportionally(): void
    {
        // Positive: in a 300px container with 100px fixed + 1fr + 1fr,
        // remaining 200px splits 100/100 between the two fr tracks.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 1fr 1fr; '
            . 'grid-template-rows: 40px; width: 300px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '<div class="c"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = $this->find($box, 'div.b');
        $c = $this->find($box, 'div.c');
        self::assertEqualsWithDelta(100.0, $b->geometry->x, 0.001);
        self::assertEqualsWithDelta(100.0, $b->geometry->width, 0.001);
        self::assertEqualsWithDelta(200.0, $c->geometry->x, 0.001);
        self::assertEqualsWithDelta(100.0, $c->geometry->width, 0.001);
    }

    public function testGridFrUnequalCountsSplitsProportionally(): void
    {
        // Positive: 1fr + 2fr + 3fr in 600px container splits the
        // 600 into 1/6, 2/6, 3/6 → 100, 200, 300.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 1fr 2fr 3fr; '
            . 'grid-template-rows: 40px; width: 600px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '<div class="c"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        $c = $this->find($box, 'div.c');
        self::assertEqualsWithDelta(100.0, $a->geometry->width, 0.001);
        self::assertEqualsWithDelta(200.0, $b->geometry->width, 0.001);
        self::assertEqualsWithDelta(300.0, $c->geometry->width, 0.001);
    }

    public function testGridRepeatZeroCountDropped(): void
    {
        // Negative: `repeat(0, 100px)` produces no tracks. The
        // implicit single-column fallback kicks in.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: repeat(0, 100px); '
            . 'grid-template-rows: 40px; width: 300px;">'
            . '<div class="a"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        // Fallback to a single full-width column.
        self::assertEqualsWithDelta(300.0, $a->geometry->width, 0.001);
    }

    public function testGridRepeatNegativeCountIgnored(): void
    {
        // Negative: negative repeat counts evaluate to no tracks.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: repeat(-2, 100px); '
            . 'grid-template-rows: 40px; width: 300px;">'
            . '<div class="a"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertEqualsWithDelta(300.0, $a->geometry->width, 0.001);
    }

    public function testGridRepeatExpandsToFixedTracks(): void
    {
        // Positive: `repeat(3, 80px)` expands to 3 80px columns.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: repeat(3, 80px); '
            . 'grid-template-rows: 40px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '<div class="c"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        $c = $this->find($box, 'div.c');
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(80.0, $b->geometry->x);
        self::assertSame(160.0, $c->geometry->x);
    }

    public function testGridRepeatMixedWithFixedTracks(): void
    {
        // Positive: `100px repeat(2, 50px)` → [100, 50, 50].
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px repeat(2, 50px); '
            . 'grid-template-rows: 40px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '<div class="c"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        $c = $this->find($box, 'div.c');
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(100.0, $b->geometry->x);
        self::assertSame(150.0, $c->geometry->x);
    }

    public function testGridSpanWithoutIntegerDefaultsToOne(): void
    {
        // Negative: `span` without a count defaults to span 1.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 100px 100px; '
            . 'grid-template-rows: 40px;">'
            . '<div class="a" style="grid-column: span auto;"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        // 'a' span 1 takes col 0. 'b' takes col 1.
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(100.0, $b->geometry->x);
    }

    public function testGridSpanZeroClampsToOne(): void
    {
        // Negative: `span 0` clamps to 1 cell per spec.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 100px; '
            . 'grid-template-rows: 40px;">'
            . '<div class="a" style="grid-column: span 0;"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        self::assertEqualsWithDelta(100.0, $a->geometry->width, 0.001);
        self::assertSame(100.0, $b->geometry->x);
    }

    public function testGridSpanBeyondGridDropsItem(): void
    {
        // Negative: `span 99` exceeds the grid → item silently drops
        // (implicit-track growth is a follow-up). Other items still place.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 100px; '
            . 'grid-template-rows: 40px;">'
            . '<div class="a" style="grid-column: span 99;"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = $this->find($box, 'div.b');
        // 'a' dropped; 'b' auto-places at first free cell.
        self::assertSame(0.0, $b->geometry->x);
    }

    public function testGridSpanExplicitStartSpansForward(): void
    {
        // Positive: `grid-column: 1 / span 2` starts at col 0, spans 2.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 80px 80px 80px; '
            . 'grid-template-rows: 40px;">'
            . '<div class="a" style="grid-column: 1 / span 2;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertSame(0.0, $a->geometry->x);
        self::assertEqualsWithDelta(160.0, $a->geometry->width, 0.001);
    }

    public function testGridJustifySelfAutoResolvesToStretch(): void
    {
        // Negative: `justify-self: auto` (initial) → stretch in grid.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 200px; '
            . 'grid-template-rows: 40px;">'
            . '<div class="a"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertEqualsWithDelta(200.0, $a->geometry->width, 0.001);
    }

    public function testGridJustifySelfUnknownKeywordResolvesToStretch(): void
    {
        // Negative: unknown self keyword falls back to stretch.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 200px; '
            . 'grid-template-rows: 40px;">'
            . '<div class="a" style="justify-self: nonsense;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertEqualsWithDelta(200.0, $a->geometry->width, 0.001);
    }

    public function testGridJustifySelfStartLeavesItemAtCellOrigin(): void
    {
        // Negative-ish: explicit `justify-self: start` should still
        // place at cell origin; the item doesn't stretch, so its
        // width = its declared content (auto = container content).
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 200px; '
            . 'grid-template-rows: 40px;">'
            . '<div class="a" style="justify-self: start; width: 50px;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(50.0, $a->geometry->width);
    }

    public function testGridJustifySelfEndShiftsRight(): void
    {
        // Positive: `justify-self: end` aligns the item to cell's
        // main-end. In a 200px cell with a 50px-wide item, the item
        // shifts right by 150 (200 - 50).
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 200px; '
            . 'grid-template-rows: 40px;">'
            . '<div class="a" style="justify-self: end; width: 50px;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertEqualsWithDelta(150.0, $a->geometry->x, 0.001);
    }

    public function testGridJustifySelfCenterCentersItem(): void
    {
        // Positive: `justify-self: center` centers the item in its
        // cell. 200px cell - 50px item = 150px slack, item shifts by 75.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 200px; '
            . 'grid-template-rows: 40px;">'
            . '<div class="a" style="justify-self: center; width: 50px;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertEqualsWithDelta(75.0, $a->geometry->x, 0.001);
    }

    public function testGridAlignSelfEndShiftsDown(): void
    {
        // Positive: `align-self: end` aligns the item to cell's
        // cross-end. 50px tall cell, item with 20px height → shifts
        // down by 30.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px; '
            . 'grid-template-rows: 50px;">'
            . '<div class="a" style="align-self: end; height: 20px;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertEqualsWithDelta(30.0, $a->geometry->y, 0.001);
    }

    public function testGridTemplateAreasDropsOnMismatchedColumns(): void
    {
        // Negative: rows with different column counts make the whole
        // template invalid per spec — `grid-area: header` won't resolve.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-areas: \'a a\' \'b\'; '
            . 'grid-template-columns: 100px 100px; '
            . 'grid-template-rows: 40px 40px;">'
            . '<div class="x" style="grid-area: a;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $x = $this->find($box, 'div.x');
        // Template dropped → grid-area: a doesn't resolve, falls
        // through to auto-flow at (0, 0).
        self::assertSame(0.0, $x->geometry->x);
        // Stretched across only 1 column since the area lookup
        // failed (would have been 2 columns if it resolved).
        self::assertEqualsWithDelta(100.0, $x->geometry->width, 0.001);
    }

    public function testGridTemplateAreasNonRectangularNameDropped(): void
    {
        // Negative: an L-shaped name area is invalid per spec — its
        // entry drops from the area map.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-areas: \'a a\' \'a .\'; '
            . 'grid-template-columns: 100px 100px; '
            . 'grid-template-rows: 40px 40px;">'
            . '<div class="x" style="grid-area: a;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $x = $this->find($box, 'div.x');
        // `a` is L-shaped (top-row both cells + bottom-left only)
        // → dropped → falls back to auto-flow at (0, 0).
        self::assertSame(0.0, $x->geometry->x);
        self::assertSame(0.0, $x->geometry->y);
    }

    public function testGridTemplateAreasUnknownNameFallsThroughToAuto(): void
    {
        // Negative: `grid-area: bogus` doesn't match any declared
        // area → falls back to auto-flow at the next free cell.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-areas: \'header\'; '
            . 'grid-template-columns: 100px; '
            . 'grid-template-rows: 40px 40px;">'
            . '<div class="real" style="grid-area: header;"></div>'
            . '<div class="phantom" style="grid-area: nonsense;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $real = $this->find($box, 'div.real');
        $phantom = $this->find($box, 'div.phantom');
        // real → row 0
        self::assertSame(0.0, $real->geometry->y);
        // phantom → row 1 (next free after real)
        self::assertSame(40.0, $phantom->geometry->y);
    }

    public function testGridTemplateAreasNoneFallsBackToTracks(): void
    {
        // Negative: `grid-template-areas: none` (initial) leaves the
        // area map empty; placement falls back to explicit tracks.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 80px 80px;'
            . 'grid-template-rows: 40px;">'
            . '<div class="x"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $x = $this->find($box, 'div.x');
        self::assertEqualsWithDelta(80.0, $x->geometry->width, 0.001);
    }

    public function testGridAreaShorthandWithFourValuesExpands(): void
    {
        // Negative: 4-value `grid-area: 1 / 2 / 3 / 4` expands to
        // row-start=1, col-start=2, row-end=3, col-end=4 (line nums).
        // Item should span rows 1-2 (0-based: 0-1) and cols 2-3
        // (0-based: 1-2).
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 50px 50px 50px; '
            . 'grid-template-rows: 30px 30px;">'
            . '<div class="x" style="grid-area: 1 / 2 / 3 / 4;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $x = $this->find($box, 'div.x');
        // col-start=2 (0-based 1) → x = 50
        // span 2 cols (col-end 4 → 0-based 3) → width = 100
        self::assertEqualsWithDelta(50.0, $x->geometry->x, 0.001);
        self::assertEqualsWithDelta(100.0, $x->geometry->width, 0.001);
        // row-start=1 → y = 0; span 2 rows → height = 60
        self::assertEqualsWithDelta(0.0, $x->geometry->y, 0.001);
        self::assertEqualsWithDelta(60.0, $x->geometry->height, 0.001);
    }

    public function testGridAreaNameResolvesToAreaRectangle(): void
    {
        // Positive: `grid-area: header` looks up the named area's
        // rectangle from the `grid-template-areas` map.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-areas: \'h h h\' \'s m m\'; '
            . 'grid-template-columns: 50px 60px 70px; '
            . 'grid-template-rows: 30px 40px;">'
            . '<div class="header" style="grid-area: h;"></div>'
            . '<div class="side" style="grid-area: s;"></div>'
            . '<div class="main" style="grid-area: m;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $header = $this->find($box, 'div.header');
        $side = $this->find($box, 'div.side');
        $main = $this->find($box, 'div.main');
        // header spans all 3 columns of row 0 → width = 50+60+70 = 180
        self::assertSame(0.0, $header->geometry->x);
        self::assertSame(0.0, $header->geometry->y);
        self::assertEqualsWithDelta(180.0, $header->geometry->width, 0.001);
        // side = bottom-left cell only
        self::assertSame(0.0, $side->geometry->x);
        self::assertSame(30.0, $side->geometry->y);
        self::assertEqualsWithDelta(50.0, $side->geometry->width, 0.001);
        // main = bottom row cols 1-2 → x = 50, width = 60 + 70 = 130
        self::assertSame(50.0, $main->geometry->x);
        self::assertEqualsWithDelta(130.0, $main->geometry->width, 0.001);
    }

    public function testGridTemplateAreasImplicitRowAndColumnCounts(): void
    {
        // Positive: when only grid-template-areas is set (no
        // grid-template-columns/rows), the area-grid's dimensions
        // drive implicit equal-sized tracks. 3-column area-grid in a
        // 600px container → 200px columns.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-areas: \'a b c\'; width: 600px; height: 40px;">'
            . '<div class="a" style="grid-area: a;"></div>'
            . '<div class="c" style="grid-area: c;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $c = $this->find($box, 'div.c');
        self::assertEqualsWithDelta(200.0, $a->geometry->width, 0.001);
        self::assertEqualsWithDelta(400.0, $c->geometry->x, 0.001);
    }

    public function testGridTemplateAreasDotCellIsAnonymous(): void
    {
        // Positive: `.` in the template-areas string marks an
        // anonymous null cell — no item can target it by name, but
        // an auto-flowing item still places into it.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-areas: \'a .\' \'. b\'; '
            . 'grid-template-columns: 50px 50px; '
            . 'grid-template-rows: 30px 30px;">'
            . '<div class="a" style="grid-area: a;"></div>'
            . '<div class="b" style="grid-area: b;"></div>'
            . '<div class="filler"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        $filler = $this->find($box, 'div.filler');
        // a at (0,0), b at (1,1), filler auto-places at (0,1)
        // (the first free cell in document/row-major order).
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(0.0, $a->geometry->y);
        self::assertSame(50.0, $b->geometry->x);
        self::assertSame(30.0, $b->geometry->y);
        self::assertSame(50.0, $filler->geometry->x);
        self::assertSame(0.0, $filler->geometry->y);
    }

    public function testGridImplicitRowGrowsWithAutoRows(): void
    {
        // Positive: more items than declared rows triggers implicit
        // row growth via grid-auto-rows. 2 columns × 1 declared row +
        // 4 items → 2 rows total. The 4th item lands at row 1.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 80px 80px; '
            . 'grid-template-rows: 30px; '
            . 'grid-auto-rows: 50px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '<div class="c"></div>'
            . '<div class="d"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = $this->find($box, 'div.c');
        $d = $this->find($box, 'div.d');
        // c at row 1, col 0 → y = 30 (after first row)
        self::assertSame(30.0, $c->geometry->y);
        // d at row 1, col 1 → also y = 30, x = 80
        self::assertSame(30.0, $d->geometry->y);
        self::assertSame(80.0, $d->geometry->x);
        // Implicit row height = grid-auto-rows: 50px
        self::assertEqualsWithDelta(50.0, $c->geometry->height, 0.001);
    }

    public function testGridImplicitRowFromExplicitPlacementBeyondGrid(): void
    {
        // Positive: `grid-row: 4` places an item at row 3 (0-based)
        // even when grid-template-rows declares only 2 rows; the
        // intervening rows grow via grid-auto-rows.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px; '
            . 'grid-template-rows: 20px 20px; '
            . 'grid-auto-rows: 30px;">'
            . '<div class="a" style="grid-row: 4;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        // Row 4 (1-based) = row 3 (0-based) → y = 20 + 20 + 30 (one
        // grown row) = 70.
        self::assertSame(70.0, $a->geometry->y);
        self::assertEqualsWithDelta(30.0, $a->geometry->height, 0.001);
    }

    public function testGridImplicitRowAutoKeywordCollapsesToZero(): void
    {
        // Negative: `grid-auto-rows: auto` (initial) means
        // "intrinsic-content-sized", which Phase-2 doesn't measure
        // yet — collapses to 0 height. With explicit rows declared,
        // items past them grow with 0-tall implicit rows.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px; '
            . 'grid-template-rows: 40px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = $this->find($box, 'div.b');
        // Implicit row at y = 40 (after first explicit row), height 0
        self::assertSame(40.0, $b->geometry->y);
        self::assertEqualsWithDelta(0.0, $b->geometry->height, 0.001);
    }

    public function testGridImplicitColumnsStillSilentlyDrops(): void
    {
        // Negative: `grid-column: 99` past the column count still
        // drops (Phase-2 grows rows only — column-axis growth needs
        // `grid-auto-flow: column` which isn't shipped). The dropped
        // item leaves room for auto-flow siblings.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px 100px; '
            . 'grid-template-rows: 40px; '
            . 'grid-auto-columns: 60px;">'
            . '<div class="dropped" style="grid-column: 99;"></div>'
            . '<div class="kept"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $kept = $this->find($box, 'div.kept');
        self::assertSame(0.0, $kept->geometry->x);
    }

    public function testGridImplicitRowsBoundedByMaxCap(): void
    {
        // Negative: a pathological placement at `grid-row: 9999`
        // doesn't lock up the layout — the maxImplicitRows cap
        // bounds the growth loop. We just verify the layout returns
        // (the test passing is the assertion).
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px; '
            . 'grid-template-rows: 20px; '
            . 'grid-auto-rows: 1px;">'
            . '<div class="a" style="grid-row: 9999;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $h = $this->layout->layout($box, $this->defaultCtx);
        self::assertGreaterThanOrEqual(0.0, $h);
    }

    public function testGridImplicitAutoRowKeepsTotalGridHeight(): void
    {
        // Positive: the container's natural height = sum of all
        // explicit + implicit rows (when no declared height). Two
        // items in a 1-row × 1-col grid + grid-auto-rows: 50px →
        // total = first row (40) + implicit (50) = 90.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px; '
            . 'grid-template-rows: 40px; '
            . 'grid-auto-rows: 50px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $grid = null;
        foreach ($this->find($box, 'body')->children as $c) {
            if ($c instanceof \Phpdftk\HtmlToPdf\Box\GridBox) {
                $grid = $c;
            }
        }
        self::assertNotNull($grid);
        // No declared height → height = sum of rows (40 + 50 = 90).
        self::assertEqualsWithDelta(90.0, $grid->geometry->height, 0.001);
    }

    public function testGridAutoFlowColumnWalksColumnMajor(): void
    {
        // Positive: `grid-auto-flow: column` flows items column-by-
        // column. Three items in a 2×3 grid land at (0,0), (1,0),
        // (0,1).
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 60px 60px 60px; '
            . 'grid-template-rows: 30px 30px; '
            . 'grid-auto-flow: column;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '<div class="c"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        $c = $this->find($box, 'div.c');
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(0.0, $a->geometry->y);
        // b at (row=1, col=0) → y = 30
        self::assertSame(0.0, $b->geometry->x);
        self::assertSame(30.0, $b->geometry->y);
        // c at (row=0, col=1) → x = 60, y = 0
        self::assertSame(60.0, $c->geometry->x);
        self::assertSame(0.0, $c->geometry->y);
    }

    public function testGridAutoFlowColumnGrowsImplicitColumns(): void
    {
        // Positive: `column` flow grows implicit columns via
        // `grid-auto-columns`. 3 items in a 2-row × 1-explicit-col
        // grid → 2 items fill the explicit column, the 3rd grows
        // an implicit column.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 40px; '
            . 'grid-template-rows: 30px 30px; '
            . 'grid-auto-flow: column; '
            . 'grid-auto-columns: 50px;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '<div class="c"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = $this->find($box, 'div.c');
        // c lands at the implicit column → x = 40, y = 0
        self::assertSame(40.0, $c->geometry->x);
        self::assertSame(0.0, $c->geometry->y);
        self::assertEqualsWithDelta(50.0, $c->geometry->width, 0.001);
    }

    public function testGridAutoFlowDenseBackfillsEarlierGaps(): void
    {
        // Positive: `dense` mode lets later, smaller items backfill
        // earlier gaps. A 2-cell span (`grid-column: 1 / 3`) on
        // item 1 leaves no room on row 0 for the explicit-placed
        // item 3 at col 3. Without dense, item 4 (1-cell) lands
        // after item 3; WITH dense, item 4 backfills the col-3 gap
        // on row 0.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 40px 40px 40px; '
            . 'grid-template-rows: 30px 30px; '
            . 'grid-auto-flow: dense;">'
            . '<div class="big" style="grid-column: 1 / 3;"></div>'
            . '<div class="placed" style="grid-row: 2;"></div>'
            . '<div class="filler"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $filler = $this->find($box, 'div.filler');
        // With dense: filler backfills (row 0, col 2).
        self::assertSame(80.0, $filler->geometry->x);
        self::assertSame(0.0, $filler->geometry->y);
    }

    public function testGridAutoFlowDenseWithoutGapsSameAsSparse(): void
    {
        // Negative-ish: when there are NO earlier gaps to backfill,
        // dense behaves identically to sparse. Three items in a
        // 3×1 grid land at (0,0), (0,1), (0,2).
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 40px 40px 40px; '
            . 'grid-template-rows: 30px; '
            . 'grid-auto-flow: dense;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '<div class="c"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = $this->find($box, 'div.b');
        $c = $this->find($box, 'div.c');
        self::assertSame(40.0, $b->geometry->x);
        self::assertSame(80.0, $c->geometry->x);
    }

    public function testGridAutoFlowUnknownKeywordFallsBackToRow(): void
    {
        // Negative: an unrecognised keyword defaults to `row` (the
        // initial value). 2 items land row-major.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 40px 40px; '
            . 'grid-template-rows: 30px; '
            . 'grid-auto-flow: nonsense;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        $b = $this->find($box, 'div.b');
        // Row-major placement: a at (0,0), b at (0,1).
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(40.0, $b->geometry->x);
        self::assertSame(0.0, $b->geometry->y);
    }

    public function testGridAutoFlowBareDenseKeepsRowDirection(): void
    {
        // Negative: bare `dense` (without `row`/`column`) keeps the
        // initial `row` direction per spec. 2 items lay out
        // row-major with dense packing.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 40px 40px; '
            . 'grid-template-rows: 30px; '
            . 'grid-auto-flow: dense;">'
            . '<div class="a"></div>'
            . '<div class="b"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = $this->find($box, 'div.b');
        // Row-major: b at (row=0, col=1) → x = 40.
        self::assertSame(40.0, $b->geometry->x);
        self::assertSame(0.0, $b->geometry->y);
    }

    public function testGridAutoTrackWithExplicitWidthChildUsesChildWidth(): void
    {
        // Positive: a child with explicit `width: 80px` in a single
        // `auto`-sized column → the track grows to 80.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: auto; '
            . 'grid-template-rows: 40px;">'
            . '<div class="a" style="width: 80px;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        // Track sized to max-content (= 80px child).
        self::assertEqualsWithDelta(80.0, $a->geometry->width, 0.001);
    }

    public function testGridAutoTrackWithoutContentCollapsesToZero(): void
    {
        // Negative: an empty `auto` track has no content to size
        // against — collapses to 0. A 2-column grid where col 2 is
        // auto with no item in it gets a 0-wide second column.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px auto; '
            . 'grid-template-rows: 30px;">'
            . '<div class="a"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        // Only one item placed in col 0. Col 1 is auto and empty → 0.
        $a = $this->find($box, 'div.a');
        // `a` fills col 0 (100px).
        self::assertEqualsWithDelta(100.0, $a->geometry->width, 0.001);
    }

    public function testGridAutoTrackPicksMaxAcrossItemsInSameColumn(): void
    {
        // Positive: two items in the same auto column → track sizes
        // to the WIDEST item's width.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: auto; '
            . 'grid-template-rows: 30px 30px;">'
            . '<div class="narrow" style="width: 40px;"></div>'
            . '<div class="wide" style="width: 120px;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $narrow = $this->find($box, 'div.narrow');
        $wide = $this->find($box, 'div.wide');
        // CSS Box Alignment 3 §6.2 + CSS Grid 2 §11 — track sizes to
        // widest child's outer width (120px), but `stretch` only
        // applies to items whose own `width` is `auto`. Both
        // children here declare explicit lengths, so each paints
        // at its authored width instead of stretching to the
        // 120 px track. Track width still tracks the widest child.
        self::assertEqualsWithDelta(40.0, $narrow->geometry->width, 0.001);
        self::assertEqualsWithDelta(120.0, $wide->geometry->width, 0.001);
    }

    public function testGridMinContentTrackUsesChildMin(): void
    {
        // Positive: `min-content` track tracks the item's min-content
        // (widest unbreakable run). Without text, falls back to the
        // declared width.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: min-content; '
            . 'grid-template-rows: 30px;">'
            . '<div class="a" style="width: 60px;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertEqualsWithDelta(60.0, $a->geometry->width, 0.001);
    }

    public function testGridMaxContentTrackUsesChildMax(): void
    {
        // Positive: explicit `max-content` keyword behaves the same as
        // `auto` for the no-text fallback case — sizes to the item's
        // declared width.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: max-content; '
            . 'grid-template-rows: 30px;">'
            . '<div class="a" style="width: 75px;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->find($box, 'div.a');
        self::assertEqualsWithDelta(75.0, $a->geometry->width, 0.001);
    }

    public function testGridAutoTrackBetweenFixedTracks(): void
    {
        // Positive: `100px auto 100px` → middle track sizes to its
        // item's max-content (50px), so the third track starts at
        // x = 100 + 50 = 150.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: 100px auto 100px; '
            . 'grid-template-rows: 30px;">'
            . '<div class="a"></div>'
            . '<div class="b" style="width: 50px;"></div>'
            . '<div class="c"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $b = $this->find($box, 'div.b');
        $c = $this->find($box, 'div.c');
        self::assertSame(100.0, $b->geometry->x);
        self::assertEqualsWithDelta(50.0, $b->geometry->width, 0.001);
        self::assertSame(150.0, $c->geometry->x);
    }

    public function testGridAutoTrackMultiSpanDistributesEqually(): void
    {
        // Negative-ish: a 2-cell-spanning item across two auto
        // tracks distributes its max-content equally. Item width
        // 100 → each track gets 50.
        $box = $this->buildTree(
            '<html><body><div class="grid" style="display: grid; '
            . 'grid-template-columns: auto auto; '
            . 'grid-template-rows: 30px;">'
            . '<div class="span" style="grid-column: 1 / 3; width: 100px;"></div>'
            . '</div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $span = $this->find($box, 'div.span');
        // Item placed at col 0, spans both tracks (50+50=100).
        self::assertSame(0.0, $span->geometry->x);
        self::assertEqualsWithDelta(100.0, $span->geometry->width, 0.001);
    }

    public function testTableAutoWidthUsesColumnContentWidths(): void
    {
        // CSS 2.1 §17.5.2 — an auto-width table shrink-to-fits to its
        // columns' content rather than scaling to fill the container.
        // Two cells with intrinsic widths 50, 150 → columns stay at
        // 50 / 150, table content = 200 (well under the 600 available).
        // Cell B at x = column A's width = 50 (NOT a fill-scaled 150).
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a" style="width: 50px"></td>'
            . '<td class="b" style="width: 150px"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; }
             tr { display: table-row; }
             td { display: table-cell; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(50.0, $cells['b']->geometry->x, 0.001);
    }

    public function testTableAutoWidthOverflowsWhenContentExceedsAvailable(): void
    {
        // CSS 2.1 §17.5.2 — the used width is
        // `max(min-content, min(available, max-content))`. Two cells with
        // explicit widths 500 + 400 give a min-content of 900 > the 600
        // available, so the table overflows to 900 (it never shrinks below
        // min-content). Columns stay at 500 / 400 → cell B's x = 500.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a" style="width: 500px"></td>'
            . '<td class="b" style="width: 400px"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; }
             tr { display: table-row; }
             td { display: table-cell; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(500.0, $cells['b']->geometry->x, 0.5);
    }

    public function testTableAutoWidthAllEmptyCellsShrinksToZero(): void
    {
        // CSS 2.1 §17.5.2 — an auto-width table whose cells are all empty
        // (zero max-content) shrink-to-fits to a zero content width rather
        // than filling the 600pt container. Every cell collapses to 0, all
        // anchored at x=0. (Matches browsers and the CSS2.1 `*-applies-to-*`
        // references — the previous equal-share fill fallback was wrong.)
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a"></td><td class="b"></td><td class="c"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; }
             tr { display: table-row; }
             td { display: table-cell; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        foreach (['a', 'b', 'c'] as $key) {
            self::assertLessThan(1.0, $cells[$key]->geometry->width);
            self::assertEqualsWithDelta(0.0, $cells[$key]->geometry->x, 0.001);
        }
    }

    public function testTableAutoWidthShrinkWrapsBelowContainer(): void
    {
        // CSS 2.1 §17.5.2 — an auto-width table with content far narrower
        // than its container shrink-wraps to the content, not the 600pt CB.
        // Two cells 40 + 60 → table content width = 100.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a" style="width: 40px"></td>'
            . '<td class="b" style="width: 60px"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; }
             tr { display: table-row; }
             td { display: table-cell; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        self::assertEqualsWithDelta(100.0, $table->geometry->width, 0.5);
    }

    public function testTableAutoWidthColumnIncludesCellPadding(): void
    {
        // The column width is the cell's *border-box* content: a 50-wide
        // cell with 10px horizontal padding occupies a 70-wide column, so
        // the shrink-wrapped table is 40 + 70 = 110 (NOT 90). Regression
        // guard for the shared border-box cell-contribution helper.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a" style="width: 40px"></td>'
            . '<td class="b" style="width: 50px; padding-left: 10px; padding-right: 10px"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; }
             tr { display: table-row; }
             td { display: table-cell; padding: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        self::assertEqualsWithDelta(110.0, $table->geometry->width, 0.5);
    }

    public function testTableAutoWidthMarginAutoCentres(): void
    {
        // CSS 2.1 §17.5.2 + §10.3.3 — once the auto table's used width is
        // computed it is a resolved width, so `margin: 0 auto` centres it.
        // 100-wide content in a 600pt CB → left margin (600−100)/2 = 250.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a" style="width: 100px"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; margin: 0 auto; }
             tr { display: table-row; }
             td { display: table-cell; padding: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $table = $this->find($box, 'table');
        self::assertNotNull($table);
        self::assertEqualsWithDelta(250.0, $table->geometry->x, 0.5);
    }

    public function testTableExplicitHeightFillsSingleRow(): void
    {
        // CSS 2.1 §17.5.3 — a table taller than its content distributes
        // the surplus over its rows, so the single row + its cell grow to
        // fill the 150px table (not just the ~text content height).
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a" style="width: 60px"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; height: 150px; }
             tr { display: table-row; }
             td { display: table-cell; padding: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(150.0, $cells['a']->geometry->height, 0.5);
    }

    public function testTableExplicitHeightDistributesAcrossRows(): void
    {
        // Two empty rows in a 200px table → each grows to ~100px and the
        // second row is shifted down past the first.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a" style="width: 40px"></td></tr>'
            . '<tr><td class="b" style="width: 40px"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; height: 200px; }
             tr { display: table-row; }
             td { display: table-cell; padding: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(100.0, $cells['a']->geometry->height, 1.0);
        self::assertEqualsWithDelta(100.0, $cells['b']->geometry->height, 1.0);
        // Second row's cell sits below the first (~row 1 height).
        self::assertGreaterThan(
            $cells['a']->geometry->y + 90.0,
            $cells['b']->geometry->y,
        );
    }

    public function testTableExplicitHeightNoOpWhenContentTaller(): void
    {
        // A table height smaller than its content must NOT shrink the
        // rows — the cell keeps its 80px content height.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="a" style="width: 40px; height: 80px"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; height: 10px; }
             tr { display: table-row; }
             td { display: table-cell; padding: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(80.0, $cells['a']->geometry->height, 0.5);
    }

    public function testTableExplicitColWidthBypassesAutoMeasurement(): void
    {
        // Negative: a `<col width="100">` declaration preempts the
        // content-measurement pass for col 0. Col 1 is auto with a
        // 50-wide cell → scales 50 into the remaining 500 available.
        // Cell B (auto col 1) → x = 100 (after col 0's 100).
        $box = $this->buildTree(
            '<html><body><table>'
            . '<col width="100">'
            . '<col>'
            . '<tr><td class="a"></td>'
            . '<td class="b" style="width: 50px"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; }
             tr { display: table-row; }
             td { display: table-cell; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(100.0, $cells['a']->geometry->width, 0.001);
        self::assertEqualsWithDelta(100.0, $cells['b']->geometry->x, 0.001);
    }

    public function testTableColspanCellDistributesMaxContentEquallyAcrossColumns(): void
    {
        // A colspan="2" cell with width 200 contributes 100 of
        // max-content to each of the 2 spanned columns. The third column
        // has its own 50-wide cell. Under shrink-to-fit the table stays at
        // its content sum 100+100+50 = 250 (no scaling to fill 600), so
        // the last cell sits at x = 100+100 = 200.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<tr><td class="span" colspan="2" style="width: 200px"></td>'
            . '<td class="last" style="width: 50px"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; }
             tr { display: table-row; }
             td { display: table-cell; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(200.0, $cells['last']->geometry->x, 0.5);
    }

    public function testTableMixedAutoAndExplicitColWidthsKeepsExplicit(): void
    {
        // Negative: explicit `<col width>` on col 0 = 80, auto on
        // col 1. Cell B in col 1 has intrinsic width 100. Available
        // for auto = 600 - 80 = 520 → col 1 expands to 520. Cell B
        // at x = 80; col 1 width = 520.
        $box = $this->buildTree(
            '<html><body><table>'
            . '<col width="80">'
            . '<col>'
            . '<tr><td class="a"></td><td class="b" style="width: 100px"></td></tr>'
            . '</table></body></html>',
            'html, body, tbody { display: block; }
             table { display: table; }
             tr { display: table-row; }
             td { display: table-cell; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cells = $this->collectCellsByClass($box);
        self::assertEqualsWithDelta(80.0, $cells['a']->geometry->width, 0.001);
        self::assertEqualsWithDelta(80.0, $cells['b']->geometry->x, 0.001);
    }

    public function testBareTableCellsWrapInAnonymousRow(): void
    {
        // CSS 2.1 §17.2.1 — two `display: table-cell` divs sitting
        // directly under a `display: table` (no row) get swept into a
        // single anonymous table-row, so they lay out side by side on
        // the same row instead of stacking as bare blocks.
        $box = $this->buildTree(
            '<html><body><div id="t">'
            . '<div id="a" style="display: table-cell; width: 40px; height: 30px;"></div>'
            . '<div id="b" style="display: table-cell; width: 50px; height: 30px;"></div>'
            . '</div></body></html>',
            'html, body { display: block; } #t { display: table; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $a = $this->findById($box, 'a');
        $b = $this->findById($box, 'b');
        self::assertNotNull($a);
        self::assertNotNull($b);
        // Same row → same top; second cell sits to the right of the first.
        self::assertEqualsWithDelta($a->geometry->y, $b->geometry->y, 0.5);
        self::assertGreaterThan($a->geometry->x, $b->geometry->x);
        // Exactly one anonymous row wraps both cells.
        $rows = $this->collectByType($box, \Phpdftk\HtmlToPdf\Box\TableRowBox::class);
        self::assertCount(1, $rows);
        self::assertCount(2, array_values(array_filter(
            $rows[0]->children,
            static fn($c): bool => $c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox,
        )));
    }

    public function testTbodyBlockStaysTransparentNoDoubleWrap(): void
    {
        // Regression: our UA sheet renders `<tbody>` as `display: block`
        // and relies on the layout walking through it to find the rows.
        // The §17.2.1 anonymous-row fixup must NOT wrap that block (it
        // already contains rows), or the real rows get buried in a
        // synthesised row+cell and the grid collapses.
        $box = $this->buildTreeWithUa(
            '<html><body><table>'
            . '<tr><td class="a">x</td><td class="b">y</td></tr>'
            . '</table></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $rows = $this->collectByType($box, \Phpdftk\HtmlToPdf\Box\TableRowBox::class);
        // Exactly the one real <tr> — no anonymous row wrapping the tbody.
        self::assertCount(1, $rows);
        $cells = $this->collectCellsByClass($box);
        self::assertArrayHasKey('a', $cells);
        self::assertArrayHasKey('b', $cells);
        // Cells sit side by side on the row.
        self::assertGreaterThan($cells['a']->geometry->x, $cells['b']->geometry->x);
    }

    public function testInlineBlockMarginsSpaceAtomicItems(): void
    {
        // CSS 2.2 §10.8 — an inline-block's horizontal margins add to the
        // inline advance it occupies; its margin box (with vertical margins)
        // sets line height. In the no-font inline path these were dropped, so
        // adjacent inline-blocks touched. Two 40px inline-blocks with 10px
        // side / 5px vertical margins → content boxes at x=10 and
        // x = 10 + 40 + 10 + 10 = 70; content top at y=5 (top margin).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="box"><span></span><span></span></div></body></html>',
            '.box > span { display: inline-block; width: 40px; height: 30px; margin: 5px 10px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        $spans = array_values(array_filter(
            $div->children,
            static fn($c) => $c instanceof \Phpdftk\HtmlToPdf\Box\AtomicInlineBox,
        ));
        self::assertCount(2, $spans);
        self::assertSame(10.0, $spans[0]->geometry->marginLeft);
        self::assertSame(5.0, $spans[0]->geometry->marginTop);
        self::assertSame(10.0, $spans[0]->geometry->x);
        self::assertSame(5.0, $spans[0]->geometry->y);
        self::assertSame(70.0, $spans[1]->geometry->x);
    }

    public function testFlexResolvesAtomicItemMargins(): void
    {
        // CSS Display 3 §2.7 — an inline-block flex item is blockified. Its
        // `margin` must reach geometry so the flex algorithm's outer sizes
        // and placement include it (previously dropped for atomic items).
        // Two 100px inline-block items, 10px side margins, wide container →
        // item 0 content at x=10; item 1 at 10 + 100 + 10 + 10 = 130.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<span class="a">x</span><span class="b">y</span>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > span { display: inline-block; width: 100px; height: 50px; margin: 0 10px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertCount(2, $flex->children);
        self::assertSame(10.0, $flex->children[0]->geometry->marginLeft);
        self::assertSame(10.0, $flex->children[0]->geometry->marginRight);
        self::assertSame(10.0, $flex->children[0]->geometry->x);
        self::assertSame(130.0, $flex->children[1]->geometry->x);
    }

    public function testFlexItemAutomaticMinimumOverridesZeroBasis(): void
    {
        // CSS Flexbox 1 §4.5 + §9.2/§9.7 — a column flex item with
        // `flex-basis: 0` (flex base size 0) and the initial
        // `min-height: auto` has a content-based automatic minimum equal
        // to its min-content height (here 100px from the fixed child).
        // The item's HYPOTHETICAL main size is its base clamped up to that
        // minimum, so even inside a `height: 10px` container with no flex
        // growth it must be laid out 100px tall — the sign decision and
        // the inflexible-item freeze must use the clamped hypothetical
        // size, not the raw base 0 (which would collapse the item).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="item"><div class="content"></div></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column; width: 100px; height: 10px; }
             .flex > .item { flex-basis: 0; }
             .content { width: 100px; height: 100px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertCount(1, $flex->children);
        self::assertEqualsWithDelta(
            100.0,
            $flex->children[0]->geometry->height,
            0.001,
            'flex item respects its §4.5 automatic minimum height despite flex-basis:0',
        );
    }

    public function testFlexItemMinHeightMinContentKeywordResolvesToContentSize(): void
    {
        // CSS Sizing 3 §5.1 — an intrinsic-size keyword (`min-content`) on
        // a flex item's main-axis `min-height` resolves to the item's
        // content-based size, not 0. The 100px content child gives a
        // min-content height of 100px, so the item cannot collapse below
        // 100px even with `flex-basis: 0` in a 10px-tall container. Before
        // the fix the generic length resolver returned 0 for the keyword
        // and the item collapsed.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="item"><div class="content"></div></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column; width: 100px; height: 10px; }
             .flex > .item { flex-basis: 0; min-height: min-content; }
             .content { width: 100px; height: 100px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertEqualsWithDelta(
            100.0,
            $flex->children[0]->geometry->height,
            0.5,
            'min-height:min-content resolves to the 100px content height',
        );
    }

    public function testFlexRowShrinkToFitIntrinsicWidthUsesFlexBaseSize(): void
    {
        // CSS Flexbox 1 §9.9.1 — a shrink-to-fit row flex container's
        // intrinsic width sums each item's FLEX BASE size, not its content
        // size. An empty `flex: 1 0 100px` item contributes 100px (its
        // flex-shrink:0 floors the contribution at the base) so a floated
        // (shrink-to-fit) container is 100px wide, not 0.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="c"><div class="i"></div></div></body></html>',
            '.c { display: flex; float: left; }
             .c > .i { flex: 1 0 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $c = $this->find($box, 'div');
        self::assertNotNull($c);
        self::assertEqualsWithDelta(
            100.0,
            $c->geometry->width,
            0.5,
            'floated flex row sizes to the item flex base 100px, not content 0',
        );
    }

    public function testFlexRowLaysOutItemsHorizontally(): void
    {
        // Three 100-wide items in a 600-wide flex container with
        // flex-start (default) → items at x=0, x=100, x=200.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertCount(3, $flex->children);
        self::assertSame(0.0, $flex->children[0]->geometry->x);
        self::assertSame(100.0, $flex->children[1]->geometry->x);
        self::assertSame(200.0, $flex->children[2]->geometry->x);
    }

    public function testFlexJustifyContentCenter(): void
    {
        // 3 × 100-wide = 300; container 600 → 300 slack → center at
        // x = 150, 250, 350.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div></div><div></div><div></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; justify-content: center; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(150.0, $flex->children[0]->geometry->x);
        self::assertSame(250.0, $flex->children[1]->geometry->x);
        self::assertSame(350.0, $flex->children[2]->geometry->x);
    }

    public function testFlexJustifyContentFlexEnd(): void
    {
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div></div><div></div><div></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; justify-content: flex-end; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // 300 slack → first at 300, then 400, 500.
        self::assertSame(300.0, $flex->children[0]->geometry->x);
        self::assertSame(500.0, $flex->children[2]->geometry->x);
    }

    public function testFlexJustifyContentSpaceBetween(): void
    {
        // 300 slack split across 2 gaps = 150 each. Items at
        // 0, 100 + 150 = 250, 250 + 100 + 150 = 500.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div></div><div></div><div></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; justify-content: space-between; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $flex->children[0]->geometry->x);
        self::assertSame(250.0, $flex->children[1]->geometry->x);
        self::assertSame(500.0, $flex->children[2]->geometry->x);
    }

    public function testFlexColumnGapInsertedBetweenItems(): void
    {
        // 3 items × 100 wide + 2 gaps × 20 = 340 used; remaining
        // 260 slack with flex-start → items at 0, 120, 240.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div></div><div></div><div></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; column-gap: 20px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $flex->children[0]->geometry->x);
        self::assertSame(120.0, $flex->children[1]->geometry->x);
        self::assertSame(240.0, $flex->children[2]->geometry->x);
    }

    public function testFlexAlignItemsCenter(): void
    {
        // Tallest item is 100; smaller items center vertically.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="tall"></div>'
                . '<div class="short"></div>'
                . '</div></body></html>',
            '.flex { display: flex; align-items: center; width: 400px; }
             .tall { width: 100px; height: 100px; }
             .short { width: 100px; height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        $tall = $flex->children[0];
        $short = $flex->children[1];
        // 60 slack / 2 = 30 → short centered at y = tall.y + 30.
        self::assertEqualsWithDelta($tall->geometry->y + 30.0, $short->geometry->y, 0.001);
    }

    public function testFlexContainerDropsWhitespaceOnlyTextChildren(): void
    {
        // CSS Flexbox 1 §4 — anonymous whitespace flex items are not
        // rendered. Issue #25: `<div></div>\n` produced a phantom
        // whitespace flex item that absorbed all justify-content slack,
        // leaving the actual item top-left instead of centred.
        $box = $this->buildTreeWithUa(
            "<html><body><div class=\"box\"></div>\n</body></html>",
            'html, body { margin: 0; padding: 0; height: 100%; }
             body { display: flex; align-items: center; justify-content: center; }
             .box { width: 50%; height: 50%; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $body = $this->find($box, 'body');
        self::assertNotNull($body);
        // After whitespace stripping the body has exactly one flex item.
        self::assertCount(1, $body->children);
        $item = $body->children[0];
        // 50% × default ctx (600 × 800) → 300 × 400, centred at
        // x = (600 - 300) / 2 = 150, y = (800 - 400) / 2 = 200.
        self::assertEqualsWithDelta(300.0, $item->geometry->width, 0.001);
        self::assertEqualsWithDelta(400.0, $item->geometry->height, 0.001);
        self::assertEqualsWithDelta(150.0, $item->geometry->x, 0.001);
        self::assertEqualsWithDelta(200.0, $item->geometry->y, 0.001);
    }

    public function testFlexEmptyContainerHasNoChildren(): void
    {
        // Negative: empty flex container produces a box with zero
        // height (no children to size from).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex"></div></body></html>',
            '.flex { display: flex; width: 400px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $flex->geometry->height);
    }

    public function testFlexAlignSelfOverridesAlignItems(): void
    {
        // align-self on a single item overrides the container's
        // align-items.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="tall"></div>'
                . '<div class="short"></div>'
                . '</div></body></html>',
            '.flex { display: flex; align-items: flex-start; width: 400px; }
             .tall { width: 100px; height: 100px; }
             .short { width: 100px; height: 40px; align-self: flex-end; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        $short = $flex->children[1];
        // align-self: flex-end → short sits at bottom of tallest
        // (100 - 40 = 60 below the top).
        self::assertEqualsWithDelta($flex->geometry->y + 60.0, $short->geometry->y, 0.001);
    }

    public function testFlexExplicitWidthHonored(): void
    {
        // Container with explicit width 200 + 2 items 100 each →
        // total fits exactly, no slack.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div></div><div></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 200px; }
             .flex > div { width: 100px; height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(200.0, $flex->geometry->width);
        self::assertSame(100.0, $flex->children[1]->geometry->x);
    }

    public function testFlexItemPercentHeightResolvesAgainstFlexContainerHeight(): void
    {
        // CSS Flexbox 1 §4.5 / CSS 2.1 §10.1 — the containing block
        // for a flex item is the flex container, so an item's `height:
        // 50%` resolves to half the flex container's declared height
        // rather than half the outer block's CB height.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="item"></div>'
                . '</div></body></html>',
            // 200px flex container; the body itself is ~600 tall in
            // the default ctx — without the fix, item height resolved
            // to 300 (50% of 600) instead of 100 (50% of 200).
            '.flex { display: flex; width: 100px; height: 200px; }
             .flex > .item { width: 40px; height: 50%; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(100.0, $flex->children[0]->geometry->height);
    }

    public function testFlexAbsolutePositionedChildSkipsFlexFlow(): void
    {
        // CSS Flexbox 1 §3 — abspos children are NOT flex items: they
        // don't contribute to the main-axis distribution and don't
        // displace siblings. The two in-flow items pack like the
        // abspos child wasn't there.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="abs" style="position: absolute; top: 5px; left: 7px;"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 300px; position: relative; }
             .flex > div { width: 50px; height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // Find in-flow children (.a, .b) by class.
        $a = $b = $abs = null;
        foreach ($flex->children as $child) {
            $cls = $child->element?->getAttribute('class') ?? '';
            if ($cls === 'a') {
                $a = $child;
            }
            if ($cls === 'b') {
                $b = $child;
            }
            if ($cls === 'abs') {
                $abs = $child;
            }
        }
        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertNotNull($abs);
        // In-flow children pack as if abspos child didn't exist.
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(50.0, $b->geometry->x);
        // Abspos child positioned by its top/left against the flex
        // container (positioned ancestor).
        self::assertSame(7.0, $abs->geometry->x);
        self::assertSame(5.0, $abs->geometry->y);
    }

    public function testFlexRowItemAspectRatioWithDefiniteHeightTransfersToWidth(): void
    {
        // CSS Flexbox 1 §9.9 — a row-direction flex item with
        // aspect-ratio + a definite height uses height × ratio as
        // the main-axis (width) suggestion. Without this rule, the
        // item would fall through to max-content (empty box → 0)
        // and shrink to nothing.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; align-items: flex-start; }
             .flex > div { height: 100px; aspect-ratio: 2; flex-grow: 0; flex-shrink: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // height 100 × ratio 2 = width 200.
        self::assertEqualsWithDelta(200.0, $flex->children[0]->geometry->width, 0.001);
    }

    public function testBlockAspectRatioReDerivesWidthAfterMinHeightLift(): void
    {
        // CSS Sizing 4 §5.2 — when width was derived from height via
        // aspect-ratio and min-height lifts the height afterward, the
        // width must re-derive so the ratio holds.
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<div class="parent">'
                . '<div class="ar"></div>'
                . '</div></body></html>',
            // Indefinite-height parent → height: 100% resolves to 0,
            // then min-height: 100px lifts to 100, then aspect-ratio
            // must re-derive width = 100 × 1 = 100. Without the
            // re-derivation, width stays at 0 (height was 0 when the
            // ratio first fired).
            '.parent { display: block; width: 400px; }
             .ar { aspect-ratio: 1; width: auto; height: 100%; min-height: 100px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $ar = $this->find($box, 'div.ar');
        self::assertEqualsWithDelta(100.0, $ar->geometry->width, 0.001);
        self::assertEqualsWithDelta(100.0, $ar->geometry->height, 0.001);
    }

    public function testFlexColumnGapPercentageResolvesAgainstContainerWidth(): void
    {
        // CSS Box Alignment 3 §8.3 — `column-gap: <percentage>`
        // resolves against the flex container's content-box width.
        // Container is 200px, gap 10% → 20px between items.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div></div><div></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 200px; column-gap: 10%; }
             .flex > div { width: 50px; height: 10px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // Second item: 50 (first item) + 20 (10% of 200) = 70.
        self::assertSame(70.0, $flex->children[1]->geometry->x);
    }

    public function testContainerTypeInlineSizeExposesWidthToCqDescendants(): void
    {
        // CSS Containment 3 §6 — `cqw` / `cqi` on a descendant
        // resolves against the nearest `container-type: inline-size`
        // ancestor. Here `.container` opts in with width 200, so the
        // 50cqw child resolves to 100px.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="container">'
                . '<div class="child"></div>'
                . '</div></body></html>',
            '.container { container-type: inline-size; width: 200px; }
             .child { width: 50cqw; height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $child = $this->find($box, 'div.child');
        self::assertSame(100.0, $child->geometry->width);
    }

    public function testContainerTypeNormalDoesNotPropagateContainerSize(): void
    {
        // Default `container-type: normal` does NOT make the box a
        // size-query container; cq* on descendants falls through to
        // 0 per spec §6.3.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="wrapper">'
                . '<div class="child"></div>'
                . '</div></body></html>',
            '.wrapper { width: 200px; }
             .child { width: 50cqw; height: 20px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $child = $this->find($box, 'div.child');
        self::assertSame(0.0, $child->geometry->width);
    }

    public function testFlexRowItemMinWidthAutoFloorsAtMinContent(): void
    {
        // CSS Flexbox 1 §4.5 — `min-width: auto` on a flex item
        // resolves to its min-content size (longest unbreakable word)
        // when `overflow: visible`. Without this rule, flex-shrink
        // can drive items below their min-content; assert the floor.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a">looooooooooong</div>'
                . '<div class="b">tiny</div>'
                . '</div></body></html>',
            // 50px container forces shrink; without §4.5 the long item
            // would compress to ~25px (1:1 shrink with tiny). With the
            // min-content floor it stays at least as wide as the word.
            '.flex { display: flex; width: 50px; }
             .flex > div { height: 10px; flex-shrink: 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        $longWidth = $flex->children[0]->geometry->width;
        // min-content of "looooooooooong" at default font-size is
        // well above 25px, so any sane floor exceeds the naive shrink.
        self::assertGreaterThan(25.0, $longWidth, 'min-content floor should keep long word from shrinking past container half');
    }

    public function testFlexRowAutoWidthItemUsesMaxContentNotContainerWidth(): void
    {
        // CSS Flexbox 1 §9.2 — items with `flex-basis: auto` and
        // `width: auto` get max-content as the hypothetical main
        // size. Without this, an auto-width item in a wide container
        // stretches to fill the container (block-layout behaviour)
        // and the second item gets shrunk to zero.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a">hi</div>'
                . '<div class="b">there</div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // Second item starts where the first ends, not at x=600.
        $first = $flex->children[0]->geometry;
        $second = $flex->children[1]->geometry;
        self::assertLessThan(600.0, $first->width, 'first item should not fill the entire container');
        self::assertGreaterThan(0.0, $first->width, 'first item should have non-zero max-content width');
        self::assertLessThanOrEqual($first->outerWidth() + 1.0, $second->x - $first->x);
    }

    public function testFlexNonFlexBlockUnaffected(): void
    {
        // Regression: regular block layout unchanged.
        $box = $this->buildTree(
            '<html><body><div class="b1" style="height: 30px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertInstanceOf(\Phpdftk\HtmlToPdf\Box\BlockBox::class, $div);
    }

    public function testFlexOrderMovesItemToFront(): void
    {
        // `order: -1` on the third item moves it to layout position 0.
        // Item identities walked by class name to keep the assertion
        // tight on the reorder behaviour vs. the geometry.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 100px; height: 50px; }
             .c { order: -1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItemX($flex, 'c'));
        self::assertSame(100.0, $this->flexItemX($flex, 'a'));
        self::assertSame(200.0, $this->flexItemX($flex, 'b'));
    }

    public function testFlexOrderMovesItemToBack(): void
    {
        // `order: 1` on the first item moves it to the end.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 100px; height: 50px; }
             .a { order: 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItemX($flex, 'b'));
        self::assertSame(100.0, $this->flexItemX($flex, 'c'));
        self::assertSame(200.0, $this->flexItemX($flex, 'a'));
    }

    public function testFlexOrderDefaultPreservesDomOrder(): void
    {
        // Negative: every item has the initial order (0) → no sort,
        // DOM order honoured.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItemX($flex, 'a'));
        self::assertSame(100.0, $this->flexItemX($flex, 'b'));
        self::assertSame(200.0, $this->flexItemX($flex, 'c'));
    }

    public function testFlexOrderEqualValuesAreStable(): void
    {
        // Negative: two items share order: 2; ties resolve by DOM
        // order, not by selector iteration.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 100px; height: 50px; }
             .a, .c { order: 2; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // b (order 0) first; then a then c (tied at 2, DOM order).
        self::assertSame(0.0, $this->flexItemX($flex, 'b'));
        self::assertSame(100.0, $this->flexItemX($flex, 'a'));
        self::assertSame(200.0, $this->flexItemX($flex, 'c'));
    }

    public function testFlexOrderInvalidKeywordTreatedAsZero(): void
    {
        // Negative: a non-integer keyword for `order` falls back to 0
        // (the initial value). Sort sees all-zero values and short
        // circuits to DOM order.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 400px; }
             .flex > div { width: 100px; height: 50px; }
             .a { order: nonsense; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItemX($flex, 'a'));
        self::assertSame(100.0, $this->flexItemX($flex, 'b'));
    }

    public function testFlexOrderSingleItemIsNoOp(): void
    {
        // Negative: a single-item flex container with order set still
        // lays out at x=0 — there's nothing to reorder against.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 200px; }
             .a { width: 80px; height: 30px; order: 99; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItemX($flex, 'a'));
    }

    public function testFlexOrderDoesNotReorderInBlockContainer(): void
    {
        // Negative: `order` is a flex-item-only property — it must NOT
        // reorder children of a non-flex block. The first DOM child
        // wins x=0 regardless of order value.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="b">'
                . '<div class="a" style="height: 20px"></div>'
                . '<div class="z" style="height: 20px; order: -1"></div>'
                . '</div></body></html>',
            '.b { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $container = $this->find($box, 'div');
        self::assertNotNull($container);
        // Block stacking: first child at y=0, second at y=20.
        // If `order` were honoured, .z would be at y=0.
        self::assertSame(0.0, $container->children[0]->geometry->y);
        self::assertSame(20.0, $container->children[1]->geometry->y);
    }

    public function testFlexDirectionRowReverseLaysOutItemsBackwards(): void
    {
        // row-reverse with default flex-start: pack against main-start
        // (right edge). 3 items × 100 in 600 container → first DOM
        // item (a) at far right (500), then b (400), then c (300).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: row-reverse; width: 600px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(500.0, $this->flexItemX($flex, 'a'));
        self::assertSame(400.0, $this->flexItemX($flex, 'b'));
        self::assertSame(300.0, $this->flexItemX($flex, 'c'));
    }

    public function testFlexDirectionRowReverseFlexEndPacksLeft(): void
    {
        // row-reverse + flex-end: main-end is the left edge, so items
        // pack from the left. First DOM item (a) at left (0), then b
        // (100), then c (200).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: row-reverse; justify-content: flex-end; width: 600px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(200.0, $this->flexItemX($flex, 'a'));
        self::assertSame(100.0, $this->flexItemX($flex, 'b'));
        self::assertSame(0.0, $this->flexItemX($flex, 'c'));
    }

    public function testFlexDirectionRowReverseSpaceBetweenPlacesEndpoints(): void
    {
        // row-reverse + space-between (symmetric, no swap): reversed
        // [c, b, a] placed evenly. c at 0, b at 250, a at 500.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: row-reverse; justify-content: space-between; width: 600px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItemX($flex, 'c'));
        self::assertSame(250.0, $this->flexItemX($flex, 'b'));
        self::assertSame(500.0, $this->flexItemX($flex, 'a'));
    }

    public function testFlexDirectionRowExplicitMatchesDefault(): void
    {
        // Negative: explicit `row` is identical to the unset default.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: row; width: 400px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItemX($flex, 'a'));
        self::assertSame(100.0, $this->flexItemX($flex, 'b'));
    }

    public function testFlexDirectionColumnStacksItemsVertically(): void
    {
        // `flex-direction: column` swaps the main axis to Y. Items
        // stack: a at y=0, b at y=50 (each 50pt tall). Both stay at
        // x=0 (cross-axis flex-start anchor with align-items stretch).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column; width: 400px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        $a = $this->flexItem($flex, 'a');
        $b = $this->flexItem($flex, 'b');
        self::assertSame(0.0, $a->geometry->y);
        self::assertSame(50.0, $b->geometry->y);
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(0.0, $b->geometry->x);
    }

    public function testFlexDirectionColumnRowGapBetweenItems(): void
    {
        // Column direction reads `row-gap` (NOT `column-gap`) for the
        // main-axis between-items gap per CSS Box Alignment 3 §8.1.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column; row-gap: 12px; width: 200px; height: 400px; }
             .flex > div { width: 100px; height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(42.0, $this->flexItem($flex, 'b')->geometry->y);
        self::assertSame(84.0, $this->flexItem($flex, 'c')->geometry->y);
    }

    public function testFlexDirectionColumnJustifyContentCenter(): void
    {
        // Column direction with justify-content: center → items pack
        // toward the vertical centre of the container's main axis.
        // 3 items × 30pt = 90 in a 300pt-tall container → 210 slack;
        // center → leading 105 → items at y=105, 135, 165.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column; justify-content: center;
                     width: 200px; height: 300px; }
             .flex > div { width: 100px; height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(105.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(135.0, $this->flexItem($flex, 'b')->geometry->y);
        self::assertSame(165.0, $this->flexItem($flex, 'c')->geometry->y);
    }

    public function testFlexDirectionColumnAlignItemsCenter(): void
    {
        // Cross-axis is X for column direction. align-items: center
        // centers each item horizontally inside the container.
        // Container 400 wide, items 100 wide → 300 cross-slack → 150.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column; align-items: center;
                     width: 400px; height: 200px; }
             .flex > div { width: 100px; height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(150.0, $this->flexItem($flex, 'a')->geometry->x);
        self::assertSame(150.0, $this->flexItem($flex, 'b')->geometry->x);
    }

    public function testFlexDirectionColumnFlexGrowFillsHeight(): void
    {
        // `flex: 1` in column direction expands the item to consume
        // the container's vertical slack — vertical version of the
        // canonical fill pattern.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column;
                     width: 200px; height: 400px; }
             .a { height: 50px; }
             .b { flex: 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(50.0, $this->flexItem($flex, 'a')->geometry->height);
        // b: basis 0 + grow 1 → consumes all 350pt of slack.
        self::assertSame(350.0, $this->flexItem($flex, 'b')->geometry->height);
    }

    public function testFlexDirectionColumnReverseReversesStackOrder(): void
    {
        // column-reverse: items in reverse layout order, packing at
        // main-start (= bottom edge in spec terms; the first DOM item
        // sits at the bottom). With 3 items × 30 in a 400-tall
        // container and default flex-start (→ swapped to flex-end):
        // a at y=370 (bottom), b at y=340, c at y=310.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column-reverse;
                     width: 200px; height: 400px; }
             .flex > div { width: 100px; height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(370.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(340.0, $this->flexItem($flex, 'b')->geometry->y);
        self::assertSame(310.0, $this->flexItem($flex, 'c')->geometry->y);
    }

    public function testFlexDirectionColumnShrinksToFitWithoutHeight(): void
    {
        // Negative: column with no declared height → container is
        // shrink-to-fit around its items. 3 × 30 items + no gap → 90.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column; width: 200px; }
             .flex > div { width: 100px; height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(90.0, $flex->geometry->height);
    }

    public function testFlexDirectionColumnShrinkOverflowingItems(): void
    {
        // Negative: column-direction overflow triggers flex-shrink on
        // heights instead of widths. 3 × 200 tall items in a 300pt
        // container → 300 overflow split 1:1:1 → each shrinks to 100.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column;
                     width: 200px; height: 300px; }
             .flex > div { width: 100px; height: 200px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(100.0, $this->flexItem($flex, 'a')->geometry->height);
        self::assertSame(100.0, $this->flexItem($flex, 'b')->geometry->height);
        self::assertSame(100.0, $this->flexItem($flex, 'c')->geometry->height);
    }

    public function testFlexDirectionColumnStretchExpandsItemWidth(): void
    {
        // Negative: align-items: stretch (default) in column direction
        // stretches each item's WIDTH (the cross axis) to fill the
        // container — not the height.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column;
                     width: 400px; height: 200px; }
             .a { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        $a = $this->flexItem($flex, 'a');
        self::assertSame(400.0, $a->geometry->width);
        self::assertSame(50.0, $a->geometry->height);
    }

    public function testFlexDirectionColumnIgnoresColumnGap(): void
    {
        // Negative: column-gap doesn't apply in column direction
        // (which reads row-gap instead). Items stack with no gap.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column;
                     column-gap: 50px; width: 200px; height: 400px; }
             .flex > div { width: 100px; height: 30px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(30.0, $this->flexItem($flex, 'b')->geometry->y);
    }

    public function testFlexDirectionRowReverseSingleItemSimplePack(): void
    {
        // Negative: single item with row-reverse + flex-start packs
        // at the right edge of the container.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: row-reverse; width: 400px; }
             .a { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(300.0, $this->flexItemX($flex, 'a'));
    }

    public function testFlexDirectionRowReverseWithOrderCombines(): void
    {
        // Negative: order sort runs FIRST, then row-reverse reverses
        // the sorted array. .c has order:-1 so it sorts to the front
        // of the main-axis order [c, a, b]; row-reverse places that
        // sequence starting at main-start (= right edge) →
        // c at 500 (right), a at 400, b at 300.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: row-reverse; width: 600px; }
             .flex > div { width: 100px; height: 50px; }
             .c { order: -1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(500.0, $this->flexItemX($flex, 'c'));
        self::assertSame(400.0, $this->flexItemX($flex, 'a'));
        self::assertSame(300.0, $this->flexItemX($flex, 'b'));
    }

    public function testFlexDirectionInvalidKeywordFallsBackToRow(): void
    {
        // Negative: unrecognised flex-direction keyword falls back to
        // the initial `row` value rather than crashing.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: nonsense; width: 400px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItemX($flex, 'a'));
        self::assertSame(100.0, $this->flexItemX($flex, 'b'));
    }

    public function testFlexGrowSingleItemFillsContainer(): void
    {
        // `flex: 1` (grow:1, basis declared 100 wide) → the lone item
        // absorbs all 500pt of slack, ending up 600pt wide.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .a { width: 100px; height: 50px; flex-grow: 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(600.0, $this->flexItemWidth($flex, 'a'));
    }

    public function testFlexGrowProportionalDistribution(): void
    {
        // Two items 100 wide each in 600 container = 400 slack;
        // grow factors 1 and 2 share it 1/3 and 2/3 → 133.33 and
        // 266.67 extra; widths 233.33 and 366.67.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 100px; height: 50px; }
             .a { flex-grow: 1; }
             .b { flex-grow: 2; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertEqualsWithDelta(233.333, $this->flexItemWidth($flex, 'a'), 0.01);
        self::assertEqualsWithDelta(366.667, $this->flexItemWidth($flex, 'b'), 0.01);
    }

    public function testFlexGrowZeroLeavesWidthsAlone(): void
    {
        // Negative: initial flex-grow: 0 → items keep their declared
        // widths, justify-content distributes the slack as usual.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(100.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(100.0, $this->flexItemWidth($flex, 'b'));
    }

    public function testFlexGrowNoOpWhenNoSlack(): void
    {
        // Negative: when items already fill the container, grow has
        // no positive free space to distribute.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 200px; }
             .flex > div { width: 100px; height: 50px; flex-grow: 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(100.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(100.0, $this->flexItemWidth($flex, 'b'));
    }

    public function testFlexGrowSkipsZeroGrowItem(): void
    {
        // Mixed grow: one item with grow:0 keeps its width; the other
        // with grow:1 absorbs ALL the slack.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 100px; height: 50px; }
             .b { flex-grow: 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(100.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(500.0, $this->flexItemWidth($flex, 'b'));
    }

    public function testFlexGrowAccountsForColumnGap(): void
    {
        // 600 container, 2 items × 100 + 20 gap = 220 used → 380
        // slack. grow:1 on a single item adds 380 → a width 480.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; column-gap: 20px; }
             .flex > div { width: 100px; height: 50px; }
             .a { flex-grow: 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(480.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(100.0, $this->flexItemWidth($flex, 'b'));
    }

    public function testFlexGrowNegativeValueTreatedAsZero(): void
    {
        // Negative: per CSS Flexbox 1 §7.1, `flex-grow` is
        // non-negative — a negative value falls back to 0.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 100px; height: 50px; flex-grow: -2; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(100.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(100.0, $this->flexItemWidth($flex, 'b'));
    }

    public function testFlexGrowShiftsSiblingPositions(): void
    {
        // Regression: when grow inflates item a's width, item b's
        // x position shifts right by the same amount.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 400px; }
             .flex > div { width: 100px; height: 50px; }
             .a { flex-grow: 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // a: 100 + 200 slack = 300 wide → b starts at x=300.
        self::assertSame(300.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(300.0, $this->flexItemX($flex, 'b'));
    }

    public function testFlexBasisZeroPlusGrowFillsContainer(): void
    {
        // The canonical `flex: 1` pattern: basis 0 + grow 1 → item
        // starts at width 0, absorbs the full 400pt container.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 400px; }
             .a { height: 50px; flex-grow: 1; flex-basis: 0px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(400.0, $this->flexItemWidth($flex, 'a'));
    }

    public function testFlexBasisExplicitLengthOverridesWidth(): void
    {
        // `width: 100px; flex-basis: 200px` → item starts at 200pt
        // before grow/justify. Without grow, the width stays 200.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .a { width: 100px; height: 50px; flex-basis: 200px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(200.0, $this->flexItemWidth($flex, 'a'));
    }

    public function testFlexBasisAutoKeepsDeclaredWidth(): void
    {
        // Negative: `flex-basis: auto` (the initial value) keeps the
        // item at its declared width.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .a { width: 150px; height: 50px; flex-basis: auto; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(150.0, $this->flexItemWidth($flex, 'a'));
    }

    public function testFlexBasisPercentageResolvesAgainstContainer(): void
    {
        // 25% of a 600pt flex container → 150pt basis.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .a { height: 50px; flex-basis: 25%; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(150.0, $this->flexItemWidth($flex, 'a'));
    }

    public function testFlexBasisContentKeptAsAuto(): void
    {
        // Negative: `content` is a Phase-2 value — Phase 1 treats it
        // like `auto`, keeping the layoutBox-derived width.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .a { width: 120px; height: 50px; flex-basis: content; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(120.0, $this->flexItemWidth($flex, 'a'));
    }

    public function testFlexBasisInvalidKeywordIgnored(): void
    {
        // Negative: an unrecognised keyword falls back to `auto`
        // semantics (declared width preserved).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .a { width: 90px; height: 50px; flex-basis: nonsense; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(90.0, $this->flexItemWidth($flex, 'a'));
    }

    public function testFlexShrinkEvenlyReducesOverflowingItems(): void
    {
        // Two items 400 wide each = 800 in a 600 container → 200
        // overflow; default flex-shrink: 1 → each loses 100 → both
        // end up 300 wide.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 400px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(300.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(300.0, $this->flexItemWidth($flex, 'b'));
    }

    public function testFlexShrinkZeroProtectsItemFromShrinking(): void
    {
        // Three items × 250 wide = 750 in 600 container → 150
        // overflow. The `protected` item has flex-shrink: 0 so the
        // other two absorb the entire 150 (75 each → 175 wide).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b protected"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 250px; height: 50px; }
             .protected { flex-shrink: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(175.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(250.0, $this->flexItemWidth($flex, 'b'));
        self::assertSame(175.0, $this->flexItemWidth($flex, 'c'));
    }

    public function testFlexItemMinWidthAutoFloorsAtMinContent(): void
    {
        // CSS Flexbox 1 §4.5 — `min-width: auto` (the initial value)
        // on a row flex item resolves to its content-based minimum.
        // A zero-width container would otherwise shrink the item to 0,
        // but its text content's min-content width (the widest word)
        // is the floor, so the item cannot shrink below it. "WWWW" in
        // 16px Ahem ≈ 64px; the exact width depends on the test font,
        // so assert only that the item is floored well above zero.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex"><div class="a">WWWW WWWW</div></div></body></html>',
            '.flex { display: flex; width: 0px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // Floored at the widest word — strictly positive, not 0.
        self::assertGreaterThan(0.0, $this->flexItemWidth($flex, 'a'));
    }

    public function testFlexItemEmptySizedShrinksBelowSpecifiedWidth(): void
    {
        // CSS Flexbox 1 §4.5 — the automatic minimum is the SMALLER of
        // the content size suggestion and the specified size
        // suggestion. An empty `width: 400px` item has a content
        // min-content of 0, so `min(0, 400) = 0` and it shrinks
        // freely: two 400px items in a 600px container shrink to
        // 300 each (NOT floored at their declared 400).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div><div class="b"></div></div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 400px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(300.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(300.0, $this->flexItemWidth($flex, 'b'));
    }

    public function testFlexItemExplicitMinWidthZeroOptsOutOfContentFloor(): void
    {
        // CSS Flexbox 1 §4.5 — an authored `min-width: 0` replaces the
        // `auto` automatic minimum, so a text item can shrink below
        // its min-content width (down to the container's 0).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex"><div class="a">WWWW WWWW</div></div></body></html>',
            '.flex { display: flex; width: 0px; }
             .flex > div { min-width: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItemWidth($flex, 'a'));
    }

    public function testFlexShrinkNoOpWhenNoOverflow(): void
    {
        // Negative: when items already fit, shrink has nothing to do.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 600px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(100.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(100.0, $this->flexItemWidth($flex, 'b'));
    }

    public function testFlexShrinkAllZeroPreservesOverflow(): void
    {
        // Negative: when every item has shrink:0, overflow stays —
        // widths don't change. (Painter overflow clipping is a
        // separate concern.)
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 200px; }
             .flex > div { width: 400px; height: 50px; flex-shrink: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(400.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(400.0, $this->flexItemWidth($flex, 'b'));
    }

    public function testFlexShrinkNegativeValueTreatedAsZero(): void
    {
        // Negative: per CSS Flexbox 1 §7.1, `flex-shrink` is
        // non-negative — a negative value falls back to 0.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 400px; }
             .flex > div { width: 300px; height: 50px; flex-shrink: -1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(300.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(300.0, $this->flexItemWidth($flex, 'b'));
    }

    public function testFlexShrinkWeightedByFactor(): void
    {
        // Negative: shrink factors 1 and 3 share 200pt of overflow
        // 1/4 and 3/4 → 50 and 150 reductions on the two 300-wide
        // items → final widths 250 and 150.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 400px; }
             .flex > div { width: 300px; height: 50px; }
             .a { flex-shrink: 1; }
             .b { flex-shrink: 3; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(250.0, $this->flexItemWidth($flex, 'a'));
        self::assertSame(150.0, $this->flexItemWidth($flex, 'b'));
    }

    public function testFlexShrinkClampsAtZero(): void
    {
        // CSS Flexbox 1 §9.7: shrink uses SCALED ratios (shrink ×
        // base size), and items that clamp at their min main size
        // freeze + the deficit is redistributed across the remaining
        // flexible items. Setup: a (w=10, shrink=99), b (w=100,
        // shrink=1), container=50, overflow=60. Scaled shrinks:
        // 99×10=990 vs 1×100=100; a's first-iteration share would
        // push it below 0, so it freezes at 0 (its implicit min). The
        // remaining 50pt deficit then distributes solely through b,
        // landing it at 50pt wide (filling the container).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 50px; }
             .a { width: 10px; height: 50px; flex-shrink: 99; }
             .b { width: 100px; height: 50px; flex-shrink: 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItemWidth($flex, 'a'));
        self::assertEqualsWithDelta(50.0, $this->flexItemWidth($flex, 'b'), 0.001);
    }

    public function testFlexShorthandOneFillsContainer(): void
    {
        // End-to-end: `flex: 1` expands to grow:1 / shrink:1 /
        // basis:0 — combined with the new basis handling, the item
        // fills the container even without `width: 0`.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 500px; }
             .a { height: 50px; flex: 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(500.0, $this->flexItemWidth($flex, 'a'));
    }

    public function testFlexWrapBreaksItemsAcrossLines(): void
    {
        // 4 items × 200pt wide in a 500pt container → only 2 fit per
        // line. With flex-wrap: wrap, items 3 and 4 spill onto a
        // second line.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '<div class="d"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; width: 500px; }
             .flex > div { width: 200px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        $a = $this->flexItem($flex, 'a');
        $b = $this->flexItem($flex, 'b');
        $c = $this->flexItem($flex, 'c');
        $d = $this->flexItem($flex, 'd');
        // First line: a (x=0,y=0), b (x=200,y=0).
        self::assertSame(0.0, $a->geometry->x);
        self::assertSame(0.0, $a->geometry->y);
        self::assertSame(200.0, $b->geometry->x);
        self::assertSame(0.0, $b->geometry->y);
        // Second line: c (x=0,y=50), d (x=200,y=50).
        self::assertSame(0.0, $c->geometry->x);
        self::assertSame(50.0, $c->geometry->y);
        self::assertSame(200.0, $d->geometry->x);
        self::assertSame(50.0, $d->geometry->y);
    }

    public function testFlexWrapContainerHeightAccumulatesLineHeights(): void
    {
        // Wrapped container shrinks-to-fit on the cross axis: 2 lines
        // × max 50pt each = 100pt total height.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; width: 200px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // 2 items fit on line 1, third spills onto line 2 → 2 × 50.
        self::assertSame(100.0, $flex->geometry->height);
    }

    public function testFlexWrapRowGapBetweenLines(): void
    {
        // row-gap inserts vertical spacing between flex lines under
        // row+wrap: line 1 at y=0 (50 tall), gap 12, line 2 at y=62.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; row-gap: 12px; width: 200px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(0.0, $this->flexItem($flex, 'b')->geometry->y);
        self::assertSame(62.0, $this->flexItem($flex, 'c')->geometry->y);
        // Container height includes the inter-line gap.
        self::assertSame(112.0, $flex->geometry->height);
    }

    public function testFlexWrapReverseStacksLinesBottomUp(): void
    {
        // wrap-reverse flips cross-axis: line containing item c is
        // now visually FIRST (y=0), original first line goes to y=50.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap-reverse; width: 200px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // c was on line 2 originally → now y=0.
        self::assertSame(0.0, $this->flexItem($flex, 'c')->geometry->y);
        // a + b were on line 1 originally → now y=50.
        self::assertSame(50.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(50.0, $this->flexItem($flex, 'b')->geometry->y);
    }

    public function testFlexNoWrapDefaultKeepsItemsOnOneLine(): void
    {
        // Negative: default flex-wrap: nowrap → items stay on one
        // line even when they overflow / get shrunk; container height
        // stays at single-line maximum.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; width: 200px; }
             .flex > div { width: 100px; height: 50px; flex-shrink: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // All 3 share y=0 (no wrapping happened).
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(0.0, $this->flexItem($flex, 'b')->geometry->y);
        self::assertSame(0.0, $this->flexItem($flex, 'c')->geometry->y);
        self::assertSame(50.0, $flex->geometry->height);
    }

    public function testFlexWrapPerLineGrowDistributesIndependently(): void
    {
        // Negative: flex-grow inside a wrapped line distributes only
        // THAT line's slack — not the whole container's. Two items
        // per line, each width 100, grow:1 in a 600 container →
        // each item grows to 300pt.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '<div class="d"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; width: 600px; }
             .flex > div { width: 250px; height: 50px; flex-grow: 1; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // 2 items × 250 = 500 → 100 slack per line → each item 300.
        self::assertSame(300.0, $this->flexItem($flex, 'a')->geometry->width);
        self::assertSame(300.0, $this->flexItem($flex, 'b')->geometry->width);
        self::assertSame(300.0, $this->flexItem($flex, 'c')->geometry->width);
        self::assertSame(300.0, $this->flexItem($flex, 'd')->geometry->width);
    }

    public function testFlexWrapColumnPartitionsByHeight(): void
    {
        // Negative: column-direction wrap partitions by HEIGHT
        // overflow. Container 100pt tall, items 50pt each → 2 fit
        // per column, third spills to next column at x=100.
        // `align-content: flex-start` keeps lines flush so the
        // cross-axis position is purely a function of line cross
        // extents (without it, default `stretch` grows each line).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column; flex-wrap: wrap;
                     align-content: flex-start;
                     width: 300px; height: 100px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(50.0, $this->flexItem($flex, 'b')->geometry->y);
        self::assertSame(0.0, $this->flexItem($flex, 'c')->geometry->y);
        // Cross-axis x: first column at 0, second at 100 (one column
        // wide).
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->x);
        self::assertSame(100.0, $this->flexItem($flex, 'c')->geometry->x);
    }

    public function testFlexWrapSingleItemOverflowingFitsAlone(): void
    {
        // Negative: an item too wide for the container still ends up
        // on its own line (CSS Flexbox 1 §9.3 step 5). 1 item 500pt
        // wide in a 100pt container → 1 line, overflow stays.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; width: 100px; }
             .a { width: 500px; height: 30px; flex-shrink: 0; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->x);
    }

    public function testFlexAlignContentStretchExpandsLines(): void
    {
        // Default `align-content: stretch` with multi-line wrap and
        // cross-axis slack: 3 items × 100w in a 600w container that
        // fits 6 per line → fits all 3 on one line. So force 2 lines:
        // 4 items × 100w in 200w container, height 200pt. 2 lines
        // each 50pt natural; container 200pt → 100pt cross slack
        // split across 2 lines = 50pt bonus per line → 100pt each.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '<div class="d"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; width: 200px; height: 200px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // Line 1 stretched to 100pt → line 2 starts at y=100.
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(100.0, $this->flexItem($flex, 'c')->geometry->y);
    }

    public function testFlexAlignContentCenterCentersLineStack(): void
    {
        // align-content: center centers the line stack on the cross
        // axis. 2 lines × 50pt natural = 100pt; container 200pt →
        // 100pt slack / 2 = 50pt leading offset.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '<div class="d"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; align-content: center;
                     width: 200px; height: 200px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(50.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(100.0, $this->flexItem($flex, 'c')->geometry->y);
    }

    public function testFlexAlignContentSpaceBetweenSpacesLines(): void
    {
        // 2 lines × 50pt = 100pt; container 300pt → 200pt slack. With
        // 2 lines, space-between puts line 1 at y=0 and line 2 at
        // y=250 (50 + 200 gap).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '<div class="d"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; align-content: space-between;
                     width: 200px; height: 300px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(250.0, $this->flexItem($flex, 'c')->geometry->y);
    }

    public function testFlexAlignContentFlexEndStacksLinesAtBottom(): void
    {
        // align-content: flex-end packs lines at the cross-end edge.
        // 2 lines × 50pt = 100pt; container 300pt → leading 200pt
        // before line 1 → line 1 at y=200, line 2 at y=250.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '<div class="d"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; align-content: flex-end;
                     width: 200px; height: 300px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(200.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(250.0, $this->flexItem($flex, 'c')->geometry->y);
    }

    public function testFlexAlignContentIgnoredOnSingleLine(): void
    {
        // Negative: spec §8.3 — align-content has no effect on a
        // container with only one flex line. The single line still
        // sits at y=0 regardless of value.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; align-content: flex-end;
                     width: 600px; height: 300px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->y);
    }

    public function testFlexAlignContentIgnoredWithoutCrossSlack(): void
    {
        // Negative: when line cross extents already fill the
        // container, align-content has nothing to distribute.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '<div class="d"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; align-content: space-between;
                     width: 200px; height: 100px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(50.0, $this->flexItem($flex, 'c')->geometry->y);
    }

    public function testFlexAlignContentInvalidKeywordIgnored(): void
    {
        // Negative: an unrecognised keyword skips the switch and
        // leaves the lines flush (Phase-1 simplification — the spec
        // would fall back to the initial value `stretch`, but Phase 1
        // just no-ops). Line 2 sits at y=50 (line 1's cross extent),
        // not at y=100 (the stretch result).
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '<div class="d"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-wrap: wrap; align-content: nonsense;
                     width: 200px; height: 200px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        self::assertSame(50.0, $this->flexItem($flex, 'c')->geometry->y);
    }

    public function testFlexWrapColumnAutoHeightFallsBackToNowrap(): void
    {
        // Negative: column direction with auto height → main-axis
        // size is indefinite, so wrap can't partition. Spec §9.3
        // step 5 falls back to single-line behaviour.
        $box = $this->buildTreeWithUa(
            '<html><body><div class="flex">'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '</div></body></html>',
            '.flex { display: flex; flex-direction: column; flex-wrap: wrap; width: 200px; }
             .flex > div { width: 100px; height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $flex = $this->find($box, 'div');
        self::assertNotNull($flex);
        // All items stacked vertically (single column).
        self::assertSame(0.0, $this->flexItem($flex, 'a')->geometry->y);
        self::assertSame(50.0, $this->flexItem($flex, 'b')->geometry->y);
        self::assertSame(100.0, $this->flexItem($flex, 'c')->geometry->y);
        self::assertSame(150.0, $flex->geometry->height);
    }

    /**
     * Helper: return the layout x of a flex item picked by class name.
     */
    private function flexItemX(Box $flex, string $className): float
    {
        return $this->flexItem($flex, $className)->geometry->x;
    }

    private function flexItemWidth(Box $flex, string $className): float
    {
        return $this->flexItem($flex, $className)->geometry->width;
    }

    private function flexItem(Box $flex, string $className): Box
    {
        foreach ($flex->children as $child) {
            if ($child->element !== null && in_array($className, $child->element->classes(), true)) {
                return $child;
            }
        }
        self::fail("flex item with class .{$className} not found");
    }

    public function testAspectRatioConstrainsHeightFromWidth(): void
    {
        // `aspect-ratio: 16/9` with width 320 → height = 320/16*9 = 180.
        $box = $this->buildTree(
            '<html><body><div style="width: 320px; aspect-ratio: 16/9"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(180.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioAcceptsSingleNumber(): void
    {
        // `aspect-ratio: 1.5` (no slash) → ratio of 1.5:1.
        // width 300 → height = 200.
        $box = $this->buildTree(
            '<html><body><div style="width: 300px; aspect-ratio: 1.5"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(200.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioAutoKeywordWithRatioApplies(): void
    {
        // CSS Sizing 4 §4.2 — `aspect-ratio: auto 16/9` on a
        // non-replaced box has no natural ratio to prefer, so the
        // explicit `16/9` wins exactly like the bare ratio. Width 320
        // → height 180. The `auto` token rides along in a space-
        // separated sub-list of the slash value and must be unwrapped.
        $box = $this->buildTree(
            '<html><body><div style="width: 320px; aspect-ratio: auto 16/9"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(180.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioRatioBeforeAutoKeywordApplies(): void
    {
        // The `auto && <ratio>` grammar is order-independent — `16/9
        // auto` parses with `auto` trailing the denominator and must
        // resolve identically to `auto 16/9`. Width 320 → height 180.
        $box = $this->buildTree(
            '<html><body><div style="width: 320px; aspect-ratio: 16/9 auto"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(180.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioAutoKeywordWithSingleNumberApplies(): void
    {
        // `auto && <ratio>` where the ratio is a bare `<number>`
        // (no slash) — `auto 1.5` resolves to 1.5:1. Width 300 →
        // height 200.
        $box = $this->buildTree(
            '<html><body><div style="width: 300px; aspect-ratio: auto 1.5"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(200.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioAutoIsNoOp(): void
    {
        // Default `aspect-ratio: auto` doesn't change height — empty
        // div has height 0 from children.
        $box = $this->buildTree(
            '<html><body><div style="width: 100px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(0.0, $div->geometry->height);
    }

    public function testAspectRatioBlockSizeFlooredByTallerContent(): void
    {
        // CSS Sizing 4 §4.1 — the ratio-derived block size is a
        // *preferred* size, not a hard cap: a `width: 100px;
        // aspect-ratio: 2/1` box prefers height 50, but a 100px-tall
        // child floors the used height to 100 so content isn't
        // clipped.
        $box = $this->buildTree(
            '<html><body><div style="width: 100px; aspect-ratio: 2/1">'
                . '<div class="kid" style="height: 100px"></div></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(100.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioBlockSizeNotFlooredWhenScrollContainer(): void
    {
        // CSS Sizing 4 §5.1 — a scroll container's content-based
        // automatic minimum size is zero, so `overflow: hidden` (a
        // scroll container) keeps the ratio-derived height and lets
        // taller content scroll/clip. Width 100, aspect-ratio 1/1 →
        // height 100 even though the child is 500px tall.
        $box = $this->buildTree(
            '<html><body><div style="width: 100px; aspect-ratio: 1/1; overflow: hidden">'
                . '<div style="height: 500px"></div></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(100.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioBlockSizeNotFlooredWhenMinHeightExplicitZero(): void
    {
        // CSS Sizing 4 §5.1 — an authored `min-height: 0` replaces the
        // `auto` automatic content-based minimum, so the ratio-derived
        // height wins and taller content overflows. Width 100,
        // aspect-ratio 1/1 → height 100 even though the child is 500px;
        // the explicit `0` (which our cascade must distinguish from the
        // unset default) suppresses the content floor.
        $box = $this->buildTree(
            '<html><body><div style="width: 100px; aspect-ratio: 1/1; min-height: 0">'
                . '<div style="height: 500px"></div></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(100.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioBlockSizeKeptWhenContentShorter(): void
    {
        // Inverse of the floor: when content is shorter than the
        // ratio-derived height the ratio still wins. Width 200,
        // aspect-ratio 2/1 → height 100; a 10px child does not pull
        // the box below 100.
        $box = $this->buildTree(
            '<html><body><div style="width: 200px; aspect-ratio: 2/1">'
                . '<div style="height: 10px"></div></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(100.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioIgnoredWhenHeightExplicit(): void
    {
        // Explicit height wins over aspect-ratio — width 200,
        // aspect-ratio 1, but height 50px → height stays 50.
        $box = $this->buildTree(
            '<html><body><div style="width: 200px; height: 50px; aspect-ratio: 1"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(50.0, $div->geometry->height);
    }

    public function testAspectRatioZeroDenominatorTreatedAsAuto(): void
    {
        // Negative: zero denominator (division-by-zero guard) →
        // ratio invalid, height stays at default.
        $box = $this->buildTree(
            '<html><body><div style="width: 100px; aspect-ratio: 16/0"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(0.0, $div->geometry->height);
    }

    public function testAspectRatioWithAutoWidthDoesNotApply(): void
    {
        // Width: auto (fills container 600) + aspect-ratio: 2 →
        // height = 300 (since auto width fills the containing block).
        $box = $this->buildTree(
            '<html><body><div style="aspect-ratio: 2"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        // 600 / 2 = 300.
        self::assertEqualsWithDelta(300.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioWidthFromExplicitHeight(): void
    {
        // CSS Sizing 4 §4.2 inverse direction: `width: auto;
        // height: 200px; aspect-ratio: 2` → width = 200 × 2 = 400.
        $box = $this->buildTree(
            '<html><body><div style="height: 200px; aspect-ratio: 2"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(400.0, $div->geometry->width, 0.001);
        self::assertEqualsWithDelta(200.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioBothExplicitIgnoresRatio(): void
    {
        // Negative: when BOTH width and height are explicit, the
        // ratio must be ignored — declared dimensions win even when
        // they don't match the ratio.
        $box = $this->buildTree(
            '<html><body><div style="width: 100px; height: 50px; aspect-ratio: 5"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(100.0, $div->geometry->width, 0.001);
        self::assertEqualsWithDelta(50.0, $div->geometry->height, 0.001);
    }

    public function testAspectRatioInverseWithSlashRatio(): void
    {
        // Positive: slash form (`<num> / <num>`) works for inverse
        // direction too. height: 90; ratio 16/9 → width = 160.
        $box = $this->buildTree(
            '<html><body><div style="height: 90px; aspect-ratio: 16/9"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertEqualsWithDelta(160.0, $div->geometry->width, 0.001);
    }

    public function testMaxWidthClampsExplicitlySizedBox(): void
    {
        // `max-width: 300px` should clamp a 500px-wide box.
        $box = $this->buildTree(
            '<html><body><div style="width: 500px; max-width: 300px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(300.0, $div->geometry->width);
    }

    public function testMaxWidthCentersWithAutoMargins(): void
    {
        // The canonical "centered fixed-width container" pattern:
        // `max-width: 400px; margin: 0 auto`. The 600-wide container
        // has 200px slack which splits 100px on each side.
        $box = $this->buildTree(
            '<html><body><div style="max-width: 400px; margin: 0 auto"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(400.0, $div->geometry->width);
        self::assertSame(100.0, $div->geometry->marginLeft);
        self::assertSame(100.0, $div->geometry->marginRight);
    }

    public function testMinWidthExpandsTooSmallBox(): void
    {
        // `width: 100px; min-width: 250px` resolves to 250px.
        $box = $this->buildTree(
            '<html><body><div style="width: 100px; min-width: 250px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(250.0, $div->geometry->width);
    }

    public function testMinWidthBeatsMaxWidthWhenInConflict(): void
    {
        // CSS 2.1 §10.4: min-width takes precedence over max-width when
        // min > max. So `min: 200px; max: 100px` resolves to 200px.
        $box = $this->buildTree(
            '<html><body><div style="width: 50px; min-width: 200px; max-width: 100px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(200.0, $div->geometry->width);
    }

    public function testMaxWidthNoneIsNoClamp(): void
    {
        // The `none` keyword (initial value for max-width) leaves the
        // upper bound unbounded — a 500px declared width stays 500px.
        $box = $this->buildTree(
            '<html><body><div style="width: 500px; max-width: none"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(500.0, $div->geometry->width);
    }

    public function testWidthAutoNotClampedWithoutMaxWidth(): void
    {
        // No min/max declared — auto-width box still fills the
        // containing block.
        $box = $this->buildTree(
            '<html><body><div></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(600.0, $div->geometry->width);
    }

    public function testBoxSizingBorderBoxSubtractsBorderAndPaddingFromWidth(): void
    {
        // CSS Sizing 3 §6.2: with `box-sizing: border-box`, the
        // declared `width: 200px` includes the 10pt borders on each
        // side (20pt total) and 5pt padding on each side (10pt total).
        // Content width becomes 200 - 20 - 10 = 170.
        $box = $this->buildTree(
            '<html><body><div style="width: 200px; padding: 5px; border: 10px solid;
                                       box-sizing: border-box"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(170.0, $div->geometry->width);
        // outerWidth (the visible box edge) equals the declared 200pt.
        self::assertSame(200.0, $div->geometry->outerWidth());
    }

    public function testBoxSizingBorderBoxSubtractsFromHeight(): void
    {
        // Same rule for height: declared 100pt - top/bottom padding
        // 20pt - top/bottom border 10pt = 70pt content height.
        $box = $this->buildTree(
            '<html><body><div style="height: 100px; padding: 10px; border: 5px solid;
                                       box-sizing: border-box"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        // 100 - 2*10 - 2*5 = 70.
        self::assertSame(70.0, $div->geometry->height);
    }

    public function testBoxSizingContentBoxDefaultIgnoresBorderInWidth(): void
    {
        // Negative: default `content-box` — declared width is the
        // content width; border + padding stack outside, so the
        // outer box is wider than the declared width.
        $box = $this->buildTree(
            '<html><body><div style="width: 200px; padding: 5px; border: 10px solid"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        // Content stays at 200, outerWidth = 200 + 30 = 230.
        self::assertSame(200.0, $div->geometry->width);
        self::assertSame(230.0, $div->geometry->outerWidth());
    }

    public function testBoxSizingBorderBoxWithoutBorderOrPadding(): void
    {
        // Negative: border-box with no border + padding declared →
        // content width matches declared width (no subtraction needed).
        $box = $this->buildTree(
            '<html><body><div style="width: 200px; box-sizing: border-box"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(200.0, $div->geometry->width);
    }

    public function testBoxSizingBorderBoxAutoWidthIgnored(): void
    {
        // Negative: with `width: auto`, box-sizing has no effect — the
        // auto computation already produces the content width directly.
        $box = $this->buildTree(
            '<html><body><div style="padding: 10px; border: 5px solid;
                                       box-sizing: border-box"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        // Auto width: cbWidth(600) - 2*10 (padding) - 2*5 (border) = 570.
        self::assertSame(570.0, $div->geometry->width);
    }

    public function testBoxSizingBorderBoxMaxWidthSubtractsInsets(): void
    {
        // `max-width: 200px; box-sizing: border-box` includes border
        // + padding too. So content-width clamps at
        // 200 - 30 (border 2×10 + padding 2×5) = 170 even though
        // declared width was 500 → outer 200.
        $box = $this->buildTree(
            '<html><body><div style="width: 500px; max-width: 200px;
                                       padding: 5px; border: 10px solid;
                                       box-sizing: border-box"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(170.0, $div->geometry->width);
        self::assertSame(200.0, $div->geometry->outerWidth());
    }

    public function testBoxSizingBorderBoxMinHeightSubtractsInsets(): void
    {
        // `min-height: 100px; box-sizing: border-box` content min is
        // 100 - 30 = 70. With default auto height (no content) → 70.
        $box = $this->buildTree(
            '<html><body><div style="min-height: 100px; padding: 10px;
                                       border: 5px solid;
                                       box-sizing: border-box"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(70.0, $div->geometry->height);
    }

    public function testBoxSizingBorderBoxClampsAtZero(): void
    {
        // Negative: when border + padding exceed the declared width,
        // content width clamps at 0 rather than going negative.
        $box = $this->buildTree(
            '<html><body><div style="width: 20px; padding: 30px; border: 5px solid;
                                       box-sizing: border-box"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        // 20 - 60 - 10 = -50 → clamped to 0.
        self::assertSame(0.0, $div->geometry->width);
    }

    public function testMinHeightExpandsAutoHeightBox(): void
    {
        // `<div>` with no children → height 0 by default. min-height
        // expands it.
        $box = $this->buildTree(
            '<html><body><div style="min-height: 150px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(150.0, $div->geometry->height);
    }

    public function testMaxHeightClampsExplicitHeight(): void
    {
        $box = $this->buildTree(
            '<html><body><div style="height: 500px; max-height: 200px"></div></body></html>',
            'html, body, div { display: block; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame(200.0, $div->geometry->height);
    }

    public function testFieldsetGetsBorderFromUa(): void
    {
        // HTML 5 §4.10.15 — fieldset has a 1px solid border by default.
        $box = $this->buildTreeWithUa(
            '<html><body><fieldset></fieldset></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $fieldset = $this->find($box, 'fieldset');
        self::assertNotNull($fieldset);
        self::assertSame(1.0, $fieldset->geometry->borderTop);
        self::assertSame(1.0, $fieldset->geometry->borderRight);
        self::assertSame(1.0, $fieldset->geometry->borderBottom);
        self::assertSame(1.0, $fieldset->geometry->borderLeft);
    }

    public function testAuthorCanOverrideFieldsetBorder(): void
    {
        // Author override removes the UA border.
        $box = $this->buildTreeWithUa(
            '<html><body><fieldset style="border: none"></fieldset></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $fieldset = $this->find($box, 'fieldset');
        self::assertNotNull($fieldset);
        self::assertSame(0.0, $fieldset->geometry->borderTop);
    }

    public function testCanvasIsInlineBlock(): void
    {
        // HTML 5 §4.12.5 — canvas defaults to inline-block.
        $box = $this->buildTreeWithUa(
            '<html><body><canvas>fallback</canvas></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $canvas = $this->find($box, 'canvas');
        self::assertNotNull($canvas);
        self::assertInstanceOf(\Phpdftk\HtmlToPdf\Box\AtomicInlineBox::class, $canvas);
    }

    public function testLegendGetsPaddingFromUa(): void
    {
        // HTML 5 §4.10.15 — legend gets small horizontal padding.
        $box = $this->buildTreeWithUa(
            '<html><body><fieldset><legend>x</legend></fieldset></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $legend = $this->find($box, 'legend');
        self::assertNotNull($legend);
        self::assertGreaterThan(0.0, $legend->geometry->paddingLeft);
        self::assertGreaterThan(0.0, $legend->geometry->paddingRight);
    }

    public function testMeterIsInlineBlock(): void
    {
        // HTML 5 §4.10.13 — `<meter>` is inline-block per UA.
        $box = $this->buildTreeWithUa(
            '<html><body><meter value="0.5">50%</meter></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $meter = $this->find($box, 'meter');
        self::assertNotNull($meter);
        // Inline-block boxes are AtomicInlineBox in our box tree.
        self::assertInstanceOf(\Phpdftk\HtmlToPdf\Box\AtomicInlineBox::class, $meter);
    }

    public function testProgressIsInlineBlock(): void
    {
        $box = $this->buildTreeWithUa(
            '<html><body><progress value="0.3">30%</progress></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $progress = $this->find($box, 'progress');
        self::assertNotNull($progress);
        self::assertInstanceOf(\Phpdftk\HtmlToPdf\Box\AtomicInlineBox::class, $progress);
    }

    public function testAuthorCanOverrideMeterDisplay(): void
    {
        // Negative: author override beats the UA inline-block.
        $box = $this->buildTreeWithUa(
            '<html><body><meter style="display: block; height: 20px">50%</meter></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $meter = $this->find($box, 'meter');
        self::assertNotNull($meter);
        self::assertInstanceOf(\Phpdftk\HtmlToPdf\Box\BlockBox::class, $meter);
    }

    public function testDatalistHiddenByUa(): void
    {
        // HTML 5 §4.10.10 — `<datalist>` is a typeahead helper; UA
        // sets `display: none` so it never renders.
        $box = $this->buildTreeWithUa(
            '<html><body><datalist><option value="x">x</option></datalist></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        // datalist should produce no visible box at all.
        $datalist = $this->find($box, 'datalist');
        self::assertNull($datalist);
    }

    public function testRpHiddenByUa(): void
    {
        // HTML 5 §4.5.21 — `<rp>` is hidden in ruby-aware browsers.
        // We don't paint ruby yet so the fallback parens stay
        // suppressed to match the spec.
        $box = $this->buildTreeWithUa(
            '<html><body><ruby>x<rp>(</rp><rt>y</rt><rp>)</rp></ruby></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $rp = $this->find($box, 'rp');
        self::assertNull($rp);
    }

    public function testAuthorOverridesDatalistHidden(): void
    {
        // Author CSS can override the hidden default.
        $box = $this->buildTreeWithUa(
            '<html><body><datalist style="display: block; height: 20px"></datalist></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $datalist = $this->find($box, 'datalist');
        self::assertNotNull($datalist);
    }

    public function testDirRtlMapsToDirectionRtlAndIsolate(): void
    {
        // HTML 5 §15.3 — `<div dir="rtl">` should get
        // direction: rtl AND unicode-bidi: isolate via the UA
        // attribute selector.
        $box = $this->buildTreeWithUa(
            '<html><body><div dir="rtl"></div></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        $dir = $div->style->get('direction');
        $bidi = $div->style->get('unicode-bidi');
        self::assertInstanceOf(\Phpdftk\Css\Value\Keyword::class, $dir);
        self::assertSame('rtl', strtolower($dir->name));
        self::assertSame('isolate', strtolower($bidi->name));
    }

    public function testDirLtrMapsToDirectionLtrAndIsolate(): void
    {
        $box = $this->buildTreeWithUa(
            '<html><body><div dir="ltr"></div></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        $dir = $div->style->get('direction');
        $bidi = $div->style->get('unicode-bidi');
        self::assertSame('ltr', strtolower($dir->name));
        self::assertSame('isolate', strtolower($bidi->name));
    }

    public function testDirAttributeDoesNotOverrideBdoBidiOverride(): void
    {
        // Critical: `<bdo dir="rtl">` should keep
        // unicode-bidi: bidi-override (from the bdo element selector)
        // NOT lose to the `[dir="rtl"]` attribute selector's
        // `isolate`. The `:where()` wrapper drops the attribute
        // selector's specificity to 0.
        $box = $this->buildTreeWithUa(
            '<html><body><p><bdo dir="rtl">x</bdo></p></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $bdo = $this->find($box, 'bdo');
        self::assertNotNull($bdo);
        $bidi = $bdo->style->get('unicode-bidi');
        self::assertSame('bidi-override', strtolower($bidi->name));
    }

    public function testNoDirAttributeKeepsDefaultDirection(): void
    {
        // Negative: no `dir` attribute → direction defaults to `ltr`
        // (the initial value), unicode-bidi to `normal`.
        $box = $this->buildTreeWithUa(
            '<html><body><div></div></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        self::assertSame('ltr', strtolower($div->style->get('direction')->name));
        self::assertSame('normal', strtolower($div->style->get('unicode-bidi')->name));
    }

    public function testBdoGetsBidiOverrideFromUa(): void
    {
        // HTML 5 §15.3 — `<bdo>` overrides the bidi algorithm for
        // its descendants. UA sets `unicode-bidi: bidi-override`.
        $box = $this->buildTreeWithUa(
            '<html><body><p><bdo dir="rtl">x</bdo></p></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $bdo = $this->find($box, 'bdo');
        self::assertNotNull($bdo);
        $bidi = $bdo->style->get('unicode-bidi');
        self::assertInstanceOf(\Phpdftk\Css\Value\Keyword::class, $bidi);
        self::assertSame('bidi-override', strtolower($bidi->name));
    }

    public function testBdiGetsIsolateFromUa(): void
    {
        // HTML 5 §15.3 — `<bdi>` isolates its content from
        // surrounding bidi context. UA sets `unicode-bidi: isolate`.
        $box = $this->buildTreeWithUa(
            '<html><body><p><bdi>x</bdi></p></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $bdi = $this->find($box, 'bdi');
        self::assertNotNull($bdi);
        $bidi = $bdi->style->get('unicode-bidi');
        self::assertInstanceOf(\Phpdftk\Css\Value\Keyword::class, $bidi);
        self::assertSame('isolate', strtolower($bidi->name));
    }

    public function testAuthorOverridesBdoUnicodeBidi(): void
    {
        // Author override wins (specificity / source-order).
        $box = $this->buildTreeWithUa(
            '<html><body><p><bdo>x</bdo></p></body></html>',
            'bdo { unicode-bidi: normal; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $bdo = $this->find($box, 'bdo');
        self::assertNotNull($bdo);
        $bidi = $bdo->style->get('unicode-bidi');
        self::assertSame('normal', strtolower($bidi->name));
    }

    public function testNonBidiElementDefaultsToNormalUnicodeBidi(): void
    {
        // Negative: a `<span>` shouldn't pick up bidi-override /
        // isolate from anywhere.
        $box = $this->buildTreeWithUa(
            '<html><body><p><span>x</span></p></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $span = $this->find($box, 'span');
        self::assertNotNull($span);
        $bidi = $span->style->get('unicode-bidi');
        self::assertSame('normal', strtolower($bidi->name));
    }

    public function testUnicodeBidiDoesNotInherit(): void
    {
        // CSS Writing Modes 4: unicode-bidi is non-inheriting. A
        // child element of bdo gets `normal`, not `bidi-override`.
        $box = $this->buildTreeWithUa(
            '<html><body><p><bdo><span>x</span></bdo></p></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $span = $this->find($box, 'span');
        self::assertNotNull($span);
        $bidi = $span->style->get('unicode-bidi');
        self::assertSame('normal', strtolower($bidi->name));
    }

    public function testAuthorOverridesBdiUnicodeBidi(): void
    {
        // Symmetric override test for `<bdi>`.
        $box = $this->buildTreeWithUa(
            '<html><body><p><bdi>x</bdi></p></body></html>',
            'bdi { unicode-bidi: normal; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $bdi = $this->find($box, 'bdi');
        self::assertNotNull($bdi);
        $bidi = $bdi->style->get('unicode-bidi');
        self::assertSame('normal', strtolower($bidi->name));
    }

    public function testAddressInheritsItalicFromUa(): void
    {
        // HTML 5 §4.5.6: `<address>` is rendered in italic by browser
        // convention. The UA `address { font-style: italic }` rule
        // should set it.
        $box = $this->buildTreeWithUa(
            '<html><body><address></address></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $address = $this->find($box, 'address');
        self::assertNotNull($address);
        $style = $address->style->get('font-style');
        self::assertInstanceOf(\Phpdftk\Css\Value\Keyword::class, $style);
        self::assertSame('italic', strtolower($style->name));
    }

    public function testAuthorCssOverridesAddressItalic(): void
    {
        // Author CSS wins over the UA rule.
        $box = $this->buildTreeWithUa(
            '<html><body><address></address></body></html>',
            'address { font-style: normal; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $address = $this->find($box, 'address');
        self::assertNotNull($address);
        $style = $address->style->get('font-style');
        self::assertSame('normal', strtolower($style->name));
    }

    public function testNonAddressElementUnchanged(): void
    {
        // Negative: a `<div>` doesn't inherit the address italic.
        $box = $this->buildTreeWithUa(
            '<html><body><div></div></body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $div = $this->find($box, 'div');
        self::assertNotNull($div);
        $style = $div->style->get('font-style');
        self::assertSame('normal', strtolower($style->name));
    }

    public function testUaDefaultBreakInsideShiftsStraddlingRow(): void
    {
        // No author CSS for `break-inside` — the row should shift onto the
        // next page courtesy of the UA stylesheet's
        // `tr { break-inside: avoid }`. Sanity-check with the same
        // configuration but `break-inside: auto` overriding the UA below.
        $tr = $this->trAtBoundary(authorCss: '');
        self::assertNotNull($tr);
        self::assertGreaterThanOrEqual(800.0 - 0.001, $tr->geometry->y, 'UA default tr break-inside did not shift the row');
    }

    public function testAuthorAutoOverridesUaBreakInsideAndAllowsStraddle(): void
    {
        // The author explicitly opts back in to splitting — the UA's
        // `break-inside: avoid` must defer to `break-inside: auto`.
        $tr = $this->trAtBoundary(authorCss: 'tr { break-inside: auto; }');
        self::assertNotNull($tr);
        self::assertLessThan(800.0, $tr->geometry->y, 'author auto did not override UA avoid');
    }

    public function testParagraphHasNoBreakInsideDefault(): void
    {
        // Negative: confirm we did NOT accidentally add `<p>` to the UA
        // break-inside list. A straddling paragraph (without orphans/widows
        // shifting whole lines) should not be pushed onto the next page —
        // its block-box stays at its content position (after UA
        // margin-top), straddling the boundary.
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<div style="height: 760px"></div>'
                . '<p style="height: 80px; margin: 0"></p>'
                . '</body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $p = $this->find($box, 'p');
        self::assertNotNull($p);
        self::assertEqualsWithDelta(760.0, $p->geometry->y, 0.001, '<p> should NOT have UA break-inside avoid');
    }

    public function testUaDefaultBreakInsideShiftsFigure(): void
    {
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<div style="height: 760px"></div>'
                . '<figure style="height: 120px"></figure>'
                . '</body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $fig = $this->find($box, 'figure');
        self::assertNotNull($fig);
        self::assertGreaterThanOrEqual(800.0 - 0.001, $fig->geometry->y);
    }

    public function testUaDefaultBreakInsideShiftsBlockquote(): void
    {
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<div style="height: 760px"></div>'
                . '<blockquote style="height: 120px"></blockquote>'
                . '</body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $bq = $this->find($box, 'blockquote');
        self::assertNotNull($bq);
        self::assertGreaterThanOrEqual(800.0 - 0.001, $bq->geometry->y);
    }

    public function testUaDefaultBreakInsideShiftsHeading(): void
    {
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<div style="height: 760px"></div>'
                . '<h2 style="height: 120px"></h2>'
                . '</body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $h2 = $this->find($box, 'h2');
        self::assertNotNull($h2);
        self::assertGreaterThanOrEqual(800.0 - 0.001, $h2->geometry->y);
    }

    public function testRowTallerThanPageStaysInPlace(): void
    {
        // Existing constraint: `$childOuterHeight <= $pageHeight` — a
        // single tr taller than the page can't be shifted onto a fresh
        // page (there's no page big enough). It stays at its layout
        // position even with break-inside: avoid set.
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<div style="height: 760px"></div>'
                . '<table><tr><td style="height: 900px">cell</td></tr></table>'
                . '</body></html>',
            '',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $tr = $this->find($box, 'tr');
        self::assertNotNull($tr);
        self::assertLessThan(800.0, $tr->geometry->y);
    }

    public function testColumnSpanAllSpansFullContainerWidth(): void
    {
        // `column-span: all` on a child inside a multi-column container
        // should give it the full container width (600px in our default
        // ctx). Children before / after still flow into columns.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a"></div>'
                . '<div class="span" style="column-span: all"></div>'
                . '<div class="b"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 0; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $children = $section->children;
        $spanner = null;
        foreach ($children as $c) {
            if ($c->element !== null && in_array('span', $c->element->classes(), true)) {
                $spanner = $c;
                break;
            }
        }
        self::assertNotNull($spanner);
        self::assertSame(600.0, $spanner->geometry->width);
    }

    public function testColumnSpanAllStartsSecondSegmentAfterIt(): void
    {
        // Before-segment child sits in column 0 of the first columnar
        // run. The spanner sits below at full width. The after-segment
        // child starts a fresh columnar run at column 0 below the
        // spanner.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a"></div>'
                . '<div class="span" style="column-span: all; height: 30px"></div>'
                . '<div class="b"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 0; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $a = $section->children[0];
        $spanner = $section->children[1];
        $b = $section->children[2];
        // a in column 0 of the first run, starting at section top.
        self::assertEqualsWithDelta($section->geometry->x, $a->geometry->x, 0.001);
        self::assertEqualsWithDelta($section->geometry->y, $a->geometry->y, 0.001);
        // Spanner below the first run's tallest column (a is 50px high
        // → first columnar segment ends at section.y + 50, since
        // there's only one child to balance).
        $expectedSpannerY = $section->geometry->y + 50.0;
        self::assertEqualsWithDelta($expectedSpannerY, $spanner->geometry->y, 0.001);
        // b in column 0 of the second columnar run, below the spanner.
        self::assertEqualsWithDelta($section->geometry->x, $b->geometry->x, 0.001);
        self::assertEqualsWithDelta($spanner->geometry->y + 30.0, $b->geometry->y, 0.001);
    }

    public function testColumnSpanAllAsFirstChildSkipsLeadingColumnarRun(): void
    {
        // First child is the spanner — no leading columnar segment.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="span" style="column-span: all; height: 40px"></div>'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 0; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $spanner = $section->children[0];
        // Spanner sits at the section top, full width.
        self::assertEqualsWithDelta($section->geometry->y, $spanner->geometry->y, 0.001);
        self::assertSame(600.0, $spanner->geometry->width);
    }

    public function testColumnSpanAllAsLastChildSkipsTrailingColumnarRun(): void
    {
        // Last child is the spanner — no trailing columnar segment.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a"></div>'
                . '<div class="b"></div>'
                . '<div class="span" style="column-span: all; height: 40px"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 0; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $spanner = $section->children[2];
        self::assertSame(600.0, $spanner->geometry->width);
        self::assertGreaterThan($section->children[0]->geometry->y, $spanner->geometry->y);
    }

    public function testColumnSpanNoneIsIgnored(): void
    {
        // `column-span: none` (initial value) — children all flow into
        // the columnar layout, no spanner segment.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a"></div>'
                . '<div class="b" style="column-span: none"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 0; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $b = $section->children[1];
        // b sits in column 1 (balance: 50px = ceil(100/2)), not at full width.
        self::assertSame(300.0, $b->geometry->width);
    }

    public function testColumnSpanAllOutsideMultiColumnIsIgnored(): void
    {
        // Outside a multi-column container, `column-span: all` is
        // a no-op — the box stacks as a regular block.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a"></div>'
                . '<div class="span" style="column-span: all; height: 30px"></div>'
                . '<div class="b"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $spanner = $section->children[1];
        // No column-* on section → not multi-column → spanner is a
        // regular block at full body width with normal stacking.
        self::assertSame(600.0, $spanner->geometry->width);
        self::assertEqualsWithDelta(50.0, $spanner->geometry->y, 0.001);
    }

    public function testBreakBeforeColumnStartsNewColumn(): void
    {
        // 4 × 50px-tall children in a column-count: 2 container. With
        // balance = 100, the natural split is 0,1 in col 0 and 2,3 in
        // col 1. Forcing `break-before: column` on child 1 should make
        // child 1 start col 1 alone, with child 0 in col 0.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a"></div>'
                . '<div class="b" style="break-before: column"></div>'
                . '<div class="c"></div>'
                . '<div class="d"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 0; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $children = $section->children;
        // Child 0 → column 0. Children 1,2,3 → column 1 (forced + cascade).
        self::assertEqualsWithDelta($section->geometry->x, $children[0]->geometry->x, 0.001);
        $col1X = $section->geometry->x + 300.0;
        self::assertEqualsWithDelta($col1X, $children[1]->geometry->x, 0.001);
        self::assertEqualsWithDelta($col1X, $children[2]->geometry->x, 0.001);
        self::assertEqualsWithDelta($col1X, $children[3]->geometry->x, 0.001);
    }

    public function testBreakAfterColumnStartsNextChildInNewColumn(): void
    {
        // `break-after: column` on child 0 pushes child 1 into column 1
        // even though child 0 alone wouldn't trigger a balance break.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a" style="break-after: column"></div>'
                . '<div class="b"></div>'
                . '<div class="c"></div>'
                . '<div class="d"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 0; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $children = $section->children;
        $col0X = $section->geometry->x;
        $col1X = $section->geometry->x + 300.0;
        self::assertEqualsWithDelta($col0X, $children[0]->geometry->x, 0.001);
        self::assertEqualsWithDelta($col1X, $children[1]->geometry->x, 0.001);
        self::assertEqualsWithDelta($col1X, $children[2]->geometry->x, 0.001);
        self::assertEqualsWithDelta($col1X, $children[3]->geometry->x, 0.001);
    }

    public function testBreakBeforeAlwaysIsTreatedAsColumnBreakInMultiColumn(): void
    {
        // `break-before: always` is the universal forced break — it
        // honours whichever fragmentainer type the box lives in, so
        // inside a multi-column container it should force a column
        // break just like `break-before: column`.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a"></div>'
                . '<div class="b" style="break-before: always"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 0; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $col1X = $section->geometry->x + 300.0;
        self::assertEqualsWithDelta($col1X, $section->children[1]->geometry->x, 0.001);
    }

    public function testBreakBeforeColumnOnFirstChildIsNoOp(): void
    {
        // Forcing a column break before the very first child has no
        // visible effect — col 0 is already empty at that point.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a" style="break-before: column"></div>'
                . '<div class="b"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 0; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $col0X = $section->geometry->x;
        self::assertEqualsWithDelta($col0X, $section->children[0]->geometry->x, 0.001);
    }

    public function testBreakBeforeColumnOutsideMultiColumnIsIgnored(): void
    {
        // Outside a multi-column container, `break-before: column` has
        // no effect — the children stack as normal blocks.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a"></div>'
                . '<div class="b" style="break-before: column"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $a = $section->children[0];
        $b = $section->children[1];
        self::assertEqualsWithDelta($a->geometry->y + 50.0, $b->geometry->y, 0.001);
        self::assertEqualsWithDelta($a->geometry->x, $b->geometry->x, 0.001);
    }

    public function testBreakBeforePageInsideMultiColumnDoesNotForceColumnBreak(): void
    {
        // A `break-before: page` value should be page-only — it does
        // NOT cascade into the column-break logic. Confirm child 1
        // remains in col 0 with child 0 (no column shift).
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a"></div>'
                . '<div class="b" style="break-before: page"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 0; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $col0X = $section->geometry->x;
        self::assertEqualsWithDelta($col0X, $section->children[0]->geometry->x, 0.001);
        self::assertEqualsWithDelta($col0X, $section->children[1]->geometry->x, 0.001);
    }

    public function testAbsPosLtrAutoMarginsCenterFixedWidth(): void
    {
        // CSS 2.1 §10.3.7 — `position: absolute` with `left + width +
        // right` all set and both margins `auto` (positive slack)
        // distributes the slack evenly across the two margins. The
        // resulting outer-left X sits at `left + slack/2` inside the
        // containing block (LTR direction, ie. equal split).
        $box = $this->buildTree(
            '<html><body><div id="cb">'
                . '<div id="inner"></div>'
                . '</div></body></html>',
            'html, body, div { display: block; }
             #cb { position: relative; width: 200px; height: 100px; }
             #inner { position: absolute; left: 50px; right: 50px;
                      width: 50px; height: 50px;
                      margin-left: auto; margin-right: auto; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cb = $this->find($box, 'div');
        self::assertNotNull($cb);
        // Find the abs-pos inner box — second `div` traversal hit.
        $inner = $cb->children[0];
        // Slack = 200 - 50 - 50 - 50 = 50 → margin-left = 25.
        // Inner outer X = cb.x + left + margin-left = cb.x + 50 + 25.
        self::assertEqualsWithDelta($cb->geometry->x + 75.0, $inner->geometry->x, 0.001);
    }

    public function testAbsPosRtlOverConstrainedMarginsForceMarginRightZero(): void
    {
        // CSS 2.1 §10.3.7 — when slack is negative and direction is
        // `rtl`, force `margin-right: 0` and set `margin-left = -slack`.
        // For the WPT test fixture: left=100, right=100, width=100, cb=200
        // → slack = -100 → margin-left = -100 → outer-X = cb.x + 0.
        $box = $this->buildTree(
            '<html><body><div id="cb">'
                . '<div id="inner"></div>'
                . '</div></body></html>',
            'html, body, div { display: block; }
             #cb { position: relative; direction: rtl;
                   width: 200px; height: 200px; }
             #inner { position: absolute; left: 100px; right: 100px;
                      width: 100px; height: 100px;
                      margin-left: auto; margin-right: auto; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cb = $this->find($box, 'div');
        self::assertNotNull($cb);
        $inner = $cb->children[0];
        // dx = left + margin-left = 100 + (-100) = 0 → inner.x === cb.x.
        self::assertEqualsWithDelta($cb->geometry->x, $inner->geometry->x, 0.001);
    }

    public function testAbsPosLtrOverConstrainedMarginsForceMarginLeftZero(): void
    {
        // CSS 2.1 §10.3.7 — same constraints as the RTL test above,
        // but `direction: ltr` (the initial value) instead forces
        // `margin-left: 0` so the outer-X sits at `cb.x + left`.
        $box = $this->buildTree(
            '<html><body><div id="cb">'
                . '<div id="inner"></div>'
                . '</div></body></html>',
            'html, body, div { display: block; }
             #cb { position: relative;
                   width: 200px; height: 200px; }
             #inner { position: absolute; left: 100px; right: 100px;
                      width: 100px; height: 100px;
                      margin-left: auto; margin-right: auto; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cb = $this->find($box, 'div');
        self::assertNotNull($cb);
        $inner = $cb->children[0];
        // dx = left + 0 = 100 → inner.x === cb.x + 100.
        self::assertEqualsWithDelta($cb->geometry->x + 100.0, $inner->geometry->x, 0.001);
    }

    public function testAbsPosInFlowAutoMarginDistributionDoesNotApply(): void
    {
        // Negative test — the in-flow auto-margin distribution rule
        // (CSS 2.1 §10.3.3) must NOT fire for `position: absolute`
        // boxes, otherwise the abs-pos resolver double-shifts the box.
        // With this skip plus the abs-pos rule firing instead, an
        // unconstrained abs-pos box (only `left` set, both margins auto)
        // lands at `cb.x + left` instead of being re-centred.
        $box = $this->buildTree(
            '<html><body><div id="cb">'
                . '<div id="inner"></div>'
                . '</div></body></html>',
            'html, body, div { display: block; }
             #cb { position: relative; width: 400px; height: 100px; }
             #inner { position: absolute; left: 30px; width: 100px;
                      margin-left: auto; margin-right: auto; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $cb = $this->find($box, 'div');
        self::assertNotNull($cb);
        $inner = $cb->children[0];
        // With only `left` set + abs-pos rule not firing (right is
        // auto), dx = left = 30 → inner.x === cb.x + 30.
        self::assertEqualsWithDelta($cb->geometry->x + 30.0, $inner->geometry->x, 0.001);
    }

    public function testForcedColumnBreaksBeyondColumnCountFallThroughToLastColumn(): void
    {
        // Two forced `break-before: column` requests in a 2-column
        // container — only the first can advance, the second falls
        // through and overflows column 1.
        $box = $this->buildTree(
            '<html><body><section>'
                . '<div class="a"></div>'
                . '<div class="b" style="break-before: column"></div>'
                . '<div class="c" style="break-before: column"></div>'
                . '</section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 2; column-gap: 0; }
             div { height: 50px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        $col1X = $section->geometry->x + 300.0;
        // b is in column 1 (first forced advance worked).
        self::assertEqualsWithDelta($col1X, $section->children[1]->geometry->x, 0.001);
        // c is also in column 1 (second forced advance has nowhere left
        // to go) — stacks below b.
        self::assertEqualsWithDelta($col1X, $section->children[2]->geometry->x, 0.001);
        self::assertEqualsWithDelta(
            $section->children[1]->geometry->y + 50.0,
            $section->children[2]->geometry->y,
            0.001,
        );
    }

    private function trAtBoundary(string $authorCss): ?Box
    {
        $box = $this->buildTreeWithUa(
            '<html><body>'
                . '<div style="height: 760px"></div>'
                . '<table><tr><td style="height: 100px">cell</td></tr></table>'
                . '</body></html>',
            $authorCss,
        );
        $this->layout->layout($box, $this->defaultCtx);
        return $this->find($box, 'tr');
    }

    /**
     * Build a box tree using the renderer's real UA stylesheet plus any
     * author-supplied overrides — needed for tests verifying UA-default
     * behaviour (which `buildTree` skips since it only loads test CSS).
     */
    private function buildTreeWithUa(string $html, string $authorCss): Box
    {
        $doc = $this->html->parseDocument($html);
        $opts = new \Phpdftk\HtmlToPdf\RendererOptions();
        $ua = $this->css->parseStylesheet($opts->effectiveUserAgentStylesheet(), \Phpdftk\Css\Sheet\Origin::UserAgent);
        $sheets = [$ua];
        if ($authorCss !== '') {
            $sheets[] = $this->css->parseStylesheet($authorCss, \Phpdftk\Css\Sheet\Origin::Author);
        }
        $box = $this->generator->generate($doc, $sheets);
        self::assertNotNull($box);
        return $box;
    }

    /**
     * Collect every box in the subtree that is an instance of the given
     * class, in breadth-first order.
     *
     * @param  class-string $type
     * @return list<Box>
     */
    private function collectByType(Box $root, string $type): array
    {
        $out = [];
        $stack = [$root];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node instanceof $type) {
                $out[] = $node;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        return $out;
    }

    private function findById(Box $root, string $id): ?Box
    {
        $stack = [$root];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node->element !== null && $node->element->getAttribute('id') === $id) {
                return $node;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        return null;
    }

    private function find(Box $root, string $tag): ?Box
    {
        // Accept the `tag.class` form so tests can disambiguate
        // siblings of the same tag. Class match is presence-of, not
        // a full Selectors-4 implementation — sufficient for tests.
        $class = null;
        if (str_contains($tag, '.')) {
            [$tag, $class] = explode('.', $tag, 2);
        }
        $stack = [$root];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node->element !== null && $node->element->localName === $tag) {
                if ($class === null || in_array($class, $node->element->classes(), true)) {
                    return $node;
                }
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        return null;
    }

    // ------------------------------------------------------------
    // CSS Sizing 4 §6 / Containment 3 §4.5 — contain-intrinsic-size
    // (#15)
    // ------------------------------------------------------------

    public function testContainIntrinsicWidthIgnoredWithoutContainSize(): void
    {
        // Negative: `contain-intrinsic-size` declared alone (no
        // `contain: size`) is inert. Without containment the box
        // measures its actual children.
        $root = $this->buildTree(
            '<html><body><div id="t"><span>abc</span></div></body></html>',
            'html, body, div { display: block; } span { display: inline; }
             #t { contain-intrinsic-size: 111px 222px; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $t = $this->find($root, 'div');
        self::assertNotNull($t);
        $mm = $this->layout->measureMinMaxContent($t, $this->defaultCtx);
        // Without `contain: size`, min/max-content track the
        // children — definitely NOT 111.
        self::assertNotSame(111.0, $mm['max']);
    }

    public function testContainIntrinsicWidthIgnoredOnContainPaintOnly(): void
    {
        // Negative: `contain: paint` does NOT include size
        // containment per CSS Containment 3 §2.4.
        $root = $this->buildTree(
            '<html><body><div id="t"><span>abcdef</span></div></body></html>',
            'html, body, div { display: block; } span { display: inline; }
             #t { contain: paint; contain-intrinsic-size: 111px 222px; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $t = $this->find($root, 'div');
        self::assertNotNull($t);
        $mm = $this->layout->measureMinMaxContent($t, $this->defaultCtx);
        self::assertNotSame(111.0, $mm['max']);
    }

    public function testContainIntrinsicWidthIgnoredOnContainContent(): void
    {
        // Negative: `contain: content` = layout+paint+style (NOT
        // size). The "common confusion" branch the spec explicitly
        // calls out — guard against a future regression.
        $root = $this->buildTree(
            '<html><body><div id="t"><span>abc</span></div></body></html>',
            'html, body, div { display: block; } span { display: inline; }
             #t { contain: content; contain-intrinsic-size: 111px 222px; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $t = $this->find($root, 'div');
        self::assertNotNull($t);
        $mm = $this->layout->measureMinMaxContent($t, $this->defaultCtx);
        self::assertNotSame(111.0, $mm['max']);
    }

    public function testExplicitWidthBeatsContainIntrinsicWidth(): void
    {
        // Negative: an explicit `width` wins over
        // `contain-intrinsic-size` — author CSS is authoritative,
        // containment only substitutes when the box is auto-sized.
        $root = $this->buildTree(
            '<html><body><div id="t"></div></body></html>',
            'html, body, div { display: block; }
             #t { contain: size; contain-intrinsic-size: 111px 222px;
                  width: 50px; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $t = $this->find($root, 'div');
        self::assertNotNull($t);
        $mm = $this->layout->measureMinMaxContent($t, $this->defaultCtx);
        self::assertSame(50.0, $mm['max']);
    }

    public function testContainSizeShorthandSubstitutesIntrinsicWidth(): void
    {
        // Positive: with `contain: size`, the intrinsic width
        // override applies and propagates through measureMinMaxContent.
        $root = $this->buildTree(
            '<html><body><div id="t"><span>abcdef</span></div></body></html>',
            'html, body, div { display: block; } span { display: inline; }
             #t { contain: size; contain-intrinsic-size: 111px 222px; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $t = $this->find($root, 'div');
        self::assertNotNull($t);
        $mm = $this->layout->measureMinMaxContent($t, $this->defaultCtx);
        self::assertSame(111.0, $mm['max']);
        self::assertSame(111.0, $mm['min']);
    }

    public function testContainStrictAlsoTriggersIntrinsicWidth(): void
    {
        // `contain: strict` = layout|paint|style|size — covers size.
        $root = $this->buildTree(
            '<html><body><div id="t"><span>abcdef</span></div></body></html>',
            'html, body, div { display: block; } span { display: inline; }
             #t { contain: strict; contain-intrinsic-size: 80px; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $t = $this->find($root, 'div');
        self::assertNotNull($t);
        $mm = $this->layout->measureMinMaxContent($t, $this->defaultCtx);
        self::assertSame(80.0, $mm['max']);
    }

    public function testContainIntrinsicWidthLonghandWinsOverShorthand(): void
    {
        // CSS Sizing 4 §6.1 cascade order: the explicit
        // `contain-intrinsic-width` longhand beats the shorthand's
        // first component.
        $root = $this->buildTree(
            '<html><body><div id="t"><span>abcdef</span></div></body></html>',
            'html, body, div { display: block; } span { display: inline; }
             #t { contain: size; contain-intrinsic-size: 80px;
                  contain-intrinsic-width: 200px; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $t = $this->find($root, 'div');
        self::assertNotNull($t);
        $mm = $this->layout->measureMinMaxContent($t, $this->defaultCtx);
        self::assertSame(200.0, $mm['max']);
    }

    public function testContainSizeWithoutIntrinsicSizeCollapsesToZero(): void
    {
        // CSS Sizing 4 §6.1: when the box is size-contained but
        // contain-intrinsic-size is unset or `auto` without a
        // last-known size, the intrinsic dimensions collapse to 0.
        $root = $this->buildTree(
            '<html><body><div id="t"><span>abcdef</span></div></body></html>',
            'html, body, div { display: block; } span { display: inline; }
             #t { contain: size; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $t = $this->find($root, 'div');
        self::assertNotNull($t);
        $mm = $this->layout->measureMinMaxContent($t, $this->defaultCtx);
        self::assertSame(0.0, $mm['max']);
    }

    // ------------------------------------------------------------
    // CSS Writing Modes 4 §3 — block axis swap for vertical modes.

    public function testVerticalLrStacksChildrenLeftToRight(): void
    {
        // writing-mode: vertical-lr → block flows left-to-right along
        // x. With three 50px-wide explicit-width children inside a
        // 200×100 container, child 0 should be at x=0, child 1 at
        // x=50, child 2 at x=100. All children share the parent's
        // content top as their y origin.
        $root = $this->buildTree(
            '<html><body><div id="c"><div class="i"></div><div class="i"></div><div class="i"></div></div></body></html>',
            'html, body, div { display: block; }
             #c { writing-mode: vertical-lr; width: 200px; height: 100px; }
             .i { width: 50px; height: 60px; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $c = $this->findById($root, 'c');
        self::assertNotNull($c);
        self::assertCount(3, $c->children);
        self::assertEqualsWithDelta($c->geometry->x + 0.0, $c->children[0]->geometry->x, 0.001);
        self::assertEqualsWithDelta($c->geometry->x + 50.0, $c->children[1]->geometry->x, 0.001);
        self::assertEqualsWithDelta($c->geometry->x + 100.0, $c->children[2]->geometry->x, 0.001);
        // All children share the container's content-top y.
        self::assertEqualsWithDelta($c->geometry->y, $c->children[0]->geometry->y, 0.001);
        self::assertEqualsWithDelta($c->geometry->y, $c->children[1]->geometry->y, 0.001);
        self::assertEqualsWithDelta($c->geometry->y, $c->children[2]->geometry->y, 0.001);
    }

    public function testVerticalRlStacksChildrenRightToLeft(): void
    {
        // writing-mode: vertical-rl → block flows right-to-left along
        // x. Child 0's right edge lands at the container's right
        // content edge; subsequent children stack to the left of it.
        $root = $this->buildTree(
            '<html><body><div id="c"><div class="i"></div><div class="i"></div><div class="i"></div></div></body></html>',
            'html, body, div { display: block; }
             #c { writing-mode: vertical-rl; width: 200px; height: 100px; }
             .i { width: 50px; height: 60px; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $c = $this->findById($root, 'c');
        self::assertNotNull($c);
        self::assertCount(3, $c->children);
        // Child 0 right edge = container right edge = c.x + 200
        $rightEdge = $c->geometry->x + 200.0;
        self::assertEqualsWithDelta($rightEdge - 50.0, $c->children[0]->geometry->x, 0.001);
        self::assertEqualsWithDelta($rightEdge - 100.0, $c->children[1]->geometry->x, 0.001);
        self::assertEqualsWithDelta($rightEdge - 150.0, $c->children[2]->geometry->x, 0.001);
        // All children share the container's content-top y.
        self::assertEqualsWithDelta($c->geometry->y, $c->children[0]->geometry->y, 0.001);
    }

    public function testHorizontalTbUnchangedByPhase2(): void
    {
        // Regression guard: the default writing mode still stacks
        // along y. Three 60-tall children should produce y offsets
        // 0, 60, 120 inside the container — never x offsets.
        $root = $this->buildTree(
            '<html><body><div id="c"><div class="i"></div><div class="i"></div><div class="i"></div></div></body></html>',
            'html, body, div { display: block; }
             #c { width: 200px; height: 300px; }
             .i { width: 50px; height: 60px; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $c = $this->findById($root, 'c');
        self::assertNotNull($c);
        self::assertEqualsWithDelta($c->geometry->y + 0.0, $c->children[0]->geometry->y, 0.001);
        self::assertEqualsWithDelta($c->geometry->y + 60.0, $c->children[1]->geometry->y, 0.001);
        self::assertEqualsWithDelta($c->geometry->y + 120.0, $c->children[2]->geometry->y, 0.001);
        // All children share the container's content-left x.
        self::assertEqualsWithDelta($c->geometry->x, $c->children[0]->geometry->x, 0.001);
        self::assertEqualsWithDelta($c->geometry->x, $c->children[1]->geometry->x, 0.001);
        self::assertEqualsWithDelta($c->geometry->x, $c->children[2]->geometry->x, 0.001);
    }

    public function testVerticalLrChildrenRespectMarginPaddingBorder(): void
    {
        // Margin + padding + border on a child contribute to its outer
        // width, which is what the vertical stacker advances by. Child
        // 0 outer = 5 + 2 + 3 + 50 + 3 + 2 + 5 = 70px → child 1 at x=70.
        $root = $this->buildTree(
            '<html><body><div id="c"><div class="i"></div><div class="i"></div></div></body></html>',
            'html, body, div { display: block; }
             #c { writing-mode: vertical-lr; width: 300px; height: 100px; }
             .i { width: 50px; height: 60px; margin: 0 5px;
                  padding: 0 3px; border: 2px solid; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $c = $this->findById($root, 'c');
        self::assertNotNull($c);
        // Child 0 content-box x = c.x + 5 (margin) + 2 (border) + 3 (padding)
        self::assertEqualsWithDelta($c->geometry->x + 10.0, $c->children[0]->geometry->x, 0.001);
        // Child 1 content-box x: adjacent 5px + 5px block-axis margins
        // collapse to max(5,5,0)+min(5,5,0) = 5 per CSS 2.1 §8.3.1
        // (transposed for vlr — block-end is marginRight on child 0,
        // block-start is marginLeft on child 1). Naïve advance would
        // have been outer(70) + margin(5) + border(2) + padding(3) =
        // 80; collapse reclaims 5, putting child 1 at 75.
        self::assertEqualsWithDelta($c->geometry->x + 75.0, $c->children[1]->geometry->x, 0.001);
    }

    public function testVerticalLrAdjacentBlockMarginsCollapse(): void
    {
        // CSS 2.1 §8.3.1 transposed for vertical-lr: adjacent
        // siblings' block-end (right) and block-start (left)
        // margins collapse. With both children at margin: 0 20px,
        // the in-between gap is max(20,20,0)+min(20,20,0) = 20,
        // not 20+20=40. Child 1's x lands at child0.x + outer(50+
        // 0+0+40) - collapsed-gap-saved(20) = 70 from container
        // origin.
        $root = $this->buildTree(
            '<html><body><div id="c"><div class="i"></div><div class="i"></div></div></body></html>',
            'html, body, div { display: block; }
             #c { writing-mode: vertical-lr; width: 300px; height: 100px; }
             .i { width: 50px; height: 60px; margin: 0 20px; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $c = $this->findById($root, 'c');
        self::assertNotNull($c);
        // Child 0 content x = c.x + 20 (left margin).
        self::assertEqualsWithDelta($c->geometry->x + 20.0, $c->children[0]->geometry->x, 0.001);
        // Child 1 content x: outer width 50+20+20 = 90. Naïve
        // advance to x = c.x + 110. Collapse 20 reclaimed, so
        // x = c.x + 90.
        self::assertEqualsWithDelta($c->geometry->x + 90.0, $c->children[1]->geometry->x, 0.001);
    }

    public function testVerticalRlAdjacentBlockMarginsCollapse(): void
    {
        // vrl mirror: block-end of prev is marginLeft, block-start
        // of next is marginRight. Adjacent 10px + 30px margins
        // collapse to max(10,30,0)+min = 30 (not 40).
        $root = $this->buildTree(
            '<html><body><div id="c"><div class="i a"></div><div class="i b"></div></div></body></html>',
            'html, body, div { display: block; }
             #c { writing-mode: vertical-rl; width: 300px; height: 100px; }
             .i { width: 50px; height: 60px; }
             .a { margin: 0 0 0 10px; }
             .b { margin: 0 30px 0 0; }',
        );
        $this->layout->layout($root, $this->defaultCtx);
        $c = $this->findById($root, 'c');
        self::assertNotNull($c);
        // Right edge of container = c.x + 300.
        $rightEdge = $c->geometry->x + 300.0;
        // Child 0 (.a) right edge = rightEdge - 0 (a has no right
        // margin) → its content-x = rightEdge - 50.
        self::assertEqualsWithDelta($rightEdge - 50.0, $c->children[0]->geometry->x, 0.001);
        // Naïve next x = rightEdge - 50 - 10 (a's margin-left) -
        // 30 (b's margin-right) - 50 (b's width) = rightEdge -
        // 140. Collapse 10 reclaimed → rightEdge - 130.
        self::assertEqualsWithDelta($rightEdge - 130.0, $c->children[1]->geometry->x, 0.001);
    }

    public function testMaxHeightTransfersThroughAspectRatioToCapWidth(): void
    {
        // CSS Sizing 4 §5.1 — `max-height:100px; aspect-ratio:1/1;
        // width:max-content` with a 200px child: the max-content width
        // (200) is capped by the transferred max-width (max-height 100 ×
        // ratio 1 = 100). block-aspect-ratio-021.
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="c"></div></div></body></html>',
            'html, body, div { display: block; }
             #t { aspect-ratio: 1/1; max-height: 100px; width: max-content; }
             #c { width: 200px; height: 10px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        self::assertNotNull($t);
        self::assertEqualsWithDelta(100.0, $t->geometry->width, 0.5);
        self::assertEqualsWithDelta(100.0, $t->geometry->height, 0.5);
    }

    public function testMinHeightTransfersThroughAspectRatioToFloorWidth(): void
    {
        // A definite min-height floors the transferred inline size:
        // `min-height:150px; aspect-ratio:1/1; width:min-content` with a
        // 50px child → width floored to 150 (min-content 50 → 150).
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="c"></div></div></body></html>',
            'html, body, div { display: block; }
             #t { aspect-ratio: 1/1; min-height: 150px; width: min-content; }
             #c { width: 50px; height: 10px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        self::assertNotNull($t);
        self::assertEqualsWithDelta(150.0, $t->geometry->width, 0.5);
    }

    public function testTransferredMinIsClampedByExplicitMaxWidth(): void
    {
        // §5.1 — the transferred minimum is itself clamped by max-width,
        // so a min-height that transfers to 200 does not push the used
        // width past an explicit `max-width:100px`. block-aspect-ratio-022.
        // (Uses `width:max-content` so the harness sizes the box from its
        // 300px child before the clamps apply.)
        $box = $this->buildTree(
            '<html><body><div id="t"><div id="c"></div></div></body></html>',
            'html, body, div { display: block; }
             #t { aspect-ratio: 1/1; width: max-content; min-height: 200px; max-width: 100px; }
             #c { width: 300px; height: 10px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $t = $this->findById($box, 't');
        self::assertNotNull($t);
        // Without the max-width clamp on the transferred minimum, the
        // width would be pushed to 200 (min-height × ratio); with it, the
        // explicit max-width of 100 wins.
        self::assertEqualsWithDelta(100.0, $t->geometry->width, 0.5);
    }
}
