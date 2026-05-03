# PDF Version Coverage

Tracks which PDF features require which specification versions. The library uses `#[RequiresPdfVersion]` attributes on classes and properties to enforce version requirements at write time.

**Mechanism:** When a feature is registered with `PdfFileWriter`, the document version auto-bumps to the minimum required. In strict mode (`setStrictVersionMode(true)`), a `VersionRequirementException` is thrown instead.

**Key files:**
- [`PdfVersion`](../packages/pdf/core/src/PdfVersion.php) — backed string enum (`V1_0`..`V2_0`)
- [`RequiresPdfVersion`](../packages/pdf/core/src/RequiresPdfVersion.php) — PHP attribute for version gating
- [`DeprecatedPdfFeature`](../packages/pdf/core/src/DeprecatedPdfFeature.php) — PHP attribute for deprecation tracking
- [`VersionRequirementResolver`](../packages/pdf/core/src/File/VersionRequirementResolver.php) — reflection-based resolver with caching

---

## PDF 1.0 (Base)

All features not explicitly annotated default to PDF 1.0. This includes:

- Core primitives (`PdfObject`, `PdfArray`, `PdfDictionary`, `PdfStream`, etc.)
- [`Catalog`](../packages/pdf/core/src/Document/Catalog.php), [`PageTree`](../packages/pdf/core/src/Document/PageTree.php), [`Page`](../packages/pdf/core/src/Document/Page.php)
- [`Type1Font`](../packages/pdf/core/src/Font/Type1Font.php) (standard 14 fonts)
- [`ContentStream`](../packages/pdf/core/src/Content/ContentStream.php) operators
- [`DeviceRGB`](../packages/pdf/core/src/Graphics/ColorSpace/DeviceRGB.php), [`DeviceCMYK`](../packages/pdf/core/src/Graphics/ColorSpace/DeviceCMYK.php), [`DeviceGray`](../packages/pdf/core/src/Graphics/ColorSpace/DeviceGray.php)

---

## PDF 1.1 (9 annotations)

| Class / Property | Source | Description |
|---|---|---|
| `CalGray` | [`ColorSpace/CalGray.php`](../packages/pdf/core/src/Graphics/ColorSpace/CalGray.php) | CIE-based gray color space |
| `CalRGB` | [`ColorSpace/CalRGB.php`](../packages/pdf/core/src/Graphics/ColorSpace/CalRGB.php) | CIE-based RGB color space |
| `Lab` | [`ColorSpace/Lab.php`](../packages/pdf/core/src/Graphics/ColorSpace/Lab.php) | CIE-based L*a*b* color space |
| `LaunchAction` | [`Action/LaunchAction.php`](../packages/pdf/core/src/Action/LaunchAction.php) | Launch an application |
| `Outline` | [`Document/Outline.php`](../packages/pdf/core/src/Document/Outline.php) | Bookmark root |
| `OutlineItem` | [`Document/OutlineItem.php`](../packages/pdf/core/src/Document/OutlineItem.php) | Bookmark entry |
| `TransitionDict` | [`Document/TransitionDict.php`](../packages/pdf/core/src/Document/TransitionDict.php) | Page transitions |
| `MMType1Font` | [`Font/MMType1Font.php`](../packages/pdf/core/src/Font/MMType1Font.php) | Multiple Master fonts |
| `ThreadAction` | [`Action/ThreadAction.php`](../packages/pdf/core/src/Action/ThreadAction.php) | Navigate article thread |

---

## PDF 1.2 (13 annotations)

