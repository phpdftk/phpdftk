# Out-of-scope ledger — the 100% contract

This ledger defines the **denominator** of phpdftk's 100% HTML / CSS / SVG compliance claim. Every surface listed here is out of scope **permanently and by construction** — no static, server-side, headless PDF renderer can implement it, and the WPT harness manifest (Phase 4A.4) skips the corresponding test directories so they don't count against the score.

This is the user-facing contract. When a user reads "100%" in our marketing or our `composer.json` description, what we promise is everything in the W3C / WHATWG snapshot **minus this list**.

The ledger is updated on the annual re-sync date (each January). Net-new spec surfaces that publish during a snapshot window inherit their out-of-scope-ness by category; new categories require a documented addition here with rationale.

## Categories

Each surface is excluded for one of seven structural reasons. Anything not assignable to one of these categories is *in* scope by default.

| # | Category | Why structurally impossible in a server-side PDF |
|---|---|---|
| 1 | Script execution | No JS runtime; PDF output is one-shot static |
| 2 | Network at render-time | All resources resolved through `phpdftk/resource-loader` (Phase 4F) with explicit allowlists; no arbitrary fetch / WebSocket / WebRTC |
| 3 | Storage | No persistence between renders |
| 4 | Input / events | No user at render time |
| 5 | Runtime media playback | PDF embeds media as data; doesn't play it back |
| 6 | Device / sensor | No physical device |
| 7 | Page lifecycle / navigation | PDF is a single rendered artifact, not a navigable document tree |

## WHATWG HTML — excluded sections

These HTML living-standard sections are entirely out of scope. We still parse the surrounding markup (the spec mandates a tolerant parser) but render the document as if the listed surface never executed.

| Section | Category | Notes |
|---|---|---|
| §4.12 Scripting (`<script>`, `<noscript>`) | 1 | `<noscript>` content *is* rendered (no JS = noscript content shows) |
| §7 Loading webpages — navigation, history, sessions | 7 | Single-shot render; no navigation |
| §8 Web application APIs | 1 | Window object, named characters, Web IDL bindings |
| §9 Communication — `postMessage`, BroadcastChannel | 2 | No runtime |
| §10 Web workers | 1, 2 | No runtime |
| §11 Web storage — `localStorage`, `sessionStorage` | 3 | No persistence |
| §12 The HTML syntax — *implemented*; §13 The XML syntax — *implemented* | — | (Listed for clarity — these are in scope) |
| Drag and drop | 4 | No user |
| Cross-document messaging | 2 | No runtime |
| Custom elements + Shadow DOM (definition / attachment) | 1 | Static rendering of declarative shadow DOM (§4.13) is in scope |
| `document.write` and dynamic markup insertion | 1 | No runtime |
| `<iframe sandbox>` runtime restrictions | 1, 7 | Static iframe content rendering is in scope |
| Form submission | 1, 2 | Form *rendering* is in scope; submit handling isn't |
| `<dialog>` modal stack management | 4 | Open-state rendering is in scope; `showModal()` / `close()` aren't |
| `<details>` toggle behaviour | 4 | Open-state rendering is in scope |
| Popover invocation (`showPopover`, `hidePopover`) | 4 | Popover-open state rendering is in scope |

## CSS — excluded modules + features

### Entirely out-of-scope CSS modules

| Module | Category | Notes |
|---|---|---|
| CSS Scroll Snap 1 | 4, 7 | No scrolling in fixed-pagination PDF |
| CSS Overscroll Behavior 1 | 4 | No scroll |
| CSS Scrollbars 1 | 4 | No scrollbars in PDF |
| CSS Scroll Anchoring 1 | 4 | No scroll |
| CSS Will Change 1 | 1 | Perf hint — implemented as a no-op (parses, ignores) |
| CSS Touch 1 | 4 | No touch |
| CSS Speech 1 | 5 | Audio synthesis |
| CSS View Transitions 1, 2 | 7 | No navigation |
| CSS Highlight API 1 (interactive) | 4 | Static `::selection` rendering is in scope; runtime CustomHighlight isn't |
| CSS Navigation 1 (`nav-up` / `-down` / `-left` / `-right`) | 4 | Spatial nav — interactive |
| CSS Device Adaptation 1 (`@viewport`) | 6 | Device viewport doesn't apply |
| CSS Lifecycle (proposed) | 7 | No lifecycle |
| CSS Animation Worklet | 1 | Worklet runtime |
| CSS Paint API / Layout API / Properties & Values API runtime | 1 | Worklet runtime. `@property` (declarative) is in scope. |

