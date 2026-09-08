# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-08 12:26:09 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.735ms | 2.496ms | 2.738ms | 4.699ms | 6.896ms |
| FPDF | 768.219μs | 850.776μs | 939.175μs | 1.510ms | 2.248ms |
| TCPDF | 10.072ms | 10.893ms | 11.848ms | 19.170ms | 28.264ms |
| mPDF | 25.571ms | 29.144ms | 32.667ms | 60.729ms | 95.351ms |
| Dompdf | 11.193ms | 15.293ms | 20.070ms | 66.273ms | 149.256ms |

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
| phpdftk | 3.339ms | 3.564ms | 3.825ms | 5.822ms | 8.186ms |
| FPDF | 1.050ms | 1.182ms | 1.260ms | 1.912ms | 2.692ms |
| TCPDF | 14.708ms | 15.351ms | 16.705ms | 24.961ms | 35.381ms |

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
| Pdf (Level 3) | 3.411ms | 4.333ms | 12.100ms |
| PdfDoc (Level 2) | 2.666ms | 3.176ms | 7.594ms |
| PdfWriter (Level 1) | 2.283ms | 2.744ms | 6.770ms |

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
| Pdf (Level 3) | 4.340ms | 11.941ms | 45.384ms |
| PdfDoc (Level 2) | 3.753ms | 9.882ms | — |

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
| Pdf (Level 3) | 4.050ms | 11.487ms | 44.750ms |
| PdfDoc (Level 2) | 3.283ms | 7.280ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.095ms | 1.659ms | 6.063ms |
| smalot/pdfparser | 1.995ms | 2.325ms | 5.492ms |
| setasign/fpdi | 1.889ms | 2.695ms | 28.656ms |

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
| phpdftk | 2.006ms | 1.331ms |
| smalot/pdfparser | FAIL | 1.884ms |
| setasign/fpdi | 2.876ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.339ms   | ±1.12%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.564ms   | ±0.55%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.825ms   | ±1.97%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.822ms   | ±5.65%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.186ms   | ±0.61%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.708ms  | ±1.12%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.351ms  | ±0.27%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.705ms  | ±0.35%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 24.961ms  | ±0.36%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 35.381ms  | ±0.20%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.050ms   | ±3.03%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.182ms   | ±2.71%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.260ms   | ±1.62%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.912ms   | ±1.65%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.692ms   | ±0.81%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.283ms   | ±1.74%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.744ms   | ±0.31%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.770ms   | ±0.40%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.666ms   | ±3.31%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.176ms   | ±7.09%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.594ms   | ±1.90%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.411ms   | ±1.52%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.333ms   | ±0.40%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.100ms  | ±0.65%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.506mb | 85.215ms  | ±3.30%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.398mb | 338.662ms | ±0.80%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.461mb | 1.344s    | ±4.60%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.572mb | 242.056ms | ±0.79%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.469mb | 194.471ms | ±1.53%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.315mb | 150.955ms | ±3.91%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.386mb | 209.348ms | ±3.28%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.923mb | 167.634ms | ±3.10%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.478mb | 321.127ms | ±0.47%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.018mb | 49.110ms  | ±3.60%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.935mb | 42.615ms  | ±3.09%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.860mb | 42.332ms  | ±2.06%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.203mb | 132.129ms | ±0.83%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.923mb | 44.307ms  | ±1.16%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.141mb | 56.284ms  | ±2.96%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.569mb | 82.859ms  | ±0.39%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.827mb | 36.144ms  | ±0.85%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.764mb | 43.949ms  | ±0.33%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.792mb | 46.462ms  | ±0.74%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.760mb | 45.182ms  | ±0.48%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.848mb | 44.242ms  | ±1.30%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.701mb | 70.595ms  | ±1.77%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.775mb | 43.082ms  | ±0.88%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.428mb | 37.699ms  | ±1.80%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.729mb | 40.944ms  | ±0.16%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.750mb | 46.492ms  | ±1.94%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.743mb | 45.566ms  | ±0.39%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 18.943mb | 41.894ms  | ±1.74%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.179mb | 214.164ms | ±2.23%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.398mb | 164.576ms | ±5.63%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.205mb | 53.915ms  | ±0.60%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.813mb | 111.173ms | ±2.72%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.880mb | 1.300s    | ±0.25%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.266mb | 23.767ms  | ±2.14%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.292mb | 52.256ms  | ±0.71%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.103mb | 463.947ms | ±0.64%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.549mb | 63.441ms  | ±9.57%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.269mb | 82.184ms  | ±1.00%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.038mb | 658.293ms | ±0.68%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.314mb | 17.620ms  | ±0.21%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.314mb | 41.090ms  | ±0.31%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.956mb | 275.711ms | ±0.88%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.213ms   | ±0.97%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.659ms   | ±0.67%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.063ms   | ±0.71%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.006ms   | ±0.55%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.331ms   | ±1.30%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.995ms   | ±0.59%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.325ms   | ±1.68%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.492ms   | ±0.77%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 553.365μs | ±4.07%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.884ms   | ±1.17%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.889ms   | ±1.07%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.695ms   | ±1.71%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.656ms  | ±0.41%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.876ms   | ±0.60%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.494ms   | ±2.02%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.085ms   | ±0.41%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.372ms   | ±0.24%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.812ms   | ±0.75%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 4.121μs   | ±20.20% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.095ms   | ±0.44%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.503mb  | 24.672ms  | ±0.63%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.671mb | 255.118ms | ±7.76%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 47.999mb | 1.103s    | ±8.07%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.050ms   | ±0.30%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.487ms  | ±2.30%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 44.750ms  | ±0.91%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.283ms   | ±0.93%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.280ms   | ±2.12%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.269ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.496ms   | ±1.40%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.738ms   | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.699ms   | ±0.36%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.896ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.536ms   | ±1.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.713ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.869ms  | ±11.87% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.581ms   | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.336ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 595.301μs | ±18.22% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.120ms   | ±0.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.561ms   | ±0.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.187ms   | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 187.177ms | ±8.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.510ms   | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.851ms   | ±23.98% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.983ms   | ±0.57%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.072ms  | ±0.73%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.893ms  | ±0.43%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.848ms  | ±0.49%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.170ms  | ±0.27%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.264ms  | ±1.66%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 768.219μs | ±1.77%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 850.776μs | ±2.46%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 939.175μs | ±2.20%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.510ms   | ±0.47%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.248ms   | ±0.67%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.571ms  | ±2.14%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.144ms  | ±0.94%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.667ms  | ±1.68%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 60.729ms  | ±0.90%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 95.351ms  | ±0.31%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.193ms  | ±0.55%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.293ms  | ±2.38%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 20.070ms  | ±0.55%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 66.273ms  | ±0.95%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 149.256ms | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.892ms   | ±3.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 53.035ms  | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 190.518ms | ±18.69% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 489.082μs | ±1.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.920ms   | ±1.15%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.342ms   | ±1.00%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.526ms  | ±3.48%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 81.197ms  | ±1.03%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.169ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.393ms  | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 197.637ms | ±29.92% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.917ms  | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.852ms  | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 14.013ms  | ±1.17%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 14.050ms  | ±5.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.287ms  | ±2.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.104ms   | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.994ms  | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.989ms  | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.735ms  | ±0.63%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.317mb  | 9.756ms   | ±0.33%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.211mb  | 9.820ms   | ±0.51%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.364mb  | 11.464ms  | ±0.45%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.060mb  | 11.270ms  | ±0.73%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.544mb  | 10.851ms  | ±5.13%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.618mb  | 9.895ms   | ±0.26%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.861mb | 17.636ms  | ±0.64%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.761mb  | 3.016ms   | ±0.66%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.648μs  | ±1.08%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 236.847μs | ±0.53%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.340ms   | ±0.46%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.941ms  | ±0.59%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 45.384ms  | ±0.89%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.753ms   | ±0.49%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.882ms   | ±0.21%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.715ms   | ±2.43%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.328ms   | ±0.54%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.436ms   | ±0.62%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.166μs   | ±18.00% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.219μs   | ±32.90% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.452mb | 16.029ms  | ±0.60%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.452mb | 16.069ms  | ±0.29%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```