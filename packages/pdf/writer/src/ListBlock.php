<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

/**
 * Bullet or numbered list of plain strings. Each item is rendered as
 * one marker (bullet glyph or running number) followed by the wrapped
 * item text.
 *
 * Nested lists are a planned future extension; for now, build flat
 * lists and call `Pdf::addList()` multiple times for hand-rolled
 * hierarchy.
 *
 * Named `ListBlock` (not `List`) to avoid colliding with PHP's reserved
 * `list` keyword.
 */
final class ListBlock
{
    /**
     * @param list<string> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly bool $numbered = false,
    ) {}
}
