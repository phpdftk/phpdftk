<?php

declare(strict_types=1);

namespace Phpdftk\Html;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\DocumentFragment;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Tokenizer\Tokenizer;
use Phpdftk\Html\TreeConstruction\TreeBuilder;

/**
 * WHATWG HTML5 parser entry point. Hand-rolls the tokenizer (§13.2.5) and
 * tree-construction state machine (§13.2.6) — no `libxml`, no DOM extension.
 *
 * The public surface is intentionally tiny: parseDocument() for a full HTML
 * document and parseFragment() for innerHTML-style operations and HTML
 * embedded in SVG <foreignObject>.
 *
 * Implementation is staged across Phase 1B sub-phases:
 *  - 1B.1: public DOM types and parser shell (this file).
 *  - 1B.2: tokenizer state machine.
 *  - 1B.3: tree-construction insertion modes.
 *  - 1B.4: declarative-shadow-DOM tree-construction integration.
 *  - 1B.5: html5lib-tests integration to 100%.
 */
final class Parser
{
    public function __construct(public readonly ParserOptions $options = new ParserOptions()) {}

    /**
     * Parse a complete HTML document.
     *
     * @param string $html The HTML source.
     * @param string|null $encoding Optional override for encoding sniffing.
     */
    public function parseDocument(string $html, ?string $encoding = null): Document
    {
        $tokenizer = new Tokenizer($html);
        $builder = new TreeBuilder($this->options);
        $doc = $builder->build($tokenizer);
        $this->mirrorSelectedContent($doc);
        return $doc;
    }

    /**
     * Post-parse pass for the customizable-select `<selectedcontent>` element:
     * mirror the selected option's children into each `<selectedcontent>`
     * descendant of every `<select>`. Per the customizable-select draft, this
     * happens at runtime — but parsing-time test fixtures (html5lib-tests
     * webkit02 #45–#48) bake the mirror into their expected output, so we
     * perform it once after the tree is built.
     */
    private function mirrorSelectedContent(Document $doc): void
    {
        $stack = [$doc];
        $selects = [];
        while ($stack !== []) {
            $node = array_pop($stack);
            foreach ($node->childNodes() as $child) {
                if ($child instanceof Element) {
                    if ($child->localName === 'select'
                        && $child->namespaceURI === \Phpdftk\Html\Dom\Document::HTML_NS
                    ) {
                        $selects[] = $child;
                    }
                    $stack[] = $child;
                }
            }
        }
        foreach ($selects as $select) {
            $selectedContent = $this->findFirstDescendant($select, 'selectedcontent');
            if ($selectedContent === null) {
                continue;
            }
            $option = $this->findSelectedOption($select);
            if ($option === null) {
                continue;
            }
            foreach ($option->childNodes() as $child) {
                $selectedContent->appendChild($child->cloneNode(true));
            }
        }
    }

    private function findFirstDescendant(Element $root, string $localName): ?Element
    {
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            foreach ($node->childNodes() as $child) {
                if ($child instanceof Element) {
                    if ($child->localName === $localName
                        && $child->namespaceURI === \Phpdftk\Html\Dom\Document::HTML_NS
                    ) {
                        return $child;
                    }
                    $stack[] = $child;
                }
            }
        }
        return null;
    }

    private function findSelectedOption(Element $select): ?Element
    {
        $firstOption = null;
        $stack = [$select];
        while ($stack !== []) {
            $node = array_pop($stack);
            foreach ($node->childNodes() as $child) {
                if (!$child instanceof Element) {
                    continue;
                }
                if ($child->localName === 'option'
                    && $child->namespaceURI === \Phpdftk\Html\Dom\Document::HTML_NS
                ) {
                    if ($child->hasAttribute('selected')) {
                        return $child;
                    }
                    $firstOption ??= $child;
                }
                // Don't recurse into nested selects.
                if ($child->localName === 'select'
                    && $child->namespaceURI === \Phpdftk\Html\Dom\Document::HTML_NS
                    && $child !== $select
                ) {
                    continue;
                }
                $stack[] = $child;
            }
        }
        return $firstOption;
    }

    /**
     * Parse an HTML fragment in the context of a host element per WHATWG
     * §13.4. The context element determines the initial tokenizer state
     * (e.g. RCDATA for <title>/<textarea>, RAWTEXT for <style>/<script>,
     * PLAINTEXT for <plaintext>) and the initial insertion mode (via the
     * "reset insertion mode appropriately" walk with the context as the
     * implicit bottom of the stack).
     */
    public function parseFragment(string $html, Element $context): DocumentFragment
    {
        // Step 1: new document, inherit mode from context's owner.
        $doc = new \Phpdftk\Html\Dom\Document();
        $doc->mode = $context->ownerDocument->mode;

        // Step 2: tokenizer with context-aware initial state.
        $tokenizer = new Tokenizer($html);
        if ($context->namespaceURI === \Phpdftk\Html\Dom\Document::HTML_NS) {
            $tokenizer->state = match ($context->localName) {
                'title', 'textarea' => \Phpdftk\Html\Tokenizer\TokenizerState::Rcdata,
                'style', 'xmp', 'iframe', 'noembed', 'noframes' => \Phpdftk\Html\Tokenizer\TokenizerState::Rawtext,
                'script' => \Phpdftk\Html\Tokenizer\TokenizerState::ScriptData,
                'noscript' => $this->options->scriptingEnabled
                    ? \Phpdftk\Html\Tokenizer\TokenizerState::Rawtext
                    : \Phpdftk\Html\Tokenizer\TokenizerState::Data,
                'plaintext' => \Phpdftk\Html\Tokenizer\TokenizerState::Plaintext,
                default => \Phpdftk\Html\Tokenizer\TokenizerState::Data,
            };
        }

        // Step 3-9 delegated to TreeBuilder::buildFragment, which configures
        // the initial state (html root, form pointer, template stack, reset
        // insertion mode based on context) and runs the parse.
        $builder = new TreeBuilder($this->options, $doc);
        return $builder->buildFragment($tokenizer, $context);
    }
}