| Class / Property | Source | Description |
|---|---|---|
| `Pattern` | [`ColorSpace/Pattern.php`](../packages/pdf/core/src/Graphics/ColorSpace/Pattern.php) | Pattern color space |
| `TilingPattern` | [`Pattern/TilingPattern.php`](../packages/pdf/core/src/Graphics/Pattern/TilingPattern.php) | Tiling patterns |
| `WidgetAnnotation` | [`Annotation/WidgetAnnotation.php`](../packages/pdf/core/src/Annotation/WidgetAnnotation.php) | Form field widgets |
| `Type0Font` | [`Font/Type0Font.php`](../packages/pdf/core/src/Font/Type0Font.php) | Composite fonts |
| `CIDFont` | [`Font/CIDFont.php`](../packages/pdf/core/src/Font/CIDFont.php) | CID-keyed fonts (abstract) |
| `AcroForm` | [`Interactive/Form/AcroForm.php`](../packages/pdf/core/src/Interactive/Form/AcroForm.php) | Interactive forms |
| `HideAction` | [`Action/HideAction.php`](../packages/pdf/core/src/Action/HideAction.php) | Show/hide annotations |
| `SubmitFormAction` | [`Action/SubmitFormAction.php`](../packages/pdf/core/src/Action/SubmitFormAction.php) | Form submission |
| `ResetFormAction` | [`Action/ResetFormAction.php`](../packages/pdf/core/src/Action/ResetFormAction.php) | Form reset |
| `ImportDataAction` | [`Action/ImportDataAction.php`](../packages/pdf/core/src/Action/ImportDataAction.php) | FDF data import |
| `AppearanceDict` | [`Annotation/AppearanceDict.php`](../packages/pdf/core/src/Annotation/AppearanceDict.php) | Annotation appearances |
| `AppearanceCharacteristics` | [`Annotation/AppearanceCharacteristics.php`](../packages/pdf/core/src/Annotation/AppearanceCharacteristics.php) | Widget appearance config |
| `AdditionalActions` | [`Action/AdditionalActions.php`](../packages/pdf/core/src/Action/AdditionalActions.php) | Trigger events |

---

## PDF 1.3 (25 annotations)

| Class / Property | Source | Description |
|---|---|---|
| `ICCBased` | [`ColorSpace/ICCBased.php`](../packages/pdf/core/src/Graphics/ColorSpace/ICCBased.php) | ICC profile color space |
| `Separation` | [`ColorSpace/Separation.php`](../packages/pdf/core/src/Graphics/ColorSpace/Separation.php) | Spot color |
| `ShadingPattern` | [`Pattern/ShadingPattern.php`](../packages/pdf/core/src/Graphics/Pattern/ShadingPattern.php) | Shading patterns |
| `Shading` (all subtypes) | [`Shading/`](../packages/pdf/core/src/Graphics/Shading/) | Types 1–7 via inheritance |
| `Func` (all subtypes) | [`Function/`](../packages/pdf/core/src/Graphics/Function/) | Types 0–4 via inheritance |
| `StructTreeRoot` | [`Document/StructTreeRoot.php`](../packages/pdf/core/src/Document/StructTreeRoot.php) | Tagged PDF structure |
| `StructElem` | [`Document/StructElem.php`](../packages/pdf/core/src/Document/StructElem.php) | Structure element |
| `JavaScriptAction` | [`Action/JavaScriptAction.php`](../packages/pdf/core/src/Action/JavaScriptAction.php) | Execute JavaScript |
| `PageLabel` | [`Document/PageLabel.php`](../packages/pdf/core/src/Document/PageLabel.php) | Page numbering |
| `SignatureField` | [`Interactive/Form/SignatureField.php`](../packages/pdf/core/src/Interactive/Form/SignatureField.php) | Digital signature field |
| `SignatureValue` | [`Interactive/Signature/SignatureValue.php`](../packages/pdf/core/src/Interactive/Signature/SignatureValue.php) | Signature dictionary |
| `SignatureReference` | [`Interactive/Signature/SignatureReference.php`](../packages/pdf/core/src/Interactive/Signature/SignatureReference.php) | Signature reference |
| `FreeTextAnnotation` | [`Annotation/FreeTextAnnotation.php`](../packages/pdf/core/src/Annotation/FreeTextAnnotation.php) | Free text |
| `LineAnnotation` | [`Annotation/LineAnnotation.php`](../packages/pdf/core/src/Annotation/LineAnnotation.php) | Line |
| `SquareAnnotation` | [`Annotation/SquareAnnotation.php`](../packages/pdf/core/src/Annotation/SquareAnnotation.php) | Square |
| `CircleAnnotation` | [`Annotation/CircleAnnotation.php`](../packages/pdf/core/src/Annotation/CircleAnnotation.php) | Circle |
| `HighlightAnnotation` | [`Annotation/HighlightAnnotation.php`](../packages/pdf/core/src/Annotation/HighlightAnnotation.php) | Highlight |
| `UnderlineAnnotation` | [`Annotation/UnderlineAnnotation.php`](../packages/pdf/core/src/Annotation/UnderlineAnnotation.php) | Underline |
| `SquigglyAnnotation` | [`Annotation/SquigglyAnnotation.php`](../packages/pdf/core/src/Annotation/SquigglyAnnotation.php) | Squiggly |
| `StrikeOutAnnotation` | [`Annotation/StrikeOutAnnotation.php`](../packages/pdf/core/src/Annotation/StrikeOutAnnotation.php) | Strike-out |
| `StampAnnotation` | [`Annotation/StampAnnotation.php`](../packages/pdf/core/src/Annotation/StampAnnotation.php) | Rubber stamp |
| `InkAnnotation` | [`Annotation/InkAnnotation.php`](../packages/pdf/core/src/Annotation/InkAnnotation.php) | Ink strokes |
| `PopupAnnotation` | [`Annotation/PopupAnnotation.php`](../packages/pdf/core/src/Annotation/PopupAnnotation.php) | Popup |
| `FileAttachmentAnnotation` | [`Annotation/FileAttachmentAnnotation.php`](../packages/pdf/core/src/Annotation/FileAttachmentAnnotation.php) | File attachment |
| `HalftoneType1/5/6/10` | [`Graphics/`](../packages/pdf/core/src/Graphics/) | Halftone dictionaries |

