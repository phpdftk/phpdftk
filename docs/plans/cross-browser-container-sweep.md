# Container-hosted cross-browser sweep

Operational plan for running the entire WPT corpus (~68k fixtures, ~28k after `--scope=rendering --skip-testharness`) through Chromium + Firefox + WebKit efficiently and portably. Companion to `docs/plans/cross-browser-oracle.md`, which defines the *what* (consensus scoring, PDF-to-PDF comparison) — this plan defines the *how* (persistent containers, shared caches, daemon protocol).

## Why

The current sweep (`scripts/cb-sweep-parallel.sh` + `scripts/cross-browser/render.mjs`) spawns N PHP shards; each shard cold-launches a browser **per fixture** via a one-shot `node render.mjs` invocation. Costs on a typical M-series macOS / 16-core Linux box:

| Per-fixture cost | Amount | × ~28k fixtures × ~3 engines |
|---|---|---|
| Chromium cold launch | ~400 ms | ~9.3 h wasted |
| Firefox cold launch (geckodriver) | ~1.5 s | ~35 h wasted |
| `docker run` startup (current `render-docker.sh`) | ~150 ms | ~3.5 h wasted on Linux |
| Actual render work | ~50–500 ms | the part we care about |

A full sweep today is dominated by process/container churn rather than the engine actually rendering anything. The fix is one persistent browser process per engine per shard, addressable over a small JSON protocol, with WPT and the byte cache bind-mounted into the container.

## Goals

1. **Persistent engines.** Each engine launches once per shard. Pages are pooled inside the browser; only the page context is recycled per fixture.
2. **Portable.** Same containers run on macOS Docker Desktop, Linux dockerd, WSL2, and CI runners. macOS keeps the native WKWebView path as an option but does not require it for a portable sweep.
3. **Cache-coherent.** Host shards and container engines share `var/wpt/browser-cache/` via a single bind mount. A cache hit in one shard helps every other shard immediately.
4. **WPT-mounted.** The full corpus + curated subset live behind a read-only bind mount. Containers never copy the corpus.
5. **All three engines on Linux.** WebKit-on-Linux via webkit2gtk closes the "Linux CI collapses to 2-of-2" gap from the oracle plan.
6. **Crash-isolated.** A browser crash in one container does not poison the sweep — the supervisor restarts the engine and the orchestrator retries the in-flight fixture.

## Non-goals

- Replacing the macOS native WKWebView path. It stays as the default on bare-macOS hosts; the WebKit container is the portable fallback (and the canonical CI engine).
- Distributed multi-host orchestration. One host, many containers, many pages — Kubernetes is out of scope.
- Replacing `cb-sweep-parallel.sh`. The PHP-side sharding stays; it just talks to long-running engine daemons instead of forking `node render.mjs`.

## Architecture

```
host (macOS / Linux / WSL2)
├── vendor-data/wpt/                ──(ro bind)──┐
├── var/wpt/browser-cache/          ──(rw bind)──┤
│                                                ▼
├── scripts/cb-sweep-parallel.sh   ────► engine daemons (HTTP, 127.0.0.1)
│   │                                          │
│   ▼                                          ▼
│  PHP shard 1..N ───► BrowserOracle ───► chromium-daemon:9101  ┐
│                                       firefox-daemon:9102    │ docker compose
│                                       webkit-daemon:9103     ┘
│
└── compose.yaml                     ──► one service per engine
                                          • persistent browser process
                                          • page pool (size = cores−2)
                                          • shared cache volume
                                          • healthcheck on GET /status
```

### Engine matrix

| Engine | Container | Persistence mechanism | Print API |
|---|---|---|---|
| Chromium (Blink) | `mcr.microsoft.com/playwright:v1.49.1-jammy` | Playwright Node server, `BrowserContext` per page | `page.pdf()` |
| Firefox (Gecko) | Playwright base + Mozilla tarball + geckodriver | Single geckodriver session, multiple windows | WebDriver `POST /session/:id/print` |
| WebKit | `debian:trixie` + `libwebkit2gtk-4.1-0` + xvfb | Long-running C/Python-GI daemon, one `WebKitWebContext` shared across views | `webkit_print_operation_print()` with `GTK_PRINT_SETTINGS_OUTPUT_URI=file://…` and `output-file-format=pdf` |

#### Opera and other "WebKit on Linux" red herrings

The research backing this plan (summarised in `docs/plans/cross-browser-oracle.md` Engine Selection):

