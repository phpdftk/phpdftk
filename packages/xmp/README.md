# phpdftk/xmp

Read and write XMP (Extensible Metadata Platform) packets. XMP is an XML-based metadata format used in PDF, JPEG, PNG, and other file formats. No PDF dependency.

Requires `ext-simplexml`.

## Installation

```bash
composer require phpdftk/xmp
```

## Usage

```php
use Phpdftk\Xmp\XmpPacket;
use Phpdftk\Xmp\XmpWriter;
use Phpdftk\Xmp\XmpReader;

// Build an XMP packet
$xmp = new XmpPacket();
$xmp = $xmp->set('dc:title', 'My Document');
$xmp = $xmp->set('dc:creator', 'Jane Smith');
$xmp = $xmp->set('xmp:CreateDate', '2026-03-17T00:00:00Z');
$xmp = $xmp->set('pdf:Producer', 'phpdftk');

// Serialize to XMP packet string (for embedding in a file)
$writer = new XmpWriter();
$packetString = $writer->write($xmp);

// Read XMP from an existing packet string
$reader = new XmpReader();
$xmp = $reader->read($packetString);
echo $xmp->get('dc:title'); // 'My Document'
```

## Common XMP Namespaces

| Prefix | Namespace URI | Typical use |
|---|---|---|
| `dc:` | Dublin Core | `title`, `creator`, `description`, `subject` |
| `xmp:` | XMP Basic | `CreateDate`, `ModifyDate`, `CreatorTool` |
| `pdf:` | PDF namespace | `Producer`, `Keywords` |
| `xmpRights:` | XMP Rights | `Marked`, `WebStatement` |

## Classes

| Class | Description |
|---|---|
| `XmpPacket` | Immutable value object; `set(key, value)` returns a new instance |
| `XmpWriter` | Serializes an `XmpPacket` to a well-formed XMP packet string |
| `XmpReader` | Parses an XMP packet string back to an `XmpPacket` via SimpleXML |
