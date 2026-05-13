# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-13 18:59:19 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 12.407ms | 2.269ms | 2.497ms | 4.545ms | 6.740ms |
| FPDF | 805.198μs | 908.358μs | 982.402μs | 1.593ms | 2.324ms |
| TCPDF | 9.943ms | 10.901ms | 12.075ms | 20.678ms | 31.539ms |
| mPDF | 25.040ms | 28.930ms | 33.055ms | 65.170ms | 105.257ms |
| Dompdf | 11.324ms | 16.112ms | 21.582ms | 73.059ms | 161.467ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 8.734mb | 5.729mb | 5.815mb | 6.449mb | 7.272mb |
| FPDF | 5.031mb | 5.031mb | 5.031mb | 5.031mb | 5.033mb |
| TCPDF | 12.869mb | 12.869mb | 12.869mb | 12.869mb | 12.869mb |
| mPDF | 17.581mb | 17.640mb | 17.678mb | 17.971mb | 18.333mb |
| Dompdf | 9.314mb | 9.534mb | 9.855mb | 12.548mb | 15.911mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.984ms | 3.160ms | 3.482ms | 5.435ms | 8.125ms |
| FPDF | 1.085ms | 1.154ms | 1.262ms | 1.951ms | 2.802ms |
| TCPDF | 14.678ms | 15.392ms | 16.844ms | 26.635ms | 38.646ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.220mb | 5.267mb | 5.326mb | 5.819mb | 6.417mb |
| FPDF | 4.365mb | 4.365mb | 4.365mb | 4.394mb | 4.455mb |
| TCPDF | 12.444mb | 12.444mb | 12.444mb | 12.444mb | 12.445mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.180ms | 1.634ms | 5.960ms |
| smalot/pdfparser | 2.004ms | 2.365ms | 5.794ms |
| setasign/fpdi | 1.922ms | 2.809ms | 29.671ms |

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
| phpdftk | 2.009ms | 1.328ms |
| smalot/pdfparser | FAIL | 1.937ms |
| setasign/fpdi | 2.973ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| EncodingBench    | benchEncodeParagraph                             |     | 50   | 5   | 4.194mb  | 41.122μs  | ±1.60%  |
| EncodingBench    | benchShowTextThroughContentStream                |     | 50   | 5   | 6.364mb  | 233.589μs | ±0.72%  |
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.220mb  | 2.984ms   | ±0.45%  |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.267mb  | 3.160ms   | ±0.25%  |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.326mb  | 3.482ms   | ±7.60%  |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.819mb  | 5.435ms   | ±0.86%  |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.417mb  | 8.125ms   | ±0.31%  |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.444mb | 14.678ms  | ±0.84%  |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.444mb | 15.392ms  | ±0.92%  |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.444mb | 16.844ms  | ±0.23%  |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.444mb | 26.635ms  | ±0.15%  |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.445mb | 38.646ms  | ±2.00%  |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.365mb  | 1.085ms   | ±1.39%  |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.365mb  | 1.154ms   | ±0.61%  |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.365mb  | 1.262ms   | ±0.97%  |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.394mb  | 1.951ms   | ±0.20%  |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.455mb  | 2.802ms   | ±0.55%  |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.195mb  | 1.186ms   | ±0.63%  |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.195mb  | 1.634ms   | ±0.67%  |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.541mb  | 5.960ms   | ±0.57%  |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.195mb  | 2.009ms   | ±1.75%  |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.195mb  | 1.328ms   | ±1.63%  |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.700mb  | 2.004ms   | ±1.20%  |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.801mb  | 2.365ms   | ±0.72%  |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.559mb  | 5.794ms   | ±0.67%  |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.195mb  | 551.863μs | ±1.28%  |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.697mb  | 1.937ms   | ±1.04%  |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.804mb  | 1.922ms   | ±1.29%  |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.804mb  | 2.809ms   | ±1.34%  |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.475mb  | 29.671ms  | ±1.13%  |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.823mb  | 2.973ms   | ±0.56%  |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.756mb  | 1.540ms   | ±1.58%  |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.817mb  | 7.116ms   | ±0.45%  |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.787mb  | 5.275ms   | ±0.48%  |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.834mb  | 3.669ms   | ±0.69%  |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.195mb  | 3.285μs   | ±18.00% |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.287mb  | 6.180ms   | ±2.06%  |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.668mb  | 2.073ms   | ±1.60%  |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.729mb  | 2.269ms   | ±0.66%  |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.815mb  | 2.497ms   | ±1.41%  |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.449mb  | 4.545ms   | ±24.49% |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.272mb  | 6.740ms   | ±0.42%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.941mb  | 2.784ms   | ±0.87%  |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.190mb  | 3.587ms   | ±2.53%  |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.617mb  | 11.993ms  | ±6.53%  |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.984mb  | 2.960ms   | ±2.52%  |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.522mb  | 2.127ms   | ±2.06%  |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.604mb  | 655.260μs | ±3.09%  |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.952mb  | 2.759ms   | ±0.31%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.046mb  | 3.210ms   | ±0.54%  |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.966mb  | 2.767ms   | ±0.47%  |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.022mb  | 158.777ms | ±21.25% |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.083mb  | 3.158ms   | ±1.00%  |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.699mb  | 5.542ms   | ±1.06%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.835mb  | 5.755ms   | ±1.04%  |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.869mb | 9.943ms   | ±0.84%  |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.869mb | 10.901ms  | ±0.44%  |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.869mb | 12.075ms  | ±0.62%  |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.869mb | 20.678ms  | ±10.56% |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.869mb | 31.539ms  | ±0.49%  |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 5.031mb  | 805.198μs | ±1.56%  |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 5.031mb  | 908.358μs | ±1.37%  |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 5.031mb  | 982.402μs | ±40.27% |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 5.031mb  | 1.593ms   | ±0.85%  |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 5.033mb  | 2.324ms   | ±1.32%  |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.581mb | 25.040ms  | ±2.04%  |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.640mb | 28.930ms  | ±0.46%  |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.678mb | 33.055ms  | ±0.20%  |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.971mb | 65.170ms  | ±25.14% |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.333mb | 105.257ms | ±0.49%  |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.314mb  | 11.324ms  | ±1.72%  |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.534mb  | 16.112ms  | ±0.59%  |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.855mb  | 21.582ms  | ±0.44%  |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.548mb | 73.059ms  | ±0.76%  |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.911mb | 161.467ms | ±0.42%  |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.896mb  | 4.823ms   | ±0.93%  |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.416mb  | 49.208ms  | ±0.40%  |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.604mb  | 1.333μs   | ±15.81% |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.604mb  | 1.333μs   | ±15.81% |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.604mb  | 1.656μs   | ±10.65% |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.014mb  | 161.781ms | ±24.55% |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.604mb  | 440.082μs | ±0.96%  |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.322mb  | 2.875ms   | ±0.60%  |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.908mb  | 2.959ms   | ±0.55%  |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.604mb  | 13.322ms  | ±3.77%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.604mb  | 88.541ms  | ±1.70%  |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.604mb  | 14.466ms  | ±0.63%  |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.603mb  | 25.676ms  | ±1.72%  |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.738mb  | 163.295ms | ±16.26% |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.861mb  | 12.605ms  | ±0.70%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.834mb  | 12.561ms  | ±1.70%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.777mb  | 12.570ms  | ±0.74%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.859mb  | 12.563ms  | ±0.56%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.976mb  | 13.147ms  | ±2.14%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.651mb  | 2.378ms   | ±2.17%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.780mb  | 12.662ms  | ±9.29%  |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.905mb  | 12.775ms  | ±0.56%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.734mb  | 12.407ms  | ±0.46%  |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```