---
title: Validation Suites
description: Enterprise PDF validation — 4 tiers of structural, conformance, accessibility, and security testing.
---

phpdftk integrates multiple enterprise-grade PDF validation tools organized into four tiers. Together they cover structural integrity, spec conformance, archival compliance, accessibility, and security.

## Tier overview

| Tier | Focus | Tools | CI behavior |
|---|---|---|---|
| **1** | Core validation | QPDF, Arlington, veraPDF | Every push/PR |
| **2** | Corpus stress-testing | Poppler, QPDF, PDFium, PDFBox, veraPDF corpora | On demand |
| **3** | Accessibility | Matterhorn Protocol (via veraPDF) | On demand |
| **4** | Reference & security | JHOVE, PDF 2.0 examples, pdfid, PDFBox Preflight | On demand |

---

## Tier 1 — Core Validation

These run automatically in CI and are the primary quality gate.

### QPDF — Structural Integrity

168 assertions across 46 test files. Validates xref tables, page trees, streams, linearization, and encryption structure.

Every integration test that generates a PDF calls `assertQpdfValid()` — if QPDF is available (Docker or local binary), the generated file is checked for structural correctness.

```php
use Phpdftk\Tests\Support\QpdfValidationTrait;

class MyTest extends TestCase
{
    use QpdfValidationTrait;

    public function testPdf(): void
    {
        $writer = new PdfWriter();
        // ... build PDF ...
        $writer->save($path);
        $this->assertQpdfValid($path);
    }
}
```

### Arlington PDF Model — Spec Conformance

6 assertions across 5 core tests. Validates every dictionary in the generated PDF against the Arlington PDF Model — a machine-readable representation of all 613 dictionary types in the PDF specification.

Checks:
- Required keys are present
- Key names are valid for the dictionary type
- Value types match the spec
- Version constraints are satisfied

### veraPDF — PDF/A & PDF/UA

2 dedicated test classes validate PDF/A-1b and PDF/UA-1 output against veraPDF's ISO 19005 and Matterhorn Protocol implementations.

```bash
# Run conformance tests (requires veraPDF Docker image)
mise run test -- --group verapdf
```

---

## Tier 2 — Corpus Stress-Testing

Large collections of real-world and edge-case PDFs from major implementations. These stress-test the reader's error tolerance.

| Corpus | Source | Files | Focus |
|---|---|---|---|
| **veraPDF** | veraPDF/veraPDF-corpus | ~1,500 | Intentionally non-conformant PDF/A (negative testing) |
| **Poppler** | freedesktop.org/poppler | ~80 | Fonts, transparency, CJK, encryption, damaged files |
| **QPDF** | qpdf/qpdf | ~700 | Linearization, object/xref streams, encryption, recovery |
| **PDFium** | chromium/pdfium | ~300 | Rendering, JavaScript, XFA, annotations, CJK |
| **PDFBox** | apache/pdfbox | ~150 | Signatures, encryption, forms, fonts, incremental updates |

All corpus PDFs are parsed with `PdfReader` in lenient mode. Encrypted and intentionally malformed files are expected to throw — unexpected exceptions are test failures.

```bash
# Initialize corpus submodules
git submodule update --init --depth 1 vendor-data/poppler-test vendor-data/qpdf vendor-data/pdfium vendor-data/pdfbox

# Run corpus tests
mise run test -- --group tier2
```

---

## Tier 3 — Accessibility Compliance

### Matterhorn Protocol

Tests PDF/UA (Universal Accessibility) compliance via veraPDF's `ua1` profile. Exercises:

- StructTreeRoot and MarkInfo presence
- Document language (`/Lang`)
- ViewerPreferences `DisplayDocTitle`
- Annotation accessibility (`/Contents` alt text)
- Tab order (`/Tabs /S`)

Both positive tests (tagged PDFs pass) and negative tests (missing tagging fails) are included.

```bash
mise run test -- --group tier3
```

---

## Tier 4 — Reference & Security

### JHOVE — Format Validation

Open Preservation Foundation's format validator. Checks structure, xref integrity, stream lengths, font embedding, and metadata. Validates "Well-Formed and Valid" status.

### PDF 2.0 Examples

7 reference PDFs from the PDF Association exercising PDF 2.0 features: page-level output intents, associated files, UTF-8 strings, incremental saves.

### pdfid — Security Scanning

Didier Stevens' security scanner. Asserts zero counts for suspicious features in generated PDFs:
- `/JS` and `/JavaScript` (embedded scripts)
- `/AA` and `/OpenAction` (automatic actions)
- `/Launch` (application execution)

### PDFBox Preflight — PDF/A Cross-Validation

Apache PDFBox's preflight module validates PDF/A-1b as a secondary cross-validator alongside veraPDF.

```bash
mise run test -- --group tier4
```

---

## Infrastructure

All validation tools use a Docker-first approach with local binary fallback.

### Docker setup

```bash
# Build/pull all validation tool images
cd docker && docker compose build && docker compose pull

# Initialize submodules (Arlington model + corpora)
git submodule update --init
```

### Graceful degradation

| Tool | If unavailable | Rationale |
|---|---|---|
| QPDF | Test passes silently | Bonus structural check; other assertions still validate |
| Arlington | Test skipped | Spec validation is the primary purpose |
| veraPDF | Test skipped | PDF/A tests are intentional opt-ins |
| JHOVE/pdfid/Preflight | Test skipped | Tier 4 tools are supplementary |

### Adding validation to a new test

```php
use Phpdftk\Tests\Support\QpdfValidationTrait;
use Phpdftk\Tests\Support\Arlington\ArlingtonValidationTrait;

class MyIntegrationTest extends TestCase
{
    use QpdfValidationTrait;
    use ArlingtonValidationTrait;

    public function testGeneratesPdf(): void
    {
        $writer = new PdfWriter();
        // ... build PDF ...
        $writer->save($path);

        $this->assertQpdfValid($path);
        $this->assertArlingtonValid($path);
    }
}
```
