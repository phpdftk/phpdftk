<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\TreeConstruction;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Parser;
use PHPUnit\Framework\TestCase;

/**
 * XML empty-element syntax in XHTML sources.
 *
 * `text/html` ignores the trailing slash on a start tag (WHATWG §13.2.5.6
 * acknowledges the self-closing flag only for void and foreign elements),
 * so `<div/>` opens a div. XHTML is XML, where `<div/>` is an empty
 * element; parsing an `.xht` / `.xhtml` document with the HTML rule nests
 * every following sibling inside the first self-closed element.
 *
 * These tests pin both halves: HTML sources must keep the HTML behaviour,
 * XHTML sources get the XML one, and none of the special start tags that
 * drive the tokenizer's text modes or pop themselves may be disturbed.
 */
final class XhtmlSelfClosingTest extends TestCase
{
    private const XHTML_PROLOG = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

    private function parse(string $source): Document
    {
        return (new Parser())->parseDocument($source);
    }

    /** @return list<Element> */
    private function bodyChildren(Document $doc): array
    {
        $body = $doc->body;
        self::assertNotNull($body);
        return $body->children();
    }

    public function testHtmlSourceStillNestsSelfClosedNonVoidElements(): void
    {
        // The negative guard: in `text/html` the slash is a parse error and
        // is ignored, so the second div is a CHILD of the first. Sniffing
        // must not change this.
        $doc = $this->parse('<!DOCTYPE html><body><div id="a"/><div id="b"/></body>');
        $children = $this->bodyChildren($doc);
        self::assertCount(1, $children);
        self::assertSame('a', $children[0]->getAttribute('id'));
        $inner = $children[0]->children();
        self::assertCount(1, $inner);
        self::assertSame('b', $inner[0]->getAttribute('id'));
    }

    public function testXmlnsAttributeAloneDoesNotSwitchToXhtmlParsing(): void
    {
        // `xmlns` is legal in `text/html` and carries no parsing meaning
        // there, so it must not be treated as an XHTML signal on its own.
        $doc = $this->parse(
            '<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml">'
            . '<body><div id="a"/><div id="b"/></body></html>',
        );
        $children = $this->bodyChildren($doc);
        self::assertCount(1, $children);
        self::assertCount(1, $children[0]->children());
    }

    public function testXmlPrologMakesSelfClosedElementsEmpty(): void
    {
        $doc = $this->parse(
            self::XHTML_PROLOG
            . '<html xmlns="http://www.w3.org/1999/xhtml"><body>'
            . '<div id="a"/><div id="b"/><div id="c"/>'
            . '</body></html>',
        );
        $children = $this->bodyChildren($doc);
        self::assertCount(3, $children);
        self::assertSame(['a', 'b', 'c'], array_map(
            static fn(Element $e): string => $e->getAttribute('id') ?? '',
            $children,
        ));
        foreach ($children as $child) {
            self::assertSame([], $child->children());
        }
    }

    public function testXhtmlDoctypeWithoutPrologAlsoSwitches(): void
    {
        $doc = $this->parse(
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"'
            . ' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'
            . '<html xmlns="http://www.w3.org/1999/xhtml"><body>'
            . '<div id="a"/><div id="b"/></body></html>',
        );
        $children = $this->bodyChildren($doc);
        self::assertCount(2, $children);
    }

    public function testSelfClosedVoidElementDoesNotUnderflowTheStack(): void
    {
        // `<br/>`, `<meta/>`, `<link/>` are popped by their own handlers;
        // a second pop would empty the open-elements stack and throw.
        $doc = $this->parse(
            self::XHTML_PROLOG
            . '<html xmlns="http://www.w3.org/1999/xhtml"><head>'
            . '<meta charset="utf-8"/><link rel="author" href="#"/></head>'
            . '<body><br/><div id="a"/></body></html>',
        );
        $children = $this->bodyChildren($doc);
        self::assertSame(['br', 'div'], array_map(
            static fn(Element $e): string => $e->localName,
            $children,
        ));
        $head = $doc->head;
        self::assertNotNull($head);
        self::assertCount(2, $head->children());
    }

    public function testSelfClosedTextModeElementKeepsItsRawTextContent(): void
    {
        // `<style>` hands the tokenizer to RAWTEXT and the insertion mode to
        // Text, which expects its element to stay open. Popping it would
        // strand the mode on the wrong node and spill the CSS into the body.
        $doc = $this->parse(
            self::XHTML_PROLOG
            . '<html xmlns="http://www.w3.org/1999/xhtml"><head>'
            . '<style type="text/css">div { color: red; }</style></head>'
            . '<body><div id="a"/></body></html>',
        );
        $styles = $doc->getElementsByTagName('style');
        self::assertCount(1, $styles);
        self::assertStringContainsString('color: red', $styles[0]->textContent());
        self::assertCount(1, $this->bodyChildren($doc));
    }

    public function testSelfClosedFormattingElementDoesNotReopenLater(): void
    {
        // A self-closed `<b/>` must leave the active-formatting list clean;
        // otherwise the reconstruction step would wrap the following text in
        // a phantom bold element.
        $doc = $this->parse(
            self::XHTML_PROLOG
            . '<html xmlns="http://www.w3.org/1999/xhtml"><body>'
            . '<b/>plain</body></html>',
        );
        $body = $doc->body;
        self::assertNotNull($body);
        $bolds = $body->getElementsByTagName('b');
        self::assertCount(1, $bolds);
        self::assertSame('', $bolds[0]->textContent());
        self::assertStringContainsString('plain', $body->textContent());
    }

    public function testSelfClosedTableCellsStaySiblings(): void
    {
        $doc = $this->parse(
            self::XHTML_PROLOG
            . '<html xmlns="http://www.w3.org/1999/xhtml"><body>'
            . '<table><tr><td id="a"/><td id="b"/></tr></table></body></html>',
        );
        $cells = $doc->getElementsByTagName('td');
        self::assertCount(2, $cells);
        self::assertSame([], $cells[0]->children());
    }
}
