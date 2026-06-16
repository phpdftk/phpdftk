---
title: Packages
description: The 30 packages in the phpdftk monorepo and how they relate.
---

phpdftk is a monorepo with 30 packages. Each has its own Composer name, PSR-4 namespace, and can be installed independently.

## Dependency graph

```
filesystem (local-file I/O guard)
    |
    v (used by all packages that read or write disk)
geometry, color, filters, encoding, font-metrics,
font-parser, image-metadata, xml, xmp, crypt
    |
    v (depended on by)
  pdf-core
    |
    +-- pdf-writer  (Level 1 + Level 2 APIs)
    +-- pdf-reader  (parser + text extraction)
    +-- pdf-toolkit (reader-to-writer pipelines)
    +-- pdf-conformance (ISO standard validation; also depends on xmp)

text, css (depended on by)
    |
    v
  html, svg, mathml  (typed-tree parsers)
    |
    v
  html-to-pdf  (uses css + text + html + svg + mathml + resource-loader
                + paged-media + raster + barcode)
  svg-to-pdf   (uses css + text + svg + resource-loader)
  mathml-to-pdf (uses css + text + mathml + font-parser; consumed by
                  html-to-pdf via the inline-MathML adapter)
```

`filesystem` is the only package allowed to call PHP's raw filesystem primitives (`fopen`, `file_get_contents`, etc.). Every other package that touches a local path goes through `Phpdftk\Filesystem\LocalFilesystem`, which rejects stream-wrapper paths (`php://`, `http://`, `phar://`, …) and normalises error reporting.

`pdf-writer` and `pdf-reader` never depend on each other. `pdf-toolkit` depends on `pdf-core` and `pdf-reader` but not `pdf-writer`. `pdf-conformance` depends on `pdf-core` and `xmp`.

The rendering packages (`html-to-pdf` / `svg-to-pdf` / `mathml-to-pdf`) emit PDF via `pdf-writer` but never call into the parsers directly — every cross-package handoff is typed (`Phpdftk\Html\Dom\Document`, `Phpdftk\Svg\SvgDocument`, `Phpdftk\Mathml\MathmlDocument`).

## PDF packages

