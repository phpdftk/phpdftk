---
title: Version Coverage
description: Complete PDF version support from 1.0 to 2.0 — 172 version-gated features with automatic enforcement.
---

phpdftk covers the **entire PDF specification history** from version 1.0 through 2.0, with 172 features precisely annotated to their introduction version. The library automatically manages version requirements — use any feature and the document version bumps to match, or enable strict mode to catch version mismatches at development time.

## How it works

```php
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Pdf\Core\Document\OCG;

$writer = new PdfWriter();

// OCG requires PDF 1.5 — version auto-bumps
$ocg = new OCG();
$ocg->name = 'Layer 1';
$writer->register($ocg);

// Output will be PDF 1.5 (not the default 1.7)
$writer->save('layered.pdf');
```

In **strict mode**, the writer throws instead of bumping:

```php
$writer->getFileWriter()->setStrictVersionMode(true);
// Now registering a 1.5 feature when targeting 1.4 throws VersionRequirementException
```

## Coverage by version

172 classes and properties are annotated across PDF 1.1 through 2.0.

### PDF 1.0 (base)

All unannotated features default to 1.0: core primitives, Catalog, PageTree, Page, Type1 fonts, content stream operators, DeviceRGB/CMYK/Gray.

### PDF 1.1 — 9 features

Bookmarks, page transitions, CIE-based color, Multiple Master fonts.

| Feature | What it adds |
|---|---|
| CalGray, CalRGB, Lab | CIE-based color spaces |
| Outline + OutlineItem | Document bookmarks |
| TransitionDict | Page transition effects |
| MMType1Font | Multiple Master fonts |
| LaunchAction, ThreadAction | Application launch, article threads |

### PDF 1.2 — 13 features

Interactive forms, composite fonts, patterns.

| Feature | What it adds |
|---|---|
| AcroForm | Interactive form support |
| WidgetAnnotation | Form field widgets |
| Type0Font, CIDFont | Composite (CJK) fonts |
| TilingPattern, Pattern | Fill patterns |
| HideAction, SubmitFormAction, ResetFormAction, ImportDataAction | Form actions |
| AppearanceDict, AppearanceCharacteristics | Annotation appearance control |
| AdditionalActions | Event triggers |

### PDF 1.3 — 25 features

ICC color, shading/functions, tagged PDF, digital signatures, text markup annotations.

| Feature | What it adds |
|---|---|
| ICCBased, Separation | ICC profiles, spot colors |
| ShadingPattern, Shading (types 1–7) | Gradient fills |
| Func (types 0–4) | Mathematical functions |
| StructTreeRoot, StructElem | Tagged PDF / accessibility |
| SignatureField, SignatureValue, SignatureReference | Digital signatures |
| JavaScriptAction, PageLabel | JavaScript, page numbering |
| 14 annotation types | FreeText, Line, Square, Circle, Polygon, PolyLine, Highlight, Underline, Squiggly, StrikeOut, Stamp, Ink, Popup, FileAttachment |

### PDF 1.4 — 13 features

Transparency, metadata, markup annotations.

| Feature | What it adds |
|---|---|
| SoftMask, GroupAttributes | Transparency model |
| MetadataStream | XMP metadata streams |
| MarkupAnnotation (base) | Reply threading, creation dates |
| OutputIntent | Color management for print |
| ExtGState transparency | Blend mode, alpha, soft mask properties |

### PDF 1.5 — 30 features

Optional content (layers), multimedia, compressed object/xref streams.

| Feature | What it adds |
|---|---|
| OCG, OCMD, OCPropertiesDict | Layers / optional content |
| CrossReferenceStream, ObjectStream | Compressed PDF internals |
| CryptFilter | Per-stream encryption |
| MediaRendition, SelectorRendition | Rich media playback |
| MediaClipData/Section, MediaPlayParams, MediaScreenParams | Media configuration |
| ScreenAnnotation, CaretAnnotation | Screen, caret markers |
| PolygonAnnotation, PolyLineAnnotation, RedactAnnotation | Geometry, redaction |
| RenditionAction, SetOCGStateAction, TransAction | Multimedia/layer actions |
| SigFieldLock | Signature field locking |

### PDF 1.6 — 17 features

3D content, DeviceN color, document timestamps.

| Feature | What it adds |
|---|---|
| ThreeDStream + 5 sub-objects | U3D and PRC 3D content |
| ThreeDAnnotation | 3D viewport in page |
| DeviceN | Multi-component color (e.g., Hexachrome) |
| DocTimeStamp | RFC 3161 document-level timestamps |
| WatermarkAnnotation | Watermarks |
| CFFFontFile | OpenType CFF font embedding |
| GoToEAction, GoTo3DViewAction | Embedded file / 3D navigation |
| CIDFontType0Font | CID CFF font type |

### PDF 1.7 — 6 features

Portable collections, document requirements.

| Feature | What it adds |
|---|---|
| Collection, CollectionSchema, CollectionItem | Portable file collections |
| Requirement, RequirementHandler | Document requirement declarations |
| Catalog::$extensions | Developer extensions dictionary |

### PDF 2.0 — 17 features

Document parts, rich media, Document Security Store.

| Feature | What it adds |
|---|---|
| DPartRoot, DPart | Variable data printing structure |
| DSS | Document Security Store (LTV signatures) |
| ProjectionAnnotation, RichMediaAnnotation | Projection, rich media |
| GoToDPAction, RichMediaExecuteAction | Document part / rich media actions |
| Associated files (Catalog, Page, FormXObject, FileSpec) | File attachment relationships |
| ViewerPreferences::$enforce | Enforced viewer settings |
| SeedValueDictionary fields | Signature appearance/lock control |

## Deprecated features

7 features are marked deprecated. With strict deprecation enabled, using them at their removal version throws `DeprecatedFeatureException`.

| Feature | Deprecated | Replacement |
|---|---|---|
| Movie | 2.0 | RichMediaAnnotation |
| MovieAction | 2.0 | RichMediaExecuteAction |
| MovieAnnotation | 2.0 | ScreenAnnotation |
| Sound | 2.0 | MediaRendition |
| SoundAction | 2.0 | RenditionAction |
| SoundAnnotation | 2.0 | RichMediaAnnotation |
| PostScriptXObject | 1.7.1 | — |

## Runtime version checks

Some features determine their version at runtime:

- **StructElem** — bumps to 2.0 when using PDF 2.0 structure types (DocumentFragment, Aside, Title, THead, TBody, TFoot, FENote, Artifact)
- **PdfEncryptor** — RC4 requires 1.4, AES-128 requires 1.6, AES-256 requires 2.0
- **PdfFileWriter** — auto-bumps to 1.5 for xref streams, syncs Catalog /Version for versions > 1.4
