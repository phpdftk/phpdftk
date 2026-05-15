# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-15 05:11:34 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 12.863ms | 2.435ms | 2.651ms | 4.710ms | 7.009ms |
| FPDF | 838.481μs | 953.280μs | 1.024ms | 1.638ms | 2.371ms |
| TCPDF | 10.213ms | 11.161ms | 12.454ms | 20.925ms | 31.847ms |
| mPDF | 26.525ms | 30.173ms | 34.480ms | 66.339ms | 106.167ms |
| Dompdf | 11.548ms | 16.097ms | 21.838ms | 73.776ms | 162.366ms |

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
| phpdftk | 3.159ms | 3.392ms | 3.696ms | 5.660ms | 8.494ms |
| FPDF | 1.129ms | 1.215ms | 1.319ms | 2.028ms | 2.857ms |
| TCPDF | 15.352ms | 16.769ms | 17.797ms | 27.429ms | 40.359ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.232mb | 5.279mb | 5.338mb | 5.830mb | 6.429mb |
| FPDF | 4.366mb | 4.366mb | 4.366mb | 4.395mb | 4.455mb |
| TCPDF | 12.445mb | 12.445mb | 12.445mb | 12.445mb | 12.445mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.185ms | 1.689ms | 5.936ms |
| smalot/pdfparser | 2.039ms | 2.427ms | 5.833ms |
| setasign/fpdi | 1.948ms | 2.847ms | 30.051ms |

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
| phpdftk | 2.033ms | 1.399ms |
| smalot/pdfparser | FAIL | 1.966ms |
| setasign/fpdi | 3.031ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| EncodingBench    | benchEncodeParagraph                             |     | 50   | 5   | 4.194mb  | 42.352μs  | ±1.39%  |
| EncodingBench    | benchShowTextThroughContentStream                |     | 50   | 5   | 6.364mb  | 238.638μs | ±0.84%  |
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.232mb  | 3.159ms   | ±3.07%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.279mb  | 3.392ms   | ±1.97%  |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.338mb  | 3.696ms   | ±1.31%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.830mb  | 5.660ms   | ±0.93%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.429mb  | 8.494ms   | ±0.64%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.445mb | 15.352ms  | ±1.76%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.445mb | 16.769ms  | ±0.36%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.445mb | 17.797ms  | ±0.81%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.445mb | 27.429ms  | ±0.60%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.445mb | 40.359ms  | ±1.12%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.366mb  | 1.129ms   | ±5.86%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.366mb  | 1.215ms   | ±2.43%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.366mb  | 1.319ms   | ±0.54%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.395mb  | 2.028ms   | ±0.70%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.455mb  | 2.857ms   | ±0.41%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.745mb  | 2.171ms   | ±2.04%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.806mb  | 2.435ms   | ±1.43%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.892mb  | 2.651ms   | ±0.79%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.526mb  | 4.710ms   | ±0.86%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.349mb  | 7.009ms   | ±1.10%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.952mb  | 2.885ms   | ±0.78%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.201mb  | 3.800ms   | ±1.33%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.629mb  | 12.401ms  | ±13.51% |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.995mb  | 3.077ms   | ±0.68%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.534mb  | 2.263ms   | ±8.39%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.604mb  | 706.485μs | ±3.10%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.963mb  | 2.885ms   | ±13.44% |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.058mb  | 3.395ms   | ±0.84%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.978mb  | 2.976ms   | ±1.36%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.034mb  | 150.642ms | ±30.82% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.095mb  | 3.527ms   | ±1.59%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.707mb  | 6.079ms   | ±22.72% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.843mb  | 5.870ms   | ±1.27%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.870mb | 10.213ms  | ±0.52%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.870mb | 11.161ms  | ±0.51%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.870mb | 12.454ms  | ±1.11%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.870mb | 20.925ms  | ±2.16%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.870mb | 31.847ms  | ±2.15%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 5.031mb  | 838.481μs | ±2.08%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 5.032mb  | 953.280μs | ±1.54%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 5.032mb  | 1.024ms   | ±1.42%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 5.032mb  | 1.638ms   | ±1.13%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 5.034mb  | 2.371ms   | ±0.46%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.582mb | 26.525ms  | ±1.74%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.640mb | 30.173ms  | ±0.91%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.679mb | 34.480ms  | ±2.01%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.972mb | 66.339ms  | ±0.59%  |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.333mb | 106.167ms | ±1.34%  |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.315mb  | 11.548ms  | ±3.28%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.535mb  | 16.097ms  | ±0.45%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.856mb  | 21.838ms  | ±1.16%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.548mb | 73.776ms  | ±0.50%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.911mb | 162.366ms | ±5.87%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.908mb  | 4.934ms   | ±1.68%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.347mb  | 50.385ms  | ±0.80%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.604mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.604mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.604mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.013mb  | 165.113ms | ±32.18% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.604mb  | 457.488μs | ±1.70%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.322mb  | 2.933ms   | ±0.53%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.919mb  | 3.082ms   | ±0.78%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.604mb  | 12.581ms  | ±6.36%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.604mb  | 84.137ms  | ±1.01%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.604mb  | 14.180ms  | ±0.66%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.604mb  | 24.963ms  | ±0.67%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.747mb  | 167.539ms | ±28.24% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.874mb  | 13.086ms  | ±0.95%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.846mb  | 12.763ms  | ±0.39%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.855mb  | 12.887ms  | ±0.54%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.871mb  | 13.032ms  | ±1.01%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.997mb  | 13.652ms  | ±0.57%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.661mb  | 2.451ms   | ±0.85%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.858mb  | 13.089ms  | ±0.66%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.917mb  | 13.191ms  | ±0.87%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.746mb  | 12.863ms  | ±0.69%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.195mb  | 1.256ms   | ±0.78%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.195mb  | 1.689ms   | ±1.16%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.552mb  | 5.936ms   | ±0.99%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.195mb  | 2.033ms   | ±1.02%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.195mb  | 1.399ms   | ±1.55%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.701mb  | 2.039ms   | ±1.49%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.802mb  | 2.427ms   | ±1.93%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.559mb  | 5.833ms   | ±0.92%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.195mb  | 563.655μs | ±1.24%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.697mb  | 1.966ms   | ±1.80%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.804mb  | 1.948ms   | ±1.47%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.805mb  | 2.847ms   | ±1.17%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.476mb  | 30.051ms  | ±2.57%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.824mb  | 3.031ms   | ±1.59%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.756mb  | 1.526ms   | ±1.02%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.817mb  | 7.072ms   | ±0.73%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.787mb  | 5.288ms   | ±0.62%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.834mb  | 3.787ms   | ±0.78%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.195mb  | 3.224μs   | ±5.66%  |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.298mb  | 6.185ms   | ±0.87%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```