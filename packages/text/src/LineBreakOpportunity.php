<?php

declare(strict_types=1);

namespace Phpdftk\Text;

/**
 * One enumerated line-break position in a source string. Offsets are byte
 * offsets into the original UTF-8 string (matching the rest of phpdftk's
 * byte-offset convention), making it cheap for callers to slice the
 * original input without re-encoding.
 *
 * `kind` reports whether ICU classified the break as mandatory (a
 * line-terminator character) or merely allowed (whitespace, hyphen, etc.).
 */
final readonly class LineBreakOpportunity
{
    public function __construct(
        public int $offset,
        public LineBreakKind $kind,
    ) {}
}
