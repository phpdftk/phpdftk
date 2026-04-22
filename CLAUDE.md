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
| `pdf/all/` | `apprlabs/pdf` | — (metapackage) | One-install bundle — transitively requires `pdf-core` + `pdf-writer` (+ `pdf-reader` when it lands) |
| `pdf/core/` | `apprlabs/pdf-core` | `ApprLabs\Pdf\Core\` | PDF object model **and** file serialization — all spec classes plus `File\PdfFileWriter`, `ObjectRegistry`, `CrossReferenceTable`, `TrailerDictionary` |
| `pdf/writer/` | `apprlabs/pdf-writer` | `ApprLabs\Pdf\Writer\` | Ergonomic builder — `PdfWriter` facade over `ApprLabs\Pdf\Core\File\PdfFileWriter` |
| `pdf/reader/` | `apprlabs/pdf-reader` | `ApprLabs\Pdf\Reader\` | Parses existing PDFs into object model |
| `pdf/toolkit/` | `apprlabs/pdf-toolkit` | `ApprLabs\Pdf\Toolkit\` | High-level pipelines: FormFiller, PdfStamper, PageSlicer, PdfMerger, PageTransformer, AnnotationFlattener, TextRedactor, MetadataEditor, PdfEncrypt, BookmarkEditor, PageLabeler, TextExtractor |
| `geometry/` | `apprlabs/geometry` | `ApprLabs\Geometry\` | Rectangle, Matrix, PageSize, BezierCurve |
| `color/` | `apprlabs/color` | `ApprLabs\Color\` | RGB/CMYK/Gray color models with conversions |
| `filters/` | `apprlabs/filters` | `ApprLabs\Filters\` | FlateDecode, ASCII85, ASCIIHex, RunLength codecs |
| `encoding/` | `apprlabs/encoding` | `ApprLabs\Encoding\` | WinAnsi/MacRoman tables, Adobe Glyph List, CMap parser |
| `font-metrics/` | `apprlabs/font-metrics` | `ApprLabs\FontMetrics\` | AFM metrics for the 14 standard PDF fonts |
| `font-parser/` | `apprlabs/font-parser` | `ApprLabs\FontParser\` | Parses TrueType fonts: metrics, glyph widths, character maps for PDF embedding |
| `image-metadata/` | `apprlabs/image-metadata` | `ApprLabs\ImageMetadata\` | Parse JPEG/PNG/GIF/TIFF/WebP headers |
| `xmp/` | `apprlabs/xmp` | `ApprLabs\Xmp\` | XMP metadata packet read/write |
| `crypt/` | `apprlabs/crypt` | `ApprLabs\Crypt\` | AES-128/256 and RC4 with PDF key derivation, public-key PKCS#7 envelope operations |

**Dependency graph:**
```
geometry, color, filters, encoding, font-metrics, font-parser, image-metadata, xmp, crypt
    ↓ (all depended on by)
  pdf-core  (ApprLabs\Pdf\Core\)
  ├── object model (Document, Font, Annotation, Graphics, …)
  └── file serialization (File\PdfFileWriter — emits %PDF, xref, trailer)
    ↓ (depended on by both)
pdf-writer                pdf-reader
(ApprLabs\Pdf\Writer\)    (ApprLabs\Pdf\Reader\)
  friendly builder         parser (skeleton)
