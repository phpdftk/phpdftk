# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-04-23 04:16:11 UTC
PHP: 8.4.19
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 59.136ms | 1.051ms | 1.200ms | 2.189ms | 3.491ms |
| FPDF | 432.429μs | 459.230μs | 541.491μs | 832.847μs | 1.216ms |
| TCPDF | 5.376ms | 5.739ms | 6.288ms | 10.713ms | 16.206ms |
| mPDF | 12.362ms | 14.743ms | 16.428ms | 33.004ms | 54.429ms |
| Dompdf | 5.431ms | 7.678ms | 10.444ms | 39.515ms | 88.040ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 4.607mb | 5.828mb | 5.914mb | 6.544mb | 7.362mb |
| FPDF | 5.281mb | 5.281mb | 5.281mb | 5.281mb | 5.281mb |
| TCPDF | 13.046mb | 13.046mb | 13.046mb | 13.046mb | 13.046mb |
| mPDF | 17.803mb | 17.880mb | 17.918mb | 18.211mb | 18.573mb |
| Dompdf | 9.456mb | 9.676mb | 9.997mb | 12.690mb | 16.053mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 1.451ms | 1.460ms | 1.644ms | 2.902ms | 4.904ms |
| FPDF | 584.822μs | 591.043μs | 655.671μs | 1.030ms | 1.478ms |
| TCPDF | 8.169ms | 8.550ms | 9.448ms | 14.134ms | 20.187ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.461mb | 5.507mb | 5.565mb | 6.056mb | 6.651mb |
| FPDF | 4.708mb | 4.708mb | 4.709mb | 4.761mb | 4.822mb |
| TCPDF | 12.762mb | 12.762mb | 12.762mb | 12.762mb | 12.762mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 8.477ms | 866.934μs | 3.832ms |
| smalot/pdfparser | 871.384μs | 1.080ms | 2.869ms |
| setasign/fpdi | 952.834μs | 1.493ms | 21.526ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.371mb | 4.469mb | 4.858mb |
| smalot/pdfparser | 4.971mb | 5.109mb | 6.893mb |
| setasign/fpdi | 5.038mb | 5.064mb | 5.821mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 1.149ms | 634.110μs |
| smalot/pdfparser | FAIL | 898.458μs |
| setasign/fpdi | 1.595ms | FAIL |

---

## Raw phpbench Output

