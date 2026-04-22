# apprlabs/pdf-core

PHP-native OOP mapping of the PDF specification (ISO 32000-2). Every PDF object type maps 1:1 to a PHP class, with each `/Field` from the spec mapping directly to a camelCase property.

## Installation

```bash
composer require apprlabs/pdf-core
```

Most users should install `apprlabs/pdf-writer` (which depends on this) or `apprlabs/pdf` (the metapackage) instead. Use `pdf-core` directly when you need full object-model access without the builder layer.

## What's Included

- **Document structure**: Catalog, PageTree, Page, Info, ViewerPreferences, Outline, PageLabel
- **Fonts**: Type1, TrueType, Type0, Type3, CIDFont subtypes, FontDescriptor, Encoding
- **Annotations**: 26 subtypes (Text, Link, FreeText, Highlight, Widget, Stamp, etc.)
- **Actions**: 20 types (GoTo, URI, JavaScript, Named, Launch, etc.)
- **Graphics**: ColorSpaces, XObjects, Functions, Shading, Patterns, ExtGState
- **Forms**: AcroForm, TextField, ButtonField, ChoiceField, SignatureField
- **Security**: EncryptDictionary, CryptFilter, digital signatures (PKCS#7, RFC 3161)
- **Content streams**: Fluent API for all 69 PDF content operators
- **File I/O**: PdfFileWriter, ObjectRegistry, CrossReferenceTable, TrailerDictionary

## Usage

```php
use ApprLabs\Pdf\Core\Document\Page;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\File\PdfFileWriter;

$writer = new PdfFileWriter();
$page = new Page(mediaBox: [0, 0, 612, 792]);
$font = new Type1Font(StandardFont::Helvetica);

// Register objects, build content streams, emit PDF bytes
$writer->register($page);
$writer->register($font);
// ...
$writer->save('/tmp/output.pdf');
```

## Documentation

Full documentation at [apprlabs.github.io/phpdftk](https://apprlabs.github.io/phpdftk/).
