<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Text;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\Font;
use Phpdftk\Pdf\Writer\Page;
use Phpdftk\Pdf\Writer\PdfWriter;

/**
 * Map CSS Fonts 4 `font-family` / `font-weight` / `font-style` triples
 * onto one of the 14 standard PDF fonts (Helvetica, Times, Courier,
 * Symbol, ZapfDingbats). The resolver registers each picked
 * `StandardFont` lazily on first use, caches the resulting writer
 * `Font` handle, and reuses it for every following text element on the
 * same page.
 *
 * Scope at 3P:
 *
 *  - Family lookup is keyword-only (CSS Fonts 4 §3.2 generic families
 *    plus a handful of synonyms). `font-family: "Open Sans", "Helvetica
 *    Neue", sans-serif` resolves to Helvetica because the first
 *    keyword the resolver recognises wins.
 *  - Weight ≥ 600 promotes the variant to bold. Numeric weights are
 *    treated like the keyword equivalents (`bold`, `bolder`, …).
 *  - `font-style: italic | oblique` picks the oblique / italic
 *    variant; `font-style: normal` (or absent) stays upright.
 *  - Symbol and ZapfDingbats are intentionally out of scope — the
 *    resolver never picks them because mapping `font-family` to those
 *    isn't standardised and SVG content rarely targets them.
 *
 * Deferred (documented in plan + README):
 *
 *  - `@font-face` / embedded TrueType + OpenType fonts (would need
 *    the renderer adapter to plumb fonts into the resolver).
 *  - OpenType shaping via `phpdftk/text`.
 */
final class FontResolver
{
    /** @var array<string, Font> */
    private array $cache = [];

    public function __construct(
        private readonly PdfWriter $writer,
        private readonly Page $page,
    ) {}

    /**
     * @param list<string> $families  Ordered list from `font-family`.
     */
    public function resolve(array $families, ?string $weight, ?string $style): Font
    {
        $variant = $this->selectVariant($families, $weight, $style);
        return $this->ensureRegistered($variant);
    }

    /**
     * @param list<string> $families
     */
    private function selectVariant(array $families, ?string $weight, ?string $style): StandardFont
    {
        $generic = $this->pickGeneric($families);
        $bold = $weight !== null && self::isBoldWeight($weight);
        $italic = $style !== null && self::isItalicStyle($style);

        return match ($generic) {
            'serif' => match (true) {
                $bold && $italic => StandardFont::TimesBoldItalic,
                $bold => StandardFont::TimesBold,
                $italic => StandardFont::TimesItalic,
                default => StandardFont::TimesRoman,
            },
            'monospace' => match (true) {
                $bold && $italic => StandardFont::CourierBoldOblique,
                $bold => StandardFont::CourierBold,
                $italic => StandardFont::CourierOblique,
                default => StandardFont::Courier,
            },
            default => match (true) {
                $bold && $italic => StandardFont::HelveticaBoldOblique,
                $bold => StandardFont::HelveticaBold,
                $italic => StandardFont::HelveticaOblique,
                default => StandardFont::Helvetica,
            },
        };
    }

    /**
     * Walk the `font-family` list left-to-right, returning the first
     * generic family it recognises. Falls back to `sans-serif` so any
     * unknown stack ends up on Helvetica.
     *
     * @param list<string> $families
     * @return 'serif'|'sans-serif'|'monospace'
     */
    private function pickGeneric(array $families): string
    {
        foreach ($families as $family) {
            $key = strtolower(trim($family));
            $match = match (true) {
                $key === 'serif',
                str_contains($key, 'times'),
                str_contains($key, 'georgia'),
                str_contains($key, 'cambria'),
                str_contains($key, 'serif') && !str_contains($key, 'sans') => 'serif',
                $key === 'monospace',
                str_contains($key, 'courier'),
                str_contains($key, 'mono'),
                str_contains($key, 'consolas'),
                str_contains($key, 'menlo') => 'monospace',
                $key === 'sans-serif',
                str_contains($key, 'helvetica'),
                str_contains($key, 'arial'),
                str_contains($key, 'verdana'),
                str_contains($key, 'sans') => 'sans-serif',
                default => null,
            };
            if ($match !== null) {
                return $match;
            }
        }
        return 'sans-serif';
    }

    private static function isBoldWeight(string $weight): bool
    {
        $value = strtolower(trim($weight));
        if (is_numeric($value)) {
            return (float) $value >= 600.0;
        }
        return match ($value) {
            'bold', 'bolder' => true,
            default => false,
        };
    }

    private static function isItalicStyle(string $style): bool
    {
        return match (strtolower(trim($style))) {
            'italic', 'oblique' => true,
            default => false,
        };
    }

    private function ensureRegistered(StandardFont $variant): Font
    {
        $key = $variant->value;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $font = $this->writer->addFont(new Type1Font($variant), $this->page);
        return $this->cache[$key] = $font;
    }
}
