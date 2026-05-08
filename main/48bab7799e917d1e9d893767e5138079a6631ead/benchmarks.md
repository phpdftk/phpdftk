# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-08 21:10:27 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 12.462ms | 2.085ms | 2.355ms | 4.208ms | 6.462ms |
| FPDF | 838.857μs | 908.636μs | 983.085μs | 1.609ms | 2.325ms |
| TCPDF | 10.037ms | 11.042ms | 12.097ms | 20.485ms | 31.527ms |
| mPDF | 25.030ms | 29.219ms | 32.975ms | 65.744ms | 105.287ms |
| Dompdf | 11.420ms | 16.091ms | 21.498ms | 73.280ms | 160.597ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 8.712mb | 5.633mb | 5.718mb | 6.349mb | 7.167mb |
| FPDF | 5.031mb | 5.031mb | 5.031mb | 5.031mb | 5.033mb |
| TCPDF | 12.869mb | 12.869mb | 12.869mb | 12.869mb | 12.869mb |
| mPDF | 17.581mb | 17.640mb | 17.678mb | 17.971mb | 18.333mb |
| Dompdf | 9.314mb | 9.534mb | 9.855mb | 12.548mb | 15.911mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.773ms | 3.050ms | 3.255ms | 5.290ms | 7.968ms |
| FPDF | 1.075ms | 1.174ms | 1.296ms | 1.973ms | 2.808ms |
| TCPDF | 14.738ms | 15.593ms | 16.935ms | 26.682ms | 38.633ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.125mb | 5.172mb | 5.231mb | 5.721mb | 6.316mb |
| FPDF | 4.365mb | 4.365mb | 4.365mb | 4.394mb | 4.455mb |
| TCPDF | 12.444mb | 12.444mb | 12.444mb | 12.444mb | 12.445mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.093ms | 1.650ms | 5.923ms |
| smalot/pdfparser | 2.007ms | 2.363ms | 5.755ms |
| setasign/fpdi | 1.927ms | 2.815ms | 29.738ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.286mb | 4.195mb | 4.541mb |
| smalot/pdfparser | 4.700mb | 4.801mb | 6.559mb |
| setasign/fpdi | 4.804mb | 4.804mb | 5.475mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 1.967ms | 1.328ms |
| smalot/pdfparser | FAIL | 1.908ms |
| setasign/fpdi | 2.989ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.125mb  | 2.773ms   | ±1.33%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.172mb  | 3.050ms   | ±19.65% |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.231mb  | 3.255ms   | ±0.52%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.721mb  | 5.290ms   | ±1.29%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.316mb  | 7.968ms   | ±2.74%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.444mb | 14.738ms  | ±6.12%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.444mb | 15.593ms  | ±0.15%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.444mb | 16.935ms  | ±0.09%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.444mb | 26.682ms  | ±1.83%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.445mb | 38.633ms  | ±0.09%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.365mb  | 1.075ms   | ±1.08%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.365mb  | 1.174ms   | ±1.17%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.365mb  | 1.296ms   | ±2.37%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.394mb  | 1.973ms   | ±0.75%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.455mb  | 2.808ms   | ±0.58%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.195mb  | 1.182ms   | ±1.43%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.195mb  | 1.650ms   | ±1.01%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.541mb  | 5.923ms   | ±0.96%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.195mb  | 1.967ms   | ±0.66%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.195mb  | 1.328ms   | ±1.18%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.700mb  | 2.007ms   | ±1.15%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.801mb  | 2.363ms   | ±1.21%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.559mb  | 5.755ms   | ±0.77%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.195mb  | 549.594μs | ±1.32%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.697mb  | 1.908ms   | ±0.88%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.804mb  | 1.927ms   | ±1.81%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.804mb  | 2.815ms   | ±1.48%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.475mb  | 29.738ms  | ±1.70%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.823mb  | 2.989ms   | ±0.62%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.756mb  | 1.482ms   | ±2.64%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.740mb  | 7.003ms   | ±0.97%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.691mb  | 5.157ms   | ±0.63%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.738mb  | 3.487ms   | ±0.73%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.195mb  | 3.366μs   | ±12.00% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.286mb  | 6.093ms   | ±0.68%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.573mb  | 1.901ms   | ±6.93%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.633mb  | 2.085ms   | ±1.78%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.718mb  | 2.355ms   | ±1.70%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.349mb  | 4.208ms   | ±0.27%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.167mb  | 6.462ms   | ±1.29%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.779mb  | 2.624ms   | ±0.95%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.093mb  | 3.435ms   | ±1.23%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.595mb  | 11.987ms  | ±1.13%  |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.888mb  | 2.813ms   | ±0.83%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.511mb  | 2.127ms   | ±0.95%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.604mb  | 651.183μs | ±3.78%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.856mb  | 2.597ms   | ±1.14%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 5.950mb  | 3.052ms   | ±1.58%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.870mb  | 2.657ms   | ±9.38%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 5.926mb  | 173.797ms | ±25.80% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 5.987mb  | 3.035ms   | ±1.25%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.602mb  | 5.541ms   | ±0.46%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.736mb  | 5.873ms   | ±1.81%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.869mb | 10.037ms  | ±1.40%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.869mb | 11.042ms  | ±0.78%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.869mb | 12.097ms  | ±0.60%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.869mb | 20.485ms  | ±0.84%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.869mb | 31.527ms  | ±0.16%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 5.031mb  | 838.857μs | ±2.97%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 5.031mb  | 908.636μs | ±2.05%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 5.031mb  | 983.085μs | ±1.52%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 5.031mb  | 1.609ms   | ±14.10% |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 5.033mb  | 2.325ms   | ±1.14%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.581mb | 25.030ms  | ±2.17%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.640mb | 29.219ms  | ±0.50%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.678mb | 32.975ms  | ±0.46%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.971mb | 65.744ms  | ±0.89%  |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.333mb | 105.287ms | ±18.02% |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.314mb  | 11.420ms  | ±1.59%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.534mb  | 16.091ms  | ±0.75%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.855mb  | 21.498ms  | ±0.40%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.548mb | 73.280ms  | ±1.65%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.911mb | 160.597ms | ±0.44%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.763mb  | 4.581ms   | ±14.45% |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.407mb  | 49.574ms  | ±1.08%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.604mb  | 1.344μs   | ±11.13% |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.604mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.604mb  | 1.333μs   | ±10.53% |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.011mb  | 222.808ms | ±8.96%  |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.604mb  | 455.693μs | ±2.12%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.217mb  | 2.746ms   | ±0.27%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.746mb  | 2.867ms   | ±1.17%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.604mb  | 14.179ms  | ±8.78%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.604mb  | 87.154ms  | ±1.00%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.604mb  | 14.620ms  | ±1.25%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.603mb  | 25.688ms  | ±1.45%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.642mb  | 172.246ms | ±27.64% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.774mb  | 12.503ms  | ±0.22%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.747mb  | 12.411ms  | ±0.57%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.755mb  | 12.634ms  | ±0.58%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.771mb  | 12.627ms  | ±1.59%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.954mb  | 13.111ms  | ±0.70%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.642mb  | 2.325ms   | ±1.83%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.758mb  | 12.607ms  | ±0.66%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.875mb  | 12.770ms  | ±1.24%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.712mb  | 12.462ms  | ±1.31%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```