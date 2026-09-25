<?php

declare(strict_types=1);

namespace Phpdftk\Text;

/**
 * CSS Text 3 §2.1 — `text-transform`, applied to a text run before
 * shaping.
 *
 * Shared because both text pipelines need it: HTML inline layout, and
 * the SVG painter for `<text>`. They used to be the same algorithm in
 * only one of them.
 *
 *  - `uppercase` / `lowercase` are full Unicode case mappings.
 *  - `capitalize` upper-cases the first grapheme of each
 *    whitespace-separated word, preserving the original separators.
 *  - `full-width` (CSS Text 4 §2.1.4) maps ASCII U+0021..U+007E to the
 *    full-width forms U+FF01..U+FF5E, and ASCII space to the
 *    ideographic space U+3000.
 *  - Anything else — `none`, `full-size-kana`, an unknown keyword —
 *    passes through unchanged.
 */
final class TextTransform
{
    public static function apply(string $text, ?string $keyword): string
    {
        if ($keyword === null || $text === '') {
            return $text;
        }
        return match (strtolower(trim($keyword))) {
            'uppercase' => mb_strtoupper($text, 'UTF-8'),
            'lowercase' => mb_strtolower($text, 'UTF-8'),
            'capitalize' => self::capitalizeWords($text),
            'full-width' => self::toFullWidth($text),
            default => $text,
        };
    }

    private static function capitalizeWords(string $text): string
    {
        // Split on whitespace runs, capitalize the first codepoint of each
        // non-empty word, and rejoin with the original separators.
        $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }
        $out = '';
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^\s+$/u', $part) === 1) {
                $out .= $part;
                continue;
            }
            $first = mb_substr($part, 0, 1, 'UTF-8');
            $rest = mb_substr($part, 1, null, 'UTF-8');
            $out .= mb_strtoupper($first, 'UTF-8') . $rest;
        }
        return $out;
    }

    private static function toFullWidth(string $text): string
    {
        $out = '';
        foreach (mb_str_split($text, 1, 'UTF-8') as $ch) {
            $cp = mb_ord($ch, 'UTF-8');
            if ($cp === false) {
                $out .= $ch;
                continue;
            }
            $out .= match (true) {
                $cp === 0x0020 => mb_chr(0x3000, 'UTF-8'),
                $cp >= 0x0021 && $cp <= 0x007E => mb_chr($cp + 0xFEE0, 'UTF-8'),
                default => $ch,
            };
        }
        return $out;
    }
}
