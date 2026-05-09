---
title: About the Suites
description: Six independent PDF validation tools verify every release — structural correctness, spec conformance, archival, accessibility, security.
---

Every PDF that phpdftk generates is verified by **six independent, industry-standard tools**. This isn't a self-assessment — these are the same tools used by national archives, print production houses, and accessibility auditors to verify PDF correctness.

## Results

**275 tests. 275 passed. 0 failed. 0 skipped.**

| Tool | Tests | What it proves |
|---|---|---|
| **QPDF** | 236 | Every xref entry, page tree link, stream length, and object reference is structurally correct |
| **Arlington** | 6 | Every dictionary key and value type matches the PDF specification tables |
| **veraPDF** | 2 | PDF/A output meets ISO 19005 archival requirements |
| **Matterhorn** | 6 | PDF/UA output meets ISO 14289 accessibility requirements |
| **JHOVE + Preflight + Security** | 25 | Well-formed format, PDF/A cross-validation, zero security indicators |

For the latest run from `main`, see [Latest Compliance Report](/standards/validation/report/).

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

## The four-tier system

Validation is organized into four tiers — structural integrity, spec conformance, accessibility, and edge-case robustness. Each tier targets a different class of bug.

### Tier 1 — Structural & Conformance (in CI)

| Suite | What it validates | Scope | CI behavior |
|---|---|---|---|
| [QPDF](/standards/validation/qpdf/) | Structural integrity (xref, page tree, streams, linearization, encryption) | All integration tests (168 assertions across 46 test files) | Runs on every push and PR |
| [Arlington PDF Model](/standards/validation/arlington/) | Dictionary-level spec conformance (keys, types, required fields, version constraints) | 5 core integration tests (6 assertions), expandable | Runs on every push and PR |
| [veraPDF](/standards/validation/verapdf/) | PDF/A archival conformance (ISO 19005), PDF/UA accessibility | Opt-in via `#[Group('verapdf')]` (2 dedicated tests) | Runs on pushes to `main` only |

### Tier 2 — Test Corpora

PDF test file collections from major PDF implementations. Used for stress-testing the reader's error tolerance and edge-case handling.

#### veraPDF Corpus (Isartor/Bavaria)

- **Source:** https://github.com/veraPDF/veraPDF-corpus
- **License:** Mixed (freely redistributable for testing)
- **Content:** 569 PDF/A-1b + 986 PDF/A-2b + 12 PDF/A-3b intentionally non-conformant files. Each violates specific ISO 19005 clauses. A correct validator must flag every file.
- **Submodule:** `vendor-data/verapdf-corpus`
- **PHPUnit group:** `#[Group('tier2')]`, `#[Group('tier2-pdfa')]`
- **Behavior:** Each PDF is run through veraPDF and asserted to be non-compliant (negative testing).

#### Poppler Test Files

- **Source:** https://gitlab.freedesktop.org/poppler/test
- **License:** Mixed (freely redistributable for testing)
- **Content:** ~80 PDFs covering fonts, transparency, annotations, encryption, CJK, damaged files, and rendering edge cases. Each file typically exercises a specific bug fix in the Poppler rendering engine (used by Evince, GNOME, LibreOffice).
- **Submodule:** `vendor-data/poppler-test`
- **PHPUnit group:** `#[Group('tier2')]`
- **Behavior:** Parsed with `PdfReader::fromFile()` in lenient mode. Encrypted and intentionally malformed PDFs are expected to throw — all other exceptions are test failures.

#### QPDF Test Suite

- **Source:** https://github.com/qpdf/qpdf (`qpdf/qtest/qpdf/`)
- **License:** Apache 2.0
- **Content:** ~700 test PDFs focused on structural PDF transformation — linearization, object streams, xref streams, encryption (all revisions), incremental updates, and damaged file recovery.
- **Submodule:** `vendor-data/qpdf`
- **PHPUnit group:** `#[Group('tier2')]`
- **Behavior:** Same as Poppler — parsed in lenient mode with expected-failure handling.

#### PDFium Test Resources

- **Source:** https://github.com/chromium/pdfium (`testing/resources/`)
- **License:** BSD 3-clause
- **Content:** ~300 test PDFs from Chrome's PDF engine covering rendering, JavaScript, XFA forms, annotations, encryption, linearization, and CJK.
- **Submodule:** `vendor-data/pdfium`
- **PHPUnit group:** `#[Group('tier2')]`, `#[Group('tier2-pdfium')]`
- **Behavior:** Parsed with `PdfReader` in lenient mode. Same error-tolerance pattern as Poppler/QPDF corpus.

#### Apache PDFBox Test Files

