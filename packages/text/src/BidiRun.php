<?php

declare(strict_types=1);

namespace Phpdftk\Text;

/**
 * A contiguous span of characters at one resolved bidi embedding level.
 *
 * Offsets are byte offsets into the original UTF-8 string. Level 0 is LTR,
 * level 1 is RTL, higher levels indicate nested embedding. Layout consumers
 * reorder runs into visual order using the standard UAX #9 §3.4 algorithm
 * (`reverse` consecutive runs at each level from the highest down).
 */
final readonly class BidiRun
{
    public function __construct(
        public int $offset,
        public int $length,
        public int $level,
    ) {}
}
