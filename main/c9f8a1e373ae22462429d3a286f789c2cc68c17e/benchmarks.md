# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-15 07:02:10 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.460ms | 2.329ms | 2.603ms | 4.591ms | 6.961ms |
| FPDF | 849.639μs | 924.996μs | 1.035ms | 1.644ms | 2.335ms |
| TCPDF | 10.510ms | 11.393ms | 12.677ms | 19.869ms | 28.883ms |
| mPDF | 27.548ms | 30.748ms | 34.372ms | 62.048ms | 97.546ms |
| Dompdf | 11.639ms | 15.595ms | 20.572ms | 66.965ms | 150.086ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 8.746mb | 5.806mb | 5.892mb | 6.526mb | 7.349mb |
| FPDF | 5.031mb | 5.032mb | 5.032mb | 5.032mb | 5.034mb |
| TCPDF | 12.870mb | 12.870mb | 12.870mb | 12.870mb | 12.870mb |
| mPDF | 17.582mb | 17.640mb | 17.679mb | 17.972mb | 18.333mb |
| Dompdf | 9.315mb | 9.535mb | 9.856mb | 12.548mb | 15.911mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.204ms | 3.322ms | 3.581ms | 5.537ms | 8.190ms |
| FPDF | 1.142ms | 1.228ms | 1.325ms | 2.023ms | 2.866ms |
| TCPDF | 15.361ms | 16.489ms | 17.512ms | 25.375ms | 36.314ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.232mb | 5.279mb | 5.338mb | 5.830mb | 6.429mb |
| FPDF | 4.366mb | 4.366mb | 4.366mb | 4.395mb | 4.455mb |
| TCPDF | 12.445mb | 12.445mb | 12.445mb | 12.445mb | 12.445mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.113ms | 1.669ms | 6.001ms |
| smalot/pdfparser | 2.042ms | 2.375ms | 5.591ms |
| setasign/fpdi | 1.913ms | 2.696ms | 28.216ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.298mb | 4.195mb | 4.552mb |
| smalot/pdfparser | 4.701mb | 4.802mb | 6.559mb |
| setasign/fpdi | 4.804mb | 4.805mb | 5.476mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 2.053ms | 1.349ms |
| smalot/pdfparser | FAIL | 1.930ms |
| setasign/fpdi | 2.860ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| EncodingBench    | benchEncodeParagraph                             |     | 50   | 5   | 4.194mb  | 42.488μs  | ±0.96%  |
| EncodingBench    | benchShowTextThroughContentStream                |     | 50   | 5   | 6.364mb  | 229.462μs | ±0.45%  |
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.232mb  | 3.204ms   | ±1.62%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.279mb  | 3.322ms   | ±0.81%  |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.338mb  | 3.581ms   | ±0.18%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.830mb  | 5.537ms   | ±0.45%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.429mb  | 8.190ms   | ±0.42%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.445mb | 15.361ms  | ±1.17%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.445mb | 16.489ms  | ±1.15%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.445mb | 17.512ms  | ±1.00%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.445mb | 25.375ms  | ±3.53%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.445mb | 36.314ms  | ±0.93%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.366mb  | 1.142ms   | ±1.50%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.366mb  | 1.228ms   | ±3.07%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.366mb  | 1.325ms   | ±0.82%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.395mb  | 2.023ms   | ±0.25%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.455mb  | 2.866ms   | ±0.48%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.745mb  | 2.148ms   | ±1.49%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.806mb  | 2.329ms   | ±1.15%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.892mb  | 2.603ms   | ±0.46%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.526mb  | 4.591ms   | ±0.97%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.349mb  | 6.961ms   | ±1.93%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.952mb  | 2.826ms   | ±0.57%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.201mb  | 3.643ms   | ±0.69%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.629mb  | 12.984ms  | ±5.82%  |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.995mb  | 2.991ms   | ±0.31%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.534mb  | 2.206ms   | ±0.94%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.604mb  | 596.110μs | ±1.10%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.963mb  | 2.842ms   | ±1.19%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.058mb  | 3.306ms   | ±1.06%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.978mb  | 2.885ms   | ±0.99%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.034mb  | 176.725ms | ±45.72% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.095mb  | 3.423ms   | ±0.63%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.707mb  | 5.760ms   | ±33.05% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.843mb  | 5.913ms   | ±0.63%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.870mb | 10.510ms  | ±4.53%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.870mb | 11.393ms  | ±1.21%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.870mb | 12.677ms  | ±1.76%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.870mb | 19.869ms  | ±1.01%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.870mb | 28.883ms  | ±0.60%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 5.031mb  | 849.639μs | ±1.22%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 5.032mb  | 924.996μs | ±1.96%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 5.032mb  | 1.035ms   | ±0.84%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 5.032mb  | 1.644ms   | ±0.97%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 5.034mb  | 2.335ms   | ±0.81%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.582mb | 27.548ms  | ±2.03%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.640mb | 30.748ms  | ±1.49%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.679mb | 34.372ms  | ±0.63%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.972mb | 62.048ms  | ±0.39%  |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.333mb | 97.546ms  | ±0.71%  |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.315mb  | 11.639ms  | ±2.26%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.535mb  | 15.595ms  | ±2.51%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.856mb  | 20.572ms  | ±0.46%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.548mb | 66.965ms  | ±0.53%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.911mb | 150.086ms | ±0.52%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.908mb  | 4.778ms   | ±0.44%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.347mb  | 54.040ms  | ±0.57%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.604mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.604mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.604mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.013mb  | 245.091ms | ±25.10% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.604mb  | 486.763μs | ±2.27%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.322mb  | 2.819ms   | ±0.26%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.919mb  | 3.041ms   | ±1.16%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.604mb  | 12.888ms  | ±9.36%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.604mb  | 82.380ms  | ±0.96%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.604mb  | 14.895ms  | ±0.45%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.604mb  | 26.225ms  | ±0.86%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.747mb  | 207.408ms | ±26.75% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.874mb  | 13.665ms  | ±0.81%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.846mb  | 13.193ms  | ±1.08%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.855mb  | 13.529ms  | ±0.51%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.871mb  | 13.430ms  | ±1.28%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.997mb  | 14.062ms  | ±0.57%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.661mb  | 2.394ms   | ±1.09%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.858mb  | 13.463ms  | ±0.34%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.917mb  | 13.693ms  | ±5.83%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.746mb  | 13.460ms  | ±1.13%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.195mb  | 1.242ms   | ±1.05%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.195mb  | 1.669ms   | ±0.68%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.552mb  | 6.001ms   | ±0.67%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.195mb  | 2.053ms   | ±1.10%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.195mb  | 1.349ms   | ±1.69%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.701mb  | 2.042ms   | ±1.55%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.802mb  | 2.375ms   | ±0.42%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.559mb  | 5.591ms   | ±1.88%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.195mb  | 574.571μs | ±1.58%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.697mb  | 1.930ms   | ±0.45%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.804mb  | 1.913ms   | ±1.65%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.805mb  | 2.696ms   | ±1.09%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.476mb  | 28.216ms  | ±0.60%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.824mb  | 2.860ms   | ±0.90%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.756mb  | 1.496ms   | ±1.48%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.817mb  | 7.020ms   | ±0.87%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.787mb  | 5.190ms   | ±0.82%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.834mb  | 3.700ms   | ±0.95%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.195mb  | 4.539μs   | ±31.42% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.298mb  | 6.113ms   | ±0.94%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```