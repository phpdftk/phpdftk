<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

/**
 * Arabic contextual shaping via Unicode Arabic Presentation Forms-B.
 *
 * Arabic letters change form (isolated / initial / medial / final)
 * based on whether they join with their neighbours. Most modern
 * fonts ship OpenType GSUB lookups that apply this automatically -
 * but writing a GSUB engine for our painter is a substantial
 * project. Unicode also provides pre-shaped codepoints in the
 * "Arabic Presentation Forms-B" block (U+FE70-U+FEFF), which most
 * fonts also carry. The pragmatic v1 approach: classify each
 * character's joining type, walk the string with a two-codepoint
 * lookahead, and substitute the appropriate Presentation Form
 * codepoint based on the resolved context.
 *
 * Supported character set: U+0621-U+064A (the basic Arabic letters
 * + hamza). Diacritics (U+064B-U+065F), Tatweel (U+0640), and
 * non-Arabic content pass through unchanged.
 *
 * Limitations:
 *   - Doesn't apply LAM-ALEF ligatures (the special U+FEFB-U+FEFE
 *     codepoints) - those require lookahead beyond the joining
 *     algorithm and aren't strictly required for legibility.
 *   - Doesn't handle Persian or Urdu extensions (U+0671-U+06D5).
 *   - Doesn't apply OpenType GSUB on non-Presentation-Forms-B
 *     fonts (a font without these codepoints will render the
 *     basic letterforms in isolated form).
 */
final class ArabicShaper
{
    public const string JOIN_NONE        = 'U';  // Non-joining
    public const string JOIN_RIGHT       = 'R';  // Right-joining only
    public const string JOIN_DUAL        = 'D';  // Dual-joining
    public const string JOIN_CAUSING     = 'C';  // Like tatweel
    public const string JOIN_TRANSPARENT = 'T';  // Diacritics

    /**
     * Apply contextual shaping to a UTF-8 string. Returns the
     * string with each shapeable Arabic letter replaced by the
     * appropriate Presentation Forms-B codepoint. Non-Arabic
     * content and unshapeable Arabic content pass through.
     */
    public static function shape(string $utf8): string
    {
        $chars = mb_str_split($utf8, 1, 'UTF-8');
        $count = count($chars);
        $output = '';
        for ($i = 0; $i < $count; $i++) {
            $cp = mb_ord($chars[$i], 'UTF-8');
            if ($cp === false || !self::isShapeable($cp)) {
                $output .= $chars[$i];
                continue;
            }
            $joinsRight = self::joinsRight($chars, $i);
            $joinsLeft = self::joinsLeft($chars, $i);
            $form = self::resolveForm($joinsRight, $joinsLeft);
            $shaped = self::presentationFormOf($cp, $form);
            $output .= $shaped !== null
                ? mb_chr($shaped, 'UTF-8')
                : $chars[$i];
        }
        return $output;
    }

    /**
     * True when `$cp` is an Arabic letter that participates in the
     * contextual-form cascade (i.e. has entries in Presentation
     * Forms-B).
     */
    public static function isShapeable(int $cp): bool
    {
        return isset(self::TYPES[$cp])
            && self::TYPES[$cp] !== self::JOIN_TRANSPARENT
            && self::TYPES[$cp] !== self::JOIN_CAUSING;
    }

    /**
     * Joining type per the Unicode property table for the basic
     * Arabic block. Returns JOIN_NONE for characters outside the
     * supported range.
     */
    public static function joiningTypeOf(int $cp): string
    {
        return self::TYPES[$cp] ?? self::JOIN_NONE;
    }

