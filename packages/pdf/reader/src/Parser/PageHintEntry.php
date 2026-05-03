<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Parser;

/**
 * A single entry from the page offset hint table — ISO 32000-2 §F.4.1.
 *
 * Each entry describes one page's objects and byte range within the
 * linearized file. The values are deltas from the table header's minimums.
 */
final class PageHintEntry
{
    public function __construct(
        public readonly int $objectCountDelta,
        public readonly int $pageLengthDelta,
        public readonly int $sharedRefCountDelta,
        /** @var list<int> */
        public readonly array $sharedObjIds,
        public readonly int $sharedObjNumeratorDelta,
        public readonly int $contentStreamOffsetDelta,
        public readonly int $contentStreamLengthDelta,
    ) {}
}
