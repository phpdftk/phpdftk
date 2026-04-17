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
# Install the whole family (object model + builder)
composer require apprlabs/pdf

# Or cherry-pick — just the builder pulls the object model transitively
composer require apprlabs/pdf-writer
```

`apprlabs/pdf` is a metapackage that depends on `apprlabs/pdf-core` and
`apprlabs/pdf-writer` (and, once it lands, `apprlabs/pdf-reader`). Use
it when you want one install command for the whole toolkit, or pick
individual sub-packages for fine-grained use.

### High-level builder — no PDF knowledge required

```php
use ApprLabs\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->addHeading('Hello, World', 1);
$pdf->addText('This is a paragraph of body text. Long lines wrap automatically '
    . 'and long documents auto-paginate onto new pages.');
$pdf->addSpacer(12);
$pdf->addImage('/path/to/photo.jpg', width: 300);

// Three output modes
$pdf->save('/tmp/hello.pdf');     // write to a file
$bytes = $pdf->toBytes();         // get bytes as a string
$pdf->writeTo(STDOUT);            // pipe to any fwrite-compatible stream
```

The `Pdf` class is a stateful, cursor-driven builder that hides the PDF
object model entirely. Text flows down the page, wraps at the content
column, and continues onto new pages when it overflows. Supports
headings (H1–H6), body paragraphs, alignment (left/center/right),
horizontal rules, spacers, images with auto-scaling, and the 14
standard PDF fonts (Helvetica / Times / Courier families plus Symbol
and ZapfDingbats). Themes control document-wide defaults; `TextStyle`
overrides individual calls.

### Low-level builder — full object-model control

If you need custom TrueType fonts, precise graphics state, content
streams, or any other spec-level feature, drop to the fluent
`PdfWriter` facade:

```php
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Font\StandardFont;

$writer = new PdfWriter();
$page = $writer->addPage(612, 792);
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
$cs   = $writer->addContentStream($page);
$cs->beginText()
   ->setFont($font, 12)
   ->moveTextPosition(72, 720)
   ->showText('Hello, World!')
   ->endText();
$writer->save('/tmp/hello.pdf');
```

`Pdf::writer()` returns the underlying `PdfWriter` so you can mix both
layers in a single document.

## Requirements

- PHP 8.4+
- `ext-zlib` (stream compression)
- `ext-openssl` (encryption)
- `ext-simplexml` (XMP metadata)

## Packages

This monorepo contains independently usable packages under `packages/`:

| Package | Description |
|---|---|
| [`apprlabs/pdf`](packages/pdf/all) | **Metapackage** — one install for the whole family (currently `pdf-core` + `pdf-writer`; `pdf-reader` added when implemented) |
| [`apprlabs/pdf-core`](packages/pdf/core) | PDF object model **and** byte-level file serialization — document structure, content streams, fonts, annotations, forms, plus `File\PdfFileWriter` which emits complete `%PDF` bytes |
| [`apprlabs/pdf-writer`](packages/pdf/writer) | Ergonomic document builder — `PdfWriter` facade over `Core\File\PdfFileWriter` with `addPage` / `addFont` / `setOutline` / `setSigner` convenience methods |
| [`apprlabs/pdf-reader`](packages/pdf/reader) | Parses existing PDFs into the object model (not yet implemented) |
| [`apprlabs/geometry`](packages/geometry) | Point, Rectangle, Matrix, PageSize constants, BezierCurve |
| [`apprlabs/color`](packages/color) | RGB, CMYK, and Gray color models with conversion utilities |
| [`apprlabs/encoding`](packages/encoding) | WinAnsi/MacRoman tables, Adobe Glyph List, CMap parser |
| [`apprlabs/font-metrics`](packages/font-metrics) | AFM data for the 14 standard PDF fonts |
| [`apprlabs/filters`](packages/filters) | FlateDecode, ASCII85, ASCIIHex, and RunLength stream filters |
| [`apprlabs/image-metadata`](packages/image-metadata) | Header-only image parsing for JPEG, PNG, GIF, TIFF, WebP |
| [`apprlabs/xmp`](packages/xmp) | Read and write XMP metadata packets |
| [`apprlabs/crypt`](packages/crypt) | AES-128/256-CBC and RC4 with PDF key derivation (ISO 32000-2) |

All sub-packages have zero PDF dependencies and can be used standalone.

## Architecture

### Namespace Layout

| Namespace | Purpose |
|---|---|
| `ApprLabs\Pdf\Core\` | Primitive types: `PdfObject`, `PdfName`, `PdfString`, `PdfNumber`, `PdfBoolean`, `PdfNull`, `PdfArray`, `PdfDictionary`, `PdfStream`, `PdfReference` |
| `ApprLabs\Pdf\Core\Document\` | `Catalog`, `PageTree`, `Page`, `Info`, `ViewerPreferences`, `Outline`, `OutlineItem`, `PageLabel`, `TransitionDict` |
| `ApprLabs\Pdf\Core\Font\` | `Type1Font`, `TrueTypeFont`, `Type0Font`, `CIDFont`, `FontDescriptor`, `Encoding`, `StandardFont` enum |
| `ApprLabs\Pdf\Core\Annotation\` | `TextAnnotation`, `LinkAnnotation`, `FreeTextAnnotation`, `HighlightAnnotation`, `StampAnnotation`, `InkAnnotation`, `PopupAnnotation`, `WidgetAnnotation` |
| `ApprLabs\Pdf\Core\Graphics\` | `ExtGState`, `DeviceRGB`, `DeviceCMYK`, `DeviceGray`, `ImageXObject`, `FormXObject` |
| `ApprLabs\Pdf\Core\Interactive\Form\` | `AcroForm`, `TextField`, `ButtonField`, `ChoiceField`, `SignatureField` |
| `ApprLabs\Pdf\Core\Action\` | `GoToAction`, `GoToRAction`, `URIAction`, `JavaScriptAction`, `NamedAction` |
| `ApprLabs\Pdf\Core\Content\` | `ContentStream` (fluent operator API), `Resources` |
| `ApprLabs\Pdf\Writer\` | `PdfWriter`, `ObjectRegistry`, `CrossReferenceTable` |

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
vendor/bin/phpunit packages/pdf/core/tests/Document/SimpleTextTest.php

# Code coverage
./scripts/coverage

# Static analysis
vendor/bin/phpstan analyse

# Benchmarks
vendor/phpbench/phpbench/phpbench run --report=default
```

## License

MIT

