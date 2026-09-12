# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-12 02:06:47 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.371ms | 1.649ms | 2.123ms | 3.305ms | 4.749ms |
| FPDF | 1.394ms | 1.087ms | 1.409ms | 1.042ms | 1.611ms |
| TCPDF | 8.579ms | 9.507ms | 9.576ms | 13.962ms | 20.440ms |
| mPDF | 17.899ms | 20.085ms | 22.871ms | 40.250ms | 62.648ms |
| Dompdf | 8.644ms | 12.084ms | 13.458ms | 42.974ms | 96.430ms |

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
| phpdftk | 2.254ms | 5.181ms | 5.266ms | 6.553ms | 5.709ms |
| FPDF | 1.129ms | 822.445μs | 951.780μs | 2.135ms | 1.988ms |
| TCPDF | 30.530ms | 12.936ms | 13.305ms | 19.594ms | 26.918ms |

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
| Pdf (Level 3) | 4.879ms | 8.977ms | 7.966ms |
| PdfDoc (Level 2) | 2.118ms | 34.380ms | 43.016ms |
| PdfWriter (Level 1) | 1.491ms | 1.963ms | 4.620ms |

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
| Pdf (Level 3) | 4.671ms | 8.156ms | 29.738ms |
| PdfDoc (Level 2) | 2.817ms | 7.394ms | — |

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
| Pdf (Level 3) | 3.785ms | 9.315ms | 26.598ms |
| PdfDoc (Level 2) | 2.196ms | 5.080ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 3.441ms | 974.984μs | 3.360ms |
| smalot/pdfparser | 1.295ms | 1.565ms | 3.822ms |
| setasign/fpdi | 1.239ms | 1.743ms | 17.001ms |

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
| phpdftk | 1.202ms | 821.169μs |
| smalot/pdfparser | FAIL | 1.260ms |
| setasign/fpdi | 1.843ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 2.254ms   | ±3.39%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 5.181ms   | ±123.90% |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 5.266ms   | ±121.39% |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 6.553ms   | ±112.83% |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 5.709ms   | ±1.71%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 30.530ms  | ±71.99%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 12.936ms  | ±11.01%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 13.305ms  | ±4.18%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 19.594ms  | ±1.64%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 26.918ms  | ±1.95%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.129ms   | ±104.97% |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 822.445μs | ±2.81%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 951.780μs | ±43.34%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 2.135ms   | ±104.28% |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 1.988ms   | ±0.51%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 1.491ms   | ±3.40%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 1.963ms   | ±63.57%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 4.620ms   | ±0.23%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.118ms   | ±64.27%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 34.380ms  | ±60.19%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 43.016ms  | ±66.64%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 4.879ms   | ±112.93% |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 8.977ms   | ±105.14% |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 7.966ms   | ±17.59%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.556mb | 48.460ms  | ±0.45%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.472mb | 205.787ms | ±0.58%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.505mb | 814.344ms | ±0.35%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.623mb | 145.159ms | ±2.18%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.585mb | 114.180ms | ±0.46%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.365mb | 88.963ms  | ±0.45%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.436mb | 120.977ms | ±1.29%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.973mb | 103.016ms | ±5.69%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.528mb | 191.500ms | ±0.67%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.068mb | 30.081ms  | ±1.19%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.984mb | 26.153ms  | ±7.29%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.910mb | 25.012ms  | ±0.49%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.254mb | 80.020ms  | ±0.81%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.973mb | 27.845ms  | ±1.34%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.191mb | 34.503ms  | ±0.28%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.619mb | 50.357ms  | ±0.08%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.877mb | 22.372ms  | ±1.16%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.880mb | 27.149ms  | ±0.79%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.907mb | 27.978ms  | ±0.13%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.876mb | 28.050ms  | ±3.55%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.898mb | 27.106ms  | ±0.90%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.751mb | 43.857ms  | ±0.76%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.858mb | 26.261ms  | ±0.68%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.477mb | 23.595ms  | ±0.57%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.779mb | 25.241ms  | ±0.61%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.865mb | 28.303ms  | ±0.14%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.859mb | 30.055ms  | ±4.22%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.025mb | 25.161ms  | ±1.24%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.230mb | 126.029ms | ±0.44%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.448mb | 97.067ms  | ±0.55%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.255mb | 32.234ms  | ±0.39%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.862mb | 64.844ms  | ±0.65%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.912mb | 777.080ms | ±0.44%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.381mb | 17.338ms  | ±39.10%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.341mb | 40.049ms  | ±53.35%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.152mb | 311.931ms | ±0.85%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.599mb | 46.279ms  | ±7.42%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.318mb | 52.990ms  | ±1.09%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.087mb | 450.367ms | ±3.50%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.363mb | 19.677ms  | ±21.38%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.363mb | 27.264ms  | ±0.47%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.006mb | 190.748ms | ±0.32%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 760.980μs | ±2.56%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 974.984μs | ±0.89%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 3.360ms   | ±0.78%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.202ms   | ±2.47%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 821.169μs | ±1.95%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.295ms   | ±1.92%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.565ms   | ±5.19%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 3.822ms   | ±6.10%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 374.425μs | ±4.08%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.260ms   | ±3.13%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.239ms   | ±1.14%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 1.743ms   | ±1.03%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 17.001ms  | ±0.92%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 1.843ms   | ±1.40%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.075ms   | ±8.10%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 4.659ms   | ±4.45%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 3.393ms   | ±1.77%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.419ms   | ±0.94%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 1.597μs   | ±35.59%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 3.441ms   | ±0.78%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.542mb  | 14.916ms  | ±0.93%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.711mb | 130.540ms | ±2.72%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.039mb | 661.859ms | ±0.97%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.820mb | 108.006ms | ±3.05%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.785ms   | ±75.79%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 9.315ms   | ±84.61%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 26.598ms  | ±3.20%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.196ms   | ±57.25%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 5.080ms   | ±7.38%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 1.551ms   | ±2.20%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 1.649ms   | ±1.48%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.123ms   | ±147.36% |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 3.305ms   | ±128.39% |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 4.749ms   | ±76.16%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 2.476ms   | ±3.65%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.601ms   | ±52.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 8.531ms   | ±49.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.508ms   | ±42.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.645ms   | ±163.00% |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 439.234μs | ±15.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.246ms   | ±139.64% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.338ms   | ±83.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.166ms   | ±3.55%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 107.107ms | ±15.21%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 2.471ms   | ±2.38%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.535ms   | ±68.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.216ms   | ±87.32%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 8.579ms   | ±37.73%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 9.507ms   | ±5.35%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.576ms   | ±38.02%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 13.962ms  | ±40.98%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 20.440ms  | ±8.53%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 1.394ms   | ±114.24% |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 1.087ms   | ±105.01% |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 1.409ms   | ±95.52%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.042ms   | ±23.24%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.611ms   | ±19.16%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 17.899ms  | ±3.15%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 20.085ms  | ±0.82%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 22.871ms  | ±14.07%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 40.250ms  | ±1.87%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 62.648ms  | ±0.73%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 8.644ms   | ±125.21% |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 12.084ms  | ±109.03% |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 13.458ms  | ±25.70%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 42.974ms  | ±0.36%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 96.430ms  | ±3.44%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 3.320ms   | ±2.62%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 30.114ms  | ±0.59%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.344μs   | ±34.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 0.344μs   | ±34.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 0.537μs   | ±41.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 97.859ms  | ±22.84%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 317.530μs | ±7.27%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 1.943ms   | ±0.83%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 2.733ms   | ±60.89%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 11.030ms  | ±26.12%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 47.991ms  | ±0.70%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 8.227ms   | ±0.28%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 14.396ms  | ±0.22%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 142.472ms | ±20.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 8.548ms   | ±0.94%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 8.374ms   | ±78.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 10.092ms  | ±31.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 8.674ms   | ±0.82%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 8.792ms   | ±98.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.141ms   | ±101.35% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 8.621ms   | ±1.26%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 10.956ms  | ±36.13%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 9.371ms   | ±45.83%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 6.955ms   | ±3.41%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 6.615ms   | ±1.98%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 7.750ms   | ±4.19%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 9.024ms   | ±5.49%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 7.451ms   | ±2.22%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 6.849ms   | ±1.38%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 14.525ms  | ±2.89%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 2.318ms   | ±7.28%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 27.746μs  | ±5.84%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 163.526μs | ±5.58%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.671ms   | ±67.45%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 8.156ms   | ±46.97%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 29.738ms  | ±6.24%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 2.817ms   | ±150.32% |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 7.394ms   | ±51.20%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 6.063ms   | ±33.02%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 5.568ms   | ±98.65%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 5.231ms   | ±1.93%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.024μs   | ±16.64%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 0.873μs   | ±47.14%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.502mb | 10.930ms  | ±1.34%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.502mb | 10.639ms  | ±0.15%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```