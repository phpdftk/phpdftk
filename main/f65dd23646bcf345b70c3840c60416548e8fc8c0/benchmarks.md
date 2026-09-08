# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-08 13:15:03 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.335ms | 2.506ms | 2.765ms | 4.759ms | 7.130ms |
| FPDF | 767.551μs | 837.913μs | 931.862μs | 1.517ms | 2.268ms |
| TCPDF | 9.967ms | 10.953ms | 11.937ms | 20.483ms | 31.290ms |
| mPDF | 25.131ms | 29.261ms | 33.332ms | 65.737ms | 105.978ms |
| Dompdf | 11.320ms | 16.024ms | 21.709ms | 73.951ms | 162.901ms |

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
| phpdftk | 3.376ms | 3.541ms | 3.856ms | 5.835ms | 8.346ms |
| FPDF | 1.014ms | 1.097ms | 1.214ms | 1.904ms | 2.775ms |
| TCPDF | 14.355ms | 15.440ms | 16.681ms | 26.466ms | 38.464ms |

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
| Pdf (Level 3) | 3.386ms | 4.444ms | 12.607ms |
| PdfDoc (Level 2) | 2.677ms | 3.166ms | 7.446ms |
| PdfWriter (Level 1) | 2.301ms | 2.764ms | 6.825ms |

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
| Pdf (Level 3) | 4.431ms | 12.190ms | 47.373ms |
| PdfDoc (Level 2) | 3.814ms | 10.008ms | — |

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
| Pdf (Level 3) | 4.072ms | 11.707ms | 45.177ms |
| PdfDoc (Level 2) | 3.317ms | 7.352ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.190ms | 1.666ms | 5.934ms |
| smalot/pdfparser | 1.983ms | 2.352ms | 5.737ms |
| setasign/fpdi | 1.962ms | 2.874ms | 30.333ms |

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
| phpdftk | 2.016ms | 1.390ms |
| smalot/pdfparser | FAIL | 1.929ms |
| setasign/fpdi | 3.010ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.376ms   | ±0.89%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.541ms   | ±0.90%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.856ms   | ±0.66%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.835ms   | ±0.71%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.346ms   | ±0.66%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.355ms  | ±1.17%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.440ms  | ±0.55%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.681ms  | ±0.31%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.466ms  | ±0.69%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 38.464ms  | ±0.66%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.014ms   | ±4.16%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.097ms   | ±2.06%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.214ms   | ±1.08%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.904ms   | ±1.23%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.775ms   | ±0.91%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.301ms   | ±1.71%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.764ms   | ±0.89%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.825ms   | ±0.93%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.677ms   | ±0.72%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.166ms   | ±0.80%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.446ms   | ±0.38%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.386ms   | ±0.66%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.444ms   | ±0.78%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.607ms  | ±0.47%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.514mb | 86.316ms  | ±0.24%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.406mb | 378.755ms | ±1.22%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.469mb | 1.474s    | ±0.75%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.581mb | 260.003ms | ±0.62%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.477mb | 196.340ms | ±0.72%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.324mb | 166.485ms | ±3.36%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.395mb | 222.111ms | ±0.82%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.931mb | 183.320ms | ±0.23%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.486mb | 349.153ms | ±0.35%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.026mb | 52.300ms  | ±0.17%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.943mb | 45.205ms  | ±0.40%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.868mb | 43.215ms  | ±1.01%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.211mb | 143.150ms | ±0.38%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.932mb | 47.992ms  | ±0.26%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.149mb | 60.532ms  | ±0.69%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.578mb | 90.137ms  | ±0.09%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.835mb | 38.483ms  | ±1.13%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.773mb | 46.857ms  | ±0.23%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.800mb | 48.088ms  | ±0.14%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.769mb | 47.440ms  | ±0.57%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.856mb | 45.400ms  | ±0.36%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.709mb | 72.987ms  | ±0.98%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.816mb | 44.764ms  | ±0.94%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.436mb | 40.006ms  | ±0.23%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.737mb | 44.267ms  | ±4.21%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.758mb | 48.743ms  | ±3.84%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.751mb | 47.879ms  | ±0.39%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 18.984mb | 43.064ms  | ±0.14%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.188mb | 231.730ms | ±1.71%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.407mb | 170.205ms | ±1.72%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.213mb | 56.641ms  | ±0.30%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.821mb | 117.079ms | ±0.78%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.889mb | 1.419s    | ±0.48%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.274mb | 25.998ms  | ±2.51%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.300mb | 59.285ms  | ±0.14%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.111mb | 532.992ms | ±1.84%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.558mb | 64.215ms  | ±9.35%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.277mb | 86.076ms  | ±1.21%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.046mb | 745.641ms | ±1.17%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.322mb | 18.918ms  | ±0.56%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.322mb | 43.089ms  | ±0.56%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.965mb | 323.364ms | ±0.96%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.243ms   | ±0.54%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.666ms   | ±0.50%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.934ms   | ±1.10%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.016ms   | ±0.46%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.390ms   | ±0.96%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.983ms   | ±1.46%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.352ms   | ±0.69%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.737ms   | ±0.92%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 542.523μs | ±1.37%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.929ms   | ±1.69%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.962ms   | ±1.01%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.874ms   | ±1.01%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.333ms  | ±1.05%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.010ms   | ±0.48%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.502ms   | ±1.54%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.328ms   | ±0.84%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.476ms   | ±0.59%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.847ms   | ±0.68%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 4.792μs   | ±18.59% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.190ms   | ±1.27%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.511mb  | 26.696ms  | ±1.69%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.679mb | 240.733ms | ±0.55%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.008mb | 1.196s    | ±0.49%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.072ms   | ±0.17%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.707ms  | ±3.28%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.177ms  | ±0.17%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.317ms   | ±0.37%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.352ms   | ±2.13%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.330ms   | ±3.92%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.506ms   | ±1.62%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.765ms   | ±0.65%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.759ms   | ±1.05%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.130ms   | ±1.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.616ms   | ±0.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.825ms   | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.265ms  | ±1.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.606ms   | ±4.94%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.371ms   | ±0.57%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 625.794μs | ±2.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.182ms   | ±1.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.629ms   | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.218ms   | ±30.58% |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 193.959ms | ±39.41% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.577ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.962ms   | ±17.92% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.020ms   | ±0.54%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.967ms   | ±1.04%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.953ms  | ±0.54%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.937ms  | ±2.99%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.483ms  | ±0.27%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.290ms  | ±0.90%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 767.551μs | ±3.26%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 837.913μs | ±2.61%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 931.862μs | ±0.33%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.517ms   | ±0.55%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.268ms   | ±0.55%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.131ms  | ±1.64%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.261ms  | ±5.11%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.332ms  | ±0.64%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 65.737ms  | ±0.62%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 105.978ms | ±1.32%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.320ms  | ±0.73%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.024ms  | ±0.30%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.709ms  | ±0.99%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 73.951ms  | ±0.86%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 162.901ms | ±0.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.091ms   | ±1.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.633ms  | ±2.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.668μs   | ±14.81% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.665μs   | ±17.39% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 225.024ms | ±28.22% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 459.278μs | ±1.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.147ms   | ±7.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.495ms   | ±0.67%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 14.277ms  | ±3.41%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 86.062ms  | ±0.76%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.215ms  | ±1.22%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.081ms  | ±1.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 201.611ms | ±17.68% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.448ms  | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.217ms  | ±0.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.607ms  | ±0.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.678ms  | ±0.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.045ms  | ±0.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.194ms   | ±2.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.730ms  | ±1.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.647ms  | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.335ms  | ±0.61%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.325mb  | 10.400ms  | ±0.22%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.219mb  | 10.562ms  | ±0.57%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.372mb  | 12.198ms  | ±0.19%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.067mb  | 12.175ms  | ±0.27%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.552mb  | 11.155ms  | ±0.23%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.626mb  | 10.610ms  | ±0.17%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.869mb | 19.948ms  | ±0.80%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.769mb  | 3.115ms   | ±0.97%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.974μs  | ±1.34%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 246.000μs | ±0.51%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.431ms   | ±0.31%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.190ms  | ±0.65%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.373ms  | ±0.74%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.814ms   | ±0.71%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 10.008ms  | ±0.77%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.962ms   | ±3.46%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.562ms   | ±0.56%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.590ms   | ±7.01%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.933μs   | ±19.89% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.897μs   | ±32.32% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.461mb | 16.234ms  | ±0.72%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.461mb | 16.375ms  | ±0.19%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```