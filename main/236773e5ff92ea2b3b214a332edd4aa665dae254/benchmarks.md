# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-08 20:16:11 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 12.251ms | 2.115ms | 2.349ms | 4.304ms | 6.403ms |
| FPDF | 822.963μs | 901.553μs | 993.937μs | 1.588ms | 2.343ms |
| TCPDF | 9.919ms | 10.875ms | 11.995ms | 20.448ms | 32.434ms |
| mPDF | 25.137ms | 29.192ms | 33.047ms | 65.386ms | 104.994ms |
| Dompdf | 11.383ms | 15.876ms | 21.492ms | 73.238ms | 161.326ms |

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
| phpdftk | 2.777ms | 2.940ms | 3.214ms | 5.121ms | 7.816ms |
| FPDF | 1.099ms | 1.162ms | 1.273ms | 1.934ms | 2.832ms |
| TCPDF | 14.505ms | 15.394ms | 16.732ms | 26.400ms | 38.312ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.089mb | 5.135mb | 5.194mb | 5.684mb | 6.279mb |
| FPDF | 4.328mb | 4.328mb | 4.328mb | 4.357mb | 4.418mb |
| TCPDF | 12.407mb | 12.407mb | 12.407mb | 12.407mb | 12.408mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.033ms | 1.621ms | 5.924ms |
| smalot/pdfparser | 1.993ms | 2.339ms | 5.647ms |
| setasign/fpdi | 1.889ms | 2.787ms | 29.661ms |

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
| phpdftk | 1.972ms | 1.322ms |
| smalot/pdfparser | FAIL | 1.905ms |
| setasign/fpdi | 2.974ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.089mb  | 2.777ms   | ±0.80%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.135mb  | 2.940ms   | ±0.91%  |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.194mb  | 3.214ms   | ±0.73%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.684mb  | 5.121ms   | ±1.28%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.279mb  | 7.816ms   | ±0.81%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.407mb | 14.505ms  | ±7.28%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.407mb | 15.394ms  | ±0.19%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.407mb | 16.732ms  | ±0.25%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.407mb | 26.400ms  | ±0.61%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.408mb | 38.312ms  | ±0.27%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.328mb  | 1.099ms   | ±2.99%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.328mb  | 1.162ms   | ±0.78%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.328mb  | 1.273ms   | ±0.58%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.357mb  | 1.934ms   | ±0.14%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.418mb  | 2.832ms   | ±1.25%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.155mb  | 1.177ms   | ±0.43%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.155mb  | 1.621ms   | ±1.19%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.504mb  | 5.924ms   | ±0.38%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.155mb  | 1.972ms   | ±0.36%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.155mb  | 1.322ms   | ±0.82%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.653mb  | 1.993ms   | ±0.93%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.753mb  | 2.339ms   | ±0.79%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.522mb  | 5.647ms   | ±0.29%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.155mb  | 547.953μs | ±2.01%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.644mb  | 1.905ms   | ±0.89%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.692mb  | 1.889ms   | ±0.52%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.692mb  | 2.787ms   | ±0.90%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.438mb  | 29.661ms  | ±0.61%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.787mb  | 2.974ms   | ±0.47%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.718mb  | 1.500ms   | ±1.46%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.703mb  | 6.900ms   | ±1.00%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.654mb  | 5.055ms   | ±0.65%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.701mb  | 3.483ms   | ±0.82%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.155mb  | 3.097μs   | ±21.35% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.249mb  | 6.033ms   | ±1.22%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.536mb  | 1.923ms   | ±0.99%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.596mb  | 2.115ms   | ±1.15%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.682mb  | 2.349ms   | ±1.57%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.312mb  | 4.304ms   | ±1.30%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.130mb  | 6.403ms   | ±0.99%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.742mb  | 2.598ms   | ±0.65%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.056mb  | 3.255ms   | ±0.70%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.558mb  | 11.856ms  | ±13.39% |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.851mb  | 2.763ms   | ±0.41%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.474mb  | 2.087ms   | ±0.45%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.632mb  | 610.757μs | ±3.60%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.819mb  | 2.587ms   | ±0.65%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 5.913mb  | 3.029ms   | ±1.30%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.833mb  | 2.657ms   | ±0.70%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 5.889mb  | 176.343ms | ±13.84% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 5.950mb  | 2.995ms   | ±0.98%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.565mb  | 5.515ms   | ±0.65%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.699mb  | 5.813ms   | ±1.37%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.832mb | 9.919ms   | ±0.53%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.832mb | 10.875ms  | ±0.58%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.832mb | 11.995ms  | ±0.46%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.832mb | 20.448ms  | ±0.82%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.832mb | 32.434ms  | ±2.28%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 4.986mb  | 822.963μs | ±1.91%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 4.986mb  | 901.553μs | ±0.98%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 4.986mb  | 993.937μs | ±0.92%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 4.986mb  | 1.588ms   | ±0.77%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 4.997mb  | 2.343ms   | ±1.07%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.544mb | 25.137ms  | ±2.79%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.603mb | 29.192ms  | ±1.86%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.642mb | 33.047ms  | ±0.65%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.934mb | 65.386ms  | ±1.98%  |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.296mb | 104.994ms | ±1.62%  |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.278mb  | 11.383ms  | ±6.22%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.497mb  | 15.876ms  | ±0.67%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.818mb  | 21.492ms  | ±1.19%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.511mb | 73.238ms  | ±0.61%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.874mb | 161.326ms | ±0.70%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.726mb  | 4.531ms   | ±0.51%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.370mb  | 49.678ms  | ±1.06%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.632mb  | 1.344μs   | ±11.13% |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.632mb  | 1.344μs   | ±11.13% |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.632mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 4.974mb  | 176.912ms | ±25.64% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.632mb  | 442.680μs | ±0.88%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.180mb  | 2.753ms   | ±1.27%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.774mb  | 2.858ms   | ±0.87%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.632mb  | 13.218ms  | ±7.79%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.632mb  | 85.917ms  | ±0.73%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.632mb  | 14.285ms  | ±2.52%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.632mb  | 24.990ms  | ±0.37%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.605mb  | 247.677ms | ±28.40% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.737mb  | 12.881ms  | ±1.30%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.710mb  | 12.582ms  | ±2.22%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.718mb  | 12.777ms  | ±1.19%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.734mb  | 12.748ms  | ±1.60%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.918mb  | 13.370ms  | ±1.37%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.605mb  | 2.370ms   | ±1.17%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.721mb  | 12.605ms  | ±0.90%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.838mb  | 12.716ms  | ±0.74%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.675mb  | 12.251ms  | ±0.99%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```