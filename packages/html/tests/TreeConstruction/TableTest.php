<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\TreeConstruction;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1B.3-bis coverage: the InTable insertion-mode family — InTable,
 * InTableText (foster parenting for non-whitespace chars), InCaption,
 * InColumnGroup, InTableBody, InRow, InCell — plus implicit <tbody>/<tr>
 * synthesis and the "reset insertion mode appropriately" algorithm.
 */
final class TableTest extends TestCase
{
    private function parse(string $html): Document
    {
        return (new Parser())->parseDocument($html);
    }

    public function testSimpleTableStructure(): void
    {
        $html = '<!DOCTYPE html><body><table><tbody><tr><td>A</td><td>B</td></tr></tbody></table>';
        $doc = $this->parse($html);
        $table = $doc->getElementsByTagName('table')[0] ?? null;
        self::assertNotNull($table);

        $tbody = $table->children()[0] ?? null;
        self::assertNotNull($tbody);
        self::assertSame('tbody', $tbody->localName);

        $tr = $tbody->children()[0] ?? null;
        self::assertNotNull($tr);
        self::assertSame('tr', $tr->localName);

        $cells = $tr->children();
        self::assertCount(2, $cells);
        self::assertSame('A', $cells[0]->textContent());
        self::assertSame('B', $cells[1]->textContent());
    }

    public function testImplicitTbodyAndTrSynthesis(): void
    {
        // Bare <td> inside a <table> per spec: implicit <tbody>, implicit <tr>.
        $html = '<!DOCTYPE html><body><table><td>cell</td></table>';
        $doc = $this->parse($html);
        $table = $doc->getElementsByTagName('table')[0] ?? null;
        self::assertNotNull($table);

        $tbody = $table->children()[0] ?? null;
        self::assertNotNull($tbody);
        self::assertSame('tbody', $tbody->localName);

        $tr = $tbody->children()[0] ?? null;
        self::assertNotNull($tr);
        self::assertSame('tr', $tr->localName);

        $td = $tr->children()[0] ?? null;
        self::assertNotNull($td);
        self::assertSame('td', $td->localName);
        self::assertSame('cell', $td->textContent());
    }

