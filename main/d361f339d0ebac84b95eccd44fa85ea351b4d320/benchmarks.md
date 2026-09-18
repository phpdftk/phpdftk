# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-18 15:21:54 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.842ms | 2.552ms | 2.745ms | 4.732ms | 6.923ms |
| FPDF | 794.001μs | 862.896μs | 948.341μs | 1.548ms | 2.254ms |
| TCPDF | 10.466ms | 11.464ms | 12.241ms | 19.375ms | 28.790ms |
| mPDF | 26.464ms | 30.477ms | 34.344ms | 61.981ms | 96.385ms |
| Dompdf | 11.232ms | 15.267ms | 20.509ms | 66.873ms | 150.603ms |

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
| phpdftk | 3.427ms | 3.612ms | 3.879ms | 5.826ms | 8.289ms |
| FPDF | 1.097ms | 1.206ms | 1.673ms | 1.970ms | 2.767ms |
| TCPDF | 15.096ms | 16.290ms | 17.420ms | 25.770ms | 36.554ms |

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
| Pdf (Level 3) | 3.422ms | 4.462ms | 12.266ms |
| PdfDoc (Level 2) | 2.723ms | 3.196ms | 7.513ms |
| PdfWriter (Level 1) | 2.327ms | 2.797ms | 6.864ms |

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
| Pdf (Level 3) | 4.321ms | 11.995ms | 45.806ms |
| PdfDoc (Level 2) | 3.783ms | 9.953ms | — |

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
| Pdf (Level 3) | 4.121ms | 11.570ms | 44.602ms |
| PdfDoc (Level 2) | 3.324ms | 7.322ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.085ms | 1.678ms | 6.007ms |
| smalot/pdfparser | 2.021ms | 2.394ms | 5.584ms |
| setasign/fpdi | 1.916ms | 2.692ms | 28.908ms |

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
| phpdftk | 2.014ms | 1.377ms |
| smalot/pdfparser | FAIL | 1.906ms |
| setasign/fpdi | 2.927ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.427ms   | ±5.58%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.612ms   | ±0.61%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.879ms   | ±0.66%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.826ms   | ±0.56%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.289ms   | ±0.36%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.096ms  | ±2.77%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.290ms  | ±0.62%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.420ms  | ±0.80%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 25.770ms  | ±0.76%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 36.554ms  | ±0.32%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.097ms   | ±1.89%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.206ms   | ±1.32%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.673ms   | ±86.49% |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.970ms   | ±0.91%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.767ms   | ±0.72%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.327ms   | ±0.49%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.797ms   | ±1.54%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.864ms   | ±1.12%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.723ms   | ±0.58%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.196ms   | ±4.79%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.513ms   | ±0.28%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.422ms   | ±0.82%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.462ms   | ±1.07%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.266ms  | ±0.66%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.982mb | 83.217ms  | ±3.61%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 22.112mb | 348.368ms | ±0.42%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 46.840mb | 1.363s    | ±4.24%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.167mb | 247.964ms | ±0.33%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.967mb | 199.094ms | ±2.10%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.801mb | 154.917ms | ±1.53%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.925mb | 218.808ms | ±3.30%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.420mb | 170.250ms | ±0.62%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 21.122mb | 325.955ms | ±0.59%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.434mb | 51.398ms  | ±6.34%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.346mb | 44.434ms  | ±1.79%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.276mb | 42.705ms  | ±1.82%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.673mb | 140.253ms | ±1.72%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.335mb | 47.461ms  | ±1.81%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.561mb | 58.232ms  | ±0.92%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.045mb | 91.335ms  | ±3.36%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.233mb | 38.126ms  | ±2.98%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.236mb | 46.874ms  | ±1.73%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.263mb | 47.934ms  | ±0.42%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.232mb | 47.977ms  | ±1.80%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.254mb | 45.303ms  | ±1.86%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.162mb | 72.469ms  | ±1.03%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.206mb | 45.317ms  | ±0.27%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.885mb | 40.146ms  | ±0.88%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.201mb | 44.616ms  | ±0.17%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.221mb | 49.530ms  | ±0.76%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.215mb | 48.054ms  | ±1.67%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.367mb | 44.155ms  | ±0.38%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.879mb | 221.950ms | ±0.52%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.820mb | 165.558ms | ±0.74%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.650mb | 56.352ms  | ±0.84%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.268mb | 111.688ms | ±0.15%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 44.952mb | 1.411s    | ±3.09%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.703mb | 24.592ms  | ±2.78%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.664mb | 53.629ms  | ±1.49%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.475mb | 497.436ms | ±1.75%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.987mb | 64.630ms  | ±10.29% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.641mb | 82.548ms  | ±1.32%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.409mb | 664.344ms | ±0.57%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.686mb | 17.683ms  | ±0.33%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.686mb | 42.200ms  | ±0.69%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.328mb | 279.633ms | ±0.33%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.236ms   | ±2.06%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.678ms   | ±0.56%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.007ms   | ±1.68%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.014ms   | ±0.70%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.377ms   | ±0.86%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.021ms   | ±0.86%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.394ms   | ±1.67%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.584ms   | ±0.72%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 567.563μs | ±2.77%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.906ms   | ±1.16%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.916ms   | ±0.94%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.692ms   | ±1.10%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.908ms  | ±0.79%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.927ms   | ±0.72%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.553ms   | ±0.59%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.129ms   | ±0.53%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.384ms   | ±0.62%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.813ms   | ±0.63%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 4.109μs   | ±18.45% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.085ms   | ±0.81%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.635mb  | 27.328ms  | ±7.97%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.033mb | 233.414ms | ±7.17%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.378mb | 1.127s    | ±1.10%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.059mb | 174.740ms | ±0.26%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.121ms   | ±0.56%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.570ms  | ±1.61%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 44.602ms  | ±0.78%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.324ms   | ±0.79%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.322ms   | ±0.30%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.321ms   | ±1.00%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.552ms   | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.745ms   | ±1.13%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.732ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.923ms   | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.555ms   | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.822ms   | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.984ms  | ±0.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.587ms   | ±1.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.422ms   | ±1.13%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 605.071μs | ±2.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.176ms   | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.624ms   | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.231ms   | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 199.003ms | ±26.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.640ms   | ±1.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.943ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.026ms   | ±1.06%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.466ms  | ±0.92%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.464ms  | ±2.69%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.241ms  | ±2.70%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.375ms  | ±0.59%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.790ms  | ±0.68%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 794.001μs | ±1.81%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 862.896μs | ±0.97%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 948.341μs | ±0.98%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.548ms   | ±0.97%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.254ms   | ±0.49%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 26.464ms  | ±1.56%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 30.477ms  | ±1.80%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 34.344ms  | ±1.25%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 61.981ms  | ±3.76%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 96.385ms  | ±0.85%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.232ms  | ±1.26%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.267ms  | ±3.16%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 20.509ms  | ±0.91%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 66.873ms  | ±0.66%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 150.603ms | ±1.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.030ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 54.007ms  | ±1.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.999μs   | ±6.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 236.911ms | ±16.39% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 487.470μs | ±1.32%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.931ms   | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.352ms   | ±0.58%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.733ms  | ±7.39%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 80.437ms  | ±0.85%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.033ms  | ±1.14%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.158ms  | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 302.298ms | ±29.67% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.883ms  | ±1.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.857ms  | ±0.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 14.050ms  | ±1.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 14.174ms  | ±0.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.329ms  | ±0.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.143ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 14.054ms  | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 14.092ms  | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.842ms  | ±0.54%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.351mb  | 9.982ms   | ±0.89%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.245mb  | 10.088ms  | ±0.17%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.397mb  | 11.782ms  | ±0.81%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.095mb  | 11.545ms  | ±1.20%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.577mb  | 11.300ms  | ±1.33%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.651mb  | 10.121ms  | ±0.92%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.896mb | 18.600ms  | ±1.19%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.791mb  | 3.061ms   | ±0.58%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.392μs  | ±0.93%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 238.448μs | ±0.85%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.321ms   | ±1.07%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.995ms  | ±2.21%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 45.806ms  | ±1.47%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.783ms   | ±0.45%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.953ms   | ±1.72%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.892ms   | ±0.90%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.416ms   | ±14.69% |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.419ms   | ±0.75%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.360μs   | ±13.42% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.321μs   | ±32.89% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.845mb | 16.924ms  | ±0.23%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.845mb | 17.187ms  | ±0.53%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```