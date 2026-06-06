# Cross-browser PDF oracle

Companion to the WPT reftest harness. Adds **independent absolute-correctness signal** by rendering each test through three production browser engines — WebKit (Safari), Blink (Chromium), Gecko (Firefox) — to PDF, rasterising the same way our renderer's output is rasterised, and asserting our PDF matches the engines' consensus.

## Why

The WPT reftest harness (`packages/wpt-harness/`) compares `test.html` against `ref.html` with **both fixtures rendered through our `Phpdftk\HtmlToPdf\Renderer`**. That model is excellent at catching *relative* correctness bugs (the two fixtures should look identical and they don't) but blind to *absolute* correctness bugs (we render both fixtures wrong in the same way — the diff is zero, the test passes, the output is still wrong).

This oracle is the second signal. It feeds the v1 100%-compliance work tracked in `docs/plans/full-spec-compliance.md` — drift the oracle catches becomes WPT-suppressed issues to fix in the codebase. It is not itself the v1 gate.

## Design constraints

1. **PDF-to-PDF, never PDF-to-screenshot.** Both browsers and our renderer go through `@media print`. Comparing our printed output to a browser viewport screenshot would conflate print-medium differences with rendering bugs.
2. **Two-of-three consensus, skip-on-disagree.** Treat browser-disagreement as a browser-bug zone, not as a test failure. We're judged only when ≥2 engines agree.
3. **PDF is the unit of comparison, not bytes.** Different PDF generators produce wildly different bytes for the same visual. Rasterise both sides and compare pixels.
4. **Fonts pinned.** Inject a fixed `@font-face` block before handing fixtures to engines so font-rendering drift doesn't dominate the budget. The font we ship is the font all four sides see.
5. **Not a merge gate initially.** Runs nightly + on-demand. Drift reported as PR comment, not blocking. Block-on-fail only after engine list and fuzz budgets are stable.

## Engine selection

| Engine  | Source                          | Why |
|---------|---------------------------------|-----|
| WebKit  | Playwright `webkit` driver      | Safari truth. Apple's engine. |
| Blink   | Playwright `chromium` driver    | Chrome / Chromium truth. Forked from WebKit in 2013; substantially diverged. |
| Gecko   | Playwright `firefox` driver     | Firefox truth. Mozilla's engine. Fully independent lineage. |

Common confusion: Chromium's Blink is *not* WebKit. They share a common ancestor (KHTML → WebKit → 2013 Blink fork) but have diverged for over a decade and are now genuinely independent rendering engines — different layout (LayoutNG vs WebKit's), different graphics (Skia vs CoreGraphics/Cairo), different subpixel rounding, different bug profiles. Three engines means three signals.

Engines explicitly NOT part of v1:

- **WeasyPrint** (Python HTML→PDF) — different category (peer renderer, not browser). May ship as opt-in `--include-weasyprint` flag for "is the other HTML→PDF library doing this the same way?" sanity checks.
- **Prince XML** — print-CSS gold standard but commercial. Aspirational.
- **wkhtmltopdf**, **PhantomJS** — abandoned, security-frozen.
- **Servo** — incomplete for print.
- **PagedJS**, **Vivliostyle** — both use Chromium underneath, no independent signal over Playwright Chromium.

## Architecture

### Components

```
scripts/cross-browser/
├── render.mjs              Node entry: (test-file, engine) → PDF on stdout
├── pool.mjs                Browser/context pool for Layer-1 parallelism
├── print-options.mjs       Canonical print-options object shared by render.mjs
├── package.json            Playwright pin + small launcher deps
└── README.md

packages/wpt-harness/src/
├── BrowserOracle.php       PHP-side: shells to scripts/cross-browser/render.mjs,
│                           caches results, returns a PNG path
├── ConsensusScorer.php     Decides PASS / SKIP-DISAGREE / FAIL from
│                           (oursPng, webkitPng, blinkPng, geckoPng)
└── CrossBrowserRunner.php  Orchestrates the per-test flow

packages/wpt-harness/curated/
└── manifest.json           Curated test IDs + per-test fuzz overrides

packages/wpt-harness/bin/
└── wpt                     Add `wpt cross-browser` subcommand

.github/workflows/
└── cross-browser.yml       Nightly + on-dispatch

var/wpt/browser-cache/      Cached browser PDFs, keyed by
                            sha256(test_bytes) + playwright_version + engine
```

