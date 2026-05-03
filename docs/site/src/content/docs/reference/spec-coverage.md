---
title: Spec Coverage
description: 100% coverage of ISO 32000-2:2020 — every PDF object type implemented as a PHP class.
---

phpdftk implements **100% of the PDF specification**. Every dictionary type, every field, every content stream operator defined in ISO 32000-2:2020 (PDF 2.0) has a corresponding PHP class with full serialization support.

This isn't partial coverage with TODOs — it's the complete spec, from PDF 1.0 primitives through PDF 2.0 features like Document Security Store, Associated Files, and Rich Media annotations.

## The numbers

| Category | Coverage |
|---|---|
| Document structure (Catalog, Page, Info, ViewerPreferences) | **100%** — all 120 fields |
| Font subtypes (Type1, TrueType, Type0, Type3, CID, MM) | **100%** — all 7 types |
| FontDescriptor fields | **100%** — all 19 fields |
| Annotation subtypes | **100%** — all 26 types |
| Markup annotation fields | **100%** — all 10 fields |
| Actions | **100%** — all 20 types |
| Interactive forms (AcroForm, fields, signatures) | **100%** — all 4 field types |
| ExtGState fields | **100%** — all 28 fields |
| Color spaces | **100%** — all 11 types |
| Patterns and shadings | **100%** — all 9 types |
| Content stream operators | **100%** — all 69 operators |
| Encryption (RC4, AES-128, AES-256, public-key) | **100%** |
| Digital signatures (PKCS#7, RFC 3161 TSA, LTV) | **100%** |
| Multimedia (Rendition, MediaClip, Navigator) | **100%** |
| 3D (U3D, PRC, views, lighting, cross-sections) | **100%** |
| Tagged PDF / accessibility | **100%** |
| Stream filters (Flate, LZW, ASCII85, CCITTFax, JBIG2) | **100%** |

**Every area is at 100%.** No partial implementations, no stubs, no "coming soon."

## What "100% coverage" means in practice

- **Every `/Field` in the spec** maps to a typed PHP property in camelCase
- **Every object type** has a PHP class with `toPdf()` serialization
- **Every content stream operator** is a fluent method on `ContentStream`
- **Version gating** tracks 172 features across PDF 1.0–2.0 with auto-bump or strict enforcement
- **Deprecation tracking** marks 7 features removed in PDF 2.0 with enforcement

## Validated by external tools

This isn't just self-reported coverage — every generated PDF passes validation by 5 independent tools:

- **QPDF** — structural integrity (236 tests)
- **Arlington PDF Model** — dictionary-level spec conformance
- **veraPDF** — ISO 19005 (PDF/A) validation
- **Matterhorn Protocol** — ISO 14289 (PDF/UA) accessibility
- **JHOVE** — format well-formedness

See the [Compliance Report](/conformance/compliance/) for current results and [Validation Suites](/reference/validations/) for infrastructure details.

## Highlights

### Fonts — complete embedding pipeline

All 7 font subtypes with full embedding: TrueType (`.ttf`), OpenType CFF (`.otf`), Type 1 (`.pfb`), WOFF/WOFF2 decompression, automatic subsetting, ToUnicode CMap generation, and kerning via GPOS tables.

### Annotations — every subtype, every field

All 26 annotation subtypes including the full markup annotation hierarchy with reply threading (`/IRT`, `/RT`), creation dates, popups, and rich content. Plus BorderStyle, BorderEffect, AppearanceDict, and AppearanceCharacteristics.

### Digital signatures — production-ready

PKCS#7 signing with ByteRange patching, RFC 3161 document timestamps via any TSA server (SHA-256/384/512), LTV signatures with DSS/VRI (certificates, OCSP responses, CRLs), and signature field seed values. Verified in CI with `openssl cms -verify`.

### Encryption — all algorithms

RC4 (40/128-bit), AES-128, AES-256 with both password-based (Standard handler) and certificate-based (Public-Key handler) encryption. Full key derivation per ISO 32000.

### PDF 2.0 features

Document Security Store, DPartRoot for variable data, Associated Files, Rich Media annotations, Projection annotations, enforced ViewerPreferences — all first-class.

---

**Related:** [Version Coverage](/reference/version-coverage/) details which features require which PDF versions. [ISO Standards](/conformance/iso-standards/) covers conformance validation across 8 standards.
