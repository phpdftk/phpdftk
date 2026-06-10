<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\Pdf\Writer\Font;

/**
 * Bundles everything the painter needs to render a glyph via the
 * math font's Type-0 / CIDFontType0 stack:
 *
 *   - The PDF font handle to install on the content stream.
 *   - A Unicode → post-subset GID map so `showText`-style emit can
 *     translate UTF-8 to the hex GID strings the Type-0 font wants.
 *   - Per-GID design-unit widths so the painter can advance the
 *     cursor by the real glyph advance, not an AFM approximation.
 *   - `unitsPerEm` so callers can convert design units → points.
 *
 * Constructed once per renderer.draw() call when a math font is
 * loaded; null otherwise.
 *
 * Width lookup falls back to {@see DEFAULT_WIDTH} for any GID that
 * isn't in the widths map (a font's notdef is rare but possible).
 */
final readonly class MathmlMathFont
{
    public const int DEFAULT_WIDTH = 500;

    /**
     * @param Font $font PDF font handle returned by PdfWriter::addOpenTypeFont().
     * @param array<int, int> $unicodeToGid Unicode codepoint → post-subset GID.
     * @param array<int, int> $glyphWidths GID → design-unit horizontal advance.
     * @param int $unitsPerEm Font's design units per em.
     */
    public function __construct(
        public Font $font,
        public array $unicodeToGid,
        public array $glyphWidths,
        public int $unitsPerEm,
    ) {}

    /**
     * Translate UTF-8 to hex-encoded post-subset GIDs the Type 0
     * font expects. Codepoints absent from the cmap are skipped -
     * the painter would render a tofu glyph anyway.
     */
    public function utf8ToHexGids(string $utf8): string
    {
        $hex = '';
        foreach (mb_str_split($utf8, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp === false) {
                continue;
            }
            $gid = $this->unicodeToGid[$cp] ?? null;
            if ($gid === null) {
                continue;
            }
            $hex .= sprintf('%04X', $gid);
        }
        return $hex;
    }

    /**
     * Compute the rendered width of a UTF-8 string in PDF points at
     * the given font size. Uses real per-GID hmtx widths so the
     * cursor mechanics line up with the ink.
     */
    public function measure(string $utf8, float $fontSize): float
    {
        if ($utf8 === '' || $fontSize <= 0.0) {
            return 0.0;
        }
        $units = 0;
        foreach (mb_str_split($utf8, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp === false) {
                continue;
            }
            $gid = $this->unicodeToGid[$cp] ?? null;
            if ($gid === null) {
                $units += self::DEFAULT_WIDTH;
                continue;
            }
            $units += $this->glyphWidths[$gid] ?? self::DEFAULT_WIDTH;
        }
        return ($units / (float) $this->unitsPerEm) * $fontSize;
    }
}
