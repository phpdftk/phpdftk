<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Bookmark;

/**
 * A bookmark (outline item) for the toolkit API.
 *
 * Represents a single entry in the document outline tree.
 * Page numbers are 1-based.
 */
final readonly class BookmarkEntry
{
    /**
     * @param list<BookmarkEntry> $children
     */
    public function __construct(
        public string $title,
        public int $pageNumber,
        public array $children = [],
    ) {}
}
