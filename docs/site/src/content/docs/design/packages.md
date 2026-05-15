---
title: Packages
description: The 16 packages in the phpdftk monorepo and how they relate.
---

phpdftk is a monorepo with 16 packages. Each has its own Composer name, PSR-4 namespace, and can be installed independently.

## Dependency graph

```
filesystem (local-file I/O guard)
    |
    v (used by all packages that read or write disk)
geometry, color, filters, encoding, font-metrics,
font-parser, image-metadata, xmp, crypt
    |
    v (all depended on by)
  pdf-core
    |
    +-- pdf-writer (Level 1 + Level 2 APIs)
    +-- pdf-reader (parser + text extraction)
    +-- pdf-toolkit (reader-to-writer pipelines)
    +-- pdf-conformance (ISO standard validation; also depends on xmp)
```

`filesystem` is the only package allowed to call PHP's raw filesystem primitives (`fopen`, `file_get_contents`, etc.). Every other package that touches a local path goes through `Phpdftk\Filesystem\LocalFilesystem`, which rejects stream-wrapper paths (`php://`, `http://`, `phar://`, …) and normalises error reporting.

`pdf-writer` and `pdf-reader` never depend on each other. `pdf-toolkit` depends on `pdf-core` and `pdf-reader` but not `pdf-writer`. `pdf-conformance` depends on `pdf-core` and `xmp`.

## PDF packages

| Package | Composer | Namespace | Purpose |
|---|---|---|---|
| `pdf/all` | `phpdftk/pdf` | -- | Metapackage: installs core + writer + reader |
| `pdf/core` | `phpdftk/pdf-core` | `Phpdftk\Pdf\Core\` | Object model + file serialization |
| `pdf/writer` | `phpdftk/pdf-writer` | `Phpdftk\Pdf\Writer\` | Level 1 (`PdfWriter`) + Level 2 (`Pdf`) APIs |
| `pdf/reader` | `phpdftk/pdf-reader` | `Phpdftk\Pdf\Reader\` | PDF parser with text extraction |
| `pdf/toolkit` | `phpdftk/pdf-toolkit` | `Phpdftk\Pdf\Toolkit\` | High-level manipulation pipelines |
| `pdf/conformance` | `phpdftk/pdf-conformance` | `Phpdftk\Pdf\Conformance\` | ISO standard validation (PDF/A, PDF/X, PDF/UA, PDF/VT, PDF/E, PDF/R, ZUGFeRD, PDF/mail) |

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
| `image-metadata` | `phpdftk/image-metadata` | `Phpdftk\ImageMetadata\` | JPEG, PNG, GIF, TIFF, WebP header parsing |
| `xmp` | `phpdftk/xmp` | `Phpdftk\Xmp\` | XMP metadata packet read/write |
| `crypt` | `phpdftk/crypt` | `Phpdftk\Crypt\` | AES-128/256, RC4, PDF key derivation, PKCS#7 |
