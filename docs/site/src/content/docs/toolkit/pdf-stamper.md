---
title: PDF Stamper
description: Add text stamps, watermarks, page numbers, headers, and footers to PDFs.
---

`PdfStamper` overlays text on existing PDF pages using incremental updates. Supports positioned text stamps, diagonal watermarks, page numbers, headers, and footers.

## Opening a PDF

```php
use Phpdftk\Pdf\Toolkit\PdfStamper;

// From file
$stamper = PdfStamper::open('report.pdf');

// From string
$stamper = PdfStamper::openString($pdfBytes);

// Encrypted PDF
$stamper = PdfStamper::open('secured.pdf', password: 'secret');
```

## Watermarks

Add a diagonal watermark across all pages:

```php
$stamper->watermark('DRAFT');
```

Customize the watermark appearance:

```php
use Phpdftk\Pdf\Toolkit\Stamper\WatermarkStyle;

$stamper->watermark('CONFIDENTIAL', style: new WatermarkStyle(
    fontSize: 72.0,
    r: 1.0, g: 0.0, b: 0.0,  // red
    opacity: 0.2,
    rotation: 55.0,
));
```

Apply to specific pages only:

```php
use Phpdftk\Pdf\Toolkit\PageSelector;

$stamper->watermark('DRAFT', pages: PageSelector::range(1, 3));
```

### WatermarkStyle defaults

| Property | Default | Description |
|---|---|---|
| `fontSize` | `60.0` | Font size in points |
| `r`, `g`, `b` | `0.8, 0.8, 0.8` | Light gray color |
| `opacity` | `0.3` | Semi-transparent |
| `rotation` | `45.0` | Degrees counter-clockwise |

## Text stamps

Place text at a predefined position on the page:

```php
use Phpdftk\Pdf\Toolkit\Stamper\StampPosition;

$stamper->stampText('APPROVED', StampPosition::TopRight);
```

### Available positions

| Position | Location |
|---|---|
| `StampPosition::TopLeft` | Top-left corner |
| `StampPosition::TopCenter` | Top center |
| `StampPosition::TopRight` | Top-right corner |
| `StampPosition::Center` | Page center |
| `StampPosition::BottomLeft` | Bottom-left corner |
| `StampPosition::BottomCenter` | Bottom center |
| `StampPosition::BottomRight` | Bottom-right corner |

### Stamp style

```php
use Phpdftk\Pdf\Toolkit\Stamper\StampStyle;

$stamper->stampText('APPROVED', StampPosition::TopRight, style: new StampStyle(
    fontSize: 14.0,
    r: 0.0, g: 0.5, b: 0.0,  // green
    opacity: 0.8,
));
```

### StampStyle defaults

| Property | Default | Description |
|---|---|---|
| `fontSize` | `12.0` | Font size in points |
| `r`, `g`, `b` | `0.0, 0.0, 0.0` | Black |
| `opacity` | `1.0` | Fully opaque |

## Page numbers

```php
$stamper->addPageNumbers();
// Default: "Page 1 of 10" at bottom center
```

Customize format and position:

```php
$stamper->addPageNumbers(
    position: StampPosition::BottomRight,
    format: '{n} / {total}',
);
```

The `{n}` and `{total}` placeholders are replaced with the current page number and total page count.

## Headers and footers

Convenience methods that stamp text at `TopCenter` or `BottomCenter`:

```php
$stamper->header('Acme Corp - Quarterly Report');
$stamper->footer('Confidential - Do Not Distribute');
```

Both accept optional `style` and `pages` parameters:

```php
$stamper->header(
    'Internal Only',
    style: new StampStyle(fontSize: 8.0, r: 0.5, g: 0.5, b: 0.5),
    pages: PageSelector::odd(),
);
```

## Chaining and saving

All operations are fluent and can be chained:

```php
PdfStamper::open('report.pdf')
    ->watermark('DRAFT')
    ->addPageNumbers(StampPosition::BottomCenter)
    ->header('Acme Corp')
    ->save('stamped.pdf');
```

```php
$bytes = $stamper->toBytes();
```

## Document info

```php
$stamper->getPageCount(); // int
```

## Escape hatch

```php
$reader = $stamper->getReader(); // PdfReader
```
