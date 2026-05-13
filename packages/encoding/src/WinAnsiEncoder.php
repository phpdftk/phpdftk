<?php

declare(strict_types=1);

namespace Phpdftk\Encoding;

/**
 * Encodes UTF-8 strings to WinAnsi (single-byte, ISO 8859-1 + Microsoft
 * additions in 0x80–0x9F) for use with Type1 standard fonts and any other
 * font whose /Encoding is WinAnsiEncoding.
 */
final class WinAnsiEncoder implements TextEncoder
{
    /** @var array<int, int>|null Codepoint → WinAnsi byte. Built lazily once per process. */
    private static ?array $codepointToByte = null;

    /** @var list<int> */
    private array $missing = [];

    public function encode(string $utf8): string
    {
        $map = self::map();
        $out = '';
        // mb_str_split decodes UTF-8 grapheme-by-grapheme; we want codepoints,
        // and WinAnsi has no grapheme/codepoint distinction in its range,
        // so a per-codepoint split is correct.
        foreach (mb_str_split($utf8, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp === false) {
                $this->missing[] = -1;
                $out .= '?';
                continue;
            }
            $byte = $map[$cp] ?? null;
            if ($byte === null) {
                // Pass C0/C1 control characters through unchanged. PDF
                // content streams may contain literal whitespace such as
                // \n or \t, and the WinAnsi forward table flags those as
                // .notdef even though their byte values are identical in
                // UTF-8 and WinAnsi.
                if ($cp < 0x20 || ($cp >= 0x7F && $cp < 0xA0)) {
                    $out .= chr($cp);
                    continue;
                }
                $this->missing[] = $cp;
                $out .= '?';
                continue;
            }
            $out .= chr($byte);
        }
        return $out;
    }

    public function getMissingCodepoints(): array
    {
        return $this->missing;
    }

    /**
     * Build the reverse WinAnsi map (codepoint → byte) from the forward
     * byte → glyph-name table and the Adobe Glyph List.
     *
     * @return array<int, int>
     */
    private static function map(): array
    {
        if (self::$codepointToByte !== null) {
            return self::$codepointToByte;
        }

        $reverse = [];
        foreach (WinAnsiTable::getTable() as $byte => $glyph) {
            if ($glyph === '.notdef') {
                continue;
            }
            $cp = GlyphList::glyphToUnicode($glyph);
            if ($cp === null) {
                continue;
            }
            // First mapping wins — WinAnsi has 0xA0 and 0x20 both glyphed as
            // 'space', and we want 0x20 to be the canonical encoding of U+0020.
            if (!isset($reverse[$cp])) {
                $reverse[$cp] = $byte;
            }
        }

        // /Encoding /WinAnsiEncoding implies bytes 32-255 are mapped, with
        // /hyphen at both 0x2D and 0xAD per the spec. Keep the canonical
        // ASCII byte for U+002D.
        $reverse[0x2D] = 0x2D;
        // Soft hyphen U+00AD also maps to 0xAD.
        $reverse[0x00AD] = 0xAD;

        self::$codepointToByte = $reverse;
        return $reverse;
    }
}
