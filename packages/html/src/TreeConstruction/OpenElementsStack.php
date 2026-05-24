<?php

declare(strict_types=1);

namespace Phpdftk\Html\TreeConstruction;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\Element;

/**
 * The stack of open elements per WHATWG §13.2.4.2. Last-in-first-out, with
 * a battery of "in scope" checks the tree-construction algorithm relies on.
 *
 * The element at the bottom of the stack (index 0) is conceptually the html
 * element; the top (last) is the "current node".
 */
final class OpenElementsStack
{
    /** @var list<Element> */
    private array $items = [];

    /**
     * Per §13.2.4.2 — the "special" element list determines which elements
     * close enclosing paragraphs, end formatting reconstruction loops, etc.
     * Listed in HTML namespace only; foreign-content scoping is handled
     * separately when Phase 1B.3-bis adds foreign-content insertion mode.
     *
     * @var array<string, true>
     */
    private const array SPECIAL_HTML = [
        'address' => true, 'applet' => true, 'area' => true, 'article' => true,
        'aside' => true, 'base' => true, 'basefont' => true, 'bgsound' => true,
        'blockquote' => true, 'body' => true, 'br' => true, 'button' => true,
        'caption' => true, 'center' => true, 'col' => true, 'colgroup' => true,
        'dd' => true, 'details' => true, 'dir' => true, 'div' => true,
        'dl' => true, 'dt' => true, 'embed' => true, 'fieldset' => true,
        'figcaption' => true, 'figure' => true, 'footer' => true, 'form' => true,
        'frame' => true, 'frameset' => true, 'h1' => true, 'h2' => true,
        'h3' => true, 'h4' => true, 'h5' => true, 'h6' => true, 'head' => true,
        'header' => true, 'hgroup' => true, 'hr' => true, 'html' => true,
        'iframe' => true, 'img' => true, 'input' => true, 'keygen' => true,
        'li' => true, 'link' => true, 'listing' => true, 'main' => true,
        'marquee' => true, 'menu' => true, 'meta' => true, 'nav' => true,
        'noembed' => true, 'noframes' => true, 'noscript' => true, 'object' => true,
        'ol' => true, 'p' => true, 'param' => true, 'plaintext' => true,
        'pre' => true, 'script' => true, 'search' => true, 'section' => true,
        'select' => true, 'source' => true, 'style' => true, 'summary' => true,
        'table' => true, 'tbody' => true, 'td' => true, 'template' => true,
        'textarea' => true, 'tfoot' => true, 'th' => true, 'thead' => true,
        'title' => true, 'tr' => true, 'track' => true, 'ul' => true,
        'wbr' => true, 'xmp' => true,
    ];

    /** Scope list used by "has X in scope" — the base case. */
    private const array SCOPE_BOUNDARIES = [
        'applet', 'caption', 'html', 'table', 'td', 'th',
        'marquee', 'object', 'template',
    ];

    /** Adds "ol" and "ul" to the boundary set for list-item scope. */
    private const array LIST_ITEM_SCOPE_EXTRA = ['ol', 'ul'];

    /** Adds "button" for button scope. */
    private const array BUTTON_SCOPE_EXTRA = ['button'];

    /** Implied-end-tag set per §13.2.4.4. */
    private const array IMPLIED_END_TAG_NAMES = [
        'dd', 'dt', 'li', 'option', 'optgroup', 'p', 'rb', 'rp', 'rt', 'rtc',
    ];

    /** Thorough implied-end-tag set per §13.2.4.4 — used after </template>. */
    private const array IMPLIED_END_TAG_NAMES_THOROUGHLY = [
        'caption', 'colgroup', 'dd', 'dt', 'li', 'optgroup', 'option', 'p',
        'rb', 'rp', 'rt', 'rtc', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr',
    ];

    public function push(Element $element): void
    {
        $this->items[] = $element;
    }

    public function pop(): Element
    {
        $popped = array_pop($this->items);
        if ($popped === null) {
            throw new \LogicException('Cannot pop from empty open-elements stack');
        }
        return $popped;
    }

    public function top(): ?Element
    {
        return $this->items === [] ? null : $this->items[array_key_last($this->items)];
    }

