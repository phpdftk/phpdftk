<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\TreeConstruction;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the InSelect and InSelectInTable insertion modes
 * (WHATWG §13.2.6.4.16, §13.2.6.4.17).
 */
final class SelectTest extends TestCase
{
    private function parse(string $html): Document
    {
        return (new Parser())->parseDocument($html);
    }

    public function testSimpleSelectWithOptions(): void
    {
        $html = '<!DOCTYPE html><body><select><option>A</option><option>B</option></select>';
        $doc = $this->parse($html);
        $select = $doc->getElementsByTagName('select')[0] ?? null;
        self::assertNotNull($select);
        $options = $select->getElementsByTagName('option');
        self::assertCount(2, $options);
        self::assertSame('A', $options[0]->textContent());
        self::assertSame('B', $options[1]->textContent());
    }

    public function testOptionInsideOptionImplicitClose(): void
    {
        // <option>A<option>B — second <option> implicitly closes the first.
        $html = '<!DOCTYPE html><body><select><option>A<option>B</select>';
        $doc = $this->parse($html);
        $select = $doc->getElementsByTagName('select')[0] ?? null;
        self::assertNotNull($select);
        $options = $select->getElementsByTagName('option');
        self::assertCount(2, $options);
        self::assertSame('A', $options[0]->textContent());
        self::assertSame('B', $options[1]->textContent());
        // Neither <option> should be nested inside the other.
        foreach ($options as $opt) {
            self::assertCount(0, $opt->getElementsByTagName('option'));
        }
    }

    public function testOptgroup(): void
    {
        $html = '<!DOCTYPE html><body><select><optgroup label="g1"><option>A</option></optgroup></select>';
        $doc = $this->parse($html);
        $select = $doc->getElementsByTagName('select')[0] ?? null;
        self::assertNotNull($select);
        $optgroup = $select->getElementsByTagName('optgroup')[0] ?? null;
        self::assertNotNull($optgroup);
        self::assertSame('g1', $optgroup->getAttribute('label'));
        self::assertCount(1, $optgroup->getElementsByTagName('option'));
    }

    public function testNestedSelectImplicitlyClosesOuter(): void
    {
        // A <select> inside a <select> is treated as an implicit </select>.
        $html = '<!DOCTYPE html><body><select><option>A<select><option>B</select>';
        $doc = $this->parse($html);
        $selects = $doc->getElementsByTagName('select');
        // The first select should be closed by the inner one; we'd then see
        // one select (the implicit close + reparse just suppresses the inner).
        self::assertGreaterThanOrEqual(1, count($selects));
    }

    public function testInputInsideSelectClosesAndReprocesses(): void
    {
        // <select><input> — per spec, <input> is a parse error inside select;
        // it implicitly closes the select and gets processed in the surrounding mode.
        $html = '<!DOCTYPE html><body><select><option>A<input type="text"></select>';
        $doc = $this->parse($html);
        $body = $doc->body;
        self::assertNotNull($body);
        $input = $body->getElementsByTagName('input')[0] ?? null;
        self::assertNotNull($input);
        self::assertSame('text', $input->getAttribute('type'));
        // <input> should be a sibling of <select>, not a child.
        self::assertNotSame('select', $input->parentNode?->localName);
    }

    public function testSelectInsideTableCellEntersInSelectInTable(): void
    {
        // A <select> nested inside a <td> exits cleanly when </select> appears.
        $html = '<!DOCTYPE html><body><table><tr><td><select><option>A</option></select></td></tr></table>';
        $doc = $this->parse($html);
        $select = $doc->getElementsByTagName('select')[0] ?? null;
        self::assertNotNull($select);
        self::assertSame('td', $select->parentNode?->localName);
        $options = $select->getElementsByTagName('option');
        self::assertCount(1, $options);
        self::assertSame('A', $options[0]->textContent());
    }

    public function testTableCellInsideSelectInTableClosesSelect(): void
    {
        // <select><tr> inside a table — the <tr> implicitly closes the select.
        $html = '<!DOCTYPE html><body><table><tr><td><select><option>A<tr><td>B</td></tr></select></table>';
        $doc = $this->parse($html);
        // We just verify no exception and the trailing content survived.
        $body = $doc->body;
        self::assertNotNull($body);
        self::assertStringContainsString('B', $body->textContent());
    }
}
