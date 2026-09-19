# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-19 04:52:28 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.663ms | 4.310ms | 4.920ms | 3.734ms | 5.368ms |
| FPDF | 612.742μs | 652.555μs | 722.223μs | 1.175ms | 1.784ms |
| TCPDF | 7.840ms | 8.473ms | 9.194ms | 15.122ms | 21.910ms |
| mPDF | 19.846ms | 23.583ms | 25.323ms | 46.786ms | 73.328ms |
| Dompdf | 8.554ms | 11.793ms | 15.437ms | 51.475ms | 114.603ms |

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
| phpdftk | 2.716ms | 2.710ms | 2.922ms | 4.434ms | 7.932ms |
| FPDF | 795.408μs | 864.720μs | 977.657μs | 1.838ms | 2.114ms |
| TCPDF | 11.305ms | 12.012ms | 12.778ms | 19.274ms | 27.370ms |

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
| Pdf (Level 3) | 2.828ms | 3.373ms | 9.353ms |
| PdfDoc (Level 2) | 2.098ms | 2.443ms | 5.765ms |
| PdfWriter (Level 1) | 13.187ms | 2.128ms | 5.297ms |

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
| Pdf (Level 3) | 3.308ms | 9.222ms | 35.619ms |
| PdfDoc (Level 2) | 2.934ms | 8.951ms | — |

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
| Pdf (Level 3) | 3.913ms | 10.902ms | 34.470ms |
| PdfDoc (Level 2) | 5.199ms | 6.185ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.660ms | 1.296ms | 4.680ms |
| smalot/pdfparser | 1.539ms | 1.798ms | 4.256ms |
| setasign/fpdi | 1.450ms | 2.086ms | 22.340ms |

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
| phpdftk | 1.553ms | 1.030ms |
| smalot/pdfparser | FAIL | 1.449ms |
| setasign/fpdi | 2.219ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 2.716ms   | ±33.96%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 2.710ms   | ±0.35%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 2.922ms   | ±0.47%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 4.434ms   | ±2.00%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 7.932ms   | ±82.69%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 11.305ms  | ±0.89%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 12.012ms  | ±0.58%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 12.778ms  | ±0.22%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 19.274ms  | ±0.58%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 27.370ms  | ±0.10%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 795.408μs | ±3.77%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 864.720μs | ±2.49%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 977.657μs | ±1.43%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.838ms   | ±82.59%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.114ms   | ±2.40%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 13.187ms  | ±116.06% |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.128ms   | ±0.85%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 5.297ms   | ±6.21%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.098ms   | ±0.43%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.443ms   | ±26.85%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 5.765ms   | ±0.68%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 2.828ms   | ±47.95%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 3.373ms   | ±0.96%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 9.353ms   | ±0.53%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.049mb | 63.093ms  | ±3.12%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 22.179mb | 267.952ms | ±0.08%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 46.907mb | 1.050s    | ±4.65%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.235mb | 198.243ms | ±2.67%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.035mb | 150.910ms | ±0.42%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.868mb | 119.230ms | ±0.70%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.992mb | 160.950ms | ±0.76%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.487mb | 132.362ms | ±2.10%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 21.189mb | 254.743ms | ±1.70%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.501mb | 38.527ms  | ±2.28%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.414mb | 33.804ms  | ±0.12%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.346mb | 33.659ms  | ±3.17%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.740mb | 109.133ms | ±2.46%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.403mb | 35.578ms  | ±0.53%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.629mb | 44.532ms  | ±3.77%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.112mb | 65.397ms  | ±2.64%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.300mb | 28.700ms  | ±0.64%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.303mb | 34.765ms  | ±0.34%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.330mb | 36.159ms  | ±1.61%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.299mb | 35.269ms  | ±1.36%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.321mb | 33.898ms  | ±0.06%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.230mb | 53.718ms  | ±0.52%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.273mb | 34.020ms  | ±1.60%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.952mb | 29.495ms  | ±0.38%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.268mb | 33.810ms  | ±2.47%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.289mb | 36.069ms  | ±0.60%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.282mb | 35.716ms  | ±0.79%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.434mb | 33.422ms  | ±1.62%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.947mb | 167.579ms | ±0.24%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.887mb | 127.715ms | ±1.60%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.782mb | 42.166ms  | ±0.10%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.335mb | 85.117ms  | ±0.15%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 45.019mb | 1.082s    | ±2.86%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.750mb | 18.314ms  | ±2.29%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.710mb | 40.431ms  | ±1.06%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.521mb | 351.317ms | ±0.12%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.034mb | 49.004ms  | ±9.39%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.687mb | 63.460ms  | ±1.28%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.456mb | 509.684ms | ±0.59%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.733mb | 13.553ms  | ±0.30%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.733mb | 32.054ms  | ±0.47%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.441mb | 215.038ms | ±0.43%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 931.163μs | ±1.73%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.296ms   | ±1.36%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 4.680ms   | ±0.33%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.553ms   | ±1.40%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.030ms   | ±0.68%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.539ms   | ±0.87%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.798ms   | ±0.88%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.256ms   | ±0.79%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 428.900μs | ±1.12%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.449ms   | ±0.93%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.450ms   | ±1.03%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.086ms   | ±0.59%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 22.340ms  | ±0.64%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.219ms   | ±0.86%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.157ms   | ±4.48%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 5.473ms   | ±0.55%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 4.138ms   | ±0.81%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.944ms   | ±0.31%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.085μs   | ±19.04%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.660ms   | ±0.44%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.651mb  | 19.609ms  | ±0.13%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.048mb | 180.571ms | ±5.47%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.394mb | 876.989ms | ±1.15%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.074mb | 137.702ms | ±2.02%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.913ms   | ±56.33%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 10.902ms  | ±103.91% |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 34.470ms  | ±0.26%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 5.199ms   | ±47.25%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 6.185ms   | ±4.52%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 5.762ms   | ±136.46% |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 4.310ms   | ±104.48% |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 4.920ms   | ±92.50%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 3.734ms   | ±130.97% |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 5.368ms   | ±0.41%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 2.734ms   | ±0.65%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 2.911ms   | ±0.53%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 9.994ms   | ±12.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.763ms   | ±0.52%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.795ms   | ±0.60%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 473.383μs | ±3.02%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.406ms   | ±1.39%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 2.752ms   | ±19.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.412ms   | ±0.38%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 198.156ms | ±20.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 5.603ms   | ±78.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 10.437ms  | ±121.01% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.244ms   | ±71.20%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.840ms   | ±90.03%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 8.473ms   | ±0.44%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.194ms   | ±0.64%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 15.122ms  | ±11.07%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 21.910ms  | ±0.33%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 612.742μs | ±3.37%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 652.555μs | ±1.36%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 722.223μs | ±0.97%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.175ms   | ±0.91%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.784ms   | ±60.53%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 19.846ms  | ±1.67%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 23.583ms  | ±33.98%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 25.323ms  | ±0.37%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 46.786ms  | ±0.35%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 73.328ms  | ±0.50%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 8.554ms   | ±0.37%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 11.793ms  | ±0.48%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 15.437ms  | ±0.99%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 51.475ms  | ±0.60%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 114.603ms | ±0.43%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 3.795ms   | ±0.81%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 41.497ms  | ±17.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.344μs   | ±11.13%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.463μs   | ±17.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 99.903ms  | ±29.83%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 378.791μs | ±1.63%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.248ms   | ±1.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 2.578ms   | ±0.98%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 10.227ms  | ±8.64%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 69.543ms  | ±2.47%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.527ms  | ±0.46%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.228ms  | ±1.44%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 233.926ms | ±21.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 10.768ms  | ±47.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 10.671ms  | ±26.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 10.832ms  | ±0.50%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 10.856ms  | ±0.55%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 10.966ms  | ±20.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.395ms   | ±63.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 10.845ms  | ±11.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 10.906ms  | ±8.06%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 10.663ms  | ±0.44%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.351mb  | 7.626ms   | ±0.30%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.245mb  | 7.620ms   | ±0.54%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.397mb  | 8.941ms   | ±1.09%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.095mb  | 8.857ms   | ±0.28%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.577mb  | 8.504ms   | ±0.12%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.651mb  | 7.709ms   | ±0.37%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.896mb | 13.986ms  | ±0.62%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.791mb  | 2.350ms   | ±0.32%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 33.121μs  | ±1.27%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 182.029μs | ±0.93%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 3.308ms   | ±0.99%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 9.222ms   | ±0.33%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 35.619ms  | ±0.37%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 2.934ms   | ±100.21% |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 8.951ms   | ±64.71%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 7.117ms   | ±45.32%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 6.423ms   | ±0.69%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 6.506ms   | ±0.45%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.576μs   | ±28.12%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.797μs   | ±32.35%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.913mb | 13.183ms  | ±0.47%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.913mb | 13.100ms  | ±0.86%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```