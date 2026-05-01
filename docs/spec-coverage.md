# PDF Specification Coverage

Tracks implementation status of ISO 32000-2:2020 (PDF 2.0) objects against `phpdftk/phpdftk`.

**Legend:** ✓ Implemented · ~ Partial · ✗ Missing

---

## Document Structure

### Catalog (`/Type /Catalog`)

| Field                | Status | Notes                                          |
|----------------------|--------|------------------------------------------------|
| `/Pages`             | ✓      |                                                |
| `/Version`           | ✓      |                                                |
| `/Outlines`          | ✓      | `Outline` + `OutlineItem` classes implemented  |
| `/Names`             | ✓      | Reference stored; `NameTree` class available   |
| `/Dests`             | ✓      | Ref stored; `Destination` class available      |
| `/ViewerPreferences` | ✓      |                                                |
| `/PageLayout`        | ✓      |                                                |
| `/PageMode`          | ✓      |                                                |
| `/OpenAction`        | ✓      |                                                |
| `/AcroForm`          | ✓      |                                                |
| `/Metadata`          | ✓      | Reference stored; XMP stream via `phpdftk/xmp` |
| `/MarkInfo`          | ✓      | `MarkInfo` class on `Catalog::$markInfo`       |
| `/Lang`              | ✓      |                                                |
| `/AA`                | ✓      | Reference stored                               |
| `/URI`               | ✓      | Inline dict                                    |
| `/SpiderInfo`        | ✓      | Reference stored                               |
| `/OutputIntents`     | ✓      | Array of OutputIntent refs                     |
| `/PieceInfo`         | ✓      | Inline dict                                    |
| `/OCProperties`      | ✓      | Reference stored                               |
| `/Perms`             | ✓      | Inline dict                                    |
| `/Legal`             | ✓      | Inline dict                                    |
| `/Requirements`      | ✓      | Array stored                                   |
| `/Collection`        | ✓      | Reference stored                               |
| `/NeedsRendering`    | ✓      |                                                |
| `/DSS`               | ✓      | `DSS` class + `DssBuilder` + `LtvSigner` for PAdES LTV — certs, OCSPs, CRLs, VRI entries |
| `/Extensions`        | ✓      | Developer extensions dict                      |
| `/AF`                | ✓      | Associated files array                         |
| `/DPartRoot`         | ✓      | `DPartRoot` reference (PDF 2.0)                |

### Page Tree (`/Type /Pages`)

| Field                   | Status | Notes                   |
|-------------------------|--------|-------------------------|
| `/Type`                 | ✓      |                         |
| `/Parent`               | ✓      |                         |
| `/Kids`                 | ✓      |                         |
| `/Count`                | ✓      |                         |
| `/MediaBox`             | ✓      |                         |
| `/Resources`            | ✓      |                         |
| `/Rotate`               | ✓      |                         |
| `/CropBox`              | ✓      | Inheritable box         |
| `/BleedBox`             | ✓      | Inheritable box         |
| `/TrimBox`              | ✓      | Inheritable box         |
| `/ArtBox`               | ✓      | Inheritable box         |
| `/BoxColorInfo`         | ✓      | Inline dict             |
| `/Group`                | ✓      | Reference stored        |
| `/Thumb`                | ✓      | Reference stored        |
| `/B`                    | ✓      | Article bead refs       |
| `/Dur`                  | ✓      |                         |
| `/Trans`                | ✓      | TransitionDict or Serializable |
| `/Annots`               | ✓      | Inheritable annotations |
| `/AA`                   | ✓      | Reference stored        |
| `/Metadata`             | ✓      | XMP stream reference    |
| `/PieceInfo`            | ✓      | Inline dict             |
| `/StructParents`        | ✓      |                         |
| `/ID`                   | ✓      |                         |
| `/PZ`                   | ✓      |                         |
| `/SeparationInfo`       | ✓      | Inline dict             |
| `/Tabs`                 | ✓      |                         |
| `/TemplateInstantiated` | ✓      |                         |
| `/PresSteps`            | ✓      | Reference stored        |
| `/UserUnit`             | ✓      |                         |
| `/VP`                   | ✓      | Viewport array          |

### Page (`/Type /Page`)

| Field                   | Status | Notes                                            |
|-------------------------|--------|--------------------------------------------------|
| `/Type`                 | ✓      |                                                  |
| `/Parent`               | ✓      |                                                  |
| `/Resources`            | ✓      |                                                  |
| `/MediaBox`             | ✓      |                                                  |
| `/CropBox`              | ✓      |                                                  |
| `/BleedBox`             | ✓      |                                                  |
| `/TrimBox`              | ✓      |                                                  |
| `/ArtBox`               | ✓      |                                                  |
| `/Contents`             | ✓      |                                                  |
| `/Rotate`               | ✓      |                                                  |
| `/Annots`               | ✓      |                                                  |
| `/Group`                | ✓      | Reference stored                                 |
| `/Thumb`                | ✓      | Reference stored                                 |
| `/UserUnit`             | ✓      |                                                  |
| `/StructParents`        | ✓      |                                                  |
| `/Trans`                | ✓      | `TransitionDict` class; S, D, Dm, M, Di, SS, B  |
| `/Dur`                  | ✓      |                                                  |
| `/BoxColorInfo`         | ✓      | Typed `BoxColorInfo` + `BoxStyle` (§14.11.2)     |
| `/B`                    | ✓      | Article beads                                    |
| `/AF`                   | ✓      | Associated files                                 |
| `/OutputIntents`        | ✓      | Page-level output intents                        |
| `/DPart`                | ✓      | Document part reference (PDF 2.0)                |
| `/AA`                   | ✓      | Reference stored                                 |
| `/Metadata`             | ✓      | XMP stream reference on page                     |
| `/PieceInfo`            | ✓      | Reference stored                                 |
| `/ID`                   | ✓      |                                                  |
| `/PZ`                   | ✓      |                                                  |
| `/SeparationInfo`       | ✓      | Inline dict                                      |
| `/Tabs`                 | ✓      |                                                  |
| `/TemplateInstantiated` | ✓      |                                                  |
| `/PresSteps`            | ✓      | Reference stored                                 |
| `/VP`                   | ✓      | Viewport array                                   |

### Info Dictionary

| Field           | Status | Notes |
|-----------------|--------|-------|
| `/Title`        | ✓      |       |
| `/Author`       | ✓      |       |
| `/Subject`      | ✓      |       |
| `/Keywords`     | ✓      |       |
| `/Creator`      | ✓      |       |
| `/Producer`     | ✓      |       |
| `/CreationDate` | ✓      |       |
| `/ModDate`      | ✓      |       |
| `/Trapped`      | ✓      |       |

### Viewer Preferences

| Field                    | Status | Notes |
|--------------------------|--------|-------|
| `/HideToolbar`           | ✓      |       |
| `/HideMenubar`           | ✓      |       |
| `/HideWindowUI`          | ✓      |       |
| `/FitWindow`             | ✓      |       |
| `/CenterWindow`          | ✓      |       |
| `/DisplayDocTitle`       | ✓      |       |
| `/NonFullScreenPageMode` | ✓      |       |
| `/Direction`             | ✓      |       |
| `/ViewArea`              | ✓      |       |
| `/ViewClip`              | ✓      |       |
| `/PrintArea`             | ✓      |       |
| `/PrintClip`             | ✓      |       |
| `/PrintScaling`          | ✓      |       |
| `/Duplex`                | ✓      |       |
| `/PickTrayByPDFSize`     | ✓      |       |
| `/PrintPageRange`        | ✓      |       |
| `/NumCopies`             | ✓      |       |

