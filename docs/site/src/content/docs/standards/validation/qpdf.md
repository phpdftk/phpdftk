---
title: QPDF
description: Structural integrity validation — every xref entry, page tree link, and stream length verified.
---


[QPDF](https://github.com/qpdf/qpdf) is an open-source PDF transformation and inspection tool (Apache 2.0). Its `--check` mode validates the structural integrity of a PDF file: cross-reference tables, page tree, stream lengths, linearization parameters, and encryption dictionaries.

## What it catches

- Malformed or inconsistent cross-reference tables
- Invalid page tree structure (missing `/Type`, broken `/Parent` links)
- Stream `/Length` mismatches
- Linearization parameter errors
- Encryption dictionary inconsistencies
- Object numbering and generation number issues
- Missing or malformed `%%EOF` markers

## Installation

```bash
# Docker (recommended — no local install needed)
cd docker && docker compose build

# Or install locally as a fallback:
brew install qpdf          # macOS
sudo apt-get install qpdf  # Ubuntu/Debian
```

## How it works

The `QpdfValidationTrait` provides two assertion methods:

```php
// Validate a PDF file on disk
$this->assertQpdfValid('/path/to/file.pdf');

// Validate in-memory PDF bytes (writes to temp file, validates, cleans up)
$this->assertQpdfValidBytes($pdfBytes);
```

Both methods:

1. Try Docker first via `DockerToolRunner` (image: `phpdftk/qpdf`)
2. Fall back to local binary via `ExternalToolLocator::find('qpdf')`
3. If neither is available, **silently return** (the test passes on its existing assertions)
4. Run `qpdf --check <file>` and assert exit code 0
5. On failure, include the full QPDF error output in the assertion message

### Trait source

`tests/Support/QpdfValidationTrait.php` (`Phpdftk\Tests\Support\QpdfValidationTrait`)

## Coverage

QPDF validation is applied to **every integration test that generates a PDF** across all three PDF packages:

| Package | Test files | Assertion calls |
|---|---|---|
| `pdf/core` | 21 | ~25 |
| `pdf/writer` | 6 | ~35 |
| `pdf/toolkit` | 11 | ~80 |
| **Total** | **39** | **~140** |

### Core integration tests

| Test file | Assertions |
|---|---|
| `SimpleTextTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `MultiPageComplexTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `GraphicsTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `AnnotationsTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `FormFieldsTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `BookmarksTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `PageLabelsTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `DocumentFeaturesTest` | `assertQpdfValid(OUTPUT_FILE)` + `assertQpdfValid($outPath)` |
| `ExtGStateIntegrationTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `SignedPdfIntegrationTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `SignatureFieldIntegrationTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `FormAppearancesIntegrationTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `MarkupAnnotationsIntegrationTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `AnnotationSubtypesTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `MultimediaAndThreeDIntegrationTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `OpenTypeFontIntegrationTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `Type3FontIntegrationTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `GraphicsPipelineIntegrationTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `XRefStreamIntegrationTest` | `assertQpdfValid(OUTPUT_FILE)` |
| `EmbeddedFontsTest` | `assertQpdfValid($outPath)` |
| `EmbeddedType1FontTest` | `assertQpdfValid($outPath)` |

### Writer tests

`WriterTest`, `PdfTest`, `PdfIntegrationTest`, `KerningIntegrationTest`, `XmpMetadataTest`, `UnicodeFontTest` — assertions on `generate()`, `toBytes()`, and `save()` outputs.

### Toolkit tests

`AnnotationFlattenerTest`, `PdfStamperTest`, `PageTransformerTest`, `TextRedactorTest`, `PageSlicerTest`, `PdfMergerTest`, `PdfEncryptTest`, `BookmarkEditorTest`, `PageLabelerTest`, `FormFillerTest`, `MetadataEditorTest` — assertions on `toBytes()` and `save()` outputs.

## CI configuration

The QPDF Docker image is built in the `test` job and runs on every push and pull request:

```yaml
# .github/workflows/ci.yml
- name: Build validation tool images
  run: cd docker && docker compose build
```

## Manual usage

Validate any PDF from the command line:

```bash
# Check a single file
qpdf --check docs/sample-pdfs/simple_text.pdf

# Check all sample PDFs
for f in docs/sample-pdfs/*.pdf; do
  echo "Checking $f..."
  qpdf --check "$f"
done

# JSON output for programmatic inspection
qpdf --json docs/sample-pdfs/simple_text.pdf | jq .
```
