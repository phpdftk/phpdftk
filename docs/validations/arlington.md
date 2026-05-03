# Arlington PDF Model Validation

The [Arlington PDF Model](https://github.com/pdf-association/arlington-pdf-model) is a machine-readable definition of every PDF object type maintained by the PDF Association (the ISO 32000 standards body). It provides the canonical grammar for PDF file structure — 613 dictionary specifications covering PDF 1.0 through 2.0.

**License:** Apache 2.0

## What it catches

The Arlington validator checks generated PDFs against the spec at the dictionary level:

| Check | Severity | Description |
|---|---|---|
| **Required keys missing** | Error | Fields marked `Required: TRUE` that are absent from the dictionary |
| **Unknown keys** | Warning | Keys present in the PDF that don't appear in the Arlington spec |
| **Version constraints** | Warning | Fields used that require a higher PDF version than the document declares |
| **Deprecated keys** | Warning | Fields used that are deprecated in the document's PDF version |

### MVP scope (current)

The validator currently checks Catalog and Page dictionaries with unconditional rules only. Conditional requirements (encoded as `fn:` predicates like `fn:IsRequired(fn:IsPresent(PieceInfo))`) are deferred.

### Future expansion

- `fn:` predicate evaluation for conditional required fields
- Type checking (verifying value types match the spec: `name`, `string`, `integer`, etc.)
- `PossibleValues` enumeration checking
- Cross-dictionary link traversal (following `Link` column references)
- Font, annotation, and action dictionary validation

## Data source

The Arlington model is included as a git submodule at `vendor-data/arlington-pdf-model/`. The validator reads TSV files from `tsv/latest/`, which contains the current definitions for all PDF versions.

```bash
# Initialize the submodule
git submodule update --init

# Verify
ls vendor-data/arlington-pdf-model/tsv/latest/ | head -5
```

### TSV format

Each TSV file defines one PDF dictionary type with 12 columns:

| Column | Description |
|---|---|
| `Key` | Property name (e.g., `Type`, `Pages`, `MediaBox`) |
| `Type` | Data types, semicolon-separated (e.g., `name`, `dictionary`, `rectangle`) |
| `SinceVersion` | PDF version when introduced (e.g., `1.0`, `1.4`, `2.0`) |
| `DeprecatedIn` | PDF version when deprecated (empty if not deprecated) |
| `Required` | `TRUE`, `FALSE`, or `fn:` predicate for conditional requirement |
| `IndirectReference` | Whether the value must be an indirect reference |
| `Inheritable` | Whether the value can be inherited from a parent |
| `DefaultValue` | Default value if the key is absent |
| `PossibleValues` | Allowed values (e.g., `[Catalog]`, `[SinglePage,OneColumn]`) |
| `SpecialCase` | Additional validity constraints |
| `Link` | References to other TSV files for nested dictionary types |
| `Note` | PDF spec table reference or GitHub issue link |

**Example** (`Catalog.tsv`, first few rows):

```
Key     Type        SinceVersion  Required  ...
Type    name        1.0           TRUE      [Catalog]
Version name        1.4           FALSE     [1.0,1.1,...,2.0]
Pages   dictionary  1.0           TRUE      ...
```

### TSV-to-PDF type mapping

Arlington TSV filenames don't always match PDF `/Type` values. The validator maps them:

| PDF `/Type` value | Arlington TSV file |
|---|---|
| `Catalog` | `Catalog.tsv` |
| `Page` | `PageObject.tsv` |
| `Pages` | `PageTreeNodeRoot.tsv` |
| `Font` | `FontType1.tsv` |
| `ExtGState` | `GraphicsStateParameter.tsv` |
| `Outlines` | `Outline.tsv` |
| `XRef` | `XRefStream.tsv` |
| `ObjStm` | `ObjectStream.tsv` |

## How it works

### Loader

`ArlingtonLoader::load()` scans all `*.tsv` files in the TSV directory, parses each into a `DictionarySpec` containing `FieldSpec` entries, and caches the result. The 613 specs are loaded once per test run.

### Validator

`ArlingtonValidator::validate()` takes a `PdfDictionary` (from `PdfReader`), a spec name, and an optional `PdfVersion`, then returns a `ValidationResult` with errors and warnings.

### Trait

The `ArlingtonValidationTrait` provides:

```php
// Validate a PDF file (reads with PdfReader, validates Catalog + all Pages)
$this->assertArlingtonValid('/path/to/file.pdf');

// Validate in-memory PDF bytes
$this->assertArlingtonValidBytes($pdfBytes);
```

If the Arlington submodule isn't initialized, both methods call `markTestSkipped()`.

### Source files

| File | Class | Purpose |
|---|---|---|
| `tests/Support/Arlington/ArlingtonLoader.php` | `ArlingtonLoader` | Parses TSV files, caches specs |
| `tests/Support/Arlington/ArlingtonValidator.php` | `ArlingtonValidator` | Validates dictionaries against specs |
| `tests/Support/Arlington/ArlingtonValidationTrait.php` | `ArlingtonValidationTrait` | PHPUnit assertion trait |
| `tests/Support/Arlington/DictionarySpec.php` | `DictionarySpec` | One dictionary type with its fields |
| `tests/Support/Arlington/FieldSpec.php` | `FieldSpec` | One field (12-column TSV row) |
| `tests/Support/Arlington/ValidationResult.php` | `ValidationResult` | Errors + warnings container |

## Coverage

Arlington validation is currently applied to 5 core integration tests:

| Test file | What it validates |
|---|---|
| `SimpleTextTest` | 3-page text PDF — Catalog + 3 Pages |
| `MultiPageComplexTest` | 10-page PDF with Info/ViewerPreferences — Catalog + 10 Pages |
| `FormFieldsTest` | AcroForm with text/checkbox/choice fields — Catalog + 1 Page |
| `BookmarksTest` | Nested outline tree — Catalog + 6 Pages |
| `DocumentFeaturesTest` | OutputIntent, OCG, tagged structure — Catalog + Pages (2 test methods) |

## CI configuration

The Arlington submodule is initialized in CI by the `submodules: true` checkout option:

```yaml
# .github/workflows/ci.yml
- uses: actions/checkout@v4
  with:
    submodules: true
```

No additional installation is needed — the validator is pure PHP.

## Manual usage

```php
use Phpdftk\Tests\Support\Arlington\ArlingtonLoader;
use Phpdftk\Tests\Support\Arlington\ArlingtonValidator;
use Phpdftk\Pdf\Reader\PdfReader;

$specs = ArlingtonLoader::load();
$validator = new ArlingtonValidator($specs);

$reader = PdfReader::fromFile('my-document.pdf');
$version = $reader->getPdfVersion();

// Validate catalog
$result = $validator->validate($reader->getCatalog(), 'Catalog', $version);
echo "Errors: " . implode(', ', $result->errors) . "\n";
echo "Warnings: " . implode(', ', $result->warnings) . "\n";

// Validate each page
for ($i = 0; $i < $reader->getPageCount(); $i++) {
    $result = $validator->validate($reader->getPage($i), 'PageObject', $version);
    echo "Page {$i}: " . count($result->errors) . " errors\n";
}
```