### Render flow per test

```
test.html
  ├─→ our Renderer ────→ ours.pdf      ──→ gs ──→ ours.png
  ├─→ render.mjs gecko ──→ gecko.pdf   ──→ gs ──→ gecko.png   (cached)
  ├─→ render.mjs blink ──→ blink.pdf   ──→ gs ──→ blink.png   (cached)
  └─→ render.mjs webkit ──→ webkit.pdf ──→ gs ──→ webkit.png  (cached)
```

### Consensus scoring

Per-test verdict via `ConsensusScorer`:

```
agreements = (
    pairwise_AE(gecko, blink)   <= BROWSER_AGREE_FUZZ,
    pairwise_AE(blink, webkit)  <= BROWSER_AGREE_FUZZ,
    pairwise_AE(webkit, gecko)  <= BROWSER_AGREE_FUZZ,
)

if sum(agreements) < 2:
    return SKIP_DISAGREE  # browser-bug zone, can't judge ours

consensus_pngs = the two (or three) that agree

if AE(ours, all consensus_pngs) <= OURS_FUZZ:
    return PASS
else:
    return FAIL
```

### Fuzz budgets

Three named budgets, locked in code:

| Name                  | Meaning                                            | Initial value                       |
|-----------------------|----------------------------------------------------|-------------------------------------|
| `BROWSER_AGREE_FUZZ`  | How different can two browsers be before "they disagree"? | `maxDifference=10, totalPixels=2%` |
| `OURS_FUZZ_GEOMETRY`  | Ours vs browser consensus on pure-shape fixtures   | `maxDifference=5, totalPixels=0.5%`  |
| `OURS_FUZZ_TEXT`      | Ours vs browser consensus on text-containing fixtures | `maxDifference=20, totalPixels=5%`  |

Per-fixture overrides go in `curated/manifest.json`. Empirically tune after Phase B has data.

## Test selection modes

- **Default (curated subset)** — ~50 high-signal fixtures from `curated/manifest.json`. Fast: ~4 min cold, <1 min warm. Suitable for dev loop and on-demand CI.
- **`--all`** — every in-scope WPT test (~2800 today, growing). 90-min budget acceptable; nightly only. Caching makes steady-state runs cheap.
- **`--filter=<glob>`** — manual subset by test ID glob.

## Parallelization (three layers, stackable)

### Layer 1: contexts within a browser process

One browser launch (~1–2 s) can host dozens of `BrowserContext`s. Each context owns its own page. `pool.mjs` runs **~8 concurrent contexts per browser** in one Node process; that's the biggest single-machine win and the cheapest layer.

```js
// scripts/cross-browser/pool.mjs (sketch)
const browser = await firefox.launch();
const pool = new ContextPool(browser, { maxConcurrent: 8 });
await Promise.all(testIds.map(id => pool.run(id, renderOne)));
```

### Layer 2: processes within a machine

Shard the test list across **M = cores − 1** Node processes via deterministic hashing (`hash(testId) % M`). Each process owns its own browser triple (Firefox + WebKit + Chromium).

### Layer 3: CI matrix

GitHub Actions `strategy.matrix` with N shards; each runner invokes `wpt cross-browser --all --shard=K/N`.

### Combined math

`2800 tests × 3 engines = 8400 renders`. With `4 CI shards × 4 processes × 8 contexts = 128 concurrent`, cold cache is ~1.5 min wall per engine, ~5 min total. Warm cache (typical) is ~30 s.

## Print-options contract

A single canonical print-options object lives in `scripts/cross-browser/print-options.mjs` and is referenced from both `render.mjs` and (via mirror constants) `Renderer`. Browsers and our engine must agree on:

| Setting                | Value      | Reason                                   |
|------------------------|------------|------------------------------------------|
| paper size             | `letter`   | Match our default `MediaBox` (612×792 pt). |
| margins (top/right/bottom/left) | `0`        | Skip browser headers/footers.           |
| background graphics    | enabled    | Browsers strip backgrounds by default.   |
| print viewport         | 612pt      | Width matches our content area.          |
| scale                  | `1.0`      | No DPI surprises.                        |
| header/footer template | empty      | No date/URL chrome.                      |
| prefer CSS page size   | true       | Honour `@page { size: … }` in fixtures.  |

