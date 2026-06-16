---
title: Installation
description: How to install phpdftk in your PHP project.
---

## Requirements

- PHP 8.4 or later
- `ext-zlib` (stream compression)
- `ext-openssl` (encryption + signing)
- `ext-simplexml` (XMP metadata)
- `ext-mbstring` and `ext-intl` (only when rendering HTML / SVG / MathML)

## Install everything

The `phpdftk/pdf` metapackage pulls in the core, writer, and reader:

```bash
composer require phpdftk/pdf
```

## Install individual packages

Pick only what you need:

```bash
# Writer only (includes core)
composer require phpdftk/pdf-writer

# Reader only (includes core)
composer require phpdftk/pdf-reader

# Toolkit (includes core + reader)
composer require phpdftk/pdf-toolkit

# Conformance validation (includes core + xmp)
composer require phpdftk/pdf-conformance
```

## Rendering packages

Render HTML, CSS, SVG, and MathML to PDF:

```bash
# Full HTML/CSS pipeline (pulls in css, html, text, svg, mathml, ...)
composer require phpdftk/html-to-pdf

# Or per-format if you only render one of the three
composer require phpdftk/svg-to-pdf
composer require phpdftk/mathml-to-pdf
```

Pull in just the parsers if you don't need the PDF-emitting side:

```bash
composer require phpdftk/html    # WHATWG HTML5 parser + DOM
composer require phpdftk/css     # CSS Syntax 3 / Selectors 4 / Cascade 5
composer require phpdftk/svg     # SVG 2 typed-tree parser
composer require phpdftk/mathml  # MathML Core typed-tree parser
composer require phpdftk/text    # UAX #14 line breaking, UAX #9 bidi, OpenType shaping
```

## Support packages

These have zero PDF dependencies and can be used standalone:

```bash
composer require phpdftk/geometry      # Rectangle, Matrix, PageSize
composer require phpdftk/color         # RGB, CMYK, Gray
composer require phpdftk/filters       # FlateDecode, ASCII85, etc.
composer require phpdftk/encoding      # WinAnsi, MacRoman, CMap
composer require phpdftk/filesystem    # Local-file I/O guard (rejects php://, http://, ...)
composer require phpdftk/font-metrics  # AFM data for 14 standard fonts
composer require phpdftk/font-parser   # TrueType/OpenType parsing + subsetting
composer require phpdftk/image-metadata # JPEG/PNG/GIF/TIFF/WebP headers
composer require phpdftk/xml           # XML parser shared by svg/mathml/xmp
composer require phpdftk/xmp           # XMP metadata read/write
composer require phpdftk/crypt         # AES/RC4 with PDF key derivation
```
