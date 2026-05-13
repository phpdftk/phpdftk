# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-13 16:12:48 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 12.374ms | 2.204ms | 2.460ms | 4.412ms | 6.524ms |
| FPDF | 805.326μs | 917.915μs | 987.919μs | 1.591ms | 2.322ms |
| TCPDF | 9.970ms | 10.936ms | 12.107ms | 20.618ms | 31.074ms |
| mPDF | 25.020ms | 28.999ms | 32.796ms | 65.024ms | 104.871ms |
| Dompdf | 11.309ms | 15.894ms | 21.293ms | 73.115ms | 161.155ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 8.734mb | 5.655mb | 5.741mb | 6.375mb | 7.198mb |
| FPDF | 5.031mb | 5.031mb | 5.031mb | 5.031mb | 5.033mb |
| TCPDF | 12.869mb | 12.869mb | 12.869mb | 12.869mb | 12.869mb |
| mPDF | 17.581mb | 17.640mb | 17.678mb | 17.971mb | 18.333mb |
| Dompdf | 9.314mb | 9.534mb | 9.855mb | 12.548mb | 15.911mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.868ms | 3.026ms | 3.338ms | 5.313ms | 8.010ms |
| FPDF | 1.088ms | 1.174ms | 1.281ms | 1.931ms | 2.815ms |
| TCPDF | 14.861ms | 15.412ms | 16.801ms | 26.440ms | 38.435ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.146mb | 5.193mb | 5.252mb | 5.745mb | 6.344mb |
| FPDF | 4.365mb | 4.365mb | 4.365mb | 4.394mb | 4.455mb |
| TCPDF | 12.444mb | 12.444mb | 12.444mb | 12.444mb | 12.445mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.160ms | 1.689ms | 5.961ms |
| smalot/pdfparser | 2.003ms | 2.363ms | 5.823ms |
| setasign/fpdi | 1.917ms | 2.819ms | 29.760ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.287mb | 4.195mb | 4.541mb |
| smalot/pdfparser | 4.700mb | 4.801mb | 6.559mb |
| setasign/fpdi | 4.804mb | 4.804mb | 5.475mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 2.014ms | 1.329ms |
| smalot/pdfparser | FAIL | 1.917ms |
| setasign/fpdi | 2.964ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| EncodingBench    | benchEncodeParagraph                             |     | 50   | 5   | 4.194mb  | 41.332μs  | ±10.74% |
| EncodingBench    | benchShowTextThroughContentStream                |     | 50   | 5   | 6.290mb  | 228.807μs | ±23.59% |
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.146mb  | 2.868ms   | ±0.66%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.193mb  | 3.026ms   | ±1.23%  |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.252mb  | 3.338ms   | ±7.33%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.745mb  | 5.313ms   | ±0.78%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.344mb  | 8.010ms   | ±4.60%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.444mb | 14.861ms  | ±7.91%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.444mb | 15.412ms  | ±0.20%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.444mb | 16.801ms  | ±2.52%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.444mb | 26.440ms  | ±0.54%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.445mb | 38.435ms  | ±0.41%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.365mb  | 1.088ms   | ±3.54%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.365mb  | 1.174ms   | ±4.44%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.365mb  | 1.281ms   | ±1.22%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.394mb  | 1.931ms   | ±0.72%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.455mb  | 2.815ms   | ±0.62%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.195mb  | 1.186ms   | ±1.07%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.195mb  | 1.689ms   | ±19.86% |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.541mb  | 5.961ms   | ±0.74%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.195mb  | 2.014ms   | ±0.56%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.195mb  | 1.329ms   | ±0.44%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.700mb  | 2.003ms   | ±0.88%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.801mb  | 2.363ms   | ±0.87%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.559mb  | 5.823ms   | ±0.68%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.195mb  | 544.717μs | ±0.96%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.697mb  | 1.917ms   | ±0.92%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.804mb  | 1.917ms   | ±0.91%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.804mb  | 2.819ms   | ±0.97%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.475mb  | 29.760ms  | ±0.35%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.823mb  | 2.964ms   | ±0.33%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.756mb  | 1.489ms   | ±0.46%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.743mb  | 7.044ms   | ±0.56%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.713mb  | 5.185ms   | ±0.40%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.760mb  | 3.587ms   | ±1.83%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.195mb  | 3.186μs   | ±17.50% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.287mb  | 6.160ms   | ±0.54%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.594mb  | 1.984ms   | ±0.53%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.655mb  | 2.204ms   | ±1.07%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.741mb  | 2.460ms   | ±0.54%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.375mb  | 4.412ms   | ±1.08%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.198mb  | 6.524ms   | ±0.47%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.867mb  | 2.672ms   | ±1.55%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.116mb  | 3.488ms   | ±0.36%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.617mb  | 11.978ms  | ±5.84%  |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.910mb  | 2.876ms   | ±4.21%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.522mb  | 2.146ms   | ±1.10%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.604mb  | 637.853μs | ±1.96%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.878mb  | 2.713ms   | ±1.41%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 5.972mb  | 3.167ms   | ±0.79%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.892mb  | 2.714ms   | ±1.23%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 5.948mb  | 186.957ms | ±21.58% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.009mb  | 3.078ms   | ±1.12%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.625mb  | 5.646ms   | ±0.89%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.761mb  | 5.857ms   | ±1.03%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.869mb | 9.970ms   | ±0.75%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.869mb | 10.936ms  | ±2.09%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.869mb | 12.107ms  | ±0.67%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.869mb | 20.618ms  | ±1.69%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.869mb | 31.074ms  | ±0.33%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 5.031mb  | 805.326μs | ±2.32%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 5.031mb  | 917.915μs | ±2.59%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 5.031mb  | 987.919μs | ±0.82%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 5.031mb  | 1.591ms   | ±1.45%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 5.033mb  | 2.322ms   | ±0.86%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.581mb | 25.020ms  | ±1.84%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.640mb | 28.999ms  | ±0.56%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.678mb | 32.796ms  | ±0.53%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.971mb | 65.024ms  | ±0.31%  |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.333mb | 104.871ms | ±10.83% |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.314mb  | 11.309ms  | ±1.15%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.534mb  | 15.894ms  | ±0.76%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.855mb  | 21.293ms  | ±1.07%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.548mb | 73.115ms  | ±0.51%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.911mb | 161.155ms | ±0.47%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.822mb  | 4.680ms   | ±2.63%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.416mb  | 49.854ms  | ±0.83%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.604mb  | 1.344μs   | ±11.13% |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.604mb  | 1.463μs   | ±17.82% |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.604mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.014mb  | 223.691ms | ±13.89% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.604mb  | 440.731μs | ±1.95%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.248mb  | 2.818ms   | ±0.52%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.834mb  | 2.887ms   | ±0.57%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.604mb  | 13.240ms  | ±1.33%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.604mb  | 87.003ms  | ±0.83%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.604mb  | 14.381ms  | ±0.16%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.603mb  | 24.926ms  | ±1.17%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.664mb  | 191.812ms | ±18.20% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.861mb  | 12.618ms  | ±1.97%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.834mb  | 12.473ms  | ±0.49%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.777mb  | 12.654ms  | ±0.95%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.859mb  | 12.640ms  | ±3.71%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.976mb  | 13.080ms  | ±0.55%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.651mb  | 2.365ms   | ±1.33%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.780mb  | 12.638ms  | ±0.54%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.905mb  | 12.703ms  | ±0.68%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.734mb  | 12.374ms  | ±0.33%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```