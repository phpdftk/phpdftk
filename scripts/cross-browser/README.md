# Cross-browser PDF oracle (driver)

Renders WPT fixtures through WebKit, Blink, and Gecko. The PHP harness in
`packages/wpt-harness/` consumes the PDFs as a ground-truth signal for the
test loop.

See `docs/plans/cross-browser-oracle.md` for the full plan.

## Install

### One-time

```sh
cd scripts/cross-browser
npm install            # installs Playwright + Playwright's bundled Chromium
./build-webkit.sh      # macOS only — compiles webkit-render to /usr/local/bin
```

### Linux (CI) Firefox path

Firefox runs inside a Docker image; the first invocation of
`./render-docker.sh firefox …` builds it (~3 min cold, cached afterwards).
You don't need anything else installed on the host.

## Manual smoke

```sh
node render.mjs chromium /path/to/fixture.html --output=cr.pdf
./render-docker.sh firefox /path/to/fixture.html /path/to/ff.pdf
node render.mjs webkit /path/to/fixture.html --output=wk.pdf  # macOS only
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

| Identifier | Engine | Branding | Acquisition                                | macOS host | Linux Docker |
|------------|--------|----------|--------------------------------------------|------------|--------------|
| `chromium` | Blink  | Chrome   | Playwright `page.pdf()`                    | ✓          | ✓            |
| `firefox`  | Gecko  | Firefox  | Mozilla's `firefox --headless --print-to-pdf=` (NOT via Playwright — `page.pdf()` is Chromium-only; NOT via Playwright's bundled Firefox — the bundled build strips `--print-to-pdf`) | ✗ (Rosetta + SWGL init fails) | ✓ via Docker |
| `webkit`   | WebKit | Safari   | Swift `WKWebView.createPDF` wrapper (`scripts/cross-browser/webkit-render.swift`) | ✓ via `build-webkit.sh` | ✗ |

Blink is NOT WebKit — they share a common ancestor (KHTML → WebKit → 2013
Blink fork) but have diverged for over a decade. We treat them as three
independent engines for consensus scoring.

## Environment overrides

| Variable           | Meaning                                                              |
|--------------------|----------------------------------------------------------------------|
| `FIREFOX_CLI`      | Path to an upstream Firefox binary. Defaults to `/usr/local/bin/firefox-cli`. Set to use a different build (e.g. on a Linux host with system Firefox in `/usr/bin/firefox`). |
| `FIREFOX_USE_XVFB` | `0` to skip wrapping Firefox with `xvfb-run`. Defaults to `1` when `/usr/bin/xvfb-run` exists. |
| `WEBKIT_CLI`       | Path to the compiled `webkit-render` Swift binary. Defaults to `/usr/local/bin/webkit-render`. |

## Print-options contract

`print-options.mjs` is the single source of truth for how every engine
prints. Anything that has to agree between PHP and Node lives there. Do
not patch options at the call site — change the constants in
`print-options.mjs` and mirror the change in:

- the Swift WebKit wrapper (`webkit-render.swift` constants);
- `packages/wpt-harness/src/BrowserOracle.php` once Phase B lands.
