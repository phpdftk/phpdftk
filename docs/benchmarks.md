# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-04-14 01:32:12 UTC
PHP: 8.4.19
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 972.508μs | 633.570μs | 731.146μs | 1.205ms | 1.811ms |
| FPDF | 410.878μs | 457.730μs | 488.648μs | 791.339μs | 1.179ms |
| TCPDF | 4.826ms | 5.303ms | 5.761ms | 10.192ms | 15.626ms |
| mPDF | 11.799ms | 13.922ms | 15.874ms | 32.442ms | 53.343ms |
| Dompdf | 5.236ms | 7.527ms | 10.329ms | 37.405ms | 87.957ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.087mb | 4.968mb | 4.992mb | 5.174mb | 5.430mb |
| FPDF | 4.902mb | 4.902mb | 4.902mb | 4.947mb | 5.005mb |
| TCPDF | 12.955mb | 12.955mb | 12.955mb | 12.955mb | 12.955mb |
| mPDF | 17.730mb | 17.789mb | 17.828mb | 18.120mb | 18.482mb |
| Dompdf | 9.365mb | 9.585mb | 9.906mb | 12.599mb | 15.962mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 889.779μs | 883.027μs | 939.442μs | 1.496ms | 2.205ms |
| FPDF | 535.091μs | 581.422μs | 638.506μs | 1.013ms | 1.427ms |
| TCPDF | 6.726ms | 7.253ms | 12.289ms | 13.752ms | 19.545ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 4.611mb | 4.611mb | 4.611mb | 4.785mb | 5.081mb |
| FPDF | 4.708mb | 4.708mb | 4.708mb | 4.761mb | 4.821mb |
| TCPDF | 12.761mb | 12.762mb | 12.762mb | 12.762mb | 12.762mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 456.874μs | 729.204μs | 3.578ms |
| smalot/pdfparser | 845.975μs | 1.039ms | 2.737ms |
| setasign/fpdi | 901.456μs | 1.458ms | 21.857ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.468mb | 4.468mb | 4.468mb |
| smalot/pdfparser | 5.095mb | 5.178mb | 6.870mb |
| setasign/fpdi | 5.015mb | 5.042mb | 5.798mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 982.235μs | 527.973μs |
| smalot/pdfparser | FAIL | 982.106μs |
| setasign/fpdi | 1.571ms | FAIL |

---

## Raw phpbench Output