---

## PDF 1.4 (13 annotations)

| Class / Property | Source | Description |
|---|---|---|
| `SoftMask` | [`Graphics/SoftMask.php`](../packages/pdf/core/src/Graphics/SoftMask.php) | Soft mask dictionary |
| `GroupAttributes` | [`Document/GroupAttributes.php`](../packages/pdf/core/src/Document/GroupAttributes.php) | Transparency group |
| `MetadataStream` | [`Document/MetadataStream.php`](../packages/pdf/core/src/Document/MetadataStream.php) | XMP metadata stream |
| `MarkupAnnotation` (all subtypes) | [`Annotation/MarkupAnnotation.php`](../packages/pdf/core/src/Annotation/MarkupAnnotation.php) | Markup annotation base |
| `OutputIntent` | [`Document/OutputIntent.php`](../packages/pdf/core/src/Document/OutputIntent.php) | Color management intent |
| `MovieAnnotation` | [`Annotation/MovieAnnotation.php`](../packages/pdf/core/src/Annotation/MovieAnnotation.php) | Movie (deprecated 2.0) |
| `ExtGState::$bm` | [`Graphics/ExtGState.php`](../packages/pdf/core/src/Graphics/ExtGState.php) | Blend mode property |
| `ExtGState::$sMask` | [`Graphics/ExtGState.php`](../packages/pdf/core/src/Graphics/ExtGState.php) | Soft mask property |
| `ExtGState::$ca` | [`Graphics/ExtGState.php`](../packages/pdf/core/src/Graphics/ExtGState.php) | Fill alpha property |
| `ExtGState::$caLower` | [`Graphics/ExtGState.php`](../packages/pdf/core/src/Graphics/ExtGState.php) | Stroke alpha property |
| `ExtGState::$ais` | [`Graphics/ExtGState.php`](../packages/pdf/core/src/Graphics/ExtGState.php) | Alpha is shape |
| `ExtGState::$tk` | [`Graphics/ExtGState.php`](../packages/pdf/core/src/Graphics/ExtGState.php) | Text knockout |
| `Page::$outputIntents` | [`Document/Page.php`](../packages/pdf/core/src/Document/Page.php) | Page-level output intents |

---

## PDF 1.5 (30 annotations)

