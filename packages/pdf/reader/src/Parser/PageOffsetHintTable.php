<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Parser;

/**
 * Parsed page offset hint table — ISO 32000-2 §F.4.1.
 *
 * Contains the header values (minimums and bit widths) and per-page entries
 * for computing byte ranges of individual pages in a linearized PDF.
 */
final class PageOffsetHintTable
{
    /**
     * @param list<PageHintEntry> $entries
     */
    public function __construct(
        public readonly int $minObjectsPerPage,
        public readonly int $firstPageLocation,
        public readonly int $minPageLength,
        public readonly int $minSharedRefsPerPage,
        public readonly int $minSharedObjId,
        public readonly int $minContentStreamOffset,
        public readonly int $minContentStreamLength,
        public readonly array $entries,
    ) {}

    /**
     * Compute the byte offset and length for a page (0-indexed).
     *
     * @return array{offset: int, length: int}
     */
    public function getPageByteRange(int $pageIndex): array
    {
        if ($pageIndex < 0 || $pageIndex >= count($this->entries)) {
            throw new \OutOfRangeException("Page index {$pageIndex} out of range");
        }

        // For the first page (index 0), the location is the firstPageLocation
        // For subsequent pages, we sum up lengths starting from after firstPageEnd
        if ($pageIndex === 0) {
            $length = $this->minPageLength + $this->entries[0]->pageLengthDelta;
            return ['offset' => $this->firstPageLocation, 'length' => $length];
        }

        // Pages after the first are sequential in the file, starting after
        // the first-page section. Their offsets are cumulative.
        $offset = 0;
        for ($i = 1; $i < $pageIndex; $i++) {
            $offset += $this->minPageLength + $this->entries[$i]->pageLengthDelta;
        }

        $length = $this->minPageLength + $this->entries[$pageIndex]->pageLengthDelta;

        return ['offset' => $offset, 'length' => $length];
    }
}
