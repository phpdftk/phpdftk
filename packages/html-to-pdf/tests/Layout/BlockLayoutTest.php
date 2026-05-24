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
