# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-08 20:02:31 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 12.243ms | 2.077ms | 2.322ms | 4.321ms | 6.359ms |
| FPDF | 817.877μs | 888.274μs | 962.474μs | 1.565ms | 2.299ms |
| TCPDF | 9.936ms | 10.918ms | 12.132ms | 20.417ms | 30.829ms |
| mPDF | 24.842ms | 28.607ms | 32.849ms | 64.156ms | 103.154ms |
| Dompdf | 11.209ms | 15.776ms | 21.048ms | 71.915ms | 159.152ms |

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
| phpdftk | 2.769ms | 3.016ms | 3.157ms | 5.092ms | 7.714ms |
| FPDF | 1.091ms | 1.163ms | 1.267ms | 1.966ms | 2.826ms |
| TCPDF | 14.961ms | 15.350ms | 21.552ms | 26.256ms | 38.285ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.089mb | 5.135mb | 5.194mb | 5.684mb | 6.279mb |
| FPDF | 4.328mb | 4.328mb | 4.328mb | 4.357mb | 4.418mb |
| TCPDF | 12.407mb | 12.407mb | 12.407mb | 12.407mb | 12.408mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.120ms | 1.614ms | 5.879ms |
| smalot/pdfparser | 1.980ms | 2.371ms | 5.633ms |
| setasign/fpdi | 1.920ms | 2.810ms | 29.738ms |

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
| phpdftk | 1.933ms | 1.308ms |
| smalot/pdfparser | FAIL | 1.900ms |
| setasign/fpdi | 2.921ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.089mb  | 2.769ms   | ±0.57%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.135mb  | 3.016ms   | ±1.54%  |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.194mb  | 3.157ms   | ±0.54%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.684mb  | 5.092ms   | ±1.14%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.279mb  | 7.714ms   | ±0.93%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.407mb | 14.961ms  | ±8.42%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.407mb | 15.350ms  | ±0.72%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.407mb | 21.552ms  | ±83.09% |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.407mb | 26.256ms  | ±0.30%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.408mb | 38.285ms  | ±0.89%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.328mb  | 1.091ms   | ±1.00%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.328mb  | 1.163ms   | ±1.26%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.328mb  | 1.267ms   | ±0.80%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.357mb  | 1.966ms   | ±1.55%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.418mb  | 2.826ms   | ±1.07%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.155mb  | 1.205ms   | ±1.38%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.155mb  | 1.614ms   | ±0.79%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.504mb  | 5.879ms   | ±0.71%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.155mb  | 1.933ms   | ±0.69%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.155mb  | 1.308ms   | ±1.20%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.653mb  | 1.980ms   | ±0.72%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.753mb  | 2.371ms   | ±0.78%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.522mb  | 5.633ms   | ±0.55%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.155mb  | 555.707μs | ±6.35%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.644mb  | 1.900ms   | ±0.48%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.692mb  | 1.920ms   | ±0.95%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.692mb  | 2.810ms   | ±8.52%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.438mb  | 29.738ms  | ±0.93%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.787mb  | 2.921ms   | ±1.05%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.718mb  | 1.482ms   | ±0.55%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.703mb  | 6.910ms   | ±0.56%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.654mb  | 5.036ms   | ±0.66%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.701mb  | 3.492ms   | ±0.28%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.155mb  | 3.097μs   | ±21.35% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.249mb  | 6.120ms   | ±1.61%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.536mb  | 1.925ms   | ±1.53%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.596mb  | 2.077ms   | ±0.40%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.682mb  | 2.322ms   | ±2.90%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.312mb  | 4.321ms   | ±0.89%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.130mb  | 6.359ms   | ±0.77%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.742mb  | 2.576ms   | ±1.40%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.056mb  | 3.206ms   | ±0.69%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.558mb  | 11.875ms  | ±33.83% |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.851mb  | 2.748ms   | ±0.83%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.474mb  | 2.084ms   | ±0.54%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.632mb  | 688.619μs | ±4.69%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.819mb  | 2.595ms   | ±0.93%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 5.913mb  | 3.042ms   | ±11.14% |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.833mb  | 2.621ms   | ±0.82%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 5.889mb  | 186.315ms | ±25.27% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 5.950mb  | 2.974ms   | ±1.47%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.565mb  | 5.527ms   | ±1.16%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.699mb  | 5.671ms   | ±7.88%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.832mb | 9.936ms   | ±2.04%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.832mb | 10.918ms  | ±0.60%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.832mb | 12.132ms  | ±0.84%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.832mb | 20.417ms  | ±0.25%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.832mb | 30.829ms  | ±0.97%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 4.986mb  | 817.877μs | ±1.19%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 4.986mb  | 888.274μs | ±1.28%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 4.986mb  | 962.474μs | ±1.09%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 4.986mb  | 1.565ms   | ±0.41%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 4.997mb  | 2.299ms   | ±2.06%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.544mb | 24.842ms  | ±1.90%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.603mb | 28.607ms  | ±0.30%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.642mb | 32.849ms  | ±0.69%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.934mb | 64.156ms  | ±0.14%  |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.296mb | 103.154ms | ±0.35%  |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.278mb  | 11.209ms  | ±1.50%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.497mb  | 15.776ms  | ±0.51%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.818mb  | 21.048ms  | ±0.47%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.511mb | 71.915ms  | ±0.34%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.874mb | 159.152ms | ±0.63%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.726mb  | 4.460ms   | ±0.94%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.370mb  | 49.406ms  | ±0.42%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.632mb  | 1.463μs   | ±17.82% |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.632mb  | 1.344μs   | ±11.13% |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.632mb  | 1.142μs   | ±22.36% |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 4.974mb  | 224.538ms | ±28.06% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.632mb  | 433.920μs | ±1.00%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.180mb  | 2.676ms   | ±0.18%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.774mb  | 2.777ms   | ±0.79%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.632mb  | 10.839ms  | ±6.91%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.632mb  | 85.158ms  | ±0.99%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.632mb  | 13.939ms  | ±0.90%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.632mb  | 25.133ms  | ±0.87%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.605mb  | 212.994ms | ±14.67% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.737mb  | 12.504ms  | ±0.61%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.710mb  | 12.416ms  | ±0.83%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.718mb  | 12.500ms  | ±2.63%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.734mb  | 12.482ms  | ±0.52%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.918mb  | 13.040ms  | ±0.88%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.605mb  | 2.303ms   | ±0.46%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.721mb  | 12.562ms  | ±0.95%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.838mb  | 12.664ms  | ±0.24%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.675mb  | 12.243ms  | ±0.64%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```