- **Opera** — Chromium-based since 2013. Same Blink engine as Chrome. Provides no independent WebKit signal.
- **Playwright bundled webkit** — patched build, `page.pdf()` is Chromium-only, no print code path.
- **WPE WebKit** — genuine WebKit, embedded port, but ships no print stack. `MiniBrowser` has no `--print-to-pdf` flag.
- **GNOME Web (Epiphany)** — uses webkit2gtk underneath but exposes no headless `--print-to-pdf` CLI; print is GUI-only.
- **wkhtmltopdf** — archived 2024-07-10, rides QtWebKit thousands of commits behind upstream. Disqualified.
- **webkit2gtk** — *the* viable option. Real upstream WebKit maintained by Igalia in-tree, packaged in Debian/Ubuntu/Fedora. Print PDF via `WebKitPrintOperation` + GTK print backend. Needs ~150 lines of binding code (no off-the-shelf CLI) and an Xvfb display target.

So the answer to "can I use Opera or something to test WebKit on Linux?" is **no for Opera, yes for webkit2gtk** — and webkit2gtk is what this plan builds the WebKit container around.

### Daemon protocol

Each engine container exposes the same HTTP/JSON surface on a fixed port:

```
GET  /status
  → 200 { "engine": "chromium", "version": "...", "ready": true,
          "pool": { "size": 14, "in_flight": 3, "queued": 0 } }

POST /render
  body: { "fixture": "/wpt/css/css-flexbox/align-items-001.html",
          "cache_key": "sha256:…",
          "viewport": { "width": 816, "height": 1056 },
          "timeout_ms": 60000 }
  → 200 { "pdf_bytes_base64": "…", "ms": 187, "from_cache": false }
  → 408 { "error": "render timed out" }
  → 502 { "error": "engine crashed; restart in progress" }
  → 304 { "from_cache": true } when X-Cache-Probe: 1

POST /flush
  → 204  (drain in-flight, restart browser; for cache-generation bumps)
```

The cache key is the existing `sha256(fixture_bytes) + cache_generation + engine` from `BrowserOracle`. The daemon checks `/var/cache/browser/<key>.pdf` before rendering; PHP shards check the same directory directly. Both writers use `tmp + rename` for atomicity, so the host and the container can both populate the cache without a coordinator.

`fixture` is always an absolute path inside the container's `/wpt/` mount, never a host path — the daemon refuses anything that doesn't start with `/wpt/`.

### Pool sizing

Inside each daemon: `min(cores − 2, 16)` page slots, matching the existing concurrency cap in `cb-sweep-parallel.sh`. A page slot is one `BrowserContext` (Chromium), one geckodriver window (Firefox), or one `WebKitWebView` (WebKit). The browser itself is a single process; recycling happens at the page level.

PHP shards still drive concurrency at the shard level (`SHARD_COUNT` PHP processes); each shard talks to all three daemons in parallel for the same fixture. With 4 shards × 14 pool slots × 3 engines, the box runs ~168 concurrent renders without spawning 168 browsers.

## Volumes

| Mount | Source | Target | Mode | Why |
|---|---|---|---|---|
| WPT corpus | `vendor-data/wpt/` | `/wpt/` | ro | Fixtures + curated subset |
| Browser cache | `var/wpt/browser-cache/` | `/var/cache/browser/` | rw | Shared sha256-keyed PDF cache |
| Per-engine tmp | tmpfs | `/tmp/` | rw | Profile dirs, screenshots, scratch |
| Optional: pinned fonts | `vendor-data/fonts/` | `/usr/local/share/fonts/phpdftk/` | ro | Removes font drift between host + container |

Mounts are declared once in `compose.yaml`; the host paths are derived from a single `PHPDFTK_ROOT` env var so the same compose file works in CI and on a dev box.

## Orchestration: `compose.yaml`

```yaml
services:
  chromium:
    build: ./scripts/cross-browser/engines/chromium
    ports: ["127.0.0.1:9101:9101"]
    volumes:
      - ${PHPDFTK_ROOT}/vendor-data/wpt:/wpt:ro
      - ${PHPDFTK_ROOT}/var/wpt/browser-cache:/var/cache/browser
      - ${PHPDFTK_ROOT}/vendor-data/fonts:/usr/local/share/fonts/phpdftk:ro
    healthcheck:
      test: ["CMD", "wget", "-qO-", "http://127.0.0.1:9101/status"]
      interval: 5s
      timeout: 2s
      retries: 6
    init: true
    deploy:
      resources:
        limits: { memory: 4G }

  firefox: { ... port 9102, same volume set ... }
  webkit:  { ... port 9103, same volume set ... }
```

