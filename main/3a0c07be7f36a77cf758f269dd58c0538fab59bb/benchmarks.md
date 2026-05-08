# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-08 20:16:22 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 12.296ms | 2.091ms | 2.320ms | 4.321ms | 6.381ms |
| FPDF | 800.691μs | 898.988μs | 986.649μs | 1.578ms | 2.305ms |
| TCPDF | 9.939ms | 10.852ms | 11.895ms | 20.537ms | 31.100ms |
| mPDF | 24.918ms | 28.938ms | 32.978ms | 64.534ms | 104.505ms |
| Dompdf | 12.040ms | 15.929ms | 21.581ms | 72.251ms | 160.630ms |

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
| phpdftk | 2.803ms | 3.195ms | 3.256ms | 5.234ms | 7.869ms |
| FPDF | 1.064ms | 1.155ms | 1.237ms | 1.923ms | 2.780ms |
| TCPDF | 14.713ms | 15.497ms | 16.650ms | 26.302ms | 38.929ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.089mb | 5.135mb | 5.194mb | 5.684mb | 6.279mb |
| FPDF | 4.328mb | 4.328mb | 4.328mb | 4.357mb | 4.418mb |
| TCPDF | 12.407mb | 12.407mb | 12.407mb | 12.407mb | 12.408mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.089ms | 1.617ms | 5.906ms |
| smalot/pdfparser | 1.971ms | 2.367ms | 5.697ms |
| setasign/fpdi | 1.891ms | 2.786ms | 29.633ms |

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
| phpdftk | 1.962ms | 1.296ms |
| smalot/pdfparser | FAIL | 1.893ms |
| setasign/fpdi | 2.943ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.089mb  | 2.803ms   | ±2.94%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.135mb  | 3.195ms   | ±14.29% |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.194mb  | 3.256ms   | ±0.59%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.684mb  | 5.234ms   | ±0.38%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.279mb  | 7.869ms   | ±0.37%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.407mb | 14.713ms  | ±6.10%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.407mb | 15.497ms  | ±2.96%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.407mb | 16.650ms  | ±0.43%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.407mb | 26.302ms  | ±0.81%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.408mb | 38.929ms  | ±0.68%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.328mb  | 1.064ms   | ±0.97%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.328mb  | 1.155ms   | ±0.37%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.328mb  | 1.237ms   | ±0.57%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.357mb  | 1.923ms   | ±0.91%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.418mb  | 2.780ms   | ±0.32%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.155mb  | 1.189ms   | ±1.05%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.155mb  | 1.617ms   | ±0.61%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.504mb  | 5.906ms   | ±0.85%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.155mb  | 1.962ms   | ±11.56% |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.155mb  | 1.296ms   | ±6.41%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.653mb  | 1.971ms   | ±1.30%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.753mb  | 2.367ms   | ±0.79%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.522mb  | 5.697ms   | ±0.64%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.155mb  | 529.768μs | ±1.02%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.644mb  | 1.893ms   | ±0.22%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.692mb  | 1.891ms   | ±0.75%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.692mb  | 2.786ms   | ±0.77%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.438mb  | 29.633ms  | ±0.95%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.787mb  | 2.943ms   | ±1.03%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.718mb  | 1.505ms   | ±0.91%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.703mb  | 6.963ms   | ±0.88%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.654mb  | 5.149ms   | ±11.09% |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.701mb  | 3.501ms   | ±3.99%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.155mb  | 3.176μs   | ±15.14% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.249mb  | 6.089ms   | ±0.44%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.536mb  | 1.890ms   | ±0.52%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.596mb  | 2.091ms   | ±0.74%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.682mb  | 2.320ms   | ±1.25%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.312mb  | 4.321ms   | ±0.63%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.130mb  | 6.381ms   | ±0.75%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.742mb  | 2.600ms   | ±7.79%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.056mb  | 3.231ms   | ±1.09%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.558mb  | 11.931ms  | ±6.22%  |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.851mb  | 2.723ms   | ±0.76%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.474mb  | 2.079ms   | ±0.84%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.632mb  | 622.929μs | ±4.45%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.819mb  | 2.585ms   | ±0.26%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 5.913mb  | 3.037ms   | ±0.65%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.833mb  | 2.587ms   | ±0.41%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 5.889mb  | 166.582ms | ±18.60% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 5.950mb  | 2.985ms   | ±0.04%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.565mb  | 5.385ms   | ±0.77%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.699mb  | 5.656ms   | ±0.46%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.832mb | 9.939ms   | ±0.50%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.832mb | 10.852ms  | ±0.31%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.832mb | 11.895ms  | ±0.39%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.832mb | 20.537ms  | ±0.61%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.832mb | 31.100ms  | ±0.42%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 4.986mb  | 800.691μs | ±1.36%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 4.986mb  | 898.988μs | ±1.39%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 4.986mb  | 986.649μs | ±1.26%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 4.986mb  | 1.578ms   | ±0.61%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 4.997mb  | 2.305ms   | ±1.10%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.544mb | 24.918ms  | ±2.04%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.603mb | 28.938ms  | ±0.59%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.642mb | 32.978ms  | ±0.80%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.934mb | 64.534ms  | ±1.37%  |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.296mb | 104.505ms | ±0.49%  |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.278mb  | 12.040ms  | ±83.56% |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.497mb  | 15.929ms  | ±0.95%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.818mb  | 21.581ms  | ±1.03%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.511mb | 72.251ms  | ±0.45%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.874mb | 160.630ms | ±2.08%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.726mb  | 4.497ms   | ±0.73%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.370mb  | 49.657ms  | ±0.36%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.632mb  | 1.656μs   | ±10.65% |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.632mb  | 1.333μs   | ±15.81% |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.632mb  | 1.011μs   | ±14.41% |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 4.974mb  | 236.051ms | ±28.77% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.632mb  | 437.598μs | ±1.05%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.180mb  | 2.691ms   | ±0.84%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.774mb  | 2.803ms   | ±0.58%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.632mb  | 13.090ms  | ±1.88%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.632mb  | 87.495ms  | ±3.19%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.632mb  | 14.547ms  | ±1.94%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.632mb  | 25.449ms  | ±1.10%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.605mb  | 218.943ms | ±9.59%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.737mb  | 12.588ms  | ±2.28%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.710mb  | 12.401ms  | ±5.05%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.718mb  | 12.517ms  | ±0.67%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.734mb  | 12.578ms  | ±0.34%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.918mb  | 13.089ms  | ±0.97%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.605mb  | 2.332ms   | ±0.45%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.721mb  | 12.571ms  | ±2.68%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.838mb  | 12.741ms  | ±2.02%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.675mb  | 12.296ms  | ±1.19%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```