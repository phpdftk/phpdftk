# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-08 15:28:30 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 12.261ms | 2.134ms | 2.336ms | 4.314ms | 6.372ms |
| FPDF | 807.845μs | 877.717μs | 967.814μs | 1.542ms | 2.307ms |
| TCPDF | 9.888ms | 10.779ms | 11.901ms | 20.274ms | 30.904ms |
| mPDF | 25.005ms | 28.750ms | 32.637ms | 64.943ms | 102.955ms |
| Dompdf | 11.192ms | 17.548ms | 21.040ms | 71.825ms | 158.793ms |

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
| phpdftk | 2.736ms | 2.930ms | 3.249ms | 5.124ms | 7.899ms |
| FPDF | 1.153ms | 1.156ms | 1.277ms | 1.944ms | 2.756ms |
| TCPDF | 14.559ms | 15.501ms | 16.904ms | 26.255ms | 38.345ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.089mb | 5.135mb | 5.194mb | 5.684mb | 6.279mb |
| FPDF | 4.328mb | 4.328mb | 4.328mb | 4.357mb | 4.418mb |
| TCPDF | 12.407mb | 12.407mb | 12.407mb | 12.407mb | 12.408mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.023ms | 1.617ms | 5.877ms |
| smalot/pdfparser | 1.977ms | 2.369ms | 5.697ms |
| setasign/fpdi | 1.920ms | 2.793ms | 29.445ms |

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
| phpdftk | 1.938ms | 1.297ms |
| smalot/pdfparser | FAIL | 1.904ms |
| setasign/fpdi | 2.934ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.089mb  | 2.736ms   | ±0.47%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.135mb  | 2.930ms   | ±0.14%  |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.194mb  | 3.249ms   | ±2.41%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.684mb  | 5.124ms   | ±0.40%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.279mb  | 7.899ms   | ±1.64%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.407mb | 14.559ms  | ±7.93%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.407mb | 15.501ms  | ±0.55%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.407mb | 16.904ms  | ±1.14%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.407mb | 26.255ms  | ±0.94%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.408mb | 38.345ms  | ±0.19%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.328mb  | 1.153ms   | ±5.18%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.328mb  | 1.156ms   | ±0.46%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.328mb  | 1.277ms   | ±3.96%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.357mb  | 1.944ms   | ±0.77%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.418mb  | 2.756ms   | ±0.30%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.155mb  | 1.177ms   | ±0.53%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.155mb  | 1.617ms   | ±0.57%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.504mb  | 5.877ms   | ±0.59%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.155mb  | 1.938ms   | ±0.81%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.155mb  | 1.297ms   | ±1.00%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.653mb  | 1.977ms   | ±1.00%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.753mb  | 2.369ms   | ±0.37%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.522mb  | 5.697ms   | ±0.48%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.155mb  | 531.341μs | ±1.22%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.644mb  | 1.904ms   | ±1.30%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.692mb  | 1.920ms   | ±2.34%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.692mb  | 2.793ms   | ±0.57%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.438mb  | 29.445ms  | ±0.63%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.787mb  | 2.934ms   | ±0.85%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.718mb  | 1.478ms   | ±1.07%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.703mb  | 6.870ms   | ±0.82%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.654mb  | 5.049ms   | ±0.54%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.701mb  | 3.491ms   | ±0.21%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.155mb  | 3.297μs   | ±20.20% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.249mb  | 6.023ms   | ±3.26%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.536mb  | 1.937ms   | ±1.65%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.596mb  | 2.134ms   | ±1.50%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.682mb  | 2.336ms   | ±0.98%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.312mb  | 4.314ms   | ±0.40%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.130mb  | 6.372ms   | ±1.05%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.742mb  | 2.568ms   | ±0.54%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.056mb  | 3.211ms   | ±0.84%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.558mb  | 11.904ms  | ±7.51%  |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.851mb  | 2.766ms   | ±1.68%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.474mb  | 2.093ms   | ±1.18%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.632mb  | 641.275μs | ±2.97%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.819mb  | 2.607ms   | ±1.85%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 5.913mb  | 3.018ms   | ±0.82%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.833mb  | 2.603ms   | ±0.43%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 5.889mb  | 257.995ms | ±27.52% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 5.950mb  | 2.962ms   | ±0.30%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.565mb  | 5.429ms   | ±0.73%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.699mb  | 5.693ms   | ±10.78% |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.832mb | 9.888ms   | ±0.51%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.832mb | 10.779ms  | ±0.43%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.832mb | 11.901ms  | ±0.33%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.832mb | 20.274ms  | ±0.86%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.832mb | 30.904ms  | ±0.22%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 4.986mb  | 807.845μs | ±2.08%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 4.986mb  | 877.717μs | ±4.57%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 4.986mb  | 967.814μs | ±1.70%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 4.986mb  | 1.542ms   | ±3.77%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 4.997mb  | 2.307ms   | ±1.14%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.544mb | 25.005ms  | ±1.98%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.603mb | 28.750ms  | ±1.77%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.642mb | 32.637ms  | ±0.91%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.934mb | 64.943ms  | ±0.82%  |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.296mb | 102.955ms | ±1.00%  |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.278mb  | 11.192ms  | ±1.35%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.497mb  | 17.548ms  | ±68.62% |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.818mb  | 21.040ms  | ±0.59%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.511mb | 71.825ms  | ±0.52%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.874mb | 158.793ms | ±0.57%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.726mb  | 4.436ms   | ±0.70%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.370mb  | 49.353ms  | ±2.07%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.632mb  | 1.022μs   | ±25.78% |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.632mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.632mb  | 1.334μs   | ±9.52%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 4.974mb  | 171.295ms | ±26.54% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.632mb  | 438.613μs | ±0.56%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.180mb  | 2.697ms   | ±0.34%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.774mb  | 2.758ms   | ±1.27%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.632mb  | 11.681ms  | ±4.82%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.632mb  | 84.977ms  | ±2.16%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.632mb  | 14.288ms  | ±0.89%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.632mb  | 24.922ms  | ±1.05%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.605mb  | 293.615ms | ±12.41% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.737mb  | 12.458ms  | ±0.41%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.710mb  | 12.330ms  | ±0.94%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.718mb  | 12.356ms  | ±0.62%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.734mb  | 12.594ms  | ±1.48%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.918mb  | 12.934ms  | ±0.80%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.605mb  | 2.324ms   | ±0.44%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.721mb  | 12.583ms  | ±0.64%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.838mb  | 12.528ms  | ±0.70%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.675mb  | 12.261ms  | ±0.74%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```