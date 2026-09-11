# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-11 13:48:27 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.830ms | 2.171ms | 2.399ms | 4.289ms | 6.317ms |
| FPDF | 718.899μs | 701.939μs | 874.228μs | 1.368ms | 1.950ms |
| TCPDF | 8.507ms | 9.907ms | 10.861ms | 16.718ms | 25.730ms |
| mPDF | 21.910ms | 25.534ms | 27.663ms | 49.786ms | 76.239ms |
| Dompdf | 9.769ms | 12.566ms | 16.141ms | 56.690ms | 118.583ms |

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
| phpdftk | 2.637ms | 2.735ms | 3.011ms | 5.100ms | 7.263ms |
| FPDF | 936.863μs | 999.630μs | 1.092ms | 1.773ms | 2.687ms |
| TCPDF | 13.949ms | 14.790ms | 17.029ms | 22.744ms | 34.905ms |

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
| Pdf (Level 3) | 2.957ms | 3.596ms | 10.252ms |
| PdfDoc (Level 2) | 2.289ms | 2.640ms | 6.608ms |
| PdfWriter (Level 1) | 1.747ms | 6.280ms | 5.673ms |

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
| Pdf (Level 3) | 3.857ms | 10.635ms | 39.232ms |
| PdfDoc (Level 2) | 3.014ms | 8.457ms | — |

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
| Pdf (Level 3) | 3.485ms | 9.401ms | 31.958ms |
| PdfDoc (Level 2) | 2.724ms | 5.405ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.182ms | 1.173ms | 4.030ms |
| smalot/pdfparser | 1.653ms | 1.828ms | 4.534ms |
| setasign/fpdi | 1.636ms | 2.240ms | 20.849ms |

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
| phpdftk | 1.557ms | 1.062ms |
| smalot/pdfparser | FAIL | 1.697ms |
| setasign/fpdi | 2.152ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 2.637ms   | ±2.40%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 2.735ms   | ±4.89%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.011ms   | ±3.99%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.100ms   | ±4.87%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 7.263ms   | ±0.36%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 13.949ms  | ±2.80%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 14.790ms  | ±3.17%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.029ms  | ±62.15%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 22.744ms  | ±2.49%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 34.905ms  | ±48.59%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 936.863μs | ±1.49%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 999.630μs | ±6.07%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.092ms   | ±3.94%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.773ms   | ±1.68%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.687ms   | ±0.12%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 1.747ms   | ±3.66%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 6.280ms   | ±144.37% |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 5.673ms   | ±164.04% |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.289ms   | ±6.13%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.640ms   | ±166.23% |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 6.608ms   | ±36.78%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 2.957ms   | ±155.81% |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 3.596ms   | ±84.90%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 10.252ms  | ±55.89%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.556mb | 62.672ms  | ±4.47%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.472mb | 253.468ms | ±1.00%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.505mb | 1.022s    | ±1.16%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.623mb | 183.229ms | ±2.50%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.585mb | 139.213ms | ±2.40%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.365mb | 109.620ms | ±0.90%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.436mb | 150.185ms | ±0.78%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.973mb | 126.081ms | ±3.07%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.528mb | 238.981ms | ±1.15%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.068mb | 39.523ms  | ±3.81%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.984mb | 35.694ms  | ±0.69%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.910mb | 34.561ms  | ±0.15%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.254mb | 107.441ms | ±2.56%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.973mb | 38.289ms  | ±1.58%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.191mb | 45.894ms  | ±4.15%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.619mb | 62.515ms  | ±1.55%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.877mb | 26.916ms  | ±2.16%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.880mb | 32.565ms  | ±1.47%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.907mb | 38.033ms  | ±4.77%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.876mb | 37.391ms  | ±0.92%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.898mb | 35.726ms  | ±4.24%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.751mb | 54.208ms  | ±2.59%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.858mb | 32.744ms  | ±4.23%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.477mb | 30.407ms  | ±2.70%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.779mb | 31.616ms  | ±1.25%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.865mb | 33.827ms  | ±0.44%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.859mb | 32.962ms  | ±2.23%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.025mb | 30.829ms  | ±6.21%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.230mb | 170.041ms | ±3.05%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.448mb | 124.719ms | ±2.86%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.255mb | 40.309ms  | ±2.52%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.862mb | 84.914ms  | ±3.36%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.912mb | 974.659ms | ±0.91%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.381mb | 75.610ms  | ±47.84%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.341mb | 45.404ms  | ±9.19%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.152mb | 377.810ms | ±1.99%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.599mb | 56.983ms  | ±28.87%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.318mb | 71.250ms  | ±5.31%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.087mb | 566.215ms | ±1.57%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.363mb | 16.385ms  | ±3.94%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.363mb | 35.687ms  | ±3.13%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.006mb | 241.767ms | ±1.68%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 888.017μs | ±4.62%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.173ms   | ±3.48%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 4.030ms   | ±4.53%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.557ms   | ±6.07%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.062ms   | ±4.31%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.653ms   | ±3.31%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.828ms   | ±5.27%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.534ms   | ±3.12%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 431.619μs | ±4.17%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.697ms   | ±2.17%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.636ms   | ±7.12%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.240ms   | ±3.34%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 20.849ms  | ±4.77%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.152ms   | ±5.58%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.249ms   | ±4.08%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 5.373ms   | ±5.24%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 4.000ms   | ±2.73%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.848ms   | ±1.02%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 1.786μs   | ±28.98%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.182ms   | ±6.89%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.542mb  | 18.532ms  | ±0.83%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.711mb | 168.070ms | ±2.20%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.039mb | 838.718ms | ±1.37%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.820mb | 143.449ms | ±2.81%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.485ms   | ±0.90%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 9.401ms   | ±96.05%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 31.958ms  | ±2.37%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.724ms   | ±145.41% |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 5.405ms   | ±1.80%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 1.743ms   | ±5.75%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.171ms   | ±2.01%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.399ms   | ±2.08%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.289ms   | ±2.67%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.317ms   | ±1.69%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.021ms   | ±1.99%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.018ms   | ±6.09%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 9.717ms   | ±3.98%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.736ms   | ±2.47%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.858ms   | ±66.76%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 505.087μs | ±31.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.621ms   | ±7.36%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 2.797ms   | ±5.77%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.692ms   | ±4.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 125.948ms | ±38.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.218ms   | ±1.84%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.739ms   | ±81.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 4.600ms   | ±7.00%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 8.507ms   | ±2.25%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 9.907ms   | ±5.16%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 10.861ms  | ±3.64%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 16.718ms  | ±4.82%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 25.730ms  | ±4.39%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 718.899μs | ±4.62%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 701.939μs | ±9.21%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 874.228μs | ±76.61%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.368ms   | ±4.88%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.950ms   | ±5.20%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 21.910ms  | ±2.57%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 25.534ms  | ±3.07%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 27.663ms  | ±2.57%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 49.786ms  | ±4.52%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 76.239ms  | ±1.83%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 9.769ms   | ±3.75%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 12.566ms  | ±1.88%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 16.141ms  | ±2.55%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 56.690ms  | ±4.76%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 118.583ms | ±1.73%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 3.869ms   | ±3.87%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 36.533ms  | ±2.40%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.957μs   | ±33.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 0.989μs   | ±18.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 0.989μs   | ±18.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 173.590ms | ±19.49%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 329.038μs | ±2.27%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.252ms   | ±0.39%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 2.824ms   | ±4.41%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.458ms  | ±27.05%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 61.197ms  | ±3.67%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 10.046ms  | ±0.96%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 18.306ms  | ±2.43%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 176.616ms | ±31.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 9.716ms   | ±1.78%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 9.516ms   | ±2.48%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 10.672ms  | ±5.37%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 9.690ms   | ±2.54%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 10.666ms  | ±123.00% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.719ms   | ±5.09%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 9.997ms   | ±4.25%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 9.844ms   | ±8.03%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 9.830ms   | ±2.09%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 9.762ms   | ±9.27%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 9.338ms   | ±2.93%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 9.826ms   | ±3.63%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 10.847ms  | ±3.22%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 8.857ms   | ±4.72%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 9.117ms   | ±2.70%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 15.244ms  | ±2.07%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 2.365ms   | ±5.15%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 28.972μs  | ±4.87%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 205.948μs | ±6.97%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 3.857ms   | ±3.54%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 10.635ms  | ±3.45%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 39.232ms  | ±3.48%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.014ms   | ±11.30%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 8.457ms   | ±5.96%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 6.559ms   | ±8.65%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 6.314ms   | ±8.07%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 6.434ms   | ±6.18%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 0.933μs   | ±38.53%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.061μs   | ±35.36%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.502mb | 12.967ms  | ±2.01%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.502mb | 12.779ms  | ±1.31%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```