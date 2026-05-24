<?php

declare(strict_types=1);

namespace Phpdftk\Text;

/**
 * Output of {@see Bidi::analyze}. Stores the runs in logical (source) order
 * and exposes `charLevelAt` for callers that need a per-character level
 * (e.g. shaping a run that spans multiple levels).
 */
final readonly class BidiResult
{
    /** @param list<BidiRun> $runs in logical / source order */
    public function __construct(
        public BidiBase $resolvedBase,
        public array $runs,
    ) {}

    /**
     * Resolved bidi level for a single byte offset. Returns null if the
     * offset is outside any run (typically because it's at or past the end
     * of the input).
     *
     * Binary searches `runs` — O(log n).
     */
    public function charLevelAt(int $offset): ?int
    {
        if ($this->runs === []) {
            return null;
        }
        $lo = 0;
        $hi = count($this->runs) - 1;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            $run = $this->runs[$mid];
            if ($offset < $run->offset) {
                $hi = $mid - 1;
            } elseif ($offset >= $run->offset + $run->length) {
                $lo = $mid + 1;
            } else {
                return $run->level;
            }
        }
        return null;
    }
}
