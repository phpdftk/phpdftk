# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies
composer install

# Run all tests
vendor/bin/phpunit

# Run a single test suite
vendor/bin/phpunit --testsuite core
vendor/bin/phpunit --testsuite writer

# Run a single test file
vendor/bin/phpunit packages/pdf/core/tests/Document/SimpleTextTest.php

# Run a single test method
vendor/bin/phpunit --filter testGeneratesSimpleTextPdf

# Run static analysis
scripts/analyse

# Run benchmarks (generates docs/benchmarks.md automatically)
scripts/benchmark

# Run benchmarks for a specific class
vendor/phpbench/phpbench/phpbench run benchmarks/GeneratePdfBench.php --report=default

# Generate code coverage (writes docs/coverage-badge.svg)
scripts/coverage
```

## Repository Structure

This is a **monorepo with 12 packages** under `packages/`. All packages use the `apprlabs` Composer vendor prefix and `ApprLabs\` PHP namespace root.

| Package dir | Composer name | PHP namespace | Purpose |
|---|---|---|---|
| `pdf/core/` | `apprlabs/pdf-core` | `ApprLabs\Pdf\Core\` | PDF object model — all spec classes, content streams, document structure |
| `pdf/writer/` | `apprlabs/pdf-writer` | `ApprLabs\Pdf\Writer\` | Serializes object model to PDF bytes (PdfWriter, ObjectRegistry, CrossReferenceTable) |
| `pdf/reader/` | `apprlabs/pdf-reader` | `ApprLabs\Pdf\Reader\` | Parses existing PDFs into object model (skeleton — not yet implemented) |
| `geometry/` | `apprlabs/geometry` | `ApprLabs\Geometry\` | Rectangle, Matrix, PageSize, BezierCurve |
| `color/` | `apprlabs/color` | `ApprLabs\Color\` | RGB/CMYK/Gray color models with conversions |
| `filters/` | `apprlabs/filters` | `ApprLabs\Filters\` | FlateDecode, ASCII85, ASCIIHex, RunLength codecs |
| `encoding/` | `apprlabs/encoding` | `ApprLabs\Encoding\` | WinAnsi/MacRoman tables, Adobe Glyph List, CMap parser |
| `font-metrics/` | `apprlabs/font-metrics` | `ApprLabs\FontMetrics\` | AFM metrics for the 14 standard PDF fonts |
| `font-parser/` | `apprlabs/font-parser` | `ApprLabs\FontParser\` | Parses TrueType fonts: metrics, glyph widths, character maps for PDF embedding |
| `image-metadata/` | `apprlabs/image-metadata` | `ApprLabs\ImageMetadata\` | Parse JPEG/PNG/GIF/TIFF/WebP headers |
| `xmp/` | `apprlabs/xmp` | `ApprLabs\Xmp\` | XMP metadata packet read/write |
| `crypt/` | `apprlabs/crypt` | `ApprLabs\Crypt\` | AES-128/256 and RC4 with PDF key derivation |

**Dependency graph:**
```
geometry, color, filters, encoding, font-metrics, font-parser, image-metadata, xmp, crypt
    ↓ (all depended on by)
  pdf-core  (ApprLabs\Pdf\Core\)
    ↓ (depended on by both)
