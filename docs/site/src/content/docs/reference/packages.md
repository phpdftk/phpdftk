---
title: Packages
description: The 12 packages in the phpdftk monorepo and how they relate.
---

phpdftk is a monorepo with 12 packages. Each has its own Composer name, PSR-4 namespace, and can be installed independently.

## Dependency graph

```
geometry, color, filters, encoding, font-metrics,
font-parser, image-metadata, xmp, crypt
    |
    v (all depended on by)
  pdf-core
    |
    +-- pdf-writer (Level 1 + Level 2 APIs)
    +-- pdf-reader (parser + text extraction)
    +-- pdf-toolkit (reader-to-writer pipelines)
```

`pdf-writer` and `pdf-reader` never depend on each other. `pdf-toolkit` depends on `pdf-core` and `pdf-reader` but not `pdf-writer`.

## PDF packages

| Package | Composer | Namespace | Purpose |
|---|---|---|---|
| `pdf/all` | `apprlabs/pdf` | -- | Metapackage: installs core + writer + reader |
| `pdf/core` | `apprlabs/pdf-core` | `ApprLabs\Pdf\Core\` | Object model + file serialization |
| `pdf/writer` | `apprlabs/pdf-writer` | `ApprLabs\Pdf\Writer\` | Level 1 (`PdfWriter`) + Level 2 (`Pdf`) APIs |
| `pdf/reader` | `apprlabs/pdf-reader` | `ApprLabs\Pdf\Reader\` | PDF parser with text extraction |
| `pdf/toolkit` | `apprlabs/pdf-toolkit` | `ApprLabs\Pdf\Toolkit\` | High-level manipulation pipelines |

## Support packages

These have **zero PDF dependencies** and can be used standalone in any PHP project:

| Package | Composer | Namespace | Purpose |
|---|---|---|---|
| `geometry` | `apprlabs/geometry` | `ApprLabs\Geometry\` | Rectangle, Matrix, PageSize, BezierCurve |
| `color` | `apprlabs/color` | `ApprLabs\Color\` | RGB, CMYK, Gray with conversions |
| `filters` | `apprlabs/filters` | `ApprLabs\Filters\` | FlateDecode, ASCII85, ASCIIHex, RunLength, LZW |
| `encoding` | `apprlabs/encoding` | `ApprLabs\Encoding\` | WinAnsi, MacRoman, Adobe Glyph List, CMap parser |
| `font-metrics` | `apprlabs/font-metrics` | `ApprLabs\FontMetrics\` | AFM data for all 14 standard PDF fonts |
| `font-parser` | `apprlabs/font-parser` | `ApprLabs\FontParser\` | TrueType/OpenType parsing, subsetting, kerning, ligatures |
| `image-metadata` | `apprlabs/image-metadata` | `ApprLabs\ImageMetadata\` | JPEG, PNG, GIF, TIFF, WebP header parsing |
| `xmp` | `apprlabs/xmp` | `ApprLabs\Xmp\` | XMP metadata packet read/write |
| `crypt` | `apprlabs/crypt` | `ApprLabs\Crypt\` | AES-128/256, RC4, PDF key derivation, PKCS#7 |
