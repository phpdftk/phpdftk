# phpdftk/font-metrics

AFM (Adobe Font Metrics) data for the 14 standard PDF fonts. No PDF dependency — useful anywhere you need glyph widths, bounding boxes, or typographic metrics without loading font files.

## Installation

```bash
composer require phpdftk/font-metrics
```

## Usage

```php
use Phpdftk\FontMetrics\StandardFontMetrics;

// Get metrics for a standard font by PostScript name
$metrics = StandardFontMetrics::get('Helvetica');
$metrics = StandardFontMetrics::get('Times-Roman');
$metrics = StandardFontMetrics::get('Courier-Bold');

// Typographic metrics
echo $metrics->ascender;      // e.g. 718
echo $metrics->descender;     // e.g. -207
echo $metrics->capHeight;     // e.g. 718
echo $metrics->xHeight;       // e.g. 523
echo $metrics->italicAngle;   // e.g. 0 (or negative for italic fonts)
echo $metrics->stemV;         // e.g. 88

// Bounding box [llx, lly, urx, ury]
[$llx, $lly, $urx, $ury] = $metrics->fontBBox;

// Per-glyph widths (WinAnsi code points 0–255, in 1/1000 em)
$width = $metrics->widths[65]; // width of 'A' in Helvetica
$width = $metrics->missingWidth; // fallback for unmapped glyphs

// Convenience: text width in points at a given size
$widthPts = $metrics->textWidth('Hello', fontSize: 12);
```

## Supported Fonts

All 14 standard PDF fonts (guaranteed available in every PDF viewer without embedding):

| Font | PostScript Name |
|---|---|
| Helvetica | `Helvetica` |
| Helvetica Bold | `Helvetica-Bold` |
| Helvetica Oblique | `Helvetica-Oblique` |
| Helvetica Bold Oblique | `Helvetica-BoldOblique` |
| Times Roman | `Times-Roman` |
| Times Bold | `Times-Bold` |
| Times Italic | `Times-Italic` |
| Times Bold Italic | `Times-BoldItalic` |
| Courier | `Courier` |
| Courier Bold | `Courier-Bold` |
| Courier Oblique | `Courier-Oblique` |
| Courier Bold Oblique | `Courier-BoldOblique` |
| Symbol | `Symbol` |
| Zapf Dingbats | `ZapfDingbats` |

## Classes

| Class | Description |
|---|---|
| `StandardFontMetrics` | Static `get(string): AfmData` — returns metrics for any of the 14 standard fonts |
| `AfmData` | Value object: `ascender`, `descender`, `capHeight`, `xHeight`, `italicAngle`, `stemV`, `missingWidth`, `fontBBox`, `widths[]` |