### Missing Document Structure Objects

| Object                                         | Status | Notes                                                                  |
|------------------------------------------------|--------|------------------------------------------------------------------------|
| `Outline` (`/Type /Outlines`)                  | ✓      | First, Last, Count; `PdfWriter::setOutline()` wires to Catalog         |
| `OutlineItem`                                  | ✓      | Title, Parent, Prev, Next, First, Last, Count, Dest, A, C, F           |
| `PageLabel` (`/Type /PageLabel`)               | ✓      | S, P, St; `PdfWriter::setPageLabels()` builds inline number tree       |
| Named destinations                             | ✓      | `PdfWriter::setNamedDestinations()` with NameTree                      |
| Explicit destinations                          | ✓      | `Destination` class with static factory methods for all 8 types        |
| `OutputIntent` (`/Type /OutputIntent`)         | ✓      | S, OutputConditionIdentifier, RegistryName, Info, DestOutputProfile    |
| `OCG` (`/Type /OCG`)                           | ✓      | Name, Intent, Usage; extends PdfObject                                 |
| `OCMD` (`/Type /OCMD`)                         | ✓      | OCGs, P, VE; extends PdfObject                                        |
| `OCProperties`                                 | ✓      | OCGs, D, Configs; extends PdfObject                                    |
| `TransitionDict` (`/Type /Trans`)              | ✓      | S, D, Dm, M, Di, SS, B; assigned to `Page::$transition`               |
| `GroupAttributes`                              | ✓      | S, CS, I, K; implements Serializable                                   |
| `NameTree`                                     | ✓      | Kids, Names, Limits; extends PdfObject                                 |
| `NumberTree`                                   | ✓      | Kids, Nums, Limits; extends PdfObject                                  |
| `MarkInfo` dict                                | ✓      | Marked, UserProperties, Suspects; assigned to `Catalog::$markInfo`     |
| `Collection` (`/Type /Collection`)             | ✓      | Schema, D, View, Sort; extends PdfObject                               |
| `CollectionItem` (`/Type /CollectionItem`)     | ✓      | Field values dict; extends PdfObject                                   |
| `CollectionSchema` (`/Type /CollectionSchema`) | ✓      | Field definitions; extends PdfObject                                   |
| `Thread` (`/Type /Thread`)                     | ✓      | I, F; extends PdfObject                                                |
| `Bead` (`/Type /Bead`)                         | ✓      | T, N, V, P, R; extends PdfObject                                       |
| `StructTreeRoot` (`/Type /StructTreeRoot`)     | ✓      | K, IDTree, ParentTree, RoleMap, ClassMap; extends PdfObject            |
| `StructElem` (`/Type /StructElem`)             | ✓      | S, P, ID, Pg, K, A, C, R, T, Lang, Alt, E, ActualText; extends PdfObject |
| `ObjectRef` (`/Type /OBJR`)                    | ✓      | Pg, Obj; extends PdfObject                                             |
| Cross-reference stream (`/Type /XRef`)         | ✓      | Size, Index, Prev, W, Root, Info, ID; binary entry packing             |
| Object stream (`/Type /ObjStm`)                | ✓      | N, First, Extends; packs compressed indirect objects                   |

---

## Fonts

### Font (`/Type /Font`) — Common Fields

| Field             | Status | Notes                                            |
|-------------------|--------|--------------------------------------------------|
| `/Type`           | ✓      |                                                  |
| `/Subtype`        | ✓      |                                                  |
| `/BaseFont`       | ✓      |                                                  |
| `/FirstChar`      | ✓      | Auto-populated from AFM data                     |
| `/LastChar`       | ✓      | Auto-populated from AFM data                     |
| `/Widths`         | ✓      | Auto-populated from AFM data                     |
| `/FontDescriptor` | ✓      |                                                  |
| `/Encoding`       | ✓      |                                                  |
| `/ToUnicode`      | ✓      | CMap stream generated from TrueType cmap table |

### Font Subtypes

| Subtype         | Class              | Status | Notes                                                          |
|-----------------|--------------------|--------|----------------------------------------------------------------|
| `/Type1`        | `Type1Font`        | ✓      | Includes standard 14 with AFM widths                           |
| `/TrueType`     | `TrueTypeFont`     | ✓      | Full font program embedding via `/FontFile2`                   |
| `/Type0`        | `Type0Font`        | ✓      | Composite font                                                 |
| `/CIDFontType0` | `CIDFontType0Font` | ✓      | Type 1/CFF descendant of Type 0 (enforced subclass)            |
| `/CIDFontType2` | `CIDFontType2Font` | ✓      | TrueType descendant of Type 0; /CIDToGIDMap supported          |
| `/MMType1`      | `MMType1Font`      | ✓      | Multiple Master; encodes spaces in instance name as underscore |
| `/Type3`        | `Type3Font`        | ✓      | FontBBox, FontMatrix, CharProcs, Encoding, Resources           |

### FontDescriptor (`/Type /FontDescriptor`)

| Field           | Status | Notes                                       |
|-----------------|--------|---------------------------------------------|
| `/FontName`     | ✓      |                                             |
| `/FontFamily`   | ✓      |                                             |
| `/FontStretch`  | ✓      |                                             |
| `/FontWeight`   | ✓      |                                             |
| `/Flags`        | ✓      |                                             |
| `/FontBBox`     | ✓      |                                             |
| `/ItalicAngle`  | ✓      |                                             |
| `/Ascent`       | ✓      |                                             |
| `/Descent`      | ✓      |                                             |
| `/Leading`      | ✓      |                                             |
| `/CapHeight`    | ✓      |                                             |
| `/XHeight`      | ✓      |                                             |
| `/StemV`        | ✓      |                                             |
| `/StemH`        | ✓      |                                             |
| `/AvgWidth`     | ✓      |                                             |
| `/MaxWidth`     | ✓      |                                             |
| `/MissingWidth` | ✓      |                                             |
| `/FontFile`     | ✓      | Type 1 font program embedding via `Type1Font::fromFile()` + `Type1FontFile` |
| `/FontFile2`    | ✓      | TrueType font program embedding via `TrueTypeFont::fromFile()` |
| `/FontFile3`    | ✓      | CFF font program embedding via `CFFFontFile`                    |
| `/CharSet`      | ✓      |                                             |

### Encoding (`/Type /Encoding`)

| Field           | Status | Notes |
|-----------------|--------|-------|
| `/BaseEncoding` | ✓      |       |
| `/Differences`  | ✓      |       |

### Missing Font Objects

