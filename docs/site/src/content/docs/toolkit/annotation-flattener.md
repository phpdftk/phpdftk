---
title: Annotation Flattener
description: Flatten annotations and form widgets into page content, making them non-interactive.
---

`AnnotationFlattener` burns annotation appearances into the page content stream and removes the annotation objects, producing a static PDF. This is useful for locking down filled forms, finalizing review markup, or preparing documents for print.

## Opening a PDF

```php
use ApprLabs\Pdf\Toolkit\AnnotationFlattener;

// From file
$flattener = AnnotationFlattener::open('form.pdf');

// From string
$flattener = AnnotationFlattener::openString($pdfBytes);

// Encrypted PDF
$flattener = AnnotationFlattener::open('secured.pdf', password: 'secret');
```

## Flatten all annotations

```php
$flattener->flattenAll()->save('flat.pdf');
```

Restrict to specific pages:

```php
use ApprLabs\Pdf\Toolkit\PageSelector;

$flattener->flattenAll(PageSelector::pages(1, 2))->save('flat.pdf');
```

## Flatten by annotation type

Flatten only specific annotation subtypes (PDF `/Subtype` values):

```php
$flattener->flattenType('FreeText', 'Stamp')->save('flat.pdf');
```

## Flatten forms only

A convenience method that flattens only Widget annotations (form fields):

```php
$flattener->flattenForms()->save('flat-form.pdf');

// On specific pages
$flattener->flattenForms(PageSelector::range(1, 3))->save('flat-form.pdf');
```

## How it works

For each annotation targeted for flattening:

1. The annotation's normal appearance stream (`/AP /N`) is located
2. The appearance is positioned using the annotation's `/Rect` and the appearance's `/BBox`
3. A transformation matrix maps the appearance BBox to the annotation Rect
4. The appearance is invoked as an XObject (`Do` operator) in a new content stream appended to the page
5. The annotation is removed from the page's `/Annots` array

Annotations without an appearance stream are left unchanged.

## Chaining and saving

```php
AnnotationFlattener::open('reviewed.pdf')
    ->flattenType('Highlight', 'StrikeOut')
    ->flattenForms()
    ->save('finalized.pdf');
```

```php
$bytes = $flattener->toBytes();
```

## Document info

```php
$flattener->getPageCount(); // int
```

## Escape hatch

```php
$reader = $flattener->getReader(); // PdfReader
```
