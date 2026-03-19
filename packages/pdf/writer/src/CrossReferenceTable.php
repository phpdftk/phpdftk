<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer;

/**
 * Builds the PDF cross-reference table.
 *
 * Each entry is exactly 20 bytes:
 *   OOOOOOOOOO GGGGG N \r\n   (in-use objects)
 *   OOOOOOOOOO GGGGG F \r\n   (free objects)
 *
 * where O = 10-digit byte offset, G = 5-digit generation number.
 */
class CrossReferenceTable
{
    /** @var array<int, int> objectNumber => byte offset */
    private array $entries = [];

    /**
     * Record the byte offset for an in-use object.
     */
    public function add(int $objNum, int $offset): void
    {
        $this->entries[$objNum] = $offset;
    }

    /**
     * Build and return the complete xref section as a string.
     * The returned string starts with "xref\n" and ends with the last entry (no trailing newline).
     */
    public function build(int $size): string
    {
        $xref = "xref\n";
        $xref .= sprintf("0 %d\n", $size);

        // Object 0: free list head — generation 65535, free
        $xref .= "0000000000 65535 f \r\n";

        // Objects 1..N
        for ($i = 1; $i < $size; $i++) {
            $offset = $this->entries[$i] ?? 0;
            // Each entry is exactly 20 bytes: 10 + space + 5 + space + n/f + space + CR + LF
            $xref .= sprintf("%010d 00000 n \r\n", $offset);
        }

        return $xref;
    }
}
