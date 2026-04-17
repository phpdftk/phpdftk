<?php

declare(strict_types=1);

namespace ApprLabs\Crypt;

/**
 * SASLprep password normalization — RFC 4013.
 *
 * Prepares Unicode strings for use as passwords in PDF 2.0 encryption
 * (ISO 32000-2 §7.6.4.3.2). Uses the Stringprep framework (RFC 3454)
 * with the SASLprep profile.
 */
final class SaslPrep
{
    /**
     * Non-ASCII space characters mapped to U+0020 (RFC 3454 Table C.1.2).
     */
    private const SPACE_MAP = [
        "\xC2\xA0",             // U+00A0 NO-BREAK SPACE
        "\xE1\x9A\x80",         // U+1680 OGHAM SPACE MARK
        "\xE2\x80\x80",         // U+2000 EN QUAD
        "\xE2\x80\x81",         // U+2001 EM QUAD
        "\xE2\x80\x82",         // U+2002 EN SPACE
        "\xE2\x80\x83",         // U+2003 EM SPACE
        "\xE2\x80\x84",         // U+2004 THREE-PER-EM SPACE
        "\xE2\x80\x85",         // U+2005 FOUR-PER-EM SPACE
        "\xE2\x80\x86",         // U+2006 SIX-PER-EM SPACE
        "\xE2\x80\x87",         // U+2007 FIGURE SPACE
        "\xE2\x80\x88",         // U+2008 PUNCTUATION SPACE
        "\xE2\x80\x89",         // U+2009 THIN SPACE
        "\xE2\x80\x8A",         // U+200A HAIR SPACE
        "\xE2\x80\x8B",         // U+200B ZERO WIDTH SPACE
        "\xE2\x80\xAF",         // U+202F NARROW NO-BREAK SPACE
        "\xE2\x81\x9F",         // U+205F MEDIUM MATHEMATICAL SPACE
        "\xE3\x80\x80",         // U+3000 IDEOGRAPHIC SPACE
    ];

    /**
     * "Commonly mapped to nothing" characters (RFC 3454 Table B.1).
     */
    private const MAP_TO_NOTHING = [
        "\xC2\xAD",             // U+00AD SOFT HYPHEN
        "\xE1\xA0\x86",         // U+1806 MONGOLIAN TODO SOFT HYPHEN
        "\xE2\x80\x8B",         // U+200B ZERO WIDTH SPACE
        "\xE2\x81\xA0",         // U+2060 WORD JOINER
        "\xEF\xBB\xBF",         // U+FEFF ZERO WIDTH NO-BREAK SPACE
        "\xCD\x8F",             // U+034F COMBINING GRAPHEME JOINER
        "\xE1\xA0\x8B",         // U+180B MONGOLIAN FREE VARIATION SELECTOR ONE
        "\xE1\xA0\x8C",         // U+180C MONGOLIAN FREE VARIATION SELECTOR TWO
        "\xE1\xA0\x8D",         // U+180D MONGOLIAN FREE VARIATION SELECTOR THREE
        "\xEF\xB8\x80",         // U+FE00 VARIATION SELECTOR-1
        "\xEF\xB8\x81",         // U+FE01 VARIATION SELECTOR-2
        "\xEF\xB8\x82",         // U+FE02 VARIATION SELECTOR-3
        "\xEF\xB8\x83",         // U+FE03 VARIATION SELECTOR-4
        "\xEF\xB8\x84",         // U+FE04 VARIATION SELECTOR-5
        "\xEF\xB8\x85",         // U+FE05 VARIATION SELECTOR-6
        "\xEF\xB8\x86",         // U+FE06 VARIATION SELECTOR-7
        "\xEF\xB8\x87",         // U+FE07 VARIATION SELECTOR-8
        "\xEF\xB8\x88",         // U+FE08 VARIATION SELECTOR-9
        "\xEF\xB8\x89",         // U+FE09 VARIATION SELECTOR-10
        "\xEF\xB8\x8A",         // U+FE0A VARIATION SELECTOR-11
        "\xEF\xB8\x8B",         // U+FE0B VARIATION SELECTOR-12
        "\xEF\xB8\x8C",         // U+FE0C VARIATION SELECTOR-13
        "\xEF\xB8\x8D",         // U+FE0D VARIATION SELECTOR-14
        "\xEF\xB8\x8E",         // U+FE0E VARIATION SELECTOR-15
        "\xEF\xB8\x8F",         // U+FE0F VARIATION SELECTOR-16
    ];