### Partially out-of-scope CSS modules

Modules where the visual / declarative part is in scope but specific runtime features are out.

| Module | In scope | Out of scope | Category |
|---|---|---|---|
| CSS Selectors 4 | All structural / attribute / pseudo-class matching | `:hover`, `:focus`, `:focus-visible`, `:focus-within`, `:active`, `:user-invalid`, `:visited` (privacy), `:target-within` | 4 |
| CSS UI 4 | `caret-color`, `accent-color`, `appearance` (for form rendering) | `cursor`, `resize`, `pointer-events` (interactive parts), `user-select` | 4 |
| CSS Containment 3 | `contain-intrinsic-size`, size containment for layout | `contain: paint` perf semantics (no-op), `contain: style` perf (no-op) | 1 |
| CSS Logical Properties 1 | Declarative logical properties | Browser-internal RTL detection beyond `dir=` attr | — (none — fully in scope) |
| CSS Color Adjustment 1 | Light / dark color schemes (declarative) | `prefers-color-scheme` media query — defaults to `light` | 6 |
| CSS Inline 3 | `text-box-trim`, `initial-letter`, baseline alignment | — | — (fully in scope) |
| CSS Custom Properties 1 | `--foo`, `var()`, `@property` declarative | Paint Worklet integration | 1 |
| CSS Media Queries 5 | `print`, `screen`, `min-width`, `max-width`, `orientation`, paged contexts | `pointer`, `hover`, `prefers-reduced-motion`, `prefers-contrast` (defaults to no-preference), `update`, `inverted-colors` | 6 |

### Specific CSS properties / functions out of scope

| Property / function | Why | Category |
|---|---|---|
| `cursor: *` | No cursor in PDF | 4 |
| `resize: *` | No interactive resizing | 4 |
| `caret-color` declaration | In scope (used by form rendering); blinking caret rendering is out | 4 |
| `pointer-events: *` | No pointer | 4 |
| `user-select: *` | PDF text selection is the viewer's concern | 4 |
| `touch-action: *` | No touch | 4 |
| `scroll-*` properties (snap, padding, margin, behavior) | No scroll | 4 |
| `overscroll-behavior-*` | No scroll | 4 |
| `will-change` | Perf hint; parsed and ignored | 1 |
| `contain-paint` perf semantics | Perf hint | 1 |
| `image()` function with `image-set()` runtime selection | DPR is host-defined; `image-set()` picks one declaratively | 6 |
| `env(safe-area-inset-*)` | No safe area | 6 |
| `env(viewport-segment-*)` | No multi-screen | 6 |

## SVG 2 — excluded features

| Feature | Category | Notes |
|---|---|---|
| All event attributes (`onclick`, `onmouseover`, `onload`, …) | 1, 4 | Parsed, ignored |
| `<a>` interactive behaviour | 4 | Static rendering + PDF `/Link` annotation IS in scope |
| `cursor` attribute / `<cursor>` element | 4 | — |
| `pointer-events` attribute | 4 | — |
| SVG fonts (`<font>`, `<glyph>`) | — | Deprecated by SVG 2; out by deprecation, not by category |
| `<animate>`, `<animateTransform>`, `<animateMotion>`, `<set>` playback | See Animation strategy below | Final-state rendering via the `Pdf::renderAnimationsAt()` hook |
| `<script>` inside SVG | 1 | — |
| `<discard>` | 7 | Element removal at time t |
| `<view>` element | 7 | Navigation-target only |
| `<switch>` system-language interactive selection | 6 | First-match rendering IS in scope |
| `<foreignObject>` running JS / form interaction | 1, 4 | foreignObject *rendering* of HTML/CSS IS in scope |

## Web platform APIs — entirely out of scope

These are not rendered, parsed (where they appear in HTML), or surfaced. Listed for the contract's completeness.

