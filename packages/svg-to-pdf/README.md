# phpdftk/svg-to-pdf

Render SVG to PDF. Geometric painter for vector content: paths (full SVG 2 grammar), basic shapes, gradients, clip paths, text with OpenType shaping, opacity, transforms.

```php
use Phpdftk\SvgToPdf\Renderer;

$writer = (new Renderer())->render($svg);
$writer->save('out.pdf');
```

Or placed inline via the existing writer flow:

```php
$pdf->addSvg($svgContent);                      // Level 3 flow
$page->drawSvg($svgContent, $x, $y, $w, $h);    // Level 2 positioned
$tpl = $pdfDoc->createSvgTemplate($svgContent); // reusable FormXObject
```

## Installation

```bash
composer require phpdftk/svg-to-pdf
```

## Status

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Skeleton only; implementation pending.

## License

MIT
