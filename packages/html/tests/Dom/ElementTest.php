<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\Dom;

use Phpdftk\Html\Dom\Attr;
use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Dom\ShadowRoot;
use Phpdftk\Html\Dom\ShadowRootInit;
use Phpdftk\Html\Dom\ShadowRootMode;
use PHPUnit\Framework\TestCase;

final class ElementTest extends TestCase
{
    public function testTagNameIsUppercaseForHtmlNamespace(): void
    {
        $doc = new Document();
        $el = $doc->createElement('div');
        self::assertSame('div', $el->localName);
        self::assertSame('DIV', $el->tagName);
        self::assertSame(Document::HTML_NS, $el->namespaceURI);
    }

    public function testTagNamePreservesCaseForForeignNamespace(): void
    {
        $doc = new Document();
        $el = new Element($doc, 'linearGradient', Document::SVG_NS);
        self::assertSame('linearGradient', $el->localName);
        self::assertSame('linearGradient', $el->tagName);
    }

    public function testAttributeRoundtrip(): void
    {
        $doc = new Document();
        $el = $doc->createElement('input');
        self::assertFalse($el->hasAttribute('type'));
        self::assertNull($el->getAttribute('type'));

        $el->setAttribute('type', 'text');
        self::assertTrue($el->hasAttribute('type'));
        self::assertSame('text', $el->getAttribute('type'));

        $el->setAttribute('Type', 'email');
        self::assertSame('email', $el->getAttribute('type'));

        $el->removeAttribute('type');
        self::assertFalse($el->hasAttribute('type'));
    }

    public function testAttributesListedInInsertionOrder(): void
    {
        $doc = new Document();
        $el = $doc->createElement('div');
        $el->setAttribute('one', '1');
        $el->setAttribute('two', '2');
        $el->setAttribute('three', '3');

        $names = array_map(static fn(Attr $a): string => $a->localName, $el->attributes());
        self::assertSame(['one', 'two', 'three'], $names);
    }

    public function testIdAccessorMirrorsIdAttribute(): void
    {
        $doc = new Document();
        $el = $doc->createElement('div');
        self::assertNull($el->id);

        $el->setAttribute('id', 'main');
        self::assertSame('main', $el->id);
    }

    public function testClassListAdd(): void
    {
        $doc = new Document();
        $el = $doc->createElement('div');

        $el->classList->add('alpha', 'beta');
        self::assertSame('alpha beta', $el->getAttribute('class'));
        self::assertTrue($el->classList->contains('alpha'));
        self::assertTrue($el->classList->contains('beta'));
        self::assertFalse($el->classList->contains('gamma'));
    }

    public function testClassListRemoveAndDedup(): void
    {
        $doc = new Document();
        $el = $doc->createElement('div');
        $el->setAttribute('class', '  one  two   one  three  ');

        self::assertSame(['one', 'two', 'three'], $el->classList->values());

        $el->classList->remove('two');
        self::assertSame(['one', 'three'], $el->classList->values());
    }

    public function testClassListToggle(): void
    {
        $doc = new Document();
        $el = $doc->createElement('div');

        self::assertTrue($el->classList->toggle('active'));
        self::assertTrue($el->classList->contains('active'));

        self::assertFalse($el->classList->toggle('active'));
        self::assertFalse($el->classList->contains('active'));

        self::assertTrue($el->classList->toggle('forced', true));
        self::assertTrue($el->classList->toggle('forced', true));
        self::assertTrue($el->classList->contains('forced'));
    }

    public function testClassListRejectsEmptyOrWhitespaceTokens(): void
    {
        $doc = new Document();
        $el = $doc->createElement('div');

        $this->expectException(\InvalidArgumentException::class);
        $el->classList->add('with space');
    }

    public function testChildrenFiltersToElementsOnly(): void
    {
        $doc = new Document();
        $root = $doc->createElement('div');
        $a = $doc->createElement('span');
        $b = $doc->createElement('p');
        $root->appendChild($a);
        $root->appendChild($doc->createTextNode('text between'));
        $root->appendChild($b);

        self::assertCount(3, $root->childNodes());
        self::assertSame([$a, $b], $root->children());
    }

    public function testGetElementsByTagNameDeep(): void
    {
        $doc = new Document();
        $root = $doc->createElement('article');
        $h1 = $doc->createElement('h1');
        $section = $doc->createElement('section');
        $h2 = $doc->createElement('h2');
        $section->appendChild($h2);
        $root->appendChild($h1);
        $root->appendChild($section);

        self::assertSame([$h1], $root->getElementsByTagName('h1'));
        self::assertSame([$h2], $root->getElementsByTagName('h2'));
        self::assertSame([$h1, $section, $h2], $root->getElementsByTagName('*'));
    }

    public function testAttachShadowEligibleHostAndDuplicate(): void
    {
        $doc = new Document();
        $host = $doc->createElement('div');
        self::assertNull($host->shadowRoot);

        $root = $host->attachShadow(ShadowRootMode::Open);
        self::assertInstanceOf(ShadowRoot::class, $root);
        self::assertSame($host, $root->host);
        self::assertSame($root, $host->shadowRoot);
        self::assertSame(ShadowRootMode::Open, $root->mode);

        $this->expectException(\LogicException::class);
        $host->attachShadow(ShadowRootMode::Open);
    }

    public function testAttachShadowOnIneligibleElement(): void
    {
        $doc = new Document();
        $host = $doc->createElement('img');

        $this->expectException(\LogicException::class);
        $host->attachShadow(ShadowRootMode::Open);
    }

    public function testAttachShadowOnCustomElement(): void
    {
        $doc = new Document();
        $host = $doc->createElement('my-widget');
        $root = $host->attachShadow(ShadowRootMode::Closed);
        self::assertSame(ShadowRootMode::Closed, $root->mode);
    }

    public function testCloneNodeCopiesAttributesAndClonableShadow(): void
    {
        $doc = new Document();
        $el = $doc->createElement('div');
        $el->setAttribute('id', 'host');
        $el->classList->add('alpha');
        $shadow = $el->attachShadow(ShadowRootMode::Open, new ShadowRootInit(clonable: true));
        $shadow->appendChild($doc->createTextNode('inside'));

        $copy = $el->cloneNode(true);

        self::assertInstanceOf(Element::class, $copy);
        self::assertSame('host', $copy->getAttribute('id'));
        self::assertTrue($copy->classList->contains('alpha'));
        self::assertNotNull($copy->shadowRoot);
        self::assertSame('inside', $copy->shadowRoot->textContent());
    }

    public function testQuerySelectorAllUsesCssMatcher(): void
    {
        $doc = new Document();
        $root = $doc->createElement('section');
        $a = $doc->createElement('p');
        $a->setAttribute('class', 'intro');
        $b = $doc->createElement('p');
        $c = $doc->createElement('span');
        $c->setAttribute('class', 'intro');
        $root->appendChild($a);
        $root->appendChild($b);
        $root->appendChild($c);

        $matches = $root->querySelectorAll('p.intro');
        self::assertCount(1, $matches);
        self::assertSame($a, $matches[0]);

        self::assertSame($a, $root->querySelector('.intro'));
        self::assertTrue($a->matches('p.intro'));
        self::assertFalse($b->matches('.intro'));
    }
}
