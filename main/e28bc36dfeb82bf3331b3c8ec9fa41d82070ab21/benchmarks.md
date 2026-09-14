# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-14 16:38:26 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.867ms | 2.496ms | 2.773ms | 4.705ms | 6.911ms |
| FPDF | 769.425μs | 867.994μs | 935.232μs | 1.530ms | 2.241ms |
| TCPDF | 10.214ms | 10.929ms | 12.025ms | 19.229ms | 28.051ms |
| mPDF | 26.102ms | 30.158ms | 33.614ms | 61.975ms | 95.939ms |
| Dompdf | 11.373ms | 15.251ms | 20.028ms | 66.222ms | 150.795ms |

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
| phpdftk | 3.267ms | 3.556ms | 3.785ms | 5.824ms | 8.129ms |
| FPDF | 1.059ms | 1.139ms | 1.235ms | 1.912ms | 2.728ms |
| TCPDF | 14.653ms | 15.562ms | 16.652ms | 24.978ms | 35.412ms |

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
| Pdf (Level 3) | 3.353ms | 4.339ms | 12.118ms |
| PdfDoc (Level 2) | 2.975ms | 3.139ms | 7.405ms |
| PdfWriter (Level 1) | 2.263ms | 2.751ms | 6.784ms |

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
| Pdf (Level 3) | 4.272ms | 11.906ms | 45.602ms |
| PdfDoc (Level 2) | 3.733ms | 9.846ms | — |

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
| Pdf (Level 3) | 4.045ms | 11.662ms | 44.865ms |
| PdfDoc (Level 2) | 3.287ms | 7.254ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.073ms | 1.679ms | 6.025ms |
| smalot/pdfparser | 2.012ms | 2.383ms | 5.538ms |
| setasign/fpdi | 1.906ms | 2.702ms | 29.124ms |

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
| phpdftk | 2.041ms | 1.354ms |
| smalot/pdfparser | FAIL | 1.911ms |
| setasign/fpdi | 2.854ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.267ms   | ±1.47%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.556ms   | ±2.94%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.785ms   | ±0.71%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.824ms   | ±8.56%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.129ms   | ±0.08%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.653ms  | ±0.91%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.562ms  | ±0.69%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.652ms  | ±0.38%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 24.978ms  | ±0.08%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 35.412ms  | ±0.34%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.059ms   | ±1.98%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.139ms   | ±1.19%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.235ms   | ±0.38%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.912ms   | ±1.23%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.728ms   | ±0.88%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.263ms   | ±1.39%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.751ms   | ±0.60%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.784ms   | ±0.58%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.975ms   | ±127.78% |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.139ms   | ±0.74%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.405ms   | ±0.57%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.353ms   | ±0.83%   |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.339ms   | ±0.76%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.118ms  | ±0.49%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.644mb | 79.624ms  | ±2.99%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.589mb | 343.313ms | ±2.61%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.744mb | 1.329s    | ±0.37%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.739mb | 243.838ms | ±2.16%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.672mb | 192.990ms | ±0.20%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.453mb | 151.119ms | ±2.87%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.552mb | 205.669ms | ±0.95%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.069mb | 176.083ms | ±0.38%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.685mb | 321.724ms | ±3.77%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.146mb | 49.094ms  | ±0.81%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.062mb | 42.886ms  | ±3.88%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.987mb | 41.081ms  | ±0.48%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.341mb | 132.659ms | ±0.84%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.051mb | 45.323ms  | ±0.51%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.269mb | 56.847ms  | ±0.52%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.703mb | 89.717ms  | ±3.38%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.953mb | 36.303ms  | ±0.90%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.956mb | 44.779ms  | ±1.08%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.984mb | 46.535ms  | ±0.29%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.952mb | 45.585ms  | ±1.34%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.975mb | 43.725ms  | ±2.35%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.893mb | 69.892ms  | ±0.40%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.934mb | 44.025ms  | ±1.27%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.620mb | 37.918ms  | ±0.56%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.921mb | 41.725ms  | ±0.28%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.942mb | 46.170ms  | ±0.59%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.935mb | 46.110ms  | ±2.22%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.102mb | 42.353ms  | ±2.89%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.326mb | 215.843ms | ±2.86%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.532mb | 164.751ms | ±1.45%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.338mb | 54.734ms  | ±2.00%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.950mb | 108.359ms | ±0.57%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 43.151mb | 1.306s    | ±3.97%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.456mb | 24.011ms  | ±2.24%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.416mb | 52.302ms  | ±0.80%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.227mb | 487.085ms | ±3.26%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.739mb | 65.947ms  | ±10.18%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.393mb | 85.124ms  | ±1.11%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.162mb | 661.723ms | ±0.18%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.438mb | 18.071ms  | ±1.43%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.438mb | 41.338ms  | ±0.62%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.081mb | 278.942ms | ±0.10%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.227ms   | ±1.82%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.679ms   | ±1.06%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.025ms   | ±1.05%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.041ms   | ±1.29%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.354ms   | ±1.21%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.012ms   | ±1.34%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.383ms   | ±0.51%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.538ms   | ±0.48%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 565.190μs | ±2.75%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.911ms   | ±0.98%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.906ms   | ±1.04%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.702ms   | ±0.72%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.124ms  | ±1.00%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.854ms   | ±0.33%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.500ms   | ±0.26%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.112ms   | ±0.28%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.318ms   | ±0.47%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.770ms   | ±0.70%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 4.121μs   | ±20.20%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.073ms   | ±0.72%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.542mb  | 25.516ms  | ±7.69%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.711mb | 227.479ms | ±1.96%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.039mb | 1.112s    | ±1.38%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.820mb | 173.814ms | ±0.47%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.045ms   | ±0.71%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.662ms  | ±5.15%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 44.865ms  | ±0.61%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.287ms   | ±0.63%   |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.254ms   | ±1.28%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.307ms   | ±0.99%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.496ms   | ±1.46%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.773ms   | ±7.45%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.705ms   | ±1.22%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.911ms   | ±0.68%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.502ms   | ±2.00%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.747ms   | ±0.71%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.849ms  | ±6.66%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.574ms   | ±0.76%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.368ms   | ±7.52%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 580.628μs | ±1.54%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.162ms   | ±1.30%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.579ms   | ±1.43%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.155ms   | ±0.86%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 283.300ms | ±21.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.581ms   | ±1.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.925ms   | ±27.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.082ms   | ±1.48%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.214ms  | ±0.87%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.929ms  | ±1.10%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.025ms  | ±0.75%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.229ms  | ±1.14%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.051ms  | ±0.22%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 769.425μs | ±0.88%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 867.994μs | ±1.43%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 935.232μs | ±0.92%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.530ms   | ±1.05%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.241ms   | ±0.54%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 26.102ms  | ±2.29%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 30.158ms  | ±1.56%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.614ms  | ±0.46%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 61.975ms  | ±0.62%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 95.939ms  | ±0.57%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.373ms  | ±0.93%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.251ms  | ±12.92%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 20.028ms  | ±0.75%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 66.222ms  | ±0.62%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 150.795ms | ±0.89%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.975ms   | ±4.23%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 53.689ms  | ±1.49%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.710μs   | ±14.29%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 221.168ms | ±26.21%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 490.810μs | ±1.66%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.936ms   | ±0.93%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.389ms   | ±0.65%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.433ms  | ±4.61%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 83.444ms  | ±0.67%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.157ms  | ±1.33%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.153ms  | ±0.70%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 187.889ms | ±30.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.861ms  | ±0.92%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.708ms  | ±0.84%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.991ms  | ±1.55%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 14.075ms  | ±0.68%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.319ms  | ±0.78%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.057ms   | ±4.37%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.890ms  | ±0.58%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 14.036ms  | ±0.53%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.867ms  | ±1.28%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 9.800ms   | ±0.50%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 9.806ms   | ±0.58%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 11.708ms  | ±0.72%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 11.326ms  | ±0.40%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 11.110ms  | ±1.05%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 10.042ms  | ±1.23%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 17.985ms  | ±0.51%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 3.050ms   | ±0.70%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.794μs  | ±0.87%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 236.295μs | ±1.08%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.272ms   | ±0.94%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.906ms  | ±0.89%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 45.602ms  | ±0.47%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.733ms   | ±0.73%   |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.846ms   | ±0.38%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.734ms   | ±0.70%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.231ms   | ±0.59%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.489ms   | ±0.65%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.249μs   | ±15.29%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.121μs   | ±35.36%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.578mb | 16.876ms  | ±1.39%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.578mb | 16.689ms  | ±0.16%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```