```
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                        | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page                              |     | 2    | 3   | 5.461mb  | 1.451ms   | ±41.98% |
| MemoryBench      | benchPhpdftk5Pages                             |     | 2    | 3   | 5.507mb  | 1.460ms   | ±1.82%  |
| MemoryBench      | benchPhpdftk10Pages                            |     | 2    | 3   | 5.565mb  | 1.644ms   | ±13.86% |
| MemoryBench      | benchPhpdftk50Pages                            |     | 2    | 3   | 6.056mb  | 2.902ms   | ±10.36% |
| MemoryBench      | benchPhpdftk100Pages                           |     | 2    | 3   | 6.651mb  | 4.904ms   | ±14.63% |
| MemoryBench      | benchTcpdf1Page                                |     | 2    | 3   | 12.762mb | 8.169ms   | ±10.65% |
| MemoryBench      | benchTcpdf5Pages                               |     | 2    | 3   | 12.762mb | 8.550ms   | ±5.47%  |
| MemoryBench      | benchTcpdf10Pages                              |     | 2    | 3   | 12.762mb | 9.448ms   | ±1.02%  |
| MemoryBench      | benchTcpdf50Pages                              |     | 2    | 3   | 12.762mb | 14.134ms  | ±1.05%  |
| MemoryBench      | benchTcpdf100Pages                             |     | 2    | 3   | 12.762mb | 20.187ms  | ±0.24%  |
| MemoryBench      | benchFpdf1Page                                 |     | 2    | 3   | 4.708mb  | 584.822μs | ±16.45% |
| MemoryBench      | benchFpdf5Pages                                |     | 2    | 3   | 4.708mb  | 591.043μs | ±1.55%  |
| MemoryBench      | benchFpdf10Pages                               |     | 2    | 3   | 4.709mb  | 655.671μs | ±0.48%  |
| MemoryBench      | benchFpdf50Pages                               |     | 2    | 3   | 4.761mb  | 1.030ms   | ±1.33%  |
| MemoryBench      | benchFpdf100Pages                              |     | 2    | 3   | 4.822mb  | 1.478ms   | ±0.74%  |
| ReadPdfBench     | benchPhpdftk1Page                              |     | 3    | 5   | 4.469mb  | 601.157μs | ±28.67% |
| ReadPdfBench     | benchPhpdftk10Pages                            |     | 3    | 5   | 4.469mb  | 866.934μs | ±4.47%  |
| ReadPdfBench     | benchPhpdftk100Pages                           |     | 3    | 5   | 4.858mb  | 3.832ms   | ±1.89%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                  |     | 3    | 5   | 4.500mb  | 1.149ms   | ±1.94%  |
| ReadPdfBench     | benchPhpdftkXrefStream                         |     | 3    | 5   | 4.469mb  | 634.110μs | ±8.64%  |
| ReadPdfBench     | benchSmalot1Page                               |     | 3    | 5   | 4.971mb  | 871.384μs | ±31.36% |
| ReadPdfBench     | benchSmalot10Pages                             |     | 3    | 5   | 5.109mb  | 1.080ms   | ±3.52%  |
| ReadPdfBench     | benchSmalot100Pages                            |     | 3    | 5   | 6.893mb  | 2.869ms   | ±2.07%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                   |     | 3    | 5   | 4.469mb  | 237.971μs | ±1.02%  |
| ReadPdfBench     | benchSmalotXrefStream                          |     | 3    | 5   | 4.964mb  | 898.458μs | ±4.87%  |
| ReadPdfBench     | benchFpdi1Page                                 |     | 3    | 5   | 5.038mb  | 952.834μs | ±34.14% |
| ReadPdfBench     | benchFpdi10Pages                               |     | 3    | 5   | 5.064mb  | 1.493ms   | ±6.48%  |
| ReadPdfBench     | benchFpdi100Pages                              |     | 3    | 5   | 5.821mb  | 21.526ms  | ±1.55%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                     |     | 3    | 5   | 5.169mb  | 1.595ms   | ±8.55%  |
| ReadPdfBench     | benchFpdiXrefStream                            |     | 3    | 5   | 4.940mb  | 705.052μs | ±20.66% |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects     |     | 3    | 5   | 6.048mb  | 4.044ms   | ±4.24%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                      |     | 3    | 5   | 6.183mb  | 3.192ms   | ±5.89%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                       |     | 5    | 3   | 6.371mb  | 8.477ms   | ±1.28%  |
| GeneratePdfBench | benchPhpdftk1Page                              |     | 3    | 5   | 5.768mb  | 938.324μs | ±3.68%  |
| GeneratePdfBench | benchPhpdftk5Pages                             |     | 3    | 5   | 5.828mb  | 1.051ms   | ±2.35%  |
| GeneratePdfBench | benchPhpdftk10Pages                            |     | 3    | 5   | 5.914mb  | 1.200ms   | ±1.01%  |
| GeneratePdfBench | benchPhpdftk50Pages                            |     | 3    | 5   | 6.544mb  | 2.189ms   | ±1.41%  |
| GeneratePdfBench | benchPhpdftk100Pages                           |     | 3    | 5   | 7.362mb  | 3.491ms   | ±1.77%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions |     | 3    | 5   | 5.975mb  | 1.308ms   | ±3.56%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations             |     | 3    | 5   | 6.225mb  | 1.660ms   | ±6.96%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont            |     | 3    | 5   | 9.762mb  | 10.378ms  | ±1.46%  |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure       |     | 3    | 5   | 6.020mb  | 1.399ms   | ±11.76% |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font               |     | 3    | 5   | 5.705mb  | 1.049ms   | ±4.55%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams               |     | 3    | 5   | 4.607mb  | 317.207μs | ±19.90% |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns     |     | 3    | 5   | 5.988mb  | 1.307ms   | ±10.15% |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D         |     | 3    | 5   | 6.149mb  | 1.564ms   | ±10.92% |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField          |     | 3    | 5   | 6.002mb  | 1.544ms   | ±12.67% |
| GeneratePdfBench | benchPhpdftk10PagesSigned                      |     | 3    | 5   | 6.058mb  | 28.027ms  | ±33.28% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations       |     | 3    | 5   | 6.118mb  | 1.550ms   | ±3.98%  |
| GeneratePdfBench | benchTcpdf1Page                                |     | 3    | 5   | 13.046mb | 5.376ms   | ±1.94%  |
| GeneratePdfBench | benchTcpdf5Pages                               |     | 3    | 5   | 13.046mb | 5.739ms   | ±1.09%  |
| GeneratePdfBench | benchTcpdf10Pages                              |     | 3    | 5   | 13.046mb | 6.288ms   | ±0.94%  |
| GeneratePdfBench | benchTcpdf50Pages                              |     | 3    | 5   | 13.046mb | 10.713ms  | ±0.58%  |
| GeneratePdfBench | benchTcpdf100Pages                             |     | 3    | 5   | 13.046mb | 16.206ms  | ±0.93%  |
| GeneratePdfBench | benchFpdf1Page                                 |     | 3    | 5   | 5.281mb  | 432.429μs | ±14.79% |
| GeneratePdfBench | benchFpdf5Pages                                |     | 3    | 5   | 5.281mb  | 459.230μs | ±3.69%  |
| GeneratePdfBench | benchFpdf10Pages                               |     | 3    | 5   | 5.281mb  | 541.491μs | ±21.16% |
| GeneratePdfBench | benchFpdf50Pages                               |     | 3    | 5   | 5.281mb  | 832.847μs | ±5.02%  |
| GeneratePdfBench | benchFpdf100Pages                              |     | 3    | 5   | 5.281mb  | 1.216ms   | ±5.15%  |
| GeneratePdfBench | benchMpdf1Page                                 |     | 3    | 5   | 17.803mb | 12.362ms  | ±11.67% |
| GeneratePdfBench | benchMpdf5Pages                                |     | 3    | 5   | 17.880mb | 14.743ms  | ±6.47%  |
| GeneratePdfBench | benchMpdf10Pages                               |     | 3    | 5   | 17.918mb | 16.428ms  | ±5.83%  |
| GeneratePdfBench | benchMpdf50Pages                               |     | 3    | 5   | 18.211mb | 33.004ms  | ±1.53%  |
| GeneratePdfBench | benchMpdf100Pages                              |     | 3    | 5   | 18.573mb | 54.429ms  | ±0.98%  |
| GeneratePdfBench | benchDompdf1Page                               |     | 3    | 5   | 9.456mb  | 5.431ms   | ±18.54% |
| GeneratePdfBench | benchDompdf5Pages                              |     | 3    | 5   | 9.676mb  | 7.678ms   | ±1.12%  |
| GeneratePdfBench | benchDompdf10Pages                             |     | 3    | 5   | 9.997mb  | 10.444ms  | ±0.86%  |
| GeneratePdfBench | benchDompdf50Pages                             |     | 3    | 5   | 12.690mb | 39.515ms  | ±2.07%  |
| GeneratePdfBench | benchDompdf100Pages                            |     | 3    | 5   | 16.053mb | 88.040ms  | ±5.60%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances         |     | 3    | 5   | 6.889mb  | 2.303ms   | ±10.65% |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff             |     | 3    | 5   | 9.600mb  | 25.589ms  | ±0.76%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting           |     | 3    | 5   | 9.356mb  | 24.705ms  | ±0.97%  |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText              |     | 3    | 5   | 9.412mb  | 25.079ms  | ±0.77%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption     |     | 3    | 5   | 5.215mb  | 35.150ms  | ±15.63% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse            |     | 3    | 5   | 4.607mb  | 276.215μs | ±10.49% |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating           |     | 5    | 3   | 7.348mb  | 1.489ms   | ±3.47%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                  |     | 3    | 5   | 5.942mb  | 1.411ms   | ±3.76%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                   |     | 10   | 5   | 4.607mb  | 10.413ms  | ±1.88%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                     |     | 10   | 5   | 4.607mb  | 59.136ms  | ±2.33%  |
+------------------+------------------------------------------------+-----+------+-----+----------+-----------+---------+

```