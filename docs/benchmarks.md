# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-03-26 03:15:26 UTC
PHP: 8.4.19
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 847.787μs | 607.529μs | 680.476μs | 1.138ms | 1.760ms |
| FPDF | 422.341μs | 449.005μs | 494.967μs | 800.354μs | 1.205ms |
| TCPDF | 6.291ms | 6.766ms | 7.253ms | 11.611ms | 17.448ms |
| mPDF | 13.487ms | 13.964ms | 20.683ms | 37.410ms | 54.206ms |
| Dompdf | 5.734ms | 7.712ms | 10.369ms | 37.889ms | 85.115ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 4.926mb | 4.669mb | 4.669mb | 4.807mb | 5.063mb |
| FPDF | 4.803mb | 4.803mb | 4.803mb | 4.848mb | 4.905mb |
| TCPDF | 12.856mb | 12.856mb | 12.856mb | 12.856mb | 12.856mb |
| mPDF | 17.548mb | 17.690mb | 17.728mb | 18.021mb | 18.383mb |
| Dompdf | 9.266mb | 9.486mb | 9.807mb | 12.500mb | 15.863mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 1.095ms | 853.181μs | 908.973μs | 1.409ms | 2.206ms |
| FPDF | 557.246μs | 619.697μs | 662.491μs | 1.049ms | 1.494ms |
| TCPDF | 8.316ms | 8.905ms | 9.580ms | 14.706ms | 21.262ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 4.568mb | 4.568mb | 4.568mb | 4.738mb | 5.033mb |
| FPDF | 4.700mb | 4.700mb | 4.700mb | 4.753mb | 4.813mb |
| TCPDF | 12.753mb | 12.754mb | 12.754mb | 12.754mb | 12.754mb |

---

## Raw phpbench Output