| Object                        | Status | Notes                                                                   |
|-------------------------------|--------|-------------------------------------------------------------------------|
| `CIDSystemInfo` dict          | ✓      | Registry, Ordering, Supplement; typed on `CIDFont::$cidSystemInfo`      |
| `CMap` stream (`/Type /CMap`) | ✓      | CMapName, CIDSystemInfo, WMode; CMapStream class                        |
| ToUnicode CMap stream         | ✓      | Generated from TrueType cmap table; WinAnsi byte → Unicode mapping      |
| TrueType font embedding       | ✓      | Full font program embedded via `/FontFile2`; `TrueTypeFont::fromFile()` |
| Font subsetting               | ✓      | TrueTypeSubsetter implemented                                           |
| OpenType font support         | ✓      | OpenTypeParser + CFFFontFile via `/FontFile3`                            |
| Type 1 font parsing           | ✓      | `Type1Parser` — PFB/PFA formats, `Type1Font::fromFile()` factory        |
| WOFF 1.0 decompression        | ✓      | `WoffParser` — WOFF→sfnt conversion                                     |
| WOFF 2.0 decompression        | ✓      | `Woff2Parser` — Brotli decompression, table transforms, sfnt reconstruction |
| Variable font detection       | ✓      | `TrueTypeParser::parseFvar()` — axes, named instances, `isVariableFont` |

---

## Annotations (`/Type /Annot`)

### Common Base Fields

| Field           | Status | Notes                     |
|-----------------|--------|---------------------------|
| `/Type`         | ✓      |                           |
| `/Subtype`      | ✓      |                           |
| `/Rect`         | ✓      |                           |
| `/Contents`     | ✓      |                           |
| `/P`            | ✓      | Page reference            |
| `/NM`           | ✓      | Annotation name           |
| `/M`            | ✓      | Modification date         |
| `/F`            | ✓      | Flags                     |
| `/AP`           | ✓      | Appearance dict reference |
| `/AS`           | ✓      | Appearance state          |
| `/Border`       | ✓      |                           |
| `/C`            | ✓      | Color                     |
| `/StructParent` | ✓      |                           |
| `/OC`           | ✓      | Optional content          |
| `/AF`           | ✓      | Associated files          |
| `/ca`           | ✓      | Constant opacity          |
| `/BM`           | ✓      | Blend mode                |
| `/Lang`         | ✓      | Language                  |

### Markup Annotation Base Fields (§12.5.6.2 Table 170)

| Field          | Status | Notes                                                  |
|----------------|--------|--------------------------------------------------------|
| `/T`           | ✓      | Text label (author) on `MarkupAnnotation`              |
| `/Popup`       | ✓      | Linked popup annotation reference                      |
| `/CA`          | ✓      | Constant opacity override on markup                    |
| `/RC`          | ✓      | Rich content stream                                    |
| `/CreationDate`| ✓      |                                                        |
| `/IRT`         | ✓      | In-reply-to chaining                                   |
| `/Subj`        | ✓      | Short description                                      |
| `/RT`          | ✓      | Reply type (R / Group)                                 |
| `/IT`          | ✓      | Intent (e.g. FreeTextCallout, PolygonCloud, LineArrow) |
| `/ExData`      | ✓      | External data dict                                     |

All 17 markup annotation subclasses (`TextAnnotation`, `FreeTextAnnotation`,
`LineAnnotation`, `SquareAnnotation`, `CircleAnnotation`, `PolygonAnnotation`,
`PolyLineAnnotation`, `HighlightAnnotation`, `UnderlineAnnotation`,
`SquigglyAnnotation`, `StrikeOutAnnotation`, `StampAnnotation`,
`CaretAnnotation`, `InkAnnotation`, `FileAttachmentAnnotation`,
`SoundAnnotation`, `RedactAnnotation`) extend `MarkupAnnotation` and inherit
these fields.

### Annotation Subtypes

| Subtype           | Class                 | Status | Notes                                                 |
|-------------------|-----------------------|--------|-------------------------------------------------------|
| `/Text`           | `TextAnnotation`      | ✓      | Open, Name, State, StateModel                         |
| `/Link`           | `LinkAnnotation`      | ✓      | Dest, H, PA, QuadPoints, BS, A                        |
| `/FreeText`       | `FreeTextAnnotation`  | ✓      | DA, Q, RC, DS, CL, IT, BE, RD, BS, LE                 |
| `/Highlight`      | `HighlightAnnotation` | ✓      | QuadPoints                                            |
| `/Stamp`          | `StampAnnotation`     | ✓      | Name                                                  |
| `/Ink`            | `InkAnnotation`       | ✓      | InkList, BS                                           |
| `/Popup`          | `PopupAnnotation`     | ✓      | Parent, Open                                          |
| `/Widget`         | `WidgetAnnotation`    | ✓      | H, MK, A, AA, BS, Parent                              |
| `/Line`           | `LineAnnotation`           | ✓      | L, LE, IC, LL, LLE, Cap, IT, LLO, CP, Measure, CO    |
| `/Square`         | `SquareAnnotation`         | ✓      | IC, BE, RD, Measure                                   |
| `/Circle`         | `CircleAnnotation`         | ✓      | IC, BE, RD, Measure                                   |
| `/Polygon`        | `PolygonAnnotation`        | ✓      | Vertices, LE, IC, BE, IT, Measure                     |
| `/PolyLine`       | `PolyLineAnnotation`       | ✓      | Vertices, LE, IC, BE, IT, Measure                     |
| `/Underline`      | `UnderlineAnnotation`      | ✓      | QuadPoints                                            |
| `/Squiggly`       | `SquigglyAnnotation`       | ✓      | QuadPoints                                            |
| `/StrikeOut`      | `StrikeOutAnnotation`      | ✓      | QuadPoints                                            |
| `/Caret`          | `CaretAnnotation`          | ✓      | RD, Sy                                                |
| `/FileAttachment` | `FileAttachmentAnnotation` | ✓      | FS, Name                                              |
| `/Sound`          | `SoundAnnotation`          | ✓      | Sound, Name                                           |
| `/Movie`          | `MovieAnnotation`          | ✓      | T, Movie, A                                           |
| `/Screen`         | `ScreenAnnotation`         | ✓      | T, MK, A, AA                                          |
| `/PrinterMark`    | `PrinterMarkAnnotation`    | ✓      | MN                                                    |
| `/TrapNet`        | `TrapNetAnnotation`        | ✓      | LastModified, Version, AnnotStates, FontFauxing       |
| `/Watermark`      | `WatermarkAnnotation`      | ✓      | FixedPrint                                            |
| `/3D`             | `ThreeDAnnotation`         | ✓      | 3DD, 3DV, 3DA, 3DI, 3DB                               |
| `/Redact`         | `RedactAnnotation`         | ✓      | QuadPoints, IC, RO, OverlayText, Repeat, DA, Q        |
| `/Projection`     | `ProjectionAnnotation`     | ✓      |                                                       |
| `/RichMedia`      | `RichMediaAnnotation`      | ✓      | RichMediaSettings, RichMediaContent                   |

### Supporting Annotation Dictionaries

| Object                           | Status | Notes                                                |
|----------------------------------|--------|------------------------------------------------------|
| `AppearanceDict` (AP)            | ✓      | N, R, D; implements Serializable                     |
| `AppearanceCharacteristics` (MK) | ✓      | R, BC, BG, CA, RC, AC, I, RI, IX, IF, TP; implements Serializable |
| `BorderStyle` (BS)               | ✓      | W, S, D; `Annotation::$bs` accepts it directly       |
| `BorderEffect` (BE)              | ✓      | S, I; `FreeTextAnnotation::$be` accepts it directly  |

