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
| `/BoxColorInfo`         | ✗      |                                                  |
| `/B`                    | ✗      | Article beads                                    |
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
| Cross-reference stream (`/Type /XRef`)         | ✗      | Size, Index, Prev, W — PDF 1.5+                                        |
| Object stream (`/Type /ObjStm`)                | ✗      | N, First, Extends — PDF 1.5+ compressed objects                        |

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

| Subtype         | Class          | Status | Notes                                                             |
|-----------------|----------------|--------|-------------------------------------------------------------------|
| `/Type1`        | `Type1Font`    | ✓      | Includes standard 14 with AFM widths                              |
| `/TrueType`     | `TrueTypeFont` | ✓      | Full font program embedding via `/FontFile2`; metrics from TTF tables |
| `/Type0`        | `Type0Font`    | ✓      | Composite font                                                    |
| `/CIDFontType0` | `CIDFont`      | ~      | Generic CIDFont; subtype not enforced                             |
| `/CIDFontType2` | `CIDFont`      | ~      | Generic CIDFont; subtype not enforced                             |
| `/MMType1`      | —              | ✗      | Multiple Master fonts                                             |
| `/Type3`        | —              | ✗      | Glyph procedures defined in PDF; CharProcs, FontMatrix, Resources |

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
| `/FontFile`     | ✓      | Reference stored; embedding not implemented |
| `/FontFile2`    | ✓      | TrueType font program embedding via `TrueTypeFont::fromFile()` |
| `/FontFile3`    | ✓      | Reference stored; embedding not implemented |
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
| `CMap` stream (`/Type /CMap`) | ✗      | CMapName, CIDSystemInfo, WMode                                          |
| ToUnicode CMap stream         | ✓      | Generated from TrueType cmap table; WinAnsi byte → Unicode mapping      |
| TrueType font embedding       | ✓      | Full font program embedded via `/FontFile2`; `TrueTypeFont::fromFile()` |
| Font subsetting               | ✗      | Glyph subsetting and stream embedding                                   |
| OpenType font support         | ✗      | `/FontFile3` with `/Subtype /OpenType`                                  |

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

| `/S` Value          | Class              | Status | Notes                                        |
|---------------------|--------------------|--------|----------------------------------------------|
| `/GoTo`             | `GoToAction`       | ✓      | D                                            |
| `/URI`              | `URIAction`        | ✓      | URI, IsMap                                   |
| `/Named`            | `NamedAction`      | ✓      | N                                            |
| `/JavaScript`       | `JavaScriptAction` | ✓      | JS                                           |
| `/GoToR`            | `GoToRAction`      | ✓      | F, D, NewWindow                              |
| `/GoToE`            | —                  | ✗      | F, D, NewWindow, T — embedded PDF navigation |
| `/GoToDP`           | —                  | ✗      | D, DP                                        |
| `/Launch`           | —                  | ✗      | F, Win, Mac, Unix, NewWindow                 |
| `/Thread`           | —                  | ✗      | F, D, B — article thread navigation          |
| `/Sound`            | —                  | ✗      | Sound, Volume, Synchronous, Repeat, Mix      |
| `/Movie`            | —                  | ✗      | Annotation, Operation, Parameter             |
| `/Hide`             | —                  | ✗      | H (field/annotation reference + bool)        |
| `/SubmitForm`       | —                  | ✗      | F, Fields, Flags                             |
| `/ResetForm`        | —                  | ✗      | Fields, Flags                                |
| `/ImportData`       | —                  | ✗      | F                                            |
| `/SetOCGState`      | —                  | ✗      | State, PreserveRB                            |
| `/Rendition`        | —                  | ✗      | OP, R, AN, JS                                |
| `/Trans`            | —                  | ✗      | Trans                                        |
| `/GoTo3DView`       | —                  | ✗      | TA, V                                        |
| `/RichMediaExecute` | —                  | ✗      |                                              |

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

| Type   | Class            | Status | Notes                                        |
|--------|------------------|--------|----------------------------------------------|
| `/Btn` | `ButtonField`    | ✓      | H, MK, Opt; pushbutton/checkbox/radio via Ff |
| `/Tx`  | `TextField`      | ✓      | MaxLen, Q; multiline/password/comb via Ff    |
| `/Ch`  | `ChoiceField`    | ✓      | Opt, TI, I; combo/edit/sort via Ff           |
| `/Sig` | `SignatureField` | ~      | SigFlags only; signature value dict missing  |

