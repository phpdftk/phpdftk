# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-08 20:16:27 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 12.292ms | 2.130ms | 2.352ms | 4.389ms | 6.484ms |
| FPDF | 829.925μs | 895.023μs | 987.000μs | 1.581ms | 2.353ms |
| TCPDF | 9.989ms | 10.907ms | 12.057ms | 20.643ms | 31.338ms |
| mPDF | 25.250ms | 29.023ms | 33.023ms | 65.332ms | 105.795ms |
| Dompdf | 11.436ms | 16.058ms | 21.606ms | 73.629ms | 162.373ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 8.675mb | 5.596mb | 5.682mb | 6.312mb | 7.130mb |
| FPDF | 4.986mb | 4.986mb | 4.986mb | 4.986mb | 4.997mb |
| TCPDF | 12.832mb | 12.832mb | 12.832mb | 12.832mb | 12.832mb |
| mPDF | 17.544mb | 17.603mb | 17.642mb | 17.934mb | 18.296mb |
| Dompdf | 9.278mb | 9.497mb | 9.818mb | 12.511mb | 15.874mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.776ms | 3.055ms | 3.254ms | 5.194ms | 7.873ms |
| FPDF | 1.074ms | 1.499ms | 18.103ms | 1.964ms | 2.814ms |
| TCPDF | 14.604ms | 15.644ms | 16.939ms | 26.434ms | 38.800ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.089mb | 5.135mb | 5.194mb | 5.684mb | 6.279mb |
| FPDF | 4.328mb | 4.328mb | 4.328mb | 4.357mb | 4.418mb |
| TCPDF | 12.407mb | 12.407mb | 12.407mb | 12.407mb | 12.408mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.125ms | 1.636ms | 5.920ms |
| smalot/pdfparser | 1.989ms | 2.377ms | 5.765ms |
| setasign/fpdi | 1.953ms | 2.825ms | 29.718ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.249mb | 4.155mb | 4.504mb |
| smalot/pdfparser | 4.653mb | 4.753mb | 6.522mb |
| setasign/fpdi | 4.692mb | 4.692mb | 5.438mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 1.974ms | 1.322ms |
| smalot/pdfparser | FAIL | 1.909ms |
| setasign/fpdi | 2.951ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.089mb  | 2.776ms   | ±1.20%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.135mb  | 3.055ms   | ±1.46%  |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.194mb  | 3.254ms   | ±0.52%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.684mb  | 5.194ms   | ±1.41%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.279mb  | 7.873ms   | ±1.28%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.407mb | 14.604ms  | ±5.93%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.407mb | 15.644ms  | ±0.52%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.407mb | 16.939ms  | ±0.92%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.407mb | 26.434ms  | ±0.91%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.408mb | 38.800ms  | ±0.59%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.328mb  | 1.074ms   | ±2.68%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.328mb  | 1.499ms   | ±85.79% |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.328mb  | 18.103ms  | ±71.30% |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.357mb  | 1.964ms   | ±0.69%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.418mb  | 2.814ms   | ±0.60%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.155mb  | 1.186ms   | ±1.48%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.155mb  | 1.636ms   | ±0.82%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.504mb  | 5.920ms   | ±1.51%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.155mb  | 1.974ms   | ±2.09%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.155mb  | 1.322ms   | ±0.72%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.653mb  | 1.989ms   | ±1.03%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.753mb  | 2.377ms   | ±1.25%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.522mb  | 5.765ms   | ±0.80%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.155mb  | 547.279μs | ±1.15%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.644mb  | 1.909ms   | ±0.97%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.692mb  | 1.953ms   | ±0.74%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.692mb  | 2.825ms   | ±1.27%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.438mb  | 29.718ms  | ±0.26%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.787mb  | 2.951ms   | ±0.88%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.718mb  | 1.498ms   | ±0.71%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.703mb  | 6.965ms   | ±0.78%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.654mb  | 5.105ms   | ±0.37%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.701mb  | 3.504ms   | ±0.53%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.155mb  | 3.376μs   | ±14.32% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.249mb  | 6.125ms   | ±0.96%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.536mb  | 1.933ms   | ±1.27%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.596mb  | 2.130ms   | ±0.75%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.682mb  | 2.352ms   | ±1.29%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.312mb  | 4.389ms   | ±5.62%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.130mb  | 6.484ms   | ±0.42%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.742mb  | 2.594ms   | ±0.61%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.056mb  | 3.246ms   | ±0.28%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.558mb  | 11.826ms  | ±9.54%  |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.851mb  | 2.796ms   | ±0.95%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.474mb  | 2.114ms   | ±0.56%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.632mb  | 635.109μs | ±1.79%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.819mb  | 2.599ms   | ±1.15%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 5.913mb  | 3.098ms   | ±2.70%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.833mb  | 2.664ms   | ±0.62%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 5.889mb  | 173.668ms | ±28.18% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 5.950mb  | 3.033ms   | ±0.74%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.565mb  | 5.482ms   | ±1.88%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.699mb  | 5.810ms   | ±0.77%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.832mb | 9.989ms   | ±0.80%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.832mb | 10.907ms  | ±1.02%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.832mb | 12.057ms  | ±0.57%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.832mb | 20.643ms  | ±3.41%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.832mb | 31.338ms  | ±0.39%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 4.986mb  | 829.925μs | ±0.98%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 4.986mb  | 895.023μs | ±1.36%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 4.986mb  | 987.000μs | ±1.75%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 4.986mb  | 1.581ms   | ±1.16%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 4.997mb  | 2.353ms   | ±0.57%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.544mb | 25.250ms  | ±2.06%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.603mb | 29.023ms  | ±0.55%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.642mb | 33.023ms  | ±0.36%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.934mb | 65.332ms  | ±27.14% |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.296mb | 105.795ms | ±0.68%  |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.278mb  | 11.436ms  | ±1.84%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.497mb  | 16.058ms  | ±0.53%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.818mb  | 21.606ms  | ±3.38%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.511mb | 73.629ms  | ±0.87%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.874mb | 162.373ms | ±0.46%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.726mb  | 4.520ms   | ±0.58%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.370mb  | 49.426ms  | ±0.46%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.632mb  | 1.334μs   | ±9.52%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.632mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.632mb  | 1.333μs   | ±15.81% |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 4.974mb  | 225.985ms | ±28.19% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.632mb  | 439.834μs | ±0.89%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.180mb  | 2.750ms   | ±4.32%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.774mb  | 2.804ms   | ±0.58%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.632mb  | 13.097ms  | ±1.44%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.632mb  | 87.145ms  | ±0.69%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.632mb  | 14.131ms  | ±0.67%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.632mb  | 25.316ms  | ±0.74%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.605mb  | 267.113ms | ±17.87% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.737mb  | 12.605ms  | ±1.87%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.710mb  | 12.500ms  | ±0.70%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.718mb  | 12.520ms  | ±0.96%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.734mb  | 12.548ms  | ±0.29%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.918mb  | 13.009ms  | ±0.63%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.605mb  | 2.347ms   | ±0.53%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.721mb  | 12.649ms  | ±0.79%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.838mb  | 12.721ms  | ±0.19%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.675mb  | 12.292ms  | ±0.56%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```