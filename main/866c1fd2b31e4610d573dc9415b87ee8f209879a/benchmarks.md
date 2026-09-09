# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-09 23:22:42 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 11.389ms | 2.180ms | 2.403ms | 4.303ms | 6.246ms |
| FPDF | 754.100μs | 840.521μs | 881.680μs | 1.440ms | 2.185ms |
| TCPDF | 9.995ms | 10.593ms | 11.641ms | 18.996ms | 28.602ms |
| mPDF | 23.749ms | 26.650ms | 29.763ms | 55.591ms | 88.413ms |
| Dompdf | 10.455ms | 14.193ms | 18.698ms | 62.317ms | 137.394ms |

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
| phpdftk | 2.970ms | 3.277ms | 3.364ms | 5.077ms | 7.475ms |
| FPDF | 1.153ms | 1.133ms | 1.167ms | 1.813ms | 2.670ms |
| TCPDF | 14.339ms | 15.234ms | 16.262ms | 25.050ms | 36.175ms |

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
| Pdf (Level 3) | 3.073ms | 4.139ms | 11.121ms |
| PdfDoc (Level 2) | 2.476ms | 2.833ms | 6.882ms |
| PdfWriter (Level 1) | 2.111ms | 2.422ms | 6.137ms |

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
| Pdf (Level 3) | 3.875ms | 10.737ms | 41.120ms |
| PdfDoc (Level 2) | 3.363ms | 8.766ms | — |

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
| Pdf (Level 3) | 3.554ms | 10.095ms | 38.058ms |
| PdfDoc (Level 2) | 2.864ms | 6.380ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.086ms | 1.411ms | 5.075ms |
| smalot/pdfparser | 1.808ms | 2.130ms | 5.180ms |
| setasign/fpdi | 1.690ms | 2.427ms | 25.081ms |

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
| phpdftk | 1.714ms | 1.125ms |
| smalot/pdfparser | FAIL | 1.706ms |
| setasign/fpdi | 2.555ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 2.970ms   | ±3.10%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.277ms   | ±24.69%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.364ms   | ±1.41%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.077ms   | ±0.07%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 7.475ms   | ±0.70%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.339ms  | ±2.09%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.234ms  | ±0.83%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.262ms  | ±0.15%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 25.050ms  | ±0.13%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 36.175ms  | ±0.43%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.153ms   | ±8.88%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.133ms   | ±20.73%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.167ms   | ±0.92%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.813ms   | ±0.71%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.670ms   | ±1.44%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.111ms   | ±3.45%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.422ms   | ±0.91%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.137ms   | ±1.09%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.476ms   | ±1.23%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.833ms   | ±1.99%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 6.882ms   | ±0.50%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.073ms   | ±1.13%   |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.139ms   | ±0.99%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 11.121ms  | ±42.08%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.538mb | 69.969ms  | ±1.23%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.455mb | 298.666ms | ±0.10%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.487mb | 1.169s    | ±0.23%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.605mb | 209.264ms | ±0.09%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.567mb | 161.974ms | ±0.12%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.348mb | 128.814ms | ±0.73%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.418mb | 175.437ms | ±0.25%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.955mb | 145.312ms | ±0.24%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.510mb | 273.154ms | ±0.14%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.050mb | 42.452ms  | ±0.29%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.967mb | 37.140ms  | ±0.05%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.892mb | 34.750ms  | ±0.32%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.236mb | 114.048ms | ±0.11%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.956mb | 38.897ms  | ±0.26%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.173mb | 49.075ms  | ±0.40%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.602mb | 72.333ms  | ±1.76%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.859mb | 31.279ms  | ±0.47%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.862mb | 37.901ms  | ±0.31%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.889mb | 39.243ms  | ±0.47%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.858mb | 38.616ms  | ±0.75%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.880mb | 36.947ms  | ±0.57%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.733mb | 61.564ms  | ±0.31%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.840mb | 36.825ms  | ±0.24%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.460mb | 33.068ms  | ±0.28%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.761mb | 35.299ms  | ±2.29%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.848mb | 39.107ms  | ±0.49%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.841mb | 38.981ms  | ±0.31%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.007mb | 36.183ms  | ±1.16%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.212mb | 183.749ms | ±0.26%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.430mb | 137.143ms | ±0.30%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.237mb | 46.063ms  | ±0.43%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.845mb | 93.715ms  | ±0.08%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.894mb | 1.121s    | ±0.29%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.363mb | 22.854ms  | ±2.66%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.324mb | 49.779ms  | ±0.87%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.135mb | 441.495ms | ±0.07%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.581mb | 55.791ms  | ±9.50%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.301mb | 75.117ms  | ±0.91%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.070mb | 628.424ms | ±0.42%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.346mb | 17.323ms  | ±0.30%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.346mb | 38.320ms  | ±0.86%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.988mb | 287.889ms | ±0.50%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.030ms   | ±2.51%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.411ms   | ±0.35%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.075ms   | ±0.67%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.714ms   | ±0.86%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.125ms   | ±1.27%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.808ms   | ±1.00%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.130ms   | ±0.44%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.180ms   | ±0.81%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 504.999μs | ±1.88%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.706ms   | ±0.42%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.690ms   | ±1.72%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.427ms   | ±1.42%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 25.081ms  | ±0.57%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.555ms   | ±0.74%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.337ms   | ±0.78%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 6.229ms   | ±0.47%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 4.648ms   | ±0.90%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.305ms   | ±0.43%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.073μs   | ±23.57%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 5.086ms   | ±0.73%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.538mb  | 21.382ms  | ±0.57%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.707mb | 191.749ms | ±0.12%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.035mb | 948.086ms | ±0.32%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.816mb | 150.646ms | ±0.23%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.554ms   | ±0.35%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 10.095ms  | ±0.86%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 38.058ms  | ±0.34%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.864ms   | ±0.48%   |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 6.380ms   | ±0.83%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.085ms   | ±2.36%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.180ms   | ±1.63%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.403ms   | ±0.94%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.303ms   | ±0.90%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.246ms   | ±0.94%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.295ms   | ±1.17%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.378ms   | ±1.56%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 10.581ms  | ±1.67%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.225ms   | ±0.97%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.087ms   | ±1.52%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 614.177μs | ±7.02%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.929ms   | ±0.83%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.237ms   | ±0.98%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.906ms   | ±1.13%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 152.591ms | ±30.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.279ms   | ±0.44%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.087ms   | ±33.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.122ms   | ±1.37%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.995ms   | ±1.86%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.593ms  | ±0.47%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.641ms  | ±1.27%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 18.996ms  | ±0.48%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.602ms  | ±0.19%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 754.100μs | ±6.33%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 840.521μs | ±6.03%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 881.680μs | ±5.13%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.440ms   | ±0.90%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.185ms   | ±0.83%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 23.749ms  | ±1.59%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 26.650ms  | ±2.01%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 29.763ms  | ±0.50%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 55.591ms  | ±0.60%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 88.413ms  | ±0.48%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 10.455ms  | ±1.01%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 14.193ms  | ±0.69%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 18.698ms  | ±0.63%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 62.317ms  | ±0.39%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 137.394ms | ±0.45%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.448ms   | ±1.19%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 41.235ms  | ±0.16%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.999μs   | ±14.29%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 0.989μs   | ±18.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 0.678μs   | ±20.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 146.054ms | ±18.52%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 388.128μs | ±3.21%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.613ms   | ±0.16%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.093ms   | ±1.12%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 6.329ms   | ±9.89%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 70.475ms  | ±0.13%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.732ms  | ±0.59%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.532ms  | ±0.65%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 173.809ms | ±29.15%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 11.395ms  | ±0.61%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 11.472ms  | ±0.93%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 11.685ms  | ±0.65%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 11.722ms  | ±1.08%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 12.055ms  | ±105.70% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.905ms   | ±1.12%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 11.732ms  | ±0.90%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 11.508ms  | ±0.49%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 11.389ms  | ±0.59%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 9.281ms   | ±0.69%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 9.258ms   | ±0.44%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 10.654ms  | ±1.17%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 10.868ms  | ±0.76%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 9.930ms   | ±0.24%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 9.320ms   | ±0.35%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 17.280ms  | ±0.20%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 2.704ms   | ±0.35%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 35.327μs  | ±1.15%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 213.036μs | ±1.50%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 3.875ms   | ±0.96%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 10.737ms  | ±0.35%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 41.120ms  | ±0.34%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.363ms   | ±0.79%   |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 8.766ms   | ±0.69%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 7.638ms   | ±0.32%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 7.327ms   | ±0.27%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 7.413ms   | ±1.00%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.024μs   | ±16.64%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.085μs   | ±45.00%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.484mb | 14.640ms  | ±0.68%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.484mb | 14.411ms  | ±0.74%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```