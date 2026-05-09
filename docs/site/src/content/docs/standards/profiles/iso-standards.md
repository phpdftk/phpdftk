---
title: ISO Standards
description: Full coverage of 8 PDF subset ISO standards — 7 ISO specifications, 31 conformance levels, all validated in CI.
---

phpdftk supports **every major PDF subset standard** — 7 ISO specifications plus ZUGFeRD, covering 31 conformance levels. Each is validated end-to-end in CI with dedicated integration tests and external tool verification via veraPDF, Matterhorn Protocol, and JHOVE.

The `pdf-conformance` package validates documents against these standards at write time via `PdfWriter::setConformance()` or at read time via `ConformanceChecker`.

**Key files:**
- [`ConformanceProfile`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Profile/ConformanceProfile.php) — interface all profiles implement
- [`ConformanceChecker`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/ConformanceChecker.php) — read-side validation entry point
- [`ConformanceValidator`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Validator/ConformanceValidator.php) — runs constraints against a document
- [`ProfileConstraintRegistry`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Validator/ProfileConstraintRegistry.php) — maps profiles to constraints
- [`ConformanceXmpWriter`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Metadata/ConformanceXmpWriter.php) — auto-injects XMP identification metadata

## At a glance

| Standard | ISO | Profiles | PDF Version | Purpose |
|---|---|---|---|---|
| **PDF/A** | 19005 (Parts 1–4) | 11 levels | 1.4–2.0 | Long-term archival |
| **PDF/UA** | 14289 (Parts 1–2) | 2 levels | 1.7–2.0 | Universal accessibility |
| **PDF/X** | 15930 (Parts 4, 6–9) | 6 levels | 1.3–1.6 | Print production |
| **PDF/VT** | 16612 (Parts 2–3) | 3 levels | 2.0 | Variable/transactional printing |
| **PDF/E** | 24517-1 | 1 level | 1.6 | Engineering documents |
| **PDF/R** | 23504-1 | 1 level | 2.0 | Raster image transport |
| **Factur-X** | ZUGFeRD/Factur-X | 6 levels | 1.7 | European e-invoicing |
| **PDF/mail** | 23053-2 | 1 level | 2.0 | Email-safe documents |

---

## PDF/A — Long-Term Archival

**ISO 19005** (Parts 1–4) ensures documents remain readable decades from now by eliminating external dependencies.

*Profile enum:* [`PdfAProfile`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Profile/PdfAProfile.php)

### Profiles

| Case | Part | Conformance | PDF | Tagged | Transparency |
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

### What gets enforced

- All fonts must be embedded with complete glyph programs
- No encryption permitted
- XMP metadata with `pdfaid` identification required
- OutputIntent with ICC profile required
- Device-dependent color spaces trigger warnings
- JavaScript, Launch, Movie, Sound actions prohibited
- LZW compression prohibited (A-1 only)
- Transparency prohibited (A-1 only)
- Tagged structure required (Level A only)

### Constraints

