# AGENTS.md

Instructions for AI coding assistants (Claude Code, Cursor, GitHub Copilot, Cline, Aider, etc.) working in this repository. This is the canonical agent-instruction file; `CLAUDE.md`, `.cursorrules`, and `.github/copilot-instructions.md` are symlinks to it.

## What this is

phpdftk is a PHP 8.4 monorepo for generating, parsing, and validating PDFs against ISO 32000-2:2020. It maps every PDF spec object type to a PHP class with `/Field` ↔ camelCase property correspondence. Zero runtime dependencies beyond `zlib`, `openssl`, `simplexml`.

The repo publishes 16 Composer packages under the `phpdftk/*` vendor and the `Phpdftk\` PSR-4 namespace root. For the canonical package list and per-package descriptions, see `README.md` and the `composer.json` files under `packages/`. Do not duplicate that table here.

## Commands

All workflows are exposed as `composer <task>`. Trivial wrappers (`analyse`, `lint`, `lint:fix`) are inlined in `composer.json`; the rest delegate to `scripts/`.

| Command | What it does |
|---|---|
| `composer test` | Run all PHPUnit suites (suites defined in `phpunit.xml`) |
| `composer test -- --testsuite core` | Run one suite — suite names match package directories |
| `composer analyse` | PHPStan |
| `composer lint` / `composer lint:fix` | PHP-CS-Fixer (PSR-12) |
| `composer coverage` | Coverage report + badge |
| `composer benchmark` | PHPBench → regenerates `docs/site/src/content/docs/standards/performance/benchmarks.md` |
| `composer compliance` | Run external validator suites (verapdf, qpdf, pdfbox, jhove, pdfid, arlington) — needs Docker |
| `composer phpdoc` | Regenerate API docs |

`mise run <task>` is also available if `mise` is installed; see `CONTRIBUTING.md` for the human contributor flow.

## Design principles

- **Every PDF object is a PHP class** extending `PdfObject` (in `Core\`) — OR implementing `Serializable` for inline/embedded dictionaries.
- **Properties match PDF field names** in camelCase (e.g., `/MediaBox` → `$mediaBox`).
- **Each class has a `PDF_TYPE` constant** with the `/Type` value from the spec.
- **`toPdf(): string`** on every object serializes it to raw PDF syntax.
- **`toIndirectObject(): string`** on `PdfObject` wraps output in `X Y obj ... endobj`.

## `PdfObject` vs. `Serializable` (the most important distinction)

When adding a new PDF dictionary type, decide: **does it need to be referenced from elsewhere via `X 0 R`?** That single question determines the base class.

**Extend `PdfObject`** when:
- The object is referenced from elsewhere via an indirect reference.
- It needs an object number assigned by `Core\File\ObjectRegistry`.
- It is serialized as an indirect object (`5 0 obj ... endobj`).
- It must be registered via `PdfWriter::register()` / `PdfFileWriter::register()` or a dedicated `addX()` method.
- Examples: `Page`, `Font`, `Annotation`, `Outline`, `OutlineItem`, `PageLabel`.

**Implement `Serializable`** when:
- The dictionary only ever appears nested inside another object's dictionary.
- It never gets an object number and is never registered with `ObjectRegistry`.
- It is serialized inline as part of the parent object.
- Examples: `TransitionDict` (nested in `Page`), `BorderStyle` (nested in `Annotation`).

`PdfObject` extends `Serializable`, so `PdfObject` is the strict superset.

## Where things live

Top-level layout under `packages/`:

| Dir | Purpose |
|---|---|
| `pdf/all/` | Metapackage `phpdftk/pdf` — bundles core + writer + reader |
| `pdf/core/` | `Phpdftk\Pdf\Core\` — PDF object model + byte-level file serialization (`File\PdfFileWriter`) |
| `pdf/writer/` | `Phpdftk\Pdf\Writer\` — ergonomic builder over `PdfFileWriter` |
| `pdf/reader/` | `Phpdftk\Pdf\Reader\` — parse existing PDFs into the object model, extract text |
| `pdf/toolkit/` | `Phpdftk\Pdf\Toolkit\` — high-level pipelines (FormFiller, PdfStamper, PageSlicer, PdfMerger, etc.) |
| `pdf/conformance/` | `Phpdftk\Pdf\Conformance\` — ISO subset validation (PDF/A, UA, X, VT, E, R, ZUGFeRD, mail) |
| `geometry/`, `color/`, `filters/`, `encoding/`, `filesystem/`, `font-metrics/`, `font-parser/`, `image-metadata/`, `xmp/`, `crypt/` | Standalone support packages — no PDF dependency |

Inside `packages/pdf/core/src/`, sub-namespaces mirror PDF spec sections: `Document/`, `Annotation/`, `Action/`, `Graphics/` (with `ColorSpace/`, `XObject/`, `Function/`, `Shading/`, `Pattern/`), `Font/` (with `FontFile/`), `Interactive/Form/`, `Interactive/Signature/`, `Security/`, `Content/`, `File/`, `FileSpec/`, `Multimedia/`, `ThreeD/`, `Filter/`. To find a class, look there first or `grep -r "class Foo" packages/`.

`writer` and `reader` never depend on each other. Support packages (`color`, `geometry`, etc.) have no PDF dependency and can be used standalone.

## Writer package: two layers

The writer has two distinct API levels — pick the right one:

1. **`Phpdftk\Pdf\Writer\Pdf`** — top-level high-level API. Stateful cursor, default font, theme. Methods: `setFont`, `setTheme`, `addPage`, `newPage`, `addText`, `addHeading`, `addSpacer`, `addRule`, `addImage`, `save`, `toBytes`, `writeTo`. Handles word wrap, auto-pagination, automatic standard-font registration. Limited to the 14 standard fonts. Use when you don't need custom fonts or precise graphics state.

2. **`Phpdftk\Pdf\Writer\PdfWriter`** — ergonomic builder over the object model. Methods: `addPage`, `addFont`, `addContentStream`, `addImage`, `setOutline`, `setPageLabels`, `setNamedDestinations`, `register`, `setSigner`, `setInfo`, `setLinearized`, `setConformance`, `generate`, `toBytes`, `writeTo`, `save`. Requires knowledge of fonts, content-stream operators, and resource names — but gives full object-model access.

Both ultimately delegate to `Phpdftk\Pdf\Core\File\PdfFileWriter` for byte emission.

Drop down to `Pdf::writer()` when you need the lower layer from the higher one.

## ContentStream

`Phpdftk\Pdf\Core\Content\ContentStream` extends `PdfStream` with a fluent API for all PDF content operators. Method names map 1:1 to PDF operators (`BT`/`ET` → `beginText()`/`endText()`, `Tf` → `setFont()`, `Tj` → `showText()`, etc.). For the full surface, read the class file directly.

**Footgun:** `escapeString(string): string` returns the value **already wrapped in parens**. Do not wrap again when composing operator strings.

## File I/O

All local-file access goes through `Phpdftk\Filesystem\LocalFilesystem` (in `packages/filesystem/`). It's the only sanctioned place to call `fopen`, `file_get_contents`, `file_put_contents`, etc. on a user-supplied path. The wrapper:

- rejects stream-wrapper paths (`php://`, `http://`, `data://`, `phar://`, …) via `assertLocalPath()`, blocking SSRF / arbitrary-stream reads through `fileNameLikeArgs`;
- normalises failures into `RuntimeException` with a labelled message (e.g., `"Cannot read font file: /path"`);
- centralises the `createDirectories: true` policy for `writeFile()`.

