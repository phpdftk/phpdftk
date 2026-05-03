---
title: Compliance Report
description: 275 external validation tests, 275 passing — verified by QPDF, veraPDF, Arlington, Matterhorn, and JHOVE.
---

Every PDF that phpdftk generates passes validation by **5 independent, industry-standard tools**. This isn't a self-assessment — these are the same tools used by national archives, print production houses, and accessibility auditors to verify PDF correctness.

## Results

**275 tests. 275 passed. 0 failed. 0 skipped.**

| Tool | Tests | What it proves |
|---|---|---|
| **QPDF** | 236 | Every xref entry, page tree link, stream length, and object reference is structurally correct |
| **Arlington** | 6 | Every dictionary key and value type matches the PDF specification tables |
| **veraPDF** | 2 | PDF/A output meets ISO 19005 archival requirements |
| **Matterhorn** | 6 | PDF/UA output meets ISO 14289 accessibility requirements |
| **JHOVE + Preflight + Security** | 25 | Well-formed format, PDF/A cross-validation, zero security indicators |

---

## What each tool validates

### QPDF — "Is this a correct PDF?"

236 tests cover the complete output surface. QPDF validates:

- Cross-reference tables have exact 20-byte entries per ISO 32000-2
- Page trees are correctly wired (parent/child/count consistency)
- Stream `/Length` values match actual byte counts
- Linearized PDFs have valid hint tables and partition offsets
- Encrypted PDFs have correct handler structures
- Object streams and xref streams pack correctly

**Coverage:** Simple text, all 26 annotation subtypes, bookmarks, forms, embedded fonts (TrueType, Type 1, OpenType CFF), graphics pipelines, shadings, patterns, multimedia, 3D, digital signatures, LTV signing, markup annotations, custom font form appearances, XMP metadata, linearized output, xref streams, object streams.

### Arlington PDF Model — "Does this match the spec?"

The Arlington PDF Model is a machine-readable representation of every dictionary type in ISO 32000-2. It validates that every key is spelled correctly, every value has the right type, and every required field is present.

This catches the kinds of bugs that QPDF won't — a `/Filter` value that should be a name but is a string, a missing `/Type` key, or a field that doesn't exist in the spec for that dictionary type.

### veraPDF — "Is this valid PDF/A?"

veraPDF is the reference implementation for ISO 19005 validation, developed with funding from the EU PREFORMA project. When phpdftk generates a PDF/A-1b document, veraPDF confirms it meets every clause — font embedding, color spaces, metadata identification, transparency prohibition, and encryption prohibition.

### Matterhorn Protocol — "Is this accessible?"

The Matterhorn Protocol (via veraPDF's UA-1 profile) validates PDF/UA compliance. Both positive and negative tests are included:

- Tagged documents with proper structure **pass**
- Annotations with `/Contents` alt text **pass**
- Documents missing tagging, `/Lang`, `DisplayDocTitle`, or annotation alt text **correctly fail**

This proves the library produces genuinely accessible output, not just output that claims to be accessible.

### JHOVE + pdfid + PDFBox Preflight

- **JHOVE** confirms well-formed, valid PDF format (Open Preservation Foundation)
- **pdfid** confirms zero suspicious security indicators — no embedded JavaScript, no auto-open actions, no launch triggers
- **PDFBox Preflight** cross-validates PDF/A-1b against a second independent implementation

---

## Corpus testing

Beyond validating our own output, the reader is stress-tested against **2,700+ PDFs** from 5 major implementations:

| Source | Files | Purpose |
|---|---|---|
| veraPDF corpus | ~1,500 | Intentionally non-conformant PDFs (negative testing) |
| QPDF test suite | ~700 | Linearization, encryption, xref streams, damaged files |
| PDFium (Chromium) | ~300 | Rendering, JavaScript, XFA, CJK |
| Apache PDFBox | ~150 | Signatures, forms, incremental updates |
| Poppler | ~80 | Fonts, transparency, damaged files |

---

## Running it yourself

```bash
# Full compliance suite (requires Docker)
mise run compliance

# Results written to docs/generated/compliance.md
```

The suite runs automatically on every push to `main` in CI. See [Validation Suites](/reference/validations/) for Docker setup and tool integration details.
