# phpdftk/pdf

Metapackage that installs the full phpdftk family: object model (`phpdftk/pdf-core`) and builder (`phpdftk/pdf-writer`).

## Installation

```bash
composer require phpdftk/pdf
```

This pulls in everything you need to generate PDF files. For finer control, install individual packages:

```bash
composer require phpdftk/pdf-writer  # builder (pulls pdf-core transitively)
composer require phpdftk/pdf-reader  # parser
composer require phpdftk/pdf-toolkit # high-level pipelines (merge, stamp, forms, etc.)
```

## Quick Start

```php
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->addHeading('Hello, World', 1);
$pdf->addText('Body text with automatic word wrap and pagination.');
$pdf->save('/tmp/hello.pdf');
```

## Documentation

Full documentation at [apprlabs.github.io/phpdftk](https://apprlabs.github.io/phpdftk/).
