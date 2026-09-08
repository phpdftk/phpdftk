# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-08 16:47:16 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.639ms | 2.608ms | 2.838ms | 4.892ms | 7.085ms |
| FPDF | 805.311μs | 878.729μs | 977.704μs | 1.551ms | 2.293ms |
| TCPDF | 10.386ms | 11.455ms | 12.472ms | 21.285ms | 31.719ms |
| mPDF | 26.232ms | 29.948ms | 33.849ms | 67.121ms | 108.339ms |
| Dompdf | 12.018ms | 16.662ms | 22.519ms | 74.927ms | 166.383ms |

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
| phpdftk | 3.642ms | 3.873ms | 3.964ms | 6.148ms | 8.481ms |
| FPDF | 1.209ms | 1.340ms | 1.270ms | 1.931ms | 2.808ms |
| TCPDF | 15.708ms | 16.279ms | 17.210ms | 27.481ms | 40.194ms |

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
| Pdf (Level 3) | 3.515ms | 4.626ms | 12.946ms |
| PdfDoc (Level 2) | 2.826ms | 3.245ms | 7.673ms |
| PdfWriter (Level 1) | 2.380ms | 2.831ms | 7.049ms |

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
| Pdf (Level 3) | 4.593ms | 12.454ms | 47.265ms |
| PdfDoc (Level 2) | 3.842ms | 10.173ms | — |

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
| Pdf (Level 3) | 4.212ms | 11.822ms | 45.403ms |
| PdfDoc (Level 2) | 3.383ms | 7.447ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.378ms | 1.742ms | 6.006ms |
| smalot/pdfparser | 2.083ms | 2.496ms | 5.904ms |
| setasign/fpdi | 2.039ms | 2.966ms | 30.378ms |

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
| phpdftk | 2.086ms | 1.433ms |
| smalot/pdfparser | FAIL | 2.010ms |
| setasign/fpdi | 3.084ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.642ms   | ±2.88%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.873ms   | ±27.11% |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.964ms   | ±2.06%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 6.148ms   | ±12.64% |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.481ms   | ±0.63%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.708ms  | ±2.89%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.279ms  | ±1.07%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.210ms  | ±1.54%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 27.481ms  | ±1.71%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 40.194ms  | ±1.28%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.209ms   | ±5.97%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.340ms   | ±7.70%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.270ms   | ±0.24%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.931ms   | ±0.51%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.808ms   | ±0.57%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.380ms   | ±0.89%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.831ms   | ±0.72%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 7.049ms   | ±0.93%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.826ms   | ±5.30%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.245ms   | ±1.64%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.673ms   | ±5.67%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.515ms   | ±2.62%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.626ms   | ±3.89%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.946ms  | ±0.77%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.531mb | 87.843ms  | ±0.24%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.423mb | 385.411ms | ±2.94%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.486mb | 1.482s    | ±0.37%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.598mb | 270.499ms | ±0.95%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.560mb | 202.842ms | ±0.59%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.340mb | 164.055ms | ±0.45%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.411mb | 224.916ms | ±0.52%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.948mb | 187.507ms | ±1.14%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.503mb | 353.313ms | ±0.38%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.043mb | 53.663ms  | ±0.41%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.960mb | 47.357ms  | ±0.88%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.885mb | 44.846ms  | ±0.44%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.228mb | 148.080ms | ±1.15%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.948mb | 49.865ms  | ±0.44%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.166mb | 62.825ms  | ±0.44%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.594mb | 92.647ms  | ±0.74%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.852mb | 39.977ms  | ±2.00%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.855mb | 47.870ms  | ±1.13%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.882mb | 50.049ms  | ±0.94%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.851mb | 48.737ms  | ±0.73%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.873mb | 47.703ms  | ±0.45%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.726mb | 76.394ms  | ±1.74%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.833mb | 45.827ms  | ±0.89%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.453mb | 41.084ms  | ±0.15%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.754mb | 45.485ms  | ±1.03%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.840mb | 49.970ms  | ±0.76%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.834mb | 50.047ms  | ±0.22%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.000mb | 44.947ms  | ±1.15%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.205mb | 235.655ms | ±0.23%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.423mb | 172.475ms | ±0.31%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.230mb | 59.215ms  | ±1.07%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.838mb | 119.851ms | ±0.67%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.905mb | 1.437s    | ±0.96%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.356mb | 26.963ms  | ±3.69%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.317mb | 61.933ms  | ±0.53%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.128mb | 569.956ms | ±1.15%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.574mb | 67.887ms  | ±10.27% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.294mb | 90.905ms  | ±1.75%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.063mb | 758.618ms | ±1.30%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.339mb | 19.512ms  | ±1.14%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.339mb | 44.430ms  | ±0.96%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.981mb | 333.875ms | ±1.00%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.308ms   | ±1.12%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.742ms   | ±1.10%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.006ms   | ±1.09%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.086ms   | ±0.69%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.433ms   | ±0.66%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.083ms   | ±0.99%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.496ms   | ±1.79%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.904ms   | ±1.03%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 575.468μs | ±1.81%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 2.010ms   | ±0.80%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 2.039ms   | ±1.29%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.966ms   | ±0.58%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.378ms  | ±0.93%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.084ms   | ±1.00%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.594ms   | ±0.89%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.472ms   | ±0.67%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.691ms   | ±0.70%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 4.100ms   | ±0.48%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.832μs   | ±22.58% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.378ms   | ±1.12%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.535mb  | 27.765ms  | ±0.63%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.704mb | 246.419ms | ±0.43%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.032mb | 1.214s    | ±2.13%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.813mb | 192.118ms | ±0.85%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.212ms   | ±0.92%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.822ms  | ±0.79%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.403ms  | ±0.34%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.383ms   | ±1.28%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.447ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.347ms   | ±1.14%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.608ms   | ±21.46% |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.838ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.892ms   | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.085ms   | ±0.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.648ms   | ±2.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.928ms   | ±0.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.413ms  | ±1.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.704ms   | ±0.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.407ms   | ±0.88%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 673.646μs | ±3.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.263ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.755ms   | ±3.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.314ms   | ±1.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 174.761ms | ±17.78% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.635ms   | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.923ms   | ±45.61% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.086ms   | ±1.66%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.386ms  | ±3.95%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.455ms  | ±2.41%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.472ms  | ±1.81%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 21.285ms  | ±1.63%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.719ms  | ±1.21%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 805.311μs | ±1.72%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 878.729μs | ±1.81%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 977.704μs | ±5.53%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.551ms   | ±10.36% |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.293ms   | ±0.68%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 26.232ms  | ±2.45%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.948ms  | ±1.30%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.849ms  | ±1.07%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 67.121ms  | ±0.46%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 108.339ms | ±0.82%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 12.018ms  | ±1.67%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.662ms  | ±1.67%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 22.519ms  | ±0.52%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 74.927ms  | ±0.56%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 166.383ms | ±1.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.189ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.437ms  | ±3.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 218.316ms | ±20.87% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 472.337μs | ±1.29%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.102ms   | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.524ms   | ±1.73%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 14.310ms  | ±5.29%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 85.907ms  | ±1.29%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.217ms  | ±2.30%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 24.888ms  | ±1.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 170.130ms | ±23.69% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.418ms  | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.416ms  | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.675ms  | ±2.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.957ms  | ±2.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.112ms  | ±0.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.261ms   | ±7.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.817ms  | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.797ms  | ±2.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.639ms  | ±0.99%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 10.584ms  | ±0.46%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 10.708ms  | ±0.88%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 12.405ms  | ±0.26%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 12.930ms  | ±0.68%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 11.794ms  | ±6.03%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 11.245ms  | ±0.47%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 20.594ms  | ±1.28%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 3.268ms   | ±1.80%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 43.036μs  | ±1.36%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 251.519μs | ±0.53%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.593ms   | ±0.74%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.454ms  | ±0.63%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.265ms  | ±0.90%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.842ms   | ±2.43%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 10.173ms  | ±0.85%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 9.044ms   | ±1.13%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.691ms   | ±0.38%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.891ms   | ±0.96%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.960μs   | ±15.93% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.097μs   | ±29.77% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.477mb | 16.767ms  | ±0.71%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.477mb | 16.762ms  | ±0.54%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```