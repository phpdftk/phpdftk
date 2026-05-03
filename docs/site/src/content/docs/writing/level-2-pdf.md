---
title: "Level 2: Pdf"
description: The highest-level API — auto-layout, word wrap, and pagination with zero PDF knowledge.
---

The `Pdf` class is a cursor-based document builder. You add content in order, and it handles page breaks, word wrap, margins, and font management automatically.

## Basic usage

```php
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->addHeading('Annual Report', 1);
$pdf->addText('Fiscal year 2026 was a year of growth...');
$pdf->addSpacer(12);
$pdf->addHeading('Revenue', 2);
$pdf->addText('Total revenue reached $4.2M, up 23% YoY.');
$pdf->save('report.pdf');
```

## Defaults

| Setting | Default |
|---|---|
| Page size | Letter (612 x 792 pt) |
| Margins | 72 pt (1 inch) all sides |
| Font | Helvetica 11pt |
| Line height | 1.4x font size |

## Headings

Six heading levels with decreasing font sizes:

```php
$pdf->addHeading('Title', 1);      // 24pt bold
$pdf->addHeading('Section', 2);    // 20pt bold
$pdf->addHeading('Subsection', 3); // 16pt bold
// ... through level 6
```

## Text alignment

```php
use Phpdftk\Pdf\Writer\Alignment;

$pdf->addText('Left aligned (default)');
$pdf->addText('Centered text', Alignment::Center);
$pdf->addText('Right aligned', Alignment::Right);
```

## Images

```php
$pdf->addImage('photo.jpg', width: 300);
$pdf->addImage('logo.png', width: 150, alignment: Alignment::Center);
```

## Horizontal rules

```php
$pdf->addRule();            // default thin gray line
$pdf->addSpacer(24);       // vertical whitespace
```

## Themes

Customize fonts, colors, and margins:

```php
use Phpdftk\Pdf\Writer\Theme;

$theme = Theme::default()
    ->withFont('Courier')
    ->withMargin(36);    // half-inch margins

$pdf = new Pdf(theme: $theme);
```

## Escape hatch

Drop to Level 1 when you need precise control:

```php
$pdf = new Pdf();
$pdf->addText('Some auto-laid-out text...');

// Drop to Level 1 for a custom drawing
$writer = $pdf->writer();
// ... use $writer->addPage(), $writer->addFont(), etc.
```

## Output modes

```php
$pdf->save('/path/to/file.pdf');     // Write to file
$bytes = $pdf->toBytes();             // Get as string
$pdf->writeTo($stream);              // Write to stream resource
```
