# apprlabs/font-parser

Parse TrueType (.ttf), OpenType CFF (.otf), and WOFF (.woff) fonts to extract metrics, glyph widths, character maps, kerning pairs, and ligatures. Includes subsetting for PDF embedding. No external dependencies.

## Installation

```bash
composer require apprlabs/font-parser
```

## Usage

### TrueType

```php
use ApprLabs\FontParser\TrueTypeParser;

$parser = new TrueTypeParser('/path/to/font.ttf');
$data = $parser->parse();

echo $data->familyName;     // "Arial"
echo $data->postScriptName; // "ArialMT"
echo $data->ascent;         // 905
echo $data->descent;        // -212
```

### OpenType CFF

```php
use ApprLabs\FontParser\OpenTypeParser;

$parser = new OpenTypeParser('/path/to/font.otf');
$data = $parser->parse();

$data->cffBytes; // Raw CFF data for PDF embedding
```

### Subsetting

```php
use ApprLabs\FontParser\TrueTypeSubsetter;

$subset = TrueTypeSubsetter::subset($data, [65, 66, 67]); // Keep only A, B, C glyphs
```

## Classes

| Class | Description |
|---|---|
| `TrueTypeParser` | Parse .ttf files: metrics, widths, cmap, kerning, ligatures |
| `OpenTypeParser` | Parse .otf (CFF) files: same metrics plus CFF outline data |
| `CffParser` | Parse raw CFF data from OpenType fonts |
| `TrueTypeSubsetter` | Subset TrueType fonts to reduce file size |
| `CffSubsetter` | Subset CFF fonts |
| `KerningParser` | Parse GPOS PairPos and legacy kern tables |
| `GsubParser` | Parse GSUB ligature substitution tables |
| `TextShaper` | Apply kerning and ligatures to text |
| `WoffParser` | Decompress WOFF 1.0 to TrueType |

## Documentation

Full documentation at [apprlabs.github.io/phpdftk](https://apprlabs.github.io/phpdftk/).
