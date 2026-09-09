# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-09 11:44:18 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.692ms | 1.995ms | 2.113ms | 3.626ms | 5.382ms |
| FPDF | 614.182μs | 655.914μs | 716.170μs | 1.177ms | 1.773ms |
| TCPDF | 7.753ms | 8.469ms | 9.203ms | 14.818ms | 21.897ms |
| mPDF | 20.127ms | 22.489ms | 25.112ms | 46.759ms | 73.851ms |
| Dompdf | 8.751ms | 11.762ms | 15.508ms | 51.017ms | 117.677ms |

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
| phpdftk | 2.588ms | 2.751ms | 2.967ms | 4.440ms | 6.345ms |
| FPDF | 812.777μs | 888.135μs | 949.500μs | 1.467ms | 2.098ms |
| TCPDF | 11.269ms | 11.946ms | 12.840ms | 19.408ms | 27.401ms |

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
| Pdf (Level 3) | 2.608ms | 3.391ms | 9.389ms |
| PdfDoc (Level 2) | 2.072ms | 2.440ms | 5.746ms |
| PdfWriter (Level 1) | 1.764ms | 2.118ms | 5.280ms |

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
| Pdf (Level 3) | 3.351ms | 9.227ms | 35.754ms |
| PdfDoc (Level 2) | 2.891ms | 7.737ms | — |

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
| Pdf (Level 3) | 3.131ms | 8.930ms | 34.482ms |
| PdfDoc (Level 2) | 2.516ms | 5.606ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.696ms | 1.274ms | 4.643ms |
| smalot/pdfparser | 1.545ms | 1.826ms | 4.302ms |
| setasign/fpdi | 1.464ms | 2.086ms | 22.237ms |

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
| phpdftk | 1.566ms | 1.036ms |
| smalot/pdfparser | FAIL | 1.466ms |
| setasign/fpdi | 2.227ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 2.588ms   | ±0.93%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 2.751ms   | ±1.00%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 2.967ms   | ±1.24%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 4.440ms   | ±1.55%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 6.345ms   | ±1.56%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 11.269ms  | ±0.76%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 11.946ms  | ±0.27%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 12.840ms  | ±0.42%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 19.408ms  | ±0.33%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 27.401ms  | ±0.24%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 812.777μs | ±4.51%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 888.135μs | ±1.23%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 949.500μs | ±0.00%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.467ms   | ±0.72%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.098ms   | ±0.33%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 1.764ms   | ±0.92%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.118ms   | ±2.38%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 5.280ms   | ±4.78%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.072ms   | ±1.07%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.440ms   | ±1.47%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 5.746ms   | ±0.06%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 2.608ms   | ±28.52%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 3.391ms   | ±43.87%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 9.389ms   | ±0.43%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.535mb | 60.934ms  | ±3.13%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.427mb | 263.130ms | ±3.50%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.490mb | 1.033s    | ±1.12%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.602mb | 185.098ms | ±3.55%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.564mb | 151.398ms | ±0.77%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.345mb | 118.536ms | ±3.15%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.416mb | 158.037ms | ±3.43%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.952mb | 127.728ms | ±0.50%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.507mb | 248.027ms | ±3.54%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.047mb | 37.560ms  | ±0.65%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.964mb | 35.148ms  | ±2.62%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.889mb | 32.330ms  | ±1.76%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.232mb | 102.421ms | ±0.34%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.953mb | 34.382ms  | ±0.26%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.170mb | 43.325ms  | ±0.19%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.599mb | 69.830ms  | ±4.63%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.856mb | 29.597ms  | ±3.03%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.859mb | 33.954ms  | ±0.22%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.887mb | 35.507ms  | ±2.46%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.855mb | 34.506ms  | ±1.99%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.877mb | 33.325ms  | ±0.13%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.730mb | 53.121ms  | ±0.32%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.837mb | 33.380ms  | ±1.62%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.457mb | 28.921ms  | ±2.25%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.758mb | 31.470ms  | ±0.45%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.845mb | 35.565ms  | ±1.76%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.838mb | 36.036ms  | ±1.91%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.005mb | 33.563ms  | ±1.03%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.209mb | 164.485ms | ±3.27%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.428mb | 127.876ms | ±2.12%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.234mb | 41.328ms  | ±2.84%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.842mb | 89.220ms  | ±3.19%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.910mb | 998.855ms | ±0.82%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.360mb | 20.416ms  | ±53.89%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.321mb | 41.069ms  | ±0.99%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.132mb | 349.768ms | ±1.35%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.579mb | 49.337ms  | ±8.93%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.298mb | 63.297ms  | ±1.40%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.067mb | 507.651ms | ±0.27%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.343mb | 13.516ms  | ±0.07%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.343mb | 31.631ms  | ±0.64%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.985mb | 211.731ms | ±0.41%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 939.146μs | ±1.49%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.274ms   | ±1.15%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 4.643ms   | ±0.71%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.566ms   | ±0.80%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.036ms   | ±1.02%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.545ms   | ±0.93%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.826ms   | ±0.78%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.302ms   | ±0.90%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 431.271μs | ±0.56%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.466ms   | ±0.38%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.464ms   | ±0.94%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.086ms   | ±0.37%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 22.237ms  | ±0.31%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.227ms   | ±1.29%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.163ms   | ±0.54%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 5.487ms   | ±0.30%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 4.121ms   | ±0.39%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.937ms   | ±0.42%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.109μs   | ±23.57%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.696ms   | ±1.47%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.535mb  | 22.385ms  | ±1.98%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.704mb | 173.118ms | ±3.45%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.032mb | 851.263ms | ±6.86%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.813mb | 132.644ms | ±1.18%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.131ms   | ±39.55%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 8.930ms   | ±2.11%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 34.482ms  | ±0.58%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.516ms   | ±0.46%   |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 5.606ms   | ±0.54%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 1.737ms   | ±0.74%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 1.995ms   | ±168.75% |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.113ms   | ±33.86%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 3.626ms   | ±18.93%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 5.382ms   | ±3.30%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 2.748ms   | ±51.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 2.886ms   | ±0.74%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 10.115ms  | ±25.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.754ms   | ±43.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.880ms   | ±20.79%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 497.403μs | ±7.09%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.417ms   | ±0.59%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 2.749ms   | ±31.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.425ms   | ±9.65%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 152.221ms | ±41.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 2.701ms   | ±0.39%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 4.573ms   | ±29.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 4.582ms   | ±0.43%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.753ms   | ±0.17%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 8.469ms   | ±0.39%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.203ms   | ±0.53%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 14.818ms  | ±0.47%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 21.897ms  | ±0.32%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 614.182μs | ±32.72%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 655.914μs | ±1.92%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 716.170μs | ±0.71%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.177ms   | ±3.11%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.773ms   | ±118.38% |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 20.127ms  | ±19.58%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 22.489ms  | ±0.82%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 25.112ms  | ±0.25%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 46.759ms  | ±0.18%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 73.851ms  | ±0.33%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 8.751ms   | ±108.89% |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 11.762ms  | ±0.49%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 15.508ms  | ±4.83%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 51.017ms  | ±0.22%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 117.677ms | ±1.52%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 3.836ms   | ±4.24%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 41.565ms  | ±1.19%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.463μs   | ±17.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 131.073ms | ±20.09%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 375.493μs | ±1.25%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.226ms   | ±0.32%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 2.586ms   | ±0.46%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 9.253ms   | ±5.09%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 62.644ms  | ±0.50%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.464ms  | ±0.38%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.231ms  | ±1.30%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 191.080ms | ±17.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 11.047ms  | ±6.55%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 10.633ms  | ±15.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 10.815ms  | ±0.80%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 11.096ms  | ±22.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 11.003ms  | ±0.37%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.399ms   | ±0.60%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 10.867ms  | ±0.97%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 10.859ms  | ±0.69%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 10.692ms  | ±1.09%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 7.460ms   | ±0.38%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 7.600ms   | ±0.21%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 8.907ms   | ±1.12%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 8.903ms   | ±1.33%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 8.513ms   | ±0.75%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 7.717ms   | ±0.39%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 13.949ms  | ±3.01%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 2.329ms   | ±0.42%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 33.241μs  | ±0.99%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 182.028μs | ±0.39%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 3.351ms   | ±1.00%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 9.227ms   | ±0.47%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 35.754ms  | ±0.58%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 2.891ms   | ±0.85%   |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 7.737ms   | ±19.21%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 7.128ms   | ±35.38%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 6.410ms   | ±2.83%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 6.474ms   | ±0.63%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.041μs   | ±12.90%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.786μs   | ±28.98%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.482mb | 12.541ms  | ±0.68%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.482mb | 12.520ms  | ±0.41%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```