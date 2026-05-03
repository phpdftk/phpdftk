---
title: Conformance Validation
description: Built-in validation for 8 ISO standards and 31 conformance levels — PDF/A, PDF/UA, PDF/X, PDF/VT, PDF/E, PDF/R, ZUGFeRD, PDF/mail.
---

phpdftk is the only PHP PDF library with **built-in conformance validation** for all major PDF subset standards. Set a profile, and the library enforces every applicable constraint at generate time — no external validators needed, no post-hoc checking, no guesswork.

## 8 standards, 31 levels, one API call

| Standard | What it guarantees | Levels |
|---|---|---|
| **PDF/A** (ISO 19005) | Documents remain readable for decades | 11 levels |
| **PDF/UA** (ISO 14289) | Documents are accessible to assistive technology | 2 levels |
| **PDF/X** (ISO 15930) | Colors reproduce correctly in print | 6 levels |
| **PDF/VT** (ISO 16612) | Variable data prints at production speed | 3 levels |
| **PDF/E** (ISO 24517) | Engineering drawings with 3D content | 1 level |
| **PDF/R** (ISO 23504) | Scanned documents transport reliably | 1 level |
| **Factur-X** (ZUGFeRD) | Invoices are machine-readable across the EU | 6 levels |
| **PDF/mail** (ISO 23053) | Documents open safely from email | 1 level |

Every level is validated by veraPDF and Matterhorn Protocol in CI. See the [Compliance Report](/conformance/compliance/) for current results.

## Usage

### One line to enable

```php
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;

$writer = new PdfWriter();
$writer->setConformance(PdfAProfile::A2b);

// Build your document normally...
$page = $writer->addPage(612, 792);
$font = $writer->addFont(new TrueTypeFont(/* ... */));

$writer->save('archive.pdf');
// Done. The output is guaranteed PDF/A-2b compliant.
```

The library handles everything automatically:
- Injects XMP identification metadata (`pdfaid:part`, `pdfaid:conformance`)
- Pins the PDF version to the profile's requirement
- Validates all constraints before emitting a single byte
- Throws `ConformanceException` if any rule is violated

### Dual compliance

Combine profiles for documents that must meet multiple standards — accessible archival documents, for example:

```php
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfUaProfile;

$writer->setConformanceProfiles([PdfAProfile::A2a, PdfUaProfile::UA1]);
// Output satisfies BOTH PDF/A-2a and PDF/UA-1 simultaneously.
```

### Early validation

Check conformance before generating the full PDF:

```php
$results = $writer->checkConformance();
foreach ($results as $result) {
    if (!$result->isCompliant) {
        foreach ($result->violations as $violation) {
            echo "{$violation->severity->value}: {$violation->message}\n";
        }
    }
}
```

### Lenient mode

Collect violations without throwing — useful for migration or auditing:

```php
$writer->setConformance(PdfAProfile::A1b, strict: false);
$writer->save('output.pdf');

$results = $writer->getConformanceResults();
// Inspect violations without blocking output
```

## What gets enforced

27 constraint rules across all standards, each enforcing specific ISO clauses:

| Constraint | What it catches |
|---|---|
| Font embedding | Missing font programs — text won't render on other systems |
| Color space | Device-dependent colors that print differently everywhere |
| Metadata | Missing XMP identification — validators won't recognize the profile |
| Transparency | Blend modes that older RIPs can't handle |
| Encryption | Password protection that prevents archival access |
| Actions | JavaScript/Launch that pose security or reproducibility risks |
| Tagged structure | Missing accessibility tree — screen readers can't navigate |
| Output intent | No color management — unpredictable print reproduction |
| Annotations | Missing alt text — inaccessible to assistive technology |
| Tab order | Wrong reading order for keyboard navigation |
| Trim box | Print production can't determine final page dimensions |
| Embedded files | Prohibited attachments that break archival guarantees |

Plus specialized constraints: DPartRoot for variable data, 3D content validation for engineering, raster-only enforcement, invoice XML presence, and multimedia prohibition.

See [ISO Standards](/conformance/iso-standards/) for the full per-standard breakdown.

## Validate existing PDFs

Not just for PDFs you generate — validate any PDF against any profile:

```php
use Phpdftk\Pdf\Conformance\ConformanceChecker;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;

$checker = ConformanceChecker::open('existing.pdf');
$results = $checker->checkProfile(PdfAProfile::A1b);

// Or check multiple profiles
$results = $checker->checkProfiles([PdfAProfile::A1b, PdfUaProfile::UA1]);
```

Same constraint engine, same rules — regardless of which tool generated the PDF.

## Installation

```bash
composer require phpdftk/pdf-conformance
```

Add alongside the writer:

```bash
composer require phpdftk/pdf-writer phpdftk/pdf-conformance
```
