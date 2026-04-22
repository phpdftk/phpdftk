<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Parser;

/**
 * A single entry from the shared object hint table — ISO 32000-2 §F.4.2.
 */
final class SharedObjectHintEntry
{
    public function __construct(
        public readonly int $lengthDelta,
        public readonly bool $isSignatureObject,
        public readonly int $numObjects,
    ) {}
}
