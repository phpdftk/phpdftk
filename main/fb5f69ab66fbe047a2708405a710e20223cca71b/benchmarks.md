# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-14 12:49:45 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.626ms | 1.933ms | 2.122ms | 3.627ms | 5.365ms |
| FPDF | 605.004μs | 656.333μs | 709.694μs | 1.199ms | 1.761ms |
| TCPDF | 7.709ms | 8.466ms | 9.245ms | 14.869ms | 21.960ms |
| mPDF | 19.988ms | 22.582ms | 25.278ms | 47.023ms | 74.125ms |
| Dompdf | 8.602ms | 11.880ms | 15.517ms | 51.733ms | 115.399ms |

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
| phpdftk | 4.461ms | 5.532ms | 2.921ms | 4.442ms | 6.380ms |
| FPDF | 813.704μs | 881.287μs | 945.216μs | 1.482ms | 2.155ms |
| TCPDF | 11.467ms | 12.314ms | 16.494ms | 19.407ms | 27.657ms |

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
| Pdf (Level 3) | 4.269ms | 3.395ms | 9.356ms |
| PdfDoc (Level 2) | 2.097ms | 2.418ms | 17.171ms |
| PdfWriter (Level 1) | 4.691ms | 2.122ms | 5.230ms |

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
| Pdf (Level 3) | 3.344ms | 9.338ms | 35.954ms |
| PdfDoc (Level 2) | 2.890ms | 7.918ms | — |

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
| Pdf (Level 3) | 3.139ms | 8.950ms | 34.552ms |
| PdfDoc (Level 2) | 2.524ms | 5.678ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.690ms | 1.299ms | 4.675ms |
| smalot/pdfparser | 1.534ms | 1.825ms | 4.203ms |
| setasign/fpdi | 1.456ms | 2.080ms | 22.226ms |

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
| phpdftk | 1.571ms | 1.024ms |
| smalot/pdfparser | FAIL | 1.457ms |
| setasign/fpdi | 2.208ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 4.461ms   | ±113.43% |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 5.532ms   | ±120.14% |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 2.921ms   | ±1.00%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 4.442ms   | ±0.19%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 6.380ms   | ±1.02%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 11.467ms  | ±0.25%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 12.314ms  | ±13.24%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.494ms  | ±83.41%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 19.407ms  | ±0.66%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 27.657ms  | ±0.46%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 813.704μs | ±4.14%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 881.287μs | ±1.08%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 945.216μs | ±1.14%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.482ms   | ±0.63%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.155ms   | ±1.36%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 4.691ms   | ±95.71%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.122ms   | ±1.16%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 5.230ms   | ±1.43%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.097ms   | ±120.09% |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.418ms   | ±0.69%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 17.171ms  | ±85.50%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 4.269ms   | ±143.10% |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 3.395ms   | ±0.52%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 9.356ms   | ±0.73%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.556mb | 61.776ms  | ±3.46%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.472mb | 273.237ms | ±2.21%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.505mb | 1.038s    | ±0.20%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.623mb | 186.644ms | ±0.36%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.585mb | 149.500ms | ±1.55%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.365mb | 116.740ms | ±0.33%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.436mb | 158.614ms | ±0.42%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.973mb | 130.009ms | ±3.39%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.528mb | 247.888ms | ±4.30%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.068mb | 38.103ms  | ±3.30%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.984mb | 33.247ms  | ±3.30%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.910mb | 31.554ms  | ±2.28%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.254mb | 102.650ms | ±3.17%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.973mb | 34.598ms  | ±0.66%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.191mb | 45.928ms  | ±3.26%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.619mb | 64.224ms  | ±3.32%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.877mb | 28.104ms  | ±0.10%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.880mb | 34.204ms  | ±1.08%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.907mb | 36.107ms  | ±1.85%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.876mb | 34.812ms  | ±0.42%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.898mb | 33.774ms  | ±2.16%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.751mb | 53.472ms  | ±0.13%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.858mb | 33.418ms  | ±0.98%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.477mb | 29.155ms  | ±0.36%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.779mb | 32.189ms  | ±0.62%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.865mb | 35.610ms  | ±0.40%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.859mb | 35.210ms  | ±0.24%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.025mb | 32.434ms  | ±2.20%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.230mb | 173.034ms | ±2.59%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.448mb | 126.944ms | ±0.53%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.255mb | 41.755ms  | ±0.22%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.862mb | 89.558ms  | ±20.35%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.912mb | 1.004s    | ±0.54%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.381mb | 19.005ms  | ±23.20%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.341mb | 40.454ms  | ±0.87%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.152mb | 353.382ms | ±1.87%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.599mb | 49.728ms  | ±9.07%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.318mb | 64.074ms  | ±0.76%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.087mb | 513.632ms | ±0.69%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.363mb | 15.308ms  | ±58.25%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.363mb | 31.809ms  | ±0.52%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.006mb | 212.661ms | ±0.34%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 937.861μs | ±1.35%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.299ms   | ±1.16%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 4.675ms   | ±0.86%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.571ms   | ±0.98%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.024ms   | ±0.97%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.534ms   | ±1.09%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.825ms   | ±1.07%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.203ms   | ±0.89%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 431.127μs | ±1.99%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.457ms   | ±1.10%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.456ms   | ±2.12%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.080ms   | ±0.49%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 22.226ms  | ±0.79%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.208ms   | ±0.35%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.161ms   | ±0.49%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 5.516ms   | ±0.51%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 4.138ms   | ±0.34%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.934ms   | ±0.35%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.915μs   | ±18.40%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.690ms   | ±0.38%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.542mb  | 19.677ms  | ±6.67%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.711mb | 176.668ms | ±3.46%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.039mb | 865.610ms | ±8.07%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.820mb | 131.910ms | ±1.22%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.139ms   | ±0.43%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 8.950ms   | ±54.33%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 34.552ms  | ±0.77%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.524ms   | ±0.71%   |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 5.678ms   | ±50.61%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 1.800ms   | ±43.33%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 1.933ms   | ±0.67%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.122ms   | ±0.25%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 3.627ms   | ±0.70%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 5.365ms   | ±1.39%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 2.931ms   | ±119.56% |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.187ms   | ±139.45% |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 10.582ms  | ±32.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.728ms   | ±0.63%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.830ms   | ±1.18%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 457.766μs | ±6.48%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.427ms   | ±0.39%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 2.746ms   | ±1.15%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.429ms   | ±1.25%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 164.353ms | ±25.24%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 2.715ms   | ±0.66%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 4.483ms   | ±30.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 4.575ms   | ±0.90%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.709ms   | ±0.13%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 8.466ms   | ±0.29%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.245ms   | ±0.34%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 14.869ms  | ±0.36%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 21.960ms  | ±0.15%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 605.004μs | ±1.10%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 656.333μs | ±1.60%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 709.694μs | ±1.19%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.199ms   | ±9.41%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.761ms   | ±0.79%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 19.988ms  | ±1.95%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 22.582ms  | ±0.95%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 25.278ms  | ±0.20%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 47.023ms  | ±1.62%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 74.125ms  | ±0.34%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 8.602ms   | ±5.72%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 11.880ms  | ±0.44%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 15.517ms  | ±0.45%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 51.733ms  | ±0.46%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 115.399ms | ±0.48%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 3.834ms   | ±0.47%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 41.416ms  | ±1.05%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.176μs   | ±65.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 169.481ms | ±25.70%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 384.040μs | ±0.67%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.246ms   | ±0.58%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 2.578ms   | ±61.00%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 15.470ms  | ±54.35%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 63.781ms  | ±0.90%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.942ms  | ±0.89%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.587ms  | ±1.25%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 183.495ms | ±27.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 10.820ms  | ±0.29%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 10.806ms  | ±81.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 12.541ms  | ±79.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 10.883ms  | ±1.09%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 11.181ms  | ±52.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.426ms   | ±0.50%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 10.771ms  | ±0.42%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 10.824ms  | ±41.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 10.626ms  | ±0.76%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 7.549ms   | ±0.49%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 7.626ms   | ±0.26%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 8.886ms   | ±0.88%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 8.800ms   | ±0.95%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 8.522ms   | ±1.26%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 7.701ms   | ±0.42%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 13.887ms  | ±0.34%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 2.377ms   | ±0.96%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 33.300μs  | ±0.30%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 183.974μs | ±0.71%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 3.344ms   | ±0.78%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 9.338ms   | ±44.29%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 35.954ms  | ±1.00%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 2.890ms   | ±0.71%   |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 7.918ms   | ±134.78% |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 6.803ms   | ±0.53%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 6.373ms   | ±1.01%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 6.512ms   | ±0.78%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.824μs   | ±9.75%   |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.808μs   | ±35.54%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.502mb | 12.793ms  | ±2.08%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.502mb | 12.808ms  | ±0.40%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```