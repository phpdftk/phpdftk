# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-03-17 22:27:47 UTC
PHP: 8.4.8
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.500ms | 1.892ms | 1.845ms | 3.534ms | 5.774ms |
| FPDF | 1.347ms | 1.739ms | 1.656ms | 2.340ms | 3.836ms |
| TCPDF | 15.585ms | 17.413ms | 19.116ms | 31.737ms | 48.565ms |
| mPDF | 39.245ms | 44.128ms | 50.414ms | 104.150ms | 166.399ms |
| Dompdf | 18.403ms | 22.980ms | 32.918ms | 134.986ms | 356.157ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 4.593mb | 4.582mb | 4.582mb | 4.707mb | 4.951mb |
| FPDF | 4.754mb | 4.754mb | 4.754mb | 4.799mb | 4.856mb |
| TCPDF | 12.807mb | 12.807mb | 12.807mb | 12.807mb | 12.807mb |
| mPDF | 17.499mb | 17.641mb | 17.679mb | 17.972mb | 18.334mb |
| Dompdf | 9.216mb | 9.436mb | 9.757mb | 12.450mb | 15.813mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.368ms | 2.503ms | 3.217ms | 4.711ms | 7.246ms |
| FPDF | 1.540ms | 1.981ms | 2.005ms | 3.711ms | 4.288ms |
| TCPDF | 29.352ms | 26.057ms | 28.201ms | 41.748ms | 59.109ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 4.522mb | 4.522mb | 4.522mb | 4.678mb | 4.961mb |
| FPDF | 4.693mb | 4.693mb | 4.693mb | 4.745mb | 4.806mb |
| TCPDF | 12.746mb | 12.746mb | 12.746mb | 12.746mb | 12.746mb |

---

## Raw phpbench Output

```
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                        | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page                              |     | 2    | 3   | 4.522mb  | 2.368ms   | ±20.71% |
| MemoryBench      | benchPhpdftk5Pages                             |     | 2    | 3   | 4.522mb  | 2.503ms   | ±10.98% |
| MemoryBench      | benchPhpdftk10Pages                            |     | 2    | 3   | 4.522mb  | 3.217ms   | ±18.34% |
| MemoryBench      | benchPhpdftk50Pages                            |     | 2    | 3   | 4.678mb  | 4.711ms   | ±5.69%  |
| MemoryBench      | benchPhpdftk100Pages                           |     | 2    | 3   | 4.961mb  | 7.246ms   | ±6.96%  |
| MemoryBench      | benchTcpdf1Page                                |     | 2    | 3   | 12.746mb | 29.352ms  | ±10.30% |
| MemoryBench      | benchTcpdf5Pages                               |     | 2    | 3   | 12.746mb | 26.057ms  | ±6.54%  |
| MemoryBench      | benchTcpdf10Pages                              |     | 2    | 3   | 12.746mb | 28.201ms  | ±4.24%  |
| MemoryBench      | benchTcpdf50Pages                              |     | 2    | 3   | 12.746mb | 41.748ms  | ±2.79%  |
| MemoryBench      | benchTcpdf100Pages                             |     | 2    | 3   | 12.746mb | 59.109ms  | ±0.96%  |
| MemoryBench      | benchFpdf1Page                                 |     | 2    | 3   | 4.693mb  | 1.540ms   | ±23.00% |
| MemoryBench      | benchFpdf5Pages                                |     | 2    | 3   | 4.693mb  | 1.981ms   | ±9.68%  |
| MemoryBench      | benchFpdf10Pages                               |     | 2    | 3   | 4.693mb  | 2.005ms   | ±6.17%  |
| MemoryBench      | benchFpdf50Pages                               |     | 2    | 3   | 4.745mb  | 3.711ms   | ±13.39% |
| MemoryBench      | benchFpdf100Pages                              |     | 2    | 3   | 4.806mb  | 4.288ms   | ±3.12%  |
| GeneratePdfBench | benchPhpdftk1Page                              |     | 3    | 5   | 4.583mb  | 1.968ms   | ±15.06% |
| GeneratePdfBench | benchPhpdftk5Pages                             |     | 3    | 5   | 4.582mb  | 1.892ms   | ±8.24%  |
| GeneratePdfBench | benchPhpdftk10Pages                            |     | 3    | 5   | 4.582mb  | 1.845ms   | ±8.91%  |
| GeneratePdfBench | benchPhpdftk50Pages                            |     | 3    | 5   | 4.707mb  | 3.534ms   | ±6.62%  |
| GeneratePdfBench | benchPhpdftk100Pages                           |     | 3    | 5   | 4.951mb  | 5.774ms   | ±14.37% |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions |     | 3    | 5   | 4.593mb  | 2.500ms   | ±18.06% |
| GeneratePdfBench | benchTcpdf1Page                                |     | 3    | 5   | 12.807mb | 15.585ms  | ±5.25%  |
| GeneratePdfBench | benchTcpdf5Pages                               |     | 3    | 5   | 12.807mb | 17.413ms  | ±1.73%  |
| GeneratePdfBench | benchTcpdf10Pages                              |     | 3    | 5   | 12.807mb | 19.116ms  | ±2.88%  |
| GeneratePdfBench | benchTcpdf50Pages                              |     | 3    | 5   | 12.807mb | 31.737ms  | ±2.37%  |
| GeneratePdfBench | benchTcpdf100Pages                             |     | 3    | 5   | 12.807mb | 48.565ms  | ±2.51%  |
| GeneratePdfBench | benchFpdf1Page                                 |     | 3    | 5   | 4.754mb  | 1.347ms   | ±11.27% |
| GeneratePdfBench | benchFpdf5Pages                                |     | 3    | 5   | 4.754mb  | 1.739ms   | ±14.67% |
| GeneratePdfBench | benchFpdf10Pages                               |     | 3    | 5   | 4.754mb  | 1.656ms   | ±12.16% |
| GeneratePdfBench | benchFpdf50Pages                               |     | 3    | 5   | 4.799mb  | 2.340ms   | ±7.78%  |
| GeneratePdfBench | benchFpdf100Pages                              |     | 3    | 5   | 4.856mb  | 3.836ms   | ±4.04%  |
| GeneratePdfBench | benchMpdf1Page                                 |     | 3    | 5   | 17.499mb | 39.245ms  | ±18.07% |
| GeneratePdfBench | benchMpdf5Pages                                |     | 3    | 5   | 17.641mb | 44.128ms  | ±5.68%  |
| GeneratePdfBench | benchMpdf10Pages                               |     | 3    | 5   | 17.679mb | 50.414ms  | ±2.84%  |
| GeneratePdfBench | benchMpdf50Pages                               |     | 3    | 5   | 17.972mb | 104.150ms | ±2.86%  |
| GeneratePdfBench | benchMpdf100Pages                              |     | 3    | 5   | 18.334mb | 166.399ms | ±2.50%  |
| GeneratePdfBench | benchDompdf1Page                               |     | 3    | 5   | 9.216mb  | 18.403ms  | ±24.21% |
| GeneratePdfBench | benchDompdf5Pages                              |     | 3    | 5   | 9.436mb  | 22.980ms  | ±3.54%  |
| GeneratePdfBench | benchDompdf10Pages                             |     | 3    | 5   | 9.757mb  | 32.918ms  | ±2.44%  |
| GeneratePdfBench | benchDompdf50Pages                             |     | 3    | 5   | 12.450mb | 134.986ms | ±26.32% |
| GeneratePdfBench | benchDompdf100Pages                            |     | 3    | 5   | 15.813mb | 356.157ms | ±10.69% |
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+

```