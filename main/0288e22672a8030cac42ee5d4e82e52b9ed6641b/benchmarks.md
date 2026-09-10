# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-10 00:22:16 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.645ms | 2.582ms | 2.855ms | 4.848ms | 7.191ms |
| FPDF | 803.631μs | 911.344μs | 1.003ms | 1.591ms | 2.372ms |
| TCPDF | 11.556ms | 12.004ms | 13.148ms | 21.648ms | 33.161ms |
| mPDF | 29.529ms | 32.446ms | 36.904ms | 69.860ms | 107.822ms |
| Dompdf | 11.681ms | 16.355ms | 21.937ms | 73.980ms | 166.525ms |

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
| phpdftk | 3.494ms | 3.688ms | 3.906ms | 5.886ms | 8.453ms |
| FPDF | 1.100ms | 1.145ms | 1.264ms | 1.937ms | 2.781ms |
| TCPDF | 15.479ms | 16.198ms | 17.518ms | 27.455ms | 39.013ms |

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
| Pdf (Level 3) | 3.449ms | 4.543ms | 12.772ms |
| PdfDoc (Level 2) | 2.767ms | 3.257ms | 7.549ms |
| PdfWriter (Level 1) | 2.370ms | 2.814ms | 6.942ms |

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
| Pdf (Level 3) | 4.499ms | 12.543ms | 47.472ms |
| PdfDoc (Level 2) | 3.869ms | 10.020ms | — |

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
| Pdf (Level 3) | 4.247ms | 11.732ms | 45.019ms |
| PdfDoc (Level 2) | 3.371ms | 7.391ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.226ms | 1.681ms | 6.004ms |
| smalot/pdfparser | 2.053ms | 2.456ms | 5.750ms |
| setasign/fpdi | 2.032ms | 2.930ms | 29.576ms |

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
| phpdftk | 2.040ms | 1.373ms |
| smalot/pdfparser | FAIL | 1.950ms |
| setasign/fpdi | 2.989ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.494ms   | ±0.83%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.688ms   | ±0.73%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.906ms   | ±0.98%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.886ms   | ±0.44%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.453ms   | ±0.14%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.479ms  | ±2.17%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.198ms  | ±1.50%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.518ms  | ±0.82%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 27.455ms  | ±0.23%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 39.013ms  | ±0.25%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.100ms   | ±3.26%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.145ms   | ±1.84%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.264ms   | ±1.36%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.937ms   | ±0.68%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.781ms   | ±1.12%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.370ms   | ±0.85%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.814ms   | ±1.83%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.942ms   | ±1.93%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.767ms   | ±1.94%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.257ms   | ±0.41%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.549ms   | ±1.07%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.449ms   | ±0.25%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.543ms   | ±2.57%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.772ms  | ±0.45%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.539mb | 88.149ms  | ±0.82%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.455mb | 389.457ms | ±2.08%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.488mb | 1.500s    | ±0.33%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.605mb | 269.859ms | ±0.34%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.567mb | 201.963ms | ±0.63%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.348mb | 164.148ms | ±0.03%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.419mb | 224.911ms | ±0.08%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.956mb | 186.770ms | ±0.74%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.510mb | 352.774ms | ±0.40%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.051mb | 53.100ms  | ±0.70%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.967mb | 46.303ms  | ±1.44%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.893mb | 43.157ms  | ±0.44%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.236mb | 146.195ms | ±0.62%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.956mb | 49.229ms  | ±0.33%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.174mb | 62.263ms  | ±0.25%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.602mb | 95.576ms  | ±1.67%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.860mb | 41.526ms  | ±1.96%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.862mb | 51.057ms  | ±0.54%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.890mb | 50.192ms  | ±0.46%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.859mb | 50.069ms  | ±4.96%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.881mb | 48.325ms  | ±1.39%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.734mb | 75.145ms  | ±0.67%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.840mb | 46.398ms  | ±1.32%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.460mb | 42.720ms  | ±0.38%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.762mb | 45.092ms  | ±0.51%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.848mb | 50.282ms  | ±0.98%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.841mb | 49.860ms  | ±0.87%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.008mb | 45.122ms  | ±0.38%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.213mb | 241.083ms | ±1.00%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.431mb | 174.571ms | ±0.59%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.238mb | 59.250ms  | ±0.78%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.845mb | 120.469ms | ±1.71%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.894mb | 1.442s    | ±0.50%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.363mb | 27.375ms  | ±2.17%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.324mb | 61.462ms  | ±2.17%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.135mb | 569.720ms | ±1.17%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.581mb | 66.212ms  | ±9.25%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.301mb | 88.224ms  | ±1.29%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.070mb | 739.428ms | ±1.01%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.346mb | 18.954ms  | ±0.49%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.346mb | 43.812ms  | ±0.12%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.988mb | 327.774ms | ±0.50%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.264ms   | ±1.30%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.681ms   | ±1.29%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.004ms   | ±1.33%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.040ms   | ±0.53%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.373ms   | ±1.30%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.053ms   | ±1.60%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.456ms   | ±1.66%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.750ms   | ±0.69%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 573.170μs | ±1.85%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.950ms   | ±0.78%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 2.032ms   | ±1.57%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.930ms   | ±1.07%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.576ms  | ±0.28%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.989ms   | ±0.92%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.546ms   | ±1.11%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.360ms   | ±0.84%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.594ms   | ±7.58%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.960ms   | ±0.74%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 5.768μs   | ±19.10% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.226ms   | ±1.41%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.538mb  | 27.685ms  | ±1.63%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.707mb | 248.881ms | ±1.34%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.035mb | 1.220s    | ±0.52%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.816mb | 195.539ms | ±0.50%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.247ms   | ±2.78%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.732ms  | ±2.08%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.019ms  | ±0.48%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.371ms   | ±1.29%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.391ms   | ±0.28%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.365ms   | ±0.91%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.582ms   | ±0.86%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.855ms   | ±2.23%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.848ms   | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.191ms   | ±1.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.631ms   | ±1.13%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.893ms   | ±1.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.417ms  | ±6.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.747ms   | ±1.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.449ms   | ±2.92%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 655.151μs | ±1.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.282ms   | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.752ms   | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.366ms   | ±1.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 175.105ms | ±17.08% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.712ms   | ±1.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.024ms   | ±25.54% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.394ms   | ±4.93%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 11.556ms  | ±2.94%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 12.004ms  | ±2.93%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 13.148ms  | ±2.40%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 21.648ms  | ±1.09%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 33.161ms  | ±0.91%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 803.631μs | ±2.95%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 911.344μs | ±2.75%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 1.003ms   | ±1.62%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.591ms   | ±0.88%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.372ms   | ±0.47%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 29.529ms  | ±2.22%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 32.446ms  | ±0.35%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 36.904ms  | ±1.83%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 69.860ms  | ±1.07%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 107.822ms | ±0.84%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.681ms  | ±2.51%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.355ms  | ±1.43%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.937ms  | ±2.20%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 73.980ms  | ±1.29%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 166.525ms | ±3.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.292ms   | ±1.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.978ms  | ±3.06%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.678μs   | ±9.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.668μs   | ±14.81% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 171.481ms | ±30.55% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 477.185μs | ±1.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.134ms   | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.623ms   | ±1.10%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 14.283ms  | ±6.57%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 86.163ms  | ±0.68%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.178ms  | ±0.81%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 24.547ms  | ±0.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 194.068ms | ±21.05% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.778ms  | ±2.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.701ms  | ±1.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.829ms  | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 14.135ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.392ms  | ±0.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.288ms   | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.873ms  | ±1.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 14.173ms  | ±1.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.645ms  | ±1.09%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 10.866ms  | ±1.29%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 10.775ms  | ±0.45%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 12.564ms  | ±0.47%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 12.574ms  | ±0.38%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 11.605ms  | ±0.61%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 10.866ms  | ±0.28%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 20.261ms  | ±0.19%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 3.229ms   | ±1.48%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.269μs  | ±1.29%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 251.466μs | ±0.73%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.499ms   | ±0.71%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.543ms  | ±0.46%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.472ms  | ±0.66%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.869ms   | ±0.79%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 10.020ms  | ±1.98%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.988ms   | ±0.37%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.523ms   | ±0.43%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.658ms   | ±0.66%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.036μs   | ±12.86% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.933μs   | ±40.94% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.485mb | 16.553ms  | ±0.55%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.485mb | 16.644ms  | ±0.32%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```