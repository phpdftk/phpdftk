# phpdftk/pdf-writer

PDF writer with two API levels: a high-level cursor-based builder (`Pdf`) that requires no PDF knowledge, and an ergonomic object-model facade (`PdfWriter`) for full control.

## Installation

```bash
composer require phpdftk/pdf-writer
```

## High-Level API

```php
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->addHeading('Hello, World', 1);
$pdf->addText('Body text with automatic word wrap and pagination.');
$pdf->addSpacer(12);
$pdf->addImage('/path/to/photo.jpg', width: 300);
$pdf->save('/tmp/hello.pdf');
```

Features: auto-pagination, word wrap, 14 standard fonts, themes, alignment, headings, rules, spacers, images.

## Object-Model API

```php
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Font\StandardFont;

$writer = new PdfWriter();
$page = $writer->addPage(612, 792);
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

$content = $writer->addContentStream($page);
$content->beginText()
    ->setFont($font, 12)
    ->moveTextPosition(72, 720)
    ->showText('Hello')
    ->endText();

$writer->save('/tmp/output.pdf');
```

Features: fonts (standard, TrueType, OpenType), images, outlines/bookmarks, page labels, digital signatures, encryption, named destinations.

## Documentation

Full documentation at [apprlabs.github.io/phpdftk](https://apprlabs.github.io/phpdftk/).
