---
title: Installation
description: How to install phpdftk in your PHP project.
---

## Requirements

- PHP 8.4 or later

## Install everything

The `apprlabs/pdf` metapackage pulls in the core, writer, and reader:

```bash
composer require apprlabs/pdf
```

## Install individual packages

Pick only what you need:

```bash
# Writer only (includes core)
composer require apprlabs/pdf-writer

# Reader only (includes core)
composer require apprlabs/pdf-reader

# Toolkit (includes core + reader)
composer require apprlabs/pdf-toolkit
```

## Support packages

These have zero PDF dependencies and can be used standalone:

```bash
composer require apprlabs/geometry      # Rectangle, Matrix, PageSize
composer require apprlabs/color         # RGB, CMYK, Gray
composer require apprlabs/filters       # FlateDecode, ASCII85, etc.
composer require apprlabs/encoding      # WinAnsi, MacRoman, CMap
composer require apprlabs/font-metrics  # AFM data for 14 standard fonts
composer require apprlabs/font-parser   # TrueType/OpenType parsing + subsetting
composer require apprlabs/image-metadata # JPEG/PNG/GIF/TIFF/WebP headers
composer require apprlabs/xmp           # XMP metadata read/write
composer require apprlabs/crypt         # AES/RC4 with PDF key derivation
```