| Class / Property | Source | Description |
|---|---|---|
| `OCG` | [`Document/OCG.php`](../packages/pdf/core/src/Document/OCG.php) | Optional content group |
| `OCMD` | [`Document/OCMD.php`](../packages/pdf/core/src/Document/OCMD.php) | Optional content membership |
| `OCPropertiesDict` | [`Document/OCPropertiesDict.php`](../packages/pdf/core/src/Document/OCPropertiesDict.php) | OC properties |
| `CrossReferenceStream` | [`Document/CrossReferenceStream.php`](../packages/pdf/core/src/Document/CrossReferenceStream.php) | Xref streams |
| `ObjectStream` | [`Document/ObjectStream.php`](../packages/pdf/core/src/Document/ObjectStream.php) | Compressed objects |
| `CryptFilter` | [`Security/CryptFilter.php`](../packages/pdf/core/src/Security/CryptFilter.php) | Encryption filters |
| `MediaRendition` | [`Multimedia/MediaRendition.php`](../packages/pdf/core/src/Multimedia/MediaRendition.php) | Media rendition |
| `SelectorRendition` | [`Multimedia/SelectorRendition.php`](../packages/pdf/core/src/Multimedia/SelectorRendition.php) | Selector rendition |
| `MediaClipData` | [`Multimedia/MediaClipData.php`](../packages/pdf/core/src/Multimedia/MediaClipData.php) | Media clip data |
| `MediaClipSection` | [`Multimedia/MediaClipSection.php`](../packages/pdf/core/src/Multimedia/MediaClipSection.php) | Media clip section |
| `MediaCriteria` | [`Multimedia/MediaCriteria.php`](../packages/pdf/core/src/Multimedia/MediaCriteria.php) | Media selection criteria |
| `MediaPlayParams` | [`Multimedia/MediaPlayParams.php`](../packages/pdf/core/src/Multimedia/MediaPlayParams.php) | Playback parameters |
| `MediaScreenParams` | [`Multimedia/MediaScreenParams.php`](../packages/pdf/core/src/Multimedia/MediaScreenParams.php) | Screen parameters |
| `Navigator` | [`Multimedia/Navigator.php`](../packages/pdf/core/src/Multimedia/Navigator.php) | Navigator dictionary |
| `ScreenAnnotation` | [`Annotation/ScreenAnnotation.php`](../packages/pdf/core/src/Annotation/ScreenAnnotation.php) | Screen annotation |
| `CaretAnnotation` | [`Annotation/CaretAnnotation.php`](../packages/pdf/core/src/Annotation/CaretAnnotation.php) | Caret annotation |
| `PolygonAnnotation` | [`Annotation/PolygonAnnotation.php`](../packages/pdf/core/src/Annotation/PolygonAnnotation.php) | Polygon |
| `PolyLineAnnotation` | [`Annotation/PolyLineAnnotation.php`](../packages/pdf/core/src/Annotation/PolyLineAnnotation.php) | Polyline |
| `RedactAnnotation` | [`Annotation/RedactAnnotation.php`](../packages/pdf/core/src/Annotation/RedactAnnotation.php) | Redaction |
| `SoundAnnotation` | [`Annotation/SoundAnnotation.php`](../packages/pdf/core/src/Annotation/SoundAnnotation.php) | Sound (deprecated 2.0) |
| `BorderEffect` | [`Annotation/BorderEffect.php`](../packages/pdf/core/src/Annotation/BorderEffect.php) | Border effect |
| `RenditionAction` | [`Action/RenditionAction.php`](../packages/pdf/core/src/Action/RenditionAction.php) | Rendition action |
| `SetOCGStateAction` | [`Action/SetOCGStateAction.php`](../packages/pdf/core/src/Action/SetOCGStateAction.php) | Set OCG state |
| `TransAction` | [`Action/TransAction.php`](../packages/pdf/core/src/Action/TransAction.php) | Transition action |
| `SigFieldLock` | [`Interactive/Form/SigFieldLock.php`](../packages/pdf/core/src/Interactive/Form/SigFieldLock.php) | Signature field lock |
| `Rendition` | [`Multimedia/Rendition.php`](../packages/pdf/core/src/Multimedia/Rendition.php) | Rendition (abstract) |
| `OCUsage` | [`Document/OCUsage.php`](../packages/pdf/core/src/Document/OCUsage.php) | OC usage application |
| `OCConfig` | [`Document/OCConfig.php`](../packages/pdf/core/src/Document/OCConfig.php) | OC configuration |
| `NamesDictionary` | [`Document/NamesDictionary.php`](../packages/pdf/core/src/Document/NamesDictionary.php) | Names dictionary |
| `Destination` | [`Document/Destination.php`](../packages/pdf/core/src/Document/Destination.php) | Explicit destinations |

---

## PDF 1.6 (17 annotations)

