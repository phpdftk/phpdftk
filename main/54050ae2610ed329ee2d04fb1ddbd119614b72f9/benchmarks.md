# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-14 20:02:59 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.256ms | 2.552ms | 2.772ms | 4.841ms | 7.082ms |
| FPDF | 799.034μs | 851.185μs | 951.759μs | 1.548ms | 2.293ms |
| TCPDF | 10.275ms | 11.081ms | 12.408ms | 20.750ms | 32.022ms |
| mPDF | 26.369ms | 30.080ms | 33.995ms | 67.555ms | 107.256ms |
| Dompdf | 11.419ms | 16.018ms | 21.997ms | 74.033ms | 163.180ms |

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
| phpdftk | 3.411ms | 3.598ms | 3.949ms | 5.892ms | 8.530ms |
| FPDF | 1.047ms | 1.150ms | 1.258ms | 1.940ms | 2.818ms |
| TCPDF | 15.205ms | 15.710ms | 17.388ms | 27.045ms | 38.949ms |

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
| Pdf (Level 3) | 3.447ms | 4.572ms | 12.680ms |
| PdfDoc (Level 2) | 2.792ms | 3.273ms | 7.720ms |
| PdfWriter (Level 1) | 2.351ms | 2.816ms | 6.971ms |

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
| Pdf (Level 3) | 4.469ms | 12.166ms | 46.753ms |
| PdfDoc (Level 2) | 3.784ms | 10.049ms | — |

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
| Pdf (Level 3) | 4.109ms | 11.725ms | 45.665ms |
| PdfDoc (Level 2) | 3.338ms | 7.404ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.224ms | 1.679ms | 6.012ms |
| smalot/pdfparser | 2.013ms | 2.418ms | 5.773ms |
| setasign/fpdi | 1.994ms | 2.874ms | 30.144ms |

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
| phpdftk | 2.045ms | 1.368ms |
| smalot/pdfparser | FAIL | 1.948ms |
| setasign/fpdi | 3.026ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.411ms   | ±0.17%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.598ms   | ±1.03%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.949ms   | ±0.64%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.892ms   | ±1.79%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.530ms   | ±0.83%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.205ms  | ±0.99%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.710ms  | ±0.87%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.388ms  | ±1.16%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 27.045ms  | ±0.62%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 38.949ms  | ±0.16%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.047ms   | ±2.02%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.150ms   | ±1.83%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.258ms   | ±4.87%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.940ms   | ±1.18%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.818ms   | ±1.59%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.351ms   | ±0.69%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.816ms   | ±0.79%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.971ms   | ±1.23%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.792ms   | ±2.82%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.273ms   | ±1.44%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.720ms   | ±0.75%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.447ms   | ±1.42%   |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.572ms   | ±0.73%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.680ms  | ±0.80%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.647mb | 88.500ms  | ±0.17%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.592mb | 381.728ms | ±0.19%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.748mb | 1.499s    | ±0.32%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.808mb | 265.976ms | ±0.30%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.675mb | 203.123ms | ±0.40%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.456mb | 164.826ms | ±1.53%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.556mb | 223.813ms | ±0.16%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.072mb | 185.716ms | ±0.16%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.688mb | 352.284ms | ±0.34%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.149mb | 53.898ms  | ±0.54%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.065mb | 47.146ms  | ±0.33%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.990mb | 43.976ms  | ±0.61%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.344mb | 148.065ms | ±0.59%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.054mb | 48.987ms  | ±0.66%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.272mb | 62.164ms  | ±0.81%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.706mb | 91.110ms  | ±0.39%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.957mb | 38.885ms  | ±1.31%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.959mb | 47.034ms  | ±0.77%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.987mb | 48.928ms  | ±0.87%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.956mb | 47.775ms  | ±1.16%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.978mb | 46.259ms  | ±0.22%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.896mb | 72.893ms  | ±1.24%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.938mb | 44.689ms  | ±0.47%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.623mb | 40.536ms  | ±0.56%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.924mb | 44.079ms  | ±0.36%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.945mb | 49.225ms  | ±0.68%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.938mb | 48.591ms  | ±0.60%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.105mb | 43.659ms  | ±0.18%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.329mb | 235.189ms | ±0.65%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.535mb | 172.703ms | ±0.65%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.342mb | 57.929ms  | ±0.59%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.953mb | 118.495ms | ±0.57%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 43.154mb | 1.425s    | ±0.45%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.459mb | 26.793ms  | ±4.37%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.420mb | 60.281ms  | ±1.03%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.231mb | 560.659ms | ±1.19%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.743mb | 67.233ms  | ±8.89%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.397mb | 88.945ms  | ±1.16%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.165mb | 756.443ms | ±0.54%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.442mb | 18.959ms  | ±0.80%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.442mb | 43.919ms  | ±0.90%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.084mb | 327.794ms | ±0.52%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.257ms   | ±1.18%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.679ms   | ±1.18%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.012ms   | ±0.50%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.045ms   | ±0.90%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.368ms   | ±0.78%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.013ms   | ±0.32%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.418ms   | ±1.24%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.773ms   | ±0.58%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 555.201μs | ±1.20%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.948ms   | ±1.04%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.994ms   | ±1.26%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.874ms   | ±0.44%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.144ms  | ±0.34%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.026ms   | ±0.47%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.534ms   | ±0.85%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.342ms   | ±0.51%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.454ms   | ±0.90%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.884ms   | ±0.65%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.797μs   | ±16.95%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.224ms   | ±1.00%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.542mb  | 27.410ms  | ±0.66%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.711mb | 244.169ms | ±0.55%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.039mb | 1.219s    | ±0.58%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.820mb | 192.244ms | ±0.55%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.109ms   | ±2.32%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.725ms  | ±1.74%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.665ms  | ±0.73%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.338ms   | ±0.86%   |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.404ms   | ±0.63%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.356ms   | ±1.76%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.552ms   | ±0.79%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.772ms   | ±1.16%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.841ms   | ±1.26%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.082ms   | ±0.93%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.650ms   | ±0.88%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.907ms   | ±0.86%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.466ms  | ±1.32%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.739ms   | ±0.94%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.420ms   | ±0.40%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 706.498μs | ±5.53%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.246ms   | ±1.76%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.703ms   | ±1.07%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.249ms   | ±0.76%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 221.587ms | ±19.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.657ms   | ±0.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.166ms   | ±121.73% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.180ms   | ±0.40%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.275ms  | ±1.43%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.081ms  | ±0.72%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.408ms  | ±0.94%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.750ms  | ±0.97%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 32.022ms  | ±0.71%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 799.034μs | ±44.99%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 851.185μs | ±2.95%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 951.759μs | ±0.97%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.548ms   | ±0.79%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.293ms   | ±0.29%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 26.369ms  | ±2.63%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 30.080ms  | ±0.89%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.995ms  | ±1.53%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 67.555ms  | ±0.96%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 107.256ms | ±0.86%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.419ms  | ±1.87%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.018ms  | ±0.57%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.997ms  | ±0.61%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 74.033ms  | ±0.87%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 163.180ms | ±0.56%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.137ms   | ±0.96%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 49.804ms  | ±0.97%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.665μs   | ±17.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 211.538ms | ±27.87%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 452.945μs | ±0.65%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.001ms   | ±0.44%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.414ms   | ±1.50%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 14.060ms  | ±6.71%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 86.214ms  | ±0.73%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.024ms  | ±0.84%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.111ms  | ±0.91%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 180.680ms | ±29.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.472ms  | ±0.42%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.375ms  | ±0.45%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.622ms  | ±0.49%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.572ms  | ±0.57%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.904ms  | ±1.08%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.193ms   | ±1.12%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.458ms  | ±0.54%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.610ms  | ±0.87%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.256ms  | ±0.55%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 10.428ms  | ±0.71%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 10.467ms  | ±0.79%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 12.096ms  | ±0.56%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 12.370ms  | ±0.70%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 11.157ms  | ±1.05%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 10.548ms  | ±0.30%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 19.714ms  | ±0.06%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 3.080ms   | ±1.46%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.519μs  | ±1.13%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 248.090μs | ±0.83%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.469ms   | ±0.79%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.166ms  | ±0.67%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 46.753ms  | ±0.35%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.784ms   | ±0.40%   |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 10.049ms  | ±0.56%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.928ms   | ±0.38%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.489ms   | ±1.34%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.607ms   | ±0.67%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.917μs   | ±23.39%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.223μs   | ±68.18%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.581mb | 16.439ms  | ±0.60%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.581mb | 16.376ms  | ±0.23%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```