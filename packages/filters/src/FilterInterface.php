<?php

declare(strict_types=1);

namespace Phpdftk\Filters;

/**
 * Symmetric encode/decode contract for PDF stream filters (ISO 32000-2 §7.4).
 *
 * Each implementation corresponds to a PDF filter name (/FlateDecode,
 * /ASCII85Decode, etc.). Encode compresses for storage; decode
 * reverses it for reading.
 */
interface FilterInterface
{
    public function encode(string $data): string;
    public function decode(string $data): string;
}