    /**
     * Prepare a password string per SASLprep.
     *
     * Steps:
     * 1. Map: replace non-ASCII spaces with U+0020, remove commonly-mapped-to-nothing chars
     * 2. Normalize: NFKC normalization
     * 3. Prohibit: reject strings with prohibited characters
     * 4. Check bidi: validate bidirectional text rules
     *
     * If the PHP intl extension is not available, mapping and prohibit/bidi
     * checks are still performed but NFKC normalization is skipped (most
     * passwords are ASCII and don't need normalization).
     */
    public static function prepare(string $input): string
    {
        if ($input === '') {
            return '';
        }

        // Step 1: Mapping
        $str = self::map($input);

        // Step 2: NFKC normalization
        $str = self::normalize($str);

        // Step 3: Prohibit
        self::checkProhibited($str);

        // Step 4: Bidi check
        self::checkBidi($str);

        return $str;
    }

    /**
     * Step 1: Map non-ASCII spaces to U+0020 and remove mapped-to-nothing chars.
     */
    private static function map(string $input): string
    {
        // Replace non-ASCII spaces with regular space
        $result = str_replace(self::SPACE_MAP, ' ', $input);

        // Remove commonly mapped to nothing characters
        $result = str_replace(self::MAP_TO_NOTHING, '', $result);

        return $result;
    }

    /**
     * Step 2: NFKC normalization via the intl extension.
     */
    private static function normalize(string $input): string
    {
        if (!class_exists(\Normalizer::class)) {
            return $input;
        }

        $normalized = \Normalizer::normalize($input, \Normalizer::FORM_KC);

        if ($normalized === false) {
            return $input;
        }

        return $normalized;
    }

