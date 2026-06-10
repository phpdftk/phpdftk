<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

/**
 * MathML `mathvariant` → Mathematical Alphanumeric Symbols
 * transformation (Core §3.2.3).
 *
 * When a token element carries `mathvariant="bold"` (or italic,
 * script, fraktur, double-struck, sans-serif, monospace, etc.),
 * its ASCII Latin letters and digits map to the pre-encoded
 * stylistic variants in the U+1D400-U+1D7FF Mathematical
 * Alphanumeric Symbols block. The transformation is a static
 * codepoint-by-codepoint mapping defined by Unicode; the painter
 * runs it before emitting the token's content so the resulting
 * codepoints feed directly into the font's cmap.
 *
 * The block has several "holes" - codepoints that would have
 * landed at a position which Unicode had already assigned for
 * historic reasons (PLANCK CONSTANT, SCRIPT CAPITAL B, etc.).
 * Those overrides are captured per-variant alongside the base
 * offsets.
 *
 * Scope:
 *   - Latin uppercase (A-Z), lowercase (a-z), digits (0-9).
 *   - All 13 standard variants from the Mathematical Alphanumeric
 *     Symbols block plus `normal` (identity).
 *
 * Out of scope (follow-ups):
 *   - Greek letters (U+1D6A4-U+1D7C9 region).
 *   - Hebrew double-struck letters (U+2135-U+2138).
 *   - `mathvariant` inheritance via `mstyle` (Core treats the
 *     attribute as non-inherited and deprecated; we apply it
 *     per-token).
 */
final class MathvariantTransform
{
    /**
     * Apply the transformation table for `$variant` to each
     * codepoint in `$content`. Codepoints outside the supported
     * ASCII ranges pass through unchanged. Unknown variants (or
     * `normal`) leave the input unchanged.
     */
    public static function apply(string $content, string $variant): string
    {
        $normalized = strtolower(trim($variant));
        if ($normalized === '' || $normalized === 'normal') {
            return $content;
        }
        $rule = self::VARIANTS[$normalized] ?? null;
        if ($rule === null) {
            return $content;
        }
        $output = '';
        foreach (mb_str_split($content, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp === false) {
                $output .= $char;
                continue;
            }
            $mapped = self::mapCodepoint($cp, $rule);
            $output .= $mapped !== null
                ? mb_chr($mapped, 'UTF-8')
                : $char;
        }
        return $output;
    }

    /**
     * True when `$variant` (case-insensitive) corresponds to a
     * supported transformation rule. Useful for callers that want
     * to skip the transformation entirely on unknown values.
     */
    public static function supports(string $variant): bool
    {
        $normalized = strtolower(trim($variant));
        return $normalized === 'normal' || isset(self::VARIANTS[$normalized]);
    }

    /**
     * @param array{
     *   upper: ?int,
     *   lower: ?int,
     *   digit: ?int,
     *   overrides: array<int, int>
     * } $rule
     */
    private static function mapCodepoint(int $cp, array $rule): ?int
    {
        if (isset($rule['overrides'][$cp])) {
            return $rule['overrides'][$cp];
        }
        if ($cp >= 0x41 && $cp <= 0x5A && $rule['upper'] !== null) {
            return $rule['upper'] + ($cp - 0x41);
        }
        if ($cp >= 0x61 && $cp <= 0x7A && $rule['lower'] !== null) {
            return $rule['lower'] + ($cp - 0x61);
        }
        if ($cp >= 0x30 && $cp <= 0x39 && $rule['digit'] !== null) {
            return $rule['digit'] + ($cp - 0x30);
        }
        return null;
    }

