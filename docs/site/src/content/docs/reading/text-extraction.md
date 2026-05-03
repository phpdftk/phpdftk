---
title: Text Extraction
description: Extract text from PDFs with encoding-aware Unicode conversion.
---

phpdftk extracts text from PDF content streams by interpreting text operators and resolving font encodings to Unicode.

## How it works

The text extractor processes content stream operators (`BT`, `ET`, `Tf`, `Td`, `Tj`, `TJ`, etc.) and converts character codes to Unicode using:

1. **ToUnicode CMap** (if present on the font) — most reliable
2. **Encoding + Differences** (custom encoding vectors)
3. **WinAnsi + Adobe Glyph List** fallback for standard fonts

Text positioning operators are used to infer spaces and line breaks.

### Form XObjects

The extractor handles the `Do` operator to recurse into Form XObjects — text inside stamped content, form field appearances, and embedded XObjects is extracted automatically. Nested Form XObjects are supported up to 10 levels deep, with font state properly saved/restored across XObject boundaries.

## Via PdfReader

```php
use Phpdftk\Pdf\Reader\PdfReader;

$pdf = PdfReader::fromFile('document.pdf');

// Single page (0-based index)
$text = $pdf->extractText(0);

// All pages
$allText = $pdf->extractAllText("\n\n");
```

## Via TextExtractor (Toolkit)

The toolkit's `TextExtractor` adds search and per-page access with 1-based page numbers:

```php
use Phpdftk\Pdf\Toolkit\TextExtractor;

$extractor = TextExtractor::open('document.pdf');

// Single page (1-based)
$text = $extractor->page(1);

// All pages
$allText = $extractor->allPages();

// Per-page map
$pages = $extractor->perPage();
// => [1 => "page 1 text", 2 => "page 2 text", ...]
```

## Searching

```php
// Literal string search
$results = $extractor->search('contract');
echo $results->count() . " matches\n";

foreach ($results as $match) {
    echo "Page {$match->pageNumber}, offset {$match->offset}: {$match->text}\n";
}

// First match only
$first = $extractor->search('signature')->first();

// Regex search
$results = $extractor->searchPattern('/\$[\d,]+\.\d{2}/');

// Quick contains check
if ($extractor->contains('confidential')) {
    echo "Document contains sensitive content\n";
}
```

## Search results

`TextSearchResults` implements `IteratorAggregate` and `Countable`:

```php
$results = $extractor->search('term');

$results->count();   // number of matches
$results->all();     // list<TextMatch>
$results->first();   // ?TextMatch (null if no matches)

foreach ($results as $match) {
    $match->pageNumber;  // 1-based
    $match->text;        // matched text
    $match->offset;      // character offset within page text
}
```
