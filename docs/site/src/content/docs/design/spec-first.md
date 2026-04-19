---
title: Spec-First Design
description: Why phpdftk treats the PDF specification as the source of truth for its API.
---

## The specification is the API

Most PDF libraries define their own vocabulary. TCPDF has `SetFont()`, `Cell()`, `MultiCell()`. mPDF has `WriteHTML()`. These are convenient but proprietary — they don't correspond to anything in the PDF specification, and they differ between libraries.

phpdftk takes a different approach: the PDF specification (ISO 32000-2:2020) **is** the API. Every dictionary type in the spec has a PHP class. Every field has a property. Every operator has a method. If you know the spec, you know the library. If you know the library, you know the spec.

## What this looks like

### Spec table to PHP class

ISO 32000-2, Table 30 defines a Page dictionary with fields like `/Type`, `/Parent`, `/MediaBox`, `/Contents`, `/Rotate`, `/Annots`. In phpdftk:

```php
class Page extends PdfObject
{
    public const PDF_TYPE = 'Page';

    public ?PdfReference $parent = null;     // /Parent
    public ?PdfArray $mediaBox = null;       // /MediaBox
    public array $contents = [];             // /Contents
    public int $rotate = 0;                  // /Rotate
    public array $annots = [];               // /Annots
    // ... every other field from Table 30
}
```

### Spec operator to PHP method

ISO 32000-2, Table 107 lists the `Tj` operator (show text string). In phpdftk:

```php
$contentStream->showText('Hello'); // emits: (Hello) Tj
```

Every one of the 69 content stream operators has a named method on `ContentStream`.

### Spec naming to PHP naming

The conversion is mechanical:

| PDF | PHP |
|---|---|
| `/MediaBox` | `$mediaBox` |
| `/FirstChar` | `$firstChar` |
| `/CIDSystemInfo` | `$cidSystemInfo` |
| `/FontFile2` | `$fontFile2` |

No creativity, no interpretation. `ucfirst(property) === PdfKey` in every case (with documented overrides for the handful of exceptions like `/AA` → `$aa`).

## Why this matters

### Transferable knowledge

A developer who has used phpdftk can read the PDF spec and know exactly which classes and properties map to which sections. A developer reading the spec for the first time can predict the phpdftk API without looking at the documentation.

### Complete coverage by construction

If a spec field exists, the property exists. The [spec coverage audit](/reference/spec-coverage/) shows 100% field coverage across every major object type — not because we went through and checked boxes, but because the design process *is* reading the spec table and creating the corresponding property.

### Future-proof

When the PDF spec adds a field to an existing dictionary type, adding support is one line: a new public property. No refactoring, no new methods, no API design decisions. The spec already made the design decision.

### No mismatches

Libraries that invent their own vocabulary eventually develop mismatches with the spec. A method called `setColor()` might set the stroke color, the fill color, or both — it depends on which library you're using. In phpdftk, there's no ambiguity:

```php
$cs->setStrokeColorRGB(1, 0, 0);   // RG operator
$cs->setFillColorRGB(0, 0, 1);     // rg operator
```

The method name tells you the operator. The operator tells you the spec section. There's one source of truth.

## The higher levels are still spec-aligned

Even the convenience APIs (`PdfWriter`, `Pdf`) don't invent non-spec vocabulary. `PdfWriter::addPage()` creates a `Page` object, registers it, and wires it into the `PageTree`. `PdfWriter::addFont()` creates a `Font` object with a `FontDescriptor`. The methods are named after *what they produce* in the spec, not after an invented abstraction.

When you call `$page->drawText()`, it emits `BT Tf Td (text) Tj ET` — the exact operators from ISO 32000-2, Table 107. The convenience is in not having to remember the operator sequence, not in hiding it.