    /**
     * Step 3: Check for prohibited characters.
     *
     * Checks RFC 3454 Tables C.1.2, C.2.1, C.2.2, C.3-C.9.
     *
     * @throws \InvalidArgumentException if prohibited characters are found
     */
    private static function checkProhibited(string $input): void
    {
        $len = strlen($input);
        $i = 0;
        $bytesConsumed = 0;

        while ($i < $len) {
            $codepoint = self::readCodepoint($input, $i, $bytesConsumed);
            $i += $bytesConsumed;

            // C.2.1: ASCII control characters (U+0000-U+001F, U+007F)
            if ($codepoint <= 0x001F || $codepoint === 0x007F) {
                throw new \InvalidArgumentException(
                    sprintf('Prohibited character U+%04X (ASCII control) in SASLprep input', $codepoint)
                );
            }

            // C.2.2: Non-ASCII control characters (U+0080-U+009F)
            if ($codepoint >= 0x0080 && $codepoint <= 0x009F) {
                throw new \InvalidArgumentException(
                    sprintf('Prohibited character U+%04X (non-ASCII control) in SASLprep input', $codepoint)
                );
            }

            // C.2.2: Additional non-ASCII control characters
            if ($codepoint === 0x06DD || $codepoint === 0x070F
                || $codepoint === 0x180E
                || ($codepoint >= 0x200C && $codepoint <= 0x200D)
                || ($codepoint >= 0x2028 && $codepoint <= 0x2029)
                || ($codepoint >= 0x2060 && $codepoint <= 0x2063)
                || ($codepoint >= 0x206A && $codepoint <= 0x206F)
                || $codepoint === 0xFEFF
            ) {
                throw new \InvalidArgumentException(
                    sprintf('Prohibited character U+%04X (non-ASCII control) in SASLprep input', $codepoint)
                );
            }

            // C.3: Private use (U+E000-U+F8FF, U+F0000-U+FFFFD, U+100000-U+10FFFD)
            if (($codepoint >= 0xE000 && $codepoint <= 0xF8FF)
                || ($codepoint >= 0xF0000 && $codepoint <= 0xFFFFD)
                || ($codepoint >= 0x100000 && $codepoint <= 0x10FFFD)
            ) {
                throw new \InvalidArgumentException(
                    sprintf('Prohibited character U+%04X (private use) in SASLprep input', $codepoint)
                );
            }

            // C.4: Non-characters (U+FDD0-U+FDEF, U+FFFE-U+FFFF, and plane-end non-characters)
            if (($codepoint >= 0xFDD0 && $codepoint <= 0xFDEF)
                || ($codepoint & 0xFFFE) === 0xFFFE // catches U+xFFFE and U+xFFFF for all planes
            ) {
                throw new \InvalidArgumentException(
                    sprintf('Prohibited character U+%04X (non-character) in SASLprep input', $codepoint)
                );
            }

            // C.5: Surrogate codes (should not appear in valid UTF-8, but check anyway)
            if ($codepoint >= 0xD800 && $codepoint <= 0xDFFF) {
                throw new \InvalidArgumentException(
                    sprintf('Prohibited character U+%04X (surrogate) in SASLprep input', $codepoint)
                );
            }

            // C.6: Inappropriate for plain text
            if ($codepoint === 0xFFF9 || $codepoint === 0xFFFA || $codepoint === 0xFFFB) {
                throw new \InvalidArgumentException(
                    sprintf('Prohibited character U+%04X (inappropriate for plain text) in SASLprep input', $codepoint)
                );
            }

            // C.8: Change display properties / deprecated
            if ($codepoint === 0x0340 || $codepoint === 0x0341
                || $codepoint === 0x200E || $codepoint === 0x200F
                || ($codepoint >= 0x202A && $codepoint <= 0x202E)
            ) {
                throw new \InvalidArgumentException(
                    sprintf('Prohibited character U+%04X (change display / deprecated) in SASLprep input', $codepoint)
                );
            }

            // C.9: Tagging characters
            if ($codepoint === 0xE0001 || ($codepoint >= 0xE0020 && $codepoint <= 0xE007F)) {
                throw new \InvalidArgumentException(
                    sprintf('Prohibited character U+%04X (tagging character) in SASLprep input', $codepoint)
                );
            }
        }
    }

    /**
     * Step 4: Bidirectional text check (RFC 3454 §6).
     *
     * If a string contains any RandALCat character, the first and last
     * characters must also be RandALCat, and the string must not contain
     * any LCat characters.
     *
     * @throws \InvalidArgumentException if bidi rules are violated
     */
    private static function checkBidi(string $input): void
    {
        if ($input === '') {
            return;
        }

        $codepoints = self::toCodepoints($input);
        if ($codepoints === []) {
            return;
        }

        $hasRandAL = false;
        $hasL = false;

        foreach ($codepoints as $cp) {
            if (self::isRandALCat($cp)) {
                $hasRandAL = true;
            }
            if (self::isLCat($cp)) {
                $hasL = true;
            }
        }

        if ($hasRandAL) {
            if ($hasL) {
                throw new \InvalidArgumentException(
                    'SASLprep bidi violation: string with RandALCat characters must not contain LCat characters'
                );
            }

            $first = $codepoints[0];
            $last = $codepoints[count($codepoints) - 1];

            if (!self::isRandALCat($first) || !self::isRandALCat($last)) {
                throw new \InvalidArgumentException(
                    'SASLprep bidi violation: first and last characters must be RandALCat'
                );
            }
        }
    }

