# phpdftk/wpt-harness

Web Platform Tests harness for phpdftk's HTML / CSS / SVG rendering pipeline.

Runs upstream WPT tests against `html-to-pdf` and `svg-to-pdf`, rasterises the output, visual-diffs it against the reference, and publishes a per-test pass / fail / pending-substrate score. The aggregate score is the measurable input to the 100% spec compliance roadmap (`docs/plans/full-spec-compliance.md`).

## Status

**Phase 4A scaffold.** Package shape only — the test runner, raster pipeline, scorer, and manifest classifier are stubs. They land in sub-phases 4A.1 through 4A.6 per the roadmap.

```
4A.1  HarnessRunner    composer wpt -> walks the manifest, renders each test
4A.2  Rasteriser       PDF -> RGBA pixel buffer (Ghostscript for v1, phpdftk/raster once 4C lands)
4A.3  Scorer           perceptual visual diff vs the WPT reference image
4A.4  Manifest         per-test in-scope / out-of-scope / pending-substrate classifier
4A.5  CI publish       weekly dashboard at docs/site/standards/spec-coverage/wpt-report.md
4A.6  PR comment       per-PR delta (passes gained / lost on the in-scope subset)
```

## Architecture

The WPT corpus lives under `vendor-data/wpt/` as a git submodule. Each test is a single HTML file (or HTML+CSS+JS bundle, where JS is no-op per the out-of-scope ledger) with an accompanying `*-ref.html` or `*-ref.png` reference. The harness:

1. **Classify** each test against the manifest (`Manifest::classify()`)
2. **Render** the test HTML via `phpdftk/html-to-pdf` (or `svg-to-pdf` for SVG tests) into a PDF
3. **Rasterise** the resulting PDF to an RGBA buffer at the WPT reference DPI
4. **Compare** against the WPT reference rendering (perceptual diff)
5. **Score** per the test type — `reftest` requires byte-equivalence within tolerance; `wpt-print-reftest` allows page-level matching

Tests classified as `out-of-scope` (per `docs/spec/out-of-scope.md`) are skipped and don't count against the denominator. Tests classified as `pending-substrate` are reported separately so the dashboard can show "blocked on 4C raster compositor" etc.

## Usage

```bash
# Run the full harness — produces a JSON report and an HTML dashboard
composer wpt

# Run a single test category
composer wpt -- --filter css/css-transforms

# Run only tests whose manifest classifies them as in-scope
composer wpt -- --in-scope-only

# Update the manifest with newly-classified tests
composer wpt -- --reclassify
```

## CLI

```bash
./vendor/bin/wpt run                  # Run harness, write report
./vendor/bin/wpt classify             # Reclassify the manifest
./vendor/bin/wpt status               # Print last-run summary
./vendor/bin/wpt diff <test-id>       # Show per-test failure diff
```

## Output

- `var/wpt/report.json`   — full per-test result ledger
- `var/wpt/summary.md`    — human-readable summary
- `var/wpt/diffs/<id>/`   — failure artefacts (rendered.png, ref.png, diff.png)
- `docs/site/standards/spec-coverage/wpt-report.md` — published dashboard (CI-generated)

## Installation

This package is **internal infrastructure**, not a user-facing API. It ships as part of the monorepo CI; downstream users don't depend on it directly.

```bash
composer require --dev phpdftk/wpt-harness
```

## License

MIT
