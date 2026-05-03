---
title: Metadata Editor
description: Read and write PDF document Info dictionary metadata.
---

`MetadataEditor` provides typed access to the PDF Info dictionary fields (Title, Author, Subject, etc.) and writes changes via incremental updates.

## Opening a PDF

```php
use Phpdftk\Pdf\Toolkit\MetadataEditor;

// From file
$editor = MetadataEditor::open('doc.pdf');

// From string
$editor = MetadataEditor::openString($pdfBytes);

// Encrypted PDF
$editor = MetadataEditor::open('secured.pdf', password: 'secret');
```

## Reading metadata

### Individual fields

```php
$editor->getTitle();        // ?string
$editor->getAuthor();       // ?string
$editor->getSubject();      // ?string
$editor->getKeywords();     // ?string
$editor->getCreator();      // ?string
$editor->getProducer();     // ?string
$editor->getCreationDate(); // ?DateTimeImmutable
$editor->getModDate();      // ?DateTimeImmutable
$editor->getTrapped();      // ?string ('True', 'False', or 'Unknown')
```

### All fields at once

```php
$info = $editor->getAll(); // MetadataInfo

$info->title;
$info->author;
$info->subject;
$info->keywords;
$info->creator;
$info->producer;
$info->creationDate;  // ?DateTimeImmutable
$info->modDate;       // ?DateTimeImmutable
$info->trapped;
```

`MetadataInfo` is a readonly value object with all nine standard Info dictionary fields.

## Writing metadata

All setters are fluent:

```php
$editor
    ->setTitle('Quarterly Report Q4 2025')
    ->setAuthor('Jane Doe')
    ->setSubject('Financial Summary')
    ->setKeywords('finance, quarterly, 2025')
    ->setCreator('Report Generator')
    ->setProducer('phpdftk');
```

### Dates

```php
$editor->setCreationDate(new \DateTimeImmutable('2025-01-15'));
$editor->setModDate(new \DateTime('now'));
```

### Trapped flag

```php
$editor->setTrapped('True');  // True, False, or Unknown
```

### Custom fields

Add non-standard keys to the Info dictionary:

```php
$editor->setCustom('Department', 'Engineering');
$editor->setCustom('ReviewStatus', 'Approved');
```

## Saving

```php
// To file
$editor->save('updated.pdf');

// To string
$bytes = $editor->toBytes();
```

## Complete example

```php
MetadataEditor::open('doc.pdf')
    ->setTitle('Annual Report 2025')
    ->setAuthor('Acme Corp')
    ->setModDate(new \DateTimeImmutable())
    ->save('updated.pdf');
```

## Document info

```php
$editor->getPageCount(); // int
```

## Escape hatch

```php
$reader = $editor->getReader(); // PdfReader
```