```
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                        | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page                              |     | 2    | 3   | 4.568mb  | 1.095ms   | ±14.20% |
| MemoryBench      | benchPhpdftk5Pages                             |     | 2    | 3   | 4.568mb  | 853.181μs | ±0.75%  |
| MemoryBench      | benchPhpdftk10Pages                            |     | 2    | 3   | 4.568mb  | 908.973μs | ±2.89%  |
| MemoryBench      | benchPhpdftk50Pages                            |     | 2    | 3   | 4.738mb  | 1.409ms   | ±3.64%  |
| MemoryBench      | benchPhpdftk100Pages                           |     | 2    | 3   | 5.033mb  | 2.206ms   | ±2.37%  |
| MemoryBench      | benchTcpdf1Page                                |     | 2    | 3   | 12.753mb | 8.316ms   | ±5.68%  |
| MemoryBench      | benchTcpdf5Pages                               |     | 2    | 3   | 12.754mb | 8.905ms   | ±1.34%  |
| MemoryBench      | benchTcpdf10Pages                              |     | 2    | 3   | 12.754mb | 9.580ms   | ±0.80%  |
| MemoryBench      | benchTcpdf50Pages                              |     | 2    | 3   | 12.754mb | 14.706ms  | ±0.38%  |
| MemoryBench      | benchTcpdf100Pages                             |     | 2    | 3   | 12.754mb | 21.262ms  | ±1.06%  |
| MemoryBench      | benchFpdf1Page                                 |     | 2    | 3   | 4.700mb  | 557.246μs | ±18.14% |
| MemoryBench      | benchFpdf5Pages                                |     | 2    | 3   | 4.700mb  | 619.697μs | ±5.46%  |
| MemoryBench      | benchFpdf10Pages                               |     | 2    | 3   | 4.700mb  | 662.491μs | ±2.66%  |
| MemoryBench      | benchFpdf50Pages                               |     | 2    | 3   | 4.753mb  | 1.049ms   | ±1.95%  |
| MemoryBench      | benchFpdf100Pages                              |     | 2    | 3   | 4.813mb  | 1.494ms   | ±1.50%  |
| GeneratePdfBench | benchPhpdftk1Page                              |     | 3    | 5   | 4.670mb  | 536.774μs | ±2.71%  |
| GeneratePdfBench | benchPhpdftk5Pages                             |     | 3    | 5   | 4.669mb  | 607.529μs | ±7.64%  |
| GeneratePdfBench | benchPhpdftk10Pages                            |     | 3    | 5   | 4.669mb  | 680.476μs | ±1.48%  |
| GeneratePdfBench | benchPhpdftk50Pages                            |     | 3    | 5   | 4.807mb  | 1.138ms   | ±1.90%  |
| GeneratePdfBench | benchPhpdftk100Pages                           |     | 3    | 5   | 5.063mb  | 1.760ms   | ±1.62%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions |     | 3    | 5   | 4.680mb  | 750.413μs | ±11.21% |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations             |     | 3    | 5   | 4.949mb  | 972.630μs | ±8.34%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont            |     | 3    | 5   | 7.054mb  | 1.540ms   | ±8.53%  |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure       |     | 3    | 5   | 4.926mb  | 847.787μs | ±17.07% |
| GeneratePdfBench | benchTcpdf1Page                                |     | 3    | 5   | 12.856mb | 6.291ms   | ±1.21%  |
| GeneratePdfBench | benchTcpdf5Pages                               |     | 3    | 5   | 12.856mb | 6.766ms   | ±1.44%  |
| GeneratePdfBench | benchTcpdf10Pages                              |     | 3    | 5   | 12.856mb | 7.253ms   | ±1.51%  |
| GeneratePdfBench | benchTcpdf50Pages                              |     | 3    | 5   | 12.856mb | 11.611ms  | ±0.69%  |
| GeneratePdfBench | benchTcpdf100Pages                             |     | 3    | 5   | 12.856mb | 17.448ms  | ±1.13%  |
| GeneratePdfBench | benchFpdf1Page                                 |     | 3    | 5   | 4.803mb  | 422.341μs | ±42.84% |
| GeneratePdfBench | benchFpdf5Pages                                |     | 3    | 5   | 4.803mb  | 449.005μs | ±23.53% |
| GeneratePdfBench | benchFpdf10Pages                               |     | 3    | 5   | 4.803mb  | 494.967μs | ±11.09% |
| GeneratePdfBench | benchFpdf50Pages                               |     | 3    | 5   | 4.848mb  | 800.354μs | ±21.15% |
| GeneratePdfBench | benchFpdf100Pages                              |     | 3    | 5   | 4.905mb  | 1.205ms   | ±6.55%  |
| GeneratePdfBench | benchMpdf1Page                                 |     | 3    | 5   | 17.548mb | 13.487ms  | ±12.01% |
| GeneratePdfBench | benchMpdf5Pages                                |     | 3    | 5   | 17.690mb | 13.964ms  | ±1.52%  |
| GeneratePdfBench | benchMpdf10Pages                               |     | 3    | 5   | 17.728mb | 20.683ms  | ±9.78%  |
| GeneratePdfBench | benchMpdf50Pages                               |     | 3    | 5   | 18.021mb | 37.410ms  | ±5.60%  |
| GeneratePdfBench | benchMpdf100Pages                              |     | 3    | 5   | 18.383mb | 54.206ms  | ±2.53%  |
| GeneratePdfBench | benchDompdf1Page                               |     | 3    | 5   | 9.266mb  | 5.734ms   | ±18.58% |
| GeneratePdfBench | benchDompdf5Pages                              |     | 3    | 5   | 9.486mb  | 7.712ms   | ±3.27%  |
| GeneratePdfBench | benchDompdf10Pages                             |     | 3    | 5   | 9.807mb  | 10.369ms  | ±0.63%  |
| GeneratePdfBench | benchDompdf50Pages                             |     | 3    | 5   | 12.500mb | 37.889ms  | ±3.77%  |
| GeneratePdfBench | benchDompdf100Pages                            |     | 3    | 5   | 15.863mb | 85.115ms  | ±5.54%  |
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+

```