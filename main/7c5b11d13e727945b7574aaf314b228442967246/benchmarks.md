# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-10 02:09:50 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 11.324ms | 2.182ms | 2.434ms | 4.312ms | 6.248ms |
| FPDF | 770.105μs | 826.243μs | 950.810μs | 1.487ms | 2.310ms |
| TCPDF | 9.737ms | 10.672ms | 11.716ms | 19.238ms | 28.942ms |
| mPDF | 24.566ms | 28.264ms | 30.391ms | 56.492ms | 88.731ms |
| Dompdf | 10.642ms | 14.535ms | 19.025ms | 62.464ms | 138.939ms |

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
| phpdftk | 2.922ms | 3.118ms | 3.380ms | 5.172ms | 7.385ms |
| FPDF | 1.042ms | 1.118ms | 1.206ms | 1.899ms | 2.705ms |
| TCPDF | 16.224ms | 17.008ms | 18.237ms | 27.085ms | 38.215ms |

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
| Pdf (Level 3) | 2.921ms | 3.979ms | 11.165ms |
| PdfDoc (Level 2) | 2.305ms | 2.707ms | 6.663ms |
| PdfWriter (Level 1) | 2.035ms | 2.401ms | 6.216ms |

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
| Pdf (Level 3) | 3.902ms | 10.558ms | 42.268ms |
| PdfDoc (Level 2) | 3.318ms | 8.852ms | — |

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
| Pdf (Level 3) | 3.527ms | 9.883ms | 37.784ms |
| PdfDoc (Level 2) | 2.818ms | 6.314ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.942mb | 6.494mb | 8.938mb |
| PdfDoc (Level 2) | 5.731mb | 6.225mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.100ms | 1.410ms | 4.990ms |
| smalot/pdfparser | 1.834ms | 2.173ms | 5.242ms |
| setasign/fpdi | 1.681ms | 2.411ms | 24.687ms |

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
| phpdftk | 1.708ms | 1.172ms |
| smalot/pdfparser | FAIL | 1.741ms |
| setasign/fpdi | 2.533ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.210mb  | 1.042ms   | ±3.35%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.210mb  | 1.410ms   | ±0.68%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.567mb  | 4.990ms   | ±0.27%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.210mb  | 1.708ms   | ±1.35%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.210mb  | 1.172ms   | ±0.51%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.715mb  | 1.834ms   | ±1.13%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.816mb  | 2.173ms   | ±1.33%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.573mb  | 5.242ms   | ±0.67%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.210mb  | 507.826μs | ±0.91%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.711mb  | 1.741ms   | ±0.92%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.744mb  | 1.681ms   | ±0.84%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.744mb  | 2.411ms   | ±2.20%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.491mb  | 24.687ms  | ±0.19%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.839mb  | 2.533ms   | ±0.57%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.771mb  | 1.348ms   | ±1.06%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.989mb  | 6.194ms   | ±0.34%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.961mb  | 4.675ms   | ±0.71%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.939mb  | 3.307ms   | ±1.07%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.210mb  | 2.366μs   | ±16.62% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.313mb  | 5.100ms   | ±0.52%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.560mb | 37.123ms  | ±0.22%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.149mb | 75.405ms  | ±0.67%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.853mb | 859.065ms | ±0.11%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.780mb | 23.114ms  | ±2.80%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.741mb | 50.395ms  | ±0.71%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.552mb | 449.520ms | ±0.75%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.408mb | 55.708ms  | ±9.71%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.062mb | 75.420ms  | ±1.26%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.830mb | 628.460ms | ±0.64%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.762mb | 17.667ms  | ±1.17%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.762mb | 38.803ms  | ±0.16%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.404mb | 288.462ms | ±0.22%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.310mb  | 3.902ms   | ±1.07%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.105mb  | 10.558ms  | ±0.27%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.513mb | 42.268ms  | ±0.46%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.116mb  | 3.318ms   | ±1.47%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.931mb  | 8.852ms   | ±0.64%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.838mb | 54.717ms  | ±0.45%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.697mb | 231.909ms | ±0.23%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.317mb | 901.733ms | ±0.22%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.112mb | 168.983ms | ±0.48%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.444mb | 139.721ms | ±0.41%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.623mb | 104.466ms | ±0.70%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.660mb | 140.110ms | ±0.04%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.216mb | 112.280ms | ±0.01%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.696mb | 217.266ms | ±0.25%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.362mb | 34.302ms  | ±0.57%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.282mb | 30.028ms  | ±0.17%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.212mb | 29.560ms  | ±0.74%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.513mb | 91.715ms  | ±0.41%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.270mb | 31.914ms  | ±0.21%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.483mb | 39.237ms  | ±0.06%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.899mb | 57.293ms  | ±0.26%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.178mb | 25.605ms  | ±0.48%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.681mb | 31.834ms  | ±0.14%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.809mb | 142.108ms | ±1.98%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.743mb | 90.894ms  | ±0.07%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.704mb  | 7.684ms   | ±1.04%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.518mb  | 7.179ms   | ±0.70%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.727mb  | 7.405ms   | ±0.30%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.353mb  | 2.035ms   | ±2.38%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.512mb  | 2.401ms   | ±0.79%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.087mb  | 6.216ms   | ±2.40%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.616mb  | 2.305ms   | ±1.09%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.774mb  | 2.707ms   | ±0.45%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.342mb  | 6.663ms   | ±0.78%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.959mb  | 2.921ms   | ±1.14%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.122mb  | 3.979ms   | ±0.69%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.799mb  | 11.165ms  | ±0.68%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.942mb  | 3.527ms   | ±3.15%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.494mb  | 9.883ms   | ±0.71%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.938mb  | 37.784ms  | ±2.49%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.731mb  | 2.818ms   | ±0.81%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.225mb  | 6.314ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.850mb  | 1.995ms   | ±0.99%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.910mb  | 2.182ms   | ±9.49%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.996mb  | 2.434ms   | ±1.31%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.631mb  | 4.312ms   | ±1.16%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.453mb  | 6.248ms   | ±0.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.252mb  | 3.263ms   | ±13.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.306mb  | 3.497ms   | ±1.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.734mb  | 10.684ms  | ±5.13%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.295mb  | 3.459ms   | ±2.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.638mb  | 2.096ms   | ±11.95% |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.618mb  | 534.413μs | ±9.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.068mb  | 2.685ms   | ±3.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.162mb  | 3.311ms   | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.082mb  | 3.047ms   | ±1.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.138mb  | 159.271ms | ±28.50% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.200mb  | 3.296ms   | ±7.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.811mb  | 5.041ms   | ±26.63% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.947mb  | 5.106ms   | ±0.44%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.884mb | 9.737ms   | ±1.95%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.884mb | 10.672ms  | ±5.08%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.884mb | 11.716ms  | ±1.79%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.884mb | 19.238ms  | ±0.46%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.884mb | 28.942ms  | ±0.34%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.045mb  | 770.105μs | ±1.84%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.046mb  | 826.243μs | ±2.09%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.046mb  | 950.810μs | ±2.35%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.046mb  | 1.487ms   | ±0.98%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.048mb  | 2.310ms   | ±3.06%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.596mb | 24.566ms  | ±3.03%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.655mb | 28.264ms  | ±2.02%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.693mb | 30.391ms  | ±0.48%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.986mb | 56.492ms  | ±0.65%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.348mb | 88.731ms  | ±0.23%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.329mb  | 10.642ms  | ±1.50%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.549mb  | 14.535ms  | ±0.79%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.870mb  | 19.025ms  | ±0.53%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.563mb | 62.464ms  | ±0.19%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.926mb | 138.939ms | ±1.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.012mb  | 4.420ms   | ±0.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.449mb  | 41.135ms  | ±0.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.618mb  | 1.000μs   | ±21.08% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.618mb  | 0.999μs   | ±14.29% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.618mb  | 1.290μs   | ±23.53% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.031mb  | 182.752ms | ±14.83% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.618mb  | 400.914μs | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.426mb  | 2.622ms   | ±0.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.024mb  | 2.780ms   | ±1.98%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.618mb  | 7.671ms   | ±3.32%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.618mb  | 69.214ms  | ±0.54%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.618mb  | 11.698ms  | ±3.87%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.618mb  | 20.195ms  | ±1.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.851mb  | 207.100ms | ±22.55% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.181mb  | 11.673ms  | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.154mb  | 11.606ms  | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.155mb  | 11.711ms  | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.179mb  | 11.655ms  | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.283mb  | 12.186ms  | ±2.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.959mb  | 2.520ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.158mb  | 11.791ms  | ±5.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.217mb  | 11.695ms  | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.111mb  | 11.324ms  | ±0.34%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.337mb  | 2.922ms   | ±0.73%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.383mb  | 3.118ms   | ±0.39%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.443mb  | 3.380ms   | ±1.15%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.935mb  | 5.172ms   | ±4.18%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.534mb  | 7.385ms   | ±0.42%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.459mb | 16.224ms  | ±0.77%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.459mb | 17.008ms  | ±1.80%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.459mb | 18.237ms  | ±1.25%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.459mb | 27.085ms  | ±0.59%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.460mb | 38.215ms  | ±0.24%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.380mb  | 1.042ms   | ±6.95%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.380mb  | 1.118ms   | ±0.52%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.380mb  | 1.206ms   | ±6.11%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.409mb  | 1.899ms   | ±1.61%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.469mb  | 2.705ms   | ±0.65%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.925mb  | 3.249ms   | ±0.63%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.413mb  | 5.097ms   | ±0.38%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.733mb  | 5.158ms   | ±0.90%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.692mb  | 4.359ms   | ±0.65%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.155mb  | 4.874ms   | ±0.52%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.220mb  | 4.828ms   | ±0.60%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.078mb  | 8.260ms   | ±0.84%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.631mb  | 2.557ms   | ±1.12%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.209mb  | 35.587μs  | ±0.70%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.469mb  | 219.795μs | ±1.54%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.856mb  | 17.682ms  | ±3.64%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.841mb | 149.555ms | ±0.11%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.357mb | 738.261ms | ±0.16%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```