```

`writer` and `reader` never depend on each other. `pdf-writer` is a
thin ergonomic facade: it composes `ApprLabs\Pdf\Core\File\PdfFileWriter`
and adds the `addPage` / `addFont` / `setOutline` / etc. builder
methods. `pdf-reader` (future) can reuse `PdfFileWriter` directly for
incremental-update emission without depending on `pdf-writer`. The
support packages have no PDF dependency and can be used standalone.
Each package has a **distinct PSR-4 namespace root** — no split-package
ambiguity.

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
- Assigned an object number by `Core\File\ObjectRegistry` when registered with `PdfFileWriter` (or, transitively, via `PdfWriter`)
- Serialized as indirect objects: `5 0 obj ... endobj`
- Must be registered via `PdfWriter::register()` / `PdfFileWriter::register()` or a dedicated `addX()` method
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
| `ApprLabs\Pdf\Core\` | `PdfObject`, `PdfName`, `PdfString`, `PdfNumber`, `PdfBoolean`, `PdfNull`, `PdfArray`, `PdfDictionary`, `PdfStream`, `PdfReference`, `Serializable`, `PdfDate` (DateTimeInterface helper) |
| `ApprLabs\Pdf\Core\Document\` | `Catalog`, `PageTree`, `Page`, `Info`, `ViewerPreferences`, `Outline`, `OutlineItem`, `PageLabel`, `TransitionDict`, `MarkInfo`, `Destination`, `GroupAttributes`, `NameTree`, `NumberTree`, `NamesDictionary`, `OutputIntent`, `Thread`, `Bead`, `OCG`, `OCMD`, `OCPropertiesDict`, `OCUsage`, `OCConfig`, `Collection`, `CollectionItem`, `CollectionSchema`, `StructTreeRoot`, `StructElem`, `ObjectRef`, `CrossReferenceStream`, `ObjectStream`, `RoleMap`, `ClassMap`, `StructAttribute`, `BoxColorInfo`, `BoxStyle`, `DSS`, `DPartRoot`, `DPart`, `Requirement`, `RequirementHandler`, `MetadataStream`, `LinearizationParameters`, `HintStream`, `StandardStructureType` |
| `ApprLabs\Pdf\Core\Document\StructAttribute\` | `LayoutAttribute`, `ListAttribute`, `PrintFieldAttribute`, `TableAttribute` |
| `ApprLabs\Pdf\Core\FileSpec\` | `FileSpec`, `EmbeddedFile`, `EmbeddedFileParams` |
| `ApprLabs\Pdf\Core\Filter\` | `FlateDecodeParams`, `CCITTFaxDecodeParams`, `JBIG2DecodeParams`, `DCTDecodeParams`, `JPXDecodeParams`, `CryptFilterDecodeParams` |
| `ApprLabs\Pdf\Core\Multimedia\` | `Sound`, `Movie`, `Rendition` (abstract), `MediaRendition`, `SelectorRendition`, `MediaClip` (abstract), `MediaClipData`, `MediaClipSection`, `MediaPlayParams`, `MediaScreenParams`, `MediaCriteria`, `Navigator` |
| `ApprLabs\Pdf\Core\ThreeD\` | `ThreeDStream`, `ThreeDView`, `ThreeDBackground`, `ThreeDRenderMode`, `ThreeDLightingScheme`, `ThreeDCrossSection`, `ThreeDNode`, `ThreeDMeasure` |
| `ApprLabs\Pdf\Core\Font\` | `Font` (abstract), `Type1Font`, `TrueTypeFont` (has `fromFile(string $path): self`), `Type0Font`, `Type3Font`, `MMType1Font`, `CIDFont`, `CIDFontType0Font`, `CIDFontType2Font`, `FontDescriptor`, `Encoding`, `StandardFont` (enum for 14 standard fonts), `CIDSystemInfo`, `CMapStream` |
| `ApprLabs\Pdf\Core\Font\FontFile\` | `Type1FontFile`, `TrueTypeFontFile`, `CFFFontFile` |
| `ApprLabs\Pdf\Core\Annotation\` | `Annotation` (abstract), `MarkupAnnotation` (abstract, extends `Annotation`), `TextAnnotation`, `LinkAnnotation`, `FreeTextAnnotation`, `HighlightAnnotation`, `StampAnnotation`, `InkAnnotation`, `PopupAnnotation`, `WidgetAnnotation`, `UnderlineAnnotation`, `SquigglyAnnotation`, `StrikeOutAnnotation`, `LineAnnotation`, `SquareAnnotation`, `CircleAnnotation`, `PolygonAnnotation`, `PolyLineAnnotation`, `CaretAnnotation`, `FileAttachmentAnnotation`, `SoundAnnotation`, `WatermarkAnnotation`, `PrinterMarkAnnotation`, `ScreenAnnotation`, `MovieAnnotation`, `RedactAnnotation`, `ThreeDAnnotation`, `ProjectionAnnotation`, `RichMediaAnnotation`, `TrapNetAnnotation`, `BorderStyle`, `BorderEffect`, `AppearanceDict`, `AppearanceCharacteristics` |
| `ApprLabs\Pdf\Core\Action\` | `Action` (abstract), `AdditionalActions`, `GoToAction`, `GoToRAction`, `GoToEAction`, `GoToDPAction`, `URIAction`, `JavaScriptAction`, `NamedAction`, `LaunchAction`, `ThreadAction`, `SoundAction`, `MovieAction`, `HideAction`, `SubmitFormAction`, `ResetFormAction`, `ImportDataAction`, `SetOCGStateAction`, `RenditionAction`, `TransAction`, `GoTo3DViewAction`, `RichMediaExecuteAction` |
| `ApprLabs\Pdf\Core\Graphics\ColorSpace\` | `ColorSpace` (abstract), `DeviceRGB`, `DeviceCMYK`, `DeviceGray`, `CalGray`, `CalRGB`, `Lab`, `ICCBased`, `Indexed`, `Pattern`, `Separation`, `DeviceN` |
| `ApprLabs\Pdf\Core\Graphics\XObject\` | `ImageXObject`, `FormXObject`, `PostScriptXObject` |
| `ApprLabs\Pdf\Core\Graphics\Function\` | `Func` (abstract), `FunctionType0`, `FunctionType2`, `FunctionType3`, `FunctionType4` |
| `ApprLabs\Pdf\Core\Graphics\Shading\` | `Shading` (abstract), `MeshShading` (abstract), `ShadingType1`..`ShadingType7` |
| `ApprLabs\Pdf\Core\Graphics\Pattern\` | `TilingPattern`, `ShadingPattern` |
| `ApprLabs\Pdf\Core\Graphics\` | `ExtGState`, `SoftMask` |
| `ApprLabs\Pdf\Core\Interactive\Form\` | `AcroForm`, `Field` (abstract), `TextField`, `ButtonField`, `ChoiceField`, `SignatureField`, `SigFieldLock`, `SeedValueDictionary` |
| `ApprLabs\Pdf\Core\Interactive\Signature\` | `SignatureValue`, `DocTimeStamp`, `SignatureReference`, `TransformParams` (abstract), `DocMDPTransformParams`, `FieldMDPTransformParams`, `UR3TransformParams`, `IdentityTransformParams`, `Pkcs7Signer`, `TsaClient` |
| `ApprLabs\Pdf\Core\Security\` | `EncryptDictionary`, `CryptFilter`, `PublicKeyRecipient`, `PdfEncryptor` (Standard + Public-Key handlers) |
| `ApprLabs\Pdf\Core\Content\` | `ContentStream`, `Resources` |
| `ApprLabs\Pdf\Core\File\` | `PdfFileWriter` (byte-level PDF emitter: header, xref, trailer, signature patching), `ObjectRegistry`, `CrossReferenceTable`, `TrailerDictionary` |

**`packages/font-parser/src/`** — PSR-4 root `ApprLabs\FontParser\`:

| Namespace | Classes |
|---|---|
| `ApprLabs\FontParser\` | `TrueTypeParser`, `TrueTypeData`, `OpenTypeParser`, `OpenTypeData`, `TrueTypeSubsetter`, `CffParser`, `CffData`, `CffSubsetter`, `KerningParser`, `GsubParser`, `TextShaper`, `WoffParser` |

**`packages/pdf/writer/src/`** — PSR-4 root `ApprLabs\Pdf\Writer\`:

| Namespace | Classes |
|---|---|
| `ApprLabs\Pdf\Writer\` | `Pdf` (high-level cursor-based builder — no PDF knowledge required), `PdfWriter` (ergonomic object-model facade), `Theme`, `TextStyle`, `PageSize` (enum), `Alignment` (enum) |

The writer package has two distinct layers:

1. **`Pdf`** — the top-level API. Stateful cursor, default font, theme.
   Methods: `setFont`, `setTheme`, `addPage`, `newPage`, `addText`,
   `addHeading`, `addSpacer`, `addRule`, `addImage`, `save`, `toBytes`,
   `writeTo`. Handles word wrap via `StandardFontMetrics` + `WinAnsiTable`,
   auto-pagination when content overflows the margin, automatic standard-
   font registration, and 14 standard fonts only. Reach for `Pdf::writer()`
   to drop to the lower layer when you need custom fonts or precise
   graphics state.
2. **`PdfWriter`** — ergonomic builder for the underlying object model.
   Methods: `addPage`, `addFont`, `addContentStream`, `addImage`,
   `setOutline`, `setPageLabels`, `setNamedDestinations`, `register`,
   `setSigner`, `setInfo`, `generate`, `toBytes`, `writeTo`, `save`.
   Requires knowledge of fonts, content-stream operators, and resource
   names — but gives full object-model access.

Both layers ultimately delegate to `ApprLabs\Pdf\Core\File\PdfFileWriter`
for byte emission.

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

// Digital signing — patches /ByteRange and /Contents at save time
setSigner(SignatureValue $sv, Pkcs7Signer $signer, int $placeholderBytes = 8192): void
setTsaClient(TsaClient $tsaClient): void
setTimestamper(SignatureValue $docTimeStamp, TsaClient $tsaClient, int $placeholderBytes = 16384): void

// Output
generate(): string
save(string $path): void
```

