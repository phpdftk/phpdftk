---
title: API Levels
description: phpdftk's three-level writer architecture — pick the right abstraction for every task.
---

phpdftk organizes PDF generation into three levels. Each level removes a category of concerns. You can drop down a level at any time via **escape hatches** — methods that return the underlying object from the level below.

```
Level 2: Pdf (cursor-based, auto-layout, themes)
  | escape hatch: $pdf->writer()
  v
Level 1: PdfWriter + Page (spatial drawing, explicit coordinates)
  | escape hatch: $page->contentStream() / $writer->fileWriter()
  v
Level 0: PdfFileWriter + PdfObject tree (raw PDF spec objects)
```

## What each level removes

| Transition | Knowledge eliminated |
|---|---|
| Level 0 to Level 1 | Object numbers, xref, trailer, resource naming, PdfReference wiring, content stream operators |
| Level 1 to Level 2 | Coordinates, page breaks, font management, text measurement |

## Level 2: `Pdf`

For documents that are mostly text and images. You think in terms of *content*, not coordinates:

```php
$pdf = new Pdf();
$pdf->addHeading('Chapter 1', 1);
$pdf->addText('Body text with automatic word wrap and pagination.');
$pdf->addImage('chart.png', width: 400);
$pdf->addRule();
$pdf->save('document.pdf');
```

**What you get:** auto page creation, word wrap, font metrics-based text measurement, heading hierarchy (h1-h6), themes, alignment, bold/italic font resolution.

**What you give up:** precise positioning, custom graphics, non-standard fonts.

**Escape hatch:** `$pdf->writer()` returns the underlying `PdfWriter`.

## Level 1: `PdfWriter` + `Page`

For documents with precise layout — invoices, reports with charts, form overlays:

```php
$writer = new PdfWriter();
$page = $writer->addPage(612, 792);
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

$page->drawText('Invoice #1234', 72, 720, $font, 18);
$page->drawLine(72, 710, 540, 710);
$page->drawRectangle(72, 600, 468, 80, fill: new RgbColor(0.95, 0.95, 1.0));
$page->drawText('Amount: $1,250.00', 82, 650, $font, 14);

$writer->save('invoice.pdf');
```

**What you get:** named drawing methods (`drawText`, `drawRectangle`, `drawCircle`, `drawImage`, etc.), automatic graphics state isolation (q/Q), font handles, image embedding.

**What you give up:** nothing — you have full PDF capability at this level.

**Escape hatches:**
- `$page->contentStream()` — raw `ContentStream` for PDF operators
- `$page->corePage()` — the underlying `Core\Document\Page` for resource/annotation access
- `$writer->fileWriter()` — the `PdfFileWriter` for byte-level control

## Level 0: `PdfFileWriter` + object model

For libraries building on top of phpdftk, or when you need something the higher levels don't expose:

```php
$fw = new PdfFileWriter();
$catalog = new Catalog();
$fw->setCatalog($catalog);

$pageTree = new PageTree();
$fw->register($pageTree);
$catalog->pages = new PdfReference($pageTree->objectNumber);

$page = new Page();
$fw->register($page);
// ... manual wiring of every object
```

**What you get:** total control over every byte in the output. Every PDF spec object is a PHP class with typed properties.

**What you give up:** convenience. You're responsible for object numbers, resource dictionaries, page tree wiring, and correct operator sequences.
