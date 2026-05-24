<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * The minimal element-shape the Selectors-4 matcher needs to traverse and
 * test a DOM. Lives in `phpdftk/css` so the matcher can stay free of any
 * specific DOM library; `phpdftk/html`'s `Element` implements this so the
 * matcher works against the WHATWG DOM.
 *
 * Tree relationships use `MatchableElement` directly so combinators
 * (`>` / `+` / `~`) can walk without leaking concrete DOM types. The matcher
 * does not mutate the tree — every method here is read-only.
 */
interface MatchableElement
{
    public function localName(): string;

    /**
     * Namespace URI. `null` when not in a namespace (HTML elements typically
     * carry `'http://www.w3.org/1999/xhtml'`); SVG / MathML elements carry
     * their own URIs.
     */
    public function namespaceUri(): ?string;

    /** Lowercased `id` attribute value, or null when absent / empty. */
    public function elementId(): ?string;

    /** @return list<string> Space-separated `class` attribute tokens. */
    public function classes(): array;

    public function hasAttribute(string $name): bool;

    public function getAttributeValue(string $name): ?string;

    /** @return array<string, string> attribute name → value, names normalised */
    public function allAttributes(): array;

    public function parentElement(): ?MatchableElement;

    public function previousElementSibling(): ?MatchableElement;

    public function nextElementSibling(): ?MatchableElement;

    /** @return list<MatchableElement> direct element children, in document order */
    public function elementChildren(): array;

    /**
     * 1-based position among the parent's element children. Returns 1 when
     * the element has no parent.
     */
    public function indexAmongSiblings(): int;

    /**
     * Like {@see indexAmongSiblings} but counted from the end. Used for the
     * `nth-last-*` pseudo-classes.
     */
    public function indexAmongSiblingsFromEnd(): int;

    /**
     * 1-based position among same-tag siblings (used by `:nth-of-type`).
     * Sibling matching uses local name + namespace URI together.
     */
    public function indexAmongTypeSiblings(): int;

    public function indexAmongTypeSiblingsFromEnd(): int;
}