| Class / Property | Source | Description |
|---|---|---|
| `DeviceN` | [`ColorSpace/DeviceN.php`](../packages/pdf/core/src/Graphics/ColorSpace/DeviceN.php) | Multi-component color space |
| `ThreeDStream` | [`ThreeD/ThreeDStream.php`](../packages/pdf/core/src/ThreeD/ThreeDStream.php) | 3D stream (U3D/PRC) |
| `ThreeDView` | [`ThreeD/ThreeDView.php`](../packages/pdf/core/src/ThreeD/ThreeDView.php) | 3D view |
| `ThreeDBackground` | [`ThreeD/ThreeDBackground.php`](../packages/pdf/core/src/ThreeD/ThreeDBackground.php) | 3D background |
| `ThreeDRenderMode` | [`ThreeD/ThreeDRenderMode.php`](../packages/pdf/core/src/ThreeD/ThreeDRenderMode.php) | 3D render mode |
| `ThreeDLightingScheme` | [`ThreeD/ThreeDLightingScheme.php`](../packages/pdf/core/src/ThreeD/ThreeDLightingScheme.php) | 3D lighting |
| `ThreeDCrossSection` | [`ThreeD/ThreeDCrossSection.php`](../packages/pdf/core/src/ThreeD/ThreeDCrossSection.php) | 3D cross-section |
| `ThreeDAnnotation` | [`Annotation/ThreeDAnnotation.php`](../packages/pdf/core/src/Annotation/ThreeDAnnotation.php) | 3D annotation |
| `DocTimeStamp` | [`Interactive/Signature/DocTimeStamp.php`](../packages/pdf/core/src/Interactive/Signature/DocTimeStamp.php) | Document timestamp |
| `WatermarkAnnotation` | [`Annotation/WatermarkAnnotation.php`](../packages/pdf/core/src/Annotation/WatermarkAnnotation.php) | Watermark |
| `CFFFontFile` | [`Font/FontFile/CFFFontFile.php`](../packages/pdf/core/src/Font/FontFile/CFFFontFile.php) | CFF font embedding |
| `GoToEAction` | [`Action/GoToEAction.php`](../packages/pdf/core/src/Action/GoToEAction.php) | Go to embedded |
| `GoTo3DViewAction` | [`Action/GoTo3DViewAction.php`](../packages/pdf/core/src/Action/GoTo3DViewAction.php) | Navigate 3D view |
| `HalftoneType16` | [`Graphics/ExtGState.php`](../packages/pdf/core/src/Graphics/ExtGState.php) | 16-bit halftone |
| `MarkInfo::$userProperties` | [`Document/MarkInfo.php`](../packages/pdf/core/src/Document/MarkInfo.php) | User properties flag |
| `MarkInfo::$suspects` | [`Document/MarkInfo.php`](../packages/pdf/core/src/Document/MarkInfo.php) | Suspects flag |
| `CIDFontType0Font` | [`Font/CIDFontType0Font.php`](../packages/pdf/core/src/Font/CIDFontType0Font.php) | CID Type 0 (CFF) |

---

## PDF 1.7 (6 annotations)

| Class / Property | Source | Description |
|---|---|---|
| `Collection` | [`Document/Collection.php`](../packages/pdf/core/src/Document/Collection.php) | Portable collection |
| `CollectionSchema` | [`Document/CollectionSchema.php`](../packages/pdf/core/src/Document/CollectionSchema.php) | Collection field defs |
| `CollectionItem` | [`Document/CollectionItem.php`](../packages/pdf/core/src/Document/CollectionItem.php) | Collection entry |
| `Requirement` | [`Document/Requirement.php`](../packages/pdf/core/src/Document/Requirement.php) | Document requirements |
| `RequirementHandler` | [`Document/RequirementHandler.php`](../packages/pdf/core/src/Document/RequirementHandler.php) | Requirement handler |
| `Catalog::$extensions` | [`Document/Catalog.php`](../packages/pdf/core/src/Document/Catalog.php) | Developer extensions |

---

## PDF 2.0 (17 annotations)

