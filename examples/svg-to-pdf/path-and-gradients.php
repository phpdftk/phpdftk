<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgRenderer;

// Demonstrates the 3L path painter (full SVG 2 path grammar + arc-to-
// cubic conversion) and the 3O gradient painters (linear + radial,
// userSpaceOnUse + objectBoundingBox), with `<defs>` referenced via
// `fill="url(#…)"` (3G + 3Q).
$svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 200">
  <defs>
    <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#0ea5e9"/>
      <stop offset="1" stop-color="#fef3c7"/>
    </linearGradient>
    <radialGradient id="sun" cx="0.5" cy="0.5" r="0.5">
      <stop offset="0" stop-color="#fbbf24"/>
      <stop offset="0.7" stop-color="#f59e0b"/>
      <stop offset="1" stop-color="#f59e0b" stop-opacity="0"/>
    </radialGradient>
  </defs>

  <!-- Sky backdrop, linear gradient in objectBoundingBox mode. -->
  <rect width="300" height="150" fill="url(#sky)"/>

  <!-- Sun, radial gradient. -->
  <circle cx="220" cy="40" r="25" fill="url(#sun)"/>

  <!-- Mountain silhouette via the path grammar (M / L / Z + curves). -->
  <path d="M 0 150 L 60 80 Q 90 60 120 90 L 180 50 L 240 100 L 300 80 L 300 150 Z"
        fill="#4338ca" stroke="#312e81" stroke-width="1"/>

  <!-- Stylised river using cubic Bézier path commands. -->
  <path d="M 0 175 C 80 165, 120 195, 200 175 S 280 165, 300 180 L 300 200 L 0 200 Z"
        fill="#1d4ed8"/>

  <!-- Arc-to-cubic exercise: a sun ray rendered as a thick arc. -->
  <path d="M 30 130 A 25 25 0 0 1 80 130" fill="none"
        stroke="#dc2626" stroke-width="2"/>
</svg>
SVG;

$writer = new PdfWriter();
$page = $writer->addPage(612.0, 792.0);
$renderer = new SvgRenderer($page, $writer);

$svgDoc = (new SvgParser())->parse($svg);
$renderer->draw($svgDoc, x: 72.0, y: 500.0, width: 468.0, height: 312.0);
// endregion: example

$writer->save(__DIR__ . '/path-and-gradients.pdf');