API surface:

- `LocalFilesystem::readFile(string $path, string $label = 'file'): string`
- `LocalFilesystem::readPrefix(string $path, int $length, string $label = 'file'): string`
- `LocalFilesystem::openReadable(string $path, string $label = 'file'): resource`
- `LocalFilesystem::writeFile(string $path, string $bytes, bool $createDirectories = false): void`
- `LocalFilesystem::assertReadableFile(string $path, string $label = 'file'): void`
- `LocalFilesystem::assertLocalPath(string $path): void`

**Rule:** when you add code that reads or writes a local file, use these helpers — do not call PHP's filesystem primitives directly. Every package that touches the filesystem (`pdf-core`, `pdf-reader`, `pdf-writer`, `pdf-toolkit`, `font-parser`, `image-metadata`) already depends on `phpdftk/filesystem`; add it to `composer.json` if you introduce a new package that needs disk I/O.

## Annotations

The abstract base `Phpdftk\Pdf\Core\Annotation\Annotation` owns `$bs` (typed `Serializable|null`) for border styles. **Never redeclare `$bs` in annotation subclasses** — assign `BorderStyle` (or any other `Serializable`) directly to the inherited property.

## Version gating

The library tracks which PDF version each feature requires and auto-bumps the document version when needed.

- `#[RequiresPdfVersion(PdfVersion::V1_6)]` on a class or property → that feature requires PDF ≥ 1.6.
- `#[DeprecatedPdfFeature(since: '2.0', replacement: '...', removedIn: '2.0')]` → marks deprecated features; the optional `removedIn` field enables strict enforcement.
- The check happens **at registration time** with `PdfFileWriter`/`PdfWriter`, not at construction.
- Default behavior auto-bumps the document version and records a warning in `getVersionWarnings()`.
- `setStrictVersionMode(true)` throws `VersionRequirementException` instead of bumping.
- Encryption gates are baked in: `PdfEncryptor::getMinimumPdfVersion()` returns `V1_4` (RC4), `V1_6` (AES-128), `V2_0` (AES-256).

