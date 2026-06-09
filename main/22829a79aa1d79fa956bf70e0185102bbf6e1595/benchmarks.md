# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-09 21:48:38 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.688ms | 2.345ms | 2.127ms | 5.608ms | 5.498ms |
| FPDF | 919.598μs | 720.952μs | 798.487μs | 1.294ms | 2.274ms |
| TCPDF | 7.947ms | 8.502ms | 10.437ms | 15.037ms | 22.647ms |
| mPDF | 19.934ms | 22.703ms | 25.452ms | 47.472ms | 74.562ms |
| Dompdf | 11.815ms | 11.999ms | 15.516ms | 51.481ms | 115.795ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.111mb | 5.910mb | 5.996mb | 6.631mb | 7.453mb |
| FPDF | 5.045mb | 5.046mb | 5.046mb | 5.046mb | 5.048mb |
| TCPDF | 12.884mb | 12.884mb | 12.884mb | 12.884mb | 12.884mb |
| mPDF | 17.596mb | 17.655mb | 17.693mb | 17.986mb | 18.348mb |
| Dompdf | 9.329mb | 9.549mb | 9.870mb | 12.563mb | 15.926mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.597ms | 2.942ms | 2.980ms | 4.555ms | 6.356ms |
| FPDF | 874.396μs | 1.397ms | 1.038ms | 1.551ms | 2.692ms |
| TCPDF | 13.686ms | 14.194ms | 15.075ms | 21.633ms | 29.715ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.337mb | 5.383mb | 5.443mb | 5.935mb | 6.534mb |
| FPDF | 4.380mb | 4.380mb | 4.380mb | 4.409mb | 4.469mb |
| TCPDF | 12.459mb | 12.459mb | 12.459mb | 12.459mb | 12.460mb |

## Writer Levels Comparison — `WriterLevelsBench`

Same workload (N pages with heading + body text) rendered through each
writer level, so the abstraction overhead is visible directly. Lower is
better; the higher-level APIs (`Pdf` → `PdfDoc` → `PdfWriter`) trade
some performance for ergonomics.

