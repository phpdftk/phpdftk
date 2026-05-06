<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Parser;

/**
 * Scans raw PDF bytes for indirect object definitions to reconstruct
 * a cross-reference table when the normal xref is corrupted.
 */
final class ObjectScanner
{
    /**
     * Scan the PDF bytes for all `N M obj` patterns.
     *
     * Tolerates malformed object headers (no trailing whitespace after
     * `obj`, e.g. `0 0 objParams`). Validates that the digits are
     * preceded by a non-digit byte (or BOF) so we don't match the
     * tail of a longer number.
     *
     * @return array<int, int> objectNumber => byte offset
     */
    public static function scan(string $data): array
    {
        $map = [];

        // Match `N M obj` allowing any character (or end-of-string) after
        // `obj` instead of a strict word boundary. PDFium fuzz inputs have
        // `0 0 objParams` style headers that the strict `\bobj\b` rejects.
        if (preg_match_all('/(?<![0-9])(\d+)[ \t\r\n]+(\d+)[ \t\r\n]+obj/', $data, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => $match) {
                $objNum = (int) $matches[1][$i][0];
                $byteOffset = (int) $match[1];

                // Keep the LAST occurrence (latest revision wins)
                $map[$objNum] = $byteOffset;
            }
        }

        ksort($map);

        return $map;
    }
}
