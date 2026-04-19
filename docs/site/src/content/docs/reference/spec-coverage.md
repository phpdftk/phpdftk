---
title: Spec Coverage
description: ISO 32000-2:2020 (PDF 2.0) specification coverage.
---

phpdftk maps every object type in the PDF specification to a PHP class. Every `/Field` from the spec maps to a camelCase property.

## Coverage summary

| Area | Implemented | Total | Coverage |
|---|---|---|---|
| Catalog fields | 28 | 28 | 100% |
| PageTree fields | 33 | 33 | 100% |
| Page fields | 32 | 32 | 100% |
| Info fields | 9 | 9 | 100% |
| ViewerPreferences | 18 | 18 | 100% |
| Document structure objects | 24 | 24 | 100% |
| Font subtypes | 7 | 7 | 100% |
| FontDescriptor fields | 19 | 19 | 100% |
| Annotation base fields | 18 | 18 | 100% |
| Markup annotation fields | 10 | 10 | 100% |
| Annotation subtypes | 26 | 26 | 100% |
| Actions | 20 | 20 | 100% |
| AcroForm fields | 8 | 8 | 100% |
| Field types | 4 | 4 | 100% |
| ExtGState fields | 28 | 28 | 100% |
| Color spaces | 11 | 11 | 100% |
| XObject subtypes | 3 | 3 | 100% |
| Function types | 4 | 4 | 100% |
| Pattern types | 2 | 2 | 100% |
| Shading types | 7 | 7 | 100% |
| Content stream operators | 69 | 69 | 100% |
| Encryption | 8 | 8 | 100% |
| Digital signatures | 7 | 7 | 100% |
| Multimedia | 7 | 7 | 100% |
| File specifications | 3 | 3 | 100% |
| Accessibility / Tagged PDF | 7 | 7 | 100% |
| 3D | 6 | 6 | 100% |

## Highlights

### Fonts
All 7 PDF font subtypes: Type1, TrueType, Type0, Type3, MMType1, CIDFontType0, CIDFontType2. TrueType and OpenType (CFF) font embedding with automatic subsetting.

### Annotations
All 26 annotation subtypes with full field coverage, including markup annotation hierarchy (Text, FreeText, Line, Highlight, Stamp, Ink, Redact, etc.).

### Security
RC4 (40/128-bit), AES-128, AES-256 encryption. Public-key (certificate-based) encryption. PKCS#7 digital signatures with ByteRange patching. DocTimeStamp + TsaClient for RFC 3161 timestamping (full TSA HTTP client with SHA-256/384/512).

### Content streams
All 69 content stream operators: text (BT/ET, Tj/TJ, Tf, Td, Tm), paths (m/l/c/re), painting (S/f/B), color (rg/RG/k/K/cs/CS), graphics state (q/Q/cm/gs), marked content (BMC/BDC/EMC), XObjects (Do), and more.

### Tagged PDF
StructTreeRoot, StructElem, RoleMap, ClassMap, StandardStructureType, marked content operators. Full accessibility tree support.

### Advanced features
- Cross-reference streams and object streams (PDF 1.5+)
- Incremental updates with /Prev chain following
- Linearization detection
- Optional content groups (layers)
- 3D annotations (U3D and PRC)
- Multimedia (Sound, Movie, Rendition, MediaClip)
- File attachments and embedded files
