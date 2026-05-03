---
title: Performance
description: Benchmark comparisons against TCPDF, mPDF, Dompdf, and FPDF.
---

All benchmarks run via phpbench on the same machine. phpdftk generates spec-compliant PDFs with proper xref tables, binary comments, and typed trailers.

## PDF Generation Time

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| **phpdftk** | 938 us | 1.1 ms | 1.2 ms | 2.2 ms | 3.5 ms |
| FPDF | 432 us | 459 us | 541 us | 833 us | 1.2 ms |
| TCPDF | 5.4 ms | 5.7 ms | 6.3 ms | 10.7 ms | 16.2 ms |
| mPDF | 12.4 ms | 14.7 ms | 16.4 ms | 33.0 ms | 54.4 ms |
| Dompdf | 5.4 ms | 7.7 ms | 10.4 ms | 39.5 ms | 88.0 ms |

phpdftk is **3-25x faster** than TCPDF, mPDF, and Dompdf. It's within 2x of FPDF (a minimal library with no font embedding, no encryption, no annotations).

## Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| **phpdftk** | 5.8 MB | 5.9 MB | 7.4 MB |
| FPDF | 5.3 MB | 5.3 MB | 5.3 MB |
| TCPDF | 13.0 MB | 13.0 MB | 13.0 MB |
| mPDF | 17.8 MB | 17.9 MB | 18.6 MB |
| Dompdf | 9.5 MB | 10.0 MB | 16.1 MB |

## PDF Parsing Performance

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| **phpdftk** | 601 us | 867 us | 3.8 ms |
| smalot/pdfparser | 871 us | 1.1 ms | 2.9 ms |
| setasign/fpdi | 953 us | 1.5 ms | 21.5 ms |

## What's being measured

- **Generation**: create N pages with text content, serialize to PDF bytes
- **Memory**: peak `memory_get_peak_usage(true)` during generation
- **Parsing**: open PDF, extract catalog/info/version, iterate all pages

## Conformance validation overhead

Cost of running ISO standard validation during PDF generation (10 pages with embedded TrueType font):

| Profile | Time |
|---|---|
| PDF/A-1b | 27.6 ms |
| PDF/UA-1 | 25.4 ms |
| PDF/X-4 | 26.5 ms |
| PDF/VT-1 | 29.4 ms |
| PDF/X-5g | 29.1 ms |
| ZUGFeRD BASIC | 30.8 ms |
| PDF/mail-1 | 34.0 ms |
| PDF/R-1 | 4.0 ms |

Most of the cost is font embedding and OutputIntent setup, not the constraint checking itself.

## Spec compliance advantage

phpdftk handles both classic xref tables (20-byte entries per ISO 32000-2 SS7.5.4) and cross-reference streams (PDF 1.5+). Many competitors fail on one or both formats.

## Running benchmarks yourself

```bash
# Full benchmark suite (generates docs/generated/benchmarks.md)
mise run benchmark

# Single benchmark class
vendor/phpbench/phpbench/phpbench run benchmarks/GeneratePdfBench.php --report=default
```
