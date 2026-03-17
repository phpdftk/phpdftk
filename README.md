# phpdftk

A PHP 8.4 monorepo for generating and manipulating PDF files. Every object in the PDF specification maps 1:1 to a PHP class, with each `/Field` from the spec mapping directly to a camelCase property.

Zero dependencies outside the standard library. Spec-compliant output. Fast and memory-efficient.

## Benchmarks

| Library | 1 page | 10 pages | 100 pages | Peak Memory (100 pg) |
|---|---|---|---|---|
| **phpdftk** | **12ms** | **17ms** | **49ms** | **4.4 MB** |
| FPDF | 9ms | 6ms | 9ms | 4.4 MB |
| TCPDF | 125ms | 150ms | 626ms | 12.4 MB |
| mPDF | 172ms | 387ms | 2.1s | 17.8 MB |
| Dompdf | 117ms | 249ms | 2.2s | 15.4 MB |

See [docs/benchmarks.md](docs/benchmarks.md) for full results.

## Quick Start

```bash
composer require phpdftk/phpdftk
```

```php
use Phpdftk\Writer\PdfWriter;
use Phpdftk\Font\Type1Font;
use Phpdftk\Font\StandardFont;

$writer = new PdfWriter();

$page = $writer->addPage(612, 792);           // letter size in points
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
$cs   = $writer->addContentStream($page);

$cs->beginText()
   ->setFont($font, 12)
   ->moveTextPosition(72, 720)
   ->showText('Hello, World!')
   ->endText();

$writer->save('/tmp/hello.pdf');
```

## Requirements

- PHP 8.4+
- `ext-zlib` (stream compression)
- `ext-openssl` (encryption)
- `ext-simplexml` (XMP metadata)

## Packages

This monorepo contains independently usable packages under `packages/`:

| Package | Description |
|---|---|
| [`phpdftk/phpdftk`](packages/phpdftk) | Core PDF library — document structure, content streams, fonts, annotations, forms, writer |
| [`phpdftk/geometry`](packages/geometry) | Point, Rectangle, Matrix, PageSize constants, BezierCurve |
| [`phpdftk/color`](packages/color) | RGB, CMYK, and Gray color models with conversion utilities |
| [`phpdftk/encoding`](packages/encoding) | WinAnsi/MacRoman tables, Adobe Glyph List, CMap parser |
| [`phpdftk/font-metrics`](packages/font-metrics) | AFM data for the 14 standard PDF fonts |
| [`phpdftk/filters`](packages/filters) | FlateDecode, ASCII85, ASCIIHex, and RunLength stream filters |
| [`phpdftk/image-metadata`](packages/image-metadata) | Header-only image parsing for JPEG, PNG, GIF, TIFF, WebP |
| [`phpdftk/xmp`](packages/xmp) | Read and write XMP metadata packets |
| [`phpdftk/crypt`](packages/crypt) | AES-128/256-CBC and RC4 with PDF key derivation (ISO 32000-2) |

All sub-packages have zero PDF dependencies and can be used standalone.

## Architecture

### Namespace Layout

| Namespace | Purpose |
|---|---|
| `Core\` | Primitive types: `PdfObject`, `PdfName`, `PdfString`, `PdfNumber`, `PdfBoolean`, `PdfNull`, `PdfArray`, `PdfDictionary`, `PdfStream`, `PdfReference` |
| `Document\` | `Catalog`, `PageTree`, `Page`, `Info`, `ViewerPreferences` |
| `Font\` | `Type1Font`, `TrueTypeFont`, `Type0Font`, `CIDFont`, `FontDescriptor`, `Encoding`, `StandardFont` enum |
| `Annotation\` | `TextAnnotation`, `LinkAnnotation`, `FreeTextAnnotation`, `HighlightAnnotation`, `StampAnnotation`, `InkAnnotation`, `PopupAnnotation`, `WidgetAnnotation` |
| `Graphics\` | `ExtGState`, `DeviceRGB`, `DeviceCMYK`, `DeviceGray`, `ImageXObject`, `FormXObject` |
| `Interactive\Form\` | `AcroForm`, `TextField`, `ButtonField`, `ChoiceField`, `SignatureField` |
| `Action\` | `GoToAction`, `URIAction`, `JavaScriptAction`, `NamedAction` |
| `Content\` | `ContentStream` (fluent operator API), `Resources` |
| `Writer\` | `PdfWriter`, `ObjectRegistry`, `CrossReferenceTable` |

### Content Streams

`ContentStream` provides a fluent API for all PDF content operators:

```php
// Graphics
$cs->saveGraphicsState()
   ->setLineWidth(2.0)
   ->setStrokeColorRGB(1.0, 0.0, 0.0)
   ->rectangle(0, 0, 200, 100)
   ->stroke()
   ->restoreGraphicsState();

// Text
$cs->beginText()
   ->setFont('F1', 14)
   ->setTextLeading(18)
   ->moveTextPosition(72, 680)
   ->showText('Line one')
   ->nextLine()
   ->showText('Line two')
   ->endText();
```

Operator groups: text, graphics state, paths, painting, color, XObjects, raw.

### Spec Compliance

- xref entries are exactly 20 bytes (`OOOOOOOOOO GGGGG n \r\n`)
- Object 0 is always the free-list head (`0000000000 65535 f \r\n`)
- Stream dictionaries include exact `/Length`
- Binary comment `%âãÏÓ` follows the header per §7.5.2
- `PdfName` hex-escapes special characters with `#XX`
- `PdfString` escapes `(`, `)`, `\`, `\n`, `\r`, `\t`

See [docs/spec-coverage.md](docs/spec-coverage.md) for a full ISO 32000-2 coverage audit.

## Development

```bash
# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run a single test
vendor/bin/phpunit tests/Document/SimpleTextTest.php

# Code coverage
./scripts/coverage

# Static analysis
vendor/bin/phpstan analyse

# Benchmarks
vendor/phpbench/phpbench/phpbench run --report=default
```

## License

MIT

