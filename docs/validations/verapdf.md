# veraPDF PDF/A Conformance Validation

[veraPDF](https://verapdf.org/) is the PDF Association's official open-source reference validator for PDF/A (ISO 19005) and PDF/UA (ISO 14289). It is the industry standard for archival and accessibility compliance testing.

**License:** GPL v3 (library), MPL 2.0 (validation profiles)

## What it validates

veraPDF checks compliance against PDF/A profiles defined in ISO 19005:

| Profile | Standard | Key requirements |
|---|---|---|
| `1a` | PDF/A-1 Level A (ISO 19005-1) | Tagged structure, Unicode mapping, embedded fonts, no encryption, no JavaScript, XMP metadata |
| `1b` | PDF/A-1 Level B (ISO 19005-1) | Visual appearance preservation — embedded fonts, ICC color, no encryption/JS |
| `2a` | PDF/A-2 Level A (ISO 19005-2) | Tagged + JPEG2000, transparency, layers, embedded files (PDF/A only) |
| `2b` | PDF/A-2 Level B (ISO 19005-2) | Visual preservation with PDF 1.7 features |
| `2u` | PDF/A-2 Level U (ISO 19005-2) | Level B + Unicode character mapping |
| `3a` | PDF/A-3 Level A (ISO 19005-3) | Level 2a + any embedded file format |
| `3b` | PDF/A-3 Level B (ISO 19005-3) | Level 2b + any embedded file format |
| `3u` | PDF/A-3 Level U (ISO 19005-3) | Level 2u + any embedded file format |
| `4` | PDF/A-4 (ISO 19005-4) | Based on PDF 2.0, replaces a/b/u levels |

veraPDF also supports PDF/UA-1 and PDF/UA-2 validation profiles for accessibility compliance.

## Installation

```bash
# Docker (recommended — no local install needed)
cd docker && docker compose pull

# Or install locally (requires Java 11+):
wget https://software.verapdf.org/releases/1.26/verapdf-installer.zip
unzip verapdf-installer.zip
cd verapdf-*/ && ./verapdf-install
export PATH="$HOME/verapdf:$PATH"
```

## How it works

The `VeraPdfValidationTrait` provides:

```php
// Assert PDF/A-1b compliance (default profile)
$this->assertVeraPdfCompliant('/path/to/file.pdf');

// Assert compliance with a specific profile
$this->assertVeraPdfCompliant('/path/to/file.pdf', '2b');
```

The trait:

1. Tries Docker first via `DockerToolRunner` (image: `verapdf/cli`)
2. Falls back to a local `verapdf` binary via `ExternalToolLocator`
3. If neither is found, calls `$this->markTestSkipped()` (explicit skip, not silent)
4. Runs `verapdf --format mrr -f <profile> <file>` and checks exit code
5. Parses the MRR XML output for `isCompliant="true"` confirmation
6. On failure, extracts up to 20 rule violation clauses for the assertion message

### Trait source

`tests/Support/VeraPdfValidationTrait.php` (`ApprLabs\Tests\Support\VeraPdfValidationTrait`)

## Coverage

veraPDF validation is opt-in via the `#[Group('verapdf')]` PHPUnit attribute. Currently 2 dedicated tests in `packages/pdf/core/tests/Conformance/PdfAConformanceTest.php`:

| Test | Description |
|---|---|
| `testMinimalPdfWithOutputIntent` | Generates a PDF with Info metadata, OutputIntent (sRGB / GTS_PDFA1), and validates PDF/A-1b compliance |
| `testVeraPdfToolchainWorks` | Generates a simple PDF and verifies veraPDF runs and produces a `validationReport` in its XML output |

### Running veraPDF tests

```bash
# Run only veraPDF tests
vendor/bin/phpunit --group verapdf

# Expected output when veraPDF is not installed:
# OK, but some tests were skipped!
# Tests: 2, Assertions: 2, Skipped: 2.
```

## CI configuration

veraPDF runs in a separate CI job, only on pushes to `main` (not on PRs) to avoid slowing the feedback loop:

```yaml
# .github/workflows/ci.yml
verapdf:
  runs-on: ubuntu-latest
  if: github.event_name == 'push' && github.ref == 'refs/heads/main'
  steps:
    - uses: actions/checkout@v4
      with:
        submodules: true
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.4'
        extensions: zlib, openssl, simplexml
        coverage: none
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
    - name: Pull veraPDF image
      run: docker pull verapdf/cli
    - name: Run PDF/A conformance tests
      run: vendor/bin/phpunit --group verapdf
```

## Manual usage

```bash
# Validate against PDF/A-1b
verapdf -f 1b docs/sample-pdfs/simple_text.pdf

# Machine-readable output (MRR XML)
verapdf --format mrr -f 1b docs/sample-pdfs/simple_text.pdf

# Check all sample PDFs
for f in docs/sample-pdfs/*.pdf; do
  echo "=== $f ==="
  verapdf --format mrr -f 1b "$f" 2>&1 | grep -o 'isCompliant="[^"]*"'
done
```
