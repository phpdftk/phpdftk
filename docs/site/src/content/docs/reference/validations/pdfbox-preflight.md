---
title: Apache PDFBox Preflight
description: PDF/A-1b conformance validation via the Apache PDFBox preflight module.
---


[Apache PDFBox](https://github.com/apache/pdfbox) is a long-established Java toolkit for PDF processing (Apache 2.0). Its `preflight` module is a dedicated PDF/A-1b validator — an independent second opinion to veraPDF for the same ISO 19005-1 conformance level. Running both in CI catches divergent interpretations of the spec that either implementation alone might miss.

## What it catches

- PDF/A-1b violations Apache's interpretation of ISO 19005-1 flags but veraPDF accepts (and vice versa)
- Font embedding gaps the JBoss preflight rules detect
- Color space and OutputIntent mismatches under PDF/A-1b's tighter constraints
- Structural anomalies (xref, page tree, streams) that pass general validators but fail archival rules
- Metadata XMP packet errors specific to PDF/A-1 identification

## Installation

The Docker image is built locally from the project's `docker/` setup:

```bash
# Docker (recommended — no local install needed)
cd docker && docker compose build pdfbox-preflight

# Local fallback (if you have the Apache PDFBox preflight CLI installed)
# Most distributions don't ship preflight as a standalone binary —
# Docker is the practical path.
```

## How it works

The `PdfBoxPreflightValidationTrait` provides two methods:

```php
// Assert PDF/A-1b conformance (markTestSkipped if Preflight missing)
$this->assertPdfBoxPreflightValid('/path/to/file.pdf');

// Get raw output for custom assertions
$output = $this->runPdfBoxPreflightRaw('/path/to/file.pdf');
```

`assertPdfBoxPreflightValid()`:

1. Tries Docker first via `DockerToolRunner` (image: `phpdftk/pdfbox-preflight`)
2. Falls back to local binary via `ExternalToolLocator::find('preflight')`
3. If neither is available, calls `markTestSkipped()`
4. Runs the preflight container against the file and asserts exit code 0
5. On failure, includes the first 2 KB of preflight output in the assertion message

### Trait source

[`tests/Support/PdfBoxPreflightValidationTrait.php`](https://github.com/phpdftk/phpdftk/blob/main/tests/Support/PdfBoxPreflightValidationTrait.php) (`Phpdftk\Tests\Support\PdfBoxPreflightValidationTrait`)

### Test source

[`packages/pdf/core/tests/Conformance/Tier4PdfBoxPreflightTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/core/tests/Conformance/Tier4PdfBoxPreflightTest.php) — Tier 4 integration test that generates PDF/A-1b fixtures and asserts both veraPDF and PDFBox Preflight accept them.

## CI configuration

The PDFBox Preflight image is built in the `test` and `compliance` jobs:

```yaml
# .github/workflows/ci.yml
- name: Build pdfid and PDFBox Preflight images
  run: cd docker && docker compose build pdfid pdfbox-preflight
```

## Manual usage

Validate a PDF/A-1b file via the Docker image directly:

```bash
docker run --rm -v "$(pwd):/data" phpdftk/pdfbox-preflight /data/file.pdf
echo "exit=$?"
# exit 0 means the file is valid PDF/A-1b
```
