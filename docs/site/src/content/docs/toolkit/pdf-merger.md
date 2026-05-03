---
title: PDF Merger
description: Combine multiple PDFs into a single document with optional page selection.
---

`PdfMerger` concatenates pages from multiple source PDFs into one output document. Unlike the reader-based toolkit classes, it uses a factory method (`create()`) instead of `open()` since it starts with no source document.

## Creating a merger

```php
use Phpdftk\Pdf\Toolkit\PdfMerger;

$merger = PdfMerger::create();
```

## Adding sources

### Full documents

```php
$merger->addFile('chapter1.pdf')
       ->addFile('chapter2.pdf')
       ->addFile('chapter3.pdf');
```

### From byte strings

```php
$merger->addString($pdfBytes);
```

### Encrypted sources

```php
$merger->addFile('secured.pdf', password: 'secret');
```

### Specific pages from a document

```php
use Phpdftk\Pdf\Toolkit\PageSelector;

$merger->addPages('large.pdf', PageSelector::range(1, 10));
$merger->addPages('appendix.pdf', PageSelector::pages(3, 7, 12));
$merger->addPages('forms.pdf', PageSelector::odd());
```

## Querying

```php
$merger->getSourceCount();    // number of source PDFs added
$merger->getTotalPageCount(); // total pages across all sources
```

## Saving

```php
// To file
$merger->save('combined.pdf');

// To string
$bytes = $merger->toBytes();
```

## Complete example

```php
PdfMerger::create()
    ->addFile('cover.pdf')
    ->addPages('body.pdf', PageSelector::range(1, 50))
    ->addFile('appendix.pdf')
    ->save('book.pdf');
```
