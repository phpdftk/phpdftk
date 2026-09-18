# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-18 11:38:58 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.118ms | 2.503ms | 2.750ms | 4.765ms | 6.966ms |
| FPDF | 800.682μs | 852.037μs | 923.821μs | 1.520ms | 2.258ms |
| TCPDF | 9.922ms | 10.820ms | 11.943ms | 20.285ms | 30.959ms |
| mPDF | 25.229ms | 28.960ms | 33.019ms | 65.475ms | 104.585ms |
| Dompdf | 11.174ms | 15.724ms | 21.320ms | 72.297ms | 159.467ms |

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
| phpdftk | 3.256ms | 3.534ms | 3.765ms | 5.855ms | 8.188ms |
| FPDF | 1.016ms | 1.096ms | 1.220ms | 1.868ms | 2.711ms |
| TCPDF | 14.232ms | 15.781ms | 16.637ms | 26.126ms | 38.502ms |

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
| Pdf (Level 3) | 3.298ms | 4.360ms | 12.432ms |
| PdfDoc (Level 2) | 2.692ms | 3.136ms | 7.466ms |
| PdfWriter (Level 1) | 2.342ms | 2.722ms | 6.794ms |

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
| Pdf (Level 3) | 4.307ms | 11.979ms | 46.599ms |
| PdfDoc (Level 2) | 3.739ms | 9.879ms | — |

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
| Pdf (Level 3) | 4.023ms | 11.591ms | 44.652ms |
| PdfDoc (Level 2) | 3.256ms | 7.246ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.165ms | 1.657ms | 5.948ms |
| smalot/pdfparser | 1.981ms | 2.348ms | 5.622ms |
| setasign/fpdi | 1.935ms | 2.794ms | 29.743ms |

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
| phpdftk | 2.011ms | 1.355ms |
| smalot/pdfparser | FAIL | 1.880ms |
| setasign/fpdi | 2.988ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.256ms   | ±2.26%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.534ms   | ±0.33%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.765ms   | ±0.31%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.855ms   | ±0.57%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.188ms   | ±0.76%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.232ms  | ±0.99%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.781ms  | ±4.60%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.637ms  | ±0.30%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.126ms  | ±0.58%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 38.502ms  | ±0.86%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.016ms   | ±10.82% |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.096ms   | ±3.56%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.220ms   | ±2.59%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.868ms   | ±0.54%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.711ms   | ±0.25%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.342ms   | ±8.54%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.722ms   | ±0.74%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.794ms   | ±0.82%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.692ms   | ±9.73%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.136ms   | ±7.28%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.466ms   | ±0.90%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.298ms   | ±2.27%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.360ms   | ±0.62%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.432ms  | ±0.11%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.717mb | 85.654ms  | ±1.17%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.735mb | 380.714ms | ±0.33%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.934mb | 1.481s    | ±0.43%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.878mb | 260.122ms | ±0.40%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.741mb | 198.781ms | ±0.17%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.529mb | 164.977ms | ±1.50%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.631mb | 219.195ms | ±1.19%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.145mb | 183.738ms | ±2.91%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.768mb | 345.794ms | ±0.09%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.218mb | 52.477ms  | ±1.17%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.134mb | 45.554ms  | ±0.34%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.058mb | 42.519ms  | ±0.66%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.417mb | 143.877ms | ±1.08%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.123mb | 47.777ms  | ±0.37%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.341mb | 60.625ms  | ±0.11%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.777mb | 89.860ms  | ±0.21%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.025mb | 38.393ms  | ±0.26%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.028mb | 46.354ms  | ±0.10%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.055mb | 48.187ms  | ±0.62%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.024mb | 46.899ms  | ±0.31%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.046mb | 45.653ms  | ±0.52%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.960mb | 71.281ms  | ±0.41%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.001mb | 44.494ms  | ±0.66%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.686mb | 39.568ms  | ±3.62%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.993mb | 43.357ms  | ±0.99%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.013mb | 47.979ms  | ±0.28%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.007mb | 47.922ms  | ±0.29%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.168mb | 43.097ms  | ±0.35%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.404mb | 229.936ms | ±0.79%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.604mb | 170.496ms | ±0.79%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.411mb | 57.176ms  | ±1.19%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.024mb | 116.339ms | ±0.14%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 43.265mb | 1.437s    | ±0.58%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.518mb | 25.594ms  | ±2.11%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.479mb | 57.083ms  | ±1.11%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.290mb | 525.356ms | ±0.55%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.802mb | 63.874ms  | ±9.06%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.456mb | 85.363ms  | ±0.90%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.225mb | 725.733ms | ±0.59%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.501mb | 18.480ms  | ±0.33%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.501mb | 42.803ms  | ±0.72%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.143mb | 318.308ms | ±0.64%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.224ms   | ±1.37%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.657ms   | ±1.23%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.948ms   | ±0.62%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.011ms   | ±0.42%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.355ms   | ±0.86%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.981ms   | ±3.40%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.348ms   | ±1.11%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.622ms   | ±0.63%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 535.877μs | ±1.22%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.880ms   | ±1.18%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.935ms   | ±1.11%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.794ms   | ±1.12%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.743ms  | ±0.53%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.988ms   | ±1.07%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.503ms   | ±0.33%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.255ms   | ±0.66%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.406ms   | ±0.58%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.803ms   | ±0.89%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.297μs   | ±20.20% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.165ms   | ±0.99%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.553mb  | 26.965ms  | ±0.12%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.739mb | 245.233ms | ±0.48%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.144mb | 1.215s    | ±0.76%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.842mb | 191.155ms | ±0.50%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.023ms   | ±0.39%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.591ms  | ±1.68%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 44.652ms  | ±0.44%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.256ms   | ±0.88%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.246ms   | ±0.21%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.296ms   | ±3.14%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.503ms   | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.750ms   | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.765ms   | ±1.43%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.966ms   | ±0.20%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.516ms   | ±6.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.827ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.425ms  | ±5.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.588ms   | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.366ms   | ±1.64%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 638.398μs | ±3.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.187ms   | ±1.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.626ms   | ±1.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.317ms   | ±3.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 236.471ms | ±29.14% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.561ms   | ±0.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.782ms   | ±30.70% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.991ms   | ±5.00%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.922ms   | ±2.96%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.820ms  | ±1.23%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.943ms  | ±0.76%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.285ms  | ±1.54%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 30.959ms  | ±1.21%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 800.682μs | ±7.26%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 852.037μs | ±17.17% |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 923.821μs | ±3.63%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.520ms   | ±1.30%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.258ms   | ±0.47%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.229ms  | ±2.80%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 28.960ms  | ±1.22%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.019ms  | ±0.79%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 65.475ms  | ±0.69%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 104.585ms | ±0.98%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.174ms  | ±0.79%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.724ms  | ±0.74%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.320ms  | ±1.50%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 72.297ms  | ±0.80%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 159.467ms | ±0.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.056ms   | ±3.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.070ms  | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.463μs   | ±17.82% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 234.623ms | ±25.69% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 446.484μs | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.986ms   | ±0.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.399ms   | ±0.79%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.393ms  | ±3.35%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 86.268ms  | ±0.92%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.245ms  | ±2.23%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 24.943ms  | ±1.06%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 281.509ms | ±24.27% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.421ms  | ±0.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.139ms  | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.481ms  | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.536ms  | ±0.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.758ms  | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.155ms   | ±12.71% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.469ms  | ±0.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.504ms  | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.118ms  | ±0.72%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.347mb  | 10.277ms  | ±0.50%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.241mb  | 10.249ms  | ±0.21%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.394mb  | 12.004ms  | ±0.92%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.091mb  | 12.114ms  | ±0.18%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.574mb  | 11.141ms  | ±0.71%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.648mb  | 10.404ms  | ±0.73%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.892mb | 19.556ms  | ±1.22%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.791mb  | 3.082ms   | ±0.50%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.400μs  | ±0.38%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 243.698μs | ±0.66%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.307ms   | ±1.18%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.979ms  | ±0.64%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 46.599ms  | ±1.47%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.739ms   | ±3.61%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.879ms   | ±0.87%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.871ms   | ±0.30%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.417ms   | ±0.33%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.536ms   | ±0.78%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.933μs   | ±19.89% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.061μs   | ±20.20% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.649mb | 16.474ms  | ±0.80%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.649mb | 16.326ms  | ±0.12%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```