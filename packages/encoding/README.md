# phpdftk/encoding

WinAnsi and MacRoman encoding tables, the Adobe Glyph List, and a CMap parser. No PDF dependency — useful for any project that needs legacy 8-bit encoding or glyph name lookups.

## Installation

```bash
composer require phpdftk/encoding
```

## Usage

```php
use Phpdftk\Encoding\WinAnsiTable;
use Phpdftk\Encoding\MacRomanTable;
use Phpdftk\Encoding\GlyphList;
use Phpdftk\Encoding\CMapParser;

// WinAnsi / MacRoman — map code point (0–255) to Unicode code point
$unicode = WinAnsiTable::toUnicode(0xE9);    // 233 → U+00E9 (é)
$unicode = MacRomanTable::toUnicode(0xE9);   // 233 → U+00E9 (é)

// Adobe Glyph List — glyph name ↔ Unicode
$codePoint = GlyphList::glyphToUnicode('eacute');  // 0xE9
$glyph     = GlyphList::unicodeToGlyph(0xE9);      // 'eacute'

// CMap parser — parse ToUnicode CMaps from PDF streams
$cmap = CMapParser::parse($cmapStreamString);
// Returns array<int, string>: code point → Unicode character
```

## Classes

| Class | Description |
|---|---|
| `WinAnsiTable` | 256-entry Windows-1252 → Unicode mapping (`toUnicode(int): int`) |
| `MacRomanTable` | 256-entry Mac OS Roman → Unicode mapping (`toUnicode(int): int`) |
| `GlyphList` | ~200-entry Adobe Glyph List; `glyphToUnicode()`, `unicodeToGlyph()` |
| `CMapParser` | Parses PDF `ToUnicode` CMap stream text into a code-point-to-char array |
