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

## Rendering HTML + CSS

When you already have HTML / CSS as the source of truth (templating engine output, Markdown via a `<style>` block, etc.), render it directly to PDF — no headless browser, no separate install:

```php
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
(new Renderer())->renderInto($writer, <<<'HTML'
<!DOCTYPE html>
<style>
  @page { size: A4; margin: 2cm }
  body { font: 11pt serif }
  h1 { color: #1a4480 }
  .highlight { background: #fff3cd; padding: 1px 4px }
</style>
<h1>Invoice #2026-001</h1>
<p>Thank you for your business. Total due: <span class="highlight">$1,250</span></p>
HTML);

$writer->save('invoice.pdf');
```

The pipeline handles inline `<svg>` and `<math>` too — see the [HTML to PDF docs](/rendering/html-to-pdf/) for the full feature surface and current WPT pass rates.

## Next steps

- [Choose Your API](/writing/api-levels/) — understand the three-level architecture
- [Rendering overview](/rendering/overview/) — HTML/CSS, SVG, and MathML pipelines
- [Performance](/standards/performance/overview/) — benchmark comparisons
- [Packages](/design/packages/) — the full package map