pdf-writer                pdf-reader
(ApprLabs\Pdf\Writer\)    (ApprLabs\Pdf\Reader\)
```

`writer` and `reader` never depend on each other. The support packages have no PDF dependency and can be used standalone. Each package has a **distinct PSR-4 namespace root** — no split-package ambiguity.

## Architecture

This library maps every PDF specification object type (ISO 32000-2:2020) to a PHP 8.4 class, with each `/Field` from the PDF spec mapping directly to a PHP property in camelCase. The goal is a 1:1 correspondence between the PDF spec and the PHP object model.

### Design Principles

- **Every PDF object is a PHP class** extending `PdfObject` (in `Core\`) — OR implementing `Serializable` for inline/embedded dictionaries
- **Properties match PDF field names** in camelCase (e.g., `/MediaBox` → `$mediaBox`, `/FirstChar` → `$firstChar`)
- **Each class has a `PDF_TYPE` constant** with the `/Type` value from the spec
- **`toPdf(): string`** on every object serializes it to raw PDF syntax
- **`toIndirectObject(): string`** on `PdfObject` wraps output in `X Y obj ... endobj`

### `PdfObject` vs. `Serializable`

This is the most important architectural distinction:

**`PdfObject` (abstract class, extends `Serializable`)**:
- Used for top-level PDF objects that need to be referenced from elsewhere
- Assigned an object number by `ObjectRegistry` when registered with `PdfWriter`
- Serialized as indirect objects: `5 0 obj ... endobj`
- Must be registered via `PdfWriter::register()` or a dedicated `addX()` method
- Examples: `Page`, `Font`, `Annotation`, `Outline`, `OutlineItem`, `PageLabel`

**`Serializable` (interface, `toPdf(): string` only)**:
- Used for "inline" dictionaries nested directly inside another object's dictionary
- Never assigned an object number; never registered with `ObjectRegistry`
- Serialized inline as part of the parent object
- Examples: `TransitionDict` (nested in `Page`), `BorderStyle` (nested in `Annotation`)

When adding a new PDF dictionary type, decide: does it need to be independently referenced via `X 0 R`? If yes → `PdfObject`. If it only appears inline inside one parent → `Serializable`.

### Namespace Layout

**`packages/pdf/core/src/`** — PSR-4 root `ApprLabs\Pdf\Core\`:

| Namespace | Classes |
|---|---|
| `ApprLabs\Pdf\Core\` | `PdfObject`, `PdfName`, `PdfString`, `PdfNumber`, `PdfBoolean`, `PdfNull`, `PdfArray`, `PdfDictionary`, `PdfStream`, `PdfReference`, `Serializable` |
| `ApprLabs\Pdf\Core\Document\` | `Catalog`, `PageTree`, `Page`, `Info`, `ViewerPreferences`, `Outline`, `OutlineItem`, `PageLabel`, `TransitionDict`, `MarkInfo`, `Destination`, `GroupAttributes`, `NameTree`, `NumberTree`, `OutputIntent`, `Thread`, `Bead`, `OCG`, `OCMD`, `OCPropertiesDict`, `Collection`, `CollectionItem`, `CollectionSchema`, `StructTreeRoot`, `StructElem`, `ObjectRef` |
| `ApprLabs\Pdf\Core\Font\` | `Font` (abstract), `Type1Font`, `TrueTypeFont` (has `fromFile(string $path): self`), `Type0Font`, `CIDFont`, `FontDescriptor`, `Encoding`, `StandardFont` (enum for 14 standard fonts), `CIDSystemInfo` |
| `ApprLabs\Pdf\Core\Annotation\` | `Annotation` (abstract), `TextAnnotation`, `LinkAnnotation`, `FreeTextAnnotation`, `HighlightAnnotation`, `StampAnnotation`, `InkAnnotation`, `PopupAnnotation`, `WidgetAnnotation`, `UnderlineAnnotation`, `SquigglyAnnotation`, `StrikeOutAnnotation`, `LineAnnotation`, `SquareAnnotation`, `CircleAnnotation`, `PolygonAnnotation`, `PolyLineAnnotation`, `CaretAnnotation`, `FileAttachmentAnnotation`, `SoundAnnotation`, `WatermarkAnnotation`, `PrinterMarkAnnotation`, `ScreenAnnotation`, `MovieAnnotation`, `RedactAnnotation`, `ThreeDAnnotation`, `ProjectionAnnotation`, `RichMediaAnnotation`, `TrapNetAnnotation`, `BorderStyle`, `BorderEffect`, `AppearanceDict`, `AppearanceCharacteristics` |
| `ApprLabs\Pdf\Core\Action\` | `Action` (abstract), `GoToAction`, `GoToRAction`, `URIAction`, `JavaScriptAction`, `NamedAction` |
| `ApprLabs\Pdf\Core\Graphics\ColorSpace\` | `ColorSpace` (abstract), `DeviceRGB`, `DeviceCMYK`, `DeviceGray` |
| `ApprLabs\Pdf\Core\Graphics\XObject\` | `ImageXObject`, `FormXObject` |
| `ApprLabs\Pdf\Core\Graphics\` | `ExtGState` |
| `ApprLabs\Pdf\Core\Interactive\Form\` | `AcroForm`, `Field` (abstract), `TextField`, `ButtonField`, `ChoiceField`, `SignatureField` |
| `ApprLabs\Pdf\Core\Content\` | `ContentStream`, `Resources` |

**`packages/font-parser/src/`** — PSR-4 root `ApprLabs\FontParser\`:

| Namespace | Classes |
|---|---|
| `ApprLabs\FontParser\` | `TrueTypeParser`, `TrueTypeData` |

**`packages/pdf/writer/src/`** — PSR-4 root `ApprLabs\Pdf\Writer\`:

| Namespace | Classes |
|---|---|
| `ApprLabs\Pdf\Writer\` | `PdfWriter`, `ObjectRegistry`, `CrossReferenceTable` |

### How a PDF is Assembled (`PdfWriter`)

```php
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Font\StandardFont;

