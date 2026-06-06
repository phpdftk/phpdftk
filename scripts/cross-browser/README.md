# Cross-browser PDF oracle (driver)

Renders WPT fixtures through WebKit, Blink, and Gecko via Playwright. The PHP
harness in `packages/wpt-harness/` consumes the PDFs as a ground-truth signal
for the test loop.

See `docs/plans/cross-browser-oracle.md` for the full plan.

## Install

```sh
cd scripts/cross-browser
npm install
```

The `postinstall` hook fetches **Firefox only** during the Phase-A spike;
adding Chromium and WebKit is a Phase-B step.

## Manual smoke

```sh
node render.mjs firefox /path/to/test.html --output=ff.pdf
```

Use `--output=path` to write to disk, or omit it to stream the PDF on stdout
(useful when the PHP harness pipes the output through Ghostscript).

## What this script does NOT do

- No browser pool. Each invocation launches and tears down. Phase C adds the
  pool for performance.
- No caching. Phase C adds `var/wpt/browser-cache/` keyed on
  `sha256(test_bytes) + playwright_version + engine`.
- No consensus scoring. That lives in `ConsensusScorer.php` (Phase B).

## Engines and identifiers

| Identifier | Engine | Branding | Acquisition | Works today? |
|------------|--------|----------|-------------|--------------|
| `chromium` | Blink  | Chrome   | Playwright `page.pdf()` | yes (macOS host, Linux Docker) |
| `firefox`  | Gecko  | Firefox  | `firefox --headless --print-to-pdf=` (NOT via Playwright — `page.pdf()` is Chromium-only) | Linux Docker only; macOS host fails with `RenderCompositorSWGL` |
| `webkit`   | WebKit | Safari   | TBD — Swift `WKWebView.createPDF` wrapper on macOS, or `webkit2gtk` binding on Linux | not wired in Phase A |

Blink is NOT WebKit — they share a common ancestor (KHTML → WebKit → 2013
Blink fork) but have diverged for over a decade. We treat them as three
independent engines for consensus scoring.

Phase A scope is `chromium` only; `firefox` arrives in Phase A2 (Linux Docker);
`webkit` arrives in Phase A3 via the Swift wrapper. See
`docs/plans/cross-browser-oracle.md` §"Phase A findings".

## Print-options contract

`print-options.mjs` is the single source of truth for how every engine
prints. Anything that has to agree between PHP and Node lives there. Do
not patch options at the call site — change the constants in
`print-options.mjs` and mirror the change in
`packages/wpt-harness/src/BrowserOracle.php`.
