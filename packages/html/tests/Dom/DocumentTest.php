<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\Dom;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\DocumentType;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    public function testDocumentElementHeadAndBodyAccessors(): void
    {
        $doc = new Document();
        $html = $doc->createElement('html');
        $head = $doc->createElement('head');
        $title = $doc->createElement('title');
        $title->setTextContent('Hello');
        $body = $doc->createElement('body');
        $head->appendChild($title);
        $html->appendChild($head);
        $html->appendChild($body);
        $doc->appendChild($html);

        self::assertSame($html, $doc->documentElement);
        self::assertSame($head, $doc->head);
        self::assertSame($body, $doc->body);
        self::assertSame('Hello', $doc->title);
    }

    public function testDoctypeAccessor(): void
    {
        $doc = new Document();
        $dt = new DocumentType($doc, 'html');
        $doc->appendChild($dt);
        $doc->appendChild($doc->createElement('html'));

        self::assertSame($dt, $doc->doctype);
        self::assertSame('html', $doc->doctype->name);
    }

    public function testGetElementById(): void
    {
        $doc = new Document();
        $root = $doc->createElement('section');
        $target = $doc->createElement('p');
        $target->setAttribute('id', 'target');
        $root->appendChild($target);
        $doc->appendChild($root);

        self::assertSame($target, $doc->getElementById('target'));
        self::assertNull($doc->getElementById('nonexistent'));
    }

    public function testFactoryMethodsAssignOwnerDocument(): void
    {
        $doc = new Document();
        $el = $doc->createElement('a');
        $text = $doc->createTextNode('foo');
        $comment = $doc->createComment('bar');
        $fragment = $doc->createDocumentFragment();

        self::assertSame($doc, $el->ownerDocument);
        self::assertSame($doc, $text->ownerDocument);
        self::assertSame($doc, $comment->ownerDocument);
        self::assertSame($doc, $fragment->ownerDocument);
    }

    public function testCreateElementLowercasesHtmlNames(): void
    {
        $doc = new Document();
        $el = $doc->createElement('DIV');
        self::assertSame('div', $el->localName);
    }
}
