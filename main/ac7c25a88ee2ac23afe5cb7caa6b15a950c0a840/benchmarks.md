# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-18 13:38:29 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 7.829ms | 1.522ms | 1.678ms | 2.861ms | 4.041ms |
| FPDF | 529.942μs | 565.763μs | 591.439μs | 943.973μs | 1.360ms |
| TCPDF | 6.205ms | 6.782ms | 7.377ms | 11.747ms | 17.279ms |
| mPDF | 15.392ms | 17.795ms | 20.444ms | 40.109ms | 59.892ms |
| Dompdf | 7.099ms | 9.364ms | 13.455ms | 41.629ms | 90.230ms |

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
| phpdftk | 41.125ms | 42.295ms | 2.160ms | 3.248ms | 4.565ms |
| FPDF | 703.945μs | 726.048μs | 42.414ms | 1.131ms | 1.652ms |
| TCPDF | 8.519ms | 9.155ms | 9.656ms | 14.662ms | 21.358ms |

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
| Pdf (Level 3) | 1.906ms | 2.604ms | 6.639ms |
| PdfDoc (Level 2) | 1.590ms | 1.777ms | 4.172ms |
| PdfWriter (Level 1) | 1.395ms | 1.629ms | 3.777ms |

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
| Pdf (Level 3) | 2.805ms | 7.134ms | 26.714ms |
| PdfDoc (Level 2) | 2.303ms | 6.244ms | — |

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
| Pdf (Level 3) | 2.413ms | 6.805ms | 26.528ms |
| PdfDoc (Level 2) | 2.004ms | 4.522ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 3.231ms | 921.311μs | 3.052ms |
| smalot/pdfparser | 1.202ms | 1.455ms | 3.172ms |
| setasign/fpdi | 1.137ms | 1.702ms | 15.002ms |

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
| phpdftk | 1.091ms | 818.019μs |
| smalot/pdfparser | FAIL | 1.129ms |
| setasign/fpdi | 1.661ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 41.125ms  | ±0.30%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 42.295ms  | ±5.07%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 2.160ms   | ±0.55%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 3.248ms   | ±1.15%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 4.565ms   | ±1.37%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 8.519ms   | ±2.18%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 9.155ms   | ±1.18%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 9.656ms   | ±0.50%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 14.662ms  | ±5.29%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 21.358ms  | ±0.50%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 703.945μs | ±3.91%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 726.048μs | ±2.89%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 42.414ms  | ±69.05% |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.131ms   | ±0.36%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 1.652ms   | ±32.51% |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 1.395ms   | ±2.55%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 1.629ms   | ±1.22%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 3.777ms   | ±0.75%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 1.590ms   | ±3.80%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 1.777ms   | ±12.96% |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 4.172ms   | ±1.94%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 1.906ms   | ±1.63%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 2.604ms   | ±2.50%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 6.639ms   | ±0.48%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.839mb | 42.532ms  | ±1.17%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.976mb | 182.800ms | ±4.39%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 46.704mb | 730.697ms | ±1.05%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.031mb | 127.134ms | ±0.41%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.831mb | 98.689ms  | ±2.11%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.658mb | 82.626ms  | ±1.71%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.779mb | 107.192ms | ±1.29%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.277mb | 90.976ms  | ±9.61%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.979mb | 169.344ms | ±0.48%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.292mb | 26.132ms  | ±2.10%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.204mb | 22.874ms  | ±0.09%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.133mb | 22.077ms  | ±0.63%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.530mb | 72.639ms  | ±1.12%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.193mb | 24.061ms  | ±0.66%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.419mb | 29.856ms  | ±0.23%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.903mb | 43.718ms  | ±0.49%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.090mb | 19.565ms  | ±0.47%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.093mb | 23.860ms  | ±0.58%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.121mb | 24.741ms  | ±0.13%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.089mb | 24.018ms  | ±0.22%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.112mb | 23.287ms  | ±0.34%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.026mb | 36.843ms  | ±1.39%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.069mb | 23.045ms  | ±0.24%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.748mb | 20.365ms  | ±0.60%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.058mb | 22.103ms  | ±0.45%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.079mb | 24.729ms  | ±0.37%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.072mb | 24.565ms  | ±0.28%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.231mb | 22.712ms  | ±0.89%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.737mb | 115.512ms | ±0.44%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.677mb | 86.709ms  | ±1.38%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.507mb | 29.273ms  | ±4.25%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.125mb | 59.215ms  | ±1.65%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 44.816mb | 712.042ms | ±0.37%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.576mb | 19.720ms  | ±97.34% |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.537mb | 32.264ms  | ±40.53% |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.348mb | 261.140ms | ±0.68%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.860mb | 34.984ms  | ±8.63%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.514mb | 45.221ms  | ±1.52%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.283mb | 386.664ms | ±0.53%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.559mb | 10.934ms  | ±13.16% |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.559mb | 23.607ms  | ±14.49% |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.201mb | 170.655ms | ±3.79%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 708.348μs | ±1.54%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 921.311μs | ±1.91%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 3.052ms   | ±1.89%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.091ms   | ±4.33%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 818.019μs | ±2.03%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.202ms   | ±3.52%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.455ms   | ±3.00%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 3.172ms   | ±1.12%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 343.562μs | ±4.64%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.129ms   | ±1.50%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.137ms   | ±3.23%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 1.702ms   | ±1.27%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 15.002ms  | ±3.10%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 1.661ms   | ±24.35% |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 902.260μs | ±0.77%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 3.807ms   | ±1.09%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 2.964ms   | ±0.56%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.254ms   | ±2.55%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.808μs   | ±24.66% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 3.231ms   | ±2.72%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.635mb  | 13.314ms  | ±0.62%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.033mb | 118.819ms | ±1.91%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.378mb | 620.224ms | ±1.09%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.059mb | 97.164ms  | ±1.90%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 2.413ms   | ±1.37%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 6.805ms   | ±12.59% |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 26.528ms  | ±2.34%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.004ms   | ±1.36%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 4.522ms   | ±12.65% |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 1.418ms   | ±49.28% |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 1.522ms   | ±1.65%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 1.678ms   | ±0.80%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 2.861ms   | ±40.09% |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 4.041ms   | ±1.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 2.218ms   | ±12.58% |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 2.301ms   | ±52.32% |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 7.270ms   | ±15.09% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.275ms   | ±15.34% |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.443ms   | ±0.27%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 418.490μs | ±3.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 1.908ms   | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 2.148ms   | ±66.95% |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 1.916ms   | ±1.32%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 112.019ms | ±33.70% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 2.114ms   | ±2.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 3.539ms   | ±31.31% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 3.513ms   | ±0.51%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 6.205ms   | ±1.10%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 6.782ms   | ±2.19%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 7.377ms   | ±1.44%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 11.747ms  | ±8.51%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 17.279ms  | ±1.02%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 529.942μs | ±1.32%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 565.763μs | ±2.26%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 591.439μs | ±1.11%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 943.973μs | ±1.14%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.360ms   | ±2.22%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 15.392ms  | ±1.49%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 17.795ms  | ±1.62%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 20.444ms  | ±6.13%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 40.109ms  | ±3.52%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 59.892ms  | ±2.25%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 7.099ms   | ±3.33%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 9.364ms   | ±1.01%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 13.455ms  | ±4.57%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 41.629ms  | ±0.99%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 90.230ms  | ±1.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 3.106ms   | ±16.61% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 28.343ms  | ±8.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.333μs   | ±15.81% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.334μs   | ±9.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.334μs   | ±9.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 120.987ms | ±23.47% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 280.746μs | ±7.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 1.752ms   | ±2.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 2.053ms   | ±1.13%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 7.245ms   | ±3.83%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 50.018ms  | ±1.79%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 7.354ms   | ±1.15%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 13.612ms  | ±1.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 153.278ms | ±29.69% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 8.036ms   | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 7.888ms   | ±0.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 8.156ms   | ±3.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 8.266ms   | ±2.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 8.437ms   | ±2.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.068ms   | ±0.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 8.181ms   | ±3.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 8.169ms   | ±1.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 7.829ms   | ±1.98%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.351mb  | 6.450ms   | ±2.60%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.245mb  | 6.296ms   | ±1.65%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.397mb  | 7.324ms   | ±1.29%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.095mb  | 7.441ms   | ±0.99%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.577mb  | 6.975ms   | ±2.74%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.651mb  | 6.321ms   | ±0.57%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.896mb | 12.044ms  | ±0.66%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.791mb  | 2.044ms   | ±2.61%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 24.026μs  | ±3.71%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 137.842μs | ±1.41%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 2.805ms   | ±2.66%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 7.134ms   | ±2.41%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 26.714ms  | ±2.06%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 2.303ms   | ±5.77%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 6.244ms   | ±1.65%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 5.415ms   | ±2.46%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 4.913ms   | ±0.66%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 4.965ms   | ±0.98%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.933μs   | ±19.89% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 3.866μs   | ±92.64% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.703mb | 9.969ms   | ±2.04%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.703mb | 9.892ms   | ±2.58%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```