| Class / Property | Source | Description |
|---|---|---|
| `DPartRoot` | [`Document/DPartRoot.php`](../packages/pdf/core/src/Document/DPartRoot.php) | Document part root |
| `DPart` | [`Document/DPart.php`](../packages/pdf/core/src/Document/DPart.php) | Document part |
| `GoToDPAction` | [`Action/GoToDPAction.php`](../packages/pdf/core/src/Action/GoToDPAction.php) | Navigate document part |
| `RichMediaExecuteAction` | [`Action/RichMediaExecuteAction.php`](../packages/pdf/core/src/Action/RichMediaExecuteAction.php) | Rich media execute |
| `DSS` | [`Document/DSS.php`](../packages/pdf/core/src/Document/DSS.php) | Document Security Store |
| `ProjectionAnnotation` | [`Annotation/ProjectionAnnotation.php`](../packages/pdf/core/src/Annotation/ProjectionAnnotation.php) | Projection annotation |
| `RichMediaAnnotation` | [`Annotation/RichMediaAnnotation.php`](../packages/pdf/core/src/Annotation/RichMediaAnnotation.php) | Rich media |
| `Catalog::$dss` | [`Document/Catalog.php`](../packages/pdf/core/src/Document/Catalog.php) | DSS reference |
| `Catalog::$af` | [`Document/Catalog.php`](../packages/pdf/core/src/Document/Catalog.php) | Associated files |
| `Catalog::$dPartRoot` | [`Document/Catalog.php`](../packages/pdf/core/src/Document/Catalog.php) | Document part root ref |
| `Page::$af` | [`Document/Page.php`](../packages/pdf/core/src/Document/Page.php) | Page associated files |
| `Page::$dPart` | [`Document/Page.php`](../packages/pdf/core/src/Document/Page.php) | Page document part |
| `FormXObject::$af` | [`Graphics/XObject/FormXObject.php`](../packages/pdf/core/src/Graphics/XObject/FormXObject.php) | Form XObject AF |
| `ViewerPreferences::$enforce` | [`Document/ViewerPreferences.php`](../packages/pdf/core/src/Document/ViewerPreferences.php) | Enforced preferences |
| `FileSpec::$afRelationship` | [`FileSpec/FileSpec.php`](../packages/pdf/core/src/FileSpec/FileSpec.php) | AF relationship |
| `SeedValueDictionary::$lockDocument` | [`Interactive/Form/SeedValueDictionary.php`](../packages/pdf/core/src/Interactive/Form/SeedValueDictionary.php) | Lock document flag |
| `SeedValueDictionary::$appearanceFilter` | [`Interactive/Form/SeedValueDictionary.php`](../packages/pdf/core/src/Interactive/Form/SeedValueDictionary.php) | Appearance filter |

---

## Deprecated Features

7 features are marked with `#[DeprecatedPdfFeature]`. When `removedIn` is set and strict deprecation is enabled, using the feature at or above that version throws `DeprecatedFeatureException`.

| Class | Source | Deprecated Since | Removed In | Replacement |
|---|---|---|---|---|
| `Movie` | [`Multimedia/Movie.php`](../packages/pdf/core/src/Multimedia/Movie.php) | 2.0 | 2.0 | `RichMediaAnnotation` |
| `MovieAction` | [`Action/MovieAction.php`](../packages/pdf/core/src/Action/MovieAction.php) | 2.0 | 2.0 | `RichMediaExecuteAction` |
| `MovieAnnotation` | [`Annotation/MovieAnnotation.php`](../packages/pdf/core/src/Annotation/MovieAnnotation.php) | 2.0 | 2.0 | `ScreenAnnotation` |
| `Sound` | [`Multimedia/Sound.php`](../packages/pdf/core/src/Multimedia/Sound.php) | 2.0 | 2.0 | `MediaRendition` |
| `SoundAction` | [`Action/SoundAction.php`](../packages/pdf/core/src/Action/SoundAction.php) | 2.0 | 2.0 | `RenditionAction` |
| `SoundAnnotation` | [`Annotation/SoundAnnotation.php`](../packages/pdf/core/src/Annotation/SoundAnnotation.php) | 2.0 | 2.0 | `RichMediaAnnotation` |
| `PostScriptXObject` | [`Graphics/XObject/PostScriptXObject.php`](../packages/pdf/core/src/Graphics/XObject/PostScriptXObject.php) | 1.7.1 | — | — |

---

## Runtime Version Checks

Some objects determine their version requirement at runtime rather than via static attributes:

- **`StructElem`** implements `PdfVersionAware` — checks `StandardStructureType` for PDF 2.0 types (`DocumentFragment`, `Aside`, `Title`, `THead`, `TBody`, `TFoot`, `FENote`, `Artifact`)
  - Source: [`Document/StructElem.php`](../packages/pdf/core/src/Document/StructElem.php)
- **`PdfEncryptor::getMinimumPdfVersion()`** — RC4 → 1.4, AES-128 → 1.6, AES-256 → 2.0
  - Source: [`Security/PdfEncryptor.php`](../packages/pdf/core/src/Security/PdfEncryptor.php)
- **`PdfFileWriter::generate()`** — auto-bumps for xref streams (→ 1.5) and syncs `Catalog::$version` for versions > 1.4
  - Source: [`File/PdfFileWriter.php`](../packages/pdf/core/src/File/PdfFileWriter.php)
