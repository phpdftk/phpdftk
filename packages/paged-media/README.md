# phpdftk/paged-media

CSS Paged Media engine for phpdftk.

Implements:

- **CSS Paged Media 3** — `@page` rules, page selectors (`:first`, `:left`, `:right`, `:nth(n)`), named pages, `size`, `margin`, page background, page marks
- **CSS Generated Content for Paged Media 3** — running elements, named flows, `string-set` / `content: string()`, `target-counter()`, `target-text()`, lists of figures / tables
- **CSS Fragmentation 3** — `break-before` / `break-after` / `break-inside`, orphans, widows, block fragmentation
- **CSS Fragmentation 4** — fragmented border decoration, repeated table headers, page-floats
- **CSS Page Floats 3** — `float: top` / `float: bottom` / column floats

## Status

**Phase 4G scaffold + extraction direction.** The package shape lands here — the public types every paged-media consumer will use (`PageBox`, `PageMargin`, `MarginBoxPosition`, `PageContent`, `FragmentationContext`). The actual paged-media logic currently lives entangled with the painter in `phpdftk/html-to-pdf`; Phase 4G.1 extracts it into this package without changing semantics, then phases 4G.2–4G.6 fill in the gaps the existing implementation hasn't covered.

```
4G.0  Scaffold + extraction direction  ← this commit
4G.1  Extract from html-to-pdf         page-box construction, @page selector
                                       resolution, margin-box collection,
                                       page-background painter, fragmentation
                                       primitives. No semantic change.
4G.2  Named pages                      `page: foo` property + `@page foo`
                                       selector + first-applies / last-applies
                                       chain
4G.3  Running elements                 `position: running()`, `running()` in
                                       `content:`, cross-page reuse
4G.4  string-set + named strings       `string-set: chapter content()`,
                                       `content: string(chapter)`
4G.5  Cross-references                 `target-counter()`, `target-text()`,
                                       leaders, lists of figures / tables
4G.6  Page floats                      `float: top` / `bottom` / column floats,
                                       float-defer model
```

## Who uses it

- **`phpdftk/html-to-pdf`** — primary consumer. Pulls page geometry,
  marginalia decisions, fragmentation breakpoints from this engine.
- **`phpdftk/svg-to-pdf`** — `<foreignObject>` content needs full HTML
  paged-media when an SVG hosts HTML. Without the extraction the
  paged-media logic would have to be duplicated.
- **Direct API callers** — `Pdf::addBlock` + the high-level flow API
  use a simpler page model today; once the engine is extracted, the
  same `PageBox` + `FragmentationContext` types power both paths.

## Usage (target API)

```php
use Phpdftk\PagedMedia\Engine;
use Phpdftk\PagedMedia\PageSelector;
use Phpdftk\PagedMedia\MarginBoxPosition;

$engine = new Engine($stylesheets);

// Resolve the page box for the first page of the document.
$pageBox = $engine->resolvePageBox(
    pageIndex: 0,
    namedPage: null,
    pseudoClasses: [PageSelector::First],
);
$pageBox->size;      // Rectangle (612 × 792 default)
$pageBox->margin;    // PageMargin (top / right / bottom / left)
$pageBox->background;
$pageBox->marginBoxes[MarginBoxPosition::TopCenter->value] ?? null;

// Decide where to break a flowed block.
$break = $engine->findBreak(
    $blockHeight,
    $availableHeight,
    $hasAvoidInside,
);
```

## Why a separate package

The 100% spec roadmap (`docs/plans/full-spec-compliance.md`) calls
out paged-media as the most critical CSS module family for a PDF
renderer. It also crosses the html-to-pdf → svg-to-pdf boundary via
foreignObject. Extraction lets:

- The same engine power both the html-to-pdf flow and SVG-hosted
  HTML
- The fragmentation logic be tested in isolation against the CSS
  Fragmentation 3 / 4 specs without spinning up the painter
- WPT tests under `css/css-page/**`, `css/css-gcpm/**`,
  `css/css-break/**`, `css/css-page-floats/**` map to a single
  package owner

## Installation

```bash
composer require phpdftk/paged-media
```

## License

MIT
