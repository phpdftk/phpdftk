# Writer Enrichments Roadmap

## Context

Today phpdftk's writer surface has three layers:

- **Level 0** — `PdfFileWriter` and the raw `PdfObject` tree (`packages/pdf/core/`)
- **Level 1** — `PdfWriter` and `Writer\Page`: resource registry + byte emission, plus a thin layer of doc-structure conveniences
- **Level 2** — `Pdf`: flow-layout document author with cursor, pagination, theming

Two distinct concerns live in `PdfWriter` today:

- **Byte-emission machinery** — fonts, images, content-stream registration, signing, encryption, conformance, linearization, version handling, `generate`/`save`. Close to the metal.
- **Friendly API over PDF document structures** — outline, page labels, named destinations, future annotation builders, form field builders, file attachments, viewer prefs, action factories, layer contexts. Conceptually these have nothing to do with bytes; they wrap `Catalog`/`Page`/`Annotation`/`FormField` etc.

Exploration surfaced ~60 additional "friendly wrapper" opportunities (Phase 4) where the core object exists in `pdf/core/` but no writer convenience is exposed. Adding all of them to `PdfWriter` would make it sprawling and conflate the two concerns. Splitting the friendly-API concern out into a new **`PdfDoc`** class — slotting it as Level 2 between `PdfWriter` (Level 1) and the renumbered `Pdf` (Level 3) — gives each class a single job and gives Phase 4 a natural home.

The resulting architecture:

```
Level 3 — Pdf                Flow-layout document author
   holds ↓                   Cursor, pagination, theming, addText / addTable / addImage
Level 2 — PdfDoc (new)       Friendly API over the PDF object model
   holds ↓                   addLink, addStickyNote, addCheckbox, attachFile,
                             setViewerPreferences, addLayer, action factories, …
   plus Writer\Page          (existing, also Level 2 in spirit) draw* + transforms + layer scope
Level 1 — PdfWriter          Resource registry + byte emission
   uses ↓                    addFont, addImage, register, generate, setSigner, …
Level 0 — PdfFileWriter      Raw object tree, xref, trailer, byte assembly
                             Catalog, Page, Annotation, FormField, FileSpec, …
```

Escape hatches: `Pdf::doc(): PdfDoc`, `PdfDoc::writer(): PdfWriter`, `PdfWriter::fileWriter(): PdfFileWriter`. Each layer can drop one level for power without leaving the system.

**Shared-renderer principle**: every new content primitive (Table, ListBlock, Callout, Barcode, SVG) is a self-contained renderer class that accepts a `ContentStream` + bounding rect. `Pdf::addX()` places it via flow; `Writer\Page::drawX()` places it explicitly. Renderer code is shared, never duplicated.

## Definition of Done

**No phase, sub-phase, or individual enrichment is considered complete unless every item below ships alongside the code.** This is non-negotiable — partial deliverables block the next phase from starting.

