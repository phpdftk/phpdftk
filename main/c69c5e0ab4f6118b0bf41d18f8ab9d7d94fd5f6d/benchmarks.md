# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-15 02:11:15 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.007ms | 2.247ms | 2.483ms | 4.425ms | 6.692ms |
| FPDF | 842.235μs | 914.686μs | 1.003ms | 1.604ms | 2.304ms |
| TCPDF | 10.067ms | 10.989ms | 11.943ms | 19.209ms | 28.156ms |
| mPDF | 25.803ms | 29.770ms | 32.679ms | 60.717ms | 95.266ms |
| Dompdf | 11.101ms | 15.133ms | 19.824ms | 65.673ms | 149.434ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 8.737mb | 5.731mb | 5.817mb | 6.451mb | 7.274mb |
| FPDF | 5.031mb | 5.031mb | 5.031mb | 5.031mb | 5.033mb |
| TCPDF | 12.869mb | 12.869mb | 12.869mb | 12.869mb | 12.869mb |
| mPDF | 17.581mb | 17.640mb | 17.678mb | 17.971mb | 18.333mb |
| Dompdf | 9.314mb | 9.534mb | 9.855mb | 12.548mb | 15.911mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.998ms | 3.142ms | 3.397ms | 5.385ms | 7.855ms |
| FPDF | 1.111ms | 1.216ms | 1.310ms | 1.970ms | 2.799ms |
| TCPDF | 14.741ms | 15.559ms | 16.627ms | 25.145ms | 35.360ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.222mb | 5.269mb | 5.328mb | 5.821mb | 6.419mb |
| FPDF | 4.365mb | 4.365mb | 4.365mb | 4.394mb | 4.455mb |
| TCPDF | 12.444mb | 12.444mb | 12.444mb | 12.444mb | 12.445mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.074ms | 1.612ms | 5.993ms |
| smalot/pdfparser | 1.997ms | 2.385ms | 5.485ms |
| setasign/fpdi | 1.855ms | 2.677ms | 28.249ms |

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
| phpdftk | 1.975ms | 1.325ms |
| smalot/pdfparser | FAIL | 1.898ms |
| setasign/fpdi | 2.852ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| EncodingBench    | benchEncodeParagraph                             |     | 50   | 5   | 4.194mb  | 42.960μs  | ±0.88%  |
| EncodingBench    | benchShowTextThroughContentStream                |     | 50   | 5   | 6.366mb  | 226.335μs | ±1.33%  |
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.222mb  | 2.998ms   | ±2.87%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.269mb  | 3.142ms   | ±1.12%  |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.328mb  | 3.397ms   | ±0.49%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.821mb  | 5.385ms   | ±0.78%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.419mb  | 7.855ms   | ±0.68%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.444mb | 14.741ms  | ±7.70%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.444mb | 15.559ms  | ±0.23%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.444mb | 16.627ms  | ±0.16%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.444mb | 25.145ms  | ±0.62%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.445mb | 35.360ms  | ±0.23%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.365mb  | 1.111ms   | ±1.41%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.365mb  | 1.216ms   | ±1.38%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.365mb  | 1.310ms   | ±1.62%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.394mb  | 1.970ms   | ±0.55%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.455mb  | 2.799ms   | ±1.08%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.195mb  | 1.168ms   | ±2.22%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.195mb  | 1.612ms   | ±0.94%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.541mb  | 5.993ms   | ±1.41%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.195mb  | 1.975ms   | ±1.06%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.195mb  | 1.325ms   | ±1.74%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.700mb  | 1.997ms   | ±0.69%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.801mb  | 2.385ms   | ±1.02%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.559mb  | 5.485ms   | ±0.48%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.195mb  | 557.992μs | ±2.43%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.697mb  | 1.898ms   | ±1.20%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.804mb  | 1.855ms   | ±0.76%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.804mb  | 2.677ms   | ±1.29%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.475mb  | 28.249ms  | ±1.80%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.823mb  | 2.852ms   | ±0.74%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.756mb  | 1.489ms   | ±1.61%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.819mb  | 6.906ms   | ±0.52%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.789mb  | 5.169ms   | ±1.04%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.836mb  | 3.653ms   | ±0.48%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.195mb  | 5.023μs   | ±27.61% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.287mb  | 6.074ms   | ±2.47%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.670mb  | 2.045ms   | ±0.84%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.731mb  | 2.247ms   | ±0.55%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.817mb  | 2.483ms   | ±0.96%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.451mb  | 4.425ms   | ±0.70%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.274mb  | 6.692ms   | ±2.28%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.943mb  | 2.912ms   | ±2.10%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.192mb  | 3.553ms   | ±0.67%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.620mb  | 12.645ms  | ±39.42% |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.986mb  | 2.868ms   | ±0.94%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.524mb  | 2.101ms   | ±0.63%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.604mb  | 613.323μs | ±3.36%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.954mb  | 2.732ms   | ±0.44%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.048mb  | 3.115ms   | ±1.00%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.968mb  | 2.755ms   | ±9.86%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.024mb  | 287.343ms | ±15.50% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.085mb  | 3.056ms   | ±0.69%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.701mb  | 5.530ms   | ±0.89%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.837mb  | 5.750ms   | ±0.70%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.869mb | 10.067ms  | ±1.09%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.869mb | 10.989ms  | ±0.70%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.869mb | 11.943ms  | ±0.62%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.869mb | 19.209ms  | ±0.54%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.869mb | 28.156ms  | ±1.03%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 5.031mb  | 842.235μs | ±9.45%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 5.031mb  | 914.686μs | ±2.21%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 5.031mb  | 1.003ms   | ±0.64%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 5.031mb  | 1.604ms   | ±1.34%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 5.033mb  | 2.304ms   | ±0.95%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.581mb | 25.803ms  | ±2.17%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.640mb | 29.770ms  | ±1.21%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.678mb | 32.679ms  | ±0.40%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.971mb | 60.717ms  | ±0.33%  |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.333mb | 95.266ms  | ±0.48%  |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.314mb  | 11.101ms  | ±1.17%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.534mb  | 15.133ms  | ±0.85%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.855mb  | 19.824ms  | ±0.39%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.548mb | 65.673ms  | ±0.47%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.911mb | 149.434ms | ±0.95%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.898mb  | 4.658ms   | ±1.72%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.338mb  | 53.638ms  | ±0.53%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.604mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.604mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.604mb  | 1.674μs   | ±61.11% |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.014mb  | 232.254ms | ±26.09% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.604mb  | 462.744μs | ±1.11%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.324mb  | 2.746ms   | ±0.58%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.910mb  | 2.902ms   | ±1.09%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.604mb  | 11.844ms  | ±8.60%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.604mb  | 80.397ms  | ±0.75%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.604mb  | 14.787ms  | ±0.88%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.603mb  | 25.866ms  | ±0.87%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.740mb  | 201.752ms | ±18.13% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.864mb  | 13.155ms  | ±0.50%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.837mb  | 13.068ms  | ±2.49%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.780mb  | 13.278ms  | ±0.52%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.862mb  | 13.567ms  | ±11.89% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.979mb  | 13.661ms  | ±10.78% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.652mb  | 2.325ms   | ±1.11%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.783mb  | 13.167ms  | ±0.65%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.908mb  | 13.466ms  | ±0.98%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.737mb  | 13.007ms  | ±0.74%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```