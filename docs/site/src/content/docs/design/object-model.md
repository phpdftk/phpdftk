---
title: The Object Model
description: How phpdftk maps ISO 32000-2 to PHP classes — and why.
---

## The core idea

Every dictionary type defined in the PDF specification has a corresponding PHP class. Every field in that dictionary is a public property in camelCase. The class constant `PDF_TYPE` matches the `/Type` value from the spec.

```
PDF spec (ISO 32000-2, Table 30):       PHP class:
─────────────────────────────           ─────────────────────────
<< /Type /Page                          class Page extends PdfObject
   /Parent 2 0 R                        {
   /MediaBox [0 0 612 792]                  public ?PdfReference $parent;
   /Contents 5 0 R                          public ?PdfArray $mediaBox;
   /Rotate 90                               public array $contents;
>>                                          public int $rotate;
                                        }
```

This is a mechanical translation. If you can read the PDF spec, you can read the PHP classes, and vice versa.

## PdfObject vs. Serializable

This is the most important architectural distinction:

### PdfObject

For top-level objects that need to be referenced from elsewhere via `X 0 R`:

- Assigned an object number by `ObjectRegistry` when registered
- Serialized as indirect objects: `5 0 obj ... endobj`
- Examples: `Page`, `Font`, `Annotation`, `Outline`, `ContentStream`

### Serializable

For inline dictionaries nested directly inside a parent's dictionary:

- Never assigned an object number
- Serialized inline as part of the parent
- Examples: `TransitionDict` (inside `Page`), `BorderStyle` (inside `Annotation`)

The rule: does it need to be independently referenced via `X 0 R`? If yes, `PdfObject`. If it only appears inline inside one parent, `Serializable`.

## Primitive types

The PDF spec defines eight primitive types. Each has a PHP class:

| PDF syntax | PHP class | Example |
|---|---|---|
| `/Name` | `PdfName` | `new PdfName('Helvetica')` |
| `(text)` | `PdfString` | `new PdfString('Hello')` |
| `42` | `PdfNumber` | `new PdfNumber(42)` |
| `true` | `PdfBoolean` | `new PdfBoolean(true)` |
| `null` | `PdfNull` | `PdfNull::instance()` |
| `[1 2 3]` | `PdfArray` | `new PdfArray([...])` |
| `<< ... >>` | `PdfDictionary` | `new PdfDictionary()` |
| `5 0 R` | `PdfReference` | `new PdfReference(5)` |

Every property on every `PdfObject` is one of these types (or a union/nullable variant). There are no untyped arrays or magic strings.

## Serialization

Every object has a `toPdf(): string` method that produces the exact PDF syntax for that object. `PdfObject` adds `toIndirectObject(): string` which wraps the output in `X Y obj ... endobj`.

Serialization is deterministic — the same object graph always produces the same bytes (modulo timestamps and random IDs). This makes testing straightforward: assert on the serialized output.

## The registry and file writer

Objects don't know their own object numbers until they're registered:

```php
$page = new Page();
// $page->objectNumber is 0 here

$fw->register($page);
// Now $page->objectNumber is assigned (e.g., 3)

// Other objects can reference it
$pageTree->kids = [new PdfReference($page->objectNumber)];
```

The `PdfFileWriter` takes all registered objects and emits them in order with correct byte offsets in the xref table. It handles:

- PDF header (`%PDF-1.7` + binary comment)
- Indirect object body (each object at its recorded byte offset)
- Cross-reference table (classic 20-byte entries or xref streams)
- Trailer dictionary (`/Size`, `/Root`, `/Info`, `/ID`, `/Encrypt`)
- `startxref` + `%%EOF`

## Hydration (reading)

The `PdfHydrator` goes the other direction — given a raw `PdfDictionary` from the parser, it instantiates the typed class:

```php
// Parser returns a raw dictionary
$dict = new PdfDictionary();
$dict->set('Type', new PdfName('Page'));
$dict->set('MediaBox', new PdfArray([...]));

// Hydrator produces a typed Page
$page = PdfHydrator::hydrate($dict, objectNumber: 3);
// $page instanceof Page === true
```

The hydrator handles:
- 47 unique `/Type` registrations
- Subtype-aware dispatch for shared types (annotations by `/Subtype`, fonts by `/Subtype`, XObjects by `/Subtype`)
- Constructor argument extraction for classes that require them
- Type coercion (`PdfNumber` to `int`/`float`, `PdfBoolean` to `bool`, etc.)

This enables round-tripping: read a PDF into typed objects, modify properties, write it back.

## Why this matters

The object model is the foundation that makes everything else possible:

- **The writer** doesn't know about PDF syntax — it just calls `toPdf()` on registered objects
- **The reader** produces the same object types the writer consumes
- **The toolkit** (form filling, merging, stamping) can modify objects and re-serialize them
- **Static analysis** catches spec violations at compile time
- **IDE support** makes the PDF spec browsable via autocomplete
