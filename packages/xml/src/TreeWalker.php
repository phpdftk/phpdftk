<?php

declare(strict_types=1);

namespace Phpdftk\Xml;

/**
 * Generic libxml-DOM walker that builds a typed parallel tree using
 * caller-supplied factories.
 *
 * Every typed-tree XML parser in the codebase walks the libxml DOM
 * the same way:
 *   1. Iterate `$src->attributes`, skip the `xmlns` declarations
 *      (the namespace is already on the node's `namespaceURI`), copy
 *      everything else verbatim onto the typed element.
 *   2. Iterate `$src->childNodes`, build a typed Text for each
 *      `\DOMText`, recurse into each `\DOMElement` to build a typed
 *      child element, and append both to the typed parent. Drop
 *      comments / processing instructions / CDATA — none of our
 *      consumers carry semantic meaning in those today.
 *
 * The walker centralises that iteration; the consumer provides four
 * closures wiring its typed types in. The closure form lets each
 * package keep its own typed `Element` / `Text` hierarchies (which
 * have format-specific accessors and aren't worth sharing across the
 * package boundary) while still sharing the walk logic.
 *
 * Closure signatures (closures are typed as plain `\Closure` because
 * PHPStan can't span generic types across the consumer/walker
 * boundary; the docstring describes the expected shapes):
 *
 *   $createElement: (string $localName) → ConsumerElement
 *   $createText:    (string $data)      → ConsumerText
 *   $setAttribute:  (ConsumerElement, string $name, string $value) → void
 *   $appendChild:   (ConsumerElement $parent, ConsumerNode $child)  → void
 */
final class TreeWalker
{
    /**
     * Populate `$root` from `$src`'s attributes and children,
     * recursing into nested elements via `$createElement`.
     *
     * @param \DOMElement $src              The libxml source element.
     *                                       Caller is responsible for
     *                                       root-level validation
     *                                       (localName, namespaceURI)
     *                                       before calling.
     * @param mixed    $root             The typed root element to
     *                                    populate.
     * @param \Closure $createElement     `(string) → ConsumerElement`
     * @param \Closure $createText        `(string) → ConsumerText`
     * @param \Closure $setAttribute      `(ConsumerElement, string, string) → void`
     * @param \Closure $appendChild       `(ConsumerElement, ConsumerNode) → void`
     */
    public function walk(
        \DOMElement $src,
        mixed $root,
        \Closure $createElement,
        \Closure $createText,
        \Closure $setAttribute,
        \Closure $appendChild,
    ): void {
        $this->copyAttributes($src, $root, $setAttribute);
        $this->copyChildren($src, $root, $createElement, $createText, $setAttribute, $appendChild);
    }

    private function copyAttributes(
        \DOMElement $src,
        mixed $dest,
        \Closure $setAttribute,
    ): void {
        foreach ($src->attributes as $attr) {
            if (!$attr instanceof \DOMAttr) {
                continue;
            }
            // Skip xmlns declarations — implied by namespaceURI on
            // each node already, no need to surface them as element
            // attributes.
            if ($attr->prefix === 'xmlns' || $attr->name === 'xmlns') {
                continue;
            }
            // Use the qualified node name so prefixed attributes
            // (`xlink:href`, `xml:lang`) stay distinct from their
            // unprefixed counterparts.
            $setAttribute($dest, $attr->nodeName, $attr->value);
        }
    }

    private function copyChildren(
        \DOMElement $src,
        mixed $dest,
        \Closure $createElement,
        \Closure $createText,
        \Closure $setAttribute,
        \Closure $appendChild,
    ): void {
        foreach ($src->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $appendChild($dest, $createText($child->data));
                continue;
            }
            if ($child instanceof \DOMElement) {
                $node = $createElement($child->localName);
                $this->copyAttributes($child, $node, $setAttribute);
                $this->copyChildren($child, $node, $createElement, $createText, $setAttribute, $appendChild);
                $appendChild($dest, $node);
            }
            // Comments / processing instructions / CDATA — drop.
        }
    }
}