`PdfWriter::generate()` delegates to `ApprLabs\Pdf\Core\File\PdfFileWriter::generate()`, which uses an array-of-chunks + `implode()` approach (not string concatenation) for O(N) performance. `Core\File\CrossReferenceTable` builds 20-byte-per-entry xref entries, and `Core\File\TrailerDictionary` emits the typed `/Size /Root /Info /ID /Prev /Encrypt` trailer.

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
- Default PDF version is `1.7` (configurable via `PdfVersion` enum)

### Version Gating

The library tracks which PDF version each feature requires and auto-bumps the document version when needed.

**Core types** (`packages/pdf/core/src/`):
- `PdfVersion` — backed string enum (`V1_0`..`V2_0`) with `isAtLeast()`, `max()`, `fromString()`
- `#[RequiresPdfVersion(PdfVersion::VX_Y)]` — PHP attribute on classes (feature introduced in version X.Y) or properties (field added in later version)
- `#[DeprecatedPdfFeature(since: 'X.Y', replacement: '...')]` — marks deprecated features
- `PdfVersionAware` interface — for objects whose version depends on runtime state (e.g., `StructElem` checks `StandardStructureType` for 2.0 types)
- `VersionRequirementResolver` — reads attributes via reflection (cached), walks class hierarchy
- `VersionRequirementException` — thrown in strict mode when version is too low