    /**
     * Per the joining algorithm: the current character must accept
     * a join on its RIGHT side (type DUAL or RIGHT) AND the
     * preceding non-transparent character must accept a join on
     * its LEFT side (type DUAL or CAUSING; basic Arabic has no
     * LEFT-only letters).
     *
     * @param list<string> $chars
     */
    private static function joinsRight(array $chars, int $i): bool
    {
        $cp = mb_ord($chars[$i], 'UTF-8');
        if ($cp === false) {
            return false;
        }
        $myType = self::joiningTypeOf($cp);
        if ($myType !== self::JOIN_DUAL && $myType !== self::JOIN_RIGHT) {
            return false;
        }
        for ($j = $i - 1; $j >= 0; $j--) {
            $prevCp = mb_ord($chars[$j], 'UTF-8');
            if ($prevCp === false) {
                return false;
            }
            $prevType = self::joiningTypeOf($prevCp);
            if ($prevType === self::JOIN_TRANSPARENT) {
                continue;
            }
            return $prevType === self::JOIN_DUAL
                || $prevType === self::JOIN_CAUSING;
        }
        return false;
    }

    /**
     * Per the joining algorithm: the current character must accept
     * a join on its LEFT side (type DUAL; basic Arabic has no
     * LEFT-only letters) AND the next non-transparent character
     * must accept a join on its RIGHT side (DUAL or RIGHT or
     * CAUSING).
     *
     * @param list<string> $chars
     */
    private static function joinsLeft(array $chars, int $i): bool
    {
        $cp = mb_ord($chars[$i], 'UTF-8');
        if ($cp === false) {
            return false;
        }
        $myType = self::joiningTypeOf($cp);
        if ($myType !== self::JOIN_DUAL) {
            return false;
        }
        $count = count($chars);
        for ($j = $i + 1; $j < $count; $j++) {
            $nextCp = mb_ord($chars[$j], 'UTF-8');
            if ($nextCp === false) {
                return false;
            }
            $nextType = self::joiningTypeOf($nextCp);
            if ($nextType === self::JOIN_TRANSPARENT) {
                continue;
            }
            return $nextType === self::JOIN_DUAL
                || $nextType === self::JOIN_RIGHT
                || $nextType === self::JOIN_CAUSING;
        }
        return false;
    }

    private static function resolveForm(bool $joinsRight, bool $joinsLeft): string
    {
        if ($joinsRight && $joinsLeft) {
            return 'medial';
        }
        if ($joinsRight) {
            return 'final';
        }
        if ($joinsLeft) {
            return 'initial';
        }
        return 'isolated';
    }

    /**
     * Map a basic-Arabic codepoint to its Presentation Forms-B
     * codepoint for the requested form, or null when no mapping
     * exists.
     */
    private static function presentationFormOf(int $cp, string $form): ?int
    {
        $row = self::PRESENTATION_FORMS[$cp] ?? null;
        if ($row === null) {
            return null;
        }
        return $row[$form] ?? null;
    }

    /**
     * Joining-type table for U+0621-U+064A (basic Arabic letters)
     * plus U+0640 (Tatweel - join causing).
     *
     * @var array<int, string>
     */
    private const array TYPES = [
        0x0621 => self::JOIN_NONE,        // HAMZA
        0x0622 => self::JOIN_RIGHT,       // ALEF WITH MADDA ABOVE
        0x0623 => self::JOIN_RIGHT,       // ALEF WITH HAMZA ABOVE
        0x0624 => self::JOIN_RIGHT,       // WAW WITH HAMZA ABOVE
        0x0625 => self::JOIN_RIGHT,       // ALEF WITH HAMZA BELOW
        0x0626 => self::JOIN_DUAL,        // YEH WITH HAMZA ABOVE
        0x0627 => self::JOIN_RIGHT,       // ALEF
        0x0628 => self::JOIN_DUAL,        // BEH
        0x0629 => self::JOIN_RIGHT,       // TEH MARBUTA
        0x062A => self::JOIN_DUAL,        // TEH
        0x062B => self::JOIN_DUAL,        // THEH
        0x062C => self::JOIN_DUAL,        // JEEM
        0x062D => self::JOIN_DUAL,        // HAH
        0x062E => self::JOIN_DUAL,        // KHAH
        0x062F => self::JOIN_RIGHT,       // DAL
        0x0630 => self::JOIN_RIGHT,       // THAL
        0x0631 => self::JOIN_RIGHT,       // REH
        0x0632 => self::JOIN_RIGHT,       // ZAIN
        0x0633 => self::JOIN_DUAL,        // SEEN
        0x0634 => self::JOIN_DUAL,        // SHEEN
        0x0635 => self::JOIN_DUAL,        // SAD
        0x0636 => self::JOIN_DUAL,        // DAD
        0x0637 => self::JOIN_DUAL,        // TAH
        0x0638 => self::JOIN_DUAL,        // ZAH
        0x0639 => self::JOIN_DUAL,        // AIN
        0x063A => self::JOIN_DUAL,        // GHAIN
        0x0640 => self::JOIN_CAUSING,     // TATWEEL
        0x0641 => self::JOIN_DUAL,        // FEH
        0x0642 => self::JOIN_DUAL,        // QAF
        0x0643 => self::JOIN_DUAL,        // KAF
        0x0644 => self::JOIN_DUAL,        // LAM
        0x0645 => self::JOIN_DUAL,        // MEEM
        0x0646 => self::JOIN_DUAL,        // NOON
        0x0647 => self::JOIN_DUAL,        // HEH
        0x0648 => self::JOIN_RIGHT,       // WAW
        0x0649 => self::JOIN_DUAL,        // ALEF MAKSURA
        0x064A => self::JOIN_DUAL,        // YEH
        // Diacritics (U+064B-U+0652) are transparent.
        0x064B => self::JOIN_TRANSPARENT,
        0x064C => self::JOIN_TRANSPARENT,
        0x064D => self::JOIN_TRANSPARENT,
        0x064E => self::JOIN_TRANSPARENT,
        0x064F => self::JOIN_TRANSPARENT,
        0x0650 => self::JOIN_TRANSPARENT,
        0x0651 => self::JOIN_TRANSPARENT,
        0x0652 => self::JOIN_TRANSPARENT,
    ];

