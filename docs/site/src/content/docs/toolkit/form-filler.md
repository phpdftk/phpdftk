---
title: Form Filler
description: Fill interactive PDF form fields with a fluent API.
---

`FormFiller` reads and fills AcroForm fields using incremental updates, preserving the original PDF structure and any existing signatures.

## Opening a PDF

```php
use ApprLabs\Pdf\Toolkit\FormFiller;

// From file
$filler = FormFiller::open('form.pdf');

// From string
$filler = FormFiller::openString($pdfBytes);

// Encrypted PDF
$filler = FormFiller::open('secured.pdf', password: 'secret');
```

## Reading field information

### List all fields

```php
$names = $filler->getFieldNames();
// => ['name', 'email', 'subscribe', 'country']
```

### Get field details

```php
$info = $filler->getFieldInfo('email');

$info->name;     // 'email'
$info->type;     // FieldType::Text
$info->value;    // current value or null
$info->flags;    // int (PDF /Ff flags)
$info->maxLen;   // ?int (max length for text fields)
$info->options;  // ?string[] (options for choice fields)
$info->rect;     // ?float[] ([x1, y1, x2, y2] widget rectangle)
```

### Get all current values

```php
$values = $filler->getFieldValues();
// => ['name' => 'Jane', 'email' => null, 'subscribe' => 'Off', ...]
```

### Check field existence

```php
if ($filler->hasField('signature')) {
    // field exists
}
```

## Filling fields

### Text fields

```php
$filler->fill('name', 'Jane Doe')
       ->fill('email', 'jane@example.com');
```

### Fill multiple fields at once

```php
$filler->fillMany([
    'name'  => 'Jane Doe',
    'email' => 'jane@example.com',
    'city'  => 'Toronto',
]);
```

### Checkboxes

```php
$filler->check('subscribe');          // check
$filler->check('subscribe', false);   // uncheck
```

### Choice fields (dropdowns, list boxes)

```php
$filler->select('country', 'Canada');
```

## Saving

```php
// To file
$filler->save('filled.pdf');

// To string
$bytes = $filler->toBytes();
```

## Field types

The `FieldType` enum covers the four PDF interactive field types:

| Enum case | PDF value | Covers |
|---|---|---|
| `FieldType::Text` | `Tx` | Text input fields |
| `FieldType::Button` | `Btn` | Checkboxes, radio buttons, push buttons |
| `FieldType::Choice` | `Ch` | Dropdowns, list boxes |
| `FieldType::Signature` | `Sig` | Signature fields |

## Document info

```php
$filler->getPageCount(); // int
```

## Escape hatch

```php
$reader = $filler->getReader(); // PdfReader
```