    /** Alias to match spec terminology. */
    public function currentNode(): ?Element
    {
        return $this->top();
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return list<Element> snapshot */
    public function items(): array
    {
        return $this->items;
    }

    public function contains(Element $element): bool
    {
        return in_array($element, $this->items, true);
    }

    public function containsLocalName(string $localName, string $namespace = Document::HTML_NS): bool
    {
        foreach ($this->items as $el) {
            if ($el->localName === $localName && $el->namespaceURI === $namespace) {
                return true;
            }
        }
        return false;
    }

    public function indexOf(Element $element): ?int
    {
        $i = array_search($element, $this->items, true);
        return $i === false ? null : $i;
    }

    public function removeAt(int $index): void
    {
        array_splice($this->items, $index, 1);
    }

    public function remove(Element $element): void
    {
        $i = $this->indexOf($element);
        if ($i !== null) {
            $this->removeAt($i);
        }
    }

    public function replaceAt(int $index, Element $element): void
    {
        $this->items[$index] = $element;
    }

    public function insertAt(int $index, Element $element): void
    {
        array_splice($this->items, $index, 0, [$element]);
    }

    /** Pop elements until the named element has been popped. */
    public function popUntilLocalName(string ...$localNames): void
    {
        while ($this->items !== []) {
            $top = $this->pop();
            if (in_array($top->localName, $localNames, true) && $top->namespaceURI === Document::HTML_NS) {
                return;
            }
        }
    }

    public function popUntilElement(Element $target): void
    {
        while ($this->items !== []) {
            $top = $this->pop();
            if ($top === $target) {
                return;
            }
        }
    }

    /**
     * Has-element-in-scope per §13.2.4.2. Walks up looking for $localName; if
     * a scope boundary is hit first, returns false.
     */
    public function hasInScope(string $localName): bool
    {
        return $this->hasInScopeWithBoundaries($localName, self::SCOPE_BOUNDARIES);
    }

    public function hasInListItemScope(string $localName): bool
    {
        return $this->hasInScopeWithBoundaries(
            $localName,
            array_merge(self::SCOPE_BOUNDARIES, self::LIST_ITEM_SCOPE_EXTRA),
        );
    }

    public function hasInButtonScope(string $localName): bool
    {
        return $this->hasInScopeWithBoundaries(
            $localName,
            array_merge(self::SCOPE_BOUNDARIES, self::BUTTON_SCOPE_EXTRA),
        );
    }

    public function hasInTableScope(string $localName): bool
    {
        return $this->hasInScopeWithBoundaries($localName, ['html', 'table', 'template']);
    }

    /**
     * Select scope is inverse: any element NOT in {optgroup, option} is a boundary.
     */
    public function hasInSelectScope(string $localName): bool
    {
        for ($i = array_key_last($this->items); $i !== null && $i >= 0; $i--) {
            $el = $this->items[$i];
            if ($el->namespaceURI !== Document::HTML_NS) {
                return false;
            }
            if ($el->localName === $localName) {
                return true;
            }
            if (!in_array($el->localName, ['optgroup', 'option'], true)) {
                return false;
            }
        }
        return false;
    }

    /**
     * "Generate implied end tags": pop p/li/dd/dt/option/optgroup/rb/rp/rt/rtc
     * until the current node is no longer one of those, optionally excluding
     * a specific local name.
     */
    public function generateImpliedEndTags(string $except = ''): void
    {
        while ($this->items !== []) {
            $top = $this->top();
            if ($top === null || $top->namespaceURI !== Document::HTML_NS) {
                return;
            }
            if ($top->localName === $except) {
                return;
            }
            if (!in_array($top->localName, self::IMPLIED_END_TAG_NAMES, true)) {
                return;
            }
            $this->pop();
        }
    }

    public function generateImpliedEndTagsThoroughly(): void
    {
        while ($this->items !== []) {
            $top = $this->top();
            if ($top === null || $top->namespaceURI !== Document::HTML_NS) {
                return;
            }
            if (!in_array($top->localName, self::IMPLIED_END_TAG_NAMES_THOROUGHLY, true)) {
                return;
            }
            $this->pop();
        }
    }

    public static function isSpecialHtmlElement(string $localName): bool
    {
        return isset(self::SPECIAL_HTML[$localName]);
    }

    /** @param list<string> $boundaries */
    private function hasInScopeWithBoundaries(string $localName, array $boundaries): bool
    {
        for ($i = array_key_last($this->items); $i !== null && $i >= 0; $i--) {
            $el = $this->items[$i];
            if ($el->localName === $localName && $el->namespaceURI === Document::HTML_NS) {
                return true;
            }
            if (in_array($el->localName, $boundaries, true) && $el->namespaceURI === Document::HTML_NS) {
                return false;
            }
        }
        return false;
    }
}
