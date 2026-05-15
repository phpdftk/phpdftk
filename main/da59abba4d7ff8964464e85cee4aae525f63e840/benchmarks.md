# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-15 02:23:51 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.072ms | 2.258ms | 2.496ms | 4.470ms | 6.683ms |
| FPDF | 832.313μs | 917.769μs | 1.016ms | 1.620ms | 2.334ms |
| TCPDF | 10.086ms | 10.995ms | 11.961ms | 19.165ms | 28.251ms |
| mPDF | 25.728ms | 29.133ms | 32.804ms | 60.723ms | 95.197ms |
| Dompdf | 11.179ms | 15.562ms | 20.150ms | 66.096ms | 148.090ms |

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
| phpdftk | 2.995ms | 3.154ms | 3.428ms | 5.349ms | 7.942ms |
| FPDF | 1.149ms | 1.232ms | 1.371ms | 2.008ms | 2.841ms |
| TCPDF | 14.780ms | 15.828ms | 17.190ms | 25.129ms | 35.639ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.222mb | 5.269mb | 5.328mb | 5.821mb | 6.419mb |
| FPDF | 4.365mb | 4.365mb | 4.365mb | 4.394mb | 4.455mb |
| TCPDF | 12.444mb | 12.444mb | 12.444mb | 12.444mb | 12.445mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.061ms | 1.609ms | 6.058ms |
| smalot/pdfparser | 1.992ms | 2.351ms | 5.556ms |
| setasign/fpdi | 1.871ms | 2.664ms | 28.377ms |

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
| phpdftk | 1.974ms | 1.319ms |
| smalot/pdfparser | FAIL | 1.903ms |
| setasign/fpdi | 2.848ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| EncodingBench    | benchEncodeParagraph                             |     | 50   | 5   | 4.194mb  | 43.062μs  | ±0.81%  |
| EncodingBench    | benchShowTextThroughContentStream                |     | 50   | 5   | 6.366mb  | 227.173μs | ±0.79%  |
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.222mb  | 2.995ms   | ±1.23%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.269mb  | 3.154ms   | ±0.38%  |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.328mb  | 3.428ms   | ±1.93%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.821mb  | 5.349ms   | ±1.60%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.419mb  | 7.942ms   | ±0.87%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.444mb | 14.780ms  | ±0.70%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.444mb | 15.828ms  | ±0.60%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.444mb | 17.190ms  | ±0.98%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.444mb | 25.129ms  | ±0.23%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.445mb | 35.639ms  | ±0.19%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.365mb  | 1.149ms   | ±1.41%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.365mb  | 1.232ms   | ±1.78%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.365mb  | 1.371ms   | ±3.25%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.394mb  | 2.008ms   | ±0.31%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.455mb  | 2.841ms   | ±1.74%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.670mb  | 2.059ms   | ±0.88%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.731mb  | 2.258ms   | ±0.84%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.817mb  | 2.496ms   | ±0.64%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.451mb  | 4.470ms   | ±0.34%  |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.274mb  | 6.683ms   | ±0.78%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.943mb  | 2.694ms   | ±0.94%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.192mb  | 3.483ms   | ±0.81%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.620mb  | 12.778ms  | ±5.17%  |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.986mb  | 2.908ms   | ±1.08%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.524mb  | 2.107ms   | ±1.08%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.604mb  | 636.097μs | ±2.39%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.954mb  | 2.717ms   | ±0.71%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.048mb  | 3.127ms   | ±0.29%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.968mb  | 2.746ms   | ±1.04%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.024mb  | 225.796ms | ±27.43% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.085mb  | 3.111ms   | ±0.72%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.701mb  | 5.505ms   | ±22.85% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.837mb  | 5.752ms   | ±0.50%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.869mb | 10.086ms  | ±0.61%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.869mb | 10.995ms  | ±0.44%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.869mb | 11.961ms  | ±0.94%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.869mb | 19.165ms  | ±0.40%  |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.869mb | 28.251ms  | ±0.75%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 5.031mb  | 832.313μs | ±0.49%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 5.031mb  | 917.769μs | ±0.89%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 5.031mb  | 1.016ms   | ±1.08%  |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 5.031mb  | 1.620ms   | ±0.73%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 5.033mb  | 2.334ms   | ±0.61%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.581mb | 25.728ms  | ±2.15%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.640mb | 29.133ms  | ±0.33%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.678mb | 32.804ms  | ±0.38%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.971mb | 60.723ms  | ±0.24%  |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.333mb | 95.197ms  | ±0.69%  |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.314mb  | 11.179ms  | ±3.44%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.534mb  | 15.562ms  | ±9.34%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.855mb  | 20.150ms  | ±0.44%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.548mb | 66.096ms  | ±4.46%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.911mb | 148.090ms | ±0.28%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.898mb  | 4.641ms   | ±0.57%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.338mb  | 53.087ms  | ±0.97%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.604mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.604mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.604mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.014mb  | 250.129ms | ±27.94% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.604mb  | 460.080μs | ±0.99%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.324mb  | 2.799ms   | ±1.01%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.910mb  | 2.909ms   | ±0.73%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.604mb  | 11.860ms  | ±4.44%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.604mb  | 80.004ms  | ±1.03%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.604mb  | 14.935ms  | ±0.60%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.603mb  | 26.340ms  | ±1.85%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.740mb  | 201.265ms | ±15.69% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.864mb  | 13.265ms  | ±1.00%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.837mb  | 13.039ms  | ±1.45%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.780mb  | 13.166ms  | ±0.69%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.862mb  | 13.220ms  | ±0.36%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.979mb  | 13.776ms  | ±0.30%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.652mb  | 2.299ms   | ±0.75%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.783mb  | 13.277ms  | ±0.39%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.908mb  | 13.503ms  | ±0.65%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.737mb  | 13.072ms  | ±0.99%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.195mb  | 1.175ms   | ±1.75%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.195mb  | 1.609ms   | ±0.73%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.541mb  | 6.058ms   | ±0.55%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.195mb  | 1.974ms   | ±0.38%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.195mb  | 1.319ms   | ±1.06%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.700mb  | 1.992ms   | ±1.05%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.801mb  | 2.351ms   | ±0.50%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.559mb  | 5.556ms   | ±0.39%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.195mb  | 565.464μs | ±1.39%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.697mb  | 1.903ms   | ±0.91%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.804mb  | 1.871ms   | ±0.94%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.804mb  | 2.664ms   | ±0.43%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.475mb  | 28.377ms  | ±2.83%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.823mb  | 2.848ms   | ±0.34%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.756mb  | 1.490ms   | ±1.09%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.819mb  | 6.838ms   | ±0.47%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.789mb  | 5.134ms   | ±0.60%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.836mb  | 3.601ms   | ±0.60%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.195mb  | 3.644μs   | ±25.48% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.287mb  | 6.061ms   | ±2.91%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```