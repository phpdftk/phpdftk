# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-10 16:03:02 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.778ms | 2.507ms | 2.751ms | 4.729ms | 6.917ms |
| FPDF | 778.605μs | 872.950μs | 958.171μs | 1.561ms | 2.282ms |
| TCPDF | 10.071ms | 10.988ms | 12.003ms | 19.332ms | 28.211ms |
| mPDF | 25.986ms | 29.976ms | 33.180ms | 61.109ms | 95.476ms |
| Dompdf | 11.439ms | 15.202ms | 20.045ms | 66.409ms | 148.593ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.139mb | 5.939mb | 6.025mb | 6.659mb | 7.481mb |
| FPDF | 5.072mb | 5.073mb | 5.073mb | 5.073mb | 5.083mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.682mb | 17.721mb | 18.014mb | 18.375mb |
| Dompdf | 9.357mb | 9.577mb | 9.898mb | 12.591mb | 15.954mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.391ms | 3.650ms | 3.795ms | 5.766ms | 8.377ms |
| FPDF | 1.075ms | 1.181ms | 1.267ms | 1.969ms | 2.762ms |
| TCPDF | 17.440ms | 19.200ms | 19.494ms | 27.946ms | 38.011ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.365mb | 5.412mb | 5.471mb | 5.964mb | 6.562mb |
| FPDF | 4.455mb | 4.455mb | 4.455mb | 4.455mb | 4.504mb |
| TCPDF | 12.487mb | 12.487mb | 12.487mb | 12.487mb | 12.487mb |

## Writer Levels Comparison — `WriterLevelsBench`

Same workload (N pages with heading + body text) rendered through each
writer level, so the abstraction overhead is visible directly. Lower is
better; the higher-level APIs (`Pdf` → `PdfDoc` → `PdfWriter`) trade
some performance for ergonomics.

