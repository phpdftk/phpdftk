---
title: Quick Start
description: Generate your first PDF in under a minute.
---

## Hello World (Level 2)

The simplest way to create a PDF. No PDF knowledge required:

```php
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->addHeading('Hello, World!', 1);
$pdf->addText('This is my first PDF generated with phpdftk.');
$pdf->save('hello.pdf');
```

This gives you Letter-sized pages, 72pt margins, Helvetica 11pt, automatic word wrap, and auto-pagination.

## Hello World (Level 1)

When you need precise control over coordinates and drawing:

```php
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Font\StandardFont;

$writer = new PdfWriter();
$page = $writer->addPage(612, 792);
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

$page->drawText('Hello, World!', 72, 720, $font, 24);

$writer->save('hello.pdf');
```

## Reading a PDF

```php
use Phpdftk\Pdf\Reader\PdfReader;

$pdf = PdfReader::fromFile('document.pdf');

echo "Pages: " . $pdf->getPageCount() . "\n";
echo "Version: " . $pdf->getVersion() . "\n";

// Extract text
$text = $pdf->extractText(0); // page index is 0-based
echo $text;
```

## Text search

```php
use Phpdftk\Pdf\Toolkit\TextExtractor;

$results = TextExtractor::open('contract.pdf')->search('indemnification');

echo $results->count() . " matches found\n";
foreach ($results as $match) {
    echo "Page {$match->pageNumber}: {$match->text}\n";
}
```

## Next steps

- [Choose Your API](/writing/api-levels/) — understand the three-level architecture
- [Performance](/standards/performance/overview/) — benchmark comparisons
- [Packages](/design/packages/) — the full package map
