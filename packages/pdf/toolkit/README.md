# apprlabs/pdf-toolkit

High-level pipelines for common PDF operations: fill forms, stamp text, merge files, extract text, encrypt, and more.

## Installation

```bash
composer require apprlabs/pdf-toolkit
```

## Tools

### Form Filling

```php
use ApprLabs\Pdf\Toolkit\FormFiller;

FormFiller::open('form.pdf')
    ->fill('name', 'Jane Doe')
    ->check('subscribe', true)
    ->select('country', 'Canada')
    ->save('filled.pdf');
```

### Stamping & Watermarks

```php
use ApprLabs\Pdf\Toolkit\PdfStamper;
use ApprLabs\Pdf\Toolkit\StampPosition;

PdfStamper::open('report.pdf')
    ->watermark('DRAFT')
    ->addPageNumbers(StampPosition::BottomCenter)
    ->save('stamped.pdf');
```

### Merging

```php
use ApprLabs\Pdf\Toolkit\PdfMerger;

PdfMerger::create()
    ->addFile('chapter1.pdf')
    ->addFile('chapter2.pdf')
    ->save('book.pdf');
```

### Page Slicing

```php
use ApprLabs\Pdf\Toolkit\PageSlicer;

PageSlicer::open('large.pdf')
    ->keepRange(1, 5)
    ->save('first-five.pdf');
```

### Encryption

```php
use ApprLabs\Pdf\Toolkit\PdfEncrypt;
use ApprLabs\Pdf\Toolkit\EncryptionMethod;

PdfEncrypt::open('doc.pdf')
    ->encrypt('user', 'owner', EncryptionMethod::Aes256)
    ->save('encrypted.pdf');
```

### All Tools

| Tool | Description |
|---|---|
| `FormFiller` | Fill form fields (text, checkbox, choice) |
| `PdfStamper` | Add text stamps, watermarks, page numbers, headers/footers |
| `PdfMerger` | Merge multiple PDFs into one |
| `PageSlicer` | Keep, remove, reorder, reverse, or split pages |
| `PageTransformer` | Rotate pages, set crop/media boxes |
| `TextExtractor` | Extract text per page or search for patterns |
| `TextRedactor` | Redact text or areas |
| `AnnotationFlattener` | Flatten annotations into page content |
| `MetadataEditor` | Read/write document info fields |
| `BookmarkEditor` | Add, get, or remove bookmarks |
| `PageLabeler` | Set page numbering styles (roman, arabic, etc.) |
| `PdfEncrypt` | Encrypt/decrypt with AES-128/256 or RC4 |

## Documentation

Full documentation at [apprlabs.github.io/phpdftk](https://apprlabs.github.io/phpdftk/).