### Missing Signature Objects

| Object                              | Status | Notes                                                                        |
|-------------------------------------|--------|------------------------------------------------------------------------------|
| Signature value dict (`/Type /Sig`) | ✗      | Filter, SubFilter, ByteRange, Contents, Reference, Name, M, Location, Reason |
| `SignatureReference` dict           | ✗      | TransformMethod, TransformParams, Data, DigestMethod                         |
| `DocMDP` transform params           | ✗      | P, V, Type                                                                   |
| `FieldMDP` transform params         | ✗      | Action, Fields, V, Type                                                      |
| `UR3` transform params              | ✗      | Usage rights                                                                 |
| `Perms` dict (in Catalog)           | ✗      | DocMDP, UR3, Legal                                                           |

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
| `/BG`             | ✗      | Black generation function     |
| `/BG2`            | ✗      | Black generation (PDF 1.3+)   |
| `/UCR`            | ✗      | Undercolor removal            |
| `/UCR2`           | ✗      | Undercolor removal (PDF 1.3+) |
| `/TR`             | ✗      | Transfer function             |
| `/TR2`            | ✗      | Transfer function (PDF 1.3+)  |
| `/HT`             | ✗      | Halftone                      |
| `/UseBlackPtComp` | ✗      | Black point compensation      |
| `/HTO`            | ✗      | Halftone origin               |

### Soft Mask Dictionary

| Field   | Status | Notes                      |
|---------|--------|----------------------------|
| `/Type` | ✗      |                            |
| `/S`    | ✗      | Alpha or Luminosity        |
| `/G`    | ✗      | Transparency group XObject |
| `/BC`   | ✗      | Backdrop color             |
| `/TR`   | ✗      | Transfer function          |

### Color Spaces

| Color Space   | Status | Notes                                                           |
|---------------|--------|-----------------------------------------------------------------|
| `/DeviceGray` | ✓      |                                                                 |
| `/DeviceRGB`  | ✓      |                                                                 |
| `/DeviceCMYK` | ✓      |                                                                 |
| `/CalGray`    | ✗      | WhitePoint, BlackPoint, Gamma                                   |
| `/CalRGB`     | ✗      | WhitePoint, BlackPoint, Gamma, Matrix                           |
| `/Lab`        | ✗      | WhitePoint, BlackPoint, Range                                   |
| `/ICCBased`   | ✗      | N, Alternate, Range, Metadata — needed for color-managed output |
| `/Indexed`    | ✗      | base, hival, lookup                                             |
| `/Pattern`    | ✗      | Pattern color space                                             |
| `/Separation` | ✗      | name, alternateSpace, tintTransform                             |
| `/DeviceN`    | ✗      | names, alternateSpace, tintTransform, attributes                |

### Pattern (`/Type /Pattern`)

| Type                       | Status | Notes                                                        |
|----------------------------|--------|--------------------------------------------------------------|
| `/PatternType 1` (Tiling)  | ✗      | PaintType, TilingType, BBox, XStep, YStep, Resources, Matrix |
| `/PatternType 2` (Shading) | ✗      | Shading, Matrix, ExtGState                                   |

### Shading (`/Type /Shading` or stream)

| Type                                    | Status | Notes                                                                      |
|-----------------------------------------|--------|----------------------------------------------------------------------------|
| `/ShadingType 1` (Function-based)       | ✗      | ColorSpace, Domain, Matrix, Function                                       |
| `/ShadingType 2` (Axial)                | ✗      | Coords, Domain, Extend, Function — linear gradient                         |
| `/ShadingType 3` (Radial)               | ✗      | Coords, Domain, Extend, Function — radial gradient                         |
| `/ShadingType 4` (Free-form Gouraud)    | ✗      | Stream: BitsPerCoordinate, BitsPerComponent, BitsPerFlag, Decode, Function |
| `/ShadingType 5` (Lattice Gouraud)      | ✗      | Stream: VerticesPerRow                                                     |
| `/ShadingType 6` (Coons patch)          | ✗      | Stream                                                                     |
| `/ShadingType 7` (Tensor-product patch) | ✗      | Stream                                                                     |