### Generation Time

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 3.294ms | 4.269ms | 12.081ms |
| PdfDoc (Level 2) | 2.630ms | 3.048ms | 7.324ms |
| PdfWriter (Level 1) | 2.312ms | 2.750ms | 6.888ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 5.987mb | 6.150mb | 7.827mb |
| PdfDoc (Level 2) | 5.644mb | 5.802mb | 7.371mb |
| PdfWriter (Level 1) | 5.381mb | 5.540mb | 7.115mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 4.238ms | 11.674ms | 45.563ms |
| PdfDoc (Level 2) | 3.657ms | 9.732ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.338mb | 9.133mb | 21.541mb |
| PdfDoc (Level 2) | 6.144mb | 8.959mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 3.965ms | 11.517ms | 44.640ms |
| PdfDoc (Level 2) | 3.211ms | 7.463ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.112ms | 1.688ms | 6.055ms |
| smalot/pdfparser | 2.005ms | 2.379ms | 5.546ms |
| setasign/fpdi | 1.920ms | 2.737ms | 28.464ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.341mb | 4.243mb | 4.594mb |
| smalot/pdfparser | 4.807mb | 4.891mb | 6.601mb |
| setasign/fpdi | 4.742mb | 4.769mb | 5.526mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 2.034ms | 1.355ms |
| smalot/pdfparser | FAIL | 1.926ms |
| setasign/fpdi | 2.881ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.247ms   | ±5.00%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.688ms   | ±0.97%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.055ms   | ±1.91%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.034ms   | ±0.76%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.355ms   | ±1.17%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 2.005ms   | ±0.88%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.379ms   | ±0.73%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.546ms   | ±0.45%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 564.153μs | ±1.01%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.926ms   | ±1.06%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.920ms   | ±0.87%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.737ms   | ±0.98%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.464ms  | ±0.54%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.881ms   | ±0.75%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.541ms   | ±1.38%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 7.130ms   | ±0.91%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 5.390ms   | ±0.95%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.967mb  | 3.817ms   | ±0.61%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.844μs   | ±24.34% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.112ms   | ±0.45%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.589mb | 44.269ms  | ±0.84%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.177mb | 89.577ms  | ±0.52%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.816mb | 1.019s    | ±0.41%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.742mb | 24.206ms  | ±2.23%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.768mb | 51.908ms  | ±1.38%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.579mb | 481.355ms | ±0.99%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.370mb | 64.061ms  | ±9.29%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.089mb | 82.716ms  | ±1.26%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.858mb | 659.505ms | ±0.67%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.790mb | 17.693ms  | ±0.54%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.790mb | 41.502ms  | ±1.13%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.432mb | 280.065ms | ±1.67%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.238ms   | ±1.41%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 11.674ms  | ±0.65%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.541mb | 45.563ms  | ±0.79%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.657ms   | ±0.63%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.959mb  | 9.732ms   | ±0.36%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.866mb | 65.168ms  | ±1.19%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.725mb | 271.900ms | ±0.22%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.345mb | 1.068s    | ±0.97%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.150mb | 200.610ms | ±0.35%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.482mb | 169.207ms | ±0.19%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.586mb | 124.052ms | ±0.26%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.623mb | 164.865ms | ±0.97%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.245mb | 133.175ms | ±1.18%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.724mb | 255.914ms | ±0.37%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.325mb | 40.347ms  | ±0.38%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.311mb | 35.403ms  | ±0.49%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.241mb | 35.068ms  | ±10.12% |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.476mb | 107.615ms | ±1.13%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.233mb | 36.772ms  | ±0.70%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.446mb | 45.793ms  | ±0.72%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.861mb | 67.516ms  | ±1.13%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.140mb | 29.782ms  | ±0.42%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.709mb | 38.107ms  | ±0.55%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.837mb | 167.683ms | ±0.25%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.706mb | 109.271ms | ±0.39%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.754ms   | ±0.40%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.546mb  | 8.195ms   | ±0.70%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.755mb  | 8.182ms   | ±0.45%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.312ms   | ±0.50%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.750ms   | ±0.31%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.888ms   | ±0.57%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.630ms   | ±1.09%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.048ms   | ±0.83%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 7.324ms   | ±0.43%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.294ms   | ±0.92%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.269ms   | ±0.59%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 12.081ms  | ±0.79%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.965ms   | ±0.73%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 11.517ms  | ±1.15%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 44.640ms  | ±0.53%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.211ms   | ±0.53%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 7.463ms   | ±14.24% |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.278ms   | ±1.34%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.507ms   | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.751ms   | ±1.17%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.729ms   | ±0.99%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.481mb  | 6.917ms   | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.445ms   | ±0.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.751ms   | ±0.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.762mb  | 13.044ms  | ±7.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.622ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.355ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 612.751μs | ±3.16%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.104ms   | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.536ms   | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.110mb  | 3.130ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 211.078ms | ±32.46% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.539ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.839mb  | 5.795ms   | ±22.28% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 5.946ms   | ±0.59%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.071ms  | ±1.37%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.988ms  | ±1.06%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.003ms  | ±0.70%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.332ms  | ±26.48% |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.211ms  | ±1.25%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 778.605μs | ±1.96%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 872.950μs | ±1.26%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 958.171μs | ±8.31%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.561ms   | ±1.84%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.282ms   | ±0.30%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.986ms  | ±2.12%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 29.976ms  | ±1.00%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.180ms  | ±1.43%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 61.109ms  | ±1.44%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 95.476ms  | ±2.50%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.439ms  | ±0.54%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.202ms  | ±0.60%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 20.045ms  | ±0.71%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 66.409ms  | ±0.70%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 148.593ms | ±0.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.040mb  | 4.962ms   | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.478mb  | 53.627ms  | ±1.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.676μs   | ±75.00% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.678μs   | ±24.22% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 196.506ms | ±31.15% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 470.639μs | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.939ms   | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.312ms   | ±0.44%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.355ms  | ±6.55%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 81.303ms  | ±1.10%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.775ms  | ±1.30%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.796ms  | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 192.869ms | ±28.50% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.210mb  | 14.064ms  | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.182mb  | 13.956ms  | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.183mb  | 14.005ms  | ±1.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.207mb  | 14.028ms  | ±0.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.311mb  | 14.698ms  | ±0.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.853ms   | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.194mb  | 13.967ms  | ±0.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.245mb  | 14.079ms  | ±0.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.139mb  | 13.778ms  | ±13.18% |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.391ms   | ±6.60%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.650ms   | ±1.17%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.795ms   | ±0.74%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.766ms   | ±0.50%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.377ms   | ±1.57%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 17.440ms  | ±2.42%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 19.200ms  | ±2.68%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 19.494ms  | ±0.71%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 27.946ms  | ±0.34%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 38.011ms  | ±0.76%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.075ms   | ±2.54%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.181ms   | ±1.02%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.267ms   | ±0.75%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.969ms   | ±1.46%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.762ms   | ±1.49%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.953mb  | 3.663ms   | ±1.65%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.441mb  | 5.321ms   | ±1.01%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.761mb  | 5.493ms   | ±0.70%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.721mb  | 4.751ms   | ±6.20%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.184mb  | 5.209ms   | ±0.62%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.249mb  | 5.097ms   | ±2.83%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.107mb  | 8.547ms   | ±0.68%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.659mb  | 2.834ms   | ±0.37%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.686μs  | ±40.35% |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 234.967μs | ±0.41%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.883mb  | 20.982ms  | ±1.93%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.869mb | 179.806ms | ±0.27%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.384mb | 891.808ms | ±1.62%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```