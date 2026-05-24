<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\Dom;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Dom\NodeType;
use Phpdftk\Html\Dom\Text;
use PHPUnit\Framework\TestCase;

final class NodeTest extends TestCase
{
    public function testAppendChildLinksParentAndSiblings(): void
    {
        $doc = new Document();
        $parent = $doc->createElement('section');
        $a = $doc->createElement('a');
        $b = $doc->createElement('b');
        $c = $doc->createElement('c');

        $parent->appendChild($a);
        $parent->appendChild($b);
        $parent->appendChild($c);

        self::assertSame($a, $parent->firstChild);
        self::assertSame($c, $parent->lastChild);
        self::assertSame($parent, $a->parentNode);
        self::assertSame($parent, $c->parentNode);

        self::assertNull($a->previousSibling);
        self::assertSame($b, $a->nextSibling);
        self::assertSame($a, $b->previousSibling);
        self::assertSame($c, $b->nextSibling);
        self::assertSame($b, $c->previousSibling);
        self::assertNull($c->nextSibling);
    }

    public function testInsertBeforeReorders(): void
    {
        $doc = new Document();
        $parent = $doc->createElement('section');
        $a = $doc->createElement('a');
        $b = $doc->createElement('b');
        $c = $doc->createElement('c');
        $parent->appendChild($a);
        $parent->appendChild($c);

        $parent->insertBefore($b, $c);

        self::assertSame([$a, $b, $c], $parent->childNodes());
    }

    public function testInsertBeforeWithNullReferenceAppends(): void
    {
        $doc = new Document();
        $parent = $doc->createElement('section');
        $a = $doc->createElement('a');
        $b = $doc->createElement('b');
        $parent->insertBefore($a, null);
        $parent->insertBefore($b, null);

        self::assertSame([$a, $b], $parent->childNodes());
    }

    public function testAppendingDetachesFromPreviousParent(): void
    {
        $doc = new Document();
        $first = $doc->createElement('first');
        $second = $doc->createElement('second');
        $child = $doc->createElement('child');

        $first->appendChild($child);
        self::assertSame($first, $child->parentNode);

        $second->appendChild($child);
        self::assertSame($second, $child->parentNode);
        self::assertNull($first->firstChild);
        self::assertSame($child, $second->firstChild);
    }

    public function testRemoveChildClearsLinks(): void
    {
        $doc = new Document();
        $parent = $doc->createElement('section');
        $a = $doc->createElement('a');
        $b = $doc->createElement('b');
        $parent->appendChild($a);
        $parent->appendChild($b);

        $parent->removeChild($a);

        self::assertNull($a->parentNode);
        self::assertNull($a->nextSibling);
        self::assertSame($b, $parent->firstChild);
        self::assertSame($b, $parent->lastChild);
        self::assertNull($b->previousSibling);
    }

    public function testReplaceChildSwapsInPlace(): void
    {
        $doc = new Document();
        $parent = $doc->createElement('section');
        $a = $doc->createElement('a');
        $b = $doc->createElement('b');
        $c = $doc->createElement('c');
        $parent->appendChild($a);
        $parent->appendChild($b);

        $parent->replaceChild($c, $a);

        self::assertSame([$c, $b], $parent->childNodes());
        self::assertNull($a->parentNode);
    }

    public function testCannotInsertSelfOrAncestor(): void
    {
        $doc = new Document();
        $a = $doc->createElement('a');
        $b = $doc->createElement('b');
        $a->appendChild($b);

        $this->expectException(\InvalidArgumentException::class);
        $b->appendChild($a);
    }

    public function testTextContentConcatenatesDescendants(): void
    {
        $doc = new Document();
        $root = $doc->createElement('div');
        $root->appendChild($doc->createTextNode('Hello '));
        $span = $doc->createElement('span');
        $span->appendChild($doc->createTextNode('world'));
        $root->appendChild($span);
        $root->appendChild($doc->createTextNode('!'));

        self::assertSame('Hello world!', $root->textContent());
    }

    public function testSetTextContentReplacesChildren(): void
    {
        $doc = new Document();
        $root = $doc->createElement('div');
        $root->appendChild($doc->createElement('span'));
        $root->appendChild($doc->createElement('p'));

        $root->setTextContent('replaced');

        self::assertCount(1, $root->childNodes());
        $first = $root->firstChild;
        self::assertInstanceOf(Text::class, $first);
        self::assertSame('replaced', $first->data);
    }

    public function testCloneNodeDeepProducesIndependentSubtree(): void
    {
        $doc = new Document();
        $root = $doc->createElement('section');
        $root->setAttribute('id', 'top');
        $root->appendChild($doc->createTextNode('hi'));

        $copy = $root->cloneNode(true);

        self::assertNotSame($root, $copy);
        self::assertInstanceOf(Element::class, $copy);
        self::assertSame('top', $copy->getAttribute('id'));
        self::assertSame('hi', $copy->textContent());
        self::assertNull($copy->parentNode);
    }

    public function testNodeTypesAndNames(): void
    {
        $doc = new Document();
        self::assertSame(NodeType::Document, $doc->nodeType());
        self::assertSame('#document', $doc->nodeName());

        $el = $doc->createElement('p');
        self::assertSame(NodeType::Element, $el->nodeType());
        self::assertSame('P', $el->nodeName());

        $text = $doc->createTextNode('foo');
        self::assertSame(NodeType::Text, $text->nodeType());
        self::assertSame('#text', $text->nodeName());

        $comment = $doc->createComment('x');
        self::assertSame(NodeType::Comment, $comment->nodeType());
        self::assertSame('#comment', $comment->nodeName());
    }

    public function testOwnerDocumentResolvesAcrossNodes(): void
    {
        $doc = new Document();
        $el = $doc->createElement('div');
        $text = $doc->createTextNode('foo');
        $el->appendChild($text);

        self::assertSame($doc, $doc->ownerDocument);
        self::assertSame($doc, $el->ownerDocument);
        self::assertSame($doc, $text->ownerDocument);
    }
}
