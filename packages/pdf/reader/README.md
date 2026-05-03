# phpdftk/pdf-reader

Parse existing PDF files into the phpdftk object model. Extract text, read metadata, inspect structure, and access typed objects.

## Installation

```bash
composer require phpdftk/pdf-reader
```

## Usage

```php
use Phpdftk\Pdf\Reader\PdfReader;

$pdf = PdfReader::fromFile('document.pdf');

// Basic info
echo $pdf->getVersion();   // "1.7"
echo $pdf->getPageCount(); // 5

// Text extraction
$text = $pdf->extractText(0);          // first page
$allText = $pdf->extractAllText("\n");  // all pages

// Typed object access
$catalog = $pdf->getTypedCatalog();
$page = $pdf->getTypedPage(0);
$pages = $pdf->getTypedPages();

// Encrypted PDFs
$pdf = PdfReader::fromFile('encrypted.pdf', password: 'secret');
```

## Features

- Parse PDF 1.0 through 2.0 files
- Classic xref tables and cross-reference streams
- Text extraction (including Form XObject content)
- Typed hydration to phpdftk object model classes
- Password-protected and public-key encrypted PDFs
- Lenient mode for malformed PDFs

## Documentation

Full documentation at [apprlabs.github.io/phpdftk](https://apprlabs.github.io/phpdftk/).
