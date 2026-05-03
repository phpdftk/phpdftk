# ISO Standards Coverage

Tracks conformance validation support for PDF subset ISO standards. The `pdf-conformance` package validates documents against these standards at write time via `PdfWriter::setConformance()` or at read time via `ConformanceChecker`.

**Key files:**
- [`ConformanceProfile`](../packages/pdf/conformance/src/Profile/ConformanceProfile.php) — interface all profiles implement
- [`ConformanceChecker`](../packages/pdf/conformance/src/ConformanceChecker.php) — read-side validation entry point
- [`ConformanceValidator`](../packages/pdf/conformance/src/Validator/ConformanceValidator.php) — runs constraints against a document
- [`ProfileConstraintRegistry`](../packages/pdf/conformance/src/Validator/ProfileConstraintRegistry.php) — maps profiles to constraints
- [`ConformanceXmpWriter`](../packages/pdf/conformance/src/Metadata/ConformanceXmpWriter.php) — auto-injects XMP identification metadata

---

## Standards Summary

| Standard | ISO Number | Profiles | PDF Version | Purpose |
|---|---|---|---|---|
| PDF/A | ISO 19005 (Parts 1–4) | 11 levels | 1.4–2.0 | Long-term archival |
| PDF/UA | ISO 14289 (Parts 1–2) | 2 levels | 1.7–2.0 | Universal accessibility |
| PDF/X | ISO 15930 (Parts 4, 6–9) | 6 levels | 1.3–1.6 | Print production |
| PDF/VT | ISO 16612 (Parts 2–3) | 3 levels | 2.0 | Variable/transactional printing |
| PDF/E | ISO 24517-1 | 1 level | 1.6 | Engineering documents |
| PDF/R | ISO 23504-1 | 1 level | 2.0 | Raster image transport |
| Factur-X | ZUGFeRD/Factur-X | 6 levels | 1.7 | European e-invoicing |
| PDF/mail | ISO 23053-2 | 1 level | 2.0 | Email-safe documents |

---

## PDF/A — Long-Term Archival (ISO 19005)

*Profile enum:* [`PdfAProfile`](../packages/pdf/conformance/src/Profile/PdfAProfile.php)

### Levels

| Case | Part | Conformance | PDF Version | Tagged | Transparency |
|---|---|---|---|---|---|
| `A1a` | Part 1 (ISO 19005-1) | Level A | 1.4 | Required | Prohibited |
| `A1b` | Part 1 (ISO 19005-1) | Level B | 1.4 | — | Prohibited |
| `A2a` | Part 2 (ISO 19005-2) | Level A | 1.7 | Required | Allowed |
| `A2b` | Part 2 (ISO 19005-2) | Level B | 1.7 | — | Allowed |
| `A2u` | Part 2 (ISO 19005-2) | Level U | 1.7 | — | Allowed |
| `A3a` | Part 3 (ISO 19005-3) | Level A | 1.7 | Required | Allowed |
| `A3b` | Part 3 (ISO 19005-3) | Level B | 1.7 | — | Allowed |
| `A3u` | Part 3 (ISO 19005-3) | Level U | 1.7 | — | Allowed |
| `A4` | Part 4 (ISO 19005-4) | — | 2.0 | — | Allowed |
| `A4e` | Part 4 (ISO 19005-4) | Level E | 2.0 | — | Allowed |
| `A4f` | Part 4 (ISO 19005-4) | Level F | 2.0 | — | Allowed |

### Constraints

