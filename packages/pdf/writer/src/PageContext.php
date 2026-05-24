<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

/**
 * Context passed to per-page render hooks (header / footer / watermark).
 *
 * Closures registered via {@see Pdf::setHeader()}, {@see Pdf::setFooter()},
 * or {@see Pdf::setWatermark()} receive this object so they can draw on
 * the page with full knowledge of which page they're on, how many pages
 * the final document will contain, and the page's geometry.
 *
 * Note: the closure runs in a deferred pass after all flow content has
 * been placed, which is why `totalPages` is reliably set — unlike in
 * the middle of `addText()` calls, where the document is still growing.
 */
final class PageContext
{
    public function __construct(
        public readonly int $pageNumber,
        public readonly int $totalPages,
        public readonly Page $page,
        public readonly float $pageWidth,
        public readonly float $pageHeight,
        public readonly Theme $theme,
    ) {}
}