Drift on any of these = test failures that aren't bugs. Lock and document.

## Phases

### Phase A — single engine, single test, end-to-end (1 day)

- Create `scripts/cross-browser/{render.mjs,package.json,README.md}`.
- Pin Playwright in package.json; install **only Firefox** in this phase.
- Wire `BrowserOracle::renderFirefox(string $testPath): string`.
- Pick `background-image-001` as the smoke fixture.
- Hand-render it through ours, Firefox, ghostscript. Visually compare.
- Lock print-options contract — write `print-options.mjs`, mirror as PHP constants in `BrowserOracle`.

**Done when:** one fixture renders through ours and Firefox, both go through `gs` at 96 DPI, the resulting PNGs are visually compared by hand and the result is "yeah, those look like the same page."

### Phase B — second + third engine, consensus scorer (1 day)

- Add `webkit` and `chromium` to Playwright install.
- Write `ConsensusScorer` (PHP) implementing the three-way decision tree.
- Pick initial fuzz budgets (above). Sanity-check on 10 fixtures.
- Spike the WebKit-Linux-PDF parity question on CI runner OS. If WebKit's `page.pdf()` is unreliable on Linux, fall back to macOS-only WebKit (mac runner in CI matrix).

**Done when:** consensus verdict for a 10-fixture set is sensible (no false SKIP-DISAGREE on simple fixtures; no false PASS where we're visibly wrong).

### Phase C — full-corpus mode + Layer 1 parallelism (1 day)

- Implement `--all` and `--filter=<glob>` flags on the runner.
- Build `pool.mjs`; wire 8-concurrent-contexts-per-browser.
- Cache layer: hash → `var/wpt/browser-cache/`. Bytewise PDF cache, not PNG (rasterising is cheap, PDF is the slow side).
- Add `composer cross-browser` task.

**Done when:** `composer cross-browser -- --all` runs end-to-end locally in under the 90-min budget cold, under 10 min warm.

### Phase D — Layer 2 + 3 parallelism + CI (1–2 days)

- Process sharding via deterministic test-ID hash.
- `.github/workflows/cross-browser.yml`: matrix of 4 shards, nightly cron, manual dispatch, optional PR trigger on `packages/html-to-pdf/**` or `packages/css/**` touches.
- Docker base: `mcr.microsoft.com/playwright:v1.NN-jammy` (bundles all engines).
- `actions/cache@v4` for `var/wpt/browser-cache/` keyed on `playwright_version + curated_manifest_hash`.
- PR comment with drift summary. Not a merge gate yet.

**Done when:** nightly CI green for two consecutive runs on the curated subset.

### Phase E — golden mode (optional, deferred)

- Once trust in consensus is established for a fixture, snapshot the agreed PNG to a `_browser-truth` orphan branch (same pattern as `_compliance` / `_benchmarks`).
- Cross-browser run then becomes: diff ours vs golden directly, skip browser renders entirely on cache hit. Browser version bump = regenerate goldens.
- This is the path to making cross-browser cheap enough to run per-PR.

## Open questions to settle before / during Phase A

1. ~~**WebKit print-to-PDF parity on Linux.**~~ — answered, hard NO. See "Phase A findings" below.
2. **Font pinning strategy.** Two choices: (a) inject `@font-face` of a specific WOFF2 into every fixture before rendering, (b) accept text drift with looser `OURS_FUZZ_TEXT`. Recommend (a).
3. **What's the failure model for "browsers agree, we disagree"?** File issue + skip on this PR's gate, or block merge? Decide before Phase D ships.
4. **Browser version bumps.** Pin Playwright in `package.json`; document the bump cadence (probably twice a year). Each bump re-runs the full corpus + regenerates Phase E goldens if it ships.

## Phase A findings — and a forced redesign

The Phase A spike turned up two blockers that change the engine-acquisition strategy.

### Playwright's `page.pdf()` is Chromium-only

`page.pdf()` was the whole reason Playwright was attractive: one API, three engines. It's documented as Chromium-only and emits `PDF generation is only supported for Headless Chromium` when called on the `firefox` or `webkit` drivers. Verified directly with Playwright 1.49.

### Firefox CLI on macOS host can't initialise its renderer in headless mode

`firefox --headless --print-to-pdf=…` on macOS reliably aborts with `[GFX1-]: RenderCompositorSWGL failed mapping default framebuffer` — Firefox can't get to a GL/Metal context without a real display. Same issue on system Firefox 151 and on Playwright-bundled Firefox 132. Workaround is Docker / Linux runner; on bare macOS it doesn't work.

### Practical consequence

The engine-acquisition layer **cannot** be "Playwright + page.pdf()" as initially planned. Actual matrix that works:

| Engine    | Acquisition                                | macOS host | Linux Docker |
|-----------|--------------------------------------------|------------|--------------|
| Chromium  | Playwright `page.pdf()` (works everywhere) | ✓          | ✓            |
| Gecko     | `firefox --headless --print-to-pdf=`       | ✗          | ✓            |
| WebKit    | TBD; not via Playwright. Investigate Linux `webkit2gtk` + custom CLI binding, or macOS-only `WKWebView.createPDF` Swift wrapper. | macOS-only via Swift | needs custom binding |

### Updated plan

- **Phase A1 (done):** Chromium via Playwright works end-to-end. Smoke fixture `background-image-001.html` renders through ours and Chromium, rasterised at 96 DPI, ~0.99% pixel AE. Pipeline validated.
- **Phase A2:** lift the Gecko story by pivoting the runner. The oracle must run inside a Linux container. Replace the "Node script with Playwright `page.pdf()`" model with "shell out to engine CLIs (`chrome --print-to-pdf=` and `firefox --headless --print-to-pdf=`) from inside a Playwright-based Docker image." The Node script becomes a thin orchestrator over CLIs, not an API consumer.
- **Phase A3:** WebKit. Pick between (a) building a custom `webkit2gtk` → PDF binding (Linux, ~1 day of C/CLI work, more maintenance), or (b) macOS-only WebKit via a tiny Swift `WKWebView.createPDF` wrapper plus a macOS CI runner (more CI surface, less code). Recommend (b) — Swift wrapper is ~80 lines and CI matrix `macos-latest` is a stock GA runner. Document the constraint that WebKit consensus is macOS-only.
- **Phase B onwards:** unchanged in intent, but the consensus is genuinely three-way only on macOS CI; Linux runners see Chromium + Gecko only. Document the asymmetry; treat the macOS-only WebKit signal as an additional check, not the minimum bar.

### What landed in Phase A1

- `scripts/cross-browser/package.json` — Playwright pinned 1.49.1, Chromium installed via postinstall.
- `scripts/cross-browser/render.mjs` — CLI takes `(engine, file, --output)`, invokes Playwright. Works for `chromium` today; `firefox` and `webkit` paths are wired but raise at runtime because of the `page.pdf()` gap.
- `scripts/cross-browser/print-options.mjs` — canonical print-options contract.
- `scripts/cross-browser/README.md` — usage + the engine-acquisition matrix.
- Smoke: `node render.mjs chromium <fixture> --output=cr.pdf` → 11 KB PDF; diff vs our render ≈ 0.99% pixel AE on background-image-001.

## Risks

- **WebKit-Linux PDF gaps** — biggest unknown. Spike in Phase A.
- **Font drift dominates fuzz budget** — mitigated by Phase A font pinning.
- **Browser bug masquerades as our bug** — mitigated by two-of-three consensus + skip-on-disagree.
- **Slow CI** — mitigated by curated subset default + Phase E golden cache.
- **Maintenance debt** — pinned Playwright version moves twice a year; budget time for re-baselining.

## Cost summary

- **Engineering**: ~4–5 day-equivalents through Phase D.
- **CI**: ~5 min per cross-browser run (warm cache, curated subset); 90 min budget for `--all` nightly; Docker pull ~30 s; browser-cache restore ~10 s.
- **Storage**: `var/wpt/browser-cache/` grows roughly `tests × engines × ~50 KB` = ~400 MB for full corpus. Gitignored.
- **Dep weight**: Node.js + Playwright + 3 browsers = ~600 MB on disk in CI Docker image (mostly already in the Playwright base image).

## Relationship to existing work

- The WPT reftest harness (`packages/wpt-harness/`) stays as today. The oracle is additional, not a replacement.
- Drift the oracle catches becomes issues to fix in the codebase, which then move WPT pass rates. The oracle doesn't directly contribute to the v1 100% claim — but its catches do.
- Goldens (Phase E) would live alongside `_benchmarks` and `_compliance` orphan branches.