### XObject (`/Type /XObject`)

| Subtype  | Class          | Status | Notes                                                                                                                     |
|----------|----------------|--------|---------------------------------------------------------------------------------------------------------------------------|
| `/Image` | `ImageXObject` | ✓      | Width, Height, ColorSpace, BitsPerComponent, Filter, DecodeParms, Intent, ImageMask, Mask, SMask, Interpolate, Alternates |
| `/Form`  | `FormXObject`  | ✓      | BBox, Matrix, Resources                                                                                                   |
| `/PS`    | —              | ✗      | PostScript XObject                                                                                                        |

### Functions

| Type                            | Status | Notes                                                            |
|---------------------------------|--------|------------------------------------------------------------------|
| `/FunctionType 0` (Sampled)     | ✗      | Domain, Range, Size, BitsPerSample, Order, Encode, Decode        |
| `/FunctionType 2` (Exponential) | ✗      | Domain, Range, C0, C1, N — simplest; needed for spot color tints |
| `/FunctionType 3` (Stitching)   | ✗      | Domain, Range, Functions, Bounds, Encode — combines functions    |
| `/FunctionType 4` (PostScript)  | ✗      | Domain, Range, PS operators in stream                            |

> Functions are a prerequisite for Shading types 1–3 and Separation/DeviceN color spaces.

### Halftone (`/Type /Halftone`)

| Type                                       | Status | Notes                          |
|--------------------------------------------|--------|--------------------------------|
| `/HalftoneType 1` (dictionary)             | ✗      | Frequency, Angle, SpotFunction |
| `/HalftoneType 5` (multidotted)            | ✗      | Dict of component halftones    |
| `/HalftoneType 6` (threshold array stream) | ✗      |                                |
| `/HalftoneType 10` (threshold)             | ✗      |                                |
| `/HalftoneType 16` (threshold)             | ✗      |                                |

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

| Object                                           | Status | Notes                               |
|--------------------------------------------------|--------|-------------------------------------|
| `Sound` (`/Type /Sound`)                         | ✗      | FS, Length, R, C, B, E, CO, CP      |
| `Movie` dict                                     | ✗      | F, Aspect, Rotate, Poster           |
| `Rendition` (`/Type /Rendition`)                 | ✗      | Subtypes: MR (Media), SR (Selector) |
| `MediaClip` (`/Type /MediaClip`)                 | ✗      | Subtypes: MCD (data), MCS (section) |
| `MediaPlayParams` (`/Type /MediaPlayParams`)     | ✗      |                                     |
| `MediaScreenParams` (`/Type /MediaScreenParams`) | ✗      |                                     |
| `Navigator` (`/Type /Navigator`)                 | ✗      |                                     |

---

## File Specifications

| Object                                        | Status | Notes                                              |
|-----------------------------------------------|--------|----------------------------------------------------|
| `FileSpec` (`/Type /Filespec`)                | ✗      | FS, F, UF, DOS, Mac, Unix, ID, V, EF, RF, Desc, CI |
| `EmbeddedFile` stream (`/Type /EmbeddedFile`) | ✗      | Subtype, Params                                    |
| `EmbeddedFileParams` dict                     | ✗      | Size, CreationDate, ModDate, Mac, CheckSum         |

---

## Encryption

| Object / Field                         | Status | Notes                                             |
|----------------------------------------|--------|---------------------------------------------------|
| `EncryptDictionary` (`/Type /Encrypt`) | ✗      | Filter, SubFilter, V, Length, CF, StmF, StrF, EFF |
| Standard handler fields                | ✗      | R, O, U, P, EncryptMetadata, OE, UE, Perms        |
| Crypt filter dict                      | ✗      | AuthEvent, CFM, Length                            |
| Public-key handler                     | ✗      | Recipients field                                  |
| RC4 cipher                             | ✓      | `phpdftk/crypt` — `Rc4Cipher`                     |
| AES-128/256 cipher                     | ✓      | `phpdftk/crypt` — `AesCipher`                     |
| PDF key derivation                     | ✓      | `phpdftk/crypt` — `PdfKeyDerivation`              |

> Cipher primitives exist in `phpdftk/crypt`; the PDF `EncryptDictionary` and `PdfWriter` integration are not yet wired
> up.

