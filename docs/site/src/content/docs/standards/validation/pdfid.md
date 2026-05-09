---
title: pdfid
description: Security scanner detecting JavaScript, auto-open actions, launch actions, and other suspicious PDF features.
---


[pdfid.py](https://github.com/DidierStevens/DidierStevensSuite) is Didier Stevens' lightweight PDF inspector (public domain). It scans a file for keywords associated with attack-prone features — `/JS`, `/JavaScript`, `/AA`, `/OpenAction`, `/Launch` — and reports their counts. The validation here doesn't ban those keys outright in arbitrary PDFs; it asserts that **phpdftk's own generated output** never produces files containing them, so the library's writer surface stays clear of the patterns malware authors exploit.

## What it catches

- `/JS` and `/JavaScript` — embedded JavaScript that some PDF readers will execute
- `/AA` — additional-actions dictionaries that fire on document/page events
- `/OpenAction` — actions that fire automatically when the document opens
- `/Launch` — actions that launch external applications or scripts

A clean run reports zero counts for all five. Any non-zero count fails the assertion.

## Installation

The Docker image is built locally from the project's `docker/` setup:

```bash
# Docker (recommended — no local install needed)
cd docker && docker compose build pdfid

# Local fallback (download Didier Stevens' script directly)
curl -O https://didierstevens.com/files/software/pdfid_v0_2_8.zip
unzip pdfid_v0_2_8.zip
chmod +x pdfid.py
mv pdfid.py /usr/local/bin/pdfid.py
```

## How it works

The `PdfIdValidationTrait` provides two methods:

```php
// Assert no suspicious indicators are present (markTestSkipped if pdfid missing)
$this->assertPdfIdClean('/path/to/file.pdf');

// Get raw output for custom assertions
$output = $this->runPdfIdRaw('/path/to/file.pdf');
```

`assertPdfIdClean()`:

1. Tries Docker first via `DockerToolRunner` (image: `phpdftk/pdfid`)
2. Falls back to local script via `ExternalToolLocator::find('pdfid.py')`
3. If neither is available, calls `markTestSkipped()`
4. Parses the output table for the five suspicious indicators
5. Fails if any indicator count is greater than zero, listing each violation in the assertion message

### Trait source

[`tests/Support/PdfIdValidationTrait.php`](https://github.com/phpdftk/phpdftk/blob/main/tests/Support/PdfIdValidationTrait.php) (`Phpdftk\Tests\Support\PdfIdValidationTrait`)

### Test source

[`packages/pdf/core/tests/Conformance/Tier4PdfIdTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/core/tests/Conformance/Tier4PdfIdTest.php) — Tier 4 integration test that generates representative fixtures and asserts each one passes pdfid's clean check.

## CI configuration

The pdfid image is built in the `test` and `compliance` jobs:

```yaml
# .github/workflows/ci.yml
- name: Build pdfid and PDFBox Preflight images
  run: cd docker && docker compose build pdfid pdfbox-preflight
```

## Manual usage

Scan a PDF directly:

```bash
# Docker
docker run --rm -v "$(pwd):/data" phpdftk/pdfid /data/file.pdf

# Local script
pdfid.py file.pdf
```

A typical clean output:

```
PDFiD 0.2.8 file.pdf
 PDF Header: %PDF-1.7
 obj                    7
 endobj                 7
 stream                 1
 endstream              1
 xref                   1
 trailer                1
 startxref              1
 /Page                  1
 /Encrypt               0
 /ObjStm                0
 /JS                    0
 /JavaScript            0
 /AA                    0
 /OpenAction            0
 /Launch                0
```

Any non-zero on `/JS` through `/Launch` is a finding.
