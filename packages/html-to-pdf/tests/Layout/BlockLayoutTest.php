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
        // Negative: the proportional share would push width below 0;
        // implementation clamps so the item just becomes 0 wide
        // rather than going negative. Setup: heavily-weighted shrink
        // on a tiny item (a=10, shrink 99) splits the 60pt overflow
        // 99:1 with b, so a's share of 59.4 exceeds a's 10pt width.
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
        // b takes its proportional 0.6pt reduction → 99.4 wide.
        self::assertEqualsWithDelta(99.4, $this->flexItemWidth($flex, 'b'), 0.001);
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
}