**Writer behavior** (`PdfFileWriter`, `IncrementalWriter`):
- **Auto-bump (default):** Registering a feature that requires version X automatically bumps the document version to X. Warnings are collected in `getVersionWarnings()`.
- **Strict mode:** `setStrictVersionMode(true)` throws `VersionRequirementException` instead of bumping.
- **Deprecation handler:** `setDeprecationHandler(Closure)` gets called when deprecated features are registered.
- **Catalog sync:** `PdfFileWriter::generate()` sets `Catalog::$version` for versions > 1.4 per ISO 32000 §7.2.2.
- **Encryption:** `PdfEncryptor::getMinimumPdfVersion()` returns `V1_4` (RC4), `V1_6` (AES-128), or `V2_0` (AES-256).
- **IncrementalWriter:** `wasVersionBumped()` indicates callers should update the Catalog `/Version`.

**Reader** (`PdfReader`):
- `getPdfVersion()` — typed version from `%PDF-X.Y` header
- `getEffectiveVersion()` — `max(header, catalog /Version)`
- `validateVersion()` — returns warnings for structural features (xref streams, encryption, OCProperties, Collection, DPartRoot, DSS, AF) that don't match the declared version

**Toolkit passthrough:** All 11 toolkit classes expose `getVersionWarnings()` after `toBytes()`/`save()`.

**Coverage:** 130 class/property-level annotations spanning PDF 1.1–2.0, 7 deprecated features, plus `StructElem` runtime checks for PDF 2.0 structure types.

