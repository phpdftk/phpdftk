---
title: Page Transformer
description: Rotate, scale, and set page boxes on PDF pages.
---

`PageTransformer` modifies page geometry -- rotation, scaling, and page box dimensions -- using incremental updates so the original content is preserved intact.

## Opening a PDF

```php
use ApprLabs\Pdf\Toolkit\PageTransformer;

// From file
$transformer = PageTransformer::open('input.pdf');

// From string
$transformer = PageTransformer::openString($pdfBytes);

// Encrypted PDF
$transformer = PageTransformer::open('secured.pdf', password: 'secret');
```

## Rotating pages

Rotation must be a multiple of 90 degrees. Rotations accumulate with any existing page rotation.

```php
// Rotate all pages
$transformer->rotate(90);

// Rotate specific pages
use ApprLabs\Pdf\Toolkit\PageSelector;

$transformer->rotate(180, PageSelector::pages(1, 3));
$transformer->rotate(270, PageSelector::even());
```

## Scaling pages

### Uniform scale factor

Multiplies all page box dimensions (MediaBox, CropBox, TrimBox, BleedBox, ArtBox) by the given factor:

```php
// Scale all pages to 50%
$transformer->scale(0.5);

// Scale specific pages to 150%
$transformer->scale(1.5, PageSelector::pages(1));
```

### Scale to fit dimensions

Computes a uniform scale factor to fit pages within the target width and height (in points):

```php
// Scale all pages to fit within A5
$transformer->scaleTo(420, 595);

// Scale specific pages
$transformer->scaleTo(300, 400, PageSelector::range(2, 5));
```

## Setting page boxes

Set any of the standard page boxes. Values are in points: x and y specify the lower-left corner, w and h specify width and height.

### CropBox

```php
$transformer->setCropBox(36, 36, 540, 720);

// On specific pages
$transformer->setCropBox(0, 0, 300, 400, PageSelector::pages(1));
```

### MediaBox

```php
$transformer->setMediaBox(0, 0, 612, 792); // Letter size
```

### TrimBox

```php
$transformer->setTrimBox(18, 18, 576, 756);
```

### BleedBox

```php
$transformer->setBleedBox(9, 9, 594, 774);
```

## Chaining operations

All operations are fluent and can be combined:

```php
PageTransformer::open('input.pdf')
    ->rotate(90)
    ->setCropBox(0, 0, 500, 700, PageSelector::pages(1))
    ->scale(0.8, PageSelector::range(2, 10))
    ->save('output.pdf');
```

## Saving

```php
// To file
$transformer->save('output.pdf');

// To string
$bytes = $transformer->toBytes();
```

## Document info

```php
$transformer->getPageCount(); // int
```

## Escape hatch

```php
$reader = $transformer->getReader(); // PdfReader
```