| Constraint | Source | A-1 | A-2 | A-3 | A-4 |
|---|---|---|---|---|---|
| FontEmbedding | [`FontEmbeddingConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| Encryption | [`EncryptionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| Metadata | [`MetadataConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/MetadataConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| OutputIntent | [`OutputIntentConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/OutputIntentConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| ColorSpace | [`ColorSpaceConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ColorSpaceConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| Action | [`ActionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ActionConstraint.php) | ✓ | ✓ | ✓ | ✓ |
| EmbeddedFile | [`EmbeddedFileConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/EmbeddedFileConstraint.php) | Prohibited | Prohibited | Allowed | Allowed |
| Transparency | [`TransparencyConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TransparencyConstraint.php) | Prohibited | — | — | — |
| Filter | [`FilterConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/FilterConstraint.php) | LZW prohibited | — | — | — |
| TaggedStructure | [`TaggedStructureConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TaggedStructureConstraint.php) | Level A only | Level A only | Level A only | — |

```php
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;

$writer->setConformance(PdfAProfile::A2b);
```

### Tests

- [`PdfA1bIntegrationTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/tests/Integration/PdfA1bIntegrationTest.php) — A-1b end-to-end
- [`PdfALevelsIntegrationTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/tests/Integration/PdfALevelsIntegrationTest.php) — A-1a through A-4f

---

## PDF/UA — Universal Accessibility

**ISO 14289** ensures documents are accessible to users of assistive technologies.

*Profile enum:* [`PdfUaProfile`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Profile/PdfUaProfile.php)

### Profiles

| Case | Part | PDF |
|---|---|---|
| `UA1` | Part 1 (ISO 14289-1) | 1.7 |
| `UA2` | Part 2 (ISO 14289-2:2024) | 2.0 |

### What gets enforced

- Complete tagged structure (StructTreeRoot, StructElem hierarchy)
- Document language (`/Lang`) required
- `DisplayDocTitle` must be true in ViewerPreferences
- All pages with annotations must have `/Tabs /S`
- Annotations must have `/Contents` for accessibility (Widget/Popup exempt)
- All fonts embedded
- XMP metadata with `pdfuaid` identification

### Constraints

| Constraint | Source | Applies |
|---|---|---|
| FontEmbedding | [`FontEmbeddingConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) | All |
| Metadata | [`MetadataConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/MetadataConstraint.php) | All |
| TaggedStructure | [`TaggedStructureConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TaggedStructureConstraint.php) | All |
| DisplayDocTitle | [`DisplayDocTitleConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/DisplayDocTitleConstraint.php) | All |
| TabOrder | [`TabOrderConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TabOrderConstraint.php) | All |
| Annotation | [`AnnotationConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/AnnotationConstraint.php) | All |

```php
use Phpdftk\Pdf\Conformance\Profile\PdfUaProfile;

$writer->setConformance(PdfUaProfile::UA1);
```

**Dual profile** — combine with PDF/A for archival + accessible:

```php
$writer->setConformanceProfiles([PdfAProfile::A2a, PdfUaProfile::UA1]);
```

### Tests

- [`PdfUaIntegrationTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/tests/Integration/PdfUaIntegrationTest.php) — UA-1/UA-2 end-to-end

---

## PDF/X — Print Production

**ISO 15930** ensures reliable color reproduction in print workflows.

*Profile enum:* [`PdfXProfile`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Profile/PdfXProfile.php)

### Profiles

| Case | ISO Part | PDF | Transparency | Reference XObjects |
|---|---|---|---|---|
| `X1a2003` | ISO 15930-4 | 1.3 | Prohibited | — |
| `X32003` | ISO 15930-6 | 1.3 | Prohibited | — |
| `X4` | ISO 15930-7 | 1.6 | Allowed | — |
| `X5g` | ISO 15930-8 | 1.6 | Allowed | Supported |
| `X5pg` | ISO 15930-8 | 1.6 | Allowed | Supported |
| `X5n` | ISO 15930-9 | 1.6 | Allowed | Supported |

### What gets enforced

- OutputIntent required (GTS_PDFX type)
- TrimBox or ArtBox required on every page
- `/Trapped` must be `True` or `False` (not `Unknown`)
- All fonts embedded
- No encryption
- Transparency prohibited for X-1a and X-3
- Reference XObject support validated for X-5

### Constraints

| Constraint | Source | X-1a/X-3 | X-4 | X-5 |
|---|---|---|---|---|
| FontEmbedding | [`FontEmbeddingConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) | ✓ | ✓ | ✓ |
| Encryption | [`EncryptionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) | ✓ | ✓ | ✓ |
| Metadata | [`MetadataConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/MetadataConstraint.php) | ✓ | ✓ | ✓ |
| OutputIntent | [`OutputIntentConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/OutputIntentConstraint.php) | ✓ | ✓ | ✓ |
| TrimBox | [`TrimBoxConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TrimBoxConstraint.php) | ✓ | ✓ | ✓ |
| Trapped | [`TrappedConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TrappedConstraint.php) | ✓ | ✓ | ✓ |
| Transparency | [`TransparencyConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TransparencyConstraint.php) | Prohibited | — | — |
| ReferenceXObject | [`ReferenceXObjectConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ReferenceXObjectConstraint.php) | — | — | ✓ |

```php
use Phpdftk\Pdf\Conformance\Profile\PdfXProfile;

$writer->setConformance(PdfXProfile::X4);
```

### Tests

- [`PdfXIntegrationTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/tests/Integration/PdfXIntegrationTest.php) — X-1a, X-3, X-4, X-5g end-to-end

---

## PDF/VT — Variable & Transactional Printing

**ISO 16612** (Parts 2–3) builds on PDF/X-4 for high-volume variable data printing (bills, statements, direct mail).

*Profile enum:* [`PdfVtProfile`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Profile/PdfVtProfile.php)

### Profiles

| Case | ISO Part | PDF | Base |
|---|---|---|---|
| `VT1` | ISO 16612-2 | 2.0 | PDF/X-4 |
| `VT2` | ISO 16612-2 | 2.0 | PDF/X-4 |
| `VT2s` | ISO 16612-3 | 2.0 | PDF/X-4 |

### What gets enforced

All PDF/X-4 constraints plus:
- `DPartRoot` required (document part hierarchy for variable records)
- XMP `pdfvtid` identification

### Constraints

Inherits all PDF/X-4 constraints plus:

| Constraint | Source |
|---|---|
| DPartRoot | [`DPartRootConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/DPartRootConstraint.php) |

```php
use Phpdftk\Pdf\Conformance\Profile\PdfVtProfile;

$writer->setConformance(PdfVtProfile::VT1);
```

### Tests

- [`PdfVtEandRIntegrationTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/tests/Integration/PdfVtEandRIntegrationTest.php) — VT-1/VT-2/VT-2s end-to-end

---

## PDF/E — Engineering Documents

**ISO 24517-1** supports 3D models, geospatial data, and interactive engineering content.

*Profile enum:* [`PdfEProfile`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Profile/PdfEProfile.php)

### Profiles

| Case | ISO Part | PDF |
|---|---|---|
| `E1` | ISO 24517-1 | 1.6 |

### What gets enforced

- 3D content must use valid U3D or PRC streams with defined views
- JavaScript and Launch actions prohibited
- OutputIntent recommended (warning if missing)
- All fonts embedded, no encryption
- XMP metadata with identification

### Constraints

| Constraint | Source |
|---|---|
| Metadata | [`MetadataConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/MetadataConstraint.php) |
| Encryption | [`EncryptionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) |
| FontEmbedding | [`FontEmbeddingConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) |
| ThreeDContent | [`ThreeDContentConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ThreeDContentConstraint.php) |
| PdfEAction | [`PdfEActionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/PdfEActionConstraint.php) |
| PdfEColorSpace | [`PdfEColorSpaceConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/PdfEColorSpaceConstraint.php) |

```php
use Phpdftk\Pdf\Conformance\Profile\PdfEProfile;

$writer->setConformance(PdfEProfile::E1);
```

### Tests

- [`PdfVtEandRIntegrationTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/tests/Integration/PdfVtEandRIntegrationTest.php) — E-1 end-to-end

---

## PDF/R — Raster Image Transport

**ISO 23504-1** is designed for scanned document workflows where raster content is primary.

*Profile enum:* [`PdfRProfile`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Profile/PdfRProfile.php)

### Profiles

| Case | ISO Part | PDF |
|---|---|---|
| `R1` | ISO 23504-1 | 2.0 |

### What gets enforced

- Raster-only content expected (non-raster triggers warning)
- JavaScript and Launch actions prohibited
- Font presence triggers warning (raster docs shouldn't need text fonts)
- No encryption

### Constraints

| Constraint | Source |
|---|---|
| Metadata | [`MetadataConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/MetadataConstraint.php) |
| Encryption | [`EncryptionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) |
| RasterContent | [`RasterContentConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/RasterContentConstraint.php) |
| PdfRAction | [`PdfRActionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/PdfRActionConstraint.php) |
| PdfRFont | [`PdfRFontConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/PdfRFontConstraint.php) |

```php
use Phpdftk\Pdf\Conformance\Profile\PdfRProfile;

$writer->setConformance(PdfRProfile::R1);
```

### Tests

- [`PdfVtEandRIntegrationTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/tests/Integration/PdfVtEandRIntegrationTest.php) — R-1 end-to-end

---

## Factur-X / ZUGFeRD — E-Invoicing

Built on **PDF/A-3b**, these profiles add structured invoice data as an embedded XML file.

*Profile enum:* [`ZugferdProfile`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Profile/ZugferdProfile.php)

### Profiles

| Case | Description | Base Profile |
|---|---|---|
| `MINIMUM` | Minimum invoice data | PDF/A-3b |
| `BASIC_WL` | Basic without lines | PDF/A-3b |
| `BASIC` | Basic invoice | PDF/A-3b |
| `EN16931` | EU norm compliant | PDF/A-3b |
| `EXTENDED` | Extended data | PDF/A-3b |
| `XRECHNUNG` | German e-invoice | PDF/A-3b |

### What gets enforced

All PDF/A-3b constraints plus:
- XML invoice file embedded via FileSpec/EmbeddedFile (named `factur-x.xml` or `xrechnung.xml`)
- Factur-X XMP metadata with conformance level identification

### Constraints

Inherits all PDF/A-3b constraints plus:

| Constraint | Source |
|---|---|
| ZugferdXmp | [`ZugferdXmpConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ZugferdXmpConstraint.php) |
| ZugferdInvoice | [`ZugferdInvoiceConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ZugferdInvoiceConstraint.php) |

```php
use Phpdftk\Pdf\Conformance\Profile\ZugferdProfile;

$writer->setConformance(ZugferdProfile::EN16931);
```

### Tests

- [`ZugferdIntegrationTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/tests/Integration/ZugferdIntegrationTest.php) — All profile levels

---

## PDF/mail — Email-Safe Documents

**ISO 23053-2** ensures documents can be safely attached to and opened from email without triggering security concerns.

*Profile enum:* [`PdfMailProfile`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Profile/PdfMailProfile.php)

### Profiles

| Case | ISO Part | PDF |
|---|---|---|
| `Mail1` | ISO 23053-2 | 2.0 |

### What gets enforced

- No encryption
- No JavaScript or Launch actions
- No interactive forms (AcroForm)
- No multimedia content
- All fonts embedded
- Pins to PDF 2.0

### Constraints

| Constraint | Source |
|---|---|
| FontEmbedding | [`FontEmbeddingConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) |
| Encryption | [`EncryptionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) |
| Metadata | [`MetadataConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/MetadataConstraint.php) |
| Action | [`ActionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ActionConstraint.php) |
| Form | [`FormConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/FormConstraint.php) |
| Multimedia | [`MultimediaConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/MultimediaConstraint.php) |

```php
use Phpdftk\Pdf\Conformance\Profile\PdfMailProfile;

$writer->setConformance(PdfMailProfile::Mail1);
```

### Tests

- [`PdfMailIntegrationTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/tests/Integration/PdfMailIntegrationTest.php) — mail-1 end-to-end

---

## Constraint Matrix

Shows which constraints apply across all standard families.

| Constraint | Source | A | UA | X | X-5 | VT | E | R | ZUGFeRD | mail |
|---|---|---|---|---|---|---|---|---|---|---|
| FontEmbedding | [`FontEmbeddingConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/FontEmbeddingConstraint.php) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | ✓ | ✓ |
| Encryption | [`EncryptionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/EncryptionConstraint.php) | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Metadata | [`MetadataConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/MetadataConstraint.php) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| OutputIntent | [`OutputIntentConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/OutputIntentConstraint.php) | ✓ | | ✓ | ✓ | ✓ | | | ✓ | |
| ColorSpace | [`ColorSpaceConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ColorSpaceConstraint.php) | ✓ | | | | | | | ✓ | |
| Action | [`ActionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ActionConstraint.php) | ✓ | | | | | | | ✓ | ✓ |
| EmbeddedFile | [`EmbeddedFileConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/EmbeddedFileConstraint.php) | ✓ | | | | | | | ✓ | |
| Transparency | [`TransparencyConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TransparencyConstraint.php) | A-1 | | X-1a/X-3 | | | | | A-1 | |
| Filter | [`FilterConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/FilterConstraint.php) | A-1 | | | | | | | | |
| TaggedStructure | [`TaggedStructureConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TaggedStructureConstraint.php) | Level A | ✓ | | | | | | Level A | |
| DisplayDocTitle | [`DisplayDocTitleConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/DisplayDocTitleConstraint.php) | | ✓ | | | | | | | |
| TabOrder | [`TabOrderConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TabOrderConstraint.php) | | ✓ | | | | | | | |
| Annotation | [`AnnotationConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/AnnotationConstraint.php) | | ✓ | | | | | | | |
| TrimBox | [`TrimBoxConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TrimBoxConstraint.php) | | | ✓ | ✓ | ✓ | | | | |
| Trapped | [`TrappedConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/TrappedConstraint.php) | | | ✓ | ✓ | ✓ | | | | |
| DPartRoot | [`DPartRootConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/DPartRootConstraint.php) | | | | | ✓ | | | | |
| ThreeDContent | [`ThreeDContentConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ThreeDContentConstraint.php) | | | | | | ✓ | | | |
| PdfEAction | [`PdfEActionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/PdfEActionConstraint.php) | | | | | | ✓ | | | |
| PdfEColorSpace | [`PdfEColorSpaceConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/PdfEColorSpaceConstraint.php) | | | | | | ✓ | | | |
| RasterContent | [`RasterContentConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/RasterContentConstraint.php) | | | | | | | ✓ | | |
| PdfRAction | [`PdfRActionConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/PdfRActionConstraint.php) | | | | | | | ✓ | | |
| PdfRFont | [`PdfRFontConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/PdfRFontConstraint.php) | | | | | | | ✓ | | |
| ReferenceXObject | [`ReferenceXObjectConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ReferenceXObjectConstraint.php) | | | | ✓ | | | | | |
| ZugferdXmp | [`ZugferdXmpConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ZugferdXmpConstraint.php) | | | | | | | | ✓ | |
| ZugferdInvoice | [`ZugferdInvoiceConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/ZugferdInvoiceConstraint.php) | | | | | | | | ✓ | |
| Form | [`FormConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/FormConstraint.php) | | | | | | | | | ✓ |
| Multimedia | [`MultimediaConstraint.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/conformance/src/Constraint/MultimediaConstraint.php) | | | | | | | | | ✓ |
