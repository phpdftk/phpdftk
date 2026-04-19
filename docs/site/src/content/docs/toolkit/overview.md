---
title: Toolkit Overview
description: High-level pipelines for common PDF operations — extract, merge, stamp, fill, encrypt.
---

The toolkit package (`apprlabs/pdf-toolkit`) provides reader-to-writer pipelines for common PDF manipulation tasks. Each class follows the same pattern: open a PDF, apply operations, save the result.

## Design principles

- **Reader-based**: toolkit classes compose `PdfReader` + `IncrementalWriter` or `PdfFileWriter` — they do not depend on the writer package
- **Fluent API**: chain operations and save in one expression
- **Escape hatches**: every class exposes `getReader()` for raw access
- **Page selection**: shared `PageSelector` utility for targeting specific pages

## Available tools

### Text Extraction

```php
use ApprLabs\Pdf\Toolkit\TextExtractor;

$text = TextExtractor::open('report.pdf')->allPages();
$results = TextExtractor::open('contract.pdf')->search('liability');
```

[Full documentation](/toolkit/text-extractor/)

### Form Filling

```php
use ApprLabs\Pdf\Toolkit\FormFiller;

FormFiller::open('form.pdf')
    ->fill('name', 'Jane Doe')
    ->check('subscribe')
    ->select('country', 'Canada')
    ->save('filled.pdf');
```

[Full documentation](/toolkit/form-filler/)

### PDF Stamping

```php
use ApprLabs\Pdf\Toolkit\PdfStamper;
use ApprLabs\Pdf\Toolkit\Stamper\StampPosition;

PdfStamper::open('report.pdf')
    ->watermark('DRAFT')
    ->addPageNumbers(StampPosition::BottomCenter)
    ->save('stamped.pdf');
```

[Full documentation](/toolkit/pdf-stamper/)

### Page Slicing

```php
use ApprLabs\Pdf\Toolkit\PageSlicer;

PageSlicer::open('large.pdf')
    ->keepRange(1, 5)
    ->save('first-five.pdf');
```

[Full documentation](/toolkit/page-slicer/)

### PDF Merging

```php
use ApprLabs\Pdf\Toolkit\PdfMerger;

PdfMerger::create()
    ->addFile('chapter1.pdf')
    ->addFile('chapter2.pdf')
    ->save('book.pdf');
```

[Full documentation](/toolkit/pdf-merger/)

### Page Transformation

```php
use ApprLabs\Pdf\Toolkit\PageTransformer;

PageTransformer::open('input.pdf')
    ->rotate(90)
    ->scale(0.8)
    ->save('output.pdf');
```

[Full documentation](/toolkit/page-transformer/)

### Annotation Flattening

```php
use ApprLabs\Pdf\Toolkit\AnnotationFlattener;

AnnotationFlattener::open('form.pdf')
    ->flattenAll()
    ->save('flat.pdf');
```

[Full documentation](/toolkit/annotation-flattener/)

### Text Redaction

```php
use ApprLabs\Pdf\Toolkit\TextRedactor;

TextRedactor::open('contract.pdf')
    ->redactText('Jane Doe')
    ->redactPattern('/\d{3}-\d{2}-\d{4}/')
    ->apply()
    ->save('redacted.pdf');
```

[Full documentation](/toolkit/text-redactor/)

### Metadata Editing

```php
use ApprLabs\Pdf\Toolkit\MetadataEditor;

MetadataEditor::open('doc.pdf')
    ->setTitle('Annual Report')
    ->setAuthor('Acme Corp')
    ->save('updated.pdf');
```

[Full documentation](/toolkit/metadata-editor/)

### Encryption

```php
use ApprLabs\Pdf\Toolkit\PdfEncrypt;
use ApprLabs\Pdf\Toolkit\Encryption\EncryptionMethod;

PdfEncrypt::open('doc.pdf')
    ->encrypt('user', 'owner', EncryptionMethod::Aes256)
    ->save('encrypted.pdf');
```

[Full documentation](/toolkit/pdf-encrypt/)

### Bookmark Editing

```php
use ApprLabs\Pdf\Toolkit\BookmarkEditor;
use ApprLabs\Pdf\Toolkit\Bookmark\BookmarkEntry;

BookmarkEditor::open('report.pdf')
    ->setBookmarks(
        new BookmarkEntry('Chapter 1', 1),
        new BookmarkEntry('Chapter 2', 10),
    )
    ->save('bookmarked.pdf');
```

[Full documentation](/toolkit/bookmark-editor/)

### Page Labeling

```php
use ApprLabs\Pdf\Toolkit\PageLabeler;

PageLabeler::open('report.pdf')
    ->setRomanNumerals(1, 4)
    ->setArabic(5, null, 1)
    ->save('labeled.pdf');
```

[Full documentation](/toolkit/page-labeler/)

### Page Selection

The `PageSelector` utility is shared across toolkit classes:

```php
use ApprLabs\Pdf\Toolkit\PageSelector;

$all = PageSelector::all();
$specific = PageSelector::pages(1, 3, 5);
$range = PageSelector::range(2, 8);
$even = PageSelector::even();
$odd = PageSelector::odd();

// Check if a page matches
$even->matches(4, 10); // true (page 4 of 10)

// Get 0-based indices for a total page count
$range->resolve(10); // [1, 2, 3, 4, 5, 6, 7] (pages 2-8)
```

## Installation

```bash
composer require apprlabs/pdf-toolkit
```

The toolkit requires `pdf-core` and `pdf-reader` (pulled in automatically).
