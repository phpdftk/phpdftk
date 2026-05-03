---
title: ISO Standards
description: Full coverage of 8 PDF subset ISO standards — 7 ISO specifications, 31 conformance levels, all validated in CI.
---

phpdftk supports **every major PDF subset standard** — 7 ISO specifications plus ZUGFeRD, covering 31 conformance levels. Each is validated end-to-end in CI with dedicated integration tests and external tool verification via veraPDF, Matterhorn Protocol, and JHOVE.

## At a glance

| Standard | ISO | Purpose | Levels |
|---|---|---|---|
| **PDF/A** | 19005 | Long-term archival | 11 |
| **PDF/UA** | 14289 | Universal accessibility | 2 |
| **PDF/X** | 15930 | Print production | 6 |
| **PDF/VT** | 16612 | Variable/transactional printing | 3 |
| **PDF/E** | 24517 | Engineering documents | 1 |
| **PDF/R** | 23504 | Raster image transport | 1 |
| **Factur-X** | ZUGFeRD | E-invoicing | 6 |
| **PDF/mail** | 23053 | Email-safe documents | 1 |

---

## PDF/A — Long-Term Archival

**ISO 19005** (Parts 1–4) ensures documents remain readable decades from now by eliminating external dependencies.

### Profiles

| Level | Part | PDF | Key requirements |
|---|---|---|---|
| A-1a | 1 | 1.4 | Tagged, no transparency, no encryption, all fonts embedded |
| A-1b | 1 | 1.4 | Visual reproduction only (no tagged requirement) |
| A-2a/b/u | 2 | 1.7 | Transparency allowed, JPEG2000 allowed |
| A-3a/b/u | 3 | 1.7 | Embedded files allowed (e.g., for invoices) |
| A-4/4e/4f | 4 | 2.0 | Full PDF 2.0 feature set |

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

```php
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;

$writer->setConformance(PdfAProfile::A2b);
```

---

## PDF/UA — Universal Accessibility

**ISO 14289** ensures documents are accessible to users of assistive technologies.

### Profiles

| Level | Part | PDF |
|---|---|---|
| UA-1 | 1 | 1.7 |
| UA-2 | 2 | 2.0 |

### What gets enforced

- Complete tagged structure (StructTreeRoot, StructElem hierarchy)
- Document language (`/Lang`) required
- `DisplayDocTitle` must be true in ViewerPreferences
- All pages with annotations must have `/Tabs /S`
- Annotations must have `/Contents` for accessibility (Widget/Popup exempt)
- All fonts embedded
- XMP metadata with `pdfuaid` identification

```php
use Phpdftk\Pdf\Conformance\Profile\PdfUaProfile;

$writer->setConformance(PdfUaProfile::UA1);
```

**Dual profile** — combine with PDF/A for archival + accessible:

```php
$writer->setConformanceProfiles([PdfAProfile::A2a, PdfUaProfile::UA1]);
```

---

## PDF/X — Print Production

**ISO 15930** ensures reliable color reproduction in print workflows.

### Profiles

| Level | Part | PDF | Transparency | External refs |
|---|---|---|---|---|
| X-1a:2003 | 4 | 1.3 | Prohibited | No |
| X-3:2003 | 6 | 1.3 | Prohibited | No |
| X-4 | 7 | 1.6 | Allowed | No |
| X-5g/5pg/5n | 8–9 | 1.6 | Allowed | Reference XObjects |

### What gets enforced

- OutputIntent required (GTS_PDFX type)
- TrimBox or ArtBox required on every page
- `/Trapped` must be `True` or `False` (not `Unknown`)
- All fonts embedded
- No encryption
- Transparency prohibited for X-1a and X-3
- Reference XObject support validated for X-5

```php
use Phpdftk\Pdf\Conformance\Profile\PdfXProfile;

$writer->setConformance(PdfXProfile::X4);
```

---

## PDF/VT — Variable & Transactional Printing

**ISO 16612** (Parts 2–3) builds on PDF/X-4 for high-volume variable data printing (bills, statements, direct mail).