- **Source:** https://github.com/apache/pdfbox
- **License:** Apache 2.0
- **Content:** ~150 test PDFs covering digital signatures, encryption, form filling, font embedding, and incremental updates.
- **Submodule:** `vendor-data/pdfbox`
- **PHPUnit group:** `#[Group('tier2')]`, `#[Group('tier2-pdfbox')]`
- **Behavior:** Parsed with `PdfReader` in lenient mode.

### Tier 3 — Accessibility Compliance

Tools and test suites for PDF/UA (Universal Accessibility) and WCAG compliance.

#### Matterhorn Protocol (via veraPDF)

- **Source:** veraPDF's `ua1` profile implements Matterhorn Protocol checks
- **Content:** Positive tests (tagged PDFs pass UA-1) and negative tests (missing tagging, lang, DisplayDocTitle, annotation alt text fail UA-1). Exercises StructTreeRoot, MarkInfo, Lang, ViewerPreferences, and annotation accessibility.
- **PHPUnit group:** `#[Group('tier3')]`, `#[Group('verapdf')]`
- **Behavior:** Generates tagged and untagged PDFs, validates against veraPDF `ua1` profile.

**Not applicable:** Matterhorn Reference Test Suite (no downloadable corpus), PAC PDF Accessibility Checker (Windows only), W3C PDF Techniques (not a corpus), PDF/UA-2 Test Resources (emerging standard, veraPDF support pending).

### Tier 4 — Reference and Conformance Targets

General-purpose validation tools and reference document collections.

#### [JHOVE](/standards/validation/jhove/)

- **Source:** https://github.com/openpreservation/jhove
- **Maintainer:** Open Preservation Foundation
- **License:** LGPL v2.1
- **Docker image:** `openpreserve/jhove`
- **Content:** Format validation and characterization for PDF. Checks structure, xref integrity, stream lengths, font embedding, and metadata. Validates "Well-Formed and valid" status.
- **PHPUnit group:** `#[Group('tier4')]`
- **Behavior:** Generated PDFs validated via `JhoveValidationTrait`. Docker-first with local fallback.

#### PDF 2.0 Examples

- **Source:** https://github.com/pdf-association/pdf20examples
- **Maintainer:** PDF Association
- **Content:** 7 reference PDFs exercising PDF 2.0 features: page-level output intents, associated files, UTF-8 strings, incremental saves.
- **Submodule:** `vendor-data/pdf20examples`
- **PHPUnit group:** `#[Group('tier4')]`
- **Behavior:** Parsed with `PdfReader`, verified for version, page count, catalog. QPDF structural validation.

#### [Didier Stevens' pdfid](/standards/validation/pdfid/)

- **Source:** https://github.com/DidierStevens/DidierStevensSuite
- **License:** Public domain
- **Docker image:** `phpdftk/pdfid` (Python Alpine + pdfid.py)
- **Content:** Security scanner detecting JavaScript, auto-open actions, launch actions, and other suspicious PDF features.
- **PHPUnit group:** `#[Group('tier4')]`, `#[Group('tier4-security')]`
- **Behavior:** Generated PDFs validated via `PdfIdValidationTrait`. Asserts zero counts for `/JS`, `/JavaScript`, `/AA`, `/OpenAction`, `/Launch`.

#### [Apache PDFBox Preflight](/standards/validation/pdfbox-preflight/)

- **Source:** https://github.com/apache/pdfbox (preflight module)
- **License:** Apache 2.0
- **Docker image:** `phpdftk/pdfbox-preflight` (Java 17 + PDFBox 2.0 preflight-app JAR)
- **Content:** PDF/A-1b validation as a secondary cross-validator alongside veraPDF.
- **PHPUnit group:** `#[Group('tier4')]`
- **Behavior:** PDF/A-1b documents validated via `PdfBoxPreflightValidationTrait`. Exit code 0 = valid.

**Not applicable:** pdfaPilot (Callas) — commercial license, not viable for open-source CI.

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

### Quick start (Docker — recommended)

```bash
# Build/pull all validation tool images (one time)
cd docker && docker compose build && docker compose pull

# Initialize Arlington model (included as git submodule)
git submodule update --init

# Run all tests (QPDF + Arlington validate automatically via Docker)
vendor/bin/phpunit

# Run PDF/A conformance tests (veraPDF via Docker)
vendor/bin/phpunit --group verapdf

# Full compliance suite
mise run compliance
```

### Quick start (local binaries — alternative)

If you prefer local binaries over Docker, the traits fall back automatically:

```bash
brew install qpdf          # macOS
sudo apt-get install qpdf  # Ubuntu/Debian
```