---

## Actions

| `/S` Value          | Class                    | Status | Notes                                    |
|---------------------|--------------------------|--------|------------------------------------------|
| `/GoTo`             | `GoToAction`             | ✓      | D                                        |
| `/URI`              | `URIAction`              | ✓      | URI, IsMap                               |
| `/Named`            | `NamedAction`            | ✓      | N                                        |
| `/JavaScript`       | `JavaScriptAction`       | ✓      | JS                                       |
| `/GoToR`            | `GoToRAction`            | ✓      | F, D, NewWindow                          |
| `/GoToE`            | `GoToEAction`            | ✓      | F, D, NewWindow, T                       |
| `/GoToDP`           | `GoToDPAction`           | ✓      | D, DP                                    |
| `/Launch`           | `LaunchAction`           | ✓      | F, Win, Mac, Unix, NewWindow             |
| `/Thread`           | `ThreadAction`           | ✓      | F, D, B                                  |
| `/Sound`            | `SoundAction`            | ✓      | Sound, Volume, Synchronous, Repeat, Mix  |
| `/Movie`            | `MovieAction`            | ✓      | Annotation, T, Operation                 |
| `/Hide`             | `HideAction`             | ✓      | T, H                                     |
| `/SubmitForm`       | `SubmitFormAction`       | ✓      | F, Fields, Flags                         |
| `/ResetForm`        | `ResetFormAction`        | ✓      | Fields, Flags                            |
| `/ImportData`       | `ImportDataAction`       | ✓      | F                                        |
| `/SetOCGState`      | `SetOCGStateAction`      | ✓      | State, PreserveRB                        |
| `/Rendition`        | `RenditionAction`        | ✓      | OP, R, AN, JS                            |
| `/Trans`            | `TransAction`            | ✓      | Trans                                    |
| `/GoTo3DView`       | `GoTo3DViewAction`       | ✓      | TA, V                                    |
| `/RichMediaExecute` | `RichMediaExecuteAction` | ✓      | TA, TI, CMD                              |

---

## Interactive Forms (AcroForm)

### AcroForm Dictionary

| Field              | Status | Notes              |
|--------------------|--------|--------------------|
| `/Fields`          | ✓      |                    |
| `/NeedAppearances` | ✓      |                    |
| `/SigFlags`        | ✓      |                    |
| `/CO`              | ✓      | Calculation order  |
| `/DR`              | ✓      | Default resources  |
| `/DA`              | ✓      | Default appearance |
| `/Q`               | ✓      | Justification      |
| `/XFA`             | ✓      | Reference stored   |

### Field Common Fields

| Field     | Status | Notes              |
|-----------|--------|--------------------|
| `/FT`     | ✓      |                    |
| `/Parent` | ✓      |                    |
| `/Kids`   | ✓      |                    |
| `/T`      | ✓      | Partial name       |
| `/TU`     | ✓      | User name          |
| `/TM`     | ✓      | Mapping name       |
| `/Ff`     | ✓      | Flags              |
| `/V`      | ✓      | Value              |
| `/DV`     | ✓      | Default value      |
| `/AA`     | ✓      | Additional actions |

### Field Types

| Type   | Class            | Status | Notes                                           |
|--------|------------------|--------|-------------------------------------------------|
| `/Btn` | `ButtonField`    | ✓      | H, MK, Opt; pushbutton/checkbox/radio via Ff    |
| `/Tx`  | `TextField`      | ✓      | MaxLen, Q; multiline/password/comb via Ff       |
| `/Ch`  | `ChoiceField`    | ✓      | Opt, TI, I; combo/edit/sort via Ff              |
| `/Sig` | `SignatureField` | ✓      | SigFlags, Lock, SV; /V accepts `SignatureValue` |

### Signature Objects

| Object                              | Status | Notes                                                                       |
|-------------------------------------|--------|-----------------------------------------------------------------------------|
| Signature value dict (`/Type /Sig`) | ✓      | `SignatureValue` — placeholder for real signing; all Table 258 entries      |
| `SignatureReference` dict           | ✓      | `SignatureReference` — TransformMethod, TransformParams, Data, DigestMethod |
| `DocMDP` transform params           | ✓      | `DocMDPTransformParams` — P, V                                              |
| `FieldMDP` transform params         | ✓      | `FieldMDPTransformParams` — Action, Fields, V                               |
| `UR3` transform params              | ✓      | `UR3TransformParams` — Document, Msg, V, Annots, Form, Signature, EF, P     |
| `Perms` dict (in Catalog)           | ✓      | `Catalog::$perms` inline PdfDictionary — DocMDP, UR3, Legal                 |

---

## Graphics

### ExtGState (`/Type /ExtGState`)

| Field             | Status | Notes                         |
|-------------------|--------|-------------------------------|
| `/LW`             | ✓      | Line width                    |
| `/LC`             | ✓      | Line cap                      |
| `/LJ`             | ✓      | Line join                     |
| `/ML`             | ✓      | Miter limit                   |
| `/D`              | ✓      | Dash pattern                  |
| `/RI`             | ✓      | Rendering intent              |
| `/OP`             | ✓      | Overprint stroke              |
| `/op`             | ✓      | Overprint fill                |
| `/OPM`            | ✓      | Overprint mode                |
| `/Font`           | ✓      |                               |
| `/FL`             | ✓      | Flatness                      |
| `/SM`             | ✓      | Smoothness                    |
| `/SA`             | ✓      | Stroke adjustment             |
| `/BM`             | ✓      | Blend mode                    |
| `/SMask`          | ✓      | Soft mask reference           |
| `/CA`             | ✓      | Stroke alpha                  |
| `/ca`             | ✓      | Fill alpha                    |
| `/AIS`            | ✓      | Alpha is shape                |
| `/TK`             | ✓      | Text knockout                 |
| `/BG`             | ✓      | Black generation function     |
| `/BG2`            | ✓      | Black generation (PDF 1.3+)   |
| `/UCR`            | ✓      | Undercolor removal            |
| `/UCR2`           | ✓      | Undercolor removal (PDF 1.3+) |
| `/TR`             | ✓      | Transfer function             |
| `/TR2`            | ✓      | Transfer function (PDF 1.3+)  |
| `/HT`             | ✓      | Halftone                      |
| `/UseBlackPtComp` | ✓      | Black point compensation      |
| `/HTO`            | ✓      | Halftone origin               |

### Soft Mask Dictionary

| Field   | Status | Notes                      |
|---------|--------|----------------------------|
| `/Type` | ✓      | `SoftMask` class           |
| `/S`    | ✓      | Alpha or Luminosity        |
| `/G`    | ✓      | Transparency group XObject |
| `/BC`   | ✓      | Backdrop color             |
| `/TR`   | ✓      | Transfer function          |

### Color Spaces

