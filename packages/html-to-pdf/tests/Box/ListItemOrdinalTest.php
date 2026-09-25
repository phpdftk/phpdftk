<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Html\Parser as HtmlParser;
use PHPUnit\Framework\TestCase;

/**
 * HTML Standard, "ordinal value" and "list owner"
 * (https://html.spec.whatwg.org/multipage/grouping-content.html#ordinal-value).
 *
 * The LIST OWNER of a list item is the NEAREST ANCESTOR `ol` / `ul` /
 * `menu` that generates boxes — not its parent node. Four consequences
 * this suite pins, each of which the old parent-and-siblings walk got
 * wrong:
 *
 *  1. Wrappers are TRANSPARENT. `<ol><li>A<div><li>B</li></div><li>C`
 *     numbers 1, 2, 3 — the `div` is not a list owner, so it neither
 *     restarts nor scopes the count.
 *  2. Only `ol` / `ul` / `menu` own. `<dir>` does not, despite being a
 *     list-ish legacy element, so items inside one keep counting in the
 *     enclosing list.
 *  3. An owner that generates NO BOXES is skipped — `display: contents`
 *     on an `ol` means its items belong to the list further out.
 *  4. Items that generate no boxes are not counted at all, so a `hidden`
 *     item does not consume an ordinal, in either direction.
 *
 * And the item does not have to be an `li`: ANY element with
 * `display: list-item` takes an ordinal from its list owner.
 */