When you add a new PDF spec feature, annotate the class or property with `#[RequiresPdfVersion]` at the correct minimum version.

## Conformance

`packages/pdf/conformance/` validates PDFs against eight ISO/industry profiles: **PDF/A**, **PDF/UA**, **PDF/X**, **PDF/VT**, **PDF/E**, **PDF/R**, **ZUGFeRD/Factur-X**, **PDF/mail**. Each profile lives as an enum implementing `ConformanceProfile`.

Pattern for adding a constraint:

1. Implement `ConformanceConstraint` under `packages/pdf/conformance/src/Constraint/`.
2. Register the constraint against the relevant profile(s) in `ProfileConstraintRegistry`.
3. Add a unit test under `packages/pdf/conformance/tests/Constraint/`.
4. Add or extend an end-to-end integration test under `packages/pdf/conformance/tests/Integration/`.

Writers opt in via `PdfWriter::setConformance(ConformanceProfile $profile, bool $strict = true)`. Strict mode throws on violations; lenient mode collects them into `ConformanceResult`.

## Tests

Tests live under each package in `packages/<pkg>/tests/`. The root `phpunit.xml` discovers them by named suite — suite names match package directories (`core`, `writer`, `reader`, `toolkit`, `conformance`, plus support packages).

Conventions:
- **Unit tests** verify class behavior in isolation.
- **Integration tests** must produce a real PDF, write it to `packages/pdf/core/tests/output/` (gitignored), and assert the file begins with `%PDF-`.
- Follow the existing folder/naming patterns when adding new tests; don't invent a new layout.
- Submodule fixtures live under `vendor-data/` (see `.gitmodules`); don't copy them into the repo.

Run a single suite: `composer test -- --testsuite core`. Run a single file: `vendor/bin/phpunit packages/pdf/core/tests/Document/SimpleTextTest.php`. Run by filter: `vendor/bin/phpunit --filter testGeneratesSimpleTextPdf`.

## Benchmarks

`composer benchmark` runs PHPBench and regenerates `docs/site/src/content/docs/standards/performance/benchmarks.md` via `scripts/parse-benchmarks.php`. Suites live in `benchmarks/`; configuration is in `phpbench.json`.

Add a benchmark under `benchmarks/` for any new public writer/reader/toolkit API or any change that could affect a hot path. Name and structure new benches to match the existing files.

## Docs

The canonical agent-readable docs live under `docs/site/src/content/docs/` (Astro Starlight site, served at phpdftk.dev). The "Standards & Performance" section is partly generated: the latest benchmarks (`standards/performance/benchmarks.md`) and the latest compliance report (`standards/validation/report.md`) are populated by CI from the `_benchmarks` and `_compliance` orphan branches — do not hand-edit those two files.

Generators:
- `scripts/parse-benchmarks.php` — phpbench → `docs/generated/benchmarks.md` (+ JSON sibling); CI publishes to `_benchmarks` branch and embeds into `standards/performance/benchmarks.md`.
- `scripts/parse-compliance.php` — compliance suites → `docs/generated/compliance.md` (+ JSON sibling). Hard-fails on regressions; CI publishes to `_compliance` branch and embeds into `standards/validation/report.md`.
- `scripts/build-pr-comment.php` — assembles the CI PR comment from the JSON siblings.
- `scripts/generate-badge.php` — coverage SVG badge.

When you add a new spec feature, update `docs/site/src/content/docs/standards/spec/coverage.md` to reflect the new coverage. Do not recreate the legacy paths `docs/generated/`, `docs/spec-coverage.md`, `docs/version-coverage.md`, or `docs/iso-standards-coverage.md` — they were intentionally migrated to `docs/site/`.

## New-feature checklist

A feature is **not done** until all four exist:

1. **Unit tests** under `packages/<pkg>/tests/` that exercise the class API.
2. **Integration test** that produces a real PDF and asserts it begins with `%PDF-` (and, where applicable, round-trips through `PdfReader`).
3. **Benchmark** under `benchmarks/` if the feature is a public writer/reader/toolkit API or affects a hot path.
4. **Doc page** or update under `docs/site/src/content/docs/` covering the new API.

## Style and gates

- PHP 8.4. PSR-12 enforced via PHP-CS-Fixer.
- PHPStan must pass at the configured level.
- Run `composer lint:fix && composer analyse && composer test` before declaring a change done.
- Commits must be DCO-signed (`git commit -s`) — see `CONTRIBUTING.md`.

## Agent skills

### Issue tracker

Issues live in GitHub Issues at `https://github.com/phpdftk/phpdftk/issues`, accessed via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The five canonical triage roles map onto GitHub labels (plus the open/closed bit for `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
