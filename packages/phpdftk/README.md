# phpdftk/phpdftk

PHP-native OOP library for generating and manipulating PDF files. Every object in the PDF specification maps to a PHP 8.4 class, with each `/Field` from the spec mapping directly to a PHP property (camelCase).

## Requirements

- PHP 8.4+
- `ext-zlib` (for stream compression)
- `ext-openssl` (for encryption)
- `ext-simplexml` (for XMP metadata)

## Installation

```bash
composer require phpdftk/phpdftk
```

## Quick Start

```php
use Phpdftk\Writer\PdfWriter;
use Phpdftk\Font\Type1Font;
use Phpdftk\Font\StandardFont;

$writer = new PdfWriter();

$page = $writer->addPage(612, 792);           // letter size in points
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
$cs   = $writer->addContentStream($page);

$cs->beginText()
   ->setFont($font, 12)
   ->moveTextPosition(72, 720)
   ->showText('Hello, World!')
   ->endText();

$writer->save('/tmp/hello.pdf');
```

## Architecture

### Design Principles

Every PDF object type is a PHP class in the `Phpdftk\` namespace. Properties match the PDF spec field names in camelCase (e.g., `/MediaBox` → `$mediaBox`). Each class has a `toPdf(): string` method for serialization to raw PDF syntax.

### Namespace Layout

| Namespace | Contents |
|---|---|
| `Core\` | Primitive PDF types: `PdfObject`, `PdfName`, `PdfString`, `PdfNumber`, `PdfBoolean`, `PdfNull`, `PdfArray`, `PdfDictionary`, `PdfStream`, `PdfReference` |
| `Document\` | `Catalog`, `PageTree`, `Page`, `Info`, `ViewerPreferences` |
| `Font\` | `Type1Font`, `TrueTypeFont`, `Type0Font`, `CIDFont`, `FontDescriptor`, `Encoding`, `StandardFont` enum |
| `Annotation\` | `TextAnnotation`, `LinkAnnotation`, `FreeTextAnnotation`, `HighlightAnnotation`, `StampAnnotation`, `InkAnnotation`, `PopupAnnotation`, `WidgetAnnotation` |
| `Graphics\` | `ExtGState`, `DeviceRGB`, `DeviceCMYK`, `DeviceGray`, `ImageXObject`, `FormXObject` |
| `Interactive\Form\` | `AcroForm`, `TextField`, `ButtonField`, `ChoiceField`, `SignatureField` |
| `Action\` | `GoToAction`, `URIAction`, `JavaScriptAction`, `NamedAction` |
| `Content\` | `ContentStream` (fluent operator API), `Resources` |
| `Writer\` | `PdfWriter`, `ObjectRegistry`, `CrossReferenceTable` |

### How a PDF is Built

`PdfWriter` orchestrates generation:

1. `addPage()` registers a page and returns it for use with `addContentStream()`
2. `addFont()` registers a font and returns the resource name (`F1`, `F2`, …)
3. `addContentStream()` creates and attaches a `ContentStream` to a page
4. `save()` / `generate()` serializes all objects, computes byte offsets, writes the xref table, and finalizes the trailer

### Content Streams

`ContentStream` provides a fluent API covering all PDF content operators:

```php
$cs->saveGraphicsState()
   ->concatMatrix(1, 0, 0, 1, 100, 100)   // translate
   ->setLineWidth(2.0)
   ->setStrokeColorRGB(1.0, 0.0, 0.0)
   ->rectangle(0, 0, 200, 100)
   ->stroke()
   ->restoreGraphicsState();

$cs->beginText()
   ->setFont('F1', 14)
   ->setTextLeading(18)
   ->moveTextPosition(72, 680)
   ->showText('Line one')
   ->nextLine()
   ->showText('Line two')
   ->endText();
```

Operator groups: text, graphics state, paths, painting, color, XObjects, raw.

### Multi-page Document with Metadata

```php
use Phpdftk\Document\Info;
use Phpdftk\Document\ViewerPreferences;

$info = new Info();
$info->title    = 'Annual Report';
$info->author   = 'Jane Smith';
$info->producer = 'phpdftk';

$prefs = new ViewerPreferences();
$prefs->hideToolbar  = true;
$prefs->displayDocTitle = true;

$writer = new PdfWriter();
$writer->setInfo($info);
$writer->setViewerPreferences($prefs);

for ($i = 1; $i <= 10; $i++) {
    $page = $writer->addPage(595, 842);  // A4
    $font = $writer->addFont(new Type1Font(StandardFont::TimesRoman));
    $cs   = $writer->addContentStream($page);
    $cs->beginText()
       ->setFont($font, 11)
       ->moveTextPosition(72, 770)
       ->showText("Page $i of 10")
       ->endText();
}

$writer->save('/tmp/report.pdf');
```

### Adding Images

```php
$imageRef = $writer->addImage('/path/to/photo.jpg');  // auto-detects JPEG/PNG
$cs->doXObject($imageRef);
```

### Annotations

```php
use Phpdftk\Annotation\LinkAnnotation;
use Phpdftk\Action\URIAction;
use Phpdftk\Geometry\Rectangle;

$action = new URIAction();
$action->uri = 'https://example.com';

$link = new LinkAnnotation();
$link->rect   = new Rectangle(72, 700, 200, 20);
$link->action = $action;

$page->annots[] = $link;
```

### Interactive Forms

```php
use Phpdftk\Interactive\Form\AcroForm;
use Phpdftk\Interactive\Form\TextField;

$form  = new AcroForm();
$field = new TextField();
$field->partialFieldName = 'name';
$field->rect = new Rectangle(72, 650, 300, 20);
$form->fields[] = $field;

$writer->setAcroForm($form);
```

## Standard Fonts

The 14 standard PDF fonts are available without embedding via the `StandardFont` enum:

`Helvetica`, `Helvetica-Bold`, `Helvetica-Oblique`, `Helvetica-BoldOblique`,
`Times-Roman`, `Times-Bold`, `Times-Italic`, `Times-BoldItalic`,
`Courier`, `Courier-Bold`, `Courier-Oblique`, `Courier-BoldOblique`,
`Symbol`, `ZapfDingbats`

## Spec Compliance

- xref entries are exactly 20 bytes (`OOOOOOOOOO GGGGG n \r\n`)
- Object 0 is always the free-list head (`0000000000 65535 f \r\n`)
- Stream dictionaries include exact `/Length`
- Binary comment `%âãÏÓ` follows the header per §7.5.2
- `PdfName` hex-escapes special characters with `#XX`
- `PdfString` escapes `(`, `)`, `\`, `\n`, `\r`, `\t`

See [docs/spec-coverage.md](../../docs/spec-coverage.md) for a full audit of ISO 32000-2 coverage.
