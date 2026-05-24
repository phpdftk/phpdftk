<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\TreeConstruction;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Dom\HTMLTemplateElement;
use Phpdftk\Html\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the InTemplate insertion mode (WHATWG §13.2.6.4.18) and the
 * `<template>` start/end tag handling in InHead.
 *
 * Per WHATWG DOM, a `<template>` element's children live in its `content`
 * DocumentFragment — accessed via `$template->content` — and not directly
 * on the template element. Tests assert via that path.
 */
final class TemplateTest extends TestCase
{
    private function parse(string $html): Document
    {
        return (new Parser())->parseDocument($html);
    }

    /** Find the first descendant of $fragment whose local name matches. */
    private static function firstDescendant(\Phpdftk\Html\Dom\Node $fragment, string $localName): ?Element
    {
        foreach ($fragment->childNodes() as $child) {
            if ($child instanceof Element && $child->localName === $localName) {
                return $child;
            }
            if ($child->hasChildNodes()) {
                $found = self::firstDescendant($child, $localName);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    public function testSimpleTemplateInHead(): void
    {
        $html = '<!DOCTYPE html><html><head><template id="t"><p>hello</p></template></head><body></body></html>';
        $doc = $this->parse($html);
        $head = $doc->head;
        self::assertNotNull($head);
        $template = $head->getElementsByTagName('template')[0] ?? null;
        self::assertInstanceOf(HTMLTemplateElement::class, $template);
        self::assertSame('t', $template->getAttribute('id'));
        self::assertNotNull($template->content);

        // The <p>hello</p> is in template.content, not direct children of <template>.
        $p = self::firstDescendant($template->content, 'p');
        self::assertNotNull($p);
        self::assertSame('hello', $p->textContent());
    }

    public function testTemplateInsideBody(): void
    {
        $html = '<!DOCTYPE html><body><template id="card"><div class="card">card content</div></template>';
        $doc = $this->parse($html);
        $body = $doc->body;
        self::assertNotNull($body);
        $template = $body->getElementsByTagName('template')[0] ?? null;
        self::assertInstanceOf(HTMLTemplateElement::class, $template);
        self::assertNotNull($template->content);

        $div = self::firstDescendant($template->content, 'div');
        self::assertNotNull($div);
        self::assertSame('card', $div->getAttribute('class'));
        self::assertSame('card content', $div->textContent());
    }

    public function testTemplateContainingTableFragment(): void
    {
        // Per spec, template can contain table fragments without an enclosing <table>.
        $html = '<!DOCTYPE html><body><template><tr><td>cell</td></tr></template>';
        $doc = $this->parse($html);
        $template = $doc->getElementsByTagName('template')[0] ?? null;
        self::assertInstanceOf(HTMLTemplateElement::class, $template);
        self::assertNotNull($template->content);

        $tr = self::firstDescendant($template->content, 'tr');
        self::assertNotNull($tr);
        $td = self::firstDescendant($tr, 'td');
        self::assertNotNull($td);
        self::assertSame('cell', $td->textContent());
    }

    public function testTemplateClosingRestoresOuterMode(): void
    {
        $html = '<!DOCTYPE html><body><template><p>inside</p></template><p>after</p>';
        $doc = $this->parse($html);
        $body = $doc->body;
        self::assertNotNull($body);
        $template = $body->getElementsByTagName('template')[0] ?? null;
        self::assertNotNull($template);
        // The "after" <p> should be a sibling of the template, not inside it.
        $directChildren = $body->children();
        $tags = array_map(fn($e) => $e->localName, $directChildren);
        self::assertContains('template', $tags);
        self::assertContains('p', $tags);
    }

    public function testNestedTemplates(): void
    {
        $html = '<!DOCTYPE html><body><template id="outer"><template id="inner">inner</template></template>';
        $doc = $this->parse($html);
        $outer = $doc->getElementById('outer');
        self::assertInstanceOf(HTMLTemplateElement::class, $outer);
        self::assertNotNull($outer->content);

        // Inner template is in outer.content (not in main document tree),
        // so getElementById on the document won't find it — per spec.
        $inner = self::firstDescendant($outer->content, 'template');
        self::assertInstanceOf(HTMLTemplateElement::class, $inner);
        self::assertSame('inner', $inner->getAttribute('id'));
        self::assertNotNull($inner->content);
        self::assertSame('inner', $inner->content->textContent());
    }
}
