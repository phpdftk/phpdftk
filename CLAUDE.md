# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/Document/SimpleTextTest.php

# Run a single test method
vendor/bin/phpunit --filter testGeneratesSimpleTextPdf

# Run benchmarks
vendor/phpbench/phpbench/phpbench run --report=default

# Run benchmarks for a specific class
vendor/phpbench/phpbench/phpbench run benchmarks/GeneratePdfBench.php --report=default
```

## Architecture

This library maps every PDF specification object type to a PHP 8.4 class, with each `/Field` from the PDF spec mapping directly to a PHP property (camelCase). The goal is a 1:1 correspondence between the PDF spec and the PHP object model.

### Design Principles

- **Every PDF object is a PHP class** extending `PdfObject` (in `src/Core/`)
- **Properties match PDF field names** in camelCase (e.g., `/MediaBox` → `$mediaBox`, `/FirstChar` → `$firstChar`)
- **Each class has a `PDF_TYPE` constant** with the `/Type` value from the spec
- **`toPdf(): string`** on every object serializes it to raw PDF syntax
- **`toIndirectObject(): string`** on `PdfObject` wraps output in `X Y obj ... endobj`

### Namespace Layout (`src/`)

| Namespace | Purpose |
|---|---|
| `Core\` | Primitive PDF types: `PdfObject`, `PdfName`, `PdfString`, `PdfNumber`, `PdfBoolean`, `PdfNull`, `PdfArray`, `PdfDictionary`, `PdfStream`, `PdfReference`, `Serializable` interface |
| `Document\` | Document structure: `Catalog`, `PageTree`, `Page`, `Info`, `ViewerPreferences` |
| `Font\` | All font types: `Font`, `Type1Font`, `TrueTypeFont`, `Type0Font`, `CIDFont`, `FontDescriptor`, `Encoding`, `StandardFont` (enum for 14 standard fonts) |
| `Annotation\` | All annotation subtypes extending abstract `Annotation`: `TextAnnotation`, `LinkAnnotation`, `FreeTextAnnotation`, `HighlightAnnotation`, `StampAnnotation`, `InkAnnotation`, `PopupAnnotation`, `WidgetAnnotation` |
| `Graphics\ColorSpace\` | `DeviceRGB`, `DeviceCMYK`, `DeviceGray` |
| `Graphics\XObject\` | `ImageXObject`, `FormXObject` |
| `Graphics\` | `ExtGState` |
| `Interactive\Form\` | `AcroForm`, `Field`, `TextField`, `ButtonField`, `ChoiceField`, `SignatureField` |
| `Action\` | `Action` (abstract), `GoToAction`, `URIAction`, `JavaScriptAction`, `NamedAction` |
| `Content\` | `ContentStream` (fluent API for all PDF operators), `Resources` (resource dictionary) |
| `Writer\` | `PdfWriter`, `ObjectRegistry`, `CrossReferenceTable` |

### How a PDF is Assembled (`src/Writer/PdfWriter.php`)

The `PdfWriter` orchestrates PDF generation:
1. **`ObjectRegistry`** assigns sequential object numbers as objects are registered via `addFont()`, `addPage()`, `addContentStream()`, etc.
2. **`PdfWriter::generate()`** serializes all objects in order, tracking byte offsets for the xref table
3. **`CrossReferenceTable`** builds the spec-compliant xref section (20-byte entries, object 0 always the free list head)
4. The trailer dictionary includes `/Root` (Catalog reference), `/Info`, `/Size`, and `/ID` (two MD5 hashes)

Typical usage:
```php
$writer = new PdfWriter();
$writer->setInfo($info);
$page = $writer->addPage(612, 792);  // letter size in points
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
$content = $writer->addContentStream($page);
$content->beginText()->setFont('F1', 12)->moveTextPosition(72, 720)->showText('Hello')->endText();
$writer->save('/path/file.pdf');
```

### Content Streams (`src/Content/ContentStream.php`)

`ContentStream` extends `PdfStream` and provides a fluent API covering all PDF content operators organized into groups:
- **Text**: `beginText()`, `endText()`, `setFont()`, `moveTextPosition()`, `showText()`, `showTextArray()`, `setTextMatrix()`, `setCharSpacing()`, `setWordSpacing()`, `setTextLeading()`, etc.
- **Graphics state**: `saveGraphicsState()`, `restoreGraphicsState()`, `setLineWidth()`, `setLineCap()`, `setLineJoin()`, `concatMatrix()`, `setGraphicsState()`
- **Paths**: `moveTo()`, `lineTo()`, `curveTo()`, `rectangle()`, `closePath()`
- **Painting**: `stroke()`, `fill()`, `fillAndStroke()`, `clip()`, `endPath()`, etc.
- **Color**: `setStrokeColorRGB()`, `setFillColorRGB()`, `setStrokeColorCMYK()`, `setFillColorGray()`, etc.
- **XObject**: `doXObject()`
- **Raw**: `raw()` for any unlisted operator

### Resources (`src/Content/Resources.php`)

The `Resources` dictionary maps string names to `PdfReference` objects. `PdfWriter` auto-assigns font resource names (`F1`, `F2`, ...) and XObject names (`X1`, `X2`, ...) when objects are added to a page. Content streams reference these by name (e.g., `setFont('F1', 12)`, `doXObject('X1')`).

### Spec Compliance Details

- xref entries are exactly 20 bytes: `OOOOOOOOOO GGGGG n \r\n`
- Object 0 is always the free list head: `0000000000 65535 f \r\n`
- Stream dictionaries include `/Length` set to the exact byte count of stream data
- `PdfName` escapes special characters with `#XX` hex notation
- `PdfString` escapes `(`, `)`, `\`, `\n`, `\r`, `\t`
- Binary comment `%âãÏÓ` follows the header line

## Tests

Tests are in `tests/` and generated PDFs are written to `tests/output/` (gitignored). Each test class asserts the file is created and starts with `%PDF`.

| Test | Output |
|---|---|
| `SimpleTextTest` | 3-page PDF with text in multiple fonts |
| `AnnotationsTest` | PDF with TextAnnotation, LinkAnnotation, HighlightAnnotation, StampAnnotation |
| `GraphicsTest` | PDF with colored shapes, paths, Bezier curves |
| `FormFieldsTest` | PDF with TextField, ButtonField, ChoiceField via AcroForm |
| `MultiPageComplexTest` | 10-page PDF with document info and ViewerPreferences |
| `PdfObjectTest` | Unit tests for all Core primitive types |

## Benchmarks

`benchmarks/GeneratePdfBench.php` and `benchmarks/MemoryBench.php` compare phpdftk against TCPDF, FPDF, mPDF, and Dompdf for 1-page and 10-page PDF generation, measuring both wall-clock time (via phpbench) and peak memory (`memory_get_peak_usage(true)`).
