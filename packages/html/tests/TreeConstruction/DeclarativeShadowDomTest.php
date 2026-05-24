<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\TreeConstruction;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Dom\HTMLTemplateElement;
use Phpdftk\Html\Dom\ShadowRoot;
use Phpdftk\Html\Dom\ShadowRootInit;
use Phpdftk\Html\Dom\ShadowRootMode;
use Phpdftk\Html\Parser;
use Phpdftk\Html\Serializer;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1B.4 — Declarative Shadow DOM tree-construction integration.
 *
 * `<template shadowrootmode="open|closed">` attaches a shadow root to the
 * parent host element (if shadow-host-eligible and not already shadow-hosting).
 * The template's children are routed into the shadow root's tree. After
 * parse completion the template element is removed from the light DOM — the
 * shadow root on the host is the surviving artefact.
 */
final class DeclarativeShadowDomTest extends TestCase
{
    private function parse(string $html): Document
    {
        return (new Parser())->parseDocument($html);
    }

    public function testOpenDsdAttachesShadowRoot(): void
    {
        $html = '<!DOCTYPE html><body><div><template shadowrootmode="open"><p>shadow content</p></template></div>';
        $doc = $this->parse($html);
        $div = $doc->getElementsByTagName('div')[0] ?? null;
        self::assertNotNull($div);
        self::assertInstanceOf(ShadowRoot::class, $div->shadowRoot);
        self::assertSame(ShadowRootMode::Open, $div->shadowRoot->mode);

        // The template element should have been removed from the light DOM.
        self::assertCount(0, $div->getElementsByTagName('template'));

        // The shadow root should contain the <p>shadow content</p>.
        $p = $div->shadowRoot->firstChild;
        self::assertInstanceOf(Element::class, $p);
        self::assertSame('p', $p->localName);
        self::assertSame('shadow content', $p->textContent());
    }

    public function testClosedDsdAttachesShadowRoot(): void
    {
        $html = '<!DOCTYPE html><body><section><template shadowrootmode="closed"><span>closed</span></template></section>';
        $doc = $this->parse($html);
        $section = $doc->getElementsByTagName('section')[0] ?? null;
        self::assertNotNull($section);
        self::assertInstanceOf(ShadowRoot::class, $section->shadowRoot);
        self::assertSame(ShadowRootMode::Closed, $section->shadowRoot->mode);
        self::assertSame('closed', $section->shadowRoot->textContent());
    }

    public function testDsdOnCustomElement(): void
    {
        $html = '<!DOCTYPE html><body><my-widget><template shadowrootmode="open"><div>widget shadow</div></template></my-widget>';
        $doc = $this->parse($html);
        $widget = $doc->getElementsByTagName('my-widget')[0] ?? null;
        self::assertNotNull($widget);
        self::assertNotNull($widget->shadowRoot);
        $div = $widget->shadowRoot->firstChild;
        self::assertInstanceOf(Element::class, $div);
        self::assertSame('widget shadow', $div->textContent());
    }

    public function testDsdInitFlags(): void
    {
        $html = '<!DOCTYPE html><body><div><template shadowrootmode="open" shadowrootserializable shadowrootclonable shadowrootdelegatesfocus>x</template></div>';
        $doc = $this->parse($html);
        $div = $doc->getElementsByTagName('div')[0] ?? null;
        self::assertNotNull($div);
        $shadow = $div->shadowRoot;
        self::assertNotNull($shadow);
        self::assertTrue($shadow->serializable);
        self::assertTrue($shadow->clonable);
        self::assertTrue($shadow->delegatesFocus);
    }