When adding new PDF spec features, add `#[RequiresPdfVersion]` to the class or property with the correct minimum version.

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
| `Content/KerningContentStreamTest.php` | Unit: showUnicodeTextKerned TJ/Tj output, sign convention, scaling, empty strings |
| `Content/LigatureContentStreamTest.php` | Unit: showUnicodeTextShaped with ligatures, kerning, combined, empty strings |
| `Action/ActionTest.php` | Unit: GoToAction, URIAction, JavaScriptAction, NamedAction |
| `Action/GoToRActionTest.php` | Unit: GoToRAction required fields, destinations, newWindow |
| `Action/ActionSubtypesTest.php` | Unit: Launch, Thread, Sound, Movie, Hide, SubmitForm, ResetForm, ImportData, SetOCGState, Rendition, Trans, GoTo3DView, RichMediaExecute, GoToE, GoToDP |
| `FileSpec/FileSpecTest.php` | Unit: FileSpec, EmbeddedFile, EmbeddedFileParams |
| `Graphics/FunctionTest.php` | Unit: FunctionType0/2/3/4 serialization and stream framing |
| `Graphics/ColorSpaceTest.php` | Unit: CalGray, CalRGB, Lab, ICCBased, Indexed, Pattern, Separation, DeviceN |
| `Graphics/ShadingTest.php` | Unit: ShadingType1..7 (dict + mesh stream types) |
| `Graphics/PatternTest.php` | Unit: TilingPattern, ShadingPattern |
| `Document/AccessibilityHelpersTest.php` | Unit: RoleMap, ClassMap, StructAttribute, StructTreeRoot typed maps |
| `Multimedia/MultimediaTest.php` | Unit: Sound, Movie, MediaRendition, SelectorRendition, MediaClipData, MediaClipSection, MediaPlayParams, MediaScreenParams, Navigator |
| `ThreeD/ThreeDTest.php` | Unit: ThreeDStream (U3D/PRC), ThreeDView, ThreeDBackground, ThreeDRenderMode, ThreeDLightingScheme, ThreeDCrossSection |
| `Interactive/FormTest.php` | Unit: AcroForm, Field subclasses |
| `Interactive/SignatureTest.php` | Unit: SignatureValue, SignatureReference, DocMDP/FieldMDP/UR3 transform params, SignatureField wiring |
| `Interactive/DocTimeStampTest.php` | Unit: DocTimeStamp /Type, /SubFilter ETSI.RFC3161, byte-range/contents patching |
| `Interactive/TsaClientTest.php` | Unit: TsaClient ASN.1 DER request building (SHA-256/384/512), response parsing (granted/rejected/missing), certReq flag, invalid URL handling |
| `Interactive/Pkcs7SignerTest.php` | Unit: Pkcs7Signer signing + DER extraction + `openssl cms -verify` round-trip |
| `Security/EncryptDictionaryTest.php` | Unit: EncryptDictionary (Standard V2/R3, AES-256 R6, public-key handler), CryptFilter, PublicKeyRecipient |
| `Security/PublicKeyEncryptorTest.php` | End-to-end: public-key AES-128 + AES-256 encryption, round-trip with certificate, multiple recipients, wrong-cert rejection |
| `Annotation/MarkupAnnotationTest.php` | Unit: markup base fields on Text/Highlight; MarkupAnnotation hierarchy |
| `Document/CatalogPhaseATest.php` | Unit: Catalog /DSS /Extensions /AF /DPartRoot; Page /AF /OutputIntents /DPart; BoxColorInfo + BoxStyle |
| `Document/NamesRequirementAndOCTest.php` | Unit: NamesDictionary, Requirement(Handler), OCUsage, OCConfig, DPartRoot, DPart, LinearizationParameters, HintStream, MetadataStream, StandardStructureType, StructAttribute helpers |
| `Document/MarkupAnnotationsIntegrationTest.php` | End-to-end: sticky note + popup + highlight reply + callout FreeText exercising /T /Subj /CreationDate /IRT /RT /IT /Popup |
| `Graphics/XObjectPhaseATest.php` | Unit: FormXObject /Metadata /StructParent /OC /AF /LastModified /Ref; ImageXObject /Decode /StructParent /Matte + filter chain |
| `Font/CMapAndFontFileTest.php` | Unit: CMapStream, Type1FontFile, TrueTypeFontFile, CFFFontFile |
| `Filter/DecodeParamsTest.php` | Unit: FlateDecodeParams, CCITTFaxDecodeParams, JBIG2DecodeParams, DCTDecodeParams, JPXDecodeParams, CryptFilterDecodeParams |
| `Multimedia/MediaCriteriaTest.php` | Unit: MediaCriteria fields |
| `ThreeD/ThreeDNodeAndMeasureTest.php` | Unit: ThreeDNode + all six ThreeDMeasure subtypes |
| `Action/AdditionalActionsTest.php` | Unit: AdditionalActions trigger helpers (catalog/page/field/annotation) |
| `Interactive/SignatureHelpersTest.php` | Unit: SigFieldLock, SeedValueDictionary, IdentityTransformParams |
| `PdfDateTest.php` | Unit: PdfDate::fromDateTime / parse round-trip |
| `File/ObjectRegistryAndXrefTest.php` | Unit: ObjectRegistry numbering + CrossReferenceTable xref format (moved from writer package) |
| `File/TrailerDictionaryTest.php` | Unit: typed TrailerDictionary (classic + incremental trailer with /Prev + /Encrypt) |
| `File/PdfFileWriterTest.php` | Unit: PdfFileWriter engine — header emission, `setCatalog`/`register`/`setInfo`, trailer structure, `save()` round-trip |
| `Font/FontTest.php` | Unit: all font types; includes TrueTypeFont::fromFile() tests |
| `Font/FontSubtypeTest.php` | Unit: Type3Font, MMType1Font, CIDFontType0Font, CIDFontType2Font |
| `Font/CIDSystemInfoTest.php` | Unit: CIDSystemInfo fields, used in CIDFont |
| `Document/CrossReferenceStreamTest.php` | Unit: CrossReferenceStream fields and binary entry packing |
| `Document/ObjectStreamTest.php` | Unit: ObjectStream packing, N/First computation, /Extends |
| `File/ObjectStreamOutputTest.php` | End-to-end: PdfFileWriter useObjectStreams, smaller output, PdfReader round-trip, xref type 2 entries |
| `File/IncrementalWriterExtendedTest.php` | End-to-end: incremental encryption, xref stream mode, deletion with xref stream |
| `Interactive/FdfXfdfTest.php` | Unit: FDF/XFDF write + read round-trip, escaping, empty handling |
| `Interactive/AppearanceGeneratorExtendedTest.php` | Unit: multi-line text field, password masking, comb text field, signature field appearance |
| `Graphics/ExtGStateTest.php` | Unit: ExtGState new fields (BG, BG2, UCR, UCR2, TR, TR2, HT, UseBlackPtComp, HTO), SoftMask, PostScriptXObject |
| `Document/ExtGStateIntegrationTest.php` | End-to-end: PDF with ExtGState using alpha, blend mode, UseBlackPtComp |
| `Document/EmbeddedFontsTest.php` | End-to-end: TrueType font embedding with FontDescriptor, ToUnicode, Widths |
| `Document/AnnotationSubtypesTest.php` | End-to-end: all 20 new annotation subtypes across 3 pages |
| `Document/DocumentFeaturesTest.php` | End-to-end: OutputIntent, page boxes, named destinations, OCG, tagged PDF, embedded TrueType |
| `Document/Type3FontIntegrationTest.php` | End-to-end: Type 3 font with inline glyph procs and custom Encoding |
| `Document/XRefStreamIntegrationTest.php` | End-to-end: hand-rolled PDF 1.5 with CrossReferenceStream + ObjectStream |
| `Document/GraphicsPipelineIntegrationTest.php` | End-to-end: axial+radial shadings, tiling pattern, FileAttachment, SubmitFormAction |
| `Document/MultimediaAndThreeDIntegrationTest.php` | End-to-end: ScreenAnnotation + MediaRendition + MediaClipData + RenditionAction, ThreeDAnnotation + ThreeDStream/View/Background/RenderMode/LightingScheme |
| `Document/SignatureFieldIntegrationTest.php` | End-to-end: SignatureField + SignatureValue placeholder + SignatureReference + DocMDPTransformParams + AcroForm SigFlags + Catalog Perms |
| `Document/SignedPdfIntegrationTest.php` | End-to-end: actually-signed PDF via `PdfWriter::setSigner()` + self-signed cert; verified with `openssl cms -verify` when the CLI is available |
| `Document/FormAppearancesIntegrationTest.php` | End-to-end: text field, checkbox, choice field with generated AppearanceGenerator appearances, NeedAppearances=false |
| `Document/OpenTypeFontIntegrationTest.php` | End-to-end: OpenType CFF font parsed via OpenTypeParser, embedded as Type0/CIDFontType0 with CFFFontFile, hex-encoded GID text |
| `Interactive/AppearanceGeneratorTest.php` | Unit: textField, checkbox, radioButton, pushButton, choiceField appearance generation, AppearanceDict builders |
| `Security/PdfEncryptorTest.php` | Unit: RC4-128 and AES-128 encrypt/decrypt round-trip, password auth, permissions |
| `File/StreamCompressionTest.php` | Unit: FlateDecode auto-compression on write, compressed PDF readability |
| `File/PdfHydratorTest.php` | Unit: type registry, key mapping, PdfNumber/PdfArray/PdfBoolean coercion |
| `File/XRefStreamOutputTest.php` | Unit: CrossReferenceStream output, round-trip via PdfReader, compression |
| `File/IncrementalWriterTest.php` | Unit: incremental update append, /Prev chain, modified objects, stacked updates, compression |
| `File/IncrementalWriterVersionTest.php` | Unit: IncrementalWriter version from reader, auto-bump sets wasVersionBumped, strict mode throws |
| `File/VersionRequirementResolverTest.php` | Unit: class-level, property-level, inheritance, effective requirement, StructElem 2.0 types, deprecation, caching |
| `File/VersionGatingTest.php` | Integration: auto-bump on register, strict mode, deprecation warnings, Catalog /Version sync, xref stream bump |
| `File/EncryptionVersionGatingTest.php` | Unit: PdfEncryptor version requirements (RC4→1.4, AES-128→1.6, AES-256→2.0), auto-bump |
| `PdfVersionTest.php` | Unit: PdfVersion enum comparisons, max(), fromString(), all cases |
| `Graphics/HalftoneTest.php` | Unit: HalftoneType 1/5/6/10/16 serialization, field output, stream framing |