### Profiles

| Level | PDF | Description |
|---|---|---|
| VT-1 | 2.0 | Single-file exchange |
| VT-2 | 2.0 | Multi-file streaming |
| VT-2s | 2.0 | Streamed subset |

### What gets enforced

All PDF/X-4 constraints plus:
- `DPartRoot` required (document part hierarchy for variable records)
- XMP `pdfvtid` identification

```php
use Phpdftk\Pdf\Conformance\Profile\PdfVtProfile;

$writer->setConformance(PdfVtProfile::VT1);
```

---

## PDF/E — Engineering Documents

**ISO 24517-1** supports 3D models, geospatial data, and interactive engineering content.

### What gets enforced

- 3D content must use valid U3D or PRC streams with defined views
- JavaScript and Launch actions prohibited
- OutputIntent recommended (warning if missing)
- All fonts embedded, no encryption
- XMP metadata with identification

```php
use Phpdftk\Pdf\Conformance\Profile\PdfEProfile;

$writer->setConformance(PdfEProfile::E1);
```

---

## PDF/R — Raster Image Transport

**ISO 23504-1** is designed for scanned document workflows where raster content is primary.

### What gets enforced

- Raster-only content expected (non-raster triggers warning)
- JavaScript and Launch actions prohibited
- Font presence triggers warning (raster docs shouldn't need text fonts)
- No encryption

```php
use Phpdftk\Pdf\Conformance\Profile\PdfRProfile;

$writer->setConformance(PdfRProfile::R1);
```

---

## Factur-X / ZUGFeRD — E-Invoicing

Built on **PDF/A-3b**, these profiles add structured invoice data as an embedded XML file.

### Profiles

| Level | Description |
|---|---|
| MINIMUM | Minimal machine-readable data |
| BASIC_WL | Basic without line items |
| BASIC | Standard invoice data |
| EN16931 | EU Directive 2014/55/EU compliant |
| EXTENDED | Rich invoice data |
| XRECHNUNG | German public sector |

### What gets enforced

All PDF/A-3b constraints plus:
- XML invoice file embedded via FileSpec/EmbeddedFile (named `factur-x.xml` or `xrechnung.xml`)
- Factur-X XMP metadata with conformance level identification

```php
use Phpdftk\Pdf\Conformance\Profile\ZugferdProfile;

$writer->setConformance(ZugferdProfile::EN16931);
```

---

## PDF/mail — Email-Safe Documents

**ISO 23053-2** ensures documents can be safely attached to and opened from email without triggering security concerns.

### What gets enforced

- No encryption
- No JavaScript or Launch actions
- No interactive forms (AcroForm)
- No multimedia content
- All fonts embedded
- Pins to PDF 2.0

```php
use Phpdftk\Pdf\Conformance\Profile\PdfMailProfile;

$writer->setConformance(PdfMailProfile::Mail1);
```

---

## Constraint matrix

Shows which rules apply to which standard families.

| Constraint | A | UA | X | VT | E | R | ZUGFeRD | mail |
|---|---|---|---|---|---|---|---|---|
| Font embedding | All | All | All | All | All | — | All | All |
| Encryption prohibited | All | — | All | All | All | All | All | All |
| XMP metadata | All | All | All | All | All | All | All | All |
| OutputIntent | All | — | All | All | Warn | — | All | — |
| Color space checks | All | — | — | — | — | — | All | — |
| Action restrictions | All | — | — | — | All | All | All | All |
| Tagged structure | Level A | All | — | — | — | — | Level A | — |
| Transparency | A-1 only | — | X-1a/X-3 | — | — | — | A-1 only | — |
| TrimBox | — | — | All | All | — | — | — | — |
| Trapped flag | — | — | All | All | — | — | — | — |
| DPartRoot | — | — | — | All | — | — | — | — |
| 3D content | — | — | — | — | Required | — | — | — |
| Forms prohibited | — | — | — | — | — | — | — | All |
| Multimedia prohibited | — | — | — | — | — | — | — | All |
