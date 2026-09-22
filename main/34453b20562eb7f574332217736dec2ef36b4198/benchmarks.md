# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-22 20:35:45 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.609ms | 2.474ms | 2.724ms | 4.668ms | 6.936ms |
| FPDF | 770.172μs | 834.190μs | 922.755μs | 1.509ms | 2.253ms |
| TCPDF | 9.955ms | 10.830ms | 11.796ms | 19.099ms | 28.139ms |
| mPDF | 25.544ms | 28.930ms | 32.626ms | 60.251ms | 95.095ms |
| Dompdf | 11.035ms | 15.138ms | 19.882ms | 66.233ms | 148.402ms |

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
| phpdftk | 3.261ms | 3.495ms | 3.741ms | 5.716ms | 8.231ms |
| FPDF | 1.043ms | 1.106ms | 1.203ms | 1.925ms | 2.698ms |
| TCPDF | 18.557ms | 15.385ms | 16.565ms | 24.788ms | 35.562ms |

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
| Pdf (Level 3) | 3.324ms | 4.277ms | 12.029ms |
| PdfDoc (Level 2) | 2.621ms | 3.079ms | 7.389ms |
| PdfWriter (Level 1) | 2.435ms | 2.711ms | 6.775ms |

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
| Pdf (Level 3) | 4.292ms | 11.779ms | 45.262ms |
| PdfDoc (Level 2) | 3.692ms | 9.780ms | — |

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
| Pdf (Level 3) | 4.015ms | 11.469ms | 44.548ms |
| PdfDoc (Level 2) | 3.246ms | 7.217ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.060ms | 1.633ms | 6.035ms |
| smalot/pdfparser | 1.966ms | 2.323ms | 5.431ms |
| setasign/fpdi | 1.896ms | 2.682ms | 28.877ms |

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
| phpdftk | 1.993ms | 1.319ms |
| smalot/pdfparser | FAIL | 1.873ms |
| setasign/fpdi | 2.892ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.261ms   | ±0.79%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.495ms   | ±0.46%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.741ms   | ±0.86%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.716ms   | ±0.42%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.231ms   | ±7.78%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 18.557ms  | ±87.12% |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.385ms  | ±0.30%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.565ms  | ±1.05%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 24.788ms  | ±0.07%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 35.562ms  | ±2.91%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.043ms   | ±0.20%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.106ms   | ±0.15%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.203ms   | ±0.14%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.925ms   | ±1.80%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.698ms   | ±0.78%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.435ms   | ±82.87% |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.711ms   | ±0.44%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.775ms   | ±0.53%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.621ms   | ±0.53%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.079ms   | ±0.25%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.389ms   | ±0.76%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.324ms   | ±0.43%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.277ms   | ±0.46%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.029ms  | ±0.30%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.695mb | 81.532ms  | ±0.40%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 26.929mb | 348.231ms | ±0.46%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 63.969mb | 1.376s    | ±4.73%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.909mb | 245.340ms | ±0.34%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.674mb | 196.787ms | ±1.23%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.594mb | 154.435ms | ±5.36%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 20.814mb | 207.861ms | ±0.49%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 20.048mb | 169.785ms | ±0.66%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.245mb | 336.175ms | ±1.64%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.151mb | 49.932ms  | ±1.99%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.063mb | 43.795ms  | ±0.25%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.990mb | 44.036ms  | ±0.14%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.398mb | 134.423ms | ±0.43%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 17.052mb | 46.156ms  | ±0.34%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.279mb | 57.780ms  | ±3.94%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.742mb | 84.324ms  | ±0.31%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.947mb | 37.514ms  | ±0.23%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.882mb | 45.183ms  | ±1.20%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.910mb | 47.534ms  | ±1.67%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.878mb | 45.955ms  | ±0.41%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.900mb | 44.182ms  | ±2.03%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.822mb | 70.158ms  | ±0.33%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.931mb | 44.049ms  | ±0.97%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.544mb | 38.541ms  | ±0.78%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.847mb | 42.129ms  | ±0.21%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.868mb | 46.919ms  | ±0.33%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.861mb | 47.233ms  | ±1.67%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.092mb | 43.637ms  | ±1.78%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.263mb | 222.441ms | ±1.38%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.519mb | 167.414ms | ±1.93%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.378mb | 55.898ms  | ±3.41%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.984mb | 112.484ms | ±0.23%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 64.743mb | 1.349s    | ±3.12%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.269mb | 23.676ms  | ±2.38%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.295mb | 52.252ms  | ±0.27%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 58.041mb | 452.763ms | ±0.40%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.552mb | 63.816ms  | ±9.10%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.206mb | 82.161ms  | ±1.39%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.975mb | 656.621ms | ±0.73%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.317mb | 17.328ms  | ±0.67%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.317mb | 40.985ms  | ±0.43%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.960mb | 274.089ms | ±0.06%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.215ms   | ±1.19%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.633ms   | ±0.84%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.035ms   | ±1.07%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.993ms   | ±1.00%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.319ms   | ±0.40%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.966ms   | ±1.17%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.323ms   | ±4.22%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.431ms   | ±0.57%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 541.751μs | ±0.61%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.873ms   | ±0.70%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.896ms   | ±1.28%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.682ms   | ±0.58%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.877ms  | ±0.54%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.892ms   | ±0.46%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.481ms   | ±1.06%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.043ms   | ±0.44%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.309ms   | ±0.78%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.796ms   | ±0.84%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 4.085μs   | ±14.78% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.060ms   | ±1.14%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.766mb  | 29.537ms  | ±7.95%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.204mb | 257.426ms | ±6.91%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.729mb | 1.131s    | ±0.10%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.211mb | 194.002ms | ±6.16%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 27.046mb | 949.751ms | ±3.27%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 24.133mb | 767.712ms | ±4.11%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.015ms   | ±0.42%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.469ms  | ±2.62%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 44.548ms  | ±1.21%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.246ms   | ±0.35%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.217ms   | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.280ms   | ±1.29%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.474ms   | ±0.36%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.724ms   | ±0.40%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.668ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.936ms   | ±4.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.494ms   | ±1.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.752ms   | ±1.16%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.930ms  | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.522ms   | ±1.06%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.348ms   | ±1.89%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 628.496μs | ±4.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.093ms   | ±3.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.534ms   | ±0.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.160ms   | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 257.492ms | ±13.15% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.494ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.765ms   | ±25.45% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.930ms   | ±0.42%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.955ms   | ±0.46%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.830ms  | ±0.23%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.796ms  | ±0.50%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.099ms  | ±0.61%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.139ms  | ±0.61%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 770.172μs | ±2.23%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 834.190μs | ±1.25%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 922.755μs | ±1.14%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.509ms   | ±0.94%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.253ms   | ±0.53%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.544ms  | ±1.90%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 28.930ms  | ±0.24%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.626ms  | ±0.20%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 60.251ms  | ±0.19%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 95.095ms  | ±0.57%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.035ms  | ±1.36%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.138ms  | ±0.20%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 19.882ms  | ±0.81%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 66.233ms  | ±0.36%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 148.402ms | ±0.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.912ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 53.275ms  | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 219.599ms | ±31.20% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 490.549μs | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.881ms   | ±0.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.309ms   | ±0.71%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.602ms  | ±5.35%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 82.356ms  | ±0.64%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.113ms  | ±5.43%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.187ms  | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 279.702ms | ±27.01% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.857ms  | ±4.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.597ms  | ±0.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.885ms  | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 14.006ms  | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.287ms  | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.055ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.961ms  | ±5.24%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.968ms  | ±0.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.609ms  | ±1.63%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.848mb  | 13.425ms  | ±0.60%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.648mb  | 12.188ms  | ±0.74%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.968mb  | 20.857ms  | ±3.93%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.916mb  | 17.058ms  | ±3.03%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 10.055mb | 23.670ms  | ±0.22%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 9.418mb  | 12.913ms  | ±1.09%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.602mb  | 15.679ms  | ±1.39%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 11.914mb | 35.947ms  | ±3.40%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 7.792mb  | 6.175ms   | ±0.50%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 43.137μs  | ±1.30%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 233.226μs | ±0.65%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.292ms   | ±0.28%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.779ms  | ±0.66%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 45.262ms  | ±0.81%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.692ms   | ±17.86% |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.780ms   | ±1.08%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.726ms   | ±1.14%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.271ms   | ±0.64%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.343ms   | ±0.34%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.061μs   | ±20.20% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.319μs   | ±25.50% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.456mb | 17.868ms  | ±0.66%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.456mb | 17.749ms  | ±0.38%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```