### `writer` package tests (`packages/pdf/writer/tests/`)

| Test file | What it tests |
|---|---|
| `Writer/WriterTest.php` | Unit: PdfWriter ergonomic API (addPage, addFont, addContentStream, save, namedDestinations, …) |
| `Writer/PageSizeAndAlignmentTest.php` | Unit: PageSize dimensions (Letter, Legal, A3/A4/A5, Tabloid) + Alignment enum |
| `Writer/ThemeTest.php` | Unit: Theme defaults + withFont/withColor/withMargin immutability + heading(1..6) lookup |
| `Writer/PdfTest.php` | Unit: high-level Pdf — auto page creation, addText, addHeading, explicit pagination, long-text auto-pagination, addSpacer, addRule, alignment, bold/italic font resolution, custom theme, save/toBytes/writeTo output modes, escape hatch |
| `Writer/PdfIntegrationTest.php` | End-to-end: generates `docs/sample-pdfs/high_level_pdf.pdf` — headings, body, rules, alignment overrides, auto-pagination across 3+ pages, exercises all three output modes |
| `Writer/XmpMetadataTest.php` | Unit: PdfWriter::setMetadata() with XmpPacket, metadata stream in output, round-trip via PdfReader |
| `Writer/UnicodeFontTest.php` | Unit: Type0FontFactory composite font stack, addCompositeFont, hex-encoded text, per-page fonts |
| `Writer/KerningIntegrationTest.php` | End-to-end: OpenType font with kerning, showUnicodeTextKerned, TJ operator verification |

