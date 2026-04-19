---
title: PdfReader
description: Parse existing PDFs into typed objects — pages, fonts, annotations, metadata.
---

`PdfReader` parses existing PDF files into the phpdftk object model. It handles classic xref tables, cross-reference streams, object streams, incremental updates, and encrypted PDFs.

## Opening a PDF

```php
use ApprLabs\Pdf\Reader\PdfReader;

// From file
$pdf = PdfReader::fromFile('document.pdf');

// From string (e.g., HTTP response body)
$pdf = PdfReader::fromString($bytes);

// From stream resource
$pdf = PdfReader::fromStream(fopen('php://stdin', 'rb'));

// Encrypted PDF
$pdf = PdfReader::fromFile('secured.pdf', password: 'secret');

// Public-key encrypted PDF
$pdf = PdfReader::fromFilePublicKey('secured.pdf', $certPem, $keyPem);
```

## Document info

```php
echo $pdf->getVersion();    // "1.7"
echo $pdf->getPageCount();  // 42

// Linearization detection
if ($pdf->isLinearized()) {
    $params = $pdf->getLinearizationParameters();
    echo "Web-optimized, {$params['pageCount']} pages";
}
```

## Accessing pages

```php
// All pages as raw dictionaries
$pages = $pdf->getPages();

// Single page by 0-based index
$page = $pdf->getPage(0);

// Typed Page objects (hydrated into Core\Document\Page)
$typedPages = $pdf->getTypedPages();
$typedPage = $pdf->getTypedPage(0);
```

## Catalog and trailer

```php
$catalog = $pdf->getCatalog();      // raw PdfDictionary
$typed = $pdf->getTypedCatalog();   // hydrated Core\Document\Catalog
$trailer = $pdf->getTrailer();
$info = $pdf->getInfo();
```

## Resolving objects

```php
// By object number
$obj = $pdf->getObject(42);

// By reference
$target = $pdf->resolveReference($ref);

// Typed hydration of any object
$typed = $pdf->getTypedObject(42);
```

## Text extraction

```php
// Single page (0-based index)
$text = $pdf->extractText(0);

// All pages concatenated
$allText = $pdf->extractAllText("\n\n");
```

## Error tolerance

In lenient mode, the reader recovers from common PDF issues:

```php
$pdf = PdfReader::fromFile('damaged.pdf', strict: false);

// Check what was wrong
foreach ($pdf->getParseWarnings() as $warning) {
    echo "Warning: $warning\n";
}
```

Recoverable issues include displaced headers, malformed xref tables, and missing trailers (reconstructed via object scanning).

## Encryption support

The reader automatically handles all standard encryption methods:

| Method | Version |
|---|---|
| RC4 40-bit | V=1 R=2 |
| RC4 128-bit | V=2 R=3 |
| AES-128 | V=4 R=4 |
| AES-256 | V=5 R=6 |
| Public-key (Adobe.PubSec) | AES-128/256 |
