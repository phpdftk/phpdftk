---
title: Installation
description: How to install phpdftk in your PHP project.
---

## Requirements

- PHP 8.4 or later

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

## Support packages

These have zero PDF dependencies and can be used standalone:

```bash
composer require phpdftk/geometry      # Rectangle, Matrix, PageSize
composer require phpdftk/color         # RGB, CMYK, Gray
composer require phpdftk/filters       # FlateDecode, ASCII85, etc.
composer require phpdftk/encoding      # WinAnsi, MacRoman, CMap
composer require phpdftk/font-metrics  # AFM data for 14 standard fonts
composer require phpdftk/font-parser   # TrueType/OpenType parsing + subsetting
composer require phpdftk/image-metadata # JPEG/PNG/GIF/TIFF/WebP headers
composer require phpdftk/xmp           # XMP metadata read/write
composer require phpdftk/crypt         # AES/RC4 with PDF key derivation
```
