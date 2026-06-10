<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\Encoding\WinAnsiEncoder;
use Phpdftk\Encoding\WinAnsiTable;
use Phpdftk\FontMetrics\AfmData;
use Phpdftk\FontMetrics\StandardFontMetrics;

/**
 * UTF-8 -> WinAnsi -> AFM-driven glyph-width measurement for the
 * two standard fonts the MathML painter uses (Times-Roman upright
 * and Times-Italic). Caches the AfmData per process so the widths
 * for the typical math run-time aren't re-derived from the embedded
 * tables on every glyph emission.
 *
 * Before this helper, the Translator used a fixed `0.5 em` advance
 * per character which left every layout calculation off by glyph-
 * specific amounts: `W` ate ~0.94 em, `i` ate ~0.28 em, and the
 * fixed estimate split the difference badly. With real widths the
 * cursor mechanics, fraction bar lengths, table column widths, and
 * vinculum widths all align with the actual ink the PDF will
 * render.
 *
 * Characters outside WinAnsi (most math operators - sums, integrals,
 * Greek letters above ASCII) fall back to the font's `missingWidth`
 * via the AFM .notdef glyph. The painter can't render those glyphs
 * in Type 1 standard fonts anyway - a real Math font will replace
 * this whole helper when it lands.
 */
final class MathmlGlyphMetrics
{
    /** PostScript name for the upright face the painter uses. */
    public const string UPRIGHT_FONT = 'Times-Roman';

    /** PostScript name for the italic face used on single-char `<mi>`. */
    public const string ITALIC_FONT = 'Times-Italic';

    private static ?AfmData $uprightCache = null;

    private static ?AfmData $italicCache = null;

    /** @var array<int, string>|null WinAnsi byte → AFM glyph name. */
    private static ?array $byteToGlyph = null;

    /**
     * Measure the rendered width of a UTF-8 string in PDF points,
     * given the current font size and whether the italic face is
     * active.
     *
     * Implementation: encode UTF-8 -> WinAnsi bytes, then sum the
     * per-glyph widths from the AFM. Non-WinAnsi characters are
     * encoded as `?` by the encoder and contribute the question
     * mark's width (close enough for layout; the renderer will
     * also display `?` for those characters until a Math font is
     * wired up).
     */
    public static function measure(string $utf8, float $fontSize, bool $italic = false): float
    {
        if ($utf8 === '' || $fontSize <= 0.0) {
            return 0.0;
        }
        $afm = $italic ? self::italic() : self::upright();
        $encoder = new WinAnsiEncoder();
        $bytes = $encoder->encode($utf8);
        // The AFM widths array is in 1/1000 em units.
        $units = 0;
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($bytes[$i]);
            $glyphName = self::glyphNameForWinAnsiByte($byte);
            $units += $afm->getWidth($glyphName);
        }
        return ($units / 1000.0) * $fontSize;
    }

    /**
     * Get the upright (Times-Roman) AfmData. Cached after first call.
     */
    public static function upright(): AfmData
    {
        return self::$uprightCache ??= StandardFontMetrics::get(self::UPRIGHT_FONT);
    }

    /**
     * Get the italic (Times-Italic) AfmData. Cached after first call.
     */
    public static function italic(): AfmData
    {
        return self::$italicCache ??= StandardFontMetrics::get(self::ITALIC_FONT);
    }

    private static function glyphNameForWinAnsiByte(int $byte): string
    {
        self::$byteToGlyph ??= WinAnsiTable::getTable();
        return self::$byteToGlyph[$byte] ?? '.notdef';
    }
}