| Constraint | Source | A-1 | A-2 | A-3 | A-4 |
|---|---|---|---|---|---|
| FontEmbedding | [`FontEmbeddingConstraint.php`](../packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| Encryption | [`EncryptionConstraint.php`](../packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| Metadata | [`MetadataConstraint.php`](../packages/pdf/conformance/src/Constraint/MetadataConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| OutputIntent | [`OutputIntentConstraint.php`](../packages/pdf/conformance/src/Constraint/OutputIntentConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| ColorSpace | [`ColorSpaceConstraint.php`](../packages/pdf/conformance/src/Constraint/ColorSpaceConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| Action | [`ActionConstraint.php`](../packages/pdf/conformance/src/Constraint/ActionConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| EmbeddedFile | [`EmbeddedFileConstraint.php`](../packages/pdf/conformance/src/Constraint/EmbeddedFileConstraint.php) | Prohibited | Prohibited | Allowed | Allowed |
| Transparency | [`TransparencyConstraint.php`](../packages/pdf/conformance/src/Constraint/TransparencyConstraint.php) | Prohibited | — | — | — |
| Filter | [`FilterConstraint.php`](../packages/pdf/conformance/src/Constraint/FilterConstraint.php) | LZW prohibited | — | — | — |
| TaggedStructure | [`TaggedStructureConstraint.php`](../packages/pdf/conformance/src/Constraint/TaggedStructureConstraint.php) | Level A only | Level A only | Level A only | — |

### Tests

- [`PdfA1bIntegrationTest.php`](../packages/pdf/conformance/tests/Integration/PdfA1bIntegrationTest.php) — A-1b end-to-end
- [`PdfALevelsIntegrationTest.php`](../packages/pdf/conformance/tests/Integration/PdfALevelsIntegrationTest.php) — A-1a through A-4f

---

## PDF/UA — Universal Accessibility (ISO 14289)

*Profile enum:* [`PdfUaProfile`](../packages/pdf/conformance/src/Profile/PdfUaProfile.php)

### Levels

| Case | Part | PDF Version |
|---|---|---|
| `UA1` | Part 1 (ISO 14289-1) | 1.7 |
| `UA2` | Part 2 (ISO 14289-2:2024) | 2.0 |

### Constraints

| Constraint | Source | Applies |
|---|---|---|
| FontEmbedding | [`FontEmbeddingConstraint.php`](../packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) | All |
| Metadata | [`MetadataConstraint.php`](../packages/pdf/conformance/src/Constraint/MetadataConstraint.php) | All |
| TaggedStructure | [`TaggedStructureConstraint.php`](../packages/pdf/conformance/src/Constraint/TaggedStructureConstraint.php) | All |
| DisplayDocTitle | [`DisplayDocTitleConstraint.php`](../packages/pdf/conformance/src/Constraint/DisplayDocTitleConstraint.php) | All |
| TabOrder | [`TabOrderConstraint.php`](../packages/pdf/conformance/src/Constraint/TabOrderConstraint.php) | All |
| Annotation | [`AnnotationConstraint.php`](../packages/pdf/conformance/src/Constraint/AnnotationConstraint.php) | All |

### Tests

- [`PdfUaIntegrationTest.php`](../packages/pdf/conformance/tests/Integration/PdfUaIntegrationTest.php) — UA-1/UA-2 end-to-end

---

## PDF/X — Print Production (ISO 15930)

*Profile enum:* [`PdfXProfile`](../packages/pdf/conformance/src/Profile/PdfXProfile.php)

### Levels

| Case | ISO Part | PDF Version | Transparency | Reference XObjects |
|---|---|---|---|---|
| `X1a2003` | ISO 15930-4 | 1.3 | Prohibited | — |
| `X32003` | ISO 15930-6 | 1.3 | Prohibited | — |
| `X4` | ISO 15930-7 | 1.6 | Allowed | — |
| `X5g` | ISO 15930-8 | 1.6 | Allowed | Supported |
| `X5pg` | ISO 15930-8 | 1.6 | Allowed | Supported |
| `X5n` | ISO 15930-9 | 1.6 | Allowed | Supported |

### Constraints

| Constraint | Source | X-1a/X-3 | X-4 | X-5 |
|---|---|---|---|---|
| FontEmbedding | [`FontEmbeddingConstraint.php`](../packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) | ✓ | ✓ | ✓ |
| Encryption | [`EncryptionConstraint.php`](../packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) | ✓ | ✓ | ✓ |
| Metadata | [`MetadataConstraint.php`](../packages/pdf/conformance/src/Constraint/MetadataConstraint.php) | ✓ | ✓ | ✓ |
| OutputIntent | [`OutputIntentConstraint.php`](../packages/pdf/conformance/src/Constraint/OutputIntentConstraint.php) | ✓ | ✓ | ✓ |
| TrimBox | [`TrimBoxConstraint.php`](../packages/pdf/conformance/src/Constraint/TrimBoxConstraint.php) | ✓ | ✓ | ✓ |
| Trapped | [`TrappedConstraint.php`](../packages/pdf/conformance/src/Constraint/TrappedConstraint.php) | ✓ | ✓ | ✓ |
| Transparency | [`TransparencyConstraint.php`](../packages/pdf/conformance/src/Constraint/TransparencyConstraint.php) | Prohibited | — | — |
| ReferenceXObject | [`ReferenceXObjectConstraint.php`](../packages/pdf/conformance/src/Constraint/ReferenceXObjectConstraint.php) | — | — | ✓ |

### Tests

- [`PdfXIntegrationTest.php`](../packages/pdf/conformance/tests/Integration/PdfXIntegrationTest.php) — X-1a, X-3, X-4, X-5g end-to-end

---

## PDF/VT — Variable & Transactional Printing (ISO 16612)

*Profile enum:* [`PdfVtProfile`](../packages/pdf/conformance/src/Profile/PdfVtProfile.php)

### Levels

| Case | ISO Part | PDF Version | Base |
|---|---|---|---|
| `VT1` | ISO 16612-2 | 2.0 | PDF/X-4 |
| `VT2` | ISO 16612-2 | 2.0 | PDF/X-4 |
| `VT2s` | ISO 16612-3 | 2.0 | PDF/X-4 |

### Constraints

Inherits all PDF/X-4 constraints plus:

| Constraint | Source |
|---|---|
| DPartRoot | [`DPartRootConstraint.php`](../packages/pdf/conformance/src/Constraint/DPartRootConstraint.php) |

### Tests

- [`PdfVtEandRIntegrationTest.php`](../packages/pdf/conformance/tests/Integration/PdfVtEandRIntegrationTest.php) — VT-1/VT-2/VT-2s end-to-end

---

## PDF/E — Engineering Documents (ISO 24517)

*Profile enum:* [`PdfEProfile`](../packages/pdf/conformance/src/Profile/PdfEProfile.php)

### Levels

| Case | ISO Part | PDF Version |
|---|---|---|
| `E1` | ISO 24517-1 | 1.6 |

### Constraints

| Constraint | Source |
|---|---|
| Metadata | [`MetadataConstraint.php`](../packages/pdf/conformance/src/Constraint/MetadataConstraint.php) |
| Encryption | [`EncryptionConstraint.php`](../packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) |
| FontEmbedding | [`FontEmbeddingConstraint.php`](../packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) |
| ThreeDContent | [`ThreeDContentConstraint.php`](../packages/pdf/conformance/src/Constraint/ThreeDContentConstraint.php) |
| PdfEAction | [`PdfEActionConstraint.php`](../packages/pdf/conformance/src/Constraint/PdfEActionConstraint.php) |
| PdfEColorSpace | [`PdfEColorSpaceConstraint.php`](../packages/pdf/conformance/src/Constraint/PdfEColorSpaceConstraint.php) |

### Tests

- [`PdfVtEandRIntegrationTest.php`](../packages/pdf/conformance/tests/Integration/PdfVtEandRIntegrationTest.php) — E-1 end-to-end

---

## PDF/R — Raster Image Transport (ISO 23504)

*Profile enum:* [`PdfRProfile`](../packages/pdf/conformance/src/Profile/PdfRProfile.php)

### Levels

| Case | ISO Part | PDF Version |
|---|---|---|
| `R1` | ISO 23504-1 | 2.0 |

### Constraints

| Constraint | Source |
|---|---|
| Metadata | [`MetadataConstraint.php`](../packages/pdf/conformance/src/Constraint/MetadataConstraint.php) |
| Encryption | [`EncryptionConstraint.php`](../packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) |
| RasterContent | [`RasterContentConstraint.php`](../packages/pdf/conformance/src/Constraint/RasterContentConstraint.php) |
| PdfRAction | [`PdfRActionConstraint.php`](../packages/pdf/conformance/src/Constraint/PdfRActionConstraint.php) |
| PdfRFont | [`PdfRFontConstraint.php`](../packages/pdf/conformance/src/Constraint/PdfRFontConstraint.php) |

### Tests

- [`PdfVtEandRIntegrationTest.php`](../packages/pdf/conformance/tests/Integration/PdfVtEandRIntegrationTest.php) — R-1 end-to-end

---

## Factur-X / ZUGFeRD — E-Invoicing

*Profile enum:* [`ZugferdProfile`](../packages/pdf/conformance/src/Profile/ZugferdProfile.php)

### Levels

| Case | Description | Base Profile |
|---|---|---|
| `MINIMUM` | Minimum invoice data | PDF/A-3b |
| `BASIC_WL` | Basic without lines | PDF/A-3b |
| `BASIC` | Basic invoice | PDF/A-3b |
| `EN16931` | EU norm compliant | PDF/A-3b |
| `EXTENDED` | Extended data | PDF/A-3b |
| `XRECHNUNG` | German e-invoice | PDF/A-3b |

### Constraints

Inherits all PDF/A-3b constraints plus:

| Constraint | Source |
|---|---|
| ZugferdXmp | [`ZugferdXmpConstraint.php`](../packages/pdf/conformance/src/Constraint/ZugferdXmpConstraint.php) |
| ZugferdInvoice | [`ZugferdInvoiceConstraint.php`](../packages/pdf/conformance/src/Constraint/ZugferdInvoiceConstraint.php) |

### Tests

- [`ZugferdIntegrationTest.php`](../packages/pdf/conformance/tests/Integration/ZugferdIntegrationTest.php) — All profile levels

---

## PDF/mail — Email-Safe Documents (ISO 23053)

*Profile enum:* [`PdfMailProfile`](../packages/pdf/conformance/src/Profile/PdfMailProfile.php)

### Levels

| Case | ISO Part | PDF Version |
|---|---|---|
| `Mail1` | ISO 23053-2 | 2.0 |

### Constraints

| Constraint | Source |
|---|---|
| FontEmbedding | [`FontEmbeddingConstraint.php`](../packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) |
| Encryption | [`EncryptionConstraint.php`](../packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) |
| Metadata | [`MetadataConstraint.php`](../packages/pdf/conformance/src/Constraint/MetadataConstraint.php) |
| Action | [`ActionConstraint.php`](../packages/pdf/conformance/src/Constraint/ActionConstraint.php) |
| Form | [`FormConstraint.php`](../packages/pdf/conformance/src/Constraint/FormConstraint.php) |
| Multimedia | [`MultimediaConstraint.php`](../packages/pdf/conformance/src/Constraint/MultimediaConstraint.php) |

### Tests

- [`PdfMailIntegrationTest.php`](../packages/pdf/conformance/tests/Integration/PdfMailIntegrationTest.php) — mail-1 end-to-end

---

## Constraint Matrix

Shows which constraints apply to which standard families.

| Constraint | Source | A | UA | X | X-5 | VT | E | R | ZUGFeRD | mail |
|---|---|---|---|---|---|---|---|---|---|---|
| FontEmbedding | [`FontEmbeddingConstraint.php`](../packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | ✓ | ✓ |
| Encryption | [`EncryptionConstraint.php`](../packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Metadata | [`MetadataConstraint.php`](../packages/pdf/conformance/src/Constraint/MetadataConstraint.php) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| OutputIntent | [`OutputIntentConstraint.php`](../packages/pdf/conformance/src/Constraint/OutputIntentConstraint.php) | ✓ | | ✓ | ✓ | ✓ | | | ✓ | |
| ColorSpace | [`ColorSpaceConstraint.php`](../packages/pdf/conformance/src/Constraint/ColorSpaceConstraint.php) | ✓ | | | | | | | ✓ | |
| Action | [`ActionConstraint.php`](../packages/pdf/conformance/src/Constraint/ActionConstraint.php) | ✓ | | | | | | | ✓ | ✓ |
| EmbeddedFile | [`EmbeddedFileConstraint.php`](../packages/pdf/conformance/src/Constraint/EmbeddedFileConstraint.php) | ✓ | | | | | | | ✓ | |
| Transparency | [`TransparencyConstraint.php`](../packages/pdf/conformance/src/Constraint/TransparencyConstraint.php) | A-1 | | X-1a/X-3 | | | | | A-1 | |
| Filter | [`FilterConstraint.php`](../packages/pdf/conformance/src/Constraint/FilterConstraint.php) | A-1 | | | | | | | | |
| TaggedStructure | [`TaggedStructureConstraint.php`](../packages/pdf/conformance/src/Constraint/TaggedStructureConstraint.php) | Level A | ✓ | | | | | | Level A | |
| DisplayDocTitle | [`DisplayDocTitleConstraint.php`](../packages/pdf/conformance/src/Constraint/DisplayDocTitleConstraint.php) | | ✓ | | | | | | | |
| TabOrder | [`TabOrderConstraint.php`](../packages/pdf/conformance/src/Constraint/TabOrderConstraint.php) | | ✓ | | | | | | | |
| Annotation | [`AnnotationConstraint.php`](../packages/pdf/conformance/src/Constraint/AnnotationConstraint.php) | | ✓ | | | | | | | |
| TrimBox | [`TrimBoxConstraint.php`](../packages/pdf/conformance/src/Constraint/TrimBoxConstraint.php) | | | ✓ | ✓ | ✓ | | | | |
| Trapped | [`TrappedConstraint.php`](../packages/pdf/conformance/src/Constraint/TrappedConstraint.php) | | | ✓ | ✓ | ✓ | | | | |
| DPartRoot | [`DPartRootConstraint.php`](../packages/pdf/conformance/src/Constraint/DPartRootConstraint.php) | | | | | ✓ | | | | |
| ThreeDContent | [`ThreeDContentConstraint.php`](../packages/pdf/conformance/src/Constraint/ThreeDContentConstraint.php) | | | | | | ✓ | | | |
| PdfEAction | [`PdfEActionConstraint.php`](../packages/pdf/conformance/src/Constraint/PdfEActionConstraint.php) | | | | | | ✓ | | | |
| PdfEColorSpace | [`PdfEColorSpaceConstraint.php`](../packages/pdf/conformance/src/Constraint/PdfEColorSpaceConstraint.php) | | | | | | ✓ | | | |
| RasterContent | [`RasterContentConstraint.php`](../packages/pdf/conformance/src/Constraint/RasterContentConstraint.php) | | | | | | | ✓ | | |
| PdfRAction | [`PdfRActionConstraint.php`](../packages/pdf/conformance/src/Constraint/PdfRActionConstraint.php) | | | | | | | ✓ | | |
| PdfRFont | [`PdfRFontConstraint.php`](../packages/pdf/conformance/src/Constraint/PdfRFontConstraint.php) | | | | | | | ✓ | | |
| ReferenceXObject | [`ReferenceXObjectConstraint.php`](../packages/pdf/conformance/src/Constraint/ReferenceXObjectConstraint.php) | | | | ✓ | | | | | |
| ZugferdXmp | [`ZugferdXmpConstraint.php`](../packages/pdf/conformance/src/Constraint/ZugferdXmpConstraint.php) | | | | | | | | ✓ | |
| ZugferdInvoice | [`ZugferdInvoiceConstraint.php`](../packages/pdf/conformance/src/Constraint/ZugferdInvoiceConstraint.php) | | | | | | | | ✓ | |
| Form | [`FormConstraint.php`](../packages/pdf/conformance/src/Constraint/FormConstraint.php) | | | | | | | | | ✓ |
| Multimedia | [`MultimediaConstraint.php`](../packages/pdf/conformance/src/Constraint/MultimediaConstraint.php) | | | | | | | | | ✓ |
