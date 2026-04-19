---
title: Escape Hatches
description: How phpdftk lets you drop between API levels without losing context.
---

## The problem with fixed abstractions

Most libraries choose one level of abstraction and commit to it. A high-level API that hides complexity is great until you need something it doesn't expose. Then you're stuck: work around the limitation, fork the library, or switch to a different one.

phpdftk's solution is **escape hatches** — each API level provides a method to access the level below. You can interleave levels in the same document without conflict.

## The escape chain

```
Level 2: Pdf
  │
  │  $pdf->writer()           → PdfWriter
  ▼
Level 1: PdfWriter + Page
  │
  │  $page->contentStream()   → ContentStream (Level 0)
  │  $page->corePage()        → Core\Document\Page (Level 0)
  │  $writer->fileWriter()    → PdfFileWriter (Level 0)
  ▼
Level 0: PdfFileWriter + raw objects
```

## Example: mixing levels

Start with Level 2 for a report, drop to Level 1 for a custom chart:

```php
$pdf = new Pdf();
$pdf->addHeading('Sales Report', 1);
$pdf->addText('Q4 revenue exceeded targets by 12%.');

// Drop to Level 1 for a custom visualization
$writer = $pdf->writer();
$page = $writer->addPage(612, 792);
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

$page->drawRectangle(72, 400, 200, 150,
    fill: new RgbColor(0.2, 0.5, 0.9));
$page->drawText('$4.2M', 120, 460, $font, 24,
    color: new RgbColor(1, 1, 1));

$pdf->save('report.pdf');
```

## Example: raw operators via Page

Level 1's `Page` handles most drawing. For operators it doesn't wrap, use `contentStream()`:

```php
$page = $writer->addPage(612, 792);

// Level 1 drawing
$page->drawRectangle(72, 72, 100, 100, fill: new RgbColor(1, 0, 0));

// Drop to Level 0 for a shading operator
$cs = $page->contentStream();
$cs->paintShading('Sh1');
```

Or use `raw()` for one-off operators:

```php
$page->raw(function (ContentStream $cs) {
    $cs->saveGraphicsState()
       ->setFillColorRGB(0, 0, 1)
       ->rectangle(200, 200, 50, 50)
       ->fill()
       ->restoreGraphicsState();
});
```

## Example: annotation access via corePage

Level 1's `Page` is a drawing surface. Annotations live on the core `Page`:

```php
$page = $writer->addPage(612, 792);

// Drawing (Level 1)
$page->drawText('Click here', 72, 720, $font, 12);

// Annotation (Level 0 via escape hatch)
$link = new LinkAnnotation(
    new PdfArray([new PdfNumber(72), new PdfNumber(710),
                  new PdfNumber(200), new PdfNumber(730)]),
);
$link->a = new URIAction(new PdfString('https://example.com'));
$writer->register($link);

$corePage = $page->corePage();
$corePage->annots[] = new PdfReference($link->objectNumber);
```

## Design constraints

Escape hatches work because of two rules:

1. **Escaping doesn't invalidate the higher level.** After calling `$page->contentStream()` and emitting raw operators, you can still call `$page->drawText()`. The Page wraps each draw call in its own `q`/`Q` pair, so graphics state isolation is maintained.

2. **The lower level is the real implementation.** `Page::drawText()` ultimately emits operators on the `ContentStream`. `PdfWriter::addPage()` ultimately calls `PdfFileWriter::register()`. There's no shadow state that diverges — the higher level is syntactic sugar over the lower level.

This means you never have to choose in advance. Start at the highest level that feels comfortable, and drop down only when you need to.
