---
title: JHOVE
description: Well-formed-and-valid PDF format validation via the PDF-hul module.
---


[JHOVE](https://github.com/openpreservation/jhove) is the Open Preservation Foundation's format validator (LGPL 2.1). Its `PDF-hul` module — the PDF Hierarchy Universal Loader — assesses PDF format conformance, returning a "Well-Formed and valid" status when the file's structure is sound and a "Not well-formed" or "Well-Formed, but not valid" status with explanatory messages when something is off.

## What it catches

- Malformed file structure that QPDF's `--check` may parse but JHOVE's preservation-grade analyzer flags
- Cross-reference inconsistencies that fall short of "valid" archival format
- Trailer/header anomalies the preservation community treats as risk factors
- PDF version mismatches between header and structural features
- Stream encoding issues that compromise long-term readability

## Installation

```bash
# Docker (recommended — no local install needed)
docker pull openpreserve/jhove

# Or install locally as a fallback:
brew install jhove          # macOS
sudo apt-get install jhove  # Ubuntu/Debian
```

## How it works

The `JhoveValidationTrait` provides two methods:

```php
// Assert a PDF is well-formed and valid (markTestSkipped if JHOVE missing)
$this->assertJhoveValid('/path/to/file.pdf');

// Get raw output for custom assertions
$output = $this->runJhoveRaw('/path/to/file.pdf');
```

`assertJhoveValid()`:

1. Tries Docker first via `DockerToolRunner` (image: `openpreserve/jhove`)
2. Falls back to local binary via `ExternalToolLocator::find('jhove')`
3. If neither is available, calls `markTestSkipped()` (test passes on existing assertions)
4. Runs `jhove -m PDF-hul -h xml <file>` and parses the `<status>` element
5. Asserts the status equals `Well-Formed and valid`
6. On failure, includes the first 2 KB of JHOVE output in the assertion message

### Trait source

[`tests/Support/JhoveValidationTrait.php`](https://github.com/phpdftk/phpdftk/blob/main/tests/Support/JhoveValidationTrait.php) (`Phpdftk\Tests\Support\JhoveValidationTrait`)

### Test source

[`packages/pdf/core/tests/Conformance/Tier4JhoveTest.php`](https://github.com/phpdftk/phpdftk/blob/main/packages/pdf/core/tests/Conformance/Tier4JhoveTest.php) — Tier 4 integration test that generates fixture PDFs and runs each through JHOVE.

## CI configuration

The JHOVE Docker image is pulled in the `test` and `compliance` jobs:

```yaml
# .github/workflows/ci.yml
- name: Pull JHOVE image
  run: docker pull openpreserve/jhove
```

## Manual usage

Validate any PDF from the command line:

```bash
# Single file (XML output)
jhove -m PDF-hul -h xml docs/sample-pdfs/simple_text.pdf

# Plain text output
jhove -m PDF-hul docs/sample-pdfs/simple_text.pdf

# Look only for the status line
jhove -m PDF-hul -h xml file.pdf | grep -oE '<status>[^<]+</status>'
```
