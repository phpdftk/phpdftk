<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\Encoding\WinAnsiTable;
use Phpdftk\FontMetrics\AfmData;

/**
 * Shared text-layout primitives — greedy word wrapping and width
 * measurement against AFM metrics. Used by `Pdf` and by the shared
 * primitive renderers (`TableRenderer`, `ListRenderer`, etc.) so flow
 * layout and explicit placement agree on geometry.
 *
 * Width measurement assumes WinAnsi-encoded byte input: the caller is
 * responsible for encoding UTF-8 to the font's byte encoding before
 * calling `measure()` / `wrap()`. This matches what `ContentStream::showText()`
 * expects when the caller passes a resource-name string (no auto-encoding).
 */
final class TextLayout
{
    /** @var array<int, string> WinAnsi byte → glyph name, cached after first call. */
    private static ?array $winAnsi = null;

    /**
     * Measure a single line of byte-encoded text in points.
     */
    public static function measure(string $text, AfmData $metrics, float $size): float
    {
        $winAnsi = self::winAnsi();
        $units = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($text[$i]);
            $glyph = $winAnsi[$byte] ?? '.notdef';
            $units += $metrics->getWidth($glyph);
        }
        return ($units / 1000.0) * $size;
    }

    /**
     * Greedy word-wrap: split on whitespace, pack words onto lines
     * until the next word would overflow the column width. A single
     * word wider than the column is emitted on its own line without
     * mid-word breaking. Explicit newlines produce paragraph breaks
     * within the returned line list.
     *
     * @return list<string>
     */
    public static function wrap(string $text, AfmData $metrics, float $size, float $columnWidth): array
    {
        $out = [];
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = explode("\n", $text);

        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph)) ?: [];
            if ($words === [''] || $words === []) {
                $out[] = '';
                continue;
            }
            $line = '';
            foreach ($words as $word) {
                $candidate = $line === '' ? $word : ($line . ' ' . $word);
                if (self::measure($candidate, $metrics, $size) <= $columnWidth) {
                    $line = $candidate;
                } else {
                    if ($line !== '') {
                        $out[] = $line;
                    }
                    $line = $word;
                }
            }
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out;
    }

    /** @return array<int, string> */
    private static function winAnsi(): array
    {
        return self::$winAnsi ??= WinAnsiTable::getTable();
    }
}
