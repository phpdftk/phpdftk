<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

/**
 * Foundation for Unicode bidi handling in MathML token content.
 *
 * The MathML painter has historically rendered token text in source
 * order, which mishandles RTL scripts (Arabic, Hebrew) and any
 * mixed-direction token (e.g. an `<mi>` containing both Hebrew and
 * Latin letters). This class is the first step toward UAX #9
 * compliance: it classifies codepoints by bidi category, decides
 * the paragraph direction from the content, and reports whether a
 * string is purely directional or mixed.
 *
 * v1 scope:
 *
 *   - Per-codepoint bidi class via `IntlChar::charDirection()`
 *     (IntlChar is exposed by the `intl` extension, available in
 *     every supported PHP 8.4 build).
 *   - Strong-direction detection per UAX #9 P2 / P3 (first strong
 *     character wins).
 *   - "is it pure or mixed?" report so the painter knows whether
 *     it can take the cheap source-order path or needs full
 *     reordering (currently the latter degrades to source-order
 *     with a note; full UAX #9 reordering lands in a follow-up).
 *
 * Numbers are treated as their own direction (EN / AN) so a mostly-
 * LTR token with embedded Arabic numerals isn't misclassified as
 * mixed.
 */
final class BidiAnalyzer
{
    /** UAX #9 strong-LTR ("L"). */
    public const string DIRECTION_LTR = 'ltr';

    /** UAX #9 strong-RTL ("R" or "AL"). */
    public const string DIRECTION_RTL = 'rtl';

    /** No strong characters in the run (neutrals, numbers, whitespace). */
    public const string DIRECTION_NEUTRAL = 'neutral';

    /**
     * Strong direction of a single Unicode codepoint, or null when
     * the codepoint carries no strong direction (neutrals,
     * numbers, separators, marks).
     */
    public static function directionOf(int $codepoint): ?string
    {
        $class = \IntlChar::charDirection($codepoint);
        return match ($class) {
            // L / LRE / LRO -> LTR
            \IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT,
            \IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT_EMBEDDING,
            \IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT_OVERRIDE => self::DIRECTION_LTR,

            // R / AL / RLE / RLO -> RTL
            \IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT,
            \IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC,
            \IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_EMBEDDING,
            \IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_OVERRIDE => self::DIRECTION_RTL,

            default => null,
        };
    }

    /**
     * UAX #9 P2 / P3 paragraph direction: the direction of the
     * first strong character in the string. Returns the supplied
     * `$fallback` (default LTR) when no strong character is found.
     */
    public static function paragraphDirection(
        string $utf8,
        string $fallback = self::DIRECTION_LTR,
    ): string {
        foreach (mb_str_split($utf8, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp === false) {
                continue;
            }
            $dir = self::directionOf($cp);
            if ($dir !== null) {
                return $dir;
            }
        }
        return $fallback;
    }

    /**
     * Determine the run's overall direction:
     *
     *   - DIRECTION_LTR if every strong character is LTR (or no
     *     strong characters but numerics push it to LTR).
     *   - DIRECTION_RTL if every strong character is RTL.
     *   - DIRECTION_NEUTRAL if there are NO strong characters AND
     *     no numerics (pure whitespace / punctuation).
     *   - 'mixed' if both LTR and RTL strong characters appear.
     */
    public static function runDirection(string $utf8): string
    {
        $hasLtr = false;
        $hasRtl = false;
        $hasNumeric = false;
        foreach (mb_str_split($utf8, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp === false) {
                continue;
            }
            $dir = self::directionOf($cp);
            if ($dir === self::DIRECTION_LTR) {
                $hasLtr = true;
            } elseif ($dir === self::DIRECTION_RTL) {
                $hasRtl = true;
            } else {
                // Check for numerics so a pure-digit string reports
                // a non-neutral direction (numbers display LTR even
                // in RTL paragraphs per UAX #9).
                $class = \IntlChar::charDirection($cp);
                if (
                    $class === \IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER
                    || $class === \IntlChar::CHAR_DIRECTION_ARABIC_NUMBER
                ) {
                    $hasNumeric = true;
                }
            }
            if ($hasLtr && $hasRtl) {
                return 'mixed';
            }
        }
        if ($hasLtr) {
            return self::DIRECTION_LTR;
        }
        if ($hasRtl) {
            return self::DIRECTION_RTL;
        }
        if ($hasNumeric) {
            return self::DIRECTION_LTR;
        }
        return self::DIRECTION_NEUTRAL;
    }

    /**
     * True when the run mixes LTR and RTL strong characters - the
     * painter needs full UAX #9 reordering. Single-direction runs
     * or all-neutral runs can take the cheap source-order path.
     */
    public static function isMixed(string $utf8): bool
    {
        return self::runDirection($utf8) === 'mixed';
    }
}
