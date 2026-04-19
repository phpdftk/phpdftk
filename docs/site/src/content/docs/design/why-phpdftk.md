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

### Why not HTML-to-PDF?

HTML-to-PDF conversion (the mPDF/Dompdf approach) is appealing because developers already know HTML and CSS. But it introduces fundamental constraints:

- **CSS is not PDF**: the box model, float behavior, and text layout rules don't map cleanly to PDF's coordinate-based content streams. Every HTML-to-PDF library has CSS features it doesn't support, and the differences are surprising.
- **Performance**: parsing HTML, applying CSS cascade, computing layout, *then* emitting PDF objects is inherently slower than emitting PDF objects directly.
- **Feature ceiling**: PDF features that have no HTML equivalent (annotations, form fields, digital signatures, optional content, 3D, multimedia) are either hacked in via custom HTML attributes or simply unavailable.

phpdftk doesn't convert from anything. It speaks PDF natively. If you want HTML-to-PDF, use a headless browser — it will do a better job than any PHP library at CSS fidelity.
