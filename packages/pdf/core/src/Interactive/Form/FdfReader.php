<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Form;

/**
 * FDF (Forms Data Format) file reader — ISO 32000-2 §12.7.8.
 *
 * Parses a standalone .fdf file and extracts field name/value pairs.
 */
final class FdfReader
{
    /**
     * Parse FDF content and return a field name → value map.
     *
     * @return array<string, string>
     */
    public static function parse(string $fdfContent): array
    {
        $fields = [];

        // Find field dictionaries: << /T (...) /V (...) >>
        // Use a regex that handles escaped parentheses inside strings
        $stringPattern = '\((?:[^()\\\\]|\\\\.)*\)';

        // Match /T and /V in either order
        $pattern = '/<<[^>]*?\/T\s*(' . $stringPattern . ')[^>]*?\/V\s*(' . $stringPattern . ')[^>]*?>>/s';
        if (preg_match_all($pattern, $fdfContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = self::decodeString($match[1]);
                $value = self::decodeString($match[2]);
                $fields[$name] = $value;
            }
        }

        // Also match /V before /T
        $pattern2 = '/<<[^>]*?\/V\s*(' . $stringPattern . ')[^>]*?\/T\s*(' . $stringPattern . ')[^>]*?>>/s';
        if (preg_match_all($pattern2, $fdfContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = self::decodeString($match[1]);
                $name = self::decodeString($match[2]);
                if (!isset($fields[$name])) {
                    $fields[$name] = $value;
                }
            }
        }

        return $fields;
    }

    /**
     * Decode a PDF literal string: strip outer parens, unescape sequences.
     */
    private static function decodeString(string $raw): string
    {
        // Strip outer parentheses
        $inner = substr($raw, 1, -1);
        // Unescape: \\ → \, \( → (, \) → )
        return str_replace(['\\\\', '\\(', '\\)'], ['\\', '(', ')'], $inner);
    }
}
