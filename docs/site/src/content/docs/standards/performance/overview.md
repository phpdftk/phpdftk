---
title: Performance
description: Methodology and framing for phpdftk's benchmark numbers — how we measure, what we measure, and why we're fast.
---

For the latest measured numbers from `main`, see [Latest Benchmarks](/standards/performance/benchmarks/). This page covers *what* we measure and *why* phpdftk is fast at it.

All benchmarks run via PHPBench on the same machine, with no opcache and no Xdebug. phpdftk generates spec-compliant PDFs with proper xref tables, binary comments, and typed trailers — every measurement is on a real, valid PDF.

## What's being measured

- **Generation** — create N pages with text content and serialize to PDF bytes. Exercises the writer end to end (page tree, content streams, font registration, xref emission).
- **Peak memory** — `memory_get_peak_usage(true)` during generation. Captures the writer's resident object graph.
- **Parsing** — open a PDF, extract catalog + info + version, iterate every page. Exercises xref resolution, dictionary parsing, and stream decoding.
- **Reader compatibility** — the same parsing benchmark run against PDFs that use spec-compliant xref tables and cross-reference streams. Some competitors fail outright on one or both formats.

## Conformance validation overhead

Running ISO conformance validation during PDF generation (10 pages, embedded TrueType font) adds roughly **25–35 ms** for most profiles. PDF/R-1 is an outlier on the cheap end at roughly **4 ms**, because it doesn't require font embedding.

The cost is dominated by font embedding and OutputIntent setup — *not* by the constraint checks themselves. The validators are linear scans over an already-built object graph; the expensive work happens in the writer pipeline regardless. See [ISO Standards](/standards/profiles/iso-standards/) for what each profile enforces.

## Spec compliance advantage

phpdftk's reader handles both classic xref tables (20-byte entries per ISO 32000-2 §7.5.4) and cross-reference streams (PDF 1.5+). Many PHP PDF libraries throw on one format or the other; phpdftk treats both as primary. This shows up as `FAIL` cells in the compatibility table on the [benchmarks page](/standards/performance/benchmarks/) when other libraries hit input they can't parse.

## Running benchmarks yourself

```bash
# Full benchmark suite (writes docs/generated/benchmarks.md)
composer benchmark

# Or via mise:
mise run benchmark

# Single benchmark class
vendor/phpbench/phpbench/phpbench run benchmarks/GeneratePdfBench.php --report=default
```

The repo-local report at `docs/generated/benchmarks.md` is what CI publishes to the `_benchmarks` orphan branch and embeds into the [Latest Benchmarks](/standards/performance/benchmarks/) page on every push to `main`.
