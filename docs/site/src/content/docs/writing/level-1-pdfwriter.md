---
title: "Level 1: PdfWriter"
description: Spatial drawing API with explicit coordinates, font handles, and full PDF feature access.
---

`PdfWriter` is the ergonomic builder for the PDF object model. It handles object registration, resource naming, and page tree wiring, while giving you precise control over what goes where.

## Creating pages

```php
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Pdf\Writer\PageSize;

$writer = new PdfWriter();

// Explicit dimensions (points)
$page = $writer->addPage(612, 792);

// Or use a named size
$page = $writer->addPage(PageSize::A4);
$page = $writer->addPage(PageSize::Legal);
```

## Fonts

```php
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Font\StandardFont;

// Standard 14 fonts (no embedding needed)
$helvetica = $writer->addFont(new Type1Font(StandardFont::Helvetica));
$courier = $writer->addFont(new Type1Font(StandardFont::Courier));

// TrueType font (auto-embedded and subsetted)
$custom = $writer->addFont(
    \ApprLabs\Pdf\Core\Font\TrueTypeFont::fromFile('/path/to/font.ttf')
);
```

The returned `Font` handle is opaque — pass it to drawing methods:

```php
$page->drawText('Hello', 72, 720, $helvetica, 12);
```

## Drawing on pages

The `Page` object provides fluent drawing methods. Each call is isolated in its own graphics state (q/Q):

### Text

```php
$page->drawText('Hello World', 72, 720, $font, 12);
$page->drawText('Red text', 72, 700, $font, 14, color: new RgbColor(1, 0, 0));
```

### Shapes

```php
use ApprLabs\Color\RgbColor;

// Rectangle with fill and stroke
$page->drawRectangle(72, 600, 200, 100,
    fill: new RgbColor(0.9, 0.9, 1.0),
    stroke: new RgbColor(0, 0, 0.5),
    strokeWidth: 2.0,
);

// Circle
$page->drawCircle(300, 400, 50, fill: new RgbColor(1, 0.8, 0));

// Line with dash pattern
use ApprLabs\Pdf\Writer\DashPattern;
$page->drawLine(72, 500, 540, 500,
    color: new RgbColor(0.5, 0.5, 0.5),
    dash: DashPattern::dashed(),
);
```

### Images

```php
$page->drawImage('photo.jpg', 72, 300, width: 200);
```

### Custom paths

```php
$page->drawPath(
    function ($p) {
        $p->moveTo(100, 100)
          ->lineTo(200, 200)
          ->curveTo(250, 250, 300, 200, 300, 150)
          ->close();
    },
    fill: new RgbColor(0.2, 0.6, 1.0),
);
```

## Bookmarks

```php
use ApprLabs\Pdf\Core\Document\Outline;
use ApprLabs\Pdf\Core\Document\OutlineItem;

$outline = new Outline();
$writer->setOutline($outline);

$item = new OutlineItem('Chapter 1');
$item->dest = $page; // link to page
$writer->addOutlineItem($item);
```

## Digital signatures

```php
$writer->setSigner($signatureValue, $pkcs7Signer);
```

## Encryption

```php
use ApprLabs\Pdf\Core\Security\PdfEncryptor;

$fileId = random_bytes(16);
$encryptor = PdfEncryptor::aes256('userpass', 'ownerpass', $fileId);
$writer->setEncryption($encryptor);
```

## Escape hatches

```php
// Drop to Level 0 — raw ContentStream operators
$cs = $page->contentStream();
$cs->beginText()
   ->setFont($font->getResourceName(), 12)
   ->moveTextPosition(72, 720)
   ->showText('Raw operator access')
   ->endText();

// Access the core Page object for annotations, resources, etc.
$corePage = $page->corePage();

// Access the PdfFileWriter for byte-level control
$fw = $writer->fileWriter();
```