| Color Space   | Status | Notes                                                           |
|---------------|--------|-----------------------------------------------------------------|
| `/DeviceGray` | ✓      | `DeviceGray`                                                    |
| `/DeviceRGB`  | ✓      | `DeviceRGB`                                                     |
| `/DeviceCMYK` | ✓      | `DeviceCMYK`                                                    |
| `/CalGray`    | ✓      | `CalGray` — WhitePoint, BlackPoint, Gamma                       |
| `/CalRGB`     | ✓      | `CalRGB` — WhitePoint, BlackPoint, Gamma, Matrix                |
| `/Lab`        | ✓      | `Lab` — WhitePoint, BlackPoint, Range                           |
| `/ICCBased`   | ✓      | `ICCBased` — wraps an ICC profile stream reference              |
| `/Indexed`    | ✓      | `Indexed` — base, hival, lookup                                 |
| `/Pattern`    | ✓      | `Pattern` — bare name or [Pattern underlyingSpace]              |
| `/Separation` | ✓      | `Separation` — colorant, alternate space, tint transform        |
| `/DeviceN`    | ✓      | `DeviceN` — names, alternate space, tint transform, attributes  |

### Pattern (`/Type /Pattern`)

| Type                       | Status | Notes                                                                          |
|----------------------------|--------|--------------------------------------------------------------------------------|
| `/PatternType 1` (Tiling)  | ✓      | `TilingPattern` — PaintType, TilingType, BBox, XStep, YStep, Resources, Matrix |
| `/PatternType 2` (Shading) | ✓      | `ShadingPattern` — Shading, Matrix, ExtGState                                  |

### Shading (`/Type /Shading` or stream)

| Type                                    | Status | Notes                                                                      |
|-----------------------------------------|--------|----------------------------------------------------------------------------|
| `/ShadingType 1` (Function-based)       | ✓      | `ShadingType1` — ColorSpace, Domain, Matrix, Function                      |
| `/ShadingType 2` (Axial)                | ✓      | `ShadingType2` — Coords, Domain, Extend, Function (linear gradient)        |
| `/ShadingType 3` (Radial)               | ✓      | `ShadingType3` — Coords, Domain, Extend, Function (radial gradient)        |
| `/ShadingType 4` (Free-form Gouraud)    | ✓      | `ShadingType4` stream — BitsPerCoordinate/Component/Flag, Decode, Function |
| `/ShadingType 5` (Lattice Gouraud)      | ✓      | `ShadingType5` stream — VerticesPerRow                                     |
| `/ShadingType 6` (Coons patch)          | ✓      | `ShadingType6` stream                                                      |
| `/ShadingType 7` (Tensor-product patch) | ✓      | `ShadingType7` stream                                                      |

### XObject (`/Type /XObject`)

| Subtype  | Class          | Status | Notes                                                                                                                     |
|----------|----------------|--------|---------------------------------------------------------------------------------------------------------------------------|
| `/Image` | `ImageXObject` | ✓      | Width, Height, ColorSpace, BitsPerComponent, Filter, DecodeParms, Intent, ImageMask, Mask, SMask, Interpolate, Alternates |
| `/Form`  | `FormXObject`  | ✓      | BBox, Matrix, Resources                                                                                                   |
| `/PS`    | `PostScriptXObject` | ✓      | Deprecated since PDF 1.7.1                                                                                                |

### Functions

| Type                            | Status | Notes                                                                              |
|---------------------------------|--------|------------------------------------------------------------------------------------|
| `/FunctionType 0` (Sampled)     | ✓      | `FunctionType0` stream — Domain, Range, Size, BitsPerSample, Order, Encode, Decode |
| `/FunctionType 2` (Exponential) | ✓      | `FunctionType2` — Domain, Range, C0, C1, N                                         |
| `/FunctionType 3` (Stitching)   | ✓      | `FunctionType3` — Domain, Functions, Bounds, Encode                                |
| `/FunctionType 4` (PostScript)  | ✓      | `FunctionType4` stream — PS operators in stream body                               |

> Functions are a prerequisite for Shading types 1–3 and Separation/DeviceN color spaces.

### Halftone (`/Type /Halftone`)

| Type                                       | Status | Notes                          |
|--------------------------------------------|--------|--------------------------------|
| `/HalftoneType 1` (dictionary)             | ✓      | Frequency, Angle, SpotFunction |
| `/HalftoneType 5` (multidotted)            | ✓      | Dict of component halftones    |
| `/HalftoneType 6` (threshold array stream) | ✓      |                                |
| `/HalftoneType 10` (threshold)             | ✓      |                                |
| `/HalftoneType 16` (threshold)             | ✓      |                                |

---

## Content Stream Operators

| Operator       | Category      | Status | Notes                                     |
|----------------|---------------|--------|-------------------------------------------|
| `m`            | Path          | ✓      |                                           |
| `l`            | Path          | ✓      |                                           |
| `c`            | Path          | ✓      |                                           |
| `v`            | Path          | ✓      |                                           |
| `y`            | Path          | ✓      |                                           |
| `h`            | Path          | ✓      |                                           |
| `re`           | Path          | ✓      |                                           |
| `S`            | Paint         | ✓      |                                           |
| `s`            | Paint         | ✓      |                                           |
| `f`            | Paint         | ✓      |                                           |
| `F`            | Paint         | ✓      |                                           |
| `f*`           | Paint         | ✓      |                                           |
| `B`            | Paint         | ✓      |                                           |
| `B*`           | Paint         | ✓      |                                           |
| `b`            | Paint         | ✓      |                                           |
| `b*`           | Paint         | ✓      |                                           |
| `n`            | Paint         | ✓      |                                           |
| `W`            | Clip          | ✓      |                                           |
| `W*`           | Clip          | ✓      |                                           |
| `q`            | State         | ✓      |                                           |
| `Q`            | State         | ✓      |                                           |
| `cm`           | State         | ✓      |                                           |
| `w`            | State         | ✓      |                                           |
| `J`            | State         | ✓      |                                           |
| `j`            | State         | ✓      |                                           |
| `M`            | State         | ✓      |                                           |
| `d`            | State         | ✓      |                                           |
| `ri`           | State         | ✓      |                                           |
| `i`            | State         | ✓      |                                           |
| `gs`           | State         | ✓      |                                           |
| `CS`           | Color         | ✓      |                                           |
| `cs`           | Color         | ✓      |                                           |
| `SC`           | Color         | ✓      |                                           |
| `SCN`          | Color         | ✓      |                                           |
| `sc`           | Color         | ✓      |                                           |
| `scn`          | Color         | ✓      |                                           |
| `G`            | Color         | ✓      |                                           |
| `g`            | Color         | ✓      |                                           |
| `RG`           | Color         | ✓      |                                           |
| `rg`           | Color         | ✓      |                                           |
| `K`            | Color         | ✓      |                                           |
| `k`            | Color         | ✓      |                                           |
| `Tc`           | Text          | ✓      |                                           |
| `Tw`           | Text          | ✓      |                                           |
| `Tz`           | Text          | ✓      |                                           |
| `TL`           | Text          | ✓      |                                           |
| `Tf`           | Text          | ✓      |                                           |
| `Tr`           | Text          | ✓      |                                           |
| `Ts`           | Text          | ✓      |                                           |
| `Td`           | Text          | ✓      |                                           |
| `TD`           | Text          | ✓      |                                           |
| `Tm`           | Text          | ✓      |                                           |
| `T*`           | Text          | ✓      |                                           |
| `Tj`           | Text          | ✓      |                                           |
| `TJ`           | Text          | ✓      |                                           |
| `Do`           | XObject       | ✓      |                                           |
| `BI`/`ID`/`EI` | Image         | ✓      |                                           |
| `'`            | Text          | ✓      | `moveToNextLineAndShowText()`             |
| `"`            | Text          | ✓      | `setSpacingMoveAndShowText()`             |
| `sh`           | Shading       | ✓      | `paintShading()`                          |
| `d0`           | Type3         | ✓      | `setGlyphWidth()`                         |
| `d1`           | Type3         | ✓      | `setGlyphWidthAndBoundingBox()`           |
| `MP`           | MarkedContent | ✓      | `markedContentPoint()`                    |
| `DP`           | MarkedContent | ✓      | `markedContentPointWithProperties()`      |
| `BMC`          | MarkedContent | ✓      | `beginMarkedContent()`                    |
| `BDC`          | MarkedContent | ✓      | `beginMarkedContentWithProperties()`      |
| `EMC`          | MarkedContent | ✓      | `endMarkedContent()`                      |
| `BX`           | Compat        | ✓      | `beginCompatibility()`                    |
| `EX`           | Compat        | ✓      | `endCompatibility()`                      |

