---
title: Overview
description: How phpdftk tracks ISO 32000-2 line by line, validates against six external suites, and stays fast doing it.
---

phpdftk is engineered against the PDF specification, not against a sample of PDFs. Every feature ties back to ISO 32000-2:2020 or one of the seven ISO subset standards, and every release is verified by independent tools that the rest of the industry already trusts.

This section is the audit trail.

## What's covered here

### [PDF Specification](/standards/spec/coverage/)

How completely we implement ISO 32000-2:2020 itself, object by object and field by field.

- **[Spec Coverage](/standards/spec/coverage/)** — every PDF object type and field with ✓/~/✗ status against the spec.
- **[Version Coverage](/standards/spec/version-coverage/)** — which features require PDF 1.0, 1.4, 1.6, 1.7, or 2.0, derived from `#[RequiresPdfVersion]` attributes in the source.

### [ISO Profiles](/standards/profiles/overview/)

The eight ISO subset standards phpdftk validates against — PDF/A, PDF/UA, PDF/X, PDF/VT, PDF/E, PDF/R, ZUGFeRD, and PDF/mail. 31 conformance levels in total.

- **[Conformance Overview](/standards/profiles/overview/)** — the API surface for opting into a profile and how strict-mode enforcement works.
- **[ISO Standards](/standards/profiles/iso-standards/)** — per-profile breakdown with constraint tables and source-file links.

### [External Validation](/standards/validation/about/)

Six independent validators run on every release: QPDF (structural), Arlington PDF Model (spec dictionary types), veraPDF (PDF/A), Matterhorn (PDF/UA), JHOVE (format), pdfid (security), and PDFBox Preflight (PDF/A cross-check). The reader is also stress-tested against 2,700+ real-world PDFs from veraPDF, QPDF, PDFium, PDFBox, and Poppler.

Start with **[About the Suites](/standards/validation/about/)** for the four-tier system, or jump to the **[Latest Compliance Report](/standards/validation/report/)** for the most recent run from `main`.

### [Performance](/standards/performance/overview/)

How phpdftk compares to FPDF, TCPDF, and mPDF on generation time, parsing throughput, and memory footprint — plus the cost of conformance validation.

- **[Performance Overview](/standards/performance/overview/)** — head-to-head comparisons and methodology.
- **[Latest Benchmarks](/standards/performance/benchmarks/)** — the most recent PHPBench run from `main`.
