<?php

declare(strict_types=1);

namespace Phpdftk\PagedMedia;

use Phpdftk\PagedMedia\Fragmentation\BreakRule;

/**
 * Paged-media engine — the consumer-facing entry point.
 *
 * Phase 4G scaffold. The engine's concrete behaviour lands in
 * sub-phases:
 *
 *   4G.1  Extract `@page` selector resolution, margin-box
 *         collection, page-background painter, fragmentation
 *         primitives from `phpdftk/html-to-pdf` (no semantic
 *         change)
 *   4G.2  Named pages — `page: foo` property + `@page foo` selector
 *   4G.3  Running elements — `position: running()` + `running()`
 *         in `content:` + cross-page reuse
 *   4G.4  `string-set` + `content: string(name)` named strings
 *   4G.5  Cross-references — `target-counter()`, `target-text()`,
 *         leaders, lists of figures / tables
 *   4G.6  Page floats — `float: top` / `bottom` / column floats,
 *         float-defer model
 *
 * The public API surface is stable from this scaffold so call sites
 * in html-to-pdf, svg-to-pdf, and the high-level Pdf API can be
 * written against it now.
 */
final class Engine
{
    /**
     * @param list<object> $stylesheets Parsed CSS stylesheets
     *                                  (typed as `phpdftk/css`
     *                                  `Stylesheet`; declared as
     *                                  `list<object>` here to avoid
     *                                  the scaffold importing the
     *                                  full css type tree).
     */
    public function __construct(
        private readonly array $stylesheets = [],
    ) {}

    /**
     * Resolve the page box for one rendered page. Walks the
     * `@page` cascade applying named-page + pseudo-class selectors,
     * resolves `size` + `margin` + `background-*`, collects the
     * 16 margin boxes.
     *
     * @param list<PageSelector> $pseudoClasses
     */
    public function resolvePageBox(
        int $pageIndex,
        ?string $namedPage = null,
        array $pseudoClasses = [],
    ): PageBox {
        unset($pageIndex, $namedPage, $pseudoClasses);
        throw new \RuntimeException('4G.1 not yet implemented');
    }

    /**
     * Decide where to break a flowed block.
     *
     * `$blockHeight`        the block's measured height in points
     * `$availableHeight`    space left in the current page area
     * `$breakBefore`        `break-before` value on the block
     * `$breakAfter`         `break-after` value on the next sibling
     * `$breakInsideAvoid`   `break-inside: avoid` on this block?
     *
     * Returns the y-coordinate (in content-area space) where the
     * break should occur, or `null` to indicate the block fits on
     * the current page and no break is needed.
     */
    public function findBreak(
        float $blockHeight,
        float $availableHeight,
        BreakRule $breakBefore = BreakRule::Auto,
        BreakRule $breakAfter = BreakRule::Auto,
        bool $breakInsideAvoid = false,
    ): ?float {
        unset($blockHeight, $availableHeight, $breakBefore, $breakAfter, $breakInsideAvoid);
        throw new \RuntimeException('4G.1 not yet implemented');
    }

    /**
     * @return list<object>
     */
    public function stylesheets(): array
    {
        return $this->stylesheets;
    }
}
