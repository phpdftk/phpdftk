# phpdftk/html-to-pdf

Render HTML + CSS to PDF. Targets browser print-stylesheet parity: CSS 2.1 visual formatting, tables, paged media, fragmentation, multi-column, flex, grid, transforms, filters, custom properties, writing modes, full declarative shadow DOM. No JavaScript execution, no headless browser dependency.

```php
use Phpdftk\HtmlToPdf\Renderer;

$result = (new Renderer())->render($html, $css);
$result->writer->save('out.pdf');
foreach ($result->warnings as $w) {
    echo $w->code->value . ': ' . $w->message . PHP_EOL;
}
```

Or integrated with the existing writer flow:

```php
$pdf = new \Phpdftk\Pdf\Writer\Pdf();
$pdf->addHtml($htmlSnippet);
$pdf->save('out.pdf');
```

## Installation

```bash
composer require phpdftk/html-to-pdf
```

## Status

Phase 1E–1N of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Skeleton only; implementation pending.

## License

MIT