---

## Digital Signatures

| Object                              | Status | Notes                                                                                 |
|-------------------------------------|--------|---------------------------------------------------------------------------------------|
| Signature value dict (`/Type /Sig`) | ✗      | Filter, SubFilter, ByteRange, Contents, Reference, Changes, Name, M, Location, Reason |
| `SignatureReference` dict           | ✗      | TransformMethod, TransformParams, Data, DigestMethod, DigestValue                     |
| `DocMDP` transform params           | ✗      | P, V, Type                                                                            |
| `FieldMDP` transform params         | ✗      | Action, Fields, V, Type                                                               |
| `UR3` transform params              | ✗      | Usage rights                                                                          |
| PKCS#7 / CAdES signing              | ✗      | Actual cryptographic signing                                                          |
| Timestamp authority                 | ✗      | RFC 3161 timestamps                                                                   |

---

## 3D

| Object                          | Status | Notes                                        |
|---------------------------------|--------|----------------------------------------------|
| `3D` stream (`/Type /3D`)       | ✗      | Subtype (U3D or PRC), VA, DV, AN, ColorSpace |
| `3DView` dict (`/Type /3DView`) | ✗      | XN, IN, MS, CO, P, O, BG, RM, LS             |
| `3DBackground` dict             | ✗      | Type, CS, C, EA                              |
| `3DRenderMode` dict             | ✗      | Type, Subtype, FC, OP, CV                    |
| `3DLightingScheme` dict         | ✗      | Type, Subtype                                |
| `3DCrossSection` dict           | ✗      | Type, C, O, LC, IV, ST                       |

---

## Accessibility / Tagged PDF

| Object / Feature                           | Status | Notes                                                          |
|--------------------------------------------|--------|----------------------------------------------------------------|
| `StructTreeRoot` (`/Type /StructTreeRoot`) | ✓      | K, IDTree, ParentTree, ParentTreeNextKey, RoleMap, ClassMap    |
| `StructElem` (`/Type /StructElem`)         | ✓      | S, P, ID, Pg, K, A, C, R, T, Lang, Alt, E, ActualText          |
| `ObjectRef` (`/Type /OBJR`)                | ✓      | Pg, Obj                                                        |
| Marked content operators                   | ✓      | `BMC`, `BDC`, `EMC`, `MP`, `DP` implemented in `ContentStream` |
| `RoleMap` dict                             | ✗      |                                                                |
| `ClassMap` dict                            | ✗      |                                                                |
| Attribute objects                          | ✗      |                                                                |

---

## Coverage Summary

| Area                       | Implemented | Total | %    |
|----------------------------|-------------|-------|------|
| Catalog fields             | 24          | 24    | 100% |
| PageTree fields            | 30          | 30    | 100% |
| Page fields                | 29          | 29    | 100% |
| Info fields                | 9           | 9     | 100% |
| ViewerPreferences fields   | 17          | 17    | 100% |
| Document structure objects | 22          | 24    | 92%  |
| Font subtypes              | 4           | 7     | 57%  |
| FontDescriptor fields      | 19          | 19    | 100% |
| Annotation base fields     | 18          | 18    | 100% |
| Annotation subtypes        | 26          | 26    | 100% |
| Supporting annot dicts     | 4           | 4     | 100% |
| Actions                    | 5           | 20    | 25%  |
| AcroForm fields            | 8           | 8     | 100% |
| Field types                | 4           | 4     | 100% |
| ExtGState fields           | 19          | 28    | 68%  |
| Color spaces               | 3           | 11    | 27%  |
| XObject subtypes           | 2           | 3     | 67%  |
| Function types             | 0           | 4     | 0%   |
| Pattern types              | 0           | 2     | 0%   |
| Shading types              | 0           | 7     | 0%   |
| Content stream operators   | 69          | 69    | 100% |
| Encryption                 | 0           | 8     | 0%*  |
| Digital signatures         | 0           | 7     | 0%   |
| Multimedia                 | 0           | 7     | 0%   |
| File specifications        | 0           | 3     | 0%   |
| Accessibility / Tagged PDF | 4           | 7     | 57%  |
| 3D                         | 0           | 6     | 0%   |

> \* Cipher primitives exist in `phpdftk/crypt`; PDF-layer wiring is incomplete.
