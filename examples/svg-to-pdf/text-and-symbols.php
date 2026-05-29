<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgRenderer;

// Demonstrates the 3P text painter (font-family / weight / style →
// Helvetica / Times / Courier variants) and the 3Q symbol / use
// expansion (defining a reusable icon once and dropping it in twice).
$svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 250">
  <defs>
    <!-- A simple star icon defined once and reused via `<use>`. -->
    <symbol id="star" viewBox="0 0 20 20">
      <polygon points="10,1 12.5,7 19,7.5 14,12 15.5,19 10,15 4.5,19 6,12 1,7.5 7.5,7"
               fill="#facc15" stroke="#854d0e" stroke-width="0.5"/>
    </symbol>
  </defs>

  <!-- Title in the default sans-serif (Helvetica). -->
  <text x="20" y="30" font-size="20" fill="#0f172a">phpdftk/svg-to-pdf</text>

  <!-- Subtitle: bold serif (Times-Bold). -->
  <text x="20" y="55" font-size="14" font-family="serif" font-weight="bold"
        fill="#475569">Adapter samples</text>

  <!-- Italic body text (Helvetica-Oblique). -->
  <text x="20" y="80" font-size="11" font-style="italic" fill="#334155">
    Rendered with the 14 standard PDF fonts via font-family / weight / style.
  </text>

  <!-- Monospace label (Courier). -->
  <text x="20" y="105" font-size="10" font-family="monospace" fill="#1e293b">
    new SvgRenderer($page, $writer)
  </text>

  <!-- Two instances of the star symbol via `<use>` with translation. -->
  <use href="#star" x="20" y="160"/>
  <use href="#star" x="50" y="160"/>
  <use href="#star" x="80" y="160"/>

  <!-- Tspan inside a text element: 3P concatenates inline at present. -->
  <text x="20" y="220" font-size="12" fill="#0f172a">
    Hello, <tspan font-weight="bold">world</tspan>!
  </text>
</svg>
SVG;

$writer = new PdfWriter();
$page = $writer->addPage(612.0, 792.0);
$renderer = new SvgRenderer($page, $writer);

$svgDoc = (new SvgParser())->parse($svg);
$renderer->draw($svgDoc, x: 72.0, y: 400.0, width: 468.0, height: 292.0);
// endregion: example

$writer->save(__DIR__ . '/text-and-symbols.pdf');
