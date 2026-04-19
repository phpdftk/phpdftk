---
title: Text Extractor
description: Extract and search text from PDFs with a clean, fluent API.
---

`TextExtractor` wraps `PdfReader`'s text extraction with a friendly, toolkit-level API. All page numbers are 1-based.

## Opening a PDF

```php
use ApprLabs\Pdf\Toolkit\TextExtractor;

// From file
$extractor = TextExtractor::open('report.pdf');

// From string
$extractor = TextExtractor::openString($pdfBytes);

// Encrypted PDF
$extractor = TextExtractor::open('secured.pdf', password: 'secret');
```

## Extracting text

```php
// Single page (1-based)
$text = $extractor->page(1);

// All pages with separator
$text = $extractor->allPages("\n---\n");

// Per-page array
$pages = $extractor->perPage();
// => [1 => "page 1 text", 2 => "page 2 text"]
```

## Searching

### Literal string

```php
$results = $extractor->search('indemnification');

echo $results->count() . " matches\n";

foreach ($results as $match) {
    echo "Page {$match->pageNumber}: {$match->text}\n";
}
```

### Regex pattern

```php
$results = $extractor->searchPattern('/\d{3}-\d{2}-\d{4}/'); // SSN pattern
```

### Quick contains check

```php
if ($extractor->contains('CONFIDENTIAL')) {
    // handle sensitive document
}
```

## Search results API

```php
$results = $extractor->search('term');

$results->count();   // int
$results->all();     // list<TextMatch>
$results->first();   // ?TextMatch

// TextMatch properties
$match->pageNumber;  // int (1-based)
$match->text;        // string
$match->offset;      // int (char offset in page text)
```

`TextSearchResults` implements `IteratorAggregate` and `Countable`, so it works with `foreach` and `count()`.

## Document info

```php
$extractor->getPageCount(); // int
```

## Escape hatch

```php
$reader = $extractor->getReader(); // PdfReader
```
