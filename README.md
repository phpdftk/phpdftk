# phpdftk

[![CI](https://github.com/phpdftk/phpdftk/actions/workflows/ci.yml/badge.svg)](https://github.com/phpdftk/phpdftk/actions/workflows/ci.yml)
[![Coverage](https://raw.githubusercontent.com/phpdftk/phpdftk/_coverage/latest/coverage-badge.svg)](https://phpdftk.dev/coverage/)
[![PHP 8.4+](https://img.shields.io/badge/php-8.4%2B-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A PHP 8.4 monorepo for generating and manipulating PDF files. Every object in the PDF specification maps 1:1 to a PHP class, with each `/Field` from the spec mapping directly to a camelCase property.

Zero dependencies outside the standard library. Spec-compliant output. Fast and memory-efficient.

## Benchmarks

| Library | 1 page | 10 pages | 100 pages | Peak Memory (100 pg) |
|---|---|---|---|---|
| **phpdftk** | **0.9 ms** | **1.2 ms** | **3.5 ms** | **7.4 MB** |
| FPDF | 0.4 ms | 0.5 ms | 1.2 ms | 5.3 MB |
| TCPDF | 5.4 ms | 6.3 ms | 16.2 ms | 13.0 MB |
| mPDF | 12.4 ms | 16.4 ms | 54.4 ms | 18.6 MB |
| Dompdf | 5.4 ms | 10.4 ms | 88.0 ms | 16.1 MB |

See the [Benchmarks page](https://phpdftk.dev/standards/performance/benchmarks/) for full results.

## Quick Start

```bash
# Install the whole family (object model + builder)
composer require phpdftk/pdf

# Or cherry-pick — just the builder pulls the object model transitively
composer require phpdftk/pdf-writer
```

`phpdftk/pdf` is a metapackage that depends on `phpdftk/pdf-core`,
`phpdftk/pdf-writer`, and `phpdftk/pdf-reader`. Use it when you want
one install command for the whole toolkit, or pick individual
sub-packages for fine-grained use.

### High-level builder — no PDF knowledge required

```php
use Phpdftk\Pdf\Writer\Pdf;

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
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Font\StandardFont;

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

### Rendering HTML / CSS / SVG / MathML to PDF

Beyond the PDF builder, phpdftk ships a full HTML/CSS/SVG/MathML rendering pipeline. WPT in-scope pass rates:

| Corpus | In-scope tests | Pass rate |
|---|---:|---:|
| **mathml/** | 167 | **100.00%** |
| **svg/** | 174 | **95.40%** |
| **html/** | 584 | **95.72%** |
| **css/** | 21 270 | **65.03%** |

```bash
composer require phpdftk/html-to-pdf
```

```php
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
(new Renderer())->renderInto($writer, <<<'HTML'
<!DOCTYPE html>
<style>
  body { font: 12pt serif; max-width: 6in; margin: 1in auto }
  h1 { color: navy; border-bottom: 2px solid navy }
  .badge { display: inline-block; padding: 2px 8px;
           background: gold; border-radius: 4px; font-size: 9pt }
</style>
<body>
  <h1>Quarterly Report <span class="badge">DRAFT</span></h1>
  <p>Revenue grew <strong>14.2%</strong> year-over-year.</p>
  <svg width="200" height="60" viewBox="0 0 200 60">
    <rect width="180" height="40" x="10" y="10" fill="#4a90e2" rx="5"/>
    <text x="100" y="35" text-anchor="middle" fill="white"
          font-family="sans-serif" font-size="14">Inline SVG works</text>
  </svg>
</body>
HTML);
$writer->save('/tmp/report.pdf');
```

Full feature matrix and per-module pass rates at [phpdftk.dev/rendering/overview](https://phpdftk.dev/rendering/overview/). The pipeline targets CSS print-stylesheet parity, not interactive parity: JavaScript never runs, animations pin to `t = 0`, and the static PDF output is byte-for-byte deterministic for the same input.

## Requirements

- PHP 8.4+
- `ext-zlib` (stream compression)
- `ext-openssl` (encryption)
- `ext-simplexml` (XMP metadata)
- `ext-mbstring` and `ext-intl` (text shaping, only when rendering HTML/SVG/MathML)

## Packages

This monorepo contains independently usable packages under `packages/`:

| Package | Description |
|---|---|
| [`phpdftk/pdf`](packages/pdf/all) | **Metapackage** — one install for the whole family (`pdf-core` + `pdf-writer` + `pdf-reader`) |
| [`phpdftk/pdf-core`](packages/pdf/core) | PDF object model **and** byte-level file serialization — document structure, content streams, fonts, annotations, forms, plus `File\PdfFileWriter` which emits complete `%PDF` bytes |
| [`phpdftk/pdf-writer`](packages/pdf/writer) | Ergonomic document builder — `PdfWriter` facade over `Core\File\PdfFileWriter` with `addPage` / `addFont` / `setOutline` / `setSigner` convenience methods |
| [`phpdftk/pdf-reader`](packages/pdf/reader) | Parses existing PDFs into typed objects, extracts text, inspects structure |
| [`phpdftk/pdf-toolkit`](packages/pdf/toolkit) | High-level pipelines — form filling, page slicing/merging, stamping, text extraction, redaction, encryption, bookmarks, page labels, metadata editing, annotation flattening, LTV signing |
| [`phpdftk/pdf-conformance`](packages/pdf/conformance) | ISO standard validation — PDF/A, PDF/X, PDF/UA, PDF/VT, PDF/E, PDF/R, ZUGFeRD/Factur-X, PDF/mail (8 standards, 31 levels) |
| [`phpdftk/geometry`](packages/geometry) | Point, Rectangle, Matrix, PageSize constants, BezierCurve |
| [`phpdftk/color`](packages/color) | RGB, CMYK, and Gray color models with conversion utilities |
| [`phpdftk/encoding`](packages/encoding) | WinAnsi/MacRoman tables, Adobe Glyph List, CMap parser |
| [`phpdftk/filesystem`](packages/filesystem) | Local filesystem utilities shared by phpdftk packages |
| [`phpdftk/font-metrics`](packages/font-metrics) | AFM data for the 14 standard PDF fonts |
| [`phpdftk/font-parser`](packages/font-parser) | TrueType/OpenType/CFF/Type1/WOFF/WOFF2 parsing, subsetting, kerning, ligatures, variable fonts |
| [`phpdftk/filters`](packages/filters) | FlateDecode, ASCII85, ASCIIHex, RunLength, LZW, CCITTFax, JBIG2, Predictor stream filters |
| [`phpdftk/image-metadata`](packages/image-metadata) | Header-only image parsing for JPEG, PNG, GIF, TIFF, WebP with ICC profile extraction |
| [`phpdftk/xmp`](packages/xmp) | Read and write XMP metadata packets |
| [`phpdftk/crypt`](packages/crypt) | AES-128/256-CBC and RC4 with PDF key derivation, PKCS#7 public-key envelopes (ISO 32000-2) |
| [`phpdftk/xml`](packages/xml) | XML parser shared by `svg`, `mathml`, and `xmp` |
| [`phpdftk/html`](packages/html) | WHATWG HTML5 parser + DOM + declarative Shadow DOM (100% `html5lib-tests` tree-construction) |
| [`phpdftk/css`](packages/css) | CSS Syntax 3 + Values 4 + Selectors 4 + Cascade 5 |
| [`phpdftk/svg`](packages/svg) | SVG 2 parser producing a typed tree |
| [`phpdftk/mathml`](packages/mathml) | MathML Core parser producing a typed tree |
| [`phpdftk/text`](packages/text) | UAX #14 line breaking, UAX #9 bidi, OpenType GSUB/GPOS shaping |
| [`phpdftk/html-to-pdf`](packages/html-to-pdf) | HTML + CSS → PDF renderer (95.72% WPT in-scope) |
| [`phpdftk/svg-to-pdf`](packages/svg-to-pdf) | SVG → PDF renderer (95.40% WPT in-scope) |
| [`phpdftk/mathml-to-pdf`](packages/mathml-to-pdf) | MathML → PDF renderer (100% WPT in-scope) |
| [`phpdftk/paged-media`](packages/paged-media) | CSS Paged Media 3 + Fragmentation 4 substrate |
| [`phpdftk/raster`](packages/raster) | Raster compositor for filter primitives, blur halos (Phase 4C) |
| [`phpdftk/resource-loader`](packages/resource-loader) | HTTP + file fetcher with SSRF gates and MIME sniffing |
| [`phpdftk/barcode`](packages/barcode) | Barcode and QR-code rendering for `<img>` / CSS background-image |
| [`phpdftk/wpt-harness`](packages/wpt-harness) | Web Platform Tests runner, manifest, and cross-browser oracle |

All support packages have zero PDF dependencies and can be used standalone. Rendering packages depend on the support packages but never on `pdf-reader` or `pdf-toolkit`.

## Architecture

### Namespace Layout

| Namespace | Purpose |
|---|---|
| `Phpdftk\Pdf\Core\` | Primitive types: `PdfObject`, `PdfName`, `PdfString`, `PdfNumber`, `PdfBoolean`, `PdfNull`, `PdfArray`, `PdfDictionary`, `PdfStream`, `PdfReference` |
| `Phpdftk\Pdf\Core\Document\` | `Catalog`, `PageTree`, `Page`, `Info`, `ViewerPreferences`, `Outline`, `OutlineItem`, `PageLabel`, `TransitionDict` |
| `Phpdftk\Pdf\Core\Font\` | `Type1Font`, `TrueTypeFont`, `Type0Font`, `CIDFont`, `FontDescriptor`, `Encoding`, `StandardFont` enum |
| `Phpdftk\Pdf\Core\Annotation\` | `TextAnnotation`, `LinkAnnotation`, `FreeTextAnnotation`, `HighlightAnnotation`, `StampAnnotation`, `InkAnnotation`, `PopupAnnotation`, `WidgetAnnotation` |
| `Phpdftk\Pdf\Core\Graphics\` | `ExtGState`, `DeviceRGB`, `DeviceCMYK`, `DeviceGray`, `ImageXObject`, `FormXObject` |
| `Phpdftk\Pdf\Core\Interactive\Form\` | `AcroForm`, `TextField`, `ButtonField`, `ChoiceField`, `SignatureField` |
| `Phpdftk\Pdf\Core\Action\` | `GoToAction`, `GoToRAction`, `URIAction`, `JavaScriptAction`, `NamedAction` |
| `Phpdftk\Pdf\Core\Content\` | `ContentStream` (fluent operator API), `Resources` |
| `Phpdftk\Pdf\Writer\` | `PdfWriter`, `ObjectRegistry`, `CrossReferenceTable` |

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

See the [Spec Coverage tracker](https://phpdftk.dev/standards/spec/coverage/) for a full ISO 32000-2 coverage audit.

## Conformance Validation

Validate PDF output against 8 ISO subset standards (31 conformance levels):

| Standard | ISO | Levels |
|---|---|---|
| PDF/A | 19005 | 1a, 1b, 2a, 2b, 2u, 3a, 3b, 3u, 4, 4e, 4f |
| PDF/UA | 14289 | UA-1, UA-2 |
| PDF/X | 15930 | X-1a:2003, X-3:2003, X-4, X-5g, X-5pg, X-5n |
| PDF/VT | 16612 | VT-1, VT-2, VT-2s |
| PDF/E | 24517 | E-1 |
| PDF/R | 23504 | R-1 |
| ZUGFeRD/Factur-X | — | MINIMUM, BASIC WL, BASIC, EN 16931, EXTENDED, XRECHNUNG |
| PDF/mail | 23053-2 | Mail-1 |

```php
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;

$writer = new PdfWriter();
$writer->setConformance(PdfAProfile::A2b);
// ... build document ...
$writer->save('archive.pdf');
// Constraints enforced automatically at generation time.
```

The library auto-injects XMP identification, pins the PDF version, and runs 14 constraint types (font embedding, color spaces, metadata, transparency, encryption, actions, tagged structure, and more).

## External Compliance

275 external validation tests passing across 5 independent tools:

- **QPDF** (236 tests) — structural integrity
- **Arlington PDF Model** (6 tests) — dictionary-level spec conformance
- **veraPDF** (2 tests) — PDF/A archival conformance
- **Matterhorn Protocol** (6 tests) — PDF/UA accessibility
- **JHOVE + Preflight** (25 tests) — format validation, PDF/A cross-validation

See the [Compliance Report](https://phpdftk.dev/standards/validation/report/) for the full report.

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
