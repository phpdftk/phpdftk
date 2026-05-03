<?php declare(strict_types=1);
namespace Phpdftk\Encoding;

/**
 * PDFDocEncoding — the encoding for PDF text strings (Info dict, bookmarks, annotations)
 * when they don't start with the UTF-16BE BOM (U+FEFF / 0xFE 0xFF).
 * Per PDF spec ISO 32000-2:2020, Table D.2.
 *
 * Maps byte values 0-255 directly to Unicode code points (not glyph names).
 */
final class PdfDocEncodingTable {
    /**
     * @return array<int, int|null> byte value (0-255) to Unicode code point, null = undefined
     */
    public static function getTable(): array {
        $table = [];

        // 0-7: special PDF control codes / undefined
        $table[0]   = null;     // undefined
        $table[1]   = null;     // undefined
        $table[2]   = null;     // undefined
        $table[3]   = null;     // undefined
        $table[4]   = null;     // undefined
        $table[5]   = null;     // undefined
        $table[6]   = null;     // undefined
        $table[7]   = null;     // undefined

        // 8-12: defined control codes
        $table[8]   = 0x0008;   // BACKSPACE
        $table[9]   = 0x0009;   // HORIZONTAL TAB
        $table[10]  = 0x000A;   // LINE FEED
        $table[11]  = 0x000B;   // VERTICAL TAB
        $table[12]  = 0x000C;   // FORM FEED
        $table[13]  = 0x000D;   // CARRIAGE RETURN

        // 14-15: undefined
        $table[14]  = null;
        $table[15]  = null;

        // 16-23: undefined
        for ($i = 16; $i <= 23; $i++) {
            $table[$i] = null;
        }

        // 24-31: special and undefined
        $table[24]  = 0x02D8;   // BREVE
        $table[25]  = 0x02C7;   // CARON
        $table[26]  = 0x02C6;   // MODIFIER LETTER CIRCUMFLEX ACCENT
        $table[27]  = 0x02D9;   // DOT ABOVE
        $table[28]  = 0x02DD;   // DOUBLE ACUTE ACCENT
        $table[29]  = 0x02DB;   // OGONEK
        $table[30]  = 0x02DA;   // RING ABOVE
        $table[31]  = 0x02DC;   // SMALL TILDE

        // 32-126: same as Unicode (ASCII)
        for ($i = 32; $i <= 126; $i++) {
            $table[$i] = $i;
        }

        // 127: undefined
        $table[127] = null;

        // 128-159: Windows-1252-like characters
        $table[128] = 0x2022;   // BULLET
        $table[129] = 0x2020;   // DAGGER
        $table[130] = 0x2021;   // DOUBLE DAGGER
        $table[131] = 0x2026;   // HORIZONTAL ELLIPSIS
        $table[132] = 0x2014;   // EM DASH
        $table[133] = 0x2013;   // EN DASH
        $table[134] = 0x0192;   // LATIN SMALL LETTER F WITH HOOK
        $table[135] = 0x2044;   // FRACTION SLASH
        $table[136] = 0x2039;   // SINGLE LEFT-POINTING ANGLE QUOTATION MARK
        $table[137] = 0x203A;   // SINGLE RIGHT-POINTING ANGLE QUOTATION MARK
        $table[138] = 0x2212;   // MINUS SIGN
        $table[139] = 0x2030;   // PER MILLE SIGN
        $table[140] = 0x201E;   // DOUBLE LOW-9 QUOTATION MARK
        $table[141] = 0x201C;   // LEFT DOUBLE QUOTATION MARK
        $table[142] = 0x201D;   // RIGHT DOUBLE QUOTATION MARK
        $table[143] = 0x2018;   // LEFT SINGLE QUOTATION MARK
        $table[144] = 0x2019;   // RIGHT SINGLE QUOTATION MARK
        $table[145] = 0x201A;   // SINGLE LOW-9 QUOTATION MARK
        $table[146] = 0x2122;   // TRADE MARK SIGN
        $table[147] = 0xFB01;   // LATIN SMALL LIGATURE FI
        $table[148] = 0xFB02;   // LATIN SMALL LIGATURE FL
        $table[149] = 0x0141;   // LATIN CAPITAL LETTER L WITH STROKE
        $table[150] = 0x0152;   // LATIN CAPITAL LIGATURE OE
        $table[151] = 0x0160;   // LATIN CAPITAL LETTER S WITH CARON
        $table[152] = 0x0178;   // LATIN CAPITAL LETTER Y WITH DIAERESIS
        $table[153] = 0x017D;   // LATIN CAPITAL LETTER Z WITH CARON
        $table[154] = 0x0131;   // LATIN SMALL LETTER DOTLESS I
        $table[155] = 0x0142;   // LATIN SMALL LETTER L WITH STROKE
        $table[156] = 0x0153;   // LATIN SMALL LIGATURE OE
        $table[157] = 0x0161;   // LATIN SMALL LETTER S WITH CARON
        $table[158] = 0x017E;   // LATIN SMALL LETTER Z WITH CARON
        $table[159] = null;     // undefined

        // 160: EURO SIGN (PDF 1.7+)
        $table[160] = 0x20AC;   // EURO SIGN

        // 161-255: same as Unicode/ISO 8859-1 (Latin-1)
        for ($i = 161; $i <= 255; $i++) {
            $table[$i] = $i;
        }

        // Override: 173 = SOFT HYPHEN in ISO 8859-1, same code point
        // (already correct from the loop above)

        return $table;
    }

    /**
     * Decode a PDFDocEncoding byte string to a UTF-8 string.
     *
     * PDF text strings use either PDFDocEncoding (single-byte) or UTF-16BE
     * (indicated by a BOM prefix 0xFE 0xFF). This method handles only the
     * PDFDocEncoding case.
     */
    public static function decode(string $bytes): string {
        $table = self::getTable();
        $result = '';
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $code = ord($bytes[$i]);
            $unicode = $table[$code];
            if ($unicode !== null) {
                $result .= mb_chr($unicode, 'UTF-8');
            }
            // Skip undefined code points
        }
        return $result;
    }

    /**
     * Decode a PDF text string — auto-detects UTF-16BE (BOM) vs PDFDocEncoding.
     */
    public static function decodeTextString(string $bytes): string {
        // Check for UTF-16BE BOM
        if (strlen($bytes) >= 2 && $bytes[0] === "\xFE" && $bytes[1] === "\xFF") {
            return mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE');
        }

        // Check for UTF-8 BOM (PDF 2.0)
        if (strlen($bytes) >= 3 && $bytes[0] === "\xEF" && $bytes[1] === "\xBB" && $bytes[2] === "\xBF") {
            return substr($bytes, 3);
        }

        return self::decode($bytes);
    }
}