The full compliance suite runs automatically on every push to `main` in CI.

---

## Architecture

All validation tools share a common Docker-first infrastructure under `tests/Support/`:

```
docker/
  docker-compose.yml                 # All validation tool images
  qpdf/Dockerfile                    # Alpine-based QPDF image

tests/Support/
  DockerToolRunner.php               # Generic Docker container execution
  DockerToolResult.php               # Docker execution result value object
  ExternalToolLocator.php            # Local binary finder (fallback)
  QpdfValidationTrait.php            # QPDF --check trait (Docker → local fallback)
  VeraPdfValidationTrait.php         # veraPDF trait (Docker → local fallback)
  JhoveValidationTrait.php           # JHOVE PDF-hul trait (Docker → local fallback)
  PdfIdValidationTrait.php           # pdfid.py security lint trait (Docker → local fallback)
  PdfBoxPreflightValidationTrait.php # PDFBox Preflight PDF/A-1b trait (Docker → local fallback)
  Arlington/
    ArlingtonLoader.php              # Parses 613 TSV specs from the Arlington model
    ArlingtonValidator.php           # Validates PdfDictionary objects against specs
    ArlingtonValidationTrait.php     # PHPUnit trait
    DictionarySpec.php               # One TSV file = one dictionary type
    FieldSpec.php                    # One TSV row = one field definition
    ValidationResult.php             # Errors + warnings container
```

### Docker-first execution

`DockerToolRunner` is the core abstraction. Every validation trait tries Docker first, then falls back to a local binary:

1. `DockerToolRunner::isAvailable()` — checks if Docker is running (cached)
2. `DockerToolRunner::hasImage($image)` — checks if the image exists locally (cached, does not auto-pull)
3. `DockerToolRunner::run($image, $args, $volumePath)` — runs the container with `-v $volumePath:/data`
4. `DockerToolRunner::tempDir()` — returns a Docker-mountable temp directory
5. `DockerToolRunner::isPathMountable($path)` — checks if a path is in a Docker-accessible location

If Docker is unavailable or the image hasn't been pulled, `ExternalToolLocator` checks for a local binary as a fallback.

### Graceful degradation

Each tool handles unavailability differently based on its role:

| Tool | If not installed | Rationale |
|---|---|---|
| QPDF | Silently returns (test passes) | Bonus structural check; existing assertions still validate |
| Arlington | `markTestSkipped()` | Spec validation is the primary purpose; visibility matters |
| veraPDF | `markTestSkipped()` | PDF/A tests are intentional opt-ins; skips should be visible |

### Adding validation to a new test

```php
use Phpdftk\Tests\Support\QpdfValidationTrait;
use Phpdftk\Tests\Support\Arlington\ArlingtonValidationTrait;

class MyNewIntegrationTest extends TestCase
{
    use QpdfValidationTrait;
    use ArlingtonValidationTrait;

    public function testGeneratesPdf(): void
    {
        $writer = new PdfWriter();
        // ... build PDF ...
        $writer->save($path);

        // Structural validation
        self::assertFileExists($path);
        $this->assertQpdfValid($path);          // file on disk
        $this->assertArlingtonValid($path);      // file on disk

        // Or for in-memory PDFs:
        $bytes = $writer->generate();
        $this->assertQpdfValidBytes($bytes);     // writes temp file, validates, cleans up
        $this->assertArlingtonValidBytes($bytes);
    }
}
```

### Adding a new validation tool

To integrate a new tool from any tier, follow this repeatable pattern:

1. **Define the Docker image** — add to `docker/docker-compose.yml` (official image or custom Dockerfile in `docker/<tool>/`)
2. **Create a validation trait** — `tests/Support/<Tool>ValidationTrait.php`:

```php
use Phpdftk\Tests\Support\DockerToolRunner;
use Phpdftk\Tests\Support\ExternalToolLocator;

trait FooValidationTrait
{
    protected function assertFooValid(string $pdfPath): void
    {
        // Docker first
        if (DockerToolRunner::isAvailable() && DockerToolRunner::hasImage('foo/foo')) {
            $result = DockerToolRunner::run(
                'foo/foo',
                ['--check', '/data/' . basename($pdfPath)],
                dirname($pdfPath),
            );
            self::assertSame(0, $result->exitCode, "foo failed:\n" . $result->output);
            return;
        }

        // Local binary fallback
        $binary = ExternalToolLocator::find('foo');
        if ($binary === null) {
            return; // or $this->markTestSkipped('foo not available');
        }
        // ... run locally ...
    }
}
```

3. **Adopt in tests** — `use FooValidationTrait;` in test classes