---

## Multimedia

| Object                                           | Status | Notes                                           |
|--------------------------------------------------|--------|-------------------------------------------------|
| `Sound` (`/Type /Sound`)                         | ✓      | `Sound` stream — R, C, B, E, CO, CP             |
| `Movie` dict                                     | ✓      | `Movie` — F, Aspect, Rotate, Poster             |
| `Rendition` (`/Type /Rendition`)                 | ✓      | `MediaRendition` (MR), `SelectorRendition` (SR) |
| `MediaClip` (`/Type /MediaClip`)                 | ✓      | `MediaClipData` (MCD), `MediaClipSection` (MCS) |
| `MediaPlayParams` (`/Type /MediaPlayParams`)     | ✓      | `MediaPlayParams` — MH, BE, PL                  |
| `MediaScreenParams` (`/Type /MediaScreenParams`) | ✓      | `MediaScreenParams` — MH, BE                    |
| `Navigator` (`/Type /Navigator`)                 | ✓      | `Navigator` — NA, NR, Duration                  |

---

## File Specifications

| Object                                        | Status | Notes                                                             |
|-----------------------------------------------|--------|-------------------------------------------------------------------|
| `FileSpec` (`/Type /Filespec`)                | ✓      | `FileSpec` — FS, F, UF, DOS, Mac, Unix, ID, V, EF, RF, Desc, CI   |
| `EmbeddedFile` stream (`/Type /EmbeddedFile`) | ✓      | `EmbeddedFile` — Subtype, Params                                  |
| `EmbeddedFileParams` dict                     | ✓      | `EmbeddedFileParams` — Size, CreationDate, ModDate, Mac, CheckSum |

---

## Encryption

| Object / Field                         | Status | Notes                                                             |
|----------------------------------------|--------|-------------------------------------------------------------------|
| `EncryptDictionary` (`/Type /Encrypt`) | ✓      | `EncryptDictionary` — object model only                           |
| Standard handler fields                | ✓      | R, O, U, P, EncryptMetadata, OE, UE, Perms on `EncryptDictionary` |
| Crypt filter dict                      | ✓      | `CryptFilter` — Type, CFM, AuthEvent, Length                      |
| Public-key handler                     | ✓      | `PublicKeyRecipient` + /Recipients array                          |
| RC4 cipher                             | ✓      | `phpdftk/crypt` — `Rc4Cipher`                                     |
| AES-128/256 cipher                     | ✓      | `phpdftk/crypt` — `AesCipher`                                     |
| PDF key derivation                     | ✓      | `phpdftk/crypt` — `PdfKeyDerivation`                              |

> Fully wired: `PdfWriter::setEncryption()` registers the `/Encrypt`
> dictionary, encrypts all strings/streams per-object during `generate()`,
> and emits `/Encrypt` in the trailer automatically. Supports RC4-40,
> RC4-128, AES-128, AES-256, and public-key (certificate-based) encryption.

---

## Digital Signatures

| Object                              | Status | Notes                                                                       |
|-------------------------------------|--------|-----------------------------------------------------------------------------|
| Signature value dict (`/Type /Sig`) | ✓      | `SignatureValue` — all Table 258 entries                                    |
| `SignatureReference` dict           | ✓      | `SignatureReference` — TransformMethod, TransformParams, Data, DigestMethod |
| `DocMDP` transform params           | ✓      | `DocMDPTransformParams`                                                     |
| `FieldMDP` transform params         | ✓      | `FieldMDPTransformParams`                                                   |
| `UR3` transform params              | ✓      | `UR3TransformParams`                                                        |
| PKCS#7 / CAdES signing              | ✓      | `Pkcs7Signer` + `PdfWriter::setSigner()` — ByteRange + /Contents patching   |
| Timestamp authority                 | ✓      | `DocTimeStamp` + `TsaClient` — full RFC 3161 TSA HTTP client with SHA-256/384/512, wired into signing pipeline via `setTimestamper()` |

---

## 3D

| Object                          | Status | Notes                                                         |
|---------------------------------|--------|---------------------------------------------------------------|
| `3D` stream (`/Type /3D`)       | ✓      | `ThreeDStream` — Subtype (U3D or PRC), VA, DV, AN, ColorSpace |
| `3DView` dict (`/Type /3DView`) | ✓      | `ThreeDView` — XN, IN, MS, C2W, CO, P, O, BG, RM, LS, SA      |
| `3DBackground` dict             | ✓      | `ThreeDBackground` — CS, C, EA                                |
| `3DRenderMode` dict             | ✓      | `ThreeDRenderMode` — Subtype, AC, FC, Opacity, CV             |
| `3DLightingScheme` dict         | ✓      | `ThreeDLightingScheme` — Subtype                              |
| `3DCrossSection` dict           | ✓      | `ThreeDCrossSection` — C, O, PC, PO, IV, IC, ST               |

---

## Accessibility / Tagged PDF

| Object / Feature                           | Status | Notes                                                           |
|--------------------------------------------|--------|-----------------------------------------------------------------|
| `StructTreeRoot` (`/Type /StructTreeRoot`) | ✓      | K, IDTree, ParentTree, ParentTreeNextKey, RoleMap, ClassMap     |
| `StructElem` (`/Type /StructElem`)         | ✓      | S, P, ID, Pg, K, A, C, R, T, Lang, Alt, E, ActualText           |
| `ObjectRef` (`/Type /OBJR`)                | ✓      | Pg, Obj                                                         |
| Marked content operators                   | ✓      | `BMC`, `BDC`, `EMC`, `MP`, `DP` implemented in `ContentStream`  |
| `RoleMap` dict                             | ✓      | `RoleMap` — typed wrapper mapping custom types to standard ones |
| `ClassMap` dict                            | ✓      | `ClassMap` — maps class names to `StructAttribute` entries      |
| Attribute objects                          | ✓      | `StructAttribute` — /O owner + arbitrary entries                |

---

## Character Encodings