final class ListItemOrdinalTest extends TestCase
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

    /**
     * Render `$bodyHtml` against the shipped UA sheet and return the
     * ordinal of every `display: list-item` box, in document order,
     * labelled by the box's text content so a mis-ordering is legible.
     *
     * @return array<string, int>
     */
    private function ordinals(string $bodyHtml, string $authorCss = ''): array
    {
        $sheets = [$this->css->parseStylesheet(
            (new RendererOptions())->effectiveUserAgentStylesheet(),
            Origin::UserAgent,
        )];
        if ($authorCss !== '') {
            $sheets[] = $this->css->parseStylesheet($authorCss, Origin::Author);
        }
        $doc = $this->html->parseDocument('<!doctype html><html><body>' . $bodyHtml . '</body></html>');
        $root = $this->generator->generate($doc, $sheets);
        self::assertNotNull($root);
        $out = [];
        $this->collect($root, $out);
        return $out;
    }

    /** @param array<string, int> $out */
    private function collect(Box $root, array &$out): void
    {
        $display = $root->style->get('display');
        // A TextBox child INHERITS the item's cascade, display included,
        // so match on the principal box only — otherwise the item's own
        // entry is immediately overwritten by its text.
        if ($display instanceof Keyword
            && strtolower($display->name) === 'list-item'
            && !$root instanceof \Phpdftk\HtmlToPdf\Box\TextBox
            && $root->element !== null
        ) {
            $label = trim($root->element->getAttribute('data-label') ?? '');
            if ($label !== '') {
                $out[$label] = $root->listItemOrdinal ?? -1;
            }
        }
        foreach ($root->children as $child) {
            $this->collect($child, $out);
        }
    }

    public function testWrapperElementsAreTransparentToTheCount(): void
    {
        // The `div` and `span` are not list owners, so C, D and E keep
        // counting in the enclosing `ol` instead of restarting at 1.
        $got = $this->ordinals(<<<'HTML'
            <ol>
              <li data-label=A>A</li>
              <li data-label=B>B</li>
              <div>
                <li data-label=C>C</li>
                <span>
                  <li data-label=D>D</li>
                  <li data-label=E>E</li>
                </span>
              </div>
              <li data-label=F>F</li>
            </ol>
        HTML);
        self::assertSame(['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6], $got);
    }

    public function testNestedListStartsItsOwnCountAndTheOuterResumes(): void
    {
        $got = $this->ordinals(<<<'HTML'
            <ol>
              <li data-label=A>A</li>
              <ol>
                <li data-label=N1>N1</li>
                <li data-label=N2>N2</li>
              </ol>
              <li data-label=B>B</li>
            </ol>
        HTML);
        self::assertSame(['A' => 1, 'N1' => 1, 'N2' => 2, 'B' => 2], $got);
    }

    public function testDirIsNotAListOwner(): void
    {
        // `<dir>` is a legacy list-ish element but NOT one of the three
        // list owners, so F and G continue the enclosing `ol`.
        $got = $this->ordinals(<<<'HTML'
            <ol>
              <li data-label=A>A</li>
              <dir>
                <li data-label=F>F</li>
                <li data-label=G>G</li>
              </dir>
              <li data-label=H>H</li>
            </ol>
        HTML);
        self::assertSame(['A' => 1, 'F' => 2, 'G' => 3, 'H' => 4], $got);
    }

    public function testMenuAndUlAreListOwners(): void
    {
        $got = $this->ordinals(<<<'HTML'
            <menu>
              <li data-label=A>A</li>
              <ul>
                <li data-label=N1>N1</li>
              </ul>
              <li data-label=B>B</li>
            </menu>
        HTML);
        self::assertSame(['A' => 1, 'N1' => 1, 'B' => 2], $got);
    }

    public function testAnOwnerThatGeneratesNoBoxesIsSkipped(): void
    {
        // `display: contents` on the inner `ol` means it owns nothing —
        // F and G belong to the outer list.
        $got = $this->ordinals(<<<'HTML'
            <ol>
              <li data-label=A>A</li>
              <ol style="display: contents">
                <li data-label=F>F</li>
                <li data-label=G>G</li>
              </ol>
              <li data-label=H>H</li>
            </ol>
        HTML);
        self::assertSame(['A' => 1, 'F' => 2, 'G' => 3, 'H' => 4], $got);
    }

    public function testItemsThatGenerateNoBoxesConsumeNoOrdinal(): void
    {
        $got = $this->ordinals(<<<'HTML'
            <ol>
              <li data-label=A>A</li>
              <li hidden>skipped</li>
              <li data-label=B>B</li>
            </ol>
        HTML);
        self::assertSame(['A' => 1, 'B' => 2], $got);
    }

    public function testAnyDisplayListItemElementTakesAnOrdinal(): void
    {
        // Not just `<li>`: CSS says the marker belongs to any
        // `display: list-item` box, and it counts in the same list.
        $got = $this->ordinals(
            <<<'HTML'
                <ul>
                  <span class=li data-label=A>A</span>
                  <span data-label=plain>not an item</span>
                  <span class=li data-label=B>B</span>
                </ul>
            HTML,
            '.li { display: list-item; }',
        );
        self::assertSame(['A' => 1, 'B' => 2], $got);
    }

    public function testValueAttributeResetsTheRunningCount(): void
    {
        $got = $this->ordinals(<<<'HTML'
            <ol>
              <li data-label=A>A</li>
              <li data-label=B value="7">B</li>
              <li data-label=C>C</li>
            </ol>
        HTML);
        self::assertSame(['A' => 1, 'B' => 7, 'C' => 8], $got);
    }

    public function testStartAttributeSeedsTheCount(): void
    {
        $got = $this->ordinals(<<<'HTML'
            <ol start="-9">
              <li data-label=A>A</li>
              <li data-label=B>B</li>
            </ol>
        HTML);
        self::assertSame(['A' => -9, 'B' => -8], $got);
    }

    public function testReversedCountsDownOverOwnedItemsAcrossWrappers(): void
    {
        // The count the reversed list starts from is the number of items
        // it OWNS — which includes the two inside the `div`.
        $got = $this->ordinals(<<<'HTML'
            <ol reversed>
              <li data-label=A>A</li>
              <div>
                <li data-label=B>B</li>
                <li data-label=C>C</li>
              </div>
            </ol>
        HTML);
        self::assertSame(['A' => 3, 'B' => 2, 'C' => 1], $got);
    }

    public function testReversedDoesNotCountItemsThatGenerateNoBoxes(): void
    {
        $got = $this->ordinals(<<<'HTML'
            <ol reversed>
              <li data-label=A>A</li>
              <li hidden>skipped</li>
              <li data-label=B>B</li>
              <li data-label=C>C</li>
            </ol>
        HTML);
        self::assertSame(['A' => 3, 'B' => 2, 'C' => 1], $got);
    }

    public function testReversedDoesNotCountItemsOwnedByANestedList(): void
    {
        $got = $this->ordinals(<<<'HTML'
            <ol reversed>
              <li data-label=A>A</li>
              <ol>
                <li data-label=N1>N1</li>
                <li data-label=N2>N2</li>
              </ol>
              <li data-label=B>B</li>
            </ol>
        HTML);
        self::assertSame(['A' => 2, 'N1' => 1, 'N2' => 2, 'B' => 1], $got);
    }

    public function testReversedWithExplicitStartCountsDownFromIt(): void
    {
        $got = $this->ordinals(<<<'HTML'
            <ol reversed start="5">
              <li data-label=A>A</li>
              <li data-label=B>B</li>
            </ol>
        HTML);
        self::assertSame(['A' => 5, 'B' => 4], $got);
    }

    public function testItemsWithNoListAncestorAreOwnedByTheirParent(): void
    {
        // The second limb of HTML's list-owner rule: with no `ol` / `ul` /
        // `menu` ancestor, the owner is the item's PARENT element. So the
        // two wrappers number independently.
        $got = $this->ordinals(<<<'HTML'
            <div>
              <li data-label=A>A</li>
              <li data-label=B>B</li>
            </div>
            <div>
              <li data-label=C>C</li>
              <li data-label=D>D</li>
            </div>
        HTML);
        self::assertSame(['A' => 1, 'B' => 2, 'C' => 1, 'D' => 2], $got);
    }

    public function testTheParentFallbackAppliesPerWrapperNotPerDocument(): void
    {
        // A wrapper interrupting a run of bare items restarts at 1, and
        // the run around it resumes where it left off — the two are
        // different owners, not one document-wide count.
        $got = $this->ordinals(<<<'HTML'
            <li data-label=A>A</li>
            <li data-label=B>B</li>
            <div><li data-label=W>W</li></div>
            <li data-label=C>C</li>
        HTML);
        self::assertSame(['A' => 1, 'B' => 2, 'W' => 1, 'C' => 3], $got);
    }

    public function testAListAncestorBeatsTheParentFallbackThroughWrappers(): void
    {
        // The parent fallback applies ONLY when there is no list ancestor.
        // Inside an `<ol>`, a wrapper is transparent again.
        $got = $this->ordinals(<<<'HTML'
            <ol>
              <li data-label=A>A</li>
              <div><li data-label=W>W</li></div>
              <li data-label=C>C</li>
            </ol>
        HTML);
        self::assertSame(['A' => 1, 'W' => 2, 'C' => 3], $got);
    }

    public function testAnEmptyListDoesNotDisturbTheCountAroundIt(): void
    {
        // A list opens a scope of its own, so seeding it with `start`
        // cannot leak into the count outside it — even when it holds no
        // items to spend that seed on.
        $got = $this->ordinals(<<<'HTML'
            <div><ol start="99"></ol></div>
            <li data-label=A>A</li>
            <li data-label=B>B</li>
        HTML);
        self::assertSame(['A' => 1, 'B' => 2], $got);
    }

    public function testAZeroCounterIncrementItemTakesNoOrdinalFromTheList(): void
    {
        // CSS Lists 3 §4 — `display: list-item` implies
        // `counter-increment: list-item 1`, and declaring 0 opts out. This
        // is exactly how the UA sheet lets a `<summary>` carry a
        // disclosure marker inside an `<ol>` without stealing a number
        // from the items around it.
        $got = $this->ordinals(
            <<<'HTML'
                <ol>
                  <li data-label=A>A</li>
                  <span class=quiet data-label=Q>quiet</span>
                  <li data-label=B>B</li>
                </ol>
            HTML,
            '.quiet { display: list-item; counter-increment: list-item 0; }',
        );
        self::assertSame(['A' => 1, 'Q' => 1, 'B' => 2], $got);
    }

    public function testSummaryInsideAnOrderedListDoesNotConsumeAnOrdinal(): void
    {
        // The shipped UA sheet's own `details > summary:first-of-type`
        // rule, end to end: the summary is a list item but its
        // `counter-increment: list-item 0` keeps the `<li>`s numbered
        // 1, 2, 3 rather than 2, 4, 6.
        $got = $this->ordinals(<<<'HTML'
            <ol>
              <details><summary data-label=S1>s</summary></details>
              <li data-label=A>1</li>
              <li data-label=B>2 <details><summary data-label=S2>s</summary></details></li>
              <li data-label=C>3</li>
            </ol>
        HTML);
        self::assertSame(1, $got['A'], 'summary ahead of the list must not consume an ordinal');
        self::assertSame(2, $got['B']);
        self::assertSame(3, $got['C']);
    }

    public function testReversedIgnoresZeroIncrementItemsInItsStartingCount(): void
    {
        $got = $this->ordinals(
            <<<'HTML'
                <ol reversed>
                  <li data-label=A>A</li>
                  <span class=quiet data-label=Q>quiet</span>
                  <li data-label=B>B</li>
                </ol>
            HTML,
            '.quiet { display: list-item; counter-increment: list-item 0; }',
        );
        self::assertSame(2, $got['A'], 'the zero-increment item is not one of the two counted');
        self::assertSame(1, $got['B']);
    }
}