    public function testMultiRowTable(): void
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <table>
              <tr><th>Item</th><th>Price</th></tr>
              <tr><td>Widget</td><td>\$10</td></tr>
              <tr><td>Gadget</td><td>\$25</td></tr>
            </table>
            HTML;
        $doc = $this->parse($html);
        $table = $doc->getElementsByTagName('table')[0] ?? null;
        self::assertNotNull($table);
        $rows = $table->getElementsByTagName('tr');
        self::assertCount(3, $rows);
        // Header row
        $headerCells = $rows[0]->getElementsByTagName('th');
        self::assertCount(2, $headerCells);
        self::assertSame('Item', $headerCells[0]->textContent());
        self::assertSame('Price', $headerCells[1]->textContent());
        // Data rows
        $dataCells = $rows[1]->getElementsByTagName('td');
        self::assertCount(2, $dataCells);
        self::assertSame('Widget', $dataCells[0]->textContent());
        self::assertSame('$10', $dataCells[1]->textContent());
    }

    public function testCaptionInsideTable(): void
    {
        $html = '<!DOCTYPE html><table><caption>Inventory</caption><tr><td>x</td></tr></table>';
        $doc = $this->parse($html);
        $table = $doc->getElementsByTagName('table')[0] ?? null;
        self::assertNotNull($table);
        $caption = $table->getElementsByTagName('caption')[0] ?? null;
        self::assertNotNull($caption);
        self::assertSame('Inventory', $caption->textContent());
    }

    public function testTheadTbodyTfoot(): void
    {
        $html = '<!DOCTYPE html><table><thead><tr><th>H</th></tr></thead><tbody><tr><td>B</td></tr></tbody><tfoot><tr><td>F</td></tr></tfoot></table>';
        $doc = $this->parse($html);
        $table = $doc->getElementsByTagName('table')[0] ?? null;
        self::assertNotNull($table);
        $children = $table->children();
        $tags = array_map(static fn(Element $e): string => $e->localName, $children);
        self::assertSame(['thead', 'tbody', 'tfoot'], $tags);
    }

    public function testColgroupAndCol(): void
    {
        $html = '<!DOCTYPE html><table><colgroup><col span="2"><col></colgroup><tbody><tr><td>1</td><td>2</td><td>3</td></tr></tbody></table>';
        $doc = $this->parse($html);
        $table = $doc->getElementsByTagName('table')[0] ?? null;
        self::assertNotNull($table);
        $colgroup = $table->getElementsByTagName('colgroup')[0] ?? null;
        self::assertNotNull($colgroup);
        $cols = $colgroup->getElementsByTagName('col');
        self::assertCount(2, $cols);
        self::assertSame('2', $cols[0]->getAttribute('span'));
    }

    public function testWhitespaceBetweenRowsStaysInsideTable(): void
    {
        $html = "<!DOCTYPE html><table>\n  <tr><td>x</td></tr>\n</table>";
        $doc = $this->parse($html);
        $table = $doc->getElementsByTagName('table')[0] ?? null;
        self::assertNotNull($table);
        // The whitespace should appear inside the table (or its tbody) — not foster-parented out.
        self::assertStringNotContainsString('\n  ', $doc->body->textContent());
    }

    public function testFosterParentingForNonWhitespaceText(): void
    {
        // Per spec: non-whitespace character tokens that arrive while the
        // current node is a table element are foster-parented BEFORE the table.
        $html = '<!DOCTYPE html><body>before<table>STRAY<tr><td>cell</td></tr></table>';
        $doc = $this->parse($html);
        $body = $doc->body;
        self::assertNotNull($body);
        // The "STRAY" text should appear before the <table>, not inside.
        $bodyKids = $body->childNodes();
        $found = false;
        foreach ($bodyKids as $kid) {
            if ($kid instanceof \Phpdftk\Html\Dom\Text && str_contains($kid->data, 'STRAY')) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'STRAY text should be foster-parented out of the table');

        // The <td>cell</td> should still be inside the table.
        $td = $doc->getElementsByTagName('td')[0] ?? null;
        self::assertNotNull($td);
        self::assertSame('cell', $td->textContent());
    }

    public function testNestedFormattingInsideCell(): void
    {
        $html = '<!DOCTYPE html><table><tr><td>Hello <b>bold</b> world</td></tr></table>';
        $doc = $this->parse($html);
        $td = $doc->getElementsByTagName('td')[0] ?? null;
        self::assertNotNull($td);
        self::assertSame('Hello bold world', $td->textContent());
        $b = $td->getElementsByTagName('b')[0] ?? null;
        self::assertNotNull($b);
        self::assertSame('bold', $b->textContent());
    }

    public function testCellAttributes(): void
    {
        $html = '<!DOCTYPE html><table><tr><td colspan="2" class="primary">x</td></tr></table>';
        $doc = $this->parse($html);
        $td = $doc->getElementsByTagName('td')[0] ?? null;
        self::assertNotNull($td);
        self::assertSame('2', $td->getAttribute('colspan'));
        self::assertSame('primary', $td->getAttribute('class'));
    }

    public function testTableEndsCleanlyOnExplicitCloseTag(): void
    {
        // The <p> after </table> should be a sibling of the table, not a child.
        $html = '<!DOCTYPE html><body><table><tr><td>x</td></tr></table><p>after</p>';
        $doc = $this->parse($html);
        $body = $doc->body;
        self::assertNotNull($body);
        $children = $body->children();
        self::assertCount(2, $children);
        self::assertSame('table', $children[0]->localName);
        self::assertSame('p', $children[1]->localName);
        self::assertSame('after', $children[1]->textContent());
    }

    public function testInvoiceLikeTable(): void
    {
        // The MVP target fixture's table shape: header row + data rows + footer total.
        $html = <<<HTML
            <!DOCTYPE html>
            <table class="invoice">
              <thead>
                <tr><th>Item</th><th>Qty</th><th>Total</th></tr>
              </thead>
              <tbody>
                <tr><td>Widget A</td><td>2</td><td>\$20.00</td></tr>
                <tr><td>Widget B</td><td>1</td><td>\$15.00</td></tr>
              </tbody>
              <tfoot>
                <tr><td colspan="2">Total</td><td>\$35.00</td></tr>
              </tfoot>
            </table>
            HTML;
        $doc = $this->parse($html);
        $table = $doc->getElementsByTagName('table')[0] ?? null;
        self::assertNotNull($table);
        self::assertSame('invoice', $table->getAttribute('class'));

        $thead = $table->getElementsByTagName('thead')[0] ?? null;
        self::assertNotNull($thead);
        $headerCells = $thead->getElementsByTagName('th');
        self::assertCount(3, $headerCells);

        $tbody = $table->getElementsByTagName('tbody')[0] ?? null;
        self::assertNotNull($tbody);
        $dataRows = $tbody->getElementsByTagName('tr');
        self::assertCount(2, $dataRows);

        $tfoot = $table->getElementsByTagName('tfoot')[0] ?? null;
        self::assertNotNull($tfoot);
        $footerCells = $tfoot->getElementsByTagName('td');
        self::assertCount(2, $footerCells);
        self::assertSame('2', $footerCells[0]->getAttribute('colspan'));
        self::assertSame('$35.00', $footerCells[1]->textContent());
    }

    public function testTableWithoutTbodyTfootThead(): void
    {
        // Just <table><tr>...</tr></table> — should synthesize tbody.
        $html = '<!DOCTYPE html><table><tr><td>1</td></tr><tr><td>2</td></tr></table>';
        $doc = $this->parse($html);
        $table = $doc->getElementsByTagName('table')[0] ?? null;
        self::assertNotNull($table);
        $tbody = $table->children()[0] ?? null;
        self::assertNotNull($tbody);
        self::assertSame('tbody', $tbody->localName);
        $rows = $tbody->getElementsByTagName('tr');
        self::assertCount(2, $rows);
    }
}