### Generation Time

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 2.560ms | 3.693ms | 9.488ms |
| PdfDoc (Level 2) | 2.102ms | 2.417ms | 5.855ms |
| PdfWriter (Level 1) | 1.816ms | 2.174ms | 5.398ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 5.959mb | 6.122mb | 7.799mb |
| PdfDoc (Level 2) | 5.616mb | 5.774mb | 7.342mb |
| PdfWriter (Level 1) | 5.353mb | 5.512mb | 7.087mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 8.816ms | 15.541ms | 35.690ms |
| PdfDoc (Level 2) | 3.408ms | 9.386ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.310mb | 9.105mb | 21.513mb |
| PdfDoc (Level 2) | 6.116mb | 8.931mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 3.158ms | 8.976ms | 35.124ms |
| PdfDoc (Level 2) | 2.652ms | 5.713ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.942mb | 6.494mb | 8.938mb |
| PdfDoc (Level 2) | 5.731mb | 6.225mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.719ms | 1.276ms | 4.738ms |
| smalot/pdfparser | 1.549ms | 1.838ms | 4.229ms |
| setasign/fpdi | 1.453ms | 2.115ms | 22.319ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.313mb | 4.210mb | 4.567mb |
| smalot/pdfparser | 4.715mb | 4.816mb | 6.573mb |
| setasign/fpdi | 4.744mb | 4.744mb | 5.491mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 1.573ms | 1.043ms |
| smalot/pdfparser | FAIL | 1.483ms |
| setasign/fpdi | 2.232ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.210mb  | 946.663μs | ±1.13%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.210mb  | 1.276ms   | ±1.53%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.567mb  | 4.738ms   | ±0.72%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.210mb  | 1.573ms   | ±3.09%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.210mb  | 1.043ms   | ±1.08%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.715mb  | 1.549ms   | ±0.88%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.816mb  | 1.838ms   | ±1.28%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.573mb  | 4.229ms   | ±0.69%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.210mb  | 438.393μs | ±24.42%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.711mb  | 1.483ms   | ±1.27%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.744mb  | 1.453ms   | ±1.54%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.744mb  | 2.115ms   | ±1.48%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.491mb  | 22.319ms  | ±6.30%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.839mb  | 2.232ms   | ±0.99%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.771mb  | 1.142ms   | ±1.63%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.989mb  | 5.526ms   | ±1.16%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.961mb  | 4.163ms   | ±0.41%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.939mb  | 2.968ms   | ±0.88%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.210mb  | 3.085μs   | ±19.04%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.313mb  | 4.719ms   | ±1.81%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.556mb | 35.148ms  | ±31.08%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.144mb | 69.190ms  | ±0.68%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.848mb | 795.317ms | ±1.16%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.775mb | 18.265ms  | ±1.82%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.736mb | 40.265ms  | ±0.48%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.547mb | 358.084ms | ±5.73%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.403mb | 49.423ms  | ±9.73%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.057mb | 63.123ms  | ±1.43%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.826mb | 526.895ms | ±1.82%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.757mb | 47.869ms  | ±51.32%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.757mb | 31.891ms  | ±0.43%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.400mb | 218.050ms | ±6.07%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.310mb  | 8.816ms   | ±82.21%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.105mb  | 15.541ms  | ±105.11% |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.513mb | 35.690ms  | ±21.82%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.116mb  | 3.408ms   | ±30.31%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.931mb  | 9.386ms   | ±68.88%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.833mb | 49.651ms  | ±0.47%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.692mb | 211.349ms | ±0.36%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.312mb | 823.171ms | ±0.39%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.108mb | 153.724ms | ±0.50%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.439mb | 130.980ms | ±0.64%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.618mb | 96.709ms  | ±0.60%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.656mb | 130.062ms | ±0.49%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.212mb | 102.934ms | ±0.67%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.691mb | 200.884ms | ±1.03%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.358mb | 31.329ms  | ±0.25%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.278mb | 27.066ms  | ±0.57%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.208mb | 26.598ms  | ±0.77%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.509mb | 84.339ms  | ±0.30%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.266mb | 29.209ms  | ±0.75%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.478mb | 35.888ms  | ±0.40%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.894mb | 51.892ms  | ±0.40%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.173mb | 23.489ms  | ±1.44%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.676mb | 29.175ms  | ±0.47%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.804mb | 131.497ms | ±0.49%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.738mb | 86.639ms  | ±3.11%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.704mb  | 7.585ms   | ±65.53%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.518mb  | 6.576ms   | ±112.29% |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.727mb  | 6.825ms   | ±25.20%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.353mb  | 1.816ms   | ±6.40%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.512mb  | 2.174ms   | ±9.47%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.087mb  | 5.398ms   | ±0.63%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.616mb  | 2.102ms   | ±82.63%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.774mb  | 2.417ms   | ±0.78%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.342mb  | 5.855ms   | ±1.05%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.959mb  | 2.560ms   | ±1.45%   |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.122mb  | 3.693ms   | ±39.45%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.799mb  | 9.488ms   | ±0.53%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.942mb  | 3.158ms   | ±87.93%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.494mb  | 8.976ms   | ±1.97%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.938mb  | 35.124ms  | ±0.60%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.731mb  | 2.652ms   | ±33.36%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.225mb  | 5.713ms   | ±127.25% |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.850mb  | 1.788ms   | ±5.78%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.910mb  | 2.345ms   | ±120.61% |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.996mb  | 2.127ms   | ±32.67%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.631mb  | 5.608ms   | ±95.64%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.453mb  | 5.498ms   | ±94.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.252mb  | 2.785ms   | ±44.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.306mb  | 2.962ms   | ±111.36% |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.734mb  | 10.089ms  | ±88.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.295mb  | 4.040ms   | ±76.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.638mb  | 1.839ms   | ±89.96%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.618mb  | 5.497ms   | ±124.20% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.068mb  | 2.361ms   | ±137.40% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.162mb  | 2.785ms   | ±72.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.082mb  | 2.579ms   | ±23.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.138mb  | 190.075ms | ±10.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.200mb  | 2.779ms   | ±95.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.811mb  | 4.914ms   | ±37.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.947mb  | 15.105ms  | ±67.28%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.884mb | 7.947ms   | ±112.64% |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.884mb | 8.502ms   | ±0.75%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.884mb | 10.437ms  | ±47.68%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.884mb | 15.037ms  | ±5.59%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.884mb | 22.647ms  | ±34.86%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.045mb  | 919.598μs | ±103.37% |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.046mb  | 720.952μs | ±5.78%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.046mb  | 798.487μs | ±68.10%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.046mb  | 1.294ms   | ±8.33%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.048mb  | 2.274ms   | ±80.96%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.596mb | 19.934ms  | ±2.45%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.655mb | 22.703ms  | ±4.05%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.693mb | 25.452ms  | ±0.28%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.986mb | 47.472ms  | ±14.09%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.348mb | 74.562ms  | ±7.49%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.329mb  | 11.815ms  | ±66.71%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.549mb  | 11.999ms  | ±104.68% |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.870mb  | 15.516ms  | ±0.76%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.563mb | 51.481ms  | ±18.33%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.926mb | 115.795ms | ±0.55%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.012mb  | 3.833ms   | ±4.98%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.449mb  | 42.171ms  | ±0.71%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.618mb  | 1.333μs   | ±15.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.618mb  | 1.344μs   | ±11.13%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.618mb  | 1.656μs   | ±10.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.031mb  | 177.165ms | ±16.04%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.618mb  | 375.653μs | ±0.77%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.426mb  | 2.251ms   | ±0.47%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.024mb  | 6.198ms   | ±125.89% |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.618mb  | 9.055ms   | ±22.95%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.618mb  | 62.877ms  | ±0.25%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.618mb  | 11.727ms  | ±0.67%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.618mb  | 20.215ms  | ±1.60%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.851mb  | 162.880ms | ±36.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.181mb  | 10.878ms  | ±0.72%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.154mb  | 16.919ms  | ±81.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.155mb  | 11.015ms  | ±10.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.179mb  | 10.848ms  | ±0.66%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.283mb  | 11.371ms  | ±14.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.959mb  | 2.232ms   | ±0.55%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.158mb  | 10.894ms  | ±0.93%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.217mb  | 10.950ms  | ±0.83%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.111mb  | 10.688ms  | ±10.40%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.337mb  | 2.597ms   | ±1.41%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.383mb  | 2.942ms   | ±37.35%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.443mb  | 2.980ms   | ±0.95%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.935mb  | 4.555ms   | ±1.19%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.534mb  | 6.356ms   | ±0.48%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.459mb | 13.686ms  | ±11.84%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.459mb | 14.194ms  | ±0.60%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.459mb | 15.075ms  | ±0.49%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.459mb | 21.633ms  | ±0.57%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.460mb | 29.715ms  | ±0.42%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.380mb  | 874.396μs | ±0.07%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.380mb  | 1.397ms   | ±104.73% |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.380mb  | 1.038ms   | ±1.89%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.409mb  | 1.551ms   | ±1.43%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.469mb  | 2.692ms   | ±80.74%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.925mb  | 2.738ms   | ±0.47%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.413mb  | 4.180ms   | ±0.65%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.733mb  | 4.307ms   | ±0.44%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.692mb  | 3.668ms   | ±1.80%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.155mb  | 4.074ms   | ±1.25%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.220mb  | 3.903ms   | ±0.76%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.078mb  | 6.599ms   | ±0.26%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.631mb  | 2.187ms   | ±0.41%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.209mb  | 33.817μs  | ±1.44%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.469mb  | 184.622μs | ±0.89%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.856mb  | 15.393ms  | ±0.69%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.841mb | 136.349ms | ±0.66%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.357mb | 689.691ms | ±3.24%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```