| Encoding              | Status | Notes                                                                              |
|-----------------------|--------|------------------------------------------------------------------------------------|
| WinAnsiEncoding       | ✓      | `WinAnsiTable` — standard Windows encoding for most modern PDFs                   |
| MacRomanEncoding      | ✓      | `MacRomanTable` — Mac OS encoding for older PDFs                                   |
| StandardEncoding      | ✓      | `StandardEncodingTable` — default Type 1 font encoding per Table D.1               |
| MacExpertEncoding     | ✓      | `MacExpertEncodingTable` — expert/small-caps Type 1 fonts per Table D.4            |
| PDFDocEncoding        | ✓      | `PdfDocEncodingTable` — text strings in Info, bookmarks, annotations; auto-detects UTF-16BE/UTF-8/fallback |
| Adobe Glyph List      | ✓      | `GlyphList` — glyph name ↔ Unicode mapping                                        |
| CJK predefined CMaps  | ✓      | 16 CJK CMaps (UniGB-UCS2-H, UniJIS-UCS2-H, etc.)                                  |

---

## Stream Filters (Codecs)

| Filter             | Encode | Decode | Notes                                                                    |
|--------------------|--------|--------|--------------------------------------------------------------------------|
| `FlateDecode`      | ✓      | ✓      | `FlateFilter` — zlib inflate/deflate                                     |
| `ASCII85Decode`    | ✓      | ✓      | `Ascii85Filter` — base-85 encoding                                      |
| `ASCIIHexDecode`   | ✓      | ✓      | `AsciiHexFilter` — hex encoding                                         |
| `RunLengthDecode`  | ✓      | ✓      | `RunLengthFilter` — PackBits-style RLE                                   |
| `LZWDecode`        | ✓      | ✓      | `LzwFilter` — LZW compression and decompression with EarlyChange support |
| `CCITTFaxDecode`   | ✓      | ✓      | `CCITTFaxFilter` — Group 3 (1D) and Group 4 (2D) Huffman fax encoding and decoding |
| `JBIG2Decode`      | ✓      | ✓      | `Jbig2Filter` — MMR generic region encoding, segment parsing, `jbig2dec` fallback  |
| `DCTDecode`        | —      | —      | Pass-through (JPEG data usable as-is)                                    |
| `JPXDecode`        | —      | —      | Pass-through (JPEG 2000 data usable as-is)                               |
| Predictor          | ✓      | ✓      | `PredictorFilter` — PNG/TIFF predictor encoding and decoding for Flate/LZW |

---

## Reader Capabilities

| Feature                          | Status | Notes                                                         |
|----------------------------------|--------|---------------------------------------------------------------|
| Classic xref table parsing       | ✓      | Standard and lenient modes                                    |
| Xref stream parsing (PDF 1.5+)  | ✓      | Binary entry unpacking with width clamping                    |
| Object stream unpacking          | ✓      | Decompresses and parses packed objects                        |
| Incremental update chains        | ✓      | `/Prev` chain with loop detection                             |
| Hybrid xref support              | ✓      | Scans both classic xref and xref streams                      |
| Linearized PDF detection         | ✓      | Scans up to 50 objects for linearization dict                 |
| Encryption (read)                | ✓      | RC4-40/128, AES-128/256, public-key                           |
| Text extraction                  | ✓      | All encoding fallbacks (Standard/MacRoman/MacExpert/WinAnsi)  |
| Positioned text extraction       | ✓      | Per-span x/y/width/height via full text state machine (CTM, Tm, Tc, Tw, Tz, Ts, font widths) |
| Error tolerance                  | ✓      | Missing startxref, corrupted xref, truncated PDF, trailing garbage, missing %%EOF |
| Object recovery                  | ✓      | Scans for endobj, tolerates missing endobj, partial dicts     |
| Stream recovery                  | ✓      | Sliding-window endstream scan with 64MB safety limit          |

---

## Writer Capabilities

| Feature                    | Status | Notes                                                                   |
|----------------------------|--------|-------------------------------------------------------------------------|
| Classic xref output        | ✓      | 20-byte-per-entry xref tables                                          |
| Xref stream output         | ✓      | `CrossReferenceStream` binary packing                                   |
| Object stream output       | ✓      | `ObjectStream` compressed indirect objects                              |
| Incremental updates        | ✓      | `IncrementalWriter` with `/Prev` chain                                  |
| Linearized output          | ✓      | Two-pass write with padded linearization dict, hint stream, first-page partitioning |
| Stream compression         | ✓      | Auto FlateDecode compression                                            |
| Version auto-bump          | ✓      | 172 `#[RequiresPdfVersion]` annotations, strict mode, ceiling mode      |
| Deprecation enforcement    | ✓      | `removedIn` on `#[DeprecatedPdfFeature]`, strict deprecation mode       |

---

## Conformance Profiles

### Supported Standards

| Standard | Profile Enum | Levels | ISO Spec | PDF Version | Constraints |
|----------|-------------|--------|----------|-------------|-------------|
| PDF/A | `PdfAProfile` | A1a, A1b, A2a, A2b, A2u, A3a, A3b, A3u, A4, A4e, A4f | ISO 19005 | 1.4–2.0 | 7–10 per level |
| PDF/UA | `PdfUaProfile` | UA1, UA2 | ISO 14289 | 1.7–2.0 | 6 |
| PDF/X | `PdfXProfile` | X-1a:2003, X-3:2003, X-4, X-5g, X-5pg, X-5n | ISO 15930 | 1.3–1.6 | 6–8 per level |
| PDF/VT | `PdfVtProfile` | VT1, VT2, VT2s | ISO 16612 | 2.0 | 7 |
| PDF/E | `PdfEProfile` | E1 | ISO 24517-1 | 1.6 | 6 |
| PDF/R | `PdfRProfile` | R1 | ISO 23504-1 | 2.0 | 5 |
| Factur-X | `ZugferdProfile` | MINIMUM, BASIC_WL, BASIC, EN16931, EXTENDED, XRECHNUNG | ZUGFeRD/Factur-X | 1.7 | 9 (PDF/A-3b + 2) |
| PDF/mail | `PdfMailProfile` | mail-1 | ISO 23053-2 | 2.0 | 6 |

### Conformance Constraint Matrix

| Constraint | A | UA | X | X-5 | VT | E | R | ZUGFeRD | mail |
|---|---|---|---|---|---|---|---|---|---|
| FontEmbedding | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | ✓ | ✓ |
| Encryption | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Metadata | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| OutputIntent | ✓ | | ✓ | ✓ | ✓ | | | ✓ | |
| ColorSpace | ✓ | | | | | | | ✓ | |
| Action | ✓ | | | | | | | ✓ | ✓ |
| EmbeddedFile | ✓ | | | | | | | ✓ | |
| Transparency | A-1 | | X-1a/X-3 | | | | | A-1 | |
| Filter | A-1 | | | | | | | | |
| TaggedStructure | Level A | ✓ | | | | | | Level A | |
| DisplayDocTitle | | ✓ | | | | | | | |
| TabOrder | | ✓ | | | | | | | |
| Annotation | | ✓ | | | | | | | |
| TrimBox | | | ✓ | ✓ | ✓ | | | | |
| Trapped | | | ✓ | ✓ | ✓ | | | | |
| DPartRoot | | | | | ✓ | | | | |
| ThreeDContent | | | | | | ✓ | | | |
| PdfEAction | | | | | | ✓ | | | |
| PdfEColorSpace | | | | | | ✓ | | | |
| RasterContent | | | | | | | ✓ | | |
| PdfRAction | | | | | | | ✓ | | |
| PdfRFont | | | | | | | ✓ | | |
| ReferenceXObject | | | | ✓ | | | | | |
| ZugferdXmp | | | | | | | | ✓ | |
| ZugferdInvoice | | | | | | | | ✓ | |
| Form | | | | | | | | | ✓ |
| Multimedia | | | | | | | | | ✓ |

