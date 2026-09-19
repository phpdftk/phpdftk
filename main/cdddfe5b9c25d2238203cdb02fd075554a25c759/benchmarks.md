# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-19 15:29:26 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.187ms | 2.507ms | 2.760ms | 4.758ms | 6.957ms |
| FPDF | 794.545μs | 845.373μs | 910.277μs | 1.509ms | 2.232ms |
| TCPDF | 9.944ms | 10.855ms | 11.900ms | 20.395ms | 30.998ms |
| mPDF | 25.144ms | 28.594ms | 32.676ms | 64.377ms | 104.254ms |
| Dompdf | 11.277ms | 15.788ms | 21.289ms | 71.948ms | 159.088ms |

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
| phpdftk | 3.347ms | 3.538ms | 3.765ms | 5.790ms | 8.247ms |
| FPDF | 1.020ms | 1.104ms | 1.207ms | 1.900ms | 2.727ms |
| TCPDF | 14.532ms | 15.382ms | 16.525ms | 26.478ms | 38.474ms |

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
| Pdf (Level 3) | 3.330ms | 4.382ms | 12.465ms |
| PdfDoc (Level 2) | 2.749ms | 3.146ms | 7.467ms |
| PdfWriter (Level 1) | 2.312ms | 2.735ms | 6.815ms |

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
| Pdf (Level 3) | 4.298ms | 12.014ms | 46.531ms |
| PdfDoc (Level 2) | 3.752ms | 9.902ms | — |

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
| Pdf (Level 3) | 4.039ms | 11.590ms | 45.154ms |
| PdfDoc (Level 2) | 3.287ms | 7.252ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.147ms | 1.666ms | 5.927ms |
| smalot/pdfparser | 1.976ms | 2.354ms | 5.631ms |
| setasign/fpdi | 1.936ms | 2.832ms | 29.597ms |

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
| phpdftk | 2.004ms | 1.356ms |
| smalot/pdfparser | FAIL | 1.883ms |
| setasign/fpdi | 2.947ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.347ms   | ±0.90%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.538ms   | ±0.40%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.765ms   | ±0.42%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.790ms   | ±1.31%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.247ms   | ±1.19%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.532ms  | ±1.86%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.382ms  | ±1.21%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.525ms  | ±0.39%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.478ms  | ±1.13%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 38.474ms  | ±0.59%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.020ms   | ±1.90%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.104ms   | ±4.11%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.207ms   | ±1.41%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.900ms   | ±2.82%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.727ms   | ±0.61%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.312ms   | ±1.79%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.735ms   | ±0.46%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.815ms   | ±1.06%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.749ms   | ±1.40%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.146ms   | ±1.26%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.467ms   | ±0.49%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.330ms   | ±1.26%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.382ms   | ±0.78%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.465ms  | ±0.67%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.267mb | 88.739ms  | ±0.31%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 22.429mb | 387.340ms | ±0.28%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 47.278mb | 1.520s    | ±1.05%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.454mb | 266.451ms | ±0.21%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.266mb | 202.386ms | ±0.42%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.082mb | 164.341ms | ±0.65%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 19.213mb | 226.387ms | ±0.61%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.760mb | 188.333ms | ±0.22%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 21.422mb | 356.721ms | ±0.70%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.743mb | 53.803ms  | ±0.30%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.656mb | 46.495ms  | ±0.57%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.585mb | 43.636ms  | ±0.51%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.947mb | 146.249ms | ±0.33%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.644mb | 49.511ms  | ±0.67%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.870mb | 62.429ms  | ±0.91%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.322mb | 92.657ms  | ±0.49%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.543mb | 39.784ms  | ±0.39%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.543mb | 47.551ms  | ±0.44%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.570mb | 49.423ms  | ±0.27%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.539mb | 48.027ms  | ±0.30%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.561mb | 46.264ms  | ±0.28%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.437mb | 72.075ms  | ±0.53%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.546mb | 45.055ms  | ±0.41%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.159mb | 40.655ms  | ±0.99%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.508mb | 44.064ms  | ±0.07%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.528mb | 49.148ms  | ±1.18%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.522mb | 49.412ms  | ±0.82%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.707mb | 44.165ms  | ±0.36%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 20.154mb | 236.131ms | ±0.18%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.103mb | 172.590ms | ±0.23%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.028mb | 58.656ms  | ±0.10%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.554mb | 120.593ms | ±0.05%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 45.248mb | 1.448s    | ±0.18%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.948mb | 25.789ms  | ±2.09%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.908mb | 58.045ms  | ±0.59%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.719mb | 523.627ms | ±0.89%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.231mb | 64.109ms  | ±9.08%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.885mb | 85.637ms  | ±1.20%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.654mb | 727.551ms | ±0.53%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.930mb | 18.463ms  | ±0.53%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.930mb | 42.532ms  | ±0.64%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.638mb | 318.672ms | ±0.49%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.224ms   | ±1.46%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.666ms   | ±1.52%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.927ms   | ±0.65%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.004ms   | ±0.32%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.356ms   | ±0.89%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.976ms   | ±0.90%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.354ms   | ±0.88%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.631ms   | ±0.49%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 538.144μs | ±1.01%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.883ms   | ±1.26%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.936ms   | ±1.18%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.832ms   | ±0.57%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.597ms  | ±0.68%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.947ms   | ±0.88%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.525ms   | ±1.40%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.264ms   | ±0.62%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.412ms   | ±0.21%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.838ms   | ±2.97%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 4.823μs   | ±28.69% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.147ms   | ±0.64%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.674mb  | 27.641ms  | ±0.84%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.071mb | 251.130ms | ±0.47%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.417mb | 1.236s    | ±0.61%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.097mb | 194.670ms | ±1.19%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.039ms   | ±0.36%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.590ms  | ±0.48%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.154ms  | ±0.81%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.287ms   | ±0.47%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.252ms   | ±0.27%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.357ms   | ±1.70%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.507ms   | ±0.95%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.760ms   | ±0.65%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.758ms   | ±0.79%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.957ms   | ±0.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.572ms   | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.816ms   | ±0.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.318ms  | ±6.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.552ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.358ms   | ±0.54%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 721.757μs | ±8.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.141ms   | ±1.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.593ms   | ±0.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.231ms   | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 258.730ms | ±21.16% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.559ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.782ms   | ±20.79% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.939ms   | ±0.92%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.944ms   | ±0.81%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.855ms  | ±1.11%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.900ms  | ±0.68%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.395ms  | ±0.51%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 30.998ms  | ±0.53%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 794.545μs | ±5.81%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 845.373μs | ±1.36%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 910.277μs | ±2.91%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.509ms   | ±1.38%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.232ms   | ±0.33%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.144ms  | ±1.30%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 28.594ms  | ±0.67%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.676ms  | ±0.49%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 64.377ms  | ±0.39%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 104.254ms | ±0.19%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.277ms  | ±0.62%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.788ms  | ±0.59%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.289ms  | ±0.38%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 71.948ms  | ±0.47%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 159.088ms | ±0.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.012ms   | ±1.15%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.079ms  | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 155.277ms | ±28.80% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 449.654μs | ±1.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.968ms   | ±0.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.412ms   | ±6.54%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.538ms  | ±3.31%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 84.743ms  | ±0.75%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.607ms  | ±1.02%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.173ms  | ±1.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 239.786ms | ±13.53% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.435ms  | ±0.34%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.204ms  | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.409ms  | ±0.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.571ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.808ms  | ±0.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.106ms   | ±1.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.481ms  | ±2.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.493ms  | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.187ms  | ±0.83%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.355mb  | 10.276ms  | ±1.14%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.249mb  | 10.394ms  | ±0.08%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.401mb  | 11.927ms  | ±0.12%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.099mb  | 12.140ms  | ±0.86%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.582mb  | 11.149ms  | ±0.40%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.655mb  | 10.310ms  | ±0.56%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.900mb | 19.592ms  | ±0.56%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.791mb  | 3.055ms   | ±0.53%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.710μs  | ±1.07%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 241.697μs | ±0.64%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.298ms   | ±0.60%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.014ms  | ±0.56%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 46.531ms  | ±0.32%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.752ms   | ±0.28%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.902ms   | ±0.81%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.867ms   | ±0.44%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.420ms   | ±0.65%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.466ms   | ±4.44%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.760μs   | ±17.58% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.958μs   | ±39.85% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.120mb | 17.403ms  | ±0.24%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.120mb | 17.336ms  | ±0.32%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```