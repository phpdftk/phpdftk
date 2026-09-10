# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-10 15:03:05 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.875ms | 2.472ms | 2.727ms | 4.712ms | 6.916ms |
| FPDF | 783.622μs | 838.885μs | 941.664μs | 1.516ms | 2.237ms |
| TCPDF | 10.248ms | 11.004ms | 11.974ms | 19.287ms | 28.161ms |
| mPDF | 25.606ms | 29.101ms | 32.678ms | 60.811ms | 95.782ms |
| Dompdf | 11.082ms | 15.169ms | 20.148ms | 66.611ms | 149.634ms |

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
| phpdftk | 3.418ms | 3.663ms | 3.973ms | 5.883ms | 8.449ms |
| FPDF | 1.113ms | 1.142ms | 1.268ms | 1.915ms | 2.757ms |
| TCPDF | 16.003ms | 16.542ms | 17.154ms | 25.535ms | 35.690ms |

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
| Pdf (Level 3) | 3.346ms | 4.362ms | 12.176ms |
| PdfDoc (Level 2) | 2.714ms | 3.145ms | 7.514ms |
| PdfWriter (Level 1) | 2.278ms | 2.747ms | 6.825ms |

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
| Pdf (Level 3) | 4.285ms | 11.818ms | 45.213ms |
| PdfDoc (Level 2) | 3.716ms | 9.825ms | — |

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
| Pdf (Level 3) | 4.037ms | 11.514ms | 44.423ms |
| PdfDoc (Level 2) | 3.270ms | 7.263ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.088ms | 1.673ms | 6.087ms |
| smalot/pdfparser | 2.000ms | 2.364ms | 5.463ms |
| setasign/fpdi | 1.883ms | 2.706ms | 28.727ms |

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
| phpdftk | 2.025ms | 1.343ms |
| smalot/pdfparser | FAIL | 1.909ms |
| setasign/fpdi | 2.872ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.418ms   | ±0.39%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.663ms   | ±0.37%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.973ms   | ±18.92% |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.883ms   | ±0.54%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.449ms   | ±1.35%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 16.003ms  | ±1.15%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.542ms  | ±3.20%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.154ms  | ±2.72%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 25.535ms  | ±0.63%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 35.690ms  | ±3.57%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.113ms   | ±1.55%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.142ms   | ±1.80%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.268ms   | ±1.39%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.915ms   | ±0.22%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.757ms   | ±0.39%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.278ms   | ±2.02%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.747ms   | ±1.84%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.825ms   | ±0.52%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.714ms   | ±0.88%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.145ms   | ±0.75%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.514ms   | ±0.66%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.346ms   | ±0.64%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.362ms   | ±1.09%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.176ms  | ±0.22%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.548mb | 80.278ms  | ±4.36%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.464mb | 347.264ms | ±3.69%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.497mb | 1.358s    | ±4.47%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.614mb | 243.490ms | ±0.76%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.576mb | 194.265ms | ±0.27%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.357mb | 152.839ms | ±0.32%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.428mb | 203.752ms | ±0.16%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.965mb | 169.009ms | ±4.82%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.519mb | 323.180ms | ±0.28%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.060mb | 50.175ms  | ±0.24%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.976mb | 43.786ms  | ±1.22%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.902mb | 41.404ms  | ±0.99%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.245mb | 137.239ms | ±2.62%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.965mb | 45.651ms  | ±0.55%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.183mb | 56.826ms  | ±0.53%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.611mb | 84.087ms  | ±4.01%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.869mb | 37.591ms  | ±2.63%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.871mb | 44.632ms  | ±0.27%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.899mb | 46.942ms  | ±1.73%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.868mb | 45.826ms  | ±1.69%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.890mb | 44.398ms  | ±1.34%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.743mb | 70.492ms  | ±0.28%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.850mb | 45.393ms  | ±0.83%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.469mb | 39.971ms  | ±1.00%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.771mb | 42.163ms  | ±1.64%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.857mb | 46.730ms  | ±2.35%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.850mb | 47.255ms  | ±1.08%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.017mb | 43.064ms  | ±1.11%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.222mb | 216.574ms | ±2.94%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.440mb | 170.470ms | ±1.94%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.247mb | 54.603ms  | ±0.61%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.854mb | 110.995ms | ±2.52%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.903mb | 1.323s    | ±2.64%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.372mb | 23.952ms  | ±2.98%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.333mb | 52.900ms  | ±0.48%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.144mb | 496.482ms | ±0.50%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.590mb | 65.740ms  | ±8.87%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.310mb | 83.458ms  | ±1.05%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.079mb | 665.079ms | ±0.75%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.355mb | 17.613ms  | ±0.40%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.355mb | 41.373ms  | ±0.60%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.997mb | 280.184ms | ±0.19%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.217ms   | ±1.06%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.673ms   | ±1.01%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.087ms   | ±0.98%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.025ms   | ±0.78%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.343ms   | ±0.57%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.000ms   | ±0.75%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.364ms   | ±0.44%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.463ms   | ±0.53%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 572.037μs | ±1.17%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.909ms   | ±10.63% |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.883ms   | ±1.38%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.706ms   | ±0.41%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.727ms  | ±0.81%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.872ms   | ±0.53%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.528ms   | ±1.76%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.080ms   | ±0.50%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.378ms   | ±1.52%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.812ms   | ±0.58%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.844μs   | ±24.34% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.088ms   | ±0.94%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.541mb  | 25.386ms  | ±7.85%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.710mb | 228.307ms | ±0.53%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.038mb | 1.121s    | ±7.63%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.819mb | 173.242ms | ±0.32%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.037ms   | ±0.58%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.514ms  | ±0.54%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 44.423ms  | ±0.74%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.270ms   | ±0.26%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.263ms   | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.264ms   | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.472ms   | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.727ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.712ms   | ±1.10%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.916ms   | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.518ms   | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.734ms   | ±0.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 13.073ms  | ±20.70% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.633ms   | ±1.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.390ms   | ±1.01%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 612.452μs | ±3.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.134ms   | ±1.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.595ms   | ±0.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.219ms   | ±0.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 205.921ms | ±29.20% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.540ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.855ms   | ±57.96% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.038ms   | ±0.74%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.248ms  | ±1.35%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.004ms  | ±1.42%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.974ms  | ±0.63%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.287ms  | ±0.56%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.161ms  | ±0.64%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 783.622μs | ±1.20%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 838.885μs | ±1.65%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 941.664μs | ±1.05%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.516ms   | ±0.43%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.237ms   | ±0.56%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.606ms  | ±2.01%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.101ms  | ±0.36%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.678ms  | ±0.36%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 60.811ms  | ±0.34%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 95.782ms  | ±0.78%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.082ms  | ±0.48%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.169ms  | ±0.40%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 20.148ms  | ±0.71%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 66.611ms  | ±2.81%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 149.634ms | ±0.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.950ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 52.820ms  | ±1.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 221.415ms | ±35.14% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 489.222μs | ±1.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.931ms   | ±2.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.353ms   | ±0.25%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 11.434ms  | ±4.59%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 81.130ms  | ±0.23%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.908ms  | ±0.62%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.157ms  | ±1.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 352.180ms | ±25.55% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.833ms  | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.746ms  | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 14.014ms  | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 14.071ms  | ±1.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.326ms  | ±0.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.114ms   | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 14.029ms  | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 14.084ms  | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.875ms  | ±0.78%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 9.884ms   | ±0.42%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 9.824ms   | ±0.38%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 11.853ms  | ±1.29%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 11.528ms  | ±0.61%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 11.019ms  | ±1.27%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 10.090ms  | ±0.25%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 17.917ms  | ±0.36%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 3.034ms   | ±0.60%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.553μs  | ±0.69%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 234.973μs | ±0.57%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.285ms   | ±0.44%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.818ms  | ±0.66%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 45.213ms  | ±0.56%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.716ms   | ±0.45%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.825ms   | ±0.67%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.719ms   | ±0.25%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.365ms   | ±0.86%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.363ms   | ±0.45%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.317μs   | ±19.69% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.121μs   | ±35.36% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.494mb | 16.276ms  | ±0.27%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.494mb | 16.118ms  | ±0.62%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```