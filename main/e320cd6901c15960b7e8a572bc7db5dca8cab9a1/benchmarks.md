# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-19 05:44:01 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.211ms | 2.503ms | 2.765ms | 4.751ms | 7.015ms |
| FPDF | 754.580μs | 843.461μs | 935.673μs | 1.547ms | 2.248ms |
| TCPDF | 10.029ms | 11.101ms | 12.002ms | 20.801ms | 30.904ms |
| mPDF | 25.232ms | 28.779ms | 32.759ms | 64.766ms | 105.680ms |
| Dompdf | 11.296ms | 16.095ms | 21.492ms | 73.351ms | 161.665ms |

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
| phpdftk | 3.302ms | 3.547ms | 3.831ms | 5.836ms | 8.254ms |
| FPDF | 1.021ms | 1.128ms | 1.224ms | 1.883ms | 2.744ms |
| TCPDF | 14.334ms | 15.357ms | 16.729ms | 26.595ms | 38.459ms |

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
| Pdf (Level 3) | 3.351ms | 4.459ms | 12.688ms |
| PdfDoc (Level 2) | 2.703ms | 3.138ms | 7.475ms |
| PdfWriter (Level 1) | 2.275ms | 2.722ms | 6.852ms |

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
| Pdf (Level 3) | 4.362ms | 12.201ms | 47.229ms |
| PdfDoc (Level 2) | 3.789ms | 9.977ms | — |

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
| Pdf (Level 3) | 4.156ms | 11.722ms | 45.476ms |
| PdfDoc (Level 2) | 3.298ms | 7.313ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.232ms | 1.656ms | 5.950ms |
| smalot/pdfparser | 1.996ms | 2.354ms | 5.739ms |
| setasign/fpdi | 1.943ms | 2.827ms | 29.736ms |

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
| phpdftk | 2.027ms | 1.359ms |
| smalot/pdfparser | FAIL | 1.905ms |
| setasign/fpdi | 2.990ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.302ms   | ±1.34%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.547ms   | ±1.52%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.831ms   | ±0.46%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.836ms   | ±0.75%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.254ms   | ±0.96%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.334ms  | ±0.97%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.357ms  | ±1.26%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.729ms  | ±0.46%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.595ms  | ±0.82%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 38.459ms  | ±0.37%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.021ms   | ±2.02%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.128ms   | ±2.03%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.224ms   | ±1.09%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.883ms   | ±0.84%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.744ms   | ±0.85%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.275ms   | ±1.67%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.722ms   | ±0.49%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.852ms   | ±0.44%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.703ms   | ±0.93%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.138ms   | ±0.52%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.475ms   | ±0.16%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.351ms   | ±0.82%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.459ms   | ±0.69%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.688ms  | ±0.49%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.073mb | 88.196ms  | ±1.74%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 22.204mb | 384.515ms | ±2.07%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 46.932mb | 1.515s    | ±0.70%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.259mb | 267.536ms | ±0.89%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.059mb | 203.799ms | ±3.29%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.892mb | 163.835ms | ±0.50%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 19.017mb | 224.299ms | ±1.61%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.511mb | 184.463ms | ±0.52%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 21.214mb | 349.571ms | ±2.61%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.526mb | 53.044ms  | ±0.17%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.438mb | 46.575ms  | ±0.52%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.370mb | 43.170ms  | ±0.11%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.764mb | 144.584ms | ±0.48%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.427mb | 48.327ms  | ±0.14%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.653mb | 61.273ms  | ±0.17%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.137mb | 91.013ms  | ±0.65%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.325mb | 39.045ms  | ±0.67%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.327mb | 46.893ms  | ±0.54%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.355mb | 48.373ms  | ±0.21%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.324mb | 47.626ms  | ±1.02%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.346mb | 46.023ms  | ±0.33%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.254mb | 71.944ms  | ±0.19%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.297mb | 44.742ms  | ±0.71%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.976mb | 39.815ms  | ±0.13%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.292mb | 43.814ms  | ±0.31%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.313mb | 48.447ms  | ±0.10%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.306mb | 48.277ms  | ±1.04%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.458mb | 43.512ms  | ±0.59%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.971mb | 232.716ms | ±1.15%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.912mb | 172.874ms | ±0.62%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.807mb | 58.837ms  | ±1.12%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.360mb | 119.625ms | ±1.81%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 45.043mb | 1.457s    | ±0.69%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.766mb | 26.747ms  | ±2.38%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.727mb | 58.727ms  | ±6.28%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.538mb | 545.609ms | ±0.35%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.050mb | 65.278ms  | ±10.52% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.704mb | 86.603ms  | ±1.28%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.473mb | 733.784ms | ±0.50%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.749mb | 18.724ms  | ±0.85%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.749mb | 42.962ms  | ±0.15%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.457mb | 320.216ms | ±0.58%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.226ms   | ±1.90%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.656ms   | ±0.98%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.950ms   | ±0.34%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.027ms   | ±2.11%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.359ms   | ±2.49%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.996ms   | ±0.70%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.354ms   | ±0.62%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.739ms   | ±0.82%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 544.674μs | ±1.75%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.905ms   | ±0.28%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.943ms   | ±1.56%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.827ms   | ±0.28%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.736ms  | ±0.22%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.990ms   | ±0.59%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.500ms   | ±1.56%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.287ms   | ±0.45%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.467ms   | ±0.91%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.854ms   | ±0.48%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.524μs   | ±19.62% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.232ms   | ±0.93%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.662mb  | 27.702ms  | ±0.38%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.060mb | 249.927ms | ±0.87%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.406mb | 1.241s    | ±0.47%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.086mb | 196.387ms | ±0.44%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.156ms   | ±0.67%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.722ms  | ±0.45%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.476ms  | ±0.66%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.298ms   | ±0.78%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.313ms   | ±0.28%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.330ms   | ±1.58%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.503ms   | ±1.53%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.765ms   | ±0.45%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.751ms   | ±10.65% |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.015ms   | ±1.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.581ms   | ±0.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.828ms   | ±0.34%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.225ms  | ±10.45% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.610ms   | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.344ms   | ±0.53%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 645.004μs | ±4.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.175ms   | ±1.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.645ms   | ±1.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.244ms   | ±1.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 179.049ms | ±20.26% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.619ms   | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.829ms   | ±18.56% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.998ms   | ±0.90%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.029ms  | ±0.88%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.101ms  | ±1.10%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.002ms  | ±2.12%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.801ms  | ±1.11%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 30.904ms  | ±0.34%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 754.580μs | ±2.30%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 843.461μs | ±1.59%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 935.673μs | ±1.67%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.547ms   | ±1.47%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.248ms   | ±1.04%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.232ms  | ±2.23%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 28.779ms  | ±0.39%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.759ms  | ±0.27%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 64.766ms  | ±0.60%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 105.680ms | ±1.74%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.296ms  | ±0.34%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.095ms  | ±0.61%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.492ms  | ±0.55%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 73.351ms  | ±0.83%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 161.665ms | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.039ms   | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 49.627ms  | ±0.29%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 215.457ms | ±16.79% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 457.005μs | ±1.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.989ms   | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.387ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.132ms  | ±8.75%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 85.244ms  | ±0.43%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.483ms  | ±1.97%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.633ms  | ±6.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 178.008ms | ±25.59% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.402ms  | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.233ms  | ±0.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.483ms  | ±0.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.584ms  | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.817ms  | ±0.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.161ms   | ±5.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.442ms  | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.409ms  | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.211ms  | ±0.33%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.351mb  | 10.553ms  | ±1.43%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.245mb  | 10.473ms  | ±0.58%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.397mb  | 12.193ms  | ±0.88%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.095mb  | 12.396ms  | ±0.90%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.577mb  | 11.240ms  | ±0.28%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.651mb  | 10.464ms  | ±0.49%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.896mb | 19.698ms  | ±0.70%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.791mb  | 3.073ms   | ±1.23%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.090μs  | ±0.99%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 248.626μs | ±1.85%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.362ms   | ±1.00%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.201ms  | ±0.75%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.229ms  | ±0.52%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.789ms   | ±0.46%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.977ms   | ±1.71%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.924ms   | ±0.33%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.602ms   | ±0.61%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.572ms   | ±0.40%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.861μs   | ±22.10% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.032μs   | ±38.20% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.937mb | 17.536ms  | ±0.57%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.937mb | 17.540ms  | ±2.90%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```