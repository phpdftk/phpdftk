<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\File;

/**
 * Builds the classic PDF cross-reference table (ISO 32000-2 section 7.5.4).
 *
 * The fixed 20-byte entry width is mandated by the spec so that readers can
 * seek directly to any entry by index without parsing the entire table:
 *
 *   OOOOOOOOOO GGGGG n\r\n   (in-use object: O = 10-digit byte offset)
 *   OOOOOOOOOO GGGGG f\r\n   (free object: O = next free object number)
 *
 * Per §7.5.4 the two-character EOL is one of `SP CR`, `SP LF`, or `CR LF`;
 * we use `CR LF` (no trailing space) so the entry totals exactly 20 bytes.
 *
 * Object 0 is always emitted as the free-list head with generation 65535,
 * which signals that it can never be reused.
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
     * Return recorded entries (objectNumber => byte offset).
     *
     * @return array<int, int>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Build and return the complete xref section as a string.
     * The returned string starts with "xref\n" and ends with the last entry (no trailing newline).
     */
    public function build(int $size): string
    {
        $xref = "xref\n";
        $xref .= sprintf("0 %d\n", $size);

        // Object 0: free list head — generation 65535, free.
        // Layout: 10-digit offset + SP + 5-digit gen + SP + 'f' + CRLF = 20 bytes.
        $xref .= "0000000000 65535 f\r\n";

        // Objects 1..N — same 20-byte layout as the free-list head.
        for ($i = 1; $i < $size; $i++) {
            $offset = $this->entries[$i] ?? 0;
            $xref .= sprintf("%010d 00000 n\r\n", $offset);
        }

        return $xref;
    }
}
