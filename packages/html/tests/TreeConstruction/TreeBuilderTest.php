<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\TreeConstruction;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\DocumentMode;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Parser;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tree-construction tests: drives Parser::parseDocument and
 * inspects the resulting DOM. Verifies the implemented insertion modes
 * (Initial, BeforeHtml, BeforeHead, InHead, AfterHead, InBody, Text,
 * AfterBody, AfterAfterBody) produce the expected document shape.
 */
final class TreeBuilderTest extends TestCase
{
    private function parse(string $html): Document
    {
        return (new Parser())->parseDocument($html);
    }

    public function testDoctypeProducesNoQuirksMode(): void
    {
        $doc = $this->parse('<!DOCTYPE html><html></html>');
        self::assertSame(DocumentMode::NoQuirks, $doc->mode);
        self::assertNotNull($doc->doctype);
        self::assertSame('html', $doc->doctype->name);
    }

    public function testMissingDoctypeForcesQuirksMode(): void
    {
        $doc = $this->parse('<html></html>');
        self::assertSame(DocumentMode::Quirks, $doc->mode);
    }

    public function testImplicitHtmlHeadAndBodyCreated(): void
    {
        $doc = $this->parse('<!DOCTYPE html>');
        self::assertNotNull($doc->documentElement);
        self::assertSame('html', $doc->documentElement->localName);
    }

    public function testExplicitHtmlHeadBody(): void
    {
        $doc = $this->parse('<!DOCTYPE html><html><head></head><body></body></html>');
        self::assertNotNull($doc->head);
        self::assertNotNull($doc->body);
        self::assertSame('head', $doc->head->localName);
        self::assertSame('body', $doc->body->localName);
    }

    public function testTitleInHeadIsTextContent(): void
    {
        $doc = $this->parse('<!DOCTYPE html><html><head><title>Hello</title></head><body></body></html>');
        self::assertSame('Hello', $doc->title);
    }

    public function testTitleAllowsEntitiesAndAngleBracketsAsRcdata(): void
    {
        $doc = $this->parse('<!DOCTYPE html><title>1 &lt; 2 &amp; 3 &gt; <not a tag></title>');
        self::assertSame('1 < 2 & 3 > <not a tag>', $doc->title);
    }

    public function testHeadingsAndParagraphs(): void
    {
        $doc = $this->parse('<!DOCTYPE html><body><h1>Title</h1><p>Para one</p><p>Para two</p></body>');
        $body = $doc->body;
        self::assertNotNull($body);
        $children = $body->children();
        self::assertCount(3, $children);
        self::assertSame('h1', $children[0]->localName);
        self::assertSame('Title', $children[0]->textContent());
        self::assertSame('p', $children[1]->localName);
        self::assertSame('Para one', $children[1]->textContent());
        self::assertSame('p', $children[2]->localName);
        self::assertSame('Para two', $children[2]->textContent());
    }

    public function testImplicitParagraphClose(): void
    {
        // <p>A<p>B</p> — the second <p> implicitly closes the first.
        $doc = $this->parse('<!DOCTYPE html><body><p>A<p>B</p></body>');
        $body = $doc->body;
        self::assertNotNull($body);
        $ps = $body->children();
        self::assertCount(2, $ps);
        self::assertSame('p', $ps[0]->localName);
        self::assertSame('A', $ps[0]->textContent());
        self::assertSame('B', $ps[1]->textContent());
    }

    public function testInlineFormatting(): void
    {
        $doc = $this->parse('<!DOCTYPE html><body><p>This is <b>bold</b> and <em>emphasised</em>.</p>');
        $body = $doc->body;
        self::assertNotNull($body);
        $p = $body->firstChild;
        self::assertInstanceOf(Element::class, $p);
        self::assertSame('p', $p->localName);
        self::assertSame('This is bold and emphasised.', $p->textContent());

        $b = $p->getElementsByTagName('b')[0] ?? null;
        self::assertNotNull($b);
        self::assertSame('bold', $b->textContent());
        $em = $p->getElementsByTagName('em')[0] ?? null;
        self::assertNotNull($em);
        self::assertSame('emphasised', $em->textContent());
    }