`init: true` reaps zombie browser children. Memory limits prevent a Firefox SWGL leak (#29) from taking the host down — the OOMKiller hits the container, the supervisor restarts the daemon, the orchestrator retries the in-flight fixture.

A `make sweep` / `composer cb-sweep:all` target wraps:

```
docker compose up -d --wait
PHP_BIN=php scripts/cb-sweep-parallel.sh ${SHARD_COUNT:-4} \
    --scope=rendering --skip-testharness \
    --engines=chromium,firefox,webkit \
    --daemon-base=http://127.0.0.1:910
docker compose down
```

`--daemon-base` is a new flag on `cb-sweep` that tells `BrowserOracle` to skip the `node render.mjs` fork and POST to `${base}${port_offset}/render` instead.

## Portability matrix

| Host | Chromium daemon | Firefox daemon | WebKit daemon | Notes |
|---|---|---|---|---|
| macOS (Docker Desktop, M-series) | ✓ via DD VM | ✓ via DD VM | ✓ via DD VM (webkit2gtk) | Native macOS WKWebView still available via `WEBKIT_CLI` env var; container path is the portable default |
| macOS (bare metal, no Docker) | bare metal Playwright | bare metal geckodriver | bare metal Swift WKWebView | Existing path; unchanged |
| Linux (dockerd, x86_64) | ✓ | ✓ | ✓ | Primary CI target |
| Linux (dockerd, arm64) | ✓ Playwright arm64 image | ✓ Mozilla linux64-aarch64 tarball | ✓ webkit2gtk arm64 packages | Raspberry Pi / Graviton CI |
| WSL2 | ✓ | ✓ | ✓ | Identical to Linux |
| Linux CI (GitHub Actions) | ✓ | ✓ | ✓ | Replaces today's 2-of-2 collapse with full 3-engine consensus |

The macOS native paths stay first-class for interactive dev (faster than DD's VM hop) but are no longer required for a portable sweep, and CI doesn't need them.

## Cache sharing

The existing `var/wpt/browser-cache/` layout is unchanged: `sha256(fixture_bytes + cache_generation + engine).pdf`. New invariants:

1. Both the host PHP shards and the in-container daemons read+write the same directory via the bind mount. No proxy, no copy.
2. Writers always do `mktemp` in the same directory then `rename()` to the final name. Atomic on local FS; safe across containers because mount UIDs match (`--user $(id -u):$(id -g)` on `docker run`, `user: "${UID}:${GID}"` in compose).
3. `cache_generation` is a single env var (`PHPDFTK_BROWSER_CACHE_GEN`) read by both sides. Bumping it invalidates everything in one move when any engine version changes.
4. The shared cache lives at `var/wpt/browser-cache/`, gitignored, ~50 KB per (fixture, engine) — ~5 GB for the full corpus × 3 engines. Pruning by mtime is a follow-up.

## WPT mount

`vendor-data/wpt/` is a git submodule (~3 GB). Bind-mounting it read-only means:

- Containers never copy the corpus (~30 s saved per `docker run`).
- Sub-tree filters apply at the orchestrator level, not the container — the container just renders whatever absolute path it's asked to, refusing anything outside `/wpt/`.
- Submodule updates on the host become visible to all running containers immediately. No image rebuild on WPT bumps.

## Phasing

| Phase | Scope | Done-criterion |
|---|---|---|
| **P0 — Chromium daemon** | Persistent Node/Playwright server, HTTP `/status` + `/render`, page pool. New `--daemon-base` flag in `cb-sweep`. Replaces today's one-shot `node render.mjs` for Chromium only. | Full curated subset (~2.8k fixtures) sweep completes in <5 min wall on a 10-core box, cold cache. ≥80% reduction vs current one-shot timing. |
| **P1 — Firefox daemon** | Same daemon shape, drives geckodriver with a long-lived session, recycles windows. Folds the macOS + Linux paths into one code path (closes the divergence flagged in this session's earlier discussion). | Curated subset green; consensus scorer sees the same Firefox PDFs cross-platform within fuzz budget. |
| **P2 — WebKit daemon** | C/Python-GI binary in `scripts/cross-browser/engines/webkit/`: long-running process with a thread pool of `WebKitWebView`s, HTTP server (libsoup is already a webkit2gtk dep), `WebKitPrintOperation` with `output-uri=file:///...` + `output-file-format=pdf`. Xvfb baked into the image. | Curated subset green on Linux with full 3-of-3 consensus. CI matrix drops the "2-of-2 on Linux" caveat. |
| **P3 — compose orchestration** | `compose.yaml`, `make sweep` target, healthcheck wiring, cache bind mount, font bind mount, OOM-restart supervision. | `composer cb-sweep:all` runs the full corpus end-to-end with one command. Documented in CONTRIBUTING.md. |
| **P4 — CI cutover** | `.github/workflows/cross-browser.yml` builds the three engine images once per cache generation, runs the sharded sweep against them. Replaces the per-fixture `render-docker.sh` invocation. | Two consecutive nightly runs green on the 3-engine consensus. |

P0 + P1 are pure perf wins on existing hardware. P2 is the new capability (WebKit on Linux). P3 + P4 are the operational wrap.

## Open questions

- **Page-level isolation vs context-level.** Playwright defaults to one context per page (cookies/storage are scoped). For a stateless reftest sweep we can probably share one default context across pages inside Chromium — measure throughput before deciding. Firefox geckodriver has no contexts, just windows; this question doesn't apply.
- **Font drift.** Both the host renderer and the container engines need to see the same font set. Bind-mounting `vendor-data/fonts/` is the cheap answer; the harder answer is shipping the fonts inside the image so a missing host mount doesn't silently change the consensus. P3 punts on this — decide before P4.
- **MathML.** Once #30 lands a `mathml-to-pdf` package, the daemons render MathML for free (engines already do). No daemon changes expected.
- **macOS native vs container WebKit.** Long-term we may deprecate the Swift wrapper if the webkit2gtk container is fast enough on Docker Desktop. Keep both behind feature flags until P4 ships and we have data.

## Risks

| Risk | Mitigation |
|---|---|
| Docker Desktop's macOS file-share is slow; bind-mounting `vendor-data/wpt/` could throttle reads | Pre-warm via a single `find /wpt -type f -print > /dev/null` at daemon startup. Switch to virtiofs (DD ≥4.6 default on Apple Silicon) — significantly faster than gRPC-FUSE |
| webkit2gtk maintenance varies by distro | Pin the base image to `debian:trixie`. Track Igalia's release cadence; bump the WebKit version on the same schedule as Chromium / Firefox |
| Daemon HTTP server becomes a bottleneck under high concurrency | Single-process daemon on loopback; the bottleneck is the browser, not the HTTP layer. If measured wrong, replace with Unix-domain sockets — same protocol, no port conflicts |
| Cache directory race under heavy concurrent writes | `tmp + rename` is atomic on local FS; bind-mount FS on Linux + DD's virtiofs both honor `rename(2)` atomicity for same-directory renames |
| OOM during long sweep (#29 follow-up) | Memory cap per container + supervisor restart + orchestrator retry. Stays correct without us having to fix the leak first |

## Files this lands in

- `scripts/cross-browser/engines/chromium/{Dockerfile,server.mjs,package.json}` — new
- `scripts/cross-browser/engines/firefox/{Dockerfile,server.mjs,package.json}` — new
- `scripts/cross-browser/engines/webkit/{Dockerfile,server.c,meson.build}` — new
- `compose.yaml` — new at repo root
- `packages/wpt-harness/src/BrowserOracle.php` — add daemon-base mode, keep one-shot mode as fallback
- `packages/wpt-harness/bin/wpt` — `--daemon-base=<url>` flag on `cb-sweep`
- `scripts/cb-sweep-parallel.sh` — optional `--with-daemons` wrapper to bring compose up/down around the sweep
- `composer.json` — `cb-sweep:all` script
- `.github/workflows/cross-browser.yml` — build + run via compose
- `docs/site/src/content/docs/standards/spec/coverage.md` — bump expected WPT pass-rate once full 3-of-3 consensus lands on Linux

## Related

- Issue #29 — cb-sweep memory leak bisect; the supervisor-restart model in this plan keeps a leak from blocking the sweep but does not fix it.
- Issue #30 — MathML rendering; benefits from the same container infrastructure without changes.
- `docs/plans/cross-browser-oracle.md` — what we're rendering and how we score it. This plan only changes *how it runs*.
- `docs/plans/full-spec-compliance.md` — the v1 100%-compliance freeze that all of this serves.