### `reader` package tests (`packages/pdf/reader/tests/`)

| Test file | What it tests |
|---|---|
| `Integration/ReadSamplePdfsTest.php` | End-to-end: reads all sample PDFs, verifies page counts, annotations, bookmarks, form fields, xref streams |
| `Integration/RoundTripTest.php` | Hydration: typed Catalog/Page/Pages via PdfHydrator, serialization round-trip |
| `Integration/TextExtractionTest.php` | ContentStreamParser + TextExtractor: text from simple/complex/embedded-font PDFs, Unicode em-dash, extractAllText, Form XObject text extraction (Do operator), nested XObjects |
| `Integration/ErrorToleranceTest.php` | Lenient mode: displaced headers, expanded startxref search, getParseWarnings() |
| `Integration/VersionValidationTest.php` | Unit: getPdfVersion, getEffectiveVersion, validateVersion, catalog version sync |

### `toolkit` package tests (`packages/pdf/toolkit/tests/`)

| Test file | What it tests |
|---|---|
| `TextExtractorTest.php` | Unit: text extraction per page, search, pattern search, iterable results |
| `PageSelectorTest.php` | Unit: all/pages/range/even/odd selection, matches, resolve |
| `MetadataEditorTest.php` | Unit: read/write Info dict fields, round-trip, custom fields, no-info PDFs |
| `FormFillerTest.php` | Unit: getFieldNames, fill text/checkbox/choice, fillMany, round-trip |
| `PdfStamperTest.php` | Unit: text stamps, watermarks, page numbers, opacity, PageSelector, headers/footers |
| `PageTransformerTest.php` | Unit: rotate, setCropBox/MediaBox, PageSelector targeting |
| `AnnotationFlattenerTest.php` | Unit: flattenAll removes annotations, appearance merged into content |
| `TextRedactorTest.php` | Unit: area redaction, text search redaction, custom color, apply() gating |
| `PageSlicerTest.php` | Unit: keep/remove/reorder/reverse/split pages, round-trip content verification |
| `PdfMergerTest.php` | Unit: merge multiple PDFs, page selection, source/page counts, round-trip |
| `PdfEncryptTest.php` | Unit: AES-128/256 encrypt, decrypt round-trip, isEncrypted query |
| `BookmarkEditorTest.php` | Unit: setBookmarks, addBookmark, hasBookmarks, getBookmarks, removeBookmarks |
| `PageLabelerTest.php` | Unit: setLabels with styles, setRomanNumerals, setArabic, removeLabels |

### Support package tests

| Package | Test file | What it tests |
|---|---|---|
| `color` | `ColorTest.php` | RGB/CMYK/Gray constructors and conversions |
| `crypt` | `CryptTest.php` | AES-128/256 and RC4 encryption/decryption |
| `crypt` | `PublicKeyEncryptionTest.php` | PKCS#7 envelope create/open, file key derivation, wrong-key rejection |
| `encoding` | `EncodingTest.php` | WinAnsi/MacRoman tables, Glyph List |
| `filters` | `FilterTest.php` | FlateDecode, ASCII85, ASCIIHex, RunLength encode/decode |
| `font-metrics` | `StandardFontMetricsTest.php` | Width lookups for all 14 standard fonts |
| `font-parser` | `TrueTypeParserTest.php` | TrueType binary parsing: metrics, widths, cmap, name tables |
| `font-parser` | `TrueTypeSubsetterTest.php` | TrueType subsetting: glyph reduction, valid header, re-parsability |
| `font-parser` | `OpenTypeParserTest.php` | OpenType CFF parsing: metrics, CFF bytes, cmap, glyph widths, sfVersion rejection |
| `font-parser` | `CffParserTest.php` | CFF binary parsing: Header, INDEX, DICT, Charset, CharStrings |
| `font-parser` | `CffSubsetterTest.php` | CFF subsetting: glyph reduction, valid header, re-parsability, subroutine preservation |
| `font-parser` | `KerningParserTest.php` | GPOS PairPos + legacy kern table parsing: kern pairs, negative values, empty fonts |
| `font-parser` | `GsubParserTest.php` | GSUB ligature parsing, TextShaper ligature application, longest-match-first |
| `font-parser` | `WoffParserTest.php` | WOFF decompression, round-trip TTF→WOFF→TTF, signature detection |
| `font-parser` | `VerticalWritingTest.php` | Identity-V encoding, vhea/vmtx vertical metrics parsing |
| `encoding` | `PredefinedCMapTest.php` | CJK predefined CMap names, CIDSystemInfo lookup, isPredefined |
| `image-metadata` | `IccProfileTest.php` | ICC profile extraction from JPEG APP2 and PNG iCCP chunks |
| `geometry` | `RectangleTest.php`, `MatrixTest.php`, `ExtendedGeometryTest.php` | Rectangle, Matrix transforms, BezierCurve, PageSize |
| `image-metadata` | `ImageParserTest.php` | JPEG/PNG/GIF/TIFF/WebP header parsing |
| `xmp` | `XmpTest.php` | XMP metadata packet read/write |