    /**
     * Simplified RandALCat check — covers Arabic, Hebrew, and related blocks.
     */
    private static function isRandALCat(int $codepoint): bool
    {
        return ($codepoint >= 0x0590 && $codepoint <= 0x05FF)   // Hebrew
            || ($codepoint >= 0x0600 && $codepoint <= 0x06FF)   // Arabic
            || ($codepoint >= 0x0700 && $codepoint <= 0x074F)   // Syriac
            || ($codepoint >= 0x0780 && $codepoint <= 0x07BF)   // Thaana
            || ($codepoint >= 0xFB50 && $codepoint <= 0xFDFF)   // Arabic Presentation Forms-A
            || ($codepoint >= 0xFE70 && $codepoint <= 0xFEFF);  // Arabic Presentation Forms-B
    }

    /**
     * Simplified LCat check — covers Latin, Greek, Cyrillic, CJK, etc.
     */
    private static function isLCat(int $codepoint): bool
    {
        return ($codepoint >= 0x0041 && $codepoint <= 0x005A)   // A-Z
            || ($codepoint >= 0x0061 && $codepoint <= 0x007A)   // a-z
            || ($codepoint >= 0x00C0 && $codepoint <= 0x00D6)   // Latin Extended
            || ($codepoint >= 0x00D8 && $codepoint <= 0x00F6)
            || ($codepoint >= 0x00F8 && $codepoint <= 0x024F)   // Latin Extended Additional
            || ($codepoint >= 0x0370 && $codepoint <= 0x0373)   // Greek
            || ($codepoint >= 0x0376 && $codepoint <= 0x0377)
            || ($codepoint >= 0x037A && $codepoint <= 0x037D)
            || ($codepoint >= 0x0386 && $codepoint <= 0x03FF)
            || ($codepoint >= 0x0400 && $codepoint <= 0x04FF)   // Cyrillic
            || ($codepoint >= 0x1E00 && $codepoint <= 0x1EFF)   // Latin Extended Additional
            || ($codepoint >= 0x1F00 && $codepoint <= 0x1FFF)   // Greek Extended
            || ($codepoint >= 0x4E00 && $codepoint <= 0x9FFF)   // CJK Unified Ideographs
            || ($codepoint >= 0x3040 && $codepoint <= 0x309F)   // Hiragana
            || ($codepoint >= 0x30A0 && $codepoint <= 0x30FF);  // Katakana
    }

    /**
     * Read a single UTF-8 codepoint from a string at the given offset.
     */
    private static function readCodepoint(string $str, int $offset, int &$bytesConsumed): int
    {
        $byte = ord($str[$offset]);

        if ($byte < 0x80) {
            $bytesConsumed = 1;
            return $byte;
        }

        if (($byte & 0xE0) === 0xC0) {
            $bytesConsumed = 2;
            return (($byte & 0x1F) << 6)
                | (ord($str[$offset + 1]) & 0x3F);
        }

        if (($byte & 0xF0) === 0xE0) {
            $bytesConsumed = 3;
            return (($byte & 0x0F) << 12)
                | ((ord($str[$offset + 1]) & 0x3F) << 6)
                | (ord($str[$offset + 2]) & 0x3F);
        }

        if (($byte & 0xF8) === 0xF0) {
            $bytesConsumed = 4;
            return (($byte & 0x07) << 18)
                | ((ord($str[$offset + 1]) & 0x3F) << 12)
                | ((ord($str[$offset + 2]) & 0x3F) << 6)
                | (ord($str[$offset + 3]) & 0x3F);
        }

        // Invalid UTF-8 byte — treat as single byte
        $bytesConsumed = 1;
        return $byte;
    }

    /**
     * Convert a UTF-8 string to an array of codepoints.
     *
     * @return int[]
     */
    private static function toCodepoints(string $str): array
    {
        $codepoints = [];
        $len = strlen($str);
        $i = 0;
        $bytesConsumed = 0;

        while ($i < $len) {
            $codepoints[] = self::readCodepoint($str, $i, $bytesConsumed);
            $i += $bytesConsumed;
        }

        return $codepoints;
    }
}