```
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                        | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page                              |     | 2    | 3   | 4.611mb  | 889.779μs | ±58.96% |
| MemoryBench      | benchPhpdftk5Pages                             |     | 2    | 3   | 4.611mb  | 883.027μs | ±2.17%  |
| MemoryBench      | benchPhpdftk10Pages                            |     | 2    | 3   | 4.611mb  | 939.442μs | ±4.11%  |
| MemoryBench      | benchPhpdftk50Pages                            |     | 2    | 3   | 4.785mb  | 1.496ms   | ±0.64%  |
| MemoryBench      | benchPhpdftk100Pages                           |     | 2    | 3   | 5.081mb  | 2.205ms   | ±0.32%  |
| MemoryBench      | benchTcpdf1Page                                |     | 2    | 3   | 12.761mb | 6.726ms   | ±12.19% |
| MemoryBench      | benchTcpdf5Pages                               |     | 2    | 3   | 12.762mb | 7.253ms   | ±0.48%  |
| MemoryBench      | benchTcpdf10Pages                              |     | 2    | 3   | 12.762mb | 12.289ms  | ±15.93% |
| MemoryBench      | benchTcpdf50Pages                              |     | 2    | 3   | 12.762mb | 13.752ms  | ±7.24%  |
| MemoryBench      | benchTcpdf100Pages                             |     | 2    | 3   | 12.762mb | 19.545ms  | ±2.51%  |
| MemoryBench      | benchFpdf1Page                                 |     | 2    | 3   | 4.708mb  | 535.091μs | ±24.43% |
| MemoryBench      | benchFpdf5Pages                                |     | 2    | 3   | 4.708mb  | 581.422μs | ±2.73%  |
| MemoryBench      | benchFpdf10Pages                               |     | 2    | 3   | 4.708mb  | 638.506μs | ±3.82%  |
| MemoryBench      | benchFpdf50Pages                               |     | 2    | 3   | 4.761mb  | 1.013ms   | ±1.38%  |
| MemoryBench      | benchFpdf100Pages                              |     | 2    | 3   | 4.821mb  | 1.427ms   | ±0.77%  |
| ReadPdfBench     | benchPhpdftk1Page                              |     | 3    | 5   | 4.468mb  | 456.874μs | ±38.66% |
| ReadPdfBench     | benchPhpdftk10Pages                            |     | 3    | 5   | 4.468mb  | 729.204μs | ±5.08%  |
| ReadPdfBench     | benchPhpdftk100Pages                           |     | 3    | 5   | 4.468mb  | 3.578ms   | ±1.82%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                  |     | 3    | 5   | 4.468mb  | 982.235μs | ±4.09%  |
| ReadPdfBench     | benchPhpdftkXrefStream                         |     | 3    | 5   | 4.468mb  | 527.973μs | ±13.39% |
| ReadPdfBench     | benchSmalot1Page                               |     | 3    | 5   | 5.095mb  | 845.975μs | ±35.21% |
| ReadPdfBench     | benchSmalot10Pages                             |     | 3    | 5   | 5.178mb  | 1.039ms   | ±2.28%  |
| ReadPdfBench     | benchSmalot100Pages                            |     | 3    | 5   | 6.870mb  | 2.737ms   | ±3.01%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                   |     | 3    | 5   | 4.468mb  | 255.962μs | ±39.28% |
| ReadPdfBench     | benchSmalotXrefStream                          |     | 3    | 5   | 5.088mb  | 982.106μs | ±26.42% |
| ReadPdfBench     | benchFpdi1Page                                 |     | 3    | 5   | 5.015mb  | 901.456μs | ±34.02% |
| ReadPdfBench     | benchFpdi10Pages                               |     | 3    | 5   | 5.042mb  | 1.458ms   | ±2.29%  |
| ReadPdfBench     | benchFpdi100Pages                              |     | 3    | 5   | 5.798mb  | 21.857ms  | ±1.50%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                     |     | 3    | 5   | 5.147mb  | 1.571ms   | ±10.40% |
| ReadPdfBench     | benchFpdiXrefStream                            |     | 3    | 5   | 5.060mb  | 693.729μs | ±5.94%  |
| GeneratePdfBench | benchPhpdftk1Page                              |     | 3    | 5   | 4.966mb  | 653.904μs | ±35.77% |
| GeneratePdfBench | benchPhpdftk5Pages                             |     | 3    | 5   | 4.968mb  | 633.570μs | ±5.82%  |
| GeneratePdfBench | benchPhpdftk10Pages                            |     | 3    | 5   | 4.992mb  | 731.146μs | ±3.35%  |
| GeneratePdfBench | benchPhpdftk50Pages                            |     | 3    | 5   | 5.174mb  | 1.205ms   | ±1.61%  |
| GeneratePdfBench | benchPhpdftk100Pages                           |     | 3    | 5   | 5.430mb  | 1.811ms   | ±1.30%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions |     | 3    | 5   | 5.031mb  | 812.654μs | ±8.57%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations             |     | 3    | 5   | 5.135mb  | 1.031ms   | ±14.35% |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont            |     | 3    | 5   | 7.422mb  | 1.840ms   | ±12.33% |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure       |     | 3    | 5   | 5.062mb  | 951.658μs | ±47.18% |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font               |     | 3    | 5   | 4.867mb  | 615.783μs | ±11.07% |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams               |     | 3    | 5   | 4.468mb  | 279.464μs | ±27.68% |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns     |     | 3    | 5   | 5.083mb  | 926.059μs | ±21.26% |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D         |     | 3    | 5   | 5.110mb  | 973.457μs | ±21.50% |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField          |     | 3    | 5   | 5.073mb  | 864.274μs | ±19.62% |
| GeneratePdfBench | benchPhpdftk10PagesSigned                      |     | 3    | 5   | 5.220mb  | 33.466ms  | ±26.70% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations       |     | 3    | 5   | 5.087mb  | 972.508μs | ±10.61% |
| GeneratePdfBench | benchTcpdf1Page                                |     | 3    | 5   | 12.955mb | 4.826ms   | ±1.39%  |
| GeneratePdfBench | benchTcpdf5Pages                               |     | 3    | 5   | 12.955mb | 5.303ms   | ±1.17%  |
| GeneratePdfBench | benchTcpdf10Pages                              |     | 3    | 5   | 12.955mb | 5.761ms   | ±1.53%  |
| GeneratePdfBench | benchTcpdf50Pages                              |     | 3    | 5   | 12.955mb | 10.192ms  | ±0.66%  |
| GeneratePdfBench | benchTcpdf100Pages                             |     | 3    | 5   | 12.955mb | 15.626ms  | ±0.80%  |
| GeneratePdfBench | benchFpdf1Page                                 |     | 3    | 5   | 4.902mb  | 410.878μs | ±2.41%  |
| GeneratePdfBench | benchFpdf5Pages                                |     | 3    | 5   | 4.902mb  | 457.730μs | ±5.33%  |
| GeneratePdfBench | benchFpdf10Pages                               |     | 3    | 5   | 4.902mb  | 488.648μs | ±2.70%  |
| GeneratePdfBench | benchFpdf50Pages                               |     | 3    | 5   | 4.947mb  | 791.339μs | ±0.91%  |
| GeneratePdfBench | benchFpdf100Pages                              |     | 3    | 5   | 5.005mb  | 1.179ms   | ±1.49%  |
| GeneratePdfBench | benchMpdf1Page                                 |     | 3    | 5   | 17.730mb | 11.799ms  | ±16.40% |
| GeneratePdfBench | benchMpdf5Pages                                |     | 3    | 5   | 17.789mb | 13.922ms  | ±3.28%  |
| GeneratePdfBench | benchMpdf10Pages                               |     | 3    | 5   | 17.828mb | 15.874ms  | ±0.58%  |
| GeneratePdfBench | benchMpdf50Pages                               |     | 3    | 5   | 18.120mb | 32.442ms  | ±0.44%  |
| GeneratePdfBench | benchMpdf100Pages                              |     | 3    | 5   | 18.482mb | 53.343ms  | ±0.32%  |
| GeneratePdfBench | benchDompdf1Page                               |     | 3    | 5   | 9.365mb  | 5.236ms   | ±21.72% |
| GeneratePdfBench | benchDompdf5Pages                              |     | 3    | 5   | 9.585mb  | 7.527ms   | ±0.63%  |
| GeneratePdfBench | benchDompdf10Pages                             |     | 3    | 5   | 9.906mb  | 10.329ms  | ±1.01%  |
| GeneratePdfBench | benchDompdf50Pages                             |     | 3    | 5   | 12.599mb | 37.405ms  | ±23.26% |
| GeneratePdfBench | benchDompdf100Pages                            |     | 3    | 5   | 15.962mb | 87.957ms  | ±6.59%  |
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+

```