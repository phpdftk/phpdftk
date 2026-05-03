<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Parser;

/**
 * Parsed shared object hint table — ISO 32000-2 §F.4.2.
 */
final class SharedObjectHintTable
{
    /**
     * @param list<SharedObjectHintEntry> $entries
     */
    public function __construct(
        public readonly int $firstSharedObjNumber,
        public readonly int $firstSharedObjOffset,
        public readonly int $numSharedGroups,
        public readonly int $minGroupLength,
        public readonly array $entries,
    ) {}
}
