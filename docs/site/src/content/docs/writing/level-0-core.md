---
title: "Level 0: PdfFileWriter"
description: Direct access to the PDF object model — every spec object is a typed PHP class.
---

Level 0 is the raw PDF object model and byte-level file emitter. Every type defined in ISO 32000-2:2020 has a corresponding PHP class with typed properties matching the spec fields.

## When to use Level 0

- Building a library or framework on top of phpdftk
- Emitting PDF features not yet exposed by higher levels
- Manipulating the object graph directly (e.g., custom annotation types)
- Learning the PDF specification interactively

## Architecture

```
PdfFileWriter (byte emitter)
  ├── ObjectRegistry (assigns object numbers)
  ├── CrossReferenceTable (20-byte xref entries)
  └── TrailerDictionary (/Size, /Root, /Info, /ID, /Encrypt)

Catalog ─── PageTree ─── Page ─── ContentStream
                                  Resources
                                  Annotations
```

## Example: minimal PDF

```php
use ApprLabs\Pdf\Core\File\PdfFileWriter;
use ApprLabs\Pdf\Core\Document\{Catalog, Page, PageTree};
use ApprLabs\Pdf\Core\Content\{ContentStream, Resources};
use ApprLabs\Pdf\Core\Font\{Type1Font, StandardFont};
use ApprLabs\Pdf\Core\{PdfArray, PdfNumber, PdfReference};

$fw = new PdfFileWriter();

$catalog = new Catalog();
$fw->setCatalog($catalog);

$pageTree = new PageTree();
$fw->register($pageTree);
$catalog->pages = new PdfReference($pageTree->objectNumber);

$page = new Page();
$fw->register($page);
$page->parent = new PdfReference($pageTree->objectNumber);
$page->mediaBox = new PdfArray([
    new PdfNumber(0), new PdfNumber(0),
    new PdfNumber(612), new PdfNumber(792),
]);
$page->resources = new Resources();

$pageTree->kids = [new PdfReference($page->objectNumber)];
$pageTree->count = 1;

$font = new Type1Font(StandardFont::Helvetica);
$fw->register($font);
$page->resources->addFont('F1', $font);

$cs = new ContentStream();
$fw->register($cs);
$cs->beginText()
   ->setFont('F1', 12)
   ->moveTextPosition(72, 720)
   ->showText('Hello from Level 0')
   ->endText();

$page->contents = [new PdfReference($cs->objectNumber)];

$fw->save('level0.pdf');
```

## Object model overview

Every `PdfObject` subclass has:
- A `PDF_TYPE` constant matching the spec's `/Type` value
- Public properties matching spec fields in camelCase
- A `toPdf(): string` method that serializes to PDF syntax
- An `objectNumber` assigned by the `ObjectRegistry` when registered

### Primitive types

| Class | PDF syntax |
|---|---|
| `PdfName` | `/Name` |
| `PdfString` | `(text)` or `<hex>` |
| `PdfNumber` | `42` or `3.14` |
| `PdfBoolean` | `true` / `false` |
| `PdfNull` | `null` |
| `PdfArray` | `[1 2 3]` |
| `PdfDictionary` | `<< /Key /Value >>` |
| `PdfReference` | `5 0 R` |
| `PdfStream` | Stream with dictionary + binary data |

### ContentStream operators

The `ContentStream` class has fluent methods for all 69 PDF content operators:

```php
$cs->saveGraphicsState()           // q
   ->setFillColorRGB(1, 0, 0)     // rg
   ->rectangle(72, 72, 100, 50)   // re
   ->fill()                        // f
   ->restoreGraphicsState();       // Q
```
