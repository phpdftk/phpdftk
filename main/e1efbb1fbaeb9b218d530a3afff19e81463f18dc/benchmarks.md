# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-22 03:34:28 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.658ms | 2.729ms | 2.858ms | 5.014ms | 7.288ms |
| FPDF | 825.871μs | 927.204μs | 976.068μs | 1.591ms | 2.362ms |
| TCPDF | 10.862ms | 11.781ms | 13.054ms | 21.786ms | 32.594ms |
| mPDF | 28.304ms | 29.252ms | 36.190ms | 66.992ms | 108.692ms |
| Dompdf | 11.914ms | 16.610ms | 22.823ms | 75.427ms | 166.535ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.218mb | 5.947mb | 6.033mb | 6.667mb | 7.490mb |
| FPDF | 5.072mb | 5.072mb | 5.072mb | 5.072mb | 5.084mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.683mb | 17.721mb | 18.014mb | 18.376mb |
| Dompdf | 9.357mb | 9.577mb | 9.898mb | 12.591mb | 15.954mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.316ms | 3.517ms | 3.774ms | 5.791ms | 11.243ms |
| FPDF | 998.493μs | 1.108ms | 1.194ms | 1.868ms | 2.750ms |
| TCPDF | 14.825ms | 15.305ms | 16.737ms | 26.334ms | 39.992ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.373mb | 5.420mb | 5.479mb | 5.972mb | 6.570mb |
| FPDF | 4.455mb | 4.455mb | 4.455mb | 4.455mb | 4.505mb |
| TCPDF | 12.487mb | 12.487mb | 12.487mb | 12.487mb | 12.488mb |

## Writer Levels Comparison — `WriterLevelsBench`

Same workload (N pages with heading + body text) rendered through each
writer level, so the abstraction overhead is visible directly. Lower is
better; the higher-level APIs (`Pdf` → `PdfDoc` → `PdfWriter`) trade
some performance for ergonomics.