1. **Positive-path tests** — unit + integration tests that exercise the documented happy path and assert correct output. Where the feature has structural meaning (annotations, form fields, file attachments, layers, outlines, viewer prefs), the integration test round-trips through `PdfReader` and asserts presence/typing of the relevant catalog entries.
2. **Negative-path tests** — unit tests covering at least: invalid/edge inputs (empty arrays, zero-size rects, missing prerequisites, null Info dicts), idempotency boundaries (calling the same setter twice, calling output methods repeatedly), and pinned behavior for documented edge cases (pages added after the decorator pass, deprecated forwarder paths continuing to work, latest-call-wins semantics on replaceable hooks).
3. **Documentation** — a doc page or update under `docs/site/src/content/docs/` covering every new API surface, embedding the runnable sample (see #4) via the `<Example>` Astro component so readers see the exact code and can download the rendered PDF. Update `docs/site/src/content/docs/standards/spec/coverage.md` whenever a new PDF spec feature gains writer coverage (Phase 4 touches this heavily).
4. **Runnable samples** — every new public API has a runnable script under `examples/writer/` (or another `examples/<area>/` directory if the feature lives outside the writer package). The script:
   - Wraps the showcased call in `// region: example` / `// endregion` markers so `<Example>` can extract the demo code without the boilerplate.
   - Calls `example_output_path('writer/<name>.pdf')` to emit the rendered PDF to `docs/site/public/samples/writer/`, so the published docs link to the same artefact a reader will download.
   - Exercises **realistic** input — not toy values. A header/footer sample sets a non-default theme and shows a multi-page document with page numbers; a link sample makes both URI and internal-destination links; a metadata sample syncs into XMP. Samples double as smoke tests for the API surface.
5. **Benchmarks** — a benchmark under `benchmarks/` for every feature on a hot path (anything in `Pdf::addX` / `Writer\Page::drawX`, the multi-column layout engine, barcode and SVG renderers, table/list pagination). Metadata setters, viewer-prefs configuration, and pure factory helpers may skip benchmarks but must explicitly note why in the PR description. Hot-path benchmarks should appear in the **Writer Levels Comparison** section of the benchmarks report when the feature exists at multiple levels (`Pdf` flow vs. `Writer\Page` positioned vs. `PdfWriter` raw), so the abstraction overhead is visible.
6. **Mark done** — flip the row in the Implementation status table from Pending → ✅ Done with a one-line note of what shipped. This is the visible acknowledgment that artefacts 1–5 are in place; reviewers should not approve a PR that ships code but leaves the status row untouched. Each phase boundary also runs `composer lint:fix && composer analyse && composer test` — passing those is a precondition for flipping the status, not a substitute for it.

PR reviewers should reject any work that ships code without all six artefacts.

## Implementation status

Each row marked ✅ Done has shipped with positive + negative tests, documentation, runnable samples under `examples/writer/`, (where the feature is on a hot path) a benchmark, and the status flip below — meeting the Definition of Done above.

| Phase | Status | Notes |
|---|---|---|
| 0 — three-level refactor | ✅ Done | `PdfDoc` added; doc-structure methods moved with `@deprecated` forwarders on `PdfWriter`. |
| 1.1 — per-page hooks | ✅ Done | `setHeader/setFooter/setWatermark`, `PageContext`, `PageDecorator`. Two-pass render. |
| 1.2 — metadata setters | ✅ Done | `setTitle/setAuthor/setSubject/setKeywords/setCreator` on both `Pdf` and `PdfDoc`. |
| 1.3 — link annotations | ✅ Done | `PdfDoc::addLink()` accepts URI string, `Destination`, or `PdfReference` target with optional `BorderStyle`. |
| 2.1 — tables | ✅ Done | `Pdf::addTable()` with auto-pagination + repeating header; `Writer\Page::drawTable()` positioned variant; shared `TableRenderer`; `TextLayout` extracted. |
| 2.2 — lists | ✅ Done | `Pdf::addList()` / `Pdf::addNumberedList()` with per-item pagination; `Writer\Page::drawList()`; shared `ListRenderer`. Nesting deferred to v2. |
| 2.3 — inline hyperlinks | ✅ Done | `TextStyle::link` URI field; `Pdf::addText()` emits one link annotation per wrapped line; works across page breaks. |
| 2.4 — page numbers | ✅ Done | `Pdf::showPageNumbers(format, alignment, size)` — sugar over `setFooter()` using `PageContext::$totalPages`. |
| 2.5 — auto-TOC | ✅ Done | `Pdf::enableOutline()` instruments `addHeading()` to register hierarchical `OutlineItem`s with `/XYZ` destinations. |
| 3.1 — text decoration | ✅ Done | `TextStyle::underline` / `::strikethrough`; per-line decoration in `Pdf::addText` and `Writer\Page::drawText`. |
| 3.2 — blockquote | ✅ Done | `Pdf::addQuote()` themed italic body + left bar with multi-page bar segments; `Writer\Page::drawQuote()`; Theme gains `quoteIndent` / `quoteBarWidth` / `quoteBarColor`. |
| 3.3 — callouts | ✅ Done | `CalloutType` enum (Note/Tip/Warning/Danger), `CalloutStyle` overrides, `Pdf::addCallout()` with auto-advance-to-new-page, `Writer\Page::drawCallout()`. |
| 4.1 — annotation builders | ✅ Done | 15 wrappers on `PdfDoc` (sticky note, free-text, highlight, underline, squiggly, strikeout, caret, ink, line, polygon, polyline, square, circle, stamp, watermark). |
| 4.3 — file attachments | ✅ Done | `PdfDoc::attachFile()` / `attachFileBytes()` with `/AFRelationship` for ZUGFeRD; appends to catalog `/AF` array. `Pdf::attachFile()` forwarder. |
| 4.4 — graphics state helpers | ✅ Done | `Writer\Page::rotate/scale/translate/skew/withTransform/setOpacity`; ExtGState reused across calls with identical alpha. |
| 4.6 — layers | ✅ Done | `PdfDoc::addLayer()` builds OCG + OCPropertiesDict lazily; `Writer\Page::inLayer()` wraps closure ops with `/OC /MC<n> BDC … EMC`. |
| 4.7 — page rotation + boxes | ✅ Done | `Writer\Page::setRotation` (90 multiples), `setCropBox`, `setBleedBox`, `setTrimBox`, `setArtBox`. |
| 4.8 — action factories | ✅ Done | `Action::uri/goTo/goToRemote/javascript/launch/namedAction/resetForm/submitForm`; `PdfDoc::setOpenAction()`; `Pdf::setOpenAction()` forwarder. |
| 4.9 — destination factories | ✅ Done | Already shipped on `Phpdftk\Pdf\Core\Document\Destination` (`xyz`, `fit`, `fitH`, `fitV`, `fitR`, `fitB`, `fitBH`, `fitBV`); used by `PdfDoc::addLink()`. |
| 4.10 — viewer preferences | ✅ Done | `PdfDoc::setViewerPreferences(ViewerPreferences\|Closure)`; `Pdf::setViewerPreferences()` forwarder. Catalog `viewerPreferences` widened to `?Serializable`. |
| 4.2 — form fields | ✅ Done | `addTextField` / `addCheckbox` / `addChoiceField` / `addSignatureField` build AcroForm lazily; Widget annotations linked via `/Parent` ↔ `/Kids`. Option DTOs under `Form/`. RadioGroup / PushButton deferred. |
| 4.5 — gradients | ✅ Done | `addLinearGradient` (ShadingType2) + `addRadialGradient` (ShadingType3) return `ShadingPattern`; `Writer\Page::useGradient()` attaches as page Pattern resource. |
| 4.11 — spot colors | ✅ Done | `registerSpotColor(name, cmykTint)` returns `SpotColor` wrapping a `Separation` value with a registered tint function; `Writer\Page::useSpotColor()` adds it to the page color-space resources. |
| 4.12 — Form XObject templates | ✅ Done | `PdfDoc::createTemplate(bbox, closure)` captures operators into a registered `FormXObject`; `Writer\Page::drawTemplate(x, y, ?w, ?h)` emits `q cm /Tpl<n> Do Q` and reuses the resource across calls. |
| 4.13 — multimedia + 3D | ✅ Done | `addSoundAnnotation` / `addMovieAnnotation` (deprecated in PDF 2.0) + `add3DAnnotation`. Caller supplies the underlying `Sound` / `Movie` / `ThreeDStream`. |
| 5.1 — total-page alias | ✅ Done | Covered by Phase 1.1's `PageContext::$totalPages` — no `{nb}` token mechanism needed. |
| 5.2 — multi-column layout | ✅ Done | `Pdf::setColumns(count, gutter)`; content fills current column then advances to next, only paging after last column overflows. `advanceOnOverflow()` helper centralises the column / page transition. |
| 5.3 — barcodes | ✅ Done | `phpdftk/barcode` ships Code 128 / 39 / Codabar / ITF / EAN-13 / EAN-8 / UPC-A and the four major 2D symbologies — QR (versions 1-40, ECC L/M/Q/H, numeric/alphanumeric/byte), Data Matrix (ECC 200, 24 square sizes 10×10–144×144, ASCII mode with digit-pair compression), PDF417 (Byte Compaction mode, ISO/IEC 15438 with the full 2 787-entry codeword pattern table from AIM USS-PDF417 Annex H1, RS over GF(929), auto-selected rows/columns/ECC level — cross-validated byte-for-byte against TCPDF), and Aztec (ISO/IEC 24778 **Compact + Full formats with high-level state-search encoder**, L=1–4 / 1–32 respectively, sizes 15×15 to 151×151, RS over GF(64)/GF(256)/GF(1024)/GF(4096) selected by layer count with GF(16) mode-message ECC, ~23% ECC, reference grid for Full L ≥ 5, Dijkstra-style state search across Upper/Lower/Mixed/Punct/Digit sub-alphabets + Binary Shift fallback — ~30–40% denser symbols than raw byte mode for ASCII payloads; algorithm + constants line-for-line against ZXing.NET reference, spot-checked bit streams against hand-traced spec values). Writer adapters: `Pdf::addBarcode`, `Writer\Page::drawBarcode`, `PdfDoc::createBarcode`. |
| 5.4 — SVG rendering | Superseded | See [`docs/plans/html-and-svg.md`](html-and-svg.md). The minimal `simplexml`-based static renderer originally scoped here is replaced by a substrate-backed approach (`phpdftk/svg` parser + `phpdftk/svg-to-pdf` painter, consuming `phpdftk/css` + `phpdftk/text`). Lands as Phase 3 of the rendering roadmap. |

## Roadmap

### Phase 0 — Three-level refactor (prerequisite for everything else) — ✅ Done

**0.1 Introduce `PdfDoc`**
- New: `packages/pdf/writer/src/PdfDoc.php`. Owns a `PdfWriter` instance internally.
- Constructor: `new PdfDoc(bool $compressStreams = true, PdfVersion|string $version = …)` — creates a fresh `PdfWriter` under the hood.
- Static factory `PdfDoc::wrap(PdfWriter $writer): self` — for users who already have a configured `PdfWriter` (signer, encryption, conformance) and want the friendly API on top.
- Accessor: `PdfDoc::writer(): PdfWriter` — escape hatch.

**0.2 Move doc-structure methods from `PdfWriter` to `PdfDoc`**
- Moved with `@deprecated` forwarding stubs on `PdfWriter` (one-release window):
  - `setOutline(Outline)` → `PdfDoc::setOutline()`
  - `addOutlineItem(OutlineItem)` → `PdfDoc::addOutlineItem()`
  - `setPageLabels(array)` → `PdfDoc::setPageLabels()`
  - `setNamedDestinations(array)` → `PdfDoc::setNamedDestinations()`
  - `setInfo(Info)` → `PdfDoc::setInfo()`
  - `setMetadata(string)` → `PdfDoc::setMetadata()`
  - `syncInfoToMetadata()` → `PdfDoc::syncInfoToMetadata()`
- `addPage(?Rectangle): Writer\Page` — kept on `PdfWriter` (registration call), also exposed on `PdfDoc::addPage()` as the recommended entry point.
- Forwarding stubs on `PdfWriter` keep `Pdf::writer()->addOutlineItem(...)` working unchanged.

**0.3 Stays on `PdfWriter` (Level 1, byte/resource concerns):**
`addFont`, `addCompositeFont`, `addOpenTypeFont`, `getFonts`, `addImage` (raw, returns resource name), `addContentStream`, `register`, `getCatalog`, `fileWriter`, `setSigner`, `setTsaClient`, `setTimestamper`, `setEncryption`, `setConformance`, `setConformanceProfiles`, `checkConformance`, `getConformanceResults`, `setLinearized`, `getPdfVersion`, `setStrictVersionMode`, `setCeilingVersion`, `setDeprecationHandler`, `setStrictDeprecation`, `getVersionWarnings`, `getEncodingWarnings`, `getContentStreams`, `generate`, `toBytes`, `writeTo`, `save`.

**0.4 `Pdf` (Level 3) rewires**
- Internal `$writer` field stays for back-compat lookups; new `$doc` field holds the `PdfDoc`. `Pdf::writer()` keeps working as `$this->doc->writer()`.
- New `Pdf::doc(): PdfDoc` accessor.

**0.5 Back-compat**
- Every method moved in 0.2 keeps a stub on `PdfWriter` for one minor release, marked `@deprecated`. The stub lazily caches a `PdfDoc::wrap($this)` and delegates.
- Tests for the deprecated paths continue to pass.

### Phase 1 — Shared foundation

**1.1 Per-page render hooks on `Pdf`** — ✅ Done
- `Pdf::setHeader(Closure)`, `setFooter(Closure)`, `setWatermark(string|Closure $textOrFn, float $opacity = 0.2, float $angleDeg = 45)`.
- `packages/pdf/writer/src/PageContext.php` — value object carrying `pageNumber`, `totalPages`, `page` (Writer\Page), `pageWidth`, `pageHeight`, `theme`.
- `packages/pdf/writer/src/PageDecorator.php` — immutable holder for the three closures.
- Hooks invoked in a single deferred pass (`Pdf::applyDecorators()`) right before `toBytes`/`save`/`writeTo` so `totalPages` is resolvable.
- `Theme` gains `headerHeight` / `footerHeight`; body cursor starts below the header reserve and the bottom-margin checks include the footer reserve.
- String-form watermark renders as centred grey diagonal Helvetica-Bold; opacity is approximated by lightening fill (true opacity arrives in Phase 4.4 with `ExtGState`).

**1.2 Document metadata fluent setters** — ✅ Done
- `PdfDoc::setTitle/setAuthor/setSubject/setKeywords/setCreator` lazily create an `Info` dict if absent and set the corresponding field. All return `$this`.
- `Pdf::setTitle()` etc. forward to `PdfDoc`. All return `$this`.
- Existing `setInfo(Info)` / `setMetadata(string)` / `syncInfoToMetadata()` still work — the fluent setters complement them.

**1.3 Link annotation builder** — ✅ Done
- `PdfDoc::addLink(Page|CorePage $page, Rectangle $rect, string|Destination|PdfReference $target, ?BorderStyle $border = null): LinkAnnotation`.
- `string` → URI link (inline `/A` action dict).
- `Destination` → inline `/Dest` (uses `Destination::fit/xyz/fitH/...`).
- `PdfReference` → `/Dest` pointing to a named-tree entry (registered via `setNamedDestinations()`).
- Core change: widened `LinkAnnotation::$dest` from `?PdfReference` to `?Serializable` so inline `Destination` objects work. `Destination` already implements `Serializable`.

### Phase 2 — Level 3 content primitives (with shared renderers usable from Level 2)

Each primitive ships as a self-contained renderer (takes `ContentStream` + `Rectangle`) plus:
- `Pdf::addX(...)` — places via flow (Level 3).
- `Writer\Page::drawX(...)` — places at explicit `(x, y, w, h)` (Level 2).

**2.1 Tables**
- New: `Table.php` (data), `TableStyle.php`, `TableRenderer.php`.
- `Pdf::addTable(array $rows, ?array $columnWidths = null, ?TableStyle $style = null)` — flows + auto-paginates row by row. Header row repeats.
- `Writer\Page::drawTable(Table $table, float $x, float $y, ?float $maxWidth = null, ?TableStyle $style = null)`.

**2.2 Lists**
- New: `ListBlock.php`, `ListStyle.php`, `ListRenderer.php`.
- `Pdf::addList(array $items, ?ListStyle $style = null)` and `Pdf::addNumberedList(...)`.
- `Writer\Page::drawList(ListBlock $list, float $x, float $y, float $maxWidth, ?ListStyle $style = null)`.

**2.3 Inline hyperlinks**
- Extend `TextStyle` with `link: ?string` and `linkTo: ?string`.
- Variadic rich-text `addText(string $a, TextStyle $sa, string $b, TextStyle $sb, …)`.
- Compute bounding rect per styled segment and call `PdfDoc::addLink()`.

**2.4 Page numbers** — `Pdf::showPageNumbers(string $format = 'Page %d of %d', Alignment $align = Alignment::Center)`. Sugar over `setFooter()`; uses `PageContext::$totalPages` from 1.1.

**2.5 Auto-TOC from headings** — `Pdf::enableOutline(bool $enabled = true)`. Each `addHeading()` registers an `OutlineItem` via `PdfDoc::addOutlineItem()`, parented hierarchically by level.

### Phase 3 — Level 3 styling

**3.1 Text decoration** — `TextStyle::underline`, `strikethrough`. Render as graphics line per segment.

**3.2 Blockquote** — `Pdf::addQuote(string, ?TextStyle)` and `Writer\Page::drawQuote(...)`. Themed indented italic body + left bar.

**3.3 Callouts** — `CalloutType` enum (`Note`, `Tip`, `Warning`, `Danger`). Theme-driven colors. `Pdf::addCallout(string, CalloutType)` and `Writer\Page::drawCallout(...)`.

### Phase 4 — Level 2 (`PdfDoc` + `Writer\Page`) wrapping pass

Wrap every existing core class that today requires `register()` plumbing. Each method below has its underlying class already in `packages/pdf/core/`.

**4.1 Annotation builders on `PdfDoc`** — one method per annotation type, attaching to `$page->annots` and registering:

```
addLink(Page, Rectangle, string|Destination, ?BorderStyle)        // already in Phase 1.3
addStickyNote(Page, point, content, options)            // TextAnnotation
addFreeText(Page, rect, content, appearance)            // FreeTextAnnotation
addHighlight(Page, quadPoints, options)                 // HighlightAnnotation
addUnderlineAnnotation(Page, quadPoints, options)       // UnderlineAnnotation
addSquiggly(Page, quadPoints, options)                  // SquigglyAnnotation
addStrikeout(Page, quadPoints, options)                 // StrikeOutAnnotation
addCaret(Page, rect, options)                           // CaretAnnotation
addInk(Page, paths, options)                            // InkAnnotation
addLineAnnotation(Page, from, to, options)              // LineAnnotation
addPolygon(Page, points, options)                       // PolygonAnnotation
addPolyline(Page, points, options)                      // PolyLineAnnotation
addSquare(Page, rect, options)                          // SquareAnnotation
addCircleAnnotation(Page, rect, options)                // CircleAnnotation
addStamp(Page, rect, name|appearance, options)          // StampAnnotation
addWatermarkAnnotation(Page, rect, content, options)    // WatermarkAnnotation
```

Shared `AnnotationOptions` value object for color/border/opacity/flags.

**4.2 Form field builders on `PdfDoc`** — lazily create `AcroForm` on `Catalog` on first call:

```
addTextField(name, Page, Rectangle, TextFieldOptions)       → TextField
addCheckbox(name, Page, Rectangle, CheckboxOptions)         → ButtonField
addRadioGroup(name, array<Page, Rectangle, exportValue>)    → ButtonField
addPushButton(name, Page, Rectangle, ButtonOptions)         → ButtonField
addChoiceField(name, Page, Rectangle, array, options)       → ChoiceField
addSignatureField(name, Page, Rectangle)                    → SignatureField
```

Option DTOs in `packages/pdf/writer/src/Form/`.

**4.3 File attachments on `PdfDoc` + mirrored on `Pdf`**
- `PdfDoc::attachFile(string $path, ?string $description = null, ?string $mimeType = null, ?string $relationship = null): FileSpec`. The `relationship` parameter unblocks ZUGFeRD/Factur-X (`/AFRelationship /Alternative`).
- `PdfDoc::attachFileBytes(string $name, string $bytes, …)` — in-memory variant.
- `Pdf::attachFile(...)` forwards to `PdfDoc`.
- All disk reads go through `Phpdftk\Filesystem\LocalFilesystem::readFile()`.

**4.4 Graphics state helpers on `Writer\Page`**
- `rotate(float $degrees, ?float $cx = null, ?float $cy = null): self`
- `scale(float $sx, float $sy): self`
- `translate(float $tx, float $ty): self`
- `skew(float $alphaDeg, float $betaDeg): self`
- `withTransform(Closure $body, ...transforms): self` — wraps body in `q ... Q`.
- `setOpacity(float $stroke, float $fill): self` — registers `ExtGState`, emits `gs`.

**4.5 Gradients on `PdfDoc`**
- `addLinearGradient(Point $from, Point $to, array $stops): ShadingPattern`
- `addRadialGradient(Point $center, float $radius, array $stops): ShadingPattern`

**4.6 Optional content (layers)**
- `PdfDoc::addLayer(string $name, bool $visible = true): OCG`
- `Writer\Page::inLayer(OCG $layer, Closure $body): self` — wraps content-stream ops with `/OC /MC0 BDC ... EMC`.

**4.7 Page rotation + page boxes on `Writer\Page`**
- `setRotation(int $degrees): self` (multiples of 90).
- `setCropBox(Rectangle)`, `setBleedBox`, `setTrimBox`, `setArtBox`.

**4.8 Action factories**
- New: `packages/pdf/writer/src/Action.php` (static factory).
- `Action::uri/goTo/goToRemote/javascript/launch/namedAction/resetForm/submitForm`.
- `PdfDoc::setOpenAction(Action): self` — sets `Catalog::$openAction`.
- `Pdf::setOpenAction(Action)` forwards.

**4.9 Destination factories** — already on `Destination` (`xyz`, `fit`, `fitH`, `fitV`, `fitR`, `fitB`, `fitBH`, `fitBV`). Confirm coverage, document.

**4.10 Viewer preferences**
- `PdfDoc::setViewerPreferences(ViewerPreferences|Closure): self`. Closure form receives a builder.
- `Pdf::setViewerPreferences(...)` forwards.

**4.11 Spot colors**
- `PdfDoc::registerSpotColor(string $name, Color $tint): Separation` — returns color space resource usable from `Writer\Page` paint ops.

**4.12 Form XObject templates**
- `PdfDoc::createTemplate(Rectangle $bbox, Closure $draw): FormXObject`
- `Writer\Page::drawTemplate(FormXObject $tpl, float $x, float $y, ?float $w = null, ?float $h = null): self`

**4.13 Multimedia + 3D (low priority but completes coverage)**
- `PdfDoc::addSoundAnnotation/addMovieAnnotation/add3DAnnotation`.

### Phase 5 — Absent-entirely features (net-new code, own packages)

Each is **not** in `pdf/core/` today and is **not** in the PDF spec — TCPDF builds them on top of standard PDF graphics. Each ships as a self-contained renderer (Level-agnostic) plus thin wrappers at Levels 2 and 3.

HTML rendering is explicitly **not** in Phase 5 — it's spun off as a separate future project (see "Out of scope").

**5.1 Total-page-count alias** — already covered by Phase 1.1's two-pass render and `PageContext::$totalPages`. No `{nb}` token mechanism needed.

**5.2 Multi-column layout on `Pdf`**
- `Pdf::setColumns(int $count, float $gutter = 12): self` reshapes the body region.
- Extract a `LayoutEngine` class from `Pdf` that owns "next available rect".
- Existing flow methods work unchanged.
- `Table`/`Image` get a `columnSpan(n)` opt-in to span columns.

**5.3 Barcodes — `phpdftk/barcode` (new package)**
- Symbology: Code 128, Code 39, EAN-13, EAN-8, UPC-A, ITF, Codabar, QR, Data Matrix, PDF417, Aztec.
- Core API (zero PDF dep): `BarcodeRenderer::render(Symbology, string $data, BarcodeOptions): BarcodeBitmap`.
- Adapter API:
  - `PdfDoc::createBarcode(...)` (Level 2 — reusable `FormXObject`)
  - `Writer\Page::drawBarcode(...)` (Level 2 — direct placement)
  - `Pdf::addBarcode(...)` (Level 3 — flow placement)
- License-aware: implement encoders fresh; do not vendor GPL.

**5.4 SVG rendering — `phpdftk/svg-to-pdf` (new package)**
- Static renderer: parses SVG with `simplexml`, emits content-stream operators.
- Adapter API:
  - `PdfDoc::createSvgTemplate(...)` (Level 2 — reusable)
  - `Writer\Page::drawSvg(...)` (Level 2)
  - `Pdf::addSvg(...)` (Level 3)
- In scope: paths, basic shapes, `g` + transforms, fills/strokes (solid + gradients via 4.5), opacity, basic `text`.
- Out of scope: filters, masks, CSS selectors, `<foreignObject>`, scripting, animation, `<pattern>`.

## Sequencing

```
Phase 0 (three-level refactor)              ✅ Done
   ↓
Phase 1 (foundation: hooks, metadata, link annotation)  ✅ Done
   ↓                ↓
Phase 2          Phase 4 (independent of Phases 1.1/2/3)
   ↓
Phase 3 (Level 3 styling)

Phase 5.1 (total-page) — already covered by 1.1
Phase 5.2 (multi-column) — independent; refactors Pdf internals (avoid in parallel with Phase 2)
Phase 5.3 (barcodes) — depends on 4.12 (FormXObject template helper) ideally; standalone if needed
Phase 5.4 (SVG) — uses 4.5 (gradients) and 4.12 (templates)
```

## Critical files

**Modified:**
- `packages/pdf/writer/src/Pdf.php` — Phase 0.4, 1.1, 1.2; future Phases 2.x, 3.x, 4.3/4.8/4.10 forwarders, 5.2/5.3/5.4 entry points.
- `packages/pdf/writer/src/PdfWriter.php` — Phase 0.2 move-with-deprecation.
- `packages/pdf/writer/src/Page.php` — future Phase 4.4 (transforms/opacity), 4.6 (`inLayer`), 4.7 (rotation/boxes), 4.12 (`drawTemplate`); Phase 2/3 draw* primitives; Phase 5.3/5.4.
- `packages/pdf/writer/src/Theme.php` — `headerHeight`/`footerHeight` (1.1); future list indent, table defaults, callout colors.
- `packages/pdf/writer/src/TextStyle.php` — future `link`/`linkTo`/`underline`/`strikethrough`.
- `packages/pdf/core/src/Annotation/LinkAnnotation.php` — `$dest` widened to `?Serializable` (1.3).

**Created:**
- Phase 0: `PdfDoc.php` ✅
- Phase 1: `PageDecorator.php`, `PageContext.php` ✅
- Phase 2 (planned): `Table.php`, `TableStyle.php`, `TableRenderer.php`, `ListBlock.php`, `ListStyle.php`, `ListRenderer.php`.
- Phase 3 (planned): `CalloutType.php`, `Callout.php`, `CalloutRenderer.php`, `Blockquote.php`, `BlockquoteRenderer.php`.
- Phase 4 (planned): `Form/TextFieldOptions.php`, `Form/CheckboxOptions.php`, `Form/RadioOptions.php`, `Form/ButtonOptions.php`, `Form/ChoiceFieldOptions.php`, `AnnotationOptions.php`, `Action.php` (factory).
- Phase 5 (planned): new packages `packages/barcode/`, `packages/svg-to-pdf/`. `packages/pdf/writer/src/LayoutEngine.php` for 5.2.

## Existing primitives reused

- `Phpdftk\Pdf\Core\Annotation\*` — Phase 4.1.
- `Phpdftk\Pdf\Core\Interactive\Form\*` — Phase 4.2.
- `Phpdftk\Pdf\Core\FileSpec\FileSpec`, `EmbeddedFile` — Phase 4.3.
- `Phpdftk\Pdf\Core\Content\ContentStream::concatMatrix()` — Phase 4.4.
- `Phpdftk\Pdf\Core\Graphics\ExtGState` — Phase 4.4 (opacity).
- `Phpdftk\Pdf\Core\Graphics\Shading\*`, `Pattern\ShadingPattern` — Phase 4.5, 5.4.
- `Phpdftk\Pdf\Core\Document\OCG`, `OCMD`, `OCConfig` — Phase 4.6.
- `Phpdftk\Pdf\Core\Document\Page::$rotate`, `$cropBox`, etc. — Phase 4.7.
- `Phpdftk\Pdf\Core\Action\*` (all 22 action classes) — Phase 4.8.
- `Phpdftk\Pdf\Core\Document\Destination` — Phase 4.9.
- `Phpdftk\Pdf\Core\Document\ViewerPreferences` — Phase 4.10.
- `Phpdftk\Pdf\Core\Graphics\ColorSpace\Separation` — Phase 4.11.
- `Phpdftk\Pdf\Core\Graphics\XObject\FormXObject` — Phase 4.12, 5.3, 5.4.
- `Phpdftk\Pdf\Core\Multimedia\Sound`, `Movie`; `ThreeD\*` — Phase 4.13.
- `Phpdftk\Filesystem\LocalFilesystem::readFile()` — Phase 4.3, 5.3/5.4.

## Per-feature completion criteria

See [Definition of Done](#definition-of-done) above. Every enrichment ships with all four artefacts: positive-path tests, negative-path tests, documentation, and (for hot-path features) benchmarks. CLAUDE.md's new-feature checklist makes the same demand; this plan enforces it across the whole roadmap.

## Verification

- `composer lint:fix && composer analyse && composer test` — passes on every phase boundary.
- Per phase: `composer test -- --testsuite writer`. For Phase 4 structural features, `PdfReader::open($path)` and assert correct typing.
- Phase 4.2/4.3: `composer compliance` for PDF/A — file attachments are key for ZUGFeRD; form fields must declare correct flags.
- Phase 5: new test suites added to root `phpunit.xml`. For 5.3, decode barcodes with a known scanner library; for 5.4, golden-PDF comparisons.
- Manual: open output PDFs in a real viewer — links clickable, form fields interactive, watermark visible, page numbers correct, outline navigable, layers togglable, barcodes scannable.

## Migration notes (Phase 0)

- One-release deprecation window: methods moved to `PdfDoc` retain stubs on `PdfWriter` marked `@deprecated`. Stubs lazily wrap `$this` in a `PdfDoc` and delegate.
- Update public examples in `docs/site/` to call `PdfDoc` directly (`$pdf->doc()->addOutlineItem(...)` instead of `$pdf->writer()->addOutlineItem(...)`).
- `CHANGELOG.md` entry: "Added `PdfDoc` — friendly API over the PDF object model. Existing methods on `PdfWriter` (`setOutline`, `addOutlineItem`, `setPageLabels`, `setNamedDestinations`, `setInfo`, `setMetadata`, `syncInfoToMetadata`) now live on `PdfDoc`; `PdfWriter` retains them as deprecated forwarders for one release."
- Major-version bump is **not** required — `PdfWriter` still satisfies all previous call patterns.

## Out of scope (deferred indefinitely)

- **HTML rendering — spun off as a separate future project.** Doing it right means a fully spec-compliant HTML5 parser + CSS engine (CSS 2.1 visual formatting, Selectors 3, Flexbox, Grid, Paged Media, Custom Properties, Transforms, Writing Modes, …) + Unicode line-breaking and bidi, plus image-format support. That is a multi-year effort comparable to WeasyPrint or Chromium and far exceeds the scope of a writer-enrichment roadmap. Users who need HTML→PDF today should use headless Chromium or WeasyPrint as a separate tool. If/when phpdftk pursues HTML rendering, it lives in its own repository with its own roadmap and maintainer team.
- Tagged-structure helpers for full PDF/UA conformance (own design pass; `StructTreeRoot`, `MarkInfo`, role mapping, content-stream tagging).
- Conformance profile presets — `forPdfA2b()`, `forPdfUa()`, `forZugferd($xml)`. Valuable but standalone effort; likely belongs at Level 2 (`PdfDoc::forZugferd()` etc.) once Phase 4.3 lands.
- Encryption preset shortcuts.
- SVG features beyond the static-rendering subset listed in 5.4.
- `<canvas>` rendering via headless JS.
- Web fonts (`@font-face`).
