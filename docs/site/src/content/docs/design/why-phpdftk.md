---
title: Why phpdftk?
description: The problems with existing PHP PDF libraries and how phpdftk addresses them.
---

## The state of PHP PDF libraries

Every popular PHP PDF library makes the same trade-off: hide the PDF spec behind an HTML-like or drawing API, and hardcode whichever subset of features the author needed. This creates three recurring problems.

### 1. The abstraction ceiling

TCPDF, mPDF, and Dompdf let you generate common documents quickly. But the moment you need something outside their API — a custom annotation, a specific blend mode, a tagged PDF structure tree, a non-standard encryption scheme — you hit a wall. There's no way down to the spec. The library authors decided what PDF features you get access to, and everything else is locked behind private methods and undocumented internals.

phpdftk inverts this. The PDF spec *is* the API. Every object, every field, every operator has a typed PHP counterpart. The higher-level APIs (`Pdf`, `PdfWriter`) are convenience layers on top — not locked doors.

### 2. The string-concatenation engine

Most PHP PDF libraries build output by concatenating strings. Object numbers are global counters. Cross-reference tables are assembled by tracking byte offsets through string length arithmetic. This works, but it means:

- No object model to inspect or manipulate before serialization
- No round-tripping (read a PDF, modify it, write it back)
- Testing requires parsing the output string to verify correctness
- Adding features means modifying the serialization engine itself

phpdftk separates the object model from serialization. You build a tree of typed PHP objects (`Catalog`, `Page`, `Font`, `ContentStream`, ...), register them with an `ObjectRegistry`, and the `PdfFileWriter` handles numbering, xref, and trailer emission. The same objects can be read from an existing PDF, modified, and written back.

### 3. The dependency sprawl

mPDF pulls in 8+ dependencies. Dompdf needs a CSS engine, an HTML parser, and optionally a font subsetter. TCPDF bundles 100+ MB of font data. These dependencies introduce version conflicts, security surface area, and installation friction.

phpdftk's support packages (geometry, color, filters, encoding, font-metrics, font-parser, image-metadata, xmp, crypt) are all first-party, zero-dependency, and independently usable. The total install is small because nothing is bundled that isn't needed.

## Design decisions

### Why typed objects instead of arrays?

PHP's tradition is to pass around associative arrays. phpdftk uses typed classes for every PDF dictionary type. This means:

- **IDE support**: autocomplete, go-to-definition, and refactoring work out of the box
- **Static analysis**: PHPStan catches type errors before runtime
- **Discoverability**: the API surface is the spec — browse `Core\Document\Page` to see all page fields
- **Safety**: you can't assign a string to a field that expects a `PdfReference`

The trade-off is verbosity for simple cases. A `new Page()` with manual property assignment is more code than `['Type' => '/Page', ...]`. The higher API levels (`PdfWriter`, `Pdf`) exist specifically to eliminate this verbosity for common cases.

### Why three API levels?

Not every PDF task requires the same level of control:

- **A report generator** doesn't need to know what a content stream operator is. It needs `addHeading()` and `addText()`.
- **An invoice template** needs precise coordinates but not raw object numbers. It needs `drawText(x, y)` and `drawRectangle()`.
- **A PDF/A validator** or a digital signing library needs direct access to the object graph, trailer, and byte offsets.

One API can't serve all three well. Making the high-level API powerful enough for the third case would make it too complex for the first. Making it simple enough for the first would cripple the third.

The escape hatch pattern solves this: start at the highest level that works, drop down when you need to. `$pdf->writer()` gives you the `PdfWriter`. `$page->contentStream()` gives you the `ContentStream`. `$writer->fileWriter()` gives you the `PdfFileWriter`. Each transition is a single method call, and you can go back up — the levels interleave cleanly.

### Why a monorepo?

The alternative is separate repositories per package. Monorepo was chosen because:

- **Atomic changes**: a font parser change that affects the writer and the reader is one commit, one PR, one review
- **Shared test infrastructure**: the root `phpunit.xml` discovers all packages
- **Cross-package refactoring**: renaming a class in `pdf-core` automatically updates all dependents
- **Single CI pipeline**: one matrix covers all packages

Each package still has its own `composer.json`, its own PSR-4 namespace, and can be installed independently via Composer's path repository support.

### What about HTML-to-PDF?

phpdftk ships an HTML-to-PDF pipeline (`phpdftk/html-to-pdf`) alongside the native PDF API. The pipeline currently passes **95.72%** of the in-scope HTML Web Platform Tests, **95.40%** of the in-scope SVG corpus, and **100%** of the in-scope MathML corpus — measured per-commit against the upstream WPT fixtures, not a hand-curated sample.

This is a deliberate departure from the old advice ("use a headless browser"). Headless browsers still win on the absolute frontier of CSS fidelity, but they introduce a dependency on a separately-managed binary that requires its own install, sandbox, and process management. For server-side report generation, invoices, manuscripts, and tagged-PDF accessibility output, a pure-PHP renderer with a published pass rate is operationally simpler than a Chromium subprocess.

The trade-offs are still real:

- **Print-stylesheet parity, not interactive parity.** phpdftk targets CSS print stylesheets and static documents. JavaScript never runs; popovers, dialogs, and interactive elements render in their initial state.
- **No animations.** SMIL in SVG and CSS animations pin to `t = 0`. Page output is a single static frame.
- **Phase 4 substrate still in flight.** Blur halos (filter effects, `text-shadow` blur, drop-shadow blur) defer to a raster compositor that lands in Phase 4C. Until then, sharp-offset shadows render correctly; blurred shadows fall back to the sharp form.

Use [`phpdftk/html-to-pdf`](/rendering/html-to-pdf/) when you want server-side HTML rendering without managing a browser binary, and want PDF-native features (annotations, form fields, signatures, tagged-PDF accessibility, conformance profiles) on the same pipeline. Use a headless browser when the document depends on JavaScript execution or you need the absolute latest CSS feature on the day the spec lands.

### Why the dual API?

PDF features that have no HTML equivalent — custom annotations, blend modes, structure-tree tagging, optional content, digital signatures, encryption, 3D — are still locked behind private methods in every other PHP PDF library. phpdftk exposes them as typed classes in `phpdftk/pdf-core` and `phpdftk/pdf-writer`, and the HTML-to-PDF pipeline never hides that surface: drop down to `Pdf::writer()` from the rendering pipeline and you get the full PDF object model.

### Why built-in conformance validation?

No other PHP PDF library validates against ISO subset standards. If you need PDF/A for archival, PDF/UA for accessibility, or PDF/X for print production, you currently have two options: hope your output happens to be compliant, or run an external validator like veraPDF after the fact and fix issues manually.

phpdftk validates at generation time. Set a conformance profile and the library enforces all applicable constraints — font embedding, color spaces, metadata identification, transparency restrictions, encryption prohibitions — before emitting any bytes. Violations are reported with ISO clause references and object paths.

This matters because conformance failures are often invisible. A PDF that renders correctly in Acrobat may fail PDF/A validation because it uses device-dependent color without an OutputIntent, or because a font is referenced but not embedded. Catching these at generation time eliminates the feedback loop of generate-validate-fix-regenerate.

The library supports 8 standard families (31 conformance levels) including PDF/A, PDF/UA, PDF/X, PDF/VT, PDF/E, PDF/R, ZUGFeRD/Factur-X, and PDF/mail. The same constraints work against both writer output and reader-parsed existing PDFs.