| Package | Composer | Namespace | Purpose |
|---|---|---|---|
| `pdf/all` | `phpdftk/pdf` | -- | Metapackage: installs core + writer + reader |
| `pdf/core` | `phpdftk/pdf-core` | `Phpdftk\Pdf\Core\` | Object model + file serialization |
| `pdf/writer` | `phpdftk/pdf-writer` | `Phpdftk\Pdf\Writer\` | Level 1 (`PdfWriter`) + Level 2 (`Pdf`) APIs |
| `pdf/reader` | `phpdftk/pdf-reader` | `Phpdftk\Pdf\Reader\` | PDF parser with text extraction |
| `pdf/toolkit` | `phpdftk/pdf-toolkit` | `Phpdftk\Pdf\Toolkit\` | High-level manipulation pipelines |
| `pdf/conformance` | `phpdftk/pdf-conformance` | `Phpdftk\Pdf\Conformance\` | ISO standard validation (PDF/A, PDF/X, PDF/UA, PDF/VT, PDF/E, PDF/R, ZUGFeRD, PDF/mail) |

## Rendering packages

These ship the HTML/CSS, SVG, and MathML pipelines that produce vector PDF output. Each has dedicated documentation under [Rendering](/rendering/overview/).

| Package | Composer | Namespace | Purpose |
|---|---|---|---|
| `html` | `phpdftk/html` | `Phpdftk\Html\` | WHATWG HTML5 parser + DOM + declarative Shadow DOM ([detail](/rendering/html/)) |
| `css` | `phpdftk/css` | `Phpdftk\Css\` | CSS Syntax 3 + Values 4 + Selectors 4 + Cascade 5 ([detail](/rendering/css/)) |
| `svg` | `phpdftk/svg` | `Phpdftk\Svg\` | SVG 2 parser producing a typed tree ([detail](/rendering/svg/)) |
| `mathml` | `phpdftk/mathml` | `Phpdftk\Mathml\` | MathML Core parser producing a typed tree ([detail](/rendering/mathml/)) |
| `text` | `phpdftk/text` | `Phpdftk\Text\` | UAX #14 line breaking, UAX #9 bidi, OpenType GSUB/GPOS shaping ([detail](/rendering/text/)) |
| `html-to-pdf` | `phpdftk/html-to-pdf` | `Phpdftk\HtmlToPdf\` | HTML + CSS → PDF translator ([detail](/rendering/html-to-pdf/)) |
| `svg-to-pdf` | `phpdftk/svg-to-pdf` | `Phpdftk\SvgToPdf\` | SVG → PDF translator ([detail](/rendering/svg-to-pdf/)) |
| `mathml-to-pdf` | `phpdftk/mathml-to-pdf` | `Phpdftk\MathmlToPdf\` | MathML → PDF translator (consumed by `html-to-pdf` for inline math) |
| `paged-media` | `phpdftk/paged-media` | `Phpdftk\PagedMedia\` | CSS Paged Media 3 + Fragmentation 4 substrate (`@page`, named pages, margin boxes, break-* properties) |
| `raster` | `phpdftk/raster` | `Phpdftk\Raster\` | Raster compositor for blur, filter primitives, and shadow halos (Phase 4C) |
| `resource-loader` | `phpdftk/resource-loader` | `Phpdftk\ResourceLoader\` | HTTP + file fetcher with SSRF gates, MIME sniffing, and content caching |
| `barcode` | `phpdftk/barcode` | `Phpdftk\Barcode\` | Barcode / QR code rendering for `<img>` and CSS background-image data-URIs |
| `wpt-harness` | `phpdftk/wpt-harness` | `Phpdftk\WptHarness\` | Web Platform Tests runner, manifest, and cross-browser oracle |

## Support packages

These have **zero PDF dependencies** and can be used standalone in any PHP project:

| Package | Composer | Namespace | Purpose |
|---|---|---|---|
| `geometry` | `phpdftk/geometry` | `Phpdftk\Geometry\` | Rectangle, Matrix, PageSize, BezierCurve |
| `color` | `phpdftk/color` | `Phpdftk\Color\` | RGB, CMYK, Gray with conversions |
| `filters` | `phpdftk/filters` | `Phpdftk\Filters\` | FlateDecode, ASCII85, ASCIIHex, RunLength, LZW, CCITTFax, JBIG2, Predictor |
| `encoding` | `phpdftk/encoding` | `Phpdftk\Encoding\` | WinAnsi, MacRoman, Adobe Glyph List, CMap parser |
| `filesystem` | `phpdftk/filesystem` | `Phpdftk\Filesystem\` | Local-file I/O wrapper that rejects stream wrappers (`php://`, `http://`, …) — every package that touches the disk routes through this |
| `font-metrics` | `phpdftk/font-metrics` | `Phpdftk\FontMetrics\` | AFM data for all 14 standard PDF fonts |
| `font-parser` | `phpdftk/font-parser` | `Phpdftk\FontParser\` | TrueType/OpenType parsing, subsetting, kerning, ligatures |
| `image-metadata` | `phpdftk/image-metadata` | `Phpdftk\ImageMetadata\` | JPEG, PNG (RGB / RGBA / grayscale / 1-8 bit indexed), GIF, TIFF, WebP, SVG header parsing |
| `xml` | `phpdftk/xml` | `Phpdftk\Xml\` | XML parser used by `svg`, `mathml`, and `xmp` |
| `xmp` | `phpdftk/xmp` | `Phpdftk\Xmp\` | XMP metadata packet read/write |
| `crypt` | `phpdftk/crypt` | `Phpdftk\Crypt\` | AES-128/256, RC4, PDF key derivation, PKCS#7 |