    public function testDsdOnIneligibleParentFallsBackToNormalTemplate(): void
    {
        // <img> isn't shadow-host-eligible (it's a void element / replaced).
        // Actually <img> is a void element so it can't have children — let's
        // use <option> which is HTML but not in the shadow-host-eligible list.
        $html = '<!DOCTYPE html><body><select><option><template shadowrootmode="open">fallback</template></option></select>';
        $doc = $this->parse($html);
        $option = $doc->getElementsByTagName('option')[0] ?? null;
        self::assertNotNull($option);
        self::assertNull($option->shadowRoot, 'Ineligible parent should not get a shadow root');

        // The template should remain in the light DOM as a normal template.
        $template = $option->getElementsByTagName('template')[0] ?? null;
        self::assertInstanceOf(HTMLTemplateElement::class, $template);
        self::assertFalse($template->isDeclarativeShadowRoot);
    }

    public function testDsdSecondTemplateOnSameHostFallsBack(): void
    {
        // Per spec: if the parent already has a shadow root, the second
        // <template shadowrootmode> is a parse error and falls back to a
        // normal template.
        $html = '<!DOCTYPE html><body><div>'
            . '<template shadowrootmode="open">first</template>'
            . '<template shadowrootmode="open">second</template>'
            . '</div>';
        $doc = $this->parse($html);
        $div = $doc->getElementsByTagName('div')[0] ?? null;
        self::assertNotNull($div);
        $shadow = $div->shadowRoot;
        self::assertNotNull($shadow);
        // First template's content went into the shadow root.
        self::assertStringContainsString('first', $shadow->textContent());

        // The second template should appear as a regular template in light DOM.
        $templates = $div->getElementsByTagName('template');
        self::assertCount(1, $templates);
        self::assertInstanceOf(HTMLTemplateElement::class, $templates[0]);
        self::assertFalse($templates[0]->isDeclarativeShadowRoot);
        self::assertSame('second', $templates[0]->content?->textContent());
    }

    public function testDsdRoundTripsViaSerialiser(): void
    {
        $html = '<div><template shadowrootmode="open" shadowrootserializable>shadow content</template>light content</div>';
        $doc = $this->parse('<!DOCTYPE html><body>' . $html);
        $div = $doc->getElementsByTagName('div')[0] ?? null;
        self::assertNotNull($div);
        self::assertNotNull($div->shadowRoot);

        // Serialize the <div> and verify the template tag is re-emitted.
        $out = (new Serializer())->serialize($div);
        self::assertStringContainsString('<template shadowrootmode="open"', $out);
        self::assertStringContainsString('shadow content', $out);
        self::assertStringContainsString('light content', $out);
    }

    public function testDsdShadowContentIsolatedFromLightDom(): void
    {
        // Content in the shadow root should NOT appear via the regular
        // light-DOM traversal of the host (it's encapsulated).
        $html = '<!DOCTYPE html><body><article>'
            . '<template shadowrootmode="open"><h2>shadow heading</h2></template>'
            . '<p>light paragraph</p>'
            . '</article>';
        $doc = $this->parse($html);
        $article = $doc->getElementsByTagName('article')[0] ?? null;
        self::assertNotNull($article);

        // Light-DOM walk should only find the <p>, not the shadow's <h2>.
        $lightHeadings = $article->getElementsByTagName('h2');
        self::assertCount(0, $lightHeadings, 'Shadow content must not appear in light-DOM traversal');

        // The shadow root contains the <h2>.
        self::assertNotNull($article->shadowRoot);
        self::assertStringContainsString('shadow heading', $article->shadowRoot->textContent());
    }

    public function testDsdWithSlotElementInShadow(): void
    {
        // The <slot> element should be a HTMLSlotElement in the shadow tree.
        $html = '<!DOCTYPE html><body><my-card>'
            . '<template shadowrootmode="open"><div class="card"><slot name="title"></slot></div></template>'
            . '</my-card>';
        $doc = $this->parse($html);
        $card = $doc->getElementsByTagName('my-card')[0] ?? null;
        self::assertNotNull($card);
        self::assertNotNull($card->shadowRoot);

        // Find the slot element in the shadow tree.
        $slots = $card->shadowRoot->slots();
        self::assertCount(1, $slots);
        self::assertSame('title', $slots[0]->name);
    }
}
