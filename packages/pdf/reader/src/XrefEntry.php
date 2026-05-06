<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader;

/**
 * A single entry from the cross-reference table.
 *
 * @param int $type       0 = free, 1 = in-use, 2 = compressed (in ObjStm)
 * @param int $offset     byte offset (type 1) or containing ObjStm number (type 2)
 * @param int $generation generation number (type 0/1) or index within ObjStm (type 2)
 */
final readonly class XrefEntry
{
    public const TYPE_FREE = 0;
    public const TYPE_IN_USE = 1;
    public const TYPE_COMPRESSED = 2;

    public function __construct(
        public int $type,
        public int $offset,
        public int $generation,
    ) {}
}
