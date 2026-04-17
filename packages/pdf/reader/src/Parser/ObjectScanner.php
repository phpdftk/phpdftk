<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Parser;

/**
 * Scans raw PDF bytes for indirect object definitions to reconstruct
 * a cross-reference table when the normal xref is corrupted.
 */
final class ObjectScanner
{
    /**
     * Scan the PDF bytes for all `N M obj` patterns.
     *
     * @return array<int, int> objectNumber => byte offset
     */
    public static function scan(string $data): array
    {
        $map = [];

        if (preg_match_all('/(\d+)\s+(\d+)\s+obj\b/', $data, $matches, PREG_OFFSET_CAPTURE)) {
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
