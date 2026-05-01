# PDF Subset Conformance Support

## Context

The `pdf-conformance` package validates PDF subset constraints at `generate()` time when a profile is active. Set a conformance profile on `PdfWriter` and the library auto-injects XMP identification, pins the PDF version, and runs all applicable constraints before emission.

## Standards Covered

| Standard | ISO | Levels | Status |
|---|---|---|---|
| PDF/A | 19005 | 1a/1b, 2a/2b/2u, 3a/3b/3u, 4/4e/4f | **Implemented** — 10 constraints, all levels enforced |
| PDF/UA | 14289 | UA-1, UA-2 | **Implemented** — 6 constraints, tagged structure + accessibility enforced |
| PDF/X | 15930 | X-1a:2003, X-3:2003, X-4 | **Implemented** — 7 constraints, print production enforced |
| PDF/VT | 16612 | VT-1, VT-2, VT-2s | **Implemented** — 7 constraints, extends PDF/X-4 + DPartRoot |
| PDF/E | 24517 | E-1 | **Implemented** — 3 constraints (fonts, metadata, encryption) |
| PDF/R | 23504 | R-1 | **Implemented** — 2 constraints (metadata, encryption) |

## Architecture

New package: `packages/pdf/conformance/` (`ApprLabs\Pdf\Conformance\`)
- Depends on `apprlabs/pdf-core` and `apprlabs/xmp`
- `apprlabs/pdf-writer` gains a `suggests` on it
- Core stays standard-agnostic; writer holds an optional `ConformanceMode` and delegates checking

### Key Design Decisions

1. **Lazy validation** at `generate()` time (matches existing `getVersionWarnings()` pattern), with optional `checkConformance()` for early advisory feedback
2. **Multiple profiles** supported simultaneously (e.g., PDF/A-2a + PDF/UA-1) — validator runs all constraint sets, merges results
3. **Conformance builds its own XMP** via `ConformanceXmpWriter` (PDF/A requires specific RDF types like `rdf:Alt` for `dc:title` that generic `XmpWriter` doesn't handle) — follows the pattern already in `PdfAConformanceTest::buildPdfAXmp()`
4. **DocumentInspector abstraction** makes constraints work against both writer state (Phase 1) and reader-parsed PDFs (Phase 6)

### Package Structure

```
packages/pdf/conformance/
  composer.json
  src/
    Profile/
      ConformanceProfile.php           -- interface
      PdfAProfile.php                  -- enum (11 cases)
      PdfXProfile.php                  -- enum (3 cases)
      PdfUaProfile.php                 -- enum (2 cases)
      PdfEProfile.php                  -- enum (1 case)
      PdfVtProfile.php                 -- enum (3 cases)
      PdfRProfile.php                  -- enum (1 case)
    Constraint/
      ConformanceConstraint.php        -- interface: check(DocumentInspector, ConformanceProfile): list<Violation>
      FontEmbeddingConstraint.php      -- all fonts embedded, ToUnicode CMaps
      ColorSpaceConstraint.php         -- device-independent color or OutputIntent coverage
      MetadataConstraint.php           -- XMP required, identification schema, Info sync
      TransparencyConstraint.php       -- prohibited in PDF/A-1, PDF/X-1a
      EncryptionConstraint.php         -- prohibited in PDF/A
      JavaScriptConstraint.php         -- prohibited in PDF/A
      TaggedStructureConstraint.php    -- MarkInfo, StructTreeRoot, heading order, alt text
      OutputIntentConstraint.php       -- required OutputIntent subtype + ICC profile
      AnnotationConstraint.php         -- /Contents or /Alt for PDF/UA
      ActionConstraint.php             -- restricted action types per standard
      TrimBoxConstraint.php            -- /TrimBox required for PDF/X
      EmbeddedFileConstraint.php       -- prohibited in PDF/A-1/2, allowed in PDF/A-3+
      FilterConstraint.php             -- no LZWDecode in PDF/A-1
    Inspection/
      DocumentInspector.php            -- interface
      WriterDocumentInspector.php      -- wraps PdfWriter/PdfFileWriter
      ReaderDocumentInspector.php      -- (Phase 6) wraps PdfReader
    Result/
      ConformanceResult.php            -- profile + isCompliant + violations[]
      ConformanceViolation.php         -- clause, message, severity, objectPath
      ViolationSeverity.php            -- enum: ERROR, WARNING
    Validator/
      ConformanceValidator.php         -- orchestrator
      ProfileConstraintRegistry.php    -- maps profile -> constraint set
    Metadata/
      ConformanceXmpWriter.php         -- builds identification XMP per profile
    ConformanceMode.php                -- value object: profiles[] + strict/lenient
  tests/
    Profile/PdfAProfileTest.php
    Constraint/FontEmbeddingConstraintTest.php
    ... (one test per constraint)
    Integration/PdfA1bIntegrationTest.php
    Integration/PdfA2bIntegrationTest.php
    ...
```

### ConformanceProfile Interface

```php
interface ConformanceProfile
{
    public function getFamily(): string;        // 'PDF/A', 'PDF/X', etc.
    public function getLevel(): string;         // '1b', 'X-4', 'UA-1', etc.
    public function getPdfVersion(): PdfVersion; // minimum required version
    public function getXmpNamespace(): string;   // XMP identification namespace URI
    public function getXmpProperties(): array;   // ['pdfaid:part' => '1', 'pdfaid:conformance' => 'B']
}
```

### Writer Integration

```php
// PdfWriter gains:
public function setConformance(ConformanceProfile ...$profiles): void;
public function checkConformance(): array; // returns ConformanceResult[]

// In generate():
// 1. Auto-inject XMP identification
// 2. Pin PDF version to profile minimum
// 3. Run ConformanceValidator
// 4. Collect violations in getConformanceViolations()
// 5. In strict mode: throw on ERROR violations
```

### Result Objects

```php
final class ConformanceViolation {
    public string $clause;          // ISO clause, e.g. "6.3.4"
    public string $message;
    public ViolationSeverity $severity;
    public ?string $objectPath;     // e.g. "Page[0].Resources.Font[F1]"
}

final class ConformanceResult {
    public ConformanceProfile $profile;
    public bool $isCompliant;
    public array $violations;       // ConformanceViolation[]
}
```

## Implementation Phases

### ~~Phase 1: Foundation + PDF/A-1b~~ (DONE)
**Created:** 17 new files in `packages/pdf/conformance/`, integrated into `PdfWriter`

1. ~~Create `packages/pdf/conformance/composer.json` with dependencies~~
2. ~~Implement profile interface + `PdfAProfile` enum~~
3. ~~Implement result objects (`ConformanceResult`, `ConformanceViolation`, `ViolationSeverity`)~~
4. ~~Implement `DocumentInspector` interface + `WriterDocumentInspector`~~
5. ~~Implement 7 constraints for PDF/A-1b~~
6. ~~Implement `ProfileConstraintRegistry` + `ConformanceValidator`~~
7. ~~Implement `ConformanceXmpWriter` for PDF/A identification~~
8. ~~Add `setConformance()` / `checkConformance()` to `PdfWriter`~~
9. ~~Extend existing `PdfAConformanceTest` to use new API, verify veraPDF passes~~
10. ~~Add unit tests for each constraint~~

### ~~Phase 2: PDF/A-1a, 2b/2u/2a, 3b/3u/3a, 4~~ (DONE)
1. ~~`TaggedStructureConstraint` for Level A (MarkInfo.Marked=true, StructTreeRoot, Lang)~~
2. ~~PDF/A-2 relaxations: allow transparency, JPEG2000, object streams~~
3. ~~`ActionConstraint`: restricted actions per PDF/A level~~
4. ~~`EmbeddedFileConstraint`: prohibited in A-1/2, allowed in A-3+~~
5. ~~PDF/A-4: PDF 2.0 base, relaxed font requirements~~
6. ~~Map each profile to constraints in registry~~
7. ~~Integration tests with veraPDF for each level~~

### ~~Phase 3: PDF/UA-1 + PDF/UA-2~~ (DONE)
1. ~~`PdfUaProfile` enum (UA-1 based on PDF 1.7, UA-2 based on PDF 2.0)~~
2. ~~`TaggedStructureConstraint` updated for PDF/UA (MarkInfo, StructTreeRoot, Lang with UA-specific clause references)~~
3. ~~`AnnotationConstraint`: /Contents required on all annotations except Widget/Popup~~
4. ~~`DisplayDocTitleConstraint`: ViewerPreferences /DisplayDocTitle must be true~~
5. ~~`TabOrderConstraint`: pages with annotations must have /Tabs /S~~
6. ~~`MetadataConstraint` reused for pdfuaid XMP identification~~

### ~~Phase 4: PDF/X (X-1a, X-3, X-4)~~ (DONE)
1. ~~`PdfXProfile` enum (X-1a:2003 PDF 1.3, X-3:2003 PDF 1.3, X-4 PDF 1.6)~~
2. ~~`OutputIntentConstraint` reused for `/GTS_PDFX` subtype~~
3. ~~`TrimBoxConstraint`: all pages need `/TrimBox` or `/ArtBox`~~
4. ~~`TrappedConstraint`: Info `/Trapped` must be `/True` or `/False`~~
5. ~~`TransparencyConstraint` updated for PDF/X-1a/X-3 (prohibit) vs X-4 (allow)~~
6. ~~`EncryptionConstraint` reused — encryption prohibited~~
7. ~~Integration tests for X-4, X-1a with happy/sad paths~~

### ~~Phase 5: PDF/VT, PDF/E, PDF/R~~ (DONE)
1. ~~PDF/VT: `PdfVtProfile` enum (VT-1/VT-2/VT-2s), extends PDF/X-4, `DPartRootConstraint` requires Catalog /DPartRoot~~
2. ~~PDF/E-1: `PdfEProfile` enum, embedded fonts + XMP + no encryption~~
3. ~~PDF/R-1: `PdfRProfile` enum, XMP + no encryption (minimal constraints for raster exchange)~~

### ~~Phase 6: Reader-side Validation~~ (DONE)
1. ~~`ReaderDocumentInspector` wrapping `PdfReader` — hydrates Catalog/Page/Info, resolves OutputIntents, detects encryption/metadata/transparency~~
2. ~~`ConformanceChecker` pipeline class — `open()`/`openString()` factory, `checkProfile()`/`checkProfiles()` API~~
3. ~~Same constraints run against parsed PDFs — round-trip validated (write compliant PDF, read back, verify compliant)~~

## Constraint Rules by Standard

### PDF/A-1b (ISO 19005-1)
- Clause 6.1: PDF version 1.4
- Clause 6.2: No device-dependent color without OutputIntent; embedded ICC profiles
- Clause 6.3: All fonts embedded; no Type 1 without full embedding; ToUnicode CMap for text extraction
- Clause 6.4: No transparency (no /Group with /S /Transparency on pages)
- Clause 6.5: No annotations with /AA or /A pointing to JavaScript
- Clause 6.6: No encryption (/Encrypt dict prohibited)
- Clause 6.7: XMP metadata stream on Catalog; pdfaid:part=1, pdfaid:conformance=B; sync with /Info dict
- Clause 6.8: No LZWDecode filter
- Clause 6.9: No embedded files (no /EmbeddedFiles in /Names)

### PDF/UA-1 (ISO 14289-1) — Enforced
- Clause 7.1: MarkInfo /Marked must be true, StructTreeRoot must be present (`TaggedStructureConstraint`)
- Clause 7.2: Catalog /Lang required (`TaggedStructureConstraint`)
- Clause 7.5: Pages with annotations must have /Tabs /S (`TabOrderConstraint`)
- Clause 7.18.1: All annotations (except Widget/Popup) must have /Contents (`AnnotationConstraint`)
- Clause 7.18.1: ViewerPreferences /DisplayDocTitle must be true (`DisplayDocTitleConstraint`)
- XMP metadata must contain pdfuaid:part identification (`MetadataConstraint`)
- All fonts must be embedded (`FontEmbeddingConstraint`)

### PDF/X-1a:2003 (ISO 15930-4) — Enforced
- OutputIntent with /S /GTS_PDFX and embedded ICC profile required (`OutputIntentConstraint`)
- No transparency (`TransparencyConstraint`)
- No encryption (`EncryptionConstraint`)
- All pages must have /TrimBox or /ArtBox (`TrimBoxConstraint`)
- Info /Trapped must be /True or /False (`TrappedConstraint`)
- All fonts must be embedded (`FontEmbeddingConstraint`)
- XMP metadata must contain pdfxid:GTS_PDFXVersion identification (`MetadataConstraint`)

### PDF/X-4 (ISO 15930-7) — Enforced
- Same constraints as X-1a except transparency is allowed
- PDF 1.6+ required (auto-pinned)

## Verification

- **Unit tests**: one per constraint, mock `DocumentInspector`, verify correct violations
- **Integration tests**: `PdfWriter` + `setConformance()`, generate real PDFs, assert no ERROR violations
- **External validation**: veraPDF for PDF/A (all levels) and PDF/UA (existing Docker infrastructure)
- **Negative tests**: intentionally non-compliant documents must produce expected violations
- **Benchmarks**: add `benchPhpdftk10PagesWithPdfAConformance()` to `GeneratePdfBench.php`

## Critical Files

| File | Action |
|---|---|
| `packages/pdf/conformance/` (new package) | Create ~20 source files |
| `packages/pdf/writer/src/PdfWriter.php` | Add `setConformance()`, `checkConformance()`, generate-time hook |
| `packages/pdf/core/src/File/PdfFileWriter.php` | Expose internals for `WriterDocumentInspector` (registered objects, encryption state) |
| `packages/pdf/core/tests/Conformance/PdfAConformanceTest.php` | Extend to use new conformance API |
| `composer.json` (root) | Add conformance package to monorepo |
| `phpunit.xml` | Add conformance test suite |
| `docs/spec-coverage.md` | Update with conformance support |
| `CLAUDE.md` | Add conformance package to repository structure |
