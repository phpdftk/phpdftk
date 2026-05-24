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
            default => (string) $value,
        };
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