    /**
     * Mapping table for the basic Arabic letters to their
     * Presentation Forms-B codepoints. Each row carries up to four
     * keys: isolated / final / initial / medial. Letters that
     * only have a subset (e.g. ALEF doesn't have initial/medial
     * forms because it never joins on the left) omit those keys.
     *
     * @var array<int, array<string, int>>
     */
    private const array PRESENTATION_FORMS = [
        // HAMZA (U+0621): isolated only - U+FE80
        0x0621 => ['isolated' => 0xFE80],
        // ALEF WITH MADDA ABOVE (U+0622): isolated U+FE81, final U+FE82
        0x0622 => ['isolated' => 0xFE81, 'final' => 0xFE82],
        // ALEF WITH HAMZA ABOVE (U+0623)
        0x0623 => ['isolated' => 0xFE83, 'final' => 0xFE84],
        // WAW WITH HAMZA ABOVE (U+0624)
        0x0624 => ['isolated' => 0xFE85, 'final' => 0xFE86],
        // ALEF WITH HAMZA BELOW (U+0625)
        0x0625 => ['isolated' => 0xFE87, 'final' => 0xFE88],
        // YEH WITH HAMZA ABOVE (U+0626) - dual
        0x0626 => ['isolated' => 0xFE89, 'final' => 0xFE8A, 'initial' => 0xFE8B, 'medial' => 0xFE8C],
        // ALEF (U+0627) - right-joining
        0x0627 => ['isolated' => 0xFE8D, 'final' => 0xFE8E],
        // BEH (U+0628) - dual
        0x0628 => ['isolated' => 0xFE8F, 'final' => 0xFE90, 'initial' => 0xFE91, 'medial' => 0xFE92],
        // TEH MARBUTA (U+0629)
        0x0629 => ['isolated' => 0xFE93, 'final' => 0xFE94],
        // TEH (U+062A) - dual
        0x062A => ['isolated' => 0xFE95, 'final' => 0xFE96, 'initial' => 0xFE97, 'medial' => 0xFE98],
        // THEH (U+062B)
        0x062B => ['isolated' => 0xFE99, 'final' => 0xFE9A, 'initial' => 0xFE9B, 'medial' => 0xFE9C],
        // JEEM (U+062C)
        0x062C => ['isolated' => 0xFE9D, 'final' => 0xFE9E, 'initial' => 0xFE9F, 'medial' => 0xFEA0],
        // HAH (U+062D)
        0x062D => ['isolated' => 0xFEA1, 'final' => 0xFEA2, 'initial' => 0xFEA3, 'medial' => 0xFEA4],
        // KHAH (U+062E)
        0x062E => ['isolated' => 0xFEA5, 'final' => 0xFEA6, 'initial' => 0xFEA7, 'medial' => 0xFEA8],
        // DAL (U+062F)
        0x062F => ['isolated' => 0xFEA9, 'final' => 0xFEAA],
        // THAL (U+0630)
        0x0630 => ['isolated' => 0xFEAB, 'final' => 0xFEAC],
        // REH (U+0631)
        0x0631 => ['isolated' => 0xFEAD, 'final' => 0xFEAE],
        // ZAIN (U+0632)
        0x0632 => ['isolated' => 0xFEAF, 'final' => 0xFEB0],
        // SEEN (U+0633)
        0x0633 => ['isolated' => 0xFEB1, 'final' => 0xFEB2, 'initial' => 0xFEB3, 'medial' => 0xFEB4],
        // SHEEN (U+0634)
        0x0634 => ['isolated' => 0xFEB5, 'final' => 0xFEB6, 'initial' => 0xFEB7, 'medial' => 0xFEB8],
        // SAD (U+0635)
        0x0635 => ['isolated' => 0xFEB9, 'final' => 0xFEBA, 'initial' => 0xFEBB, 'medial' => 0xFEBC],
        // DAD (U+0636)
        0x0636 => ['isolated' => 0xFEBD, 'final' => 0xFEBE, 'initial' => 0xFEBF, 'medial' => 0xFEC0],
        // TAH (U+0637)
        0x0637 => ['isolated' => 0xFEC1, 'final' => 0xFEC2, 'initial' => 0xFEC3, 'medial' => 0xFEC4],
        // ZAH (U+0638)
        0x0638 => ['isolated' => 0xFEC5, 'final' => 0xFEC6, 'initial' => 0xFEC7, 'medial' => 0xFEC8],
        // AIN (U+0639)
        0x0639 => ['isolated' => 0xFEC9, 'final' => 0xFECA, 'initial' => 0xFECB, 'medial' => 0xFECC],
        // GHAIN (U+063A)
        0x063A => ['isolated' => 0xFECD, 'final' => 0xFECE, 'initial' => 0xFECF, 'medial' => 0xFED0],
        // FEH (U+0641)
        0x0641 => ['isolated' => 0xFED1, 'final' => 0xFED2, 'initial' => 0xFED3, 'medial' => 0xFED4],
        // QAF (U+0642)
        0x0642 => ['isolated' => 0xFED5, 'final' => 0xFED6, 'initial' => 0xFED7, 'medial' => 0xFED8],
        // KAF (U+0643)
        0x0643 => ['isolated' => 0xFED9, 'final' => 0xFEDA, 'initial' => 0xFEDB, 'medial' => 0xFEDC],
        // LAM (U+0644)
        0x0644 => ['isolated' => 0xFEDD, 'final' => 0xFEDE, 'initial' => 0xFEDF, 'medial' => 0xFEE0],
        // MEEM (U+0645)
        0x0645 => ['isolated' => 0xFEE1, 'final' => 0xFEE2, 'initial' => 0xFEE3, 'medial' => 0xFEE4],
        // NOON (U+0646)
        0x0646 => ['isolated' => 0xFEE5, 'final' => 0xFEE6, 'initial' => 0xFEE7, 'medial' => 0xFEE8],
        // HEH (U+0647)
        0x0647 => ['isolated' => 0xFEE9, 'final' => 0xFEEA, 'initial' => 0xFEEB, 'medial' => 0xFEEC],
        // WAW (U+0648)
        0x0648 => ['isolated' => 0xFEED, 'final' => 0xFEEE],
        // ALEF MAKSURA (U+0649)
        0x0649 => ['isolated' => 0xFEEF, 'final' => 0xFEF0, 'initial' => 0xFBE8, 'medial' => 0xFBE9],
        // YEH (U+064A)
        0x064A => ['isolated' => 0xFEF1, 'final' => 0xFEF2, 'initial' => 0xFEF3, 'medial' => 0xFEF4],
    ];
}
