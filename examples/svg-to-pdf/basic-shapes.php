<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgRenderer;

// SVG demonstrating the 3K basic-shape painters: <rect>, <circle>,
// <ellipse>, <line>, <polyline>, <polygon> plus fill, stroke, opacity,
// and stroke params (3N) wired into a single illustration.
$svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">
  <rect x="10" y="10" width="50" height="50" fill="#3b82f6"/>
  <rect x="70" y="10" width="50" height="50" fill="none" stroke="#10b981"
        stroke-width="3" stroke-dasharray="4 2"/>
  <circle cx="155" cy="35" r="25" fill="#f97316" fill-opacity="0.6"/>
  <ellipse cx="155" cy="80" rx="20" ry="10" fill="#a855f7"/>
  <line x1="10" y1="80" x2="120" y2="80" stroke="#ef4444"
        stroke-width="2" stroke-linecap="round"/>
  <polyline points="10,90 30,80 50,90 70,80 90,90" fill="none"
            stroke="#0ea5e9" stroke-width="1.5"/>
  <polygon points="100,90 115,75 130,90" fill="#facc15" stroke="#854d0e"/>
</svg>
SVG;

$writer = new PdfWriter();
$page = $writer->addPage(612.0, 792.0);
$renderer = new SvgRenderer($page, $writer);

$svgDoc = (new SvgParser())->parse($svg);
$renderer->draw($svgDoc, x: 72.0, y: 500.0, width: 468.0, height: 234.0);
// endregion: example

$writer->save(__DIR__ . '/basic-shapes.pdf');
