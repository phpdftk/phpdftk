---
title: Page Labeler
description: Set page numbering schemes with roman numerals, letters, and custom prefixes.
---

`PageLabeler` assigns page label ranges to a PDF, controlling how page numbers are displayed in PDF viewers. Supports arabic numerals, roman numerals (upper and lower), alphabetic labels (upper and lower), and custom prefixes.

## Opening a PDF

```php
use Phpdftk\Pdf\Toolkit\PageLabeler;

// From file
$labeler = PageLabeler::open('report.pdf');

// From string
$labeler = PageLabeler::openString($pdfBytes);

// Encrypted PDF
$labeler = PageLabeler::open('secured.pdf', password: 'secret');
```

## Roman numerals

Set roman numeral labels for a page range (e.g. i, ii, iii, iv for front matter):

```php
$labeler->setRomanNumerals(1, 4); // pages 1-4: i, ii, iii, iv

// Uppercase: I, II, III, IV
$labeler->setRomanNumerals(1, 4, uppercase: true);
```

When the range ends before the last page, the labeler automatically sets arabic numbering on the next page (unless you have already configured that page).

## Arabic numerals

```php
// Pages 5 onward: 1, 2, 3, ...
$labeler->setArabic(5, startNumber: 1);

// Pages 5-10: 1, 2, 3, 4, 5, 6
$labeler->setArabic(5, 10, startNumber: 1);
```

## Alphabetic labels

```php
$labeler->setAlphabetic(1, 4);                 // a, b, c, d
$labeler->setAlphabetic(1, 4, uppercase: true); // A, B, C, D
```

## Custom label ranges

For full control, use `setLabels` with a `LabelStyle` enum:

```php
use Phpdftk\Pdf\Toolkit\Label\LabelStyle;

$labeler->setLabels(
    startPage: 1,
    style: LabelStyle::RomanLower,
    prefix: 'Preface-',
    startNumber: 1,
);
// => "Preface-i", "Preface-ii", ...
```

### LabelStyle values

| Enum case | PDF value | Example output |
|---|---|---|
| `LabelStyle::Arabic` | `D` | 1, 2, 3 |
| `LabelStyle::RomanLower` | `r` | i, ii, iii |
| `LabelStyle::RomanUpper` | `R` | I, II, III |
| `LabelStyle::AlphaLower` | `a` | a, b, c |
| `LabelStyle::AlphaUpper` | `A` | A, B, C |

## Removing labels

```php
$labeler->removeLabels();
```

## Common pattern: front matter + body

```php
PageLabeler::open('report.pdf')
    ->setRomanNumerals(1, 4)       // pages 1-4: i, ii, iii, iv
    ->setArabic(5, null, 1)        // pages 5+: 1, 2, 3, ...
    ->save('labeled.pdf');
```

## Saving

```php
// To file
$labeler->save('labeled.pdf');

// To string
$bytes = $labeler->toBytes();
```

## Document info

```php
$labeler->getPageCount(); // int
```

## Escape hatch

```php
$reader = $labeler->getReader(); // PdfReader
```