$writer = new PdfWriter();
$writer->setInfo($info);                                         // optional metadata
$page = $writer->addPage(612, 792);                             // letter size in points (or pass Rectangle)
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica)); // returns 'F1'
$content = $writer->addContentStream($page);
$content->beginText()->setFont('F1', 12)->moveTextPosition(72, 720)->showText('Hello')->endText();
$writer->save('/path/file.pdf');
```

**`PdfWriter` public API:**
```php
// Document structure
getCatalog(): Catalog
getPageTree(): PageTree
setInfo(Info $info): void

// Pages
addPage(Rectangle|float $widthOrRect = 612, float $height = 792): Page

// Fonts — auto-assigns resource names F1, F2, ...
addFont(Font $font, ?Page $page = null): string

// Content streams
addContentStream(Page $page): ContentStream

// Images — auto-assigns resource names X1, X2, ...
addImage(string $path, Page $page): string

// Outlines (bookmarks)
setOutline(Outline $outline): Outline
addOutlineItem(OutlineItem $item): PdfReference

// Page labels (page numbering)
setPageLabels(array $labels): void  // assoc: pageIndex => PageLabel

// Generic — use for annotations, AcroForm fields, etc.
register(PdfObject $object): PdfReference

// Output
generate(): string
save(string $path): void
```

`generate()` uses an array-of-chunks + `implode()` approach (not string concatenation) for O(N) performance. `CrossReferenceTable` builds 20-byte-per-entry xref entries.

### Bookmarks (`Outline` + `OutlineItem`)

`Outline` is the root bookmark dictionary registered via `setOutline()`. `OutlineItem` nodes form a doubly-linked list tree — each item has `$parent`, `$prev`, `$next`, `$first`, `$last`. The tree must be manually wired after adding all items.

`OutlineItem::$dest` accepts `PdfName` (named dest), `PdfArray` (explicit dest), or string. `$c` is a 3-element `PdfArray` of RGB floats. `$f` flags: `1` = italic, `2` = bold, `3` = both.

### Page Labels (`PageLabel`)

`$s` style: `D` (arabic), `r` (roman lower), `R` (roman upper), `a` (alpha lower), `A` (alpha upper). `$p` is an optional prefix. `$st` is the starting number (default 1). Pass an `int → PageLabel` map to `setPageLabels()`.

### Page Transitions (`TransitionDict`)

Implements `Serializable` (not `PdfObject`) — assign directly to `Page::$transition`. Styles: Split, Blinds, Box, Wipe, Dissolve, Glitter, R, Fly, Push, Cover, Uncover, Fade. `$d` = duration in seconds.

### Border Styles (`BorderStyle`)

Implements `Serializable`. Assign to any annotation's `$bs` property (defined on the abstract `Annotation` base class as `Serializable|null`). **Never redeclare `$bs` in annotation subclasses** — the base class owns it.

### Actions

| Class | `/S` | Use |
|---|---|---|
| `GoToAction` | `/GoTo` | Navigate to destination within the same PDF |
| `GoToRAction` | `/GoToR` | Navigate to destination in a remote PDF (`$f` = file path, `$dest` = destination — both required) |
| `URIAction` | `/URI` | Open a URI |
| `JavaScriptAction` | `/JavaScript` | Execute JavaScript |
| `NamedAction` | `/Named` | Execute a named action (NextPage, PrevPage, etc.) |

### Content Streams (`ContentStream`)

`ContentStream` extends `PdfStream` with a fluent API for all PDF content operators. `escapeString(string): string` returns `(text)` with parens already included — do not wrap again when composing operator strings.

**Method reference by operator group:**

| Group | Key methods | Operators |
|---|---|---|
| Text state | `beginText()`, `endText()`, `setFont()`, `moveTextPosition()`, `moveTextPositionNewLine()`, `showText()`, `showTextArray()`, `nextLine()`, `setTextMatrix()`, `setCharSpacing()`, `setWordSpacing()`, `setHorizontalScaling()`, `setTextLeading()`, `setTextRenderingMode()`, `setTextRise()` | BT ET Tf Td TD Tj TJ T* Tm Tc Tw Tz TL Tr Ts |
| Text shorthand | `moveToNextLineAndShowText(string $text)`, `setSpacingMoveAndShowText(float $aw, float $ac, string $text)` | `'` `"` |
| Graphics state | `saveGraphicsState()`, `restoreGraphicsState()`, `setLineWidth()`, `setLineCap()`, `setLineJoin()`, `setMiterLimit()`, `setDashPattern()`, `setRenderingIntent()`, `setFlatness()`, `setGraphicsState()`, `concatMatrix()` | q Q w J j M d ri i gs cm |
| Path construction | `moveTo()`, `lineTo()`, `curveTo()`, `curveToV()`, `curveToY()`, `closePath()`, `rectangle()` | m l c v y h re |
| Path painting | `stroke()`, `closeAndStroke()`, `fill()`, `fillEvenOdd()`, `fillAndStroke()`, `fillAndStrokeEvenOdd()`, `closeFillAndStroke()`, `closeFillAndStrokeEvenOdd()`, `endPath()`, `clip()`, `clipEvenOdd()` | S s f f* B B* b b* n W W* |
| Color | `setStrokeColorRGB()`, `setFillColorRGB()`, `setStrokeColorCMYK()`, `setFillColorCMYK()`, `setStrokeColorGray()`, `setFillColorGray()`, `setStrokeColorSpace()`, `setFillColorSpace()`, `setStrokeColor()`, `setFillColor()` | RG rg K k G g CS cs SCN scn |
| Color (typed) | `setFillRgbColor(RgbColor)`, `setStrokeRgbColor(RgbColor)`, `setFillCmykColor(CmykColor)`, `setStrokeCmykColor(CmykColor)`, `setFillGrayColor(GrayColor)`, `setStrokeGrayColor(GrayColor)` | — |
| Geometry helpers | `rectangleObject(Rectangle)`, `concatMatrixObject(Matrix)` | re cm |
| XObject | `doXObject(string $name)` | Do |
| Inline image | `inlineImage(array $params, string $data)` | BI ID EI |
| Shading | `paintShading(string $name)` | sh |
| Type 3 glyph | `setGlyphWidth(float $wx, float $wy)`, `setGlyphWidthAndBoundingBox(float $wx, $wy, $llx, $lly, $urx, $ury)` | d0 d1 |
| Marked content | `markedContentPoint(string $tag)`, `markedContentPointWithProperties(string $tag, string $props)`, `beginMarkedContent(string $tag)`, `beginMarkedContentWithProperties(string $tag, string $props)`, `endMarkedContent()` | MP DP BMC BDC EMC |
| Compatibility | `beginCompatibility()`, `endCompatibility()` | BX EX |
| Raw | `raw(string $operator)` | (any) |

