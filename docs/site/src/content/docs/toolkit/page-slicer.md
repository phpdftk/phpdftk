---
title: Page Slicer
description: Extract, remove, reorder, reverse, and split pages from PDFs.
---

`PageSlicer` restructures a PDF's page tree. It rebuilds the document from scratch using `PdfFileWriter`, so the output is a clean PDF containing only the selected pages.

## Opening a PDF

```php
use Phpdftk\Pdf\Toolkit\PageSlicer;

// From file
$slicer = PageSlicer::open('large.pdf');

// From string
$slicer = PageSlicer::openString($pdfBytes);

// Encrypted PDF
$slicer = PageSlicer::open('secured.pdf', password: 'secret');
```

## Keeping pages

### By page numbers

```php
$slicer->keepPages(1, 3, 5)->save('selected.pdf');
```

### By range

```php
$slicer->keepRange(1, 5)->save('first-five.pdf');
```

### With PageSelector

```php
use Phpdftk\Pdf\Toolkit\PageSelector;

$slicer->keep(PageSelector::even())->save('even-pages.pdf');
$slicer->keep(PageSelector::odd())->save('odd-pages.pdf');
$slicer->keep(PageSelector::range(3, 7))->save('subset.pdf');
```

## Removing pages

### By page numbers

```php
$slicer->removePages(1, 2)->save('without-cover.pdf');
```

### With PageSelector

```php
$slicer->remove(PageSelector::pages(1, 2))->save('trimmed.pdf');
```

## Reordering pages

Pass 1-based page numbers in the desired order:

```php
$slicer->reorder(3, 1, 2)->save('reordered.pdf');
```

## Reversing page order

```php
$slicer->reverse()->save('reversed.pdf');
```

## Splitting a PDF

Split at a page boundary, returning two PDF byte strings:

```php
[$firstHalf, $secondHalf] = $slicer->split(6);
// Pages 1-5 in $firstHalf, pages 6+ in $secondHalf

file_put_contents('part1.pdf', $firstHalf);
file_put_contents('part2.pdf', $secondHalf);
```

## Saving

```php
// To file
$slicer->save('output.pdf');

// To string
$bytes = $slicer->toBytes();
```

## Document info

```php
$slicer->getPageCount(); // int (original page count)
```

## Escape hatch

```php
$reader = $slicer->getReader(); // PdfReader
```
