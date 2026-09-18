# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-18 14:53:57 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 11.295ms | 2.295ms | 2.482ms | 4.440ms | 6.542ms |
| FPDF | 848.814μs | 863.834μs | 949.864μs | 1.545ms | 2.252ms |
| TCPDF | 9.982ms | 10.850ms | 11.681ms | 19.007ms | 28.206ms |
| mPDF | 23.913ms | 27.216ms | 30.436ms | 55.143ms | 85.601ms |
| Dompdf | 10.604ms | 14.474ms | 18.875ms | 59.839ms | 131.043ms |

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
| phpdftk | 3.119ms | 3.276ms | 3.485ms | 5.370ms | 7.759ms |
| FPDF | 1.154ms | 1.163ms | 1.282ms | 1.963ms | 2.814ms |
| TCPDF | 14.555ms | 15.578ms | 16.635ms | 24.838ms | 35.335ms |

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
| Pdf (Level 3) | 3.066ms | 4.146ms | 10.817ms |
| PdfDoc (Level 2) | 2.493ms | 2.836ms | 6.954ms |
| PdfWriter (Level 1) | 2.117ms | 2.537ms | 6.515ms |

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
| Pdf (Level 3) | 4.006ms | 10.619ms | 39.434ms |
| PdfDoc (Level 2) | 3.500ms | 8.755ms | — |

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
| Pdf (Level 3) | 3.640ms | 9.802ms | 36.046ms |
| PdfDoc (Level 2) | 2.956ms | 6.336ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.810ms | 1.355ms | 4.645ms |
| smalot/pdfparser | 1.858ms | 2.164ms | 5.078ms |
| setasign/fpdi | 1.715ms | 2.373ms | 22.891ms |

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
| phpdftk | 1.637ms | 1.134ms |
| smalot/pdfparser | FAIL | 1.735ms |
| setasign/fpdi | 2.497ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.119ms   | ±1.68%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.276ms   | ±2.16%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.485ms   | ±1.24%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.370ms   | ±0.50%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 7.759ms   | ±0.05%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.555ms  | ±1.27%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.578ms  | ±0.70%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.635ms  | ±1.45%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 24.838ms  | ±0.30%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 35.335ms  | ±0.93%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.154ms   | ±4.62%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.163ms   | ±1.17%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.282ms   | ±1.91%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.963ms   | ±1.19%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.814ms   | ±0.61%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.117ms   | ±1.40%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.537ms   | ±4.32%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.515ms   | ±73.63% |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.493ms   | ±2.96%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.836ms   | ±1.47%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 6.954ms   | ±1.20%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.066ms   | ±0.63%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.146ms   | ±0.76%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 10.817ms  | ±0.60%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.920mb | 67.922ms  | ±0.15%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 22.050mb | 291.781ms | ±1.10%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 46.778mb | 1.136s    | ±0.42%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.105mb | 201.012ms | ±0.22%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.905mb | 151.533ms | ±0.26%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.739mb | 124.799ms | ±0.48%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.863mb | 170.058ms | ±0.29%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.358mb | 147.083ms | ±2.15%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 21.060mb | 268.793ms | ±0.65%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.372mb | 41.645ms  | ±4.83%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.284mb | 36.875ms  | ±0.27%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.214mb | 34.506ms  | ±1.66%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.611mb | 112.596ms | ±0.69%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.273mb | 38.254ms  | ±0.55%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.499mb | 48.664ms  | ±0.77%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.983mb | 71.066ms  | ±1.23%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.171mb | 31.761ms  | ±0.29%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.174mb | 37.819ms  | ±0.52%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.201mb | 39.587ms  | ±0.54%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.170mb | 37.845ms  | ±0.55%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.192mb | 36.770ms  | ±0.20%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.100mb | 59.402ms  | ±0.60%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.144mb | 36.297ms  | ±0.06%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.823mb | 32.582ms  | ±0.46%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.139mb | 34.835ms  | ±0.01%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.159mb | 38.860ms  | ±0.16%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.153mb | 38.489ms  | ±0.35%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.305mb | 35.423ms  | ±1.01%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.817mb | 179.213ms | ±0.13%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.758mb | 132.293ms | ±0.08%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.588mb | 45.832ms  | ±0.99%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.206mb | 91.350ms  | ±0.43%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 44.890mb | 1.086s    | ±0.09%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.641mb | 22.445ms  | ±1.90%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.601mb | 48.947ms  | ±0.28%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.412mb | 428.398ms | ±0.17%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.925mb | 56.834ms  | ±8.83%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.578mb | 73.385ms  | ±1.20%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.347mb | 619.448ms | ±0.24%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.624mb | 17.329ms  | ±1.29%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.624mb | 37.710ms  | ±0.32%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.266mb | 265.512ms | ±0.15%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.039ms   | ±1.52%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.355ms   | ±0.19%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 4.645ms   | ±1.72%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.637ms   | ±0.82%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.134ms   | ±1.37%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.858ms   | ±0.91%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.164ms   | ±0.58%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.078ms   | ±1.08%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 518.886μs | ±1.51%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.735ms   | ±2.03%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.715ms   | ±1.04%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.373ms   | ±1.02%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 22.891ms  | ±0.31%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.497ms   | ±0.30%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.371ms   | ±0.47%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 6.047ms   | ±0.57%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 4.651ms   | ±0.99%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.449ms   | ±0.69%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.297μs   | ±27.59% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.810ms   | ±0.37%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.635mb  | 20.808ms  | ±1.52%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.033mb | 186.382ms | ±0.32%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.378mb | 919.882ms | ±0.07%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.059mb | 147.448ms | ±0.21%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.640ms   | ±0.30%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 9.802ms   | ±0.51%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 36.046ms  | ±0.16%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.956ms   | ±0.67%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 6.336ms   | ±1.15%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.510ms   | ±92.95% |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.295ms   | ±25.19% |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.482ms   | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.440ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.542ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.362ms   | ±12.43% |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.541ms   | ±0.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 10.495ms  | ±17.12% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.353ms   | ±6.16%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.169ms   | ±1.28%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 590.750μs | ±8.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.980ms   | ±0.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.341ms   | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.002ms   | ±1.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 217.181ms | ±33.17% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.325ms   | ±2.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.203ms   | ±18.67% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.401ms   | ±1.83%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.982ms   | ±0.65%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.850ms  | ±0.83%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.681ms  | ±5.32%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.007ms  | ±1.00%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.206ms  | ±3.90%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 848.814μs | ±6.00%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 863.834μs | ±1.47%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 949.864μs | ±0.28%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.545ms   | ±1.27%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.252ms   | ±0.61%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 23.913ms  | ±1.71%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 27.216ms  | ±0.73%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 30.436ms  | ±0.57%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 55.143ms  | ±1.41%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 85.601ms  | ±0.93%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 10.604ms  | ±0.42%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 14.474ms  | ±0.73%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 18.875ms  | ±0.29%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 59.839ms  | ±0.58%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 131.043ms | ±0.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.579ms   | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 40.285ms  | ±0.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.999μs   | ±14.29% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.290μs   | ±23.53% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.130μs   | ±23.39% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 154.217ms | ±46.44% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 378.379μs | ±1.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.596ms   | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.199ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 15.388ms  | ±24.39% |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 66.823ms  | ±0.12%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.417ms  | ±0.41%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.231ms  | ±0.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 169.710ms | ±18.89% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 11.346ms  | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 11.252ms  | ±1.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 11.562ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 11.620ms  | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 11.815ms  | ±2.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.016ms   | ±30.42% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 11.509ms  | ±1.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 11.443ms  | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 11.295ms  | ±0.48%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.351mb  | 9.515ms   | ±0.37%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.245mb  | 9.522ms   | ±0.55%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.397mb  | 10.823ms  | ±1.57%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.095mb  | 11.046ms  | ±1.42%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.577mb  | 10.073ms  | ±1.14%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.651mb  | 9.661ms   | ±0.73%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.896mb | 17.157ms  | ±0.63%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.791mb  | 2.794ms   | ±0.64%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 33.678μs  | ±1.18%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 209.154μs | ±3.21%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.006ms   | ±0.86%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 10.619ms  | ±0.74%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 39.434ms  | ±0.26%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.500ms   | ±10.62% |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 8.755ms   | ±0.87%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 7.681ms   | ±0.94%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 7.271ms   | ±0.61%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 7.419ms   | ±1.06%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.166μs   | ±30.86% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.208μs   | ±48.33% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.783mb | 15.066ms  | ±0.59%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.783mb | 15.013ms  | ±0.39%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```