### Spec Compliance Details

- xref entries are exactly 20 bytes: `OOOOOOOOOO GGGGG n \r\n`
- Object 0 is always the free list head: `0000000000 65535 f \r\n`
- Stream dictionaries include `/Length` set to the exact byte count of stream data
- `PdfName` escapes special characters with `#XX` hex notation
- `PdfString` escapes `(`, `)`, `\`, `\n`, `\r`, `\t`
- Binary comment `%âãÏÓ` follows the header line
- PDF version is `1.7`

## Tests

Tests live under each package in `packages/<pkg>/tests/`. The root `phpunit.xml` discovers all packages by named suite. Generated PDFs are written to `packages/pdf/core/tests/output/` (gitignored). All integration tests assert the file exists and begins with `%PDF`.

Run a single suite: `vendor/bin/phpunit --testsuite core`

### `core` package tests (`packages/pdf/core/tests/`)

| Test file | What it tests |
|---|---|
| `Core/PdfObjectTest.php` | All Core primitives: PdfArray, PdfDictionary, PdfName, PdfNumber, PdfString, PdfStream, PdfReference, PdfBoolean, PdfNull |
| `Document/SimpleTextTest.php` | End-to-end: 3-page PDF with multiple font types |
| `Document/AnnotationsTest.php` | End-to-end: TextAnnotation, LinkAnnotation, HighlightAnnotation, StampAnnotation |
| `Document/GraphicsTest.php` | End-to-end: colored shapes, paths, Bezier curves |
| `Document/FormFieldsTest.php` | End-to-end: AcroForm with TextField, ButtonField, ChoiceField |
| `Document/MultiPageComplexTest.php` | End-to-end: 10-page PDF with Info and ViewerPreferences |
| `Document/DocumentObjectsTest.php` | Unit: Catalog, Page, PageTree, Info, ViewerPreferences |
| `Document/OutlineTest.php` | Unit: Outline root, OutlineItem tree linking |
| `Document/PageLabelTest.php` | Unit: PageLabel styles, prefix, starting value |
| `Document/TransitionDictTest.php` | Unit: TransitionDict styles, duration, assigned to Page |
| `Document/MarkInfoTest.php` | Unit: MarkInfo fields, assigned to Catalog |
| `Document/DocumentStructureTest.php` | Unit: Destination, GroupAttributes, NameTree, NumberTree, OutputIntent, Thread, Bead, OCG, OCMD, OCPropertiesDict, Collection, CollectionItem, CollectionSchema, StructTreeRoot, StructElem, ObjectRef, AppearanceDict, AppearanceCharacteristics, and Catalog/PageTree/Page fields |
| `Annotation/AnnotationTest.php` | Unit: all annotation subtypes (Text, Link, FreeText, Highlight, Stamp, Ink, Popup, Widget, Underline, Squiggly, StrikeOut, Line, Square, Circle, Polygon, PolyLine, Caret, FileAttachment, Sound, Watermark, PrinterMark, Screen, Movie, Redact, 3D, Projection, RichMedia, TrapNet) and base fields |
| `Annotation/BorderStyleTest.php` | Unit: BorderStyle styles, dash pattern, attached to annotation |
| `Annotation/BorderEffectTest.php` | Unit: BorderEffect styles, intensity, attached to FreeTextAnnotation |
| `Content/ContentStreamTest.php` | Unit: all ~70 ContentStream operators |
| `Action/ActionTest.php` | Unit: GoToAction, URIAction, JavaScriptAction, NamedAction |
| `Action/GoToRActionTest.php` | Unit: GoToRAction required fields, destinations, newWindow |
| `Interactive/FormTest.php` | Unit: AcroForm, Field subclasses |
| `Font/FontTest.php` | Unit: all font types; includes TrueTypeFont::fromFile() tests |
| `Font/CIDSystemInfoTest.php` | Unit: CIDSystemInfo fields, used in CIDFont |
| `Document/EmbeddedFontsTest.php` | End-to-end: TrueType font embedding with FontDescriptor, ToUnicode, Widths |
| `Document/AnnotationSubtypesTest.php` | End-to-end: all 20 new annotation subtypes across 3 pages |
| `Document/DocumentFeaturesTest.php` | End-to-end: OutputIntent, page boxes, named destinations, OCG, tagged PDF, embedded TrueType |

### `writer` package tests (`packages/pdf/writer/tests/`)

| Test file | What it tests |
|---|---|
| `Writer/WriterTest.php` | Unit: PdfWriter, ObjectRegistry, CrossReferenceTable |

### Support package tests

| Package | Test file | What it tests |
|---|---|---|
| `color` | `ColorTest.php` | RGB/CMYK/Gray constructors and conversions |
| `crypt` | `CryptTest.php` | AES-128/256 and RC4 encryption/decryption |
| `encoding` | `EncodingTest.php` | WinAnsi/MacRoman tables, Glyph List |
| `filters` | `FilterTest.php` | FlateDecode, ASCII85, ASCIIHex, RunLength encode/decode |
| `font-metrics` | `StandardFontMetricsTest.php` | Width lookups for all 14 standard fonts |
| `font-parser` | `TrueTypeParserTest.php` | TrueType binary parsing: metrics, widths, cmap, name tables |
| `geometry` | `RectangleTest.php`, `MatrixTest.php`, `ExtendedGeometryTest.php` | Rectangle, Matrix transforms, BezierCurve, PageSize |
| `image-metadata` | `ImageParserTest.php` | JPEG/PNG/GIF/TIFF/WebP header parsing |
| `xmp` | `XmpTest.php` | XMP metadata packet read/write |

## Benchmarks

`benchmarks/GeneratePdfBench.php` — wall-clock time via phpbench; compares phpdftk against TCPDF, FPDF, mPDF, Dompdf at 1, 5, 10, 50, and 100 pages. Includes `benchPhpdftk10PagesWithBookmarksAndTransitions()` exercising Outline + OutlineItem + TransitionDict, `benchPhpdftk10PagesWithAnnotations()` exercising annotation subtypes, `benchPhpdftk10PagesWithEmbeddedFont()` exercising TrueType font embedding, and `benchPhpdftk10PagesWithDocumentStructure()` exercising OutputIntent, named destinations, page labels, tagged PDF structure.

`benchmarks/MemoryBench.php` — peak memory (`memory_get_peak_usage(true)`); compares phpdftk against FPDF and TCPDF at the same page counts.

Run `scripts/benchmark` to regenerate `docs/benchmarks.md` automatically.

## Scripts

| Script | What it does |
|---|---|
| `scripts/analyse` | Runs phpstan with 512M memory limit |
| `scripts/benchmark` | Runs phpbench → pipes to `scripts/parse-benchmarks.php` → writes `docs/benchmarks.md` |
| `scripts/coverage` | Generates coverage report; writes `docs/coverage-badge.svg` |
| `scripts/parse-benchmarks.php` | Parses phpbench aggregate output into markdown tables |
| `scripts/generate-badge.php` | Creates SVG badge from a coverage percentage |

## Docs

| File | Contents |
|---|---|
| `docs/benchmarks.md` | Auto-generated performance comparison (do not edit manually) |
| `docs/spec-coverage.md` | PDF spec compliance tracker — every field of every spec object with ✓/~/✗ status |
| `docs/coverage-badge.svg` | Auto-generated code coverage badge |

When implementing new spec features, update `docs/spec-coverage.md` to reflect the new coverage.
