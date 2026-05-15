# Contributing

Thank you for your interest in contributing to phpdftk!

> **AI agents and automated assistants:** see [AGENTS.md](AGENTS.md) for the canonical machine-readable project guide. Tool-specific aliases (`CLAUDE.md`, `.cursorrules`, `.github/copilot-instructions.md`) all symlink to it.

## Prerequisites

This project uses [mise](https://mise.jdx.dev/) to manage tool versions.

```bash
# Install mise (macOS)
brew install mise

# Activate mise in your shell
mise activate
```

## Getting Started

```bash
git clone https://github.com/phpdftk/phpdftk.git
cd phpdftk
mise install        # Installs PHP 8.4 + Node 24
composer install
```

**Required PHP extensions:** `zlib`, `openssl`, `simplexml`.

## Dev Commands

All tasks are defined in `.mise.toml` and run via `mise run`:

| Command | Description |
|---|---|
| `mise run test` | Run all tests |
| `mise run test -- --testsuite core` | Run a single test suite |
| `mise run test -- --filter testName` | Run a single test method |
| `mise run analyse` | Run PHPStan static analysis (level 6) |
| `mise run lint` | Check code style (PHP CS Fixer, dry-run) |
| `mise run lint:fix` | Auto-fix code style |
| `mise run benchmark` | Run benchmarks, generate `docs/generated/benchmarks.md` |
| `mise run coverage` | Generate code coverage report and badge |
| `mise run compliance` | Run external compliance validation (Docker required) |

## Repository Layout

```
phpdftk/
  packages/           # 16 Composer packages (see README for full list)
    pdf/core/         #   PDF object model + file serialization
    pdf/writer/       #   Ergonomic builder facade
    pdf/reader/       #   PDF parser
    pdf/toolkit/      #   High-level pipelines (merge, stamp, encrypt, etc.)
    pdf/conformance/  #   PDF/A, PDF/UA, PDF/X, PDF/VT, PDF/E, PDF/R validation
    pdf/all/          #   Metapackage bundle
    geometry/         #   Rectangle, Matrix, PageSize, BezierCurve
    color/            #   RGB/CMYK/Gray color models
    encoding/         #   Encoding tables, Adobe Glyph List, CMap parser
    filesystem/       #   Local-file I/O guard (rejects php://, http://, ... wrappers)
    filters/          #   FlateDecode, ASCII85, LZW, CCITTFax, JBIG2 codecs
    font-metrics/     #   AFM metrics for 14 standard PDF fonts
    font-parser/      #   TrueType/OpenType/Type1/WOFF/CFF font parsing
    image-metadata/   #   JPEG/PNG/GIF/TIFF/WebP header parsing
    xmp/              #   XMP metadata read/write
    crypt/            #   AES/RC4 encryption, PDF key derivation, PKCS#7
  benchmarks/         # phpbench performance benchmarks
  scripts/            # Shell scripts backing composer tasks (and CI helpers)
  docker/             # Dockerfiles for compliance validation tools
  docs/               # Documentation (see below)
  docs/generated/     # Auto-generated output (benchmarks, compliance, coverage badge)
  docs/site/          # Astro documentation site
```

### docs/ Contents

| File | Auto-generated? | Description |
|---|---|---|
| `generated/benchmarks.md` | Yes (`mise run benchmark`) | Performance comparison vs FPDF, TCPDF, mPDF, Dompdf |
| `generated/compliance.md` | Yes (`mise run compliance`) | External tool validation (QPDF, Arlington, veraPDF, JHOVE) |
| `generated/coverage-badge.svg` | Yes (`mise run coverage`) | Code coverage badge |
| `spec-coverage.md` | No | ISO 32000-2:2020 field-level coverage tracker |
| `version-coverage.md` | No | PDF version feature map (1.0–2.0) with source links |
| `iso-standards-coverage.md` | No | ISO conformance map (PDF/A, PDF/UA, PDF/X, etc.) with source links |

## Docker (no mise required)

If you don't have mise installed, you can use Docker instead:

```bash
# Run tests
docker compose -f docker-compose.dev.yml run --rm test

# Run static analysis
docker compose -f docker-compose.dev.yml run --rm analyse

# Run linter
docker compose -f docker-compose.dev.yml run --rm lint
```

## CI Checks

Every PR against `main` triggers the workflows in `.github/workflows/ci.yml`. All of the following checks must pass before the PR can merge.

| Job | What it does | Fails the check when… |
|---|---|---|
| `test` | phpunit (no coverage) · `composer analyse` (PHPStan) · `composer lint` (PHP CS Fixer dry-run) · `composer audit` | Any test fails, any static-analysis violation, any lint violation, or any unresolved security advisory |
| `coverage` | phpunit with pcov coverage; uploads `clover.xml` + HTML | phpunit fails |
| `publish-coverage` | Generates badge + `coverage.json`; pushes per-PR snapshot to the `_coverage` orphan branch and uploads `coverage-json` for the aggregator | Coverage report generation fails |
| `phpdoc` | Generates combined and per-package API docs via phpDocumentor; pushes per-PR snapshot to `_phpdoc` | **Any package fails to build** (per-package failures are collected and rolled into a single non-zero exit at the end so all errors are visible in one run) |
| `compliance` | Full Docker-backed conformance suite: QPDF · Arlington · veraPDF · JHOVE · Matterhorn · PDFBox Preflight | **Any suite reports `FAIL`, `NO TESTS`, or `WARN`** (the latter means the validation tool was unavailable and the suite's PASS is untrusted) |
| `benchmarks` | phpbench aggregate run vs FPDF / TCPDF / mPDF / Dompdf / smalot/pdfparser / setasign/fpdi | The phpbench run itself fails (perf regressions are gated by `pr-report` below, not by this job) |
| `pr-report` | Aggregator: downloads coverage / benchmark / compliance artifacts, fetches the latest `main` baselines from the `_coverage` / `_benchmarks` / `_compliance` orphan branches, and posts a single `<!-- pr-report -->` comment with deltas | **Coverage drops by more than 1.0 percentage point**, OR **any phpdftk benchmark row regresses by more than 15 %** vs the most recent `main` baseline |
| `validate` | `composer validate --strict --no-check-all --no-check-lock` per package `composer.json` | Any package's manifest fails strict validation |

### Threshold rationale

- **Coverage: −1.0 pp.** Strict enough to catch real regressions, lenient enough to absorb refactors that legitimately remove well-covered code.
- **Benchmark: +15 %.** Comfortably above the ±5–10 % run-to-run variance on shared GitHub-hosted runners — false-positive rate is low. The PR comment additionally highlights any regression ≥ ±10 % in bold (without failing the gate) so reviewers see drift earlier than the merge block fires.
- **Compliance: FAIL + NO TESTS + WARN.** Beyond actual test failures, `NO TESTS` usually means a corpus submodule didn't initialize and `WARN` means a validation tool was unavailable; both leave the suite's verdict untrustworthy and so are treated as non-passing.

### First-run baselines

The first PR opened after the `pr-report` gating lands will have no `main` baselines (the `latest/*.json` files don't exist on the orphan branches yet). In that case coverage / benchmark deltas render as `—`, both gates pass automatically, and the comment includes a footer noting that the baselines will populate after the next push to `main`. From then on, every PR is gated against the most recent baseline.

### Forks

Comment posting requires `pull-requests: write` on `GITHUB_TOKEN`, which GitHub restricts on fork-originated PRs. On those PRs the comment step fails silently but the underlying gates still apply — a coverage drop or benchmark regression still fails the `pr-report` job and blocks merge.

## Pull Request Guidelines

1. **Fork and branch** from `main`.
2. **Write tests** for new features and bug fixes.
3. **Run checks** before submitting: `composer test`, `composer analyse`, `composer lint`.
4. **Keep PRs focused** — one feature or fix per PR.
5. **Follow existing patterns** — match the code style, naming conventions, and architecture of the surrounding code.
6. **Sign your commits** — this project uses the [Developer Certificate of Origin (DCO)](https://developercertificate.org/). Add `Signed-off-by` to your commits with `git commit -s`.

## Reporting Bugs

Open an issue at [github.com/phpdftk/phpdftk/issues](https://github.com/phpdftk/phpdftk/issues) with:

- PHP version and OS
- Minimal reproduction steps
- Expected vs. actual behavior
