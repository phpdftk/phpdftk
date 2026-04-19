---
title: Performance
description: Benchmark comparisons against TCPDF, mPDF, Dompdf, and FPDF.
---

All benchmarks run via phpbench on the same machine. phpdftk generates spec-compliant PDFs with proper xref tables, binary comments, and typed trailers.

## PDF Generation Time

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| **phpdftk** | 653 us | 692 us | 731 us | 1.1 ms | 1.8 ms |
| FPDF | 411 us | 438 us | 489 us | 785 us | 1.2 ms |
| TCPDF | 4.8 ms | 5.2 ms | 5.8 ms | 10.7 ms | 15.6 ms |
| mPDF | 11.8 ms | 13.2 ms | 15.9 ms | 32.3 ms | 53.3 ms |
| Dompdf | 5.2 ms | 6.6 ms | 10.3 ms | 44.9 ms | 88.0 ms |

phpdftk is **3-50x faster** than TCPDF, mPDF, and Dompdf. It's within 1.5x of FPDF (a minimal library with no font embedding, no encryption, no annotations).

## Peak Memory (100 pages)

| Library | Memory |
|---|---|
| **phpdftk** | 5.4 MB |
| FPDF | 5.0 MB |
| TCPDF | 13.0 MB |
| mPDF | 18.5 MB |
| Dompdf | 16.0 MB |

## PDF Parsing Performance

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| **phpdftk** | 457 us | 1.1 ms | 3.6 ms |
| smalot/pdfparser | 846 us | 1.5 ms | 2.7 ms |
| setasign/fpdi | 901 us | 3.6 ms | 21.9 ms |

## What's being measured

- **Generation**: create N pages with text content, serialize to PDF bytes
- **Memory**: peak `memory_get_peak_usage(true)` during generation
- **Parsing**: open PDF, extract catalog/info/version, iterate all pages

## Spec compliance advantage

phpdftk handles both classic xref tables (20-byte entries per ISO 32000-2 SS7.5.4) and cross-reference streams (PDF 1.5+). Many competitors fail on one or both formats.

## Running benchmarks yourself

```bash
# Full benchmark suite (generates docs/benchmarks.md)
scripts/benchmark

# Single benchmark class
vendor/phpbench/phpbench/phpbench run benchmarks/GeneratePdfBench.php --report=default
```
