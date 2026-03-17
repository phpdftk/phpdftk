<?php
declare(strict_types=1);

$value = $argv[1] ?? '0';
$label = $argv[2] ?? 'coverage';
$numeric = (float) $value;

$color = match(true) {
    $numeric >= 90 => '#4c1',
    $numeric >= 80 => '#97ca00',
    $numeric >= 70 => '#dfb317',
    $numeric >= 60 => '#fe7d37',
    default        => '#e05d44',
};

$displayValue = number_format($numeric, 1) . '%';
$labelWidth   = (int)(strlen($label) * 6.5 + 10);
$valueWidth   = (int)(strlen($displayValue) * 7 + 10);
$totalWidth   = $labelWidth + $valueWidth;
$cx1          = (int)($labelWidth / 2 * 10);
$cx2          = (int)(($labelWidth + $valueWidth / 2) * 10);
$tw1          = (int)(strlen($label) * 60);
$tw2          = (int)(strlen($displayValue) * 65);

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$totalWidth}" height="20" role="img" aria-label="{$label}: {$displayValue}">
  <title>{$label}: {$displayValue}</title>
  <linearGradient id="s" x2="0" y2="100%">
    <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
    <stop offset="1" stop-opacity=".1"/>
  </linearGradient>
  <clipPath id="r">
    <rect width="{$totalWidth}" height="20" rx="3" fill="#fff"/>
  </clipPath>
  <g clip-path="url(#r)">
    <rect width="{$labelWidth}" height="20" fill="#555"/>
    <rect x="{$labelWidth}" width="{$valueWidth}" height="20" fill="{$color}"/>
    <rect width="{$totalWidth}" height="20" fill="url(#s)"/>
  </g>
  <g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="110">
    <text x="{$cx1}" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="{$tw1}" lengthAdjust="spacing">{$label}</text>
    <text x="{$cx1}" y="140" transform="scale(.1)" textLength="{$tw1}" lengthAdjust="spacing">{$label}</text>
    <text x="{$cx2}" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="{$tw2}" lengthAdjust="spacing">{$displayValue}</text>
    <text x="{$cx2}" y="140" transform="scale(.1)" textLength="{$tw2}" lengthAdjust="spacing">{$displayValue}</text>
  </g>
</svg>

SVG;
