<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

/**
 * Centralised CSS Counter Styles 3 §6 formatters used by both list-marker
 * painting (`<ol type="i">`, `list-style-type: lower-roman`) and `@page`
 * `counter(page, <style>)` substitution. Phase-1 styles only — the spec's
 * algorithmic counter-style families (`@counter-style`) come later.
 */
final class CounterFormat
{
    /**
     * Format the 1-based ordinal `$value` per the named counter style.
     * Returns the raw decimal string when `$style` is unrecognised — same
     * fallback browsers apply for unknown `list-style-type` keywords.
     */
    public static function format(int $value, string $style): string
    {
        return match (strtolower($style)) {
            'decimal' => (string) $value,
            'decimal-leading-zero' => str_pad((string) $value, 2, '0', STR_PAD_LEFT),
            'lower-alpha', 'lower-latin' => self::toAlpha($value, lower: true),
            'upper-alpha', 'upper-latin' => self::toAlpha($value, lower: false),
            'lower-roman' => strtolower(self::toRoman($value)),
            'upper-roman' => self::toRoman($value),
            'lower-greek' => self::toGreek($value),
            'cjk-decimal' => self::toCjkDecimal($value),
            // CSS Counter Styles 3 §7.2 — disclosure triangles for
            // `<summary>::marker`. The value is ignored (these are
            // fixed-symbol systems).
            'disclosure-open' => "\u{25BC}",
            'disclosure-closed' => "\u{25B6}",
            default => (string) $value,
        };
    }

    /**
     * CSS Counter Styles 3 §7.1.5 `cjk-decimal` — the digital style
     * using Chinese ideographic digits 〇 一 二 三 四 五 六 七 八 九.
     * Multi-digit numbers concatenate (e.g. 23 → 二三). Negative
     * values fall back to the decimal string per the spec's
     * fixed-system fallback.
     */
    public static function toCjkDecimal(int $n): string
    {
        if ($n < 0) {
            return (string) $n;
        }
        $digits = [
            '0' => "\u{3007}",
            '1' => "\u{4E00}",
            '2' => "\u{4E8C}",
            '3' => "\u{4E09}",
            '4' => "\u{56DB}",
            '5' => "\u{4E94}",
            '6' => "\u{516D}",
            '7' => "\u{4E03}",
            '8' => "\u{516B}",
            '9' => "\u{4E5D}",
        ];
        $out = '';
        foreach (str_split((string) $n) as $d) {
            $out .= $digits[$d];
        }
        return $out;
    }

    /**
     * CSS Counter Styles 3 §7.1.4 `lower-greek` — alphabetic over the
     * 24-letter Greek lowercase set α β γ δ ε ζ η θ ι κ λ μ ν ξ ο π
     * ρ σ τ υ φ χ ψ ω (note: σ, not the final-sigma ς, per spec).
     */
    public static function toGreek(int $n): string
    {
        if ($n < 1) {
            return (string) $n;
        }
        // 24 lowercase Greek letters — Unicode U+03B1..U+03C9 skipping
        // U+03C2 (final sigma).
        static $letters = [
            "α", "β", "γ", "δ", "ε", "ζ", "η", "θ",
            "ι", "κ", "λ", "μ", "ν", "ξ", "ο", "π",
            "ρ", "σ", "τ", "υ", "φ", "χ", "ψ", "ω",
        ];
        $base = count($letters);
        $out = '';
        while ($n > 0) {
            $n--;
            $out = $letters[$n % $base] . $out;
            $n = intdiv($n, $base);
        }
        return $out;
    }

    /**
     * Bijective base-26: 1→"a", 26→"z", 27→"aa", … (or upper-case
     * when `$lower` is false).
     */
    public static function toAlpha(int $n, bool $lower): string
    {
        if ($n < 1) {
            return (string) $n;
        }
        $base = $lower ? ord('a') : ord('A');
        $out = '';
        while ($n > 0) {
            $n--;
            $out = chr($base + ($n % 26)) . $out;
            $n = intdiv($n, 26);
        }
        return $out;
    }

    /** Standard subtractive Roman-numeral formatting for 1-3999. */
    public static function toRoman(int $n): string
    {
        if ($n < 1 || $n > 3999) {
            return (string) $n;
        }
        static $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];
        $out = '';
        foreach ($map as $value => $symbol) {
            while ($n >= $value) {
                $out .= $symbol;
                $n -= $value;
            }
        }
        return $out;
    }
}
