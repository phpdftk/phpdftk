# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-19 01:58:39 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.247ms | 2.493ms | 2.754ms | 4.742ms | 6.977ms |
| FPDF | 778.609μs | 844.644μs | 932.076μs | 1.539ms | 2.272ms |
| TCPDF | 10.003ms | 10.892ms | 12.069ms | 20.434ms | 31.083ms |
| mPDF | 25.111ms | 28.918ms | 33.107ms | 64.478ms | 105.974ms |
| Dompdf | 11.256ms | 15.884ms | 21.553ms | 73.241ms | 161.526ms |

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
| phpdftk | 3.304ms | 3.537ms | 3.852ms | 5.826ms | 8.369ms |
| FPDF | 1.037ms | 1.121ms | 1.211ms | 1.936ms | 2.736ms |
| TCPDF | 14.692ms | 15.682ms | 16.793ms | 26.470ms | 38.635ms |

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
| Pdf (Level 3) | 3.362ms | 4.459ms | 12.718ms |
| PdfDoc (Level 2) | 2.694ms | 3.174ms | 7.528ms |
| PdfWriter (Level 1) | 2.288ms | 2.770ms | 6.884ms |

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
| Pdf (Level 3) | 4.362ms | 12.144ms | 46.338ms |
| PdfDoc (Level 2) | 3.785ms | 9.931ms | — |

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
| Pdf (Level 3) | 4.019ms | 11.566ms | 44.939ms |
| PdfDoc (Level 2) | 3.287ms | 7.282ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.216ms | 1.685ms | 5.959ms |
| smalot/pdfparser | 1.986ms | 2.340ms | 5.715ms |
| setasign/fpdi | 1.919ms | 2.844ms | 29.497ms |

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
| phpdftk | 2.050ms | 1.362ms |
| smalot/pdfparser | FAIL | 1.898ms |
| setasign/fpdi | 3.019ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.304ms   | ±1.36%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.537ms   | ±0.72%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.852ms   | ±0.77%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.826ms   | ±0.43%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.369ms   | ±0.46%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.692ms  | ±1.18%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.682ms  | ±1.78%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.793ms  | ±1.46%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.470ms  | ±0.40%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 38.635ms  | ±0.75%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.037ms   | ±2.37%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.121ms   | ±1.00%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.211ms   | ±0.47%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.936ms   | ±2.84%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.736ms   | ±1.68%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.288ms   | ±0.94%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.770ms   | ±1.08%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.884ms   | ±0.48%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.694ms   | ±0.59%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.174ms   | ±0.86%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.528ms   | ±0.82%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.362ms   | ±0.62%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.459ms   | ±0.41%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.718ms  | ±0.69%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.005mb | 87.594ms  | ±0.44%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 22.135mb | 380.337ms | ±0.60%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 46.863mb | 1.494s    | ±0.16%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.190mb | 264.065ms | ±0.14%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.990mb | 199.696ms | ±0.71%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.824mb | 164.501ms | ±2.88%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.948mb | 222.104ms | ±0.28%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.443mb | 184.383ms | ±0.04%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 21.145mb | 351.527ms | ±0.52%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.457mb | 53.350ms  | ±0.24%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.370mb | 46.218ms  | ±0.20%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.302mb | 43.570ms  | ±1.09%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.696mb | 144.653ms | ±0.38%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.358mb | 48.503ms  | ±0.54%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.584mb | 61.575ms  | ±1.43%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.068mb | 91.407ms  | ±0.62%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.256mb | 39.092ms  | ±0.47%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.259mb | 46.793ms  | ±0.29%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.286mb | 48.650ms  | ±0.22%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.255mb | 47.578ms  | ±0.67%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.277mb | 46.109ms  | ±0.38%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.185mb | 72.444ms  | ±0.35%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.229mb | 44.679ms  | ±0.18%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.908mb | 39.928ms  | ±0.68%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.224mb | 43.337ms  | ±0.11%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.245mb | 48.663ms  | ±0.45%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.238mb | 48.117ms  | ±0.26%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.390mb | 43.384ms  | ±0.69%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.903mb | 232.165ms | ±0.36%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.843mb | 172.679ms | ±0.69%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.738mb | 57.758ms  | ±1.05%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.291mb | 117.954ms | ±0.38%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 44.975mb | 1.440s    | ±0.60%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.726mb | 25.819ms  | ±1.89%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.687mb | 58.041ms  | ±0.47%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.498mb | 522.735ms | ±1.50%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.010mb | 64.341ms  | ±8.93%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.664mb | 85.550ms  | ±1.54%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.433mb | 730.664ms | ±0.58%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.709mb | 18.839ms  | ±0.92%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.709mb | 42.962ms  | ±0.10%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.351mb | 318.494ms | ±0.43%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.222ms   | ±1.37%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.685ms   | ±0.58%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.959ms   | ±0.42%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.050ms   | ±1.58%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.362ms   | ±1.51%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.986ms   | ±1.14%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.340ms   | ±0.73%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.715ms   | ±0.39%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 542.157μs | ±3.56%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.898ms   | ±0.64%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.919ms   | ±1.04%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.844ms   | ±0.73%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.497ms  | ±1.69%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.019ms   | ±1.00%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.511ms   | ±0.63%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.342ms   | ±0.44%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.442ms   | ±0.79%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.854ms   | ±0.19%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.397μs   | ±18.73% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.216ms   | ±0.76%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.635mb  | 27.248ms  | ±0.17%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.033mb | 247.211ms | ±2.67%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.378mb | 1.242s    | ±0.65%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.059mb | 192.392ms | ±0.34%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.019ms   | ±0.49%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.566ms  | ±1.68%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 44.939ms  | ±1.74%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.287ms   | ±0.84%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.282ms   | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.286ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.493ms   | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.754ms   | ±0.89%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.742ms   | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.977ms   | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.564ms   | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.823ms   | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.268ms  | ±1.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.598ms   | ±1.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.390ms   | ±1.02%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 633.356μs | ±2.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.174ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.657ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.210ms   | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 127.260ms | ±14.04% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.584ms   | ±1.16%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.777ms   | ±2.21%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.006ms   | ±0.93%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.003ms  | ±0.48%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.892ms  | ±0.52%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.069ms  | ±0.33%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.434ms  | ±0.79%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.083ms  | ±0.36%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 778.609μs | ±2.15%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 844.644μs | ±1.50%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 932.076μs | ±0.79%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.539ms   | ±1.35%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.272ms   | ±0.76%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.111ms  | ±1.94%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 28.918ms  | ±0.33%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.107ms  | ±0.74%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 64.478ms  | ±0.67%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 105.974ms | ±0.48%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.256ms  | ±0.46%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.884ms  | ±0.56%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.553ms  | ±0.65%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 73.241ms  | ±0.77%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 161.526ms | ±1.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.059ms   | ±1.17%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.226ms  | ±1.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 277.289ms | ±26.51% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 457.932μs | ±1.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.979ms   | ±0.21%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.408ms   | ±0.25%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.357ms  | ±3.64%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 85.869ms  | ±1.77%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.404ms  | ±4.58%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.121ms  | ±1.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 185.254ms | ±36.15% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.350ms  | ±1.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.177ms  | ±2.17%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.340ms  | ±0.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.471ms  | ±0.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.876ms  | ±1.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.161ms   | ±0.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.436ms  | ±0.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.451ms  | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.247ms  | ±0.49%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.351mb  | 10.458ms  | ±0.32%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.245mb  | 10.421ms  | ±0.27%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.397mb  | 12.022ms  | ±0.23%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.095mb  | 12.243ms  | ±0.67%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.577mb  | 11.264ms  | ±0.76%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.651mb  | 10.416ms  | ±0.82%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.896mb | 19.671ms  | ±0.37%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.791mb  | 3.075ms   | ±1.10%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.207μs  | ±0.49%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 244.699μs | ±0.98%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.362ms   | ±0.40%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.144ms  | ±1.15%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 46.338ms  | ±3.95%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.785ms   | ±0.18%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.931ms   | ±0.39%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.946ms   | ±0.55%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.460ms   | ±0.39%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.565ms   | ±0.13%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.966μs   | ±19.64% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.697μs   | ±35.36% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.868mb | 17.039ms  | ±0.37%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.868mb | 17.070ms  | ±0.39%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```