### Generation Time

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 3.344ms | 4.353ms | 12.569ms |
| PdfDoc (Level 2) | 2.675ms | 3.114ms | 7.498ms |
| PdfWriter (Level 1) | 2.271ms | 2.738ms | 6.916ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 6.057mb | 6.220mb | 7.897mb |
| PdfDoc (Level 2) | 5.714mb | 5.872mb | 7.441mb |
| PdfWriter (Level 1) | 5.389mb | 5.548mb | 7.123mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 4.497ms | 12.453ms | 47.273ms |
| PdfDoc (Level 2) | 3.843ms | 10.384ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.408mb | 9.203mb | 21.611mb |
| PdfDoc (Level 2) | 6.214mb | 9.029mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 4.315ms | 12.371ms | 45.744ms |
| PdfDoc (Level 2) | 3.603ms | 7.558ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.372ms | 1.725ms | 6.060ms |
| smalot/pdfparser | 2.061ms | 2.446ms | 5.992ms |
| setasign/fpdi | 2.038ms | 2.946ms | 30.163ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.341mb | 4.243mb | 4.595mb |
| smalot/pdfparser | 4.800mb | 4.884mb | 6.601mb |
| setasign/fpdi | 4.743mb | 4.769mb | 5.526mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 2.068ms | 1.415ms |
| smalot/pdfparser | FAIL | 2.005ms |
| setasign/fpdi | 3.118ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.316ms   | ±0.36%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.517ms   | ±0.26%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.774ms   | ±0.95%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.791ms   | ±0.71%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 11.243ms  | ±84.31% |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.825ms  | ±1.73%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.305ms  | ±0.35%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.737ms  | ±0.86%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.334ms  | ±0.77%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 39.992ms  | ±21.94% |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 998.493μs | ±2.50%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.108ms   | ±0.83%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.194ms   | ±0.89%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.868ms   | ±0.41%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.750ms   | ±0.57%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.271ms   | ±0.93%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.738ms   | ±0.56%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.916ms   | ±0.96%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.675ms   | ±0.77%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.114ms   | ±0.77%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.498ms   | ±1.06%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.344ms   | ±0.89%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.353ms   | ±0.50%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.569ms  | ±0.17%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.670mb | 88.986ms  | ±5.24%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 26.905mb | 390.465ms | ±1.12%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 63.944mb | 1.530s    | ±0.31%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.885mb | 269.181ms | ±0.32%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.649mb | 203.960ms | ±0.45%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.569mb | 167.739ms | ±0.40%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 20.790mb | 229.164ms | ±0.08%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 20.023mb | 188.910ms | ±0.80%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.221mb | 355.716ms | ±0.77%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.127mb | 54.231ms  | ±0.90%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.039mb | 47.222ms  | ±0.22%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.966mb | 44.078ms  | ±0.22%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.373mb | 145.938ms | ±0.46%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 17.028mb | 49.166ms  | ±0.21%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.254mb | 62.396ms  | ±0.67%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.718mb | 93.239ms  | ±0.36%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.923mb | 40.640ms  | ±0.18%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.857mb | 48.320ms  | ±0.46%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.885mb | 50.506ms  | ±1.27%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.854mb | 49.272ms  | ±2.01%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.876mb | 47.604ms  | ±0.14%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.798mb | 74.303ms  | ±0.68%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.906mb | 46.212ms  | ±0.62%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.520mb | 41.498ms  | ±0.70%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.822mb | 44.891ms  | ±0.84%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.843mb | 49.758ms  | ±1.14%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.836mb | 49.791ms  | ±0.28%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.067mb | 44.033ms  | ±1.75%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.239mb | 240.103ms | ±0.27%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.494mb | 177.222ms | ±0.29%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.353mb | 61.748ms  | ±0.46%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.959mb | 128.213ms | ±1.14%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 64.718mb | 1.474s    | ±0.22%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.244mb | 27.471ms  | ±1.51%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.270mb | 61.493ms  | ±1.60%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 58.016mb | 570.879ms | ±0.48%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.528mb | 66.510ms  | ±9.42%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.182mb | 89.979ms  | ±0.68%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.951mb | 749.037ms | ±1.10%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.293mb | 19.048ms  | ±1.27%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.293mb | 43.897ms  | ±0.93%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.935mb | 326.615ms | ±1.05%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.264ms   | ±2.58%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.725ms   | ±0.70%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.060ms   | ±0.77%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.068ms   | ±1.30%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.415ms   | ±1.09%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.061ms   | ±1.27%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.446ms   | ±0.63%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.992ms   | ±0.71%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 574.260μs | ±2.54%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 2.005ms   | ±0.89%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 2.038ms   | ±0.86%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.946ms   | ±1.31%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.163ms  | ±2.47%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.118ms   | ±0.93%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.666ms   | ±2.33%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.494ms   | ±0.57%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.662ms   | ±2.56%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 4.005ms   | ±4.06%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.761μs   | ±25.98% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.372ms   | ±1.02%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.764mb  | 28.628ms  | ±0.32%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.201mb | 254.008ms | ±0.18%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.726mb | 1.257s    | ±0.50%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.209mb | 197.011ms | ±1.03%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 27.044mb | 1.015s    | ±0.56%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 24.131mb | 817.074ms | ±0.56%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.315ms   | ±2.28%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 12.371ms  | ±0.76%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.744ms  | ±0.56%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.603ms   | ±1.62%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.558ms   | ±1.55%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.405ms   | ±1.39%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.729ms   | ±2.15%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.858ms   | ±1.02%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 5.014ms   | ±1.40%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.288ms   | ±1.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.775ms   | ±1.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.989ms   | ±1.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.904ms  | ±9.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.884ms   | ±1.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.580ms   | ±2.34%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 685.882μs | ±26.00% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.525ms   | ±2.24%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.863ms   | ±2.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.397ms   | ±3.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 154.283ms | ±42.75% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.783ms   | ±0.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.279ms   | ±22.35% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.304ms   | ±1.19%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.862ms  | ±1.93%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.781ms  | ±4.24%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 13.054ms  | ±2.01%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 21.786ms  | ±1.93%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 32.594ms  | ±1.21%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 825.871μs | ±3.32%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 927.204μs | ±2.51%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 976.068μs | ±0.88%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.591ms   | ±1.70%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.362ms   | ±1.02%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 28.304ms  | ±3.17%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.252ms  | ±3.31%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 36.190ms  | ±3.46%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 66.992ms  | ±1.81%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 108.692ms | ±1.06%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.914ms  | ±1.15%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.610ms  | ±1.81%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 22.823ms  | ±1.39%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 75.427ms  | ±1.28%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 166.535ms | ±1.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.077ms   | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.917ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.666μs   | ±12.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 171.888ms | ±42.20% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 472.580μs | ±0.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.105ms   | ±1.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.703ms   | ±1.77%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.908ms  | ±5.79%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 84.904ms  | ±0.87%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.210ms  | ±0.67%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.002ms  | ±0.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 166.982ms | ±40.38% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.840ms  | ±1.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.748ms  | ±0.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 14.011ms  | ±1.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.913ms  | ±2.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.254ms  | ±4.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.287ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.966ms  | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 14.039ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.658ms  | ±0.73%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.835mb  | 15.058ms  | ±1.91%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.635mb  | 13.683ms  | ±0.65%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.955mb  | 21.251ms  | ±2.16%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.903mb  | 19.112ms  | ±0.97%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 10.041mb | 25.129ms  | ±0.63%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 9.405mb  | 13.582ms  | ±4.01%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.589mb  | 17.713ms  | ±1.00%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 11.901mb | 37.839ms  | ±1.31%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 7.778mb  | 6.814ms   | ±1.71%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.408μs  | ±1.39%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 254.521μs | ±1.93%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.497ms   | ±1.37%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.453ms  | ±0.89%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.273ms  | ±3.69%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.843ms   | ±0.64%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 10.384ms  | ±2.37%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 9.480ms   | ±0.96%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.884ms   | ±0.64%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.939ms   | ±1.59%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.049μs   | ±16.64% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.044μs   | ±40.77% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.431mb | 20.929ms  | ±0.45%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.431mb | 19.682ms  | ±3.17%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```