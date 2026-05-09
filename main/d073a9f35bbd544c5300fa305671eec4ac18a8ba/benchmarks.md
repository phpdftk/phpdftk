# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-09 13:17:12 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 12.461ms | 2.132ms | 2.436ms | 4.234ms | 6.475ms |
| FPDF | 858.625μs | 946.964μs | 1.008ms | 1.642ms | 2.338ms |
| TCPDF | 10.210ms | 11.227ms | 12.223ms | 20.506ms | 31.537ms |
| mPDF | 25.460ms | 29.362ms | 33.659ms | 66.470ms | 105.934ms |
| Dompdf | 11.725ms | 16.240ms | 21.670ms | 73.814ms | 162.885ms |

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
| phpdftk | 2.883ms | 3.144ms | 3.338ms | 5.307ms | 7.951ms |
| FPDF | 1.128ms | 1.178ms | 1.304ms | 2.002ms | 2.854ms |
| TCPDF | 14.842ms | 15.773ms | 16.952ms | 26.703ms | 39.193ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.125mb | 5.172mb | 5.231mb | 5.721mb | 6.316mb |
| FPDF | 4.365mb | 4.365mb | 4.365mb | 4.394mb | 4.455mb |
| TCPDF | 12.444mb | 12.444mb | 12.444mb | 12.444mb | 12.445mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.130ms | 1.639ms | 5.923ms |
| smalot/pdfparser | 2.077ms | 2.443ms | 5.867ms |
| setasign/fpdi | 1.936ms | 2.841ms | 29.888ms |

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
| phpdftk | 2.036ms | 1.351ms |
| smalot/pdfparser | FAIL | 1.950ms |
| setasign/fpdi | 2.986ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.125mb  | 2.883ms   | ±1.44%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.172mb  | 3.144ms   | ±2.26%  |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.231mb  | 3.338ms   | ±2.23%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.721mb  | 5.307ms   | ±0.34%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.316mb  | 7.951ms   | ±1.07%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.444mb | 14.842ms  | ±11.59% |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.444mb | 15.773ms  | ±1.02%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.444mb | 16.952ms  | ±2.37%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.444mb | 26.703ms  | ±2.93%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.445mb | 39.193ms  | ±0.80%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.365mb  | 1.128ms   | ±1.45%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.365mb  | 1.178ms   | ±2.21%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.365mb  | 1.304ms   | ±0.86%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.394mb  | 2.002ms   | ±0.74%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.455mb  | 2.854ms   | ±0.39%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.195mb  | 1.204ms   | ±2.34%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.195mb  | 1.639ms   | ±0.88%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.541mb  | 5.923ms   | ±0.66%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.195mb  | 2.036ms   | ±1.50%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.195mb  | 1.351ms   | ±1.45%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.700mb  | 2.077ms   | ±6.24%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.801mb  | 2.443ms   | ±1.43%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.559mb  | 5.867ms   | ±1.45%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.195mb  | 552.529μs | ±2.26%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.697mb  | 1.950ms   | ±1.16%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.804mb  | 1.936ms   | ±1.41%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.804mb  | 2.841ms   | ±0.84%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.475mb  | 29.888ms  | ±0.39%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.823mb  | 2.986ms   | ±1.21%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.756mb  | 1.533ms   | ±1.40%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.740mb  | 6.971ms   | ±1.71%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.691mb  | 5.175ms   | ±0.66%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.738mb  | 3.557ms   | ±0.33%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.195mb  | 3.724μs   | ±18.67% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.286mb  | 6.130ms   | ±1.17%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.573mb  | 1.941ms   | ±6.98%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.633mb  | 2.132ms   | ±1.75%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.718mb  | 2.436ms   | ±1.04%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.349mb  | 4.234ms   | ±3.73%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.167mb  | 6.475ms   | ±0.88%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.779mb  | 2.635ms   | ±0.64%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.093mb  | 3.508ms   | ±2.20%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.595mb  | 12.085ms  | ±29.23% |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.888mb  | 2.805ms   | ±0.84%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.511mb  | 2.120ms   | ±1.23%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.604mb  | 635.393μs | ±2.41%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.856mb  | 2.645ms   | ±1.05%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 5.950mb  | 3.101ms   | ±1.26%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.870mb  | 2.708ms   | ±3.72%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 5.926mb  | 185.897ms | ±29.58% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 5.987mb  | 3.038ms   | ±1.02%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.602mb  | 5.735ms   | ±1.26%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.736mb  | 5.933ms   | ±7.90%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.869mb | 10.210ms  | ±0.78%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.869mb | 11.227ms  | ±0.86%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.869mb | 12.223ms  | ±0.81%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.869mb | 20.506ms  | ±0.72%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.869mb | 31.537ms  | ±2.60%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 5.031mb  | 858.625μs | ±1.48%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 5.031mb  | 946.964μs | ±2.49%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 5.031mb  | 1.008ms   | ±1.93%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 5.031mb  | 1.642ms   | ±1.08%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 5.033mb  | 2.338ms   | ±0.97%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.581mb | 25.460ms  | ±1.85%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.640mb | 29.362ms  | ±0.31%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.678mb | 33.659ms  | ±0.74%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.971mb | 66.470ms  | ±0.51%  |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.333mb | 105.934ms | ±2.54%  |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.314mb  | 11.725ms  | ±0.80%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.534mb  | 16.240ms  | ±0.73%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.855mb  | 21.670ms  | ±1.18%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.548mb | 73.814ms  | ±0.38%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.911mb | 162.885ms | ±0.48%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.763mb  | 4.617ms   | ±1.12%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.407mb  | 50.214ms  | ±0.53%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.604mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.604mb  | 1.676μs   | ±71.79% |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.604mb  | 1.656μs   | ±10.65% |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.011mb  | 206.881ms | ±14.57% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.604mb  | 437.720μs | ±1.07%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.217mb  | 2.794ms   | ±0.73%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.746mb  | 2.880ms   | ±1.11%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.604mb  | 13.195ms  | ±4.64%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.604mb  | 86.444ms  | ±0.44%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.604mb  | 14.564ms  | ±1.09%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.603mb  | 24.909ms  | ±1.11%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.642mb  | 195.766ms | ±18.19% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.774mb  | 12.625ms  | ±0.18%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.747mb  | 12.541ms  | ±0.56%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.755mb  | 12.750ms  | ±0.65%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.771mb  | 12.725ms  | ±0.27%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.954mb  | 13.244ms  | ±0.51%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.642mb  | 2.390ms   | ±0.97%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.758mb  | 12.698ms  | ±0.58%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.875mb  | 12.835ms  | ±0.48%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.712mb  | 12.461ms  | ±0.82%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```