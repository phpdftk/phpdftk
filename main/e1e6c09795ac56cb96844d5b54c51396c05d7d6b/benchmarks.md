# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-10 18:01:07 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 14.933ms | 2.724ms | 2.963ms | 5.180ms | 7.399ms |
| FPDF | 855.276μs | 886.894μs | 1.030ms | 1.597ms | 2.383ms |
| TCPDF | 10.922ms | 11.866ms | 13.149ms | 21.946ms | 33.093ms |
| mPDF | 27.500ms | 31.436ms | 35.787ms | 70.670ms | 114.323ms |
| Dompdf | 11.957ms | 17.013ms | 22.722ms | 78.391ms | 172.058ms |

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
| phpdftk | 3.622ms | 4.032ms | 4.095ms | 6.297ms | 9.288ms |
| FPDF | 8.989ms | 1.330ms | 1.329ms | 1.999ms | 3.412ms |
| TCPDF | 17.202ms | 17.044ms | 18.617ms | 28.847ms | 40.639ms |

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
| Pdf (Level 3) | 3.888ms | 5.123ms | 14.015ms |
| PdfDoc (Level 2) | 3.059ms | 3.678ms | 8.455ms |
| PdfWriter (Level 1) | 2.507ms | 3.032ms | 7.516ms |

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
| Pdf (Level 3) | 4.921ms | 13.037ms | 49.980ms |
| PdfDoc (Level 2) | 4.290ms | 11.003ms | — |

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
| Pdf (Level 3) | 4.468ms | 12.626ms | 47.384ms |
| PdfDoc (Level 2) | 5.318ms | 7.893ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.928ms | 1.852ms | 6.325ms |
| smalot/pdfparser | 2.177ms | 2.553ms | 6.382ms |
| setasign/fpdi | 2.148ms | 3.087ms | 31.678ms |

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
| phpdftk | 2.198ms | 1.486ms |
| smalot/pdfparser | FAIL | 2.098ms |
| setasign/fpdi | 3.253ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.622ms   | ±2.07%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 4.032ms   | ±3.25%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 4.095ms   | ±0.58%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 6.297ms   | ±1.01%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 9.288ms   | ±19.91% |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 17.202ms  | ±16.50% |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 17.044ms  | ±3.06%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 18.617ms  | ±2.40%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 28.847ms  | ±0.13%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 40.639ms  | ±0.53%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 8.989ms   | ±64.47% |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.330ms   | ±54.26% |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.329ms   | ±1.77%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.999ms   | ±1.09%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 3.412ms   | ±67.04% |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.507ms   | ±3.95%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 3.032ms   | ±5.26%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 7.516ms   | ±10.14% |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 3.059ms   | ±4.10%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.678ms   | ±6.91%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 8.455ms   | ±2.94%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.888ms   | ±5.32%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 5.123ms   | ±3.69%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 14.015ms  | ±5.54%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.556mb | 90.283ms  | ±1.06%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.472mb | 402.225ms | ±0.53%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.505mb | 1.561s    | ±1.34%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.623mb | 286.212ms | ±1.93%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.585mb | 211.476ms | ±0.55%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.365mb | 171.173ms | ±0.45%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.436mb | 231.605ms | ±0.28%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.973mb | 194.688ms | ±1.43%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.528mb | 373.036ms | ±1.35%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.068mb | 56.288ms  | ±1.96%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.984mb | 48.752ms  | ±1.00%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.910mb | 45.727ms  | ±0.90%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.254mb | 155.661ms | ±1.09%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.973mb | 51.374ms  | ±0.15%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.191mb | 64.646ms  | ±0.15%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.619mb | 96.587ms  | ±0.58%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.877mb | 41.063ms  | ±0.67%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.880mb | 49.636ms  | ±0.79%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.907mb | 51.511ms  | ±2.91%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.876mb | 56.323ms  | ±3.51%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.898mb | 51.196ms  | ±3.09%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.751mb | 81.571ms  | ±2.56%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.858mb | 47.073ms  | ±0.60%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.477mb | 43.710ms  | ±0.55%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.779mb | 51.049ms  | ±1.78%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.865mb | 51.786ms  | ±0.88%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.859mb | 51.642ms  | ±1.09%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.025mb | 46.851ms  | ±0.90%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.230mb | 247.431ms | ±2.23%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.448mb | 189.620ms | ±0.78%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.255mb | 65.540ms  | ±3.40%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.862mb | 125.003ms | ±1.11%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.911mb | 1.604s    | ±0.99%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.381mb | 31.337ms  | ±2.57%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.341mb | 65.409ms  | ±5.59%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.152mb | 679.605ms | ±2.03%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.599mb | 71.765ms  | ±11.80% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.318mb | 95.986ms  | ±0.72%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.087mb | 813.529ms | ±0.25%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.363mb | 20.445ms  | ±2.05%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.363mb | 47.226ms  | ±1.07%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.006mb | 368.322ms | ±0.67%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.453ms   | ±1.35%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.852ms   | ±1.99%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.325ms   | ±0.99%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.198ms   | ±0.34%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.486ms   | ±1.05%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.177ms   | ±1.10%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.553ms   | ±0.52%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 6.382ms   | ±0.93%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 618.958μs | ±3.78%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 2.098ms   | ±1.38%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 2.148ms   | ±1.03%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 3.087ms   | ±1.40%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 31.678ms  | ±0.87%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.253ms   | ±1.62%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.676ms   | ±1.47%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.860ms   | ±1.09%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.918ms   | ±0.85%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 4.097ms   | ±1.78%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.915μs   | ±14.02% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.928ms   | ±3.81%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.542mb  | 29.537ms  | ±2.31%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.711mb | 256.956ms | ±1.31%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.039mb | 1.287s    | ±0.82%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.820mb | 203.830ms | ±0.43%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.468ms   | ±1.19%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 12.626ms  | ±16.04% |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 47.384ms  | ±0.93%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 5.318ms   | ±46.48% |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.893ms   | ±1.53%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.497ms   | ±1.31%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.724ms   | ±54.62% |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.963ms   | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 5.180ms   | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.399ms   | ±0.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.901ms   | ±58.36% |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 4.095ms   | ±2.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 13.240ms  | ±3.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 4.346ms   | ±14.41% |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.569ms   | ±1.39%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 752.616μs | ±8.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 4.134ms   | ±50.35% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 4.126ms   | ±19.00% |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.488ms   | ±0.94%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 194.204ms | ±19.49% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.896ms   | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.682ms   | ±32.82% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.404ms   | ±2.21%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.922ms  | ±2.18%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.866ms  | ±1.07%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 13.149ms  | ±1.33%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 21.946ms  | ±1.42%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 33.093ms  | ±0.92%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 855.276μs | ±3.75%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 886.894μs | ±2.59%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 1.030ms   | ±16.67% |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.597ms   | ±1.55%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.383ms   | ±1.26%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 27.500ms  | ±10.03% |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 31.436ms  | ±2.37%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 35.787ms  | ±1.71%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 70.670ms  | ±0.63%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 114.323ms | ±0.48%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.957ms  | ±2.50%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 17.013ms  | ±8.01%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 22.722ms  | ±2.01%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 78.391ms  | ±0.79%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 172.058ms | ±0.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.466ms   | ±1.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 51.749ms  | ±1.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.710μs   | ±14.29% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 296.380ms | ±31.83% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 528.156μs | ±1.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.482ms   | ±3.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 4.177ms   | ±43.21% |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.057ms  | ±18.16% |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 90.204ms  | ±1.32%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.080ms  | ±11.36% |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.385ms  | ±3.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 259.738ms | ±18.92% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 15.580ms  | ±1.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 15.597ms  | ±3.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 15.056ms  | ±3.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 15.505ms  | ±9.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 15.351ms  | ±3.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.828ms   | ±30.61% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 15.486ms  | ±2.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 15.289ms  | ±1.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 14.933ms  | ±1.76%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 11.693ms  | ±1.35%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 11.534ms  | ±1.10%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 13.003ms  | ±1.89%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 13.487ms  | ±2.16%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 12.451ms  | ±2.20%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 11.696ms  | ±0.56%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 20.961ms  | ±0.64%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 3.477ms   | ±0.59%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 50.636μs  | ±2.34%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 276.836μs | ±1.71%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.921ms   | ±1.92%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 13.037ms  | ±1.63%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 49.980ms  | ±0.87%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 4.290ms   | ±1.61%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 11.003ms  | ±2.20%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 10.107ms  | ±1.01%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 9.441ms   | ±2.44%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 9.848ms   | ±17.46% |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.166μs   | ±18.00% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.133μs   | ±37.94% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.502mb | 18.524ms  | ±3.52%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.502mb | 17.760ms  | ±0.18%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```