# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-03-17 06:25:29 UTC
PHP: 8.4.8
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 11.539ms | — | 17.013ms | — | 48.649ms |
| FPDF | 8.869ms | — | 6.457ms | — | 8.999ms |
| TCPDF | 125.204ms | — | 150.069ms | — | 626.189ms |
| mPDF | 171.969ms | — | 386.569ms | — | 2.098s |
| Dompdf | 117.008ms | — | 249.144ms | — | 2.202s |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 4.086mb | — | 4.086mb | — | 4.425mb |
| FPDF | 4.283mb | — | 4.283mb | — | 4.362mb |
| TCPDF | 12.365mb | — | 12.365mb | — | 12.365mb |
| mPDF | 16.994mb | — | 17.174mb | — | 17.828mb |
| Dompdf | 8.810mb | — | 9.351mb | — | 15.406mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 16.613ms | 29.009ms | 45.003ms | 109.986ms | 109.304ms |
| FPDF | 12.565ms | 14.213ms | 6.016ms | 12.664ms | 14.153ms |
| TCPDF | 469.177ms | 360.026ms | 173.699ms | 135.825ms | 336.324ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 4.086mb | 4.086mb | 4.086mb | 4.210mb | 4.471mb |
| FPDF | 4.260mb | 4.260mb | 4.260mb | 4.289mb | 4.350mb |
| TCPDF | 12.342mb | 12.342mb | 12.342mb | 12.342mb | 12.342mb |

---

## Raw phpbench Output

```
+------------------+----------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject              | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+----------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page    |     | 2    | 3   | 4.086mb  | 16.613ms  | ±46.90% |
| MemoryBench      | benchPhpdftk5Pages   |     | 2    | 3   | 4.086mb  | 29.009ms  | ±62.80% |
| MemoryBench      | benchPhpdftk10Pages  |     | 2    | 3   | 4.086mb  | 45.003ms  | ±70.45% |
| MemoryBench      | benchPhpdftk50Pages  |     | 2    | 3   | 4.210mb  | 109.986ms | ±58.77% |
| MemoryBench      | benchPhpdftk100Pages |     | 2    | 3   | 4.471mb  | 109.304ms | ±13.25% |
| MemoryBench      | benchTcpdf1Page      |     | 2    | 3   | 12.342mb | 469.177ms | ±22.05% |
| MemoryBench      | benchTcpdf5Pages     |     | 2    | 3   | 12.342mb | 360.026ms | ±36.56% |
| MemoryBench      | benchTcpdf10Pages    |     | 2    | 3   | 12.342mb | 173.699ms | ±18.62% |
| MemoryBench      | benchTcpdf50Pages    |     | 2    | 3   | 12.342mb | 135.825ms | ±14.48% |
| MemoryBench      | benchTcpdf100Pages   |     | 2    | 3   | 12.342mb | 336.324ms | ±39.56% |
| MemoryBench      | benchFpdf1Page       |     | 2    | 3   | 4.260mb  | 12.565ms  | ±12.65% |
| MemoryBench      | benchFpdf5Pages      |     | 2    | 3   | 4.260mb  | 14.213ms  | ±86.25% |
| MemoryBench      | benchFpdf10Pages     |     | 2    | 3   | 4.260mb  | 6.016ms   | ±12.82% |
| MemoryBench      | benchFpdf50Pages     |     | 2    | 3   | 4.289mb  | 12.664ms  | ±47.78% |
| MemoryBench      | benchFpdf100Pages    |     | 2    | 3   | 4.350mb  | 14.153ms  | ±12.53% |
| GeneratePdfBench | benchPhpdftk1Page    |     | 3    | 5   | 4.086mb  | 11.539ms  | ±32.34% |
| GeneratePdfBench | benchPhpdftk10Pages  |     | 3    | 5   | 4.086mb  | 17.013ms  | ±45.30% |
| GeneratePdfBench | benchPhpdftk100Pages |     | 3    | 5   | 4.425mb  | 48.649ms  | ±57.06% |
| GeneratePdfBench | benchTcpdf1Page      |     | 3    | 5   | 12.365mb | 125.204ms | ±32.71% |
| GeneratePdfBench | benchTcpdf10Pages    |     | 3    | 5   | 12.365mb | 150.069ms | ±18.76% |
| GeneratePdfBench | benchTcpdf100Pages   |     | 3    | 5   | 12.365mb | 626.189ms | ±16.72% |
| GeneratePdfBench | benchFpdf1Page       |     | 3    | 5   | 4.283mb  | 8.869ms   | ±58.10% |
| GeneratePdfBench | benchFpdf10Pages     |     | 3    | 5   | 4.283mb  | 6.457ms   | ±91.77% |
| GeneratePdfBench | benchFpdf100Pages    |     | 3    | 5   | 4.362mb  | 8.999ms   | ±17.27% |
| GeneratePdfBench | benchMpdf1Page       |     | 3    | 5   | 16.994mb | 171.969ms | ±95.68% |
| GeneratePdfBench | benchMpdf10Pages     |     | 3    | 5   | 17.174mb | 386.569ms | ±25.53% |
| GeneratePdfBench | benchMpdf100Pages    |     | 3    | 5   | 17.828mb | 2.098s    | ±40.54% |
| GeneratePdfBench | benchDompdf1Page     |     | 3    | 5   | 8.810mb  | 117.008ms | ±52.71% |
| GeneratePdfBench | benchDompdf10Pages   |     | 3    | 5   | 9.351mb  | 249.144ms | ±49.11% |
| GeneratePdfBench | benchDompdf100Pages  |     | 3    | 5   | 15.406mb | 2.202s    | ±20.61% |
+------------------+----------------------+-----+------+-----+----------+-----------+---------+

```