## Benchmarks

`benchmarks/GeneratePdfBench.php` — wall-clock time via phpbench; compares phpdftk against TCPDF, FPDF, mPDF, Dompdf at 1, 5, 10, 50, and 100 pages. Includes `benchPhpdftk10PagesWithBookmarksAndTransitions()` exercising Outline + OutlineItem + TransitionDict, `benchPhpdftk10PagesWithAnnotations()` exercising annotation subtypes, `benchPhpdftk10PagesWithEmbeddedFont()` exercising TrueType font embedding, `benchPhpdftk10PagesWithDocumentStructure()` exercising OutputIntent, named destinations, page labels, tagged PDF structure, `benchPhpdftk10PagesWithType3Font()` exercising a custom Type 3 font with inline glyph procs, `benchPhpdftkXRefAndObjectStreams()` exercising CrossReferenceStream + ObjectStream assembly, `benchPhpdftk10PagesWithShadingsAndPatterns()` exercising axial shading + shading pattern + tiling pattern, `benchPhpdftk10PagesWithMultimediaAnd3D()` exercising ScreenAnnotation + MediaRendition per page plus a shared ThreeDStream/ThreeDView driving a 3D annotation, `benchPhpdftk10PagesWithSignatureField()` exercising a SignatureField + SignatureValue placeholder + DocMDP reference + AcroForm wiring, `benchPhpdftk10PagesSigned()` exercising the full `PdfWriter::setSigner()` pipeline (placeholder → byte range → PKCS#7 sign → /Contents patch), `benchPhpdftk10PagesWithMarkupAnnotations()` exercising the full markup annotation field set (/T, /Subj, /CreationDate, /IRT, /RT, /Popup) via threaded sticky-note / highlight replies per page, `benchPhpdftk10PagesWithCffSubsetting()` exercising CFF font subsetting with a small character set, and `benchPhpdftk10PagesWithKernedText()` exercising kerned text rendering via `showUnicodeTextKerned()` with GPOS kern pairs.

`benchmarks/MemoryBench.php` — peak memory (`memory_get_peak_usage(true)`); compares phpdftk against FPDF and TCPDF at the same page counts.

`benchmarks/GeneratePdfBench.php` also includes `benchPhpdftk10PagesWithFormAppearances()` exercising `AppearanceGenerator` for text fields and checkboxes per page, `benchPhpdftk10PagesWithOpenTypeCff()` exercising OpenType CFF font embedding via `OpenTypeParser` + `addOpenTypeFont()` (requires macOS OTF font), `benchPhpdftk10PagesWithPublicKeyEncryption()` exercising public-key (certificate-based) AES-128 encryption via `PdfEncryptor::publicKeyAes128()` with PKCS#7 envelope generation, and `benchPhpdftkTsaRequestBuildAndParse()` exercising RFC 3161 `TsaClient` ASN.1 DER request building and response parsing (100 iterations, no network).

`benchmarks/ReadPdfBench.php` also includes `benchPhpdftkTextExtractionWithFormXObjects()` exercising text extraction from a 10-page PDF where each page invokes a Form XObject containing text via the `Do` operator.

`benchmarks/ReadPdfBench.php` — PDF reader/parser performance; compares phpdftk reader against smalot/pdfparser and setasign/fpdi at 1, 10, and 100 pages. Uses FPDF-generated reference PDFs (`docs/sample-pdfs/bench_*.pdf`) with classic xref tables to ensure all three readers can parse them. Each benchmark parses the file, extracts structure (catalog, info, version), and iterates all pages.

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
