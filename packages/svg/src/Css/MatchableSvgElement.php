<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Css;

use Phpdftk\Css\Selector\MatchableElement;
use Phpdftk\Svg\Element;

/**
 * Adapter that lets the `phpdftk/css` Selectors-4 matcher walk an
 * `Phpdftk\Svg\Element` tree. Lives in its own namespace so the SVG parser
 * stays usable without the CSS dependency — only callers that opt into the
 * bridge layer load this class, and only then does PHP resolve the
 * `MatchableElement` interface.
 *
 * The adapter is read-only: it never mutates the wrapped element. Sibling
 * and child traversals filter out `Text` data nodes — only `Element`
 * instances are visible to the matcher, matching the CSS Selectors model.
 */
final class MatchableSvgElement implements MatchableElement
{
    /** SVG namespace URI — every parsed SVG element lives in this namespace. */
    public const string SVG_NS = 'http://www.w3.org/2000/svg';

    public function __construct(public readonly Element $element) {}

    public function localName(): string
    {
        return $this->element->localName;
    }

    public function namespaceUri(): string
    {
        return self::SVG_NS;
    }

    public function elementId(): ?string
    {
        $id = $this->element->getAttribute('id');
        if ($id === null) {
            return null;
        }
        $trimmed = trim($id);
        return $trimmed === '' ? null : strtolower($trimmed);
    }

    /** @return list<string> */
    public function classes(): array
    {
        return $this->element->classList();
    }

    public function hasAttribute(string $name): bool
    {
        return $this->element->hasAttribute($name);
    }

    public function getAttributeValue(string $name): ?string
    {
        return $this->element->getAttribute($name);
    }

    /** @return array<string, string> */
    public function allAttributes(): array
    {
        return $this->element->attributes;
    }

    public function parentElement(): ?MatchableElement
    {
        $parent = $this->element->parent;
        return $parent === null ? null : new self($parent);
    }

    public function previousElementSibling(): ?MatchableElement
    {
        $siblings = $this->parentElementChildren();
        if ($siblings === []) {
            return null;
        }
        $position = $this->positionAmong($siblings);
        if ($position <= 0) {
            return null;
        }
        return new self($siblings[$position - 1]);
    }

    public function nextElementSibling(): ?MatchableElement
    {
        $siblings = $this->parentElementChildren();
        if ($siblings === []) {
            return null;
        }
        $position = $this->positionAmong($siblings);
        if ($position < 0 || $position + 1 >= count($siblings)) {
            return null;
        }
        return new self($siblings[$position + 1]);
    }

    /** @return list<MatchableElement> */
    public function elementChildren(): array
    {
        $out = [];
        foreach ($this->element->children as $child) {
            if ($child instanceof Element) {
                $out[] = new self($child);
            }
        }
        return $out;
    }

    public function indexAmongSiblings(): int
    {
        $siblings = $this->parentElementChildren();
        if ($siblings === []) {
            return 1;
        }
        $position = $this->positionAmong($siblings);
        return $position < 0 ? 1 : $position + 1;
    }

    public function indexAmongSiblingsFromEnd(): int
    {
        $siblings = $this->parentElementChildren();
        if ($siblings === []) {
            return 1;
        }
        $position = $this->positionAmong($siblings);
        return $position < 0 ? 1 : count($siblings) - $position;
    }

    public function indexAmongTypeSiblings(): int
    {
        $sameType = $this->sameTypeSiblings();
        if ($sameType === []) {
            return 1;
        }
        $position = $this->positionAmong($sameType);
        return $position < 0 ? 1 : $position + 1;
    }

    public function indexAmongTypeSiblingsFromEnd(): int
    {
        $sameType = $this->sameTypeSiblings();
        if ($sameType === []) {
            return 1;
        }
        $position = $this->positionAmong($sameType);
        return $position < 0 ? 1 : count($sameType) - $position;
    }

    /** @return list<Element> */
    private function parentElementChildren(): array
    {
        $parent = $this->element->parent;
        if ($parent === null) {
            return [];
        }
        $out = [];
        foreach ($parent->children as $child) {
            if ($child instanceof Element) {
                $out[] = $child;
            }
        }
        return $out;
    }

    /** @return list<Element> */
    private function sameTypeSiblings(): array
    {
        $out = [];
        foreach ($this->parentElementChildren() as $child) {
            if ($child->localName === $this->element->localName) {
                $out[] = $child;
            }
        }
        return $out;
    }

    /**
     * @param list<Element> $elements
     * @return int -1 when this element isn't in the list (shouldn't happen
     *             for a well-formed tree, but defensive)
     */
    private function positionAmong(array $elements): int
    {
        foreach ($elements as $i => $candidate) {
            if ($candidate === $this->element) {
                return $i;
            }
        }
        return -1;
    }
}