    /**
     * Per-variant base offsets plus historic overrides. Each row
     * carries the base codepoint for the 'A', 'a', and '0' slots
     * (any of which may be null if the variant has no glyphs for
     * that range) and an `overrides` map for ASCII codepoints that
     * map elsewhere because Unicode had already assigned the slot
     * (e.g. PLANCK CONSTANT for italic 'h').
     *
     * @var array<string, array{
     *   upper: ?int,
     *   lower: ?int,
     *   digit: ?int,
     *   overrides: array<int, int>
     * }>
     */
    private const array VARIANTS = [
        'bold' => [
            'upper'     => 0x1D400,
            'lower'     => 0x1D41A,
            'digit'     => 0x1D7CE,
            'overrides' => [],
        ],
        'italic' => [
            'upper'     => 0x1D434,
            'lower'     => 0x1D44E,
            'digit'     => null,
            'overrides' => [
                0x0068 => 0x210E, // h -> PLANCK CONSTANT
            ],
        ],
        'bold-italic' => [
            'upper'     => 0x1D468,
            'lower'     => 0x1D482,
            'digit'     => null,
            'overrides' => [],
        ],
        'script' => [
            'upper'     => 0x1D49C,
            'lower'     => 0x1D4B6,
            'digit'     => null,
            'overrides' => [
                0x0042 => 0x212C, // B -> SCRIPT CAPITAL B
                0x0045 => 0x2130, // E -> SCRIPT CAPITAL E
                0x0046 => 0x2131, // F -> SCRIPT CAPITAL F
                0x0048 => 0x210B, // H -> SCRIPT CAPITAL H
                0x0049 => 0x2110, // I -> SCRIPT CAPITAL I
                0x004C => 0x2112, // L -> SCRIPT CAPITAL L
                0x004D => 0x2133, // M -> SCRIPT CAPITAL M
                0x0052 => 0x211B, // R -> SCRIPT CAPITAL R
                0x0065 => 0x212F, // e -> SCRIPT SMALL E
                0x0067 => 0x210A, // g -> SCRIPT SMALL G
                0x006F => 0x2134, // o -> SCRIPT SMALL O
            ],
        ],
        'bold-script' => [
            'upper'     => 0x1D4D0,
            'lower'     => 0x1D4EA,
            'digit'     => null,
            'overrides' => [],
        ],
        'fraktur' => [
            'upper'     => 0x1D504,
            'lower'     => 0x1D51E,
            'digit'     => null,
            'overrides' => [
                0x0043 => 0x212D, // C -> BLACK-LETTER CAPITAL C
                0x0048 => 0x210C, // H -> BLACK-LETTER CAPITAL H
                0x0049 => 0x2111, // I -> BLACK-LETTER CAPITAL I
                0x0052 => 0x211C, // R -> BLACK-LETTER CAPITAL R
                0x005A => 0x2128, // Z -> BLACK-LETTER CAPITAL Z
            ],
        ],
        'bold-fraktur' => [
            'upper'     => 0x1D56C,
            'lower'     => 0x1D586,
            'digit'     => null,
            'overrides' => [],
        ],
        'double-struck' => [
            'upper'     => 0x1D538,
            'lower'     => 0x1D552,
            'digit'     => 0x1D7D8,
            'overrides' => [
                0x0043 => 0x2102, // C -> DOUBLE-STRUCK CAPITAL C
                0x0048 => 0x210D, // H -> DOUBLE-STRUCK CAPITAL H
                0x004E => 0x2115, // N -> DOUBLE-STRUCK CAPITAL N
                0x0050 => 0x2119, // P -> DOUBLE-STRUCK CAPITAL P
                0x0051 => 0x211A, // Q -> DOUBLE-STRUCK CAPITAL Q
                0x0052 => 0x211D, // R -> DOUBLE-STRUCK CAPITAL R
                0x005A => 0x2124, // Z -> DOUBLE-STRUCK CAPITAL Z
            ],
        ],
        'sans-serif' => [
            'upper'     => 0x1D5A0,
            'lower'     => 0x1D5BA,
            'digit'     => 0x1D7E2,
            'overrides' => [],
        ],
        'bold-sans-serif' => [
            'upper'     => 0x1D5D4,
            'lower'     => 0x1D5EE,
            'digit'     => 0x1D7EC,
            'overrides' => [],
        ],
        'sans-serif-italic' => [
            'upper'     => 0x1D608,
            'lower'     => 0x1D622,
            'digit'     => null,
            'overrides' => [],
        ],
        'sans-serif-bold-italic' => [
            'upper'     => 0x1D63C,
            'lower'     => 0x1D656,
            'digit'     => null,
            'overrides' => [],
        ],
        'monospace' => [
            'upper'     => 0x1D670,
            'lower'     => 0x1D68A,
            'digit'     => 0x1D7F6,
            'overrides' => [],
        ],
    ];
}