    public function testLinksWithAttributes(): void
    {
        $doc = $this->parse(
            '<!DOCTYPE html><body><a href="https://example.com" rel="nofollow">link</a>',
        );
        $body = $doc->body;
        self::assertNotNull($body);
        $a = $body->firstChild;
        self::assertInstanceOf(Element::class, $a);
        self::assertSame('a', $a->localName);
        self::assertSame('https://example.com', $a->getAttribute('href'));
        self::assertSame('nofollow', $a->getAttribute('rel'));
        self::assertSame('link', $a->textContent());
    }

    public function testNestedListsRespectListItemScope(): void
    {
        $html = '<!DOCTYPE html><body><ul><li>A<li>B<ul><li>nested</ul><li>C</ul>';
        $doc = $this->parse($html);
        $body = $doc->body;
        self::assertNotNull($body);

        $outer = $body->firstChild;
        self::assertInstanceOf(Element::class, $outer);
        self::assertSame('ul', $outer->localName);

        $lis = $outer->children();
        self::assertCount(3, $lis);
        self::assertSame('A', $lis[0]->textContent());
        self::assertSame('B', trim(self::firstTextOf($lis[1])));
        self::assertSame('C', $lis[2]->textContent());

        $innerUl = $lis[1]->getElementsByTagName('ul')[0] ?? null;
        self::assertNotNull($innerUl);
        $innerLis = $innerUl->children();
        self::assertCount(1, $innerLis);
        self::assertSame('nested', $innerLis[0]->textContent());
    }

    public function testVoidElementsDoNotHaveChildren(): void
    {
        $doc = $this->parse('<!DOCTYPE html><body>before<br>after<hr><img src="x"></body>');
        $body = $doc->body;
        self::assertNotNull($body);
        $kids = $body->childNodes();
        $tags = [];
        foreach ($kids as $k) {
            $tags[] = $k instanceof Element ? $k->localName : '#text';
        }
        self::assertSame(['#text', 'br', '#text', 'hr', 'img'], $tags);
        foreach ($body->children() as $el) {
            self::assertCount(0, $el->childNodes(), "Void element <{$el->localName}> should be childless");
        }
    }

    public function testCommentInDocumentBody(): void
    {
        $doc = $this->parse('<!DOCTYPE html><body><p>x</p><!-- footer comment --></body>');
        $body = $doc->body;
        self::assertNotNull($body);
        $kids = $body->childNodes();
        self::assertCount(2, $kids);
        self::assertSame(' footer comment ', $kids[1]->textContent());
    }

    public function testDivsAndSectionsBuildNestedTree(): void
    {
        $html = '<!DOCTYPE html><body><div class="card"><h2>Title</h2><p>Body</p></div>';
        $doc = $this->parse($html);
        $body = $doc->body;
        self::assertNotNull($body);
        $div = $body->firstChild;
        self::assertInstanceOf(Element::class, $div);
        self::assertSame('div', $div->localName);
        self::assertSame('card', $div->getAttribute('class'));
        self::assertSame('Title', $div->getElementsByTagName('h2')[0]->textContent());
        self::assertSame('Body', $div->getElementsByTagName('p')[0]->textContent());
    }

    public function testStyleContentRawText(): void
    {
        $doc = $this->parse('<!DOCTYPE html><head><style>a > b { color: red; }</style></head>');
        $head = $doc->head;
        self::assertNotNull($head);
        $style = $head->getElementsByTagName('style')[0] ?? null;
        self::assertNotNull($style);
        self::assertSame('a > b { color: red; }', $style->textContent());
    }

    public function testScriptContentRawText(): void
    {
        $doc = $this->parse('<!DOCTYPE html><head><script>if (a < b) { alert(1); }</script></head>');
        $head = $doc->head;
        self::assertNotNull($head);
        $script = $head->getElementsByTagName('script')[0] ?? null;
        self::assertNotNull($script);
        self::assertSame('if (a < b) { alert(1); }', $script->textContent());
    }

