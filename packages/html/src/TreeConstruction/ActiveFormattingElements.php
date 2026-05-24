<?php

declare(strict_types=1);

namespace Phpdftk\Html\TreeConstruction;

use Phpdftk\Html\Dom\Element;

/**
 * List of active formatting elements per WHATWG §13.2.4.3.
 *
 * Stores Element entries plus null sentinels ("markers") inserted at
 * applet/object/marquee/template scope boundaries. The list is what makes
 * the adoption agency algorithm work — it lets the tree builder remember
 * which formatting elements (b, i, code, etc.) are "active" but possibly
 * mis-nested with respect to the open-elements stack.
 *
 * Phase 1B.3 ships the data-structure operations; the full reconstruction
 * loop (which materialises formatting elements onto the open-elements stack
 * after they get popped by an unrelated end tag) is implemented as part of
 * TreeBuilder so it has access to the insertion algorithms.
 */
final class ActiveFormattingElements
{
    /**
     * Entries are either Element (for an active formatting element) or null
     * (for a marker).
     *
     * @var list<?Element>
     */
    private array $entries = [];

    public function push(Element $element): void
    {
        // Noah's Ark clause per WHATWG §13.2.4.3: if three entries already
        // exist between the last marker (or list start) and the end that
        // have the same tag name, namespace, and attribute set, remove the
        // earliest. This prevents O(N^2) blowups on pathological input like
        // `<b><b><b>...<b>`.
        $matches = [];
        for ($i = count($this->entries) - 1; $i >= 0; $i--) {
            $entry = $this->entries[$i];
            if ($entry === null) {
                break; // hit a marker
            }
            if ($this->elementsMatchForNoahsArk($entry, $element)) {
                $matches[] = $i;
                if (count($matches) >= 3) {
                    // Remove the earliest of the three.
                    $earliest = $matches[count($matches) - 1];
                    array_splice($this->entries, $earliest, 1);
                    break;
                }
            }
        }
        $this->entries[] = $element;
    }

    /**
     * Two elements match for the Noah's Ark clause iff: same local name,
     * same namespace, and identical attribute sets (name → value).
     */
    private function elementsMatchForNoahsArk(Element $a, Element $b): bool
    {
        if ($a->localName !== $b->localName || $a->namespaceURI !== $b->namespaceURI) {
            return false;
        }
        $attrsA = [];
        foreach ($a->attributes() as $attr) {
            $attrsA[$attr->qualifiedName()] = $attr->value;
        }
        $attrsB = [];
        foreach ($b->attributes() as $attr) {
            $attrsB[$attr->qualifiedName()] = $attr->value;
        }
        if (count($attrsA) !== count($attrsB)) {
            return false;
        }
        foreach ($attrsA as $name => $value) {
            if (!array_key_exists($name, $attrsB) || $attrsB[$name] !== $value) {
                return false;
            }
        }
        return true;
    }

    public function pushMarker(): void
    {
        $this->entries[] = null;
    }

    public function clearToLastMarker(): void
    {
        while ($this->entries !== []) {
            $popped = array_pop($this->entries);
            if ($popped === null) {
                return;
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /** @return list<?Element> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function contains(Element $element): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry === $element) {
                return true;
            }
        }
        return false;
    }

    public function remove(Element $element): void
    {
        foreach ($this->entries as $i => $entry) {
            if ($entry === $element) {
                array_splice($this->entries, $i, 1);
                return;
            }
        }
    }

    public function replace(Element $old, Element $new): void
    {
        foreach ($this->entries as $i => $entry) {
            if ($entry === $old) {
                $this->entries[$i] = $new;
                return;
            }
        }
    }

    public function indexOf(Element $element): ?int
    {
        foreach ($this->entries as $i => $entry) {
            if ($entry === $element) {
                return $i;
            }
        }
        return null;
    }

    public function insertAt(int $index, Element $element): void
    {
        array_splice($this->entries, $index, 0, [$element]);
    }

    /**
     * Find the last element entry between the end of the list and the most
     * recent marker (or the start) whose local name matches. Returns null if
     * no match.
     */
    public function findLastBetweenMarkerAnd(string $localName): ?Element
    {
        for ($i = array_key_last($this->entries); $i !== null && $i >= 0; $i--) {
            $entry = $this->entries[$i];
            if ($entry === null) {
                return null; // hit a marker
            }
            if ($entry->localName === $localName) {
                return $entry;
            }
        }
        return null;
    }

    /** Last entry that is an Element (skip trailing markers). */
    public function lastElement(): ?Element
    {
        for ($i = array_key_last($this->entries); $i !== null && $i >= 0; $i--) {
            $entry = $this->entries[$i];
            if ($entry !== null) {
                return $entry;
            }
        }
        return null;
    }
}