---

## Coverage Summary

| Area                       | Implemented | Total | %    |
|----------------------------|-------------|-------|------|
| Catalog fields             | 28          | 28    | 100% |
| PageTree fields            | 33          | 33    | 100% |
| Page fields                | 32          | 32    | 100% |
| Info fields                | 9           | 9     | 100% |
| ViewerPreferences fields   | 18          | 18    | 100% |
| Document structure objects | 24          | 24    | 100% |
| Font subtypes              | 7           | 7     | 100% |
| FontDescriptor fields      | 19          | 19    | 100% |
| Annotation base fields     | 18          | 18    | 100% |
| Markup annotation fields   | 10          | 10    | 100% |
| Annotation subtypes        | 26          | 26    | 100% |
| Supporting annot dicts     | 4           | 4     | 100% |
| Actions                    | 20          | 20    | 100% |
| AcroForm fields            | 8           | 8     | 100% |
| Field types                | 4           | 4     | 100% |
| ExtGState fields           | 28          | 28    | 100% |
| Soft Mask fields           | 5           | 5     | 100% |
| Color spaces               | 11          | 11    | 100% |
| XObject subtypes           | 3           | 3     | 100% |
| Function types             | 4           | 4     | 100% |
| Pattern types              | 2           | 2     | 100% |
| Shading types              | 7           | 7     | 100% |
| Content stream operators   | 69          | 69    | 100% |
| Encryption                 | 8           | 8     | 100% |
| Digital signatures         | 7           | 7     | 100% |
| Multimedia                 | 7           | 7     | 100% |
| File specifications        | 3           | 3     | 100% |
| Accessibility / Tagged PDF | 7           | 7     | 100% |
| 3D                         | 6           | 6     | 100% |
| Character encodings        | 7           | 7     | 100% |
| Stream filters (codecs)    | 10          | 10    | 100% |
| Reader capabilities        | 11          | 11    | 100% |
| Writer capabilities        | 7           | 7     | 100% |

> Every spec **object** has a PHP class and full end-to-end integration.
>
> **RFC 3161 timestamping** is fully wired: `TsaClient` sends
> `TimeStampReq` messages to any TSA server, and `PdfWriter::setTimestamper()`
> / `PdfFileWriter::setTimestamper()` produce PAdES-compatible document-level
> timestamps via `DocTimeStamp`. TSA clients can also be attached alongside
> regular signers via `setTsaClient()`.
>
> **Encryption** is fully wired: `PdfWriter::setEncryption()` (and
> `PdfFileWriter::setEncryption()`) register the `/Encrypt` dictionary,
> encrypt all strings and streams per-object during `generate()`, and
> emit `/Encrypt` in the trailer automatically. Supports RC4-40,
> RC4-128, AES-128, AES-256, and public-key (certificate-based)
> encryption.
>
> `PdfWriter::setSigner()` **is** fully wired: it computes `/ByteRange`,
> patches `/Contents` in place, and produces signatures verified in CI via
> `openssl cms -verify`.

---

## Version Gating

Every version-sensitive PDF feature is annotated with its minimum required PDF version. The writer auto-bumps the document version when features are used, or throws in strict mode.

### Annotation Coverage

| Version | Annotated Classes/Properties | Key Features |
|---------|------------------------------|-------------|
| **1.1** | 9 | CalGray, CalRGB, Lab, LaunchAction, Outline, OutlineItem, TransitionDict, MMType1Font, ThreadAction |
| **1.2** | 13 | Pattern, TilingPattern, WidgetAnnotation, Type0Font, CIDFont, AcroForm, HideAction, SubmitFormAction, ResetFormAction, ImportDataAction, AppearanceDict, AppearanceCharacteristics, AdditionalActions |
| **1.3** | 25 | ICCBased, Separation, ShadingPattern, Shading (all subtypes via inheritance), Func (all subtypes), StructTreeRoot, 14 annotation types, JavaScriptAction, PageLabel, SignatureField/Value/Reference |
| **1.4** | 13 | SoftMask, GroupAttributes, MetadataStream, MarkupAnnotation (all subtypes), OutputIntent, MovieAnnotation, ExtGState transparency properties (bm, sMask, ca, caLower, ais, tk), Page.$outputIntents |
| **1.5** | 30 | OCG, OCMD, OCPropertiesDict, CrossReferenceStream, ObjectStream, CryptFilter, Rendition/MediaRendition/SelectorRendition, MediaClip/Criteria/PlayParams/ScreenParams, Navigator, ScreenAnnotation, CaretAnnotation, PolygonAnnotation, PolyLineAnnotation, RedactAnnotation, BorderEffect, RenditionAction, SetOCGStateAction, TransAction, SoundAnnotation, SigFieldLock |
| **1.6** | 17 | DeviceN, ThreeDStream + 7 sub-objects, ThreeDAnnotation, DocTimeStamp, WatermarkAnnotation, CFFFontFile, GoToEAction, GoTo3DViewAction, HalftoneType16, MarkInfo.$userProperties/.$suspects |
| **1.7** | 6 | Collection, CollectionSchema, CollectionItem, Requirement, RequirementHandler, Catalog.$extensions |
| **2.0** | 17 | DPartRoot, DPart, GoToDPAction, RichMediaExecuteAction, DSS, ProjectionAnnotation, RichMediaAnnotation, Catalog.$dss/.$af/.$dPartRoot, Page.$af/.$dPart, FormXObject.$af, ViewerPreferences.$enforce, FileSpec.$afRelationship, SeedValueDictionary.$lockDocument/.$appearanceFilter |

### Deprecated Features

| Class | Deprecated Since | Replacement |
|-------|-----------------|-------------|
| Movie | 2.0 | RichMediaAnnotation |
| MovieAction | 2.0 | RichMediaExecuteAction |
| MovieAnnotation | 2.0 | ScreenAnnotation |
| Sound | 2.0 | MediaRendition |
| SoundAction | 2.0 | RenditionAction |
| SoundAnnotation | 2.0 | RichMediaAnnotation |
| PostScriptXObject | 1.7.1 | — |

### Runtime Checks

- `StructElem` implements `PdfVersionAware` — checks `StandardStructureType` for PDF 2.0 types (DocumentFragment, Aside, Title, THead, TBody, TFoot, FENote, Artifact)
- `PdfEncryptor::getMinimumPdfVersion()` — RC4→1.4, AES-128→1.6, AES-256→2.0
- `PdfFileWriter::generate()` auto-bumps for xref streams (→1.5) and syncs Catalog `/Version` for versions > 1.4
