<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * The root `<svg>` element. Inherits the viewBox / width / height accessors
 * from `ViewportElement` (shared with `<symbol>`), and owns the
 * document-level `findById()` resolver used by `<use>` and gradient `href`
 * lookups.
 *
 * The id index is lazily built on first lookup and then cached. Mutating the
 * tree after the first lookup invalidates the cache; rebuild by calling
 * `invalidateIdIndex()` (or just call findById on a freshly parsed
 * document — the common case).
 */
final class SvgDocument extends ViewportElement
{
    /** @var array<string, Element>|null */
    private ?array $idIndex = null;

    public function __construct()
    {
        parent::__construct('svg');
    }

    /**
     * Look up an element by its `id` attribute. Returns null when no
     * element with that id exists. Lazily caches the index after the
     * first call.
     */
    public function findById(string $id): ?Element
    {
        if ($id === '') {
            return null;
        }
        if ($this->idIndex === null) {
            $this->idIndex = [];
            self::indexInto($this, $this->idIndex);
        }
        return $this->idIndex[$id] ?? null;
    }

    /**
     * Drop the cached id index — call this after mutating the tree if
     * you need subsequent `findById` calls to see the new state.
     */
    public function invalidateIdIndex(): void
    {
        $this->idIndex = null;
    }

    /**
     * @param array<string, Element> $into
     */
    private static function indexInto(Element $element, array &$into): void
    {
        $id = $element->getAttribute('id');
        if ($id !== null && $id !== '' && !isset($into[$id])) {
            // SVG 2: duplicate ids are technically invalid; pick the first
            // in document order — same shape browsers use for
            // querySelector('#id').
            $into[$id] = $element;
        }
        foreach ($element->children as $child) {
            if ($child instanceof Element) {
                self::indexInto($child, $into);
            }
        }
    }
}