    public function testFullDocumentRealistic(): void
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
              <meta charset="utf-8">
              <title>Sample</title>
              <style>body { font-family: serif; }</style>
            </head>
            <body>
              <header><h1>Sample document</h1></header>
              <main>
                <p>Hello, &amp; welcome to <strong>phpdftk</strong>.</p>
                <ul>
                  <li>Item one</li>
                  <li>Item two with <a href="/x">a link</a></li>
                </ul>
                <hr>
                <p>Final paragraph &mdash; with an em-dash.</p>
              </main>
              <!-- end of document -->
            </body>
            </html>
            HTML;
        $doc = $this->parse($html);
        self::assertSame(DocumentMode::NoQuirks, $doc->mode);
        self::assertSame('Sample', $doc->title);
        self::assertSame('en', $doc->documentElement->getAttribute('lang'));
        $h1 = $doc->getElementsByTagName('h1')[0] ?? null;
        self::assertNotNull($h1);
        self::assertSame('Sample document', $h1->textContent());

        $links = $doc->getElementsByTagName('a');
        self::assertCount(1, $links);
        self::assertSame('/x', $links[0]->getAttribute('href'));

        $paragraphs = $doc->getElementsByTagName('p');
        self::assertCount(2, $paragraphs);
        self::assertStringContainsString('Hello, & welcome', $paragraphs[0]->textContent());
        self::assertStringContainsString("\u{2014}", $paragraphs[1]->textContent()); // em-dash
    }

    public function testCharacterReferencesInBodyDecoded(): void
    {
        $doc = $this->parse('<!DOCTYPE html><body><p>5 &lt; 10 &amp; 10 &gt; 1</p>');
        $p = $doc->body->firstChild;
        self::assertInstanceOf(Element::class, $p);
        self::assertSame('5 < 10 & 10 > 1', $p->textContent());
    }

    public function testUnknownStartTagInsertsElement(): void
    {
        $doc = $this->parse('<!DOCTYPE html><body><custom-widget data-id="42">content</custom-widget>');
        $body = $doc->body;
        self::assertNotNull($body);
        $custom = $body->firstChild;
        self::assertInstanceOf(Element::class, $custom);
        self::assertSame('custom-widget', $custom->localName);
        self::assertSame('42', $custom->getAttribute('data-id'));
        self::assertSame('content', $custom->textContent());
    }

    public function testTrailingTextAfterBodyCloseGoesIntoBody(): void
    {
        // Per spec, character tokens in AfterBody insert into <body>.
        $doc = $this->parse('<!DOCTYPE html><body>before</body>after');
        $body = $doc->body;
        self::assertNotNull($body);
        self::assertSame('beforeafter', trim($body->textContent()));
    }

    public function testCommentAfterAfterBody(): void
    {
        $doc = $this->parse('<!DOCTYPE html><body></body></html><!-- final -->');
        $kids = $doc->childNodes();
        $foundComment = false;
        foreach ($kids as $k) {
            if ($k->nodeName() === '#comment' && $k->textContent() === ' final ') {
                $foundComment = true;
                break;
            }
        }
        self::assertTrue($foundComment);
    }

    public function testDoctypePublicMappedToLimitedQuirks(): void
    {
        // WHATWG §13.2.6.2 — `-//W3C//DTD HTML 4.01 Transitional//` is
        // limited-quirks when a system identifier is also set (quirks
        // when missing). Use the systemId-present form so this asserts
        // the limited-quirks branch specifically.
        $doc = $this->parse(
            '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd"><html></html>',
        );
        self::assertSame(DocumentMode::LimitedQuirks, $doc->mode);
    }

    private static function firstTextOf(Element $el): string
    {
        $first = $el->firstChild;
        return $first === null ? '' : $first->textContent();
    }
}
