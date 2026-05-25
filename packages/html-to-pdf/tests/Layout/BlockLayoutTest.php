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
use Phpdftk\HtmlToPdf\Layout\BlockLayout;
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
             table { display: table; }
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
             table { display: table; }
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
             table { display: table; }
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

    public function testColumnCountZeroClampsToSingleColumn(): void
    {
        $box = $this->buildTree(
            '<html><body><section><div></div></section></body></html>',
            'html, body, section, div { display: block; }
             section { column-count: 0; }
             div { height: 40px; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNotNull($section->multiColumn);
        self::assertSame(1, $section->multiColumn->columnCount);
        // Degenerate single-column → child takes the container's full width.
        self::assertEqualsWithDelta($section->geometry->width, $section->multiColumn->columnWidth, 0.001);
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

    public function testMultiColumnIgnoredOnInlineOnlyChildren(): void
    {
        // `<section>` has only an inline child — multi-column doesn't
        // apply because there are no block-level fragmentainers to split.
        $box = $this->buildTree(
            '<html><body><section>inline text</section></body></html>',
            'html, body, section { display: block; }
             section { column-count: 2; }',
        );
        $this->layout->layout($box, $this->defaultCtx);
        $section = $this->find($box, 'section');
        self::assertNotNull($section);
        self::assertNull($section->multiColumn);
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
            '<html><body><table>'
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
            '<html><body><table>'
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
            '<html><body><table>'
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
            '<html><body><table>'
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
            '<html><body><table>'
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
            '<html><body><table>'
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
            '<html><body><table>'
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
            '<html><body><table>'
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
            '<html><body><table>'
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
            '<html><body><table>'
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