### Networking + RPC
ECMAScript, Web IDL, Fetch API, XMLHttpRequest, Streams, Encoding (text encoding API is implementation detail in scope; the API surface isn't), WebSockets, WebRTC, WebTransport, Server-Sent Events, Beacon API, Background Fetch, Reporting API, Network Information API, Push API.

### Storage + offline
IndexedDB, Web Storage (localStorage / sessionStorage), Cache API, File API, File System Access API, Storage Foundation API, Cookies, Storage Buckets, Quota Management.

### Workers / lifecycle
Service Workers, Dedicated Workers, Shared Workers, Worklets (Paint, Layout, Animation, Audio), Page Lifecycle, Visibility API, Document Lifecycle, Frame Timing, Background Sync, Periodic Background Sync, Wake Lock.

### Audio / video runtime
Web Audio API, Web Speech API, Speech Synthesis, Media Capture and Streams, Media Session, Media Source Extensions, Encrypted Media Extensions, WebCodecs, Screen Capture, Picture-in-Picture.

### Sensors + device
Geolocation, DeviceMotion / DeviceOrientation, Gyroscope, Accelerometer, Magnetometer, Ambient Light Sensor, Proximity Sensor, Battery Status, Vibration, Gamepad, Generic Sensor API, Idle Detection.

### Graphics surfaces
WebGL 1, WebGL 2, WebGPU, Canvas 2D (the dynamic API — `<canvas>` poster-frame extraction via `getImageData` snapshot at render time may land via foreignObject), OffscreenCanvas, WebXR.

### Connectivity
Web NFC, Web USB, Web HID, Web Serial, Web Bluetooth, Web MIDI.

### Authentication / payments
Web Authentication, Credential Management, Payment Request, Payment Handler.

### Document / window control
History API, Navigation API, Window controls (`window.open`, `window.close`, `window.focus`), Pop-up blockers, Print preview hooks (`window.print()` itself is the *trigger*, not the renderer), Browsing context restrictions.

### Input / interaction
UI Events, Pointer Events, Touch Events, Mouse Events, Keyboard Events, Wheel Events, Input Events, Composition Events, Drag and Drop, Clipboard API, Selection API (interactive part — `::selection` style IS in scope), Pointer Lock, Fullscreen.

### Permissions / security runtime
Permissions API, Content Security Policy enforcement at render-time (CSP parsing for the document `<meta>` is in scope for `style-src` impact on `@import` only), Cross-Origin policy enforcement (CORS, COEP, COOP — we resolve all URLs through the resource loader with caller-configured allowlists), Subresource Integrity *enforcement at fetch time* (we honour the `integrity=` attribute via the loader, but don't implement runtime fetch).

### Identity / federation
WebID, FedCM, Credential Management, Web OTP.

## Animation strategy

CSS Animations, CSS Transitions, CSS Motion Path, and SVG SMIL (`<animate>`, `<animateTransform>`, `<animateMotion>`, `<set>`) declare time-varying properties. PDF is static, so we need a deterministic policy. The policy:

**`Pdf::renderAnimationsAt(float $t = 1.0)`** is a configurable hook on the `Pdf` and `PdfWriter` API. Callers pick a normalised time `[0.0, 1.0]` across the longest animation; properties resolve to their value at that time. Default `1.0` (final declared state).

Out of scope: keyframe-snapshot rendering (each iteration → new page) — would need a fragmentation strategy across pagination. Marked as a possible Phase 5XX+ extension if a strong real-world request emerges.

## What "100%" means in marketing copy

Users will read "100% HTML / CSS / SVG compliance" on the project page. The honest decoder is:

> 100% of the visual-rendering surface of HTML, CSS, and SVG as defined by the W3C and WHATWG specifications snapshotted on [DATE], excluding the runtime surfaces a server-side, headless renderer cannot implement (scripting, network, storage, events, sensors, page lifecycle). See `docs/spec/out-of-scope.md` for the complete ledger. Measured against the in-scope subset of Web Platform Tests.

The full sentence appears verbatim on the project landing page next to the 100% claim, with a link to this document. No "subject to" small print, no asterisk — the ledger *is* the asterisk, written out in full.

## Update policy

- **Permanent:** entries here are sticky across annual re-syncs. Removing an entry requires demonstrating that a server-side static renderer *can* implement it.
- **Additive:** new spec surfaces that publish during the year inherit out-of-scope-ness *by category*. If a new CSS module is published with both visual and interactive features, the visual features default to in-scope and the interactive ones default to out — exact triage happens at the January re-sync.
- **Documented:** every change to this file ships in the annual re-sync changelog with rationale.
