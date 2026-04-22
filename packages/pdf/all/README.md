# apprlabs/pdf

Metapackage that installs the full phpdftk family: object model (`apprlabs/pdf-core`) and builder (`apprlabs/pdf-writer`).

## Installation

```bash
composer require apprlabs/pdf
```

This pulls in everything you need to generate PDF files. For finer control, install individual packages:

```bash
composer require apprlabs/pdf-writer  # builder (pulls pdf-core transitively)
composer require apprlabs/pdf-reader  # parser
composer require apprlabs/pdf-toolkit # high-level pipelines (merge, stamp, forms, etc.)
```

## Quick Start

```php
use ApprLabs\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->addHeading('Hello, World', 1);
$pdf->addText('Body text with automatic word wrap and pagination.');
$pdf->save('/tmp/hello.pdf');
```

## Documentation

Full documentation at [apprlabs.github.io/phpdftk](https://apprlabs.github.io/phpdftk/).
