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
     *   greek: ?int,
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
        if (isset(self::GREEK_OFFSETS[$cp]) && $rule['greek'] !== null) {
            return $rule['greek'] + self::GREEK_OFFSETS[$cp];
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
     *   greek: ?int,
     *   overrides: array<int, int>
     * }>
     */
    private const array VARIANTS = [
        'bold' => [
            'upper'     => 0x1D400,
            'lower'     => 0x1D41A,
            'digit'     => 0x1D7CE,
            'greek'     => 0x1D6A8,
            'overrides' => [],
        ],
        'italic' => [
            'upper'     => 0x1D434,
            'lower'     => 0x1D44E,
            'digit'     => null,
            'greek'     => 0x1D6E2,
            'overrides' => [
                0x0068 => 0x210E, // h -> PLANCK CONSTANT
            ],
        ],
        'bold-italic' => [
            'upper'     => 0x1D468,
            'lower'     => 0x1D482,
            'digit'     => null,
            'greek'     => 0x1D71C,
            'overrides' => [],
        ],
        'script' => [
            'upper'     => 0x1D49C,
            'lower'     => 0x1D4B6,
            'digit'     => null,
            'greek'     => null,
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
            'greek'     => null,
            'overrides' => [],
        ],
        'fraktur' => [
            'upper'     => 0x1D504,
            'lower'     => 0x1D51E,
            'digit'     => null,
            'greek'     => null,
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
            'greek'     => null,
            'overrides' => [],
        ],
        'double-struck' => [
            'upper'     => 0x1D538,
            'lower'     => 0x1D552,
            'digit'     => 0x1D7D8,
            'greek'     => null,
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
            'greek'     => null,
            'overrides' => [],
        ],
        'bold-sans-serif' => [
            'upper'     => 0x1D5D4,
            'lower'     => 0x1D5EE,
            'digit'     => 0x1D7EC,
            'greek'     => 0x1D756,
            'overrides' => [],
        ],
        'sans-serif-italic' => [
            'upper'     => 0x1D608,
            'lower'     => 0x1D622,
            'digit'     => null,
            'greek'     => null,
            'overrides' => [],
        ],
        'sans-serif-bold-italic' => [
            'upper'     => 0x1D63C,
            'lower'     => 0x1D656,
            'digit'     => null,
            'greek'     => 0x1D790,
            'overrides' => [],
        ],
        'monospace' => [
            'upper'     => 0x1D670,
            'lower'     => 0x1D68A,
            'digit'     => 0x1D7F6,
            'greek'     => null,
            'overrides' => [],
        ],
    ];

    /**
     * Position-within-Greek-block for each source codepoint that
     * has a mathematical Greek variant. Used together with each
     * variant's `'greek'` base offset to compute the target
     * codepoint. The block layout per Unicode is:
     *
     *   0-16  : caps Alpha through Rho (U+0391-U+03A1)
     *   17    : theta-symbol cap (U+03F4 ϴ)
     *   18-24 : caps Sigma through Omega (U+03A3-U+03A9)
     *   25    : NABLA (U+2207)
     *   26-42 : lower alpha through rho (U+03B1-U+03C1)
     *   43    : final-sigma (U+03C2)
     *   44-50 : lower sigma through omega (U+03C3-U+03C9)
     *   51    : PARTIAL DIFFERENTIAL (U+2202)
     *   52    : lunate epsilon (U+03F5)
     *   53    : theta symbol (U+03D1)
     *   54    : kappa symbol (U+03F0)
     *   55    : phi symbol (U+03D5)
     *   56    : rho symbol (U+03F1)
     *   57    : pi symbol (U+03D6)
     *
     * Capital reserved slot U+03A2 (would be capital final sigma)
     * has no mathematical variant; it's absent from this map.
     *
     * @var array<int, int>
     */
    private const array GREEK_OFFSETS = [
        // Capital Greek (U+0391-U+03A1)
        0x0391 => 0,   // Alpha
        0x0392 => 1,   // Beta
        0x0393 => 2,   // Gamma
        0x0394 => 3,   // Delta
        0x0395 => 4,   // Epsilon
        0x0396 => 5,   // Zeta
        0x0397 => 6,   // Eta
        0x0398 => 7,   // Theta
        0x0399 => 8,   // Iota
        0x039A => 9,   // Kappa
        0x039B => 10,  // Lambda
        0x039C => 11,  // Mu
        0x039D => 12,  // Nu
        0x039E => 13,  // Xi
        0x039F => 14,  // Omicron
        0x03A0 => 15,  // Pi
        0x03A1 => 16,  // Rho
        // Theta-symbol cap (U+03F4) inserted at slot 17.
        0x03F4 => 17,
        // Capital Greek continues U+03A3-U+03A9
        0x03A3 => 18,  // Sigma
        0x03A4 => 19,  // Tau
        0x03A5 => 20,  // Upsilon
        0x03A6 => 21,  // Phi
        0x03A7 => 22,  // Chi
        0x03A8 => 23,  // Psi
        0x03A9 => 24,  // Omega
        // NABLA at slot 25
        0x2207 => 25,
        // Lowercase Greek (U+03B1-U+03C9 with final sigma at 03C2)
        0x03B1 => 26,  // alpha
        0x03B2 => 27,  // beta
        0x03B3 => 28,  // gamma
        0x03B4 => 29,  // delta
        0x03B5 => 30,  // epsilon
        0x03B6 => 31,  // zeta
        0x03B7 => 32,  // eta
        0x03B8 => 33,  // theta
        0x03B9 => 34,  // iota
        0x03BA => 35,  // kappa
        0x03BB => 36,  // lambda
        0x03BC => 37,  // mu
        0x03BD => 38,  // nu
        0x03BE => 39,  // xi
        0x03BF => 40,  // omicron
        0x03C0 => 41,  // pi
        0x03C1 => 42,  // rho
        0x03C2 => 43,  // final sigma
        0x03C3 => 44,  // sigma
        0x03C4 => 45,  // tau
        0x03C5 => 46,  // upsilon
        0x03C6 => 47,  // phi
        0x03C7 => 48,  // chi
        0x03C8 => 49,  // psi
        0x03C9 => 50,  // omega
        // PARTIAL DIFFERENTIAL at slot 51
        0x2202 => 51,
        // Variant lowers
        0x03F5 => 52,  // lunate epsilon
        0x03D1 => 53,  // theta variant
        0x03F0 => 54,  // kappa variant
        0x03D5 => 55,  // phi variant
        0x03F1 => 56,  // rho variant
        0x03D6 => 57,  // pi variant
    ];
}
