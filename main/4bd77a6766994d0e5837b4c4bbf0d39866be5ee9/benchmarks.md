# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-17 01:37:02 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.673ms | 1.918ms | 2.106ms | 3.610ms | 5.412ms |
| FPDF | 587.128μs | 647.279μs | 713.298μs | 1.180ms | 1.752ms |
| TCPDF | 7.870ms | 8.502ms | 9.302ms | 14.816ms | 21.963ms |
| mPDF | 19.811ms | 22.517ms | 25.159ms | 46.618ms | 73.499ms |
| Dompdf | 8.582ms | 11.711ms | 15.504ms | 51.313ms | 115.959ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.148mb | 5.939mb | 6.025mb | 6.659mb | 7.482mb |
| FPDF | 5.072mb | 5.073mb | 5.073mb | 5.073mb | 5.083mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.682mb | 17.721mb | 18.014mb | 18.375mb |
| Dompdf | 9.357mb | 9.577mb | 9.898mb | 12.591mb | 15.954mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.507ms | 2.676ms | 2.915ms | 4.417ms | 6.349ms |
| FPDF | 856.068μs | 874.525μs | 959.233μs | 1.507ms | 2.129ms |
| TCPDF | 15.524ms | 13.949ms | 14.839ms | 21.518ms | 29.845ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.365mb | 5.412mb | 5.471mb | 5.964mb | 6.562mb |
| FPDF | 4.455mb | 4.455mb | 4.455mb | 4.455mb | 4.504mb |
| TCPDF | 12.487mb | 12.487mb | 12.487mb | 12.487mb | 12.487mb |

## Writer Levels Comparison — `WriterLevelsBench`

Same workload (N pages with heading + body text) rendered through each
writer level, so the abstraction overhead is visible directly. Lower is
better; the higher-level APIs (`Pdf` → `PdfDoc` → `PdfWriter`) trade
some performance for ergonomics.

### Generation Time

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 4.425ms | 3.309ms | 9.421ms |
| PdfDoc (Level 2) | 2.075ms | 3.659ms | 5.627ms |
| PdfWriter (Level 1) | 1.764ms | 2.122ms | 7.084ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 5.987mb | 6.150mb | 7.827mb |
| PdfDoc (Level 2) | 5.644mb | 5.802mb | 7.371mb |
| PdfWriter (Level 1) | 5.381mb | 5.540mb | 7.115mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 9.781ms | 9.202ms | 35.955ms |
| PdfDoc (Level 2) | 2.842ms | 7.660ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.338mb | 9.133mb | 21.542mb |
| PdfDoc (Level 2) | 6.144mb | 8.960mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 3.105ms | 9.641ms | 35.150ms |
| PdfDoc (Level 2) | 3.027ms | 5.658ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.719ms | 1.282ms | 4.686ms |
| smalot/pdfparser | 1.549ms | 1.821ms | 4.289ms |
| setasign/fpdi | 1.474ms | 2.086ms | 22.150ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.341mb | 4.243mb | 4.594mb |
| smalot/pdfparser | 4.807mb | 4.891mb | 6.601mb |
| setasign/fpdi | 4.742mb | 4.769mb | 5.526mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 1.565ms | 1.032ms |
| smalot/pdfparser | FAIL | 1.484ms |
| setasign/fpdi | 2.205ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 943.780μs | ±2.04%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.282ms   | ±0.87%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 4.686ms   | ±5.34%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.565ms   | ±7.79%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.032ms   | ±6.03%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.549ms   | ±1.02%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 1.821ms   | ±1.92%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.289ms   | ±1.03%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 438.656μs | ±2.14%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.484ms   | ±0.88%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.474ms   | ±1.19%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.086ms   | ±0.77%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 22.150ms  | ±1.11%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.205ms   | ±1.71%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.153ms   | ±0.77%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 5.487ms   | ±0.85%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 4.121ms   | ±0.64%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 2.958ms   | ±6.05%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.085μs   | ±19.04%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.719ms   | ±0.78%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 13.031mb | 34.931ms  | ±0.52%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.619mb | 70.819ms  | ±0.52%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.263mb | 819.231ms | ±0.66%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.114mb | 18.789ms  | ±0.75%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 16.074mb | 40.839ms  | ±1.12%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.885mb | 383.711ms | ±0.95%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.741mb | 50.069ms  | ±9.05%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.395mb | 64.728ms  | ±1.28%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 31.164mb | 517.218ms | ±0.09%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.096mb | 14.140ms  | ±0.73%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.096mb | 32.435ms  | ±0.33%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.738mb | 250.001ms | ±6.31%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 9.781ms   | ±104.31% |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 9.202ms   | ±30.82%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 35.955ms  | ±0.44%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 2.842ms   | ±42.59%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 7.660ms   | ±0.69%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.308mb | 50.628ms  | ±0.61%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.107mb | 213.615ms | ±1.61%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.727mb | 834.776ms | ±0.47%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.541mb | 158.506ms | ±0.36%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.872mb | 133.555ms | ±0.41%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 14.094mb | 98.222ms  | ±0.41%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.131mb | 129.943ms | ±0.97%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.687mb | 104.414ms | ±0.22%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.166mb | 201.899ms | ±0.58%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.833mb | 31.791ms  | ±0.70%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.753mb | 27.868ms  | ±0.23%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.683mb | 27.065ms  | ±0.67%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.984mb | 84.625ms  | ±0.28%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.741mb | 29.140ms  | ±0.79%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.953mb | 36.993ms  | ±0.19%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.369mb | 53.217ms  | ±0.44%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.648mb | 23.542ms  | ±0.40%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 13.091mb | 29.642ms  | ±0.88%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.219mb | 132.377ms | ±1.18%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.214mb | 90.245ms  | ±1.21%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 6.832ms   | ±7.29%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 6.509ms   | ±90.07%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 6.392ms   | ±0.36%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 1.764ms   | ±2.52%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.122ms   | ±0.40%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 7.084ms   | ±96.95%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.075ms   | ±18.27%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.659ms   | ±21.52%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 5.627ms   | ±0.43%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 4.425ms   | ±106.14% |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 3.309ms   | ±82.34%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 9.421ms   | ±0.66%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.105ms   | ±94.97%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 9.641ms   | ±55.20%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 35.150ms  | ±3.20%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.027ms   | ±87.84%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 5.658ms   | ±109.78% |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.733μs   | ±22.02%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.709μs   | ±38.57%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.295mb | 10.498ms  | ±0.51%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.295mb | 10.446ms  | ±0.50%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 1.725ms   | ±1.15%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 1.918ms   | ±46.72%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.106ms   | ±0.86%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 3.610ms   | ±0.66%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 5.412ms   | ±117.21% |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 2.628ms   | ±0.63%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 2.863ms   | ±1.13%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 10.024ms  | ±7.91%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 2.814ms   | ±101.93% |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 1.808ms   | ±1.71%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 516.743μs | ±154.71% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 2.392ms   | ±4.73%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 2.753ms   | ±0.34%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 2.454ms   | ±9.77%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 187.409ms | ±33.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 40.006ms  | ±75.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 5.229ms   | ±81.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 4.656ms   | ±43.26%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.870ms   | ±88.34%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 8.502ms   | ±0.37%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.302ms   | ±17.24%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 14.816ms  | ±0.39%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 21.963ms  | ±0.53%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 587.128μs | ±2.40%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 647.279μs | ±1.12%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 713.298μs | ±5.37%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.180ms   | ±2.07%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 1.752ms   | ±0.60%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 19.811ms  | ±1.92%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 22.517ms  | ±0.24%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 25.159ms  | ±0.22%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 46.618ms  | ±0.52%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 73.499ms  | ±0.28%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 8.582ms   | ±0.31%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 11.711ms  | ±0.50%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 15.504ms  | ±0.43%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 51.313ms  | ±0.63%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 115.959ms | ±0.37%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 3.816ms   | ±0.78%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.488mb  | 41.963ms  | ±0.57%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.594μs   | ±56.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.644μs   | ±23.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 153.006ms | ±28.20%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 364.792μs | ±0.66%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.239ms   | ±0.21%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 2.554ms   | ±0.58%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 9.621ms   | ±22.50%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 63.537ms  | ±1.24%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.936ms  | ±0.92%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.424ms  | ±2.49%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 155.410ms | ±21.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 13.096ms  | ±73.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 10.782ms  | ±1.05%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 10.853ms  | ±54.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 13.919ms  | ±74.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 11.109ms  | ±1.17%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.549ms   | ±125.90% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 11.215ms  | ±158.70% |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 11.608ms  | ±35.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 10.673ms  | ±76.01%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 2.507ms   | ±0.48%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 2.676ms   | ±0.59%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 2.915ms   | ±0.91%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 4.417ms   | ±0.45%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 6.349ms   | ±0.40%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.524ms  | ±65.89%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 13.949ms  | ±0.59%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 14.839ms  | ±0.67%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 21.518ms  | ±0.99%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 29.845ms  | ±0.41%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 856.068μs | ±2.37%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 874.525μs | ±1.02%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 959.233μs | ±0.52%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.507ms   | ±2.04%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.129ms   | ±0.81%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 7.820mb  | 6.629ms   | ±0.41%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.303mb  | 6.890ms   | ±0.41%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.539mb  | 7.801ms   | ±0.45%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.562mb  | 7.788ms   | ±0.45%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.047mb  | 7.316ms   | ±0.41%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.185mb  | 6.939ms   | ±0.42%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.038mb | 12.253ms  | ±0.40%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.685mb  | 2.200ms   | ±1.76%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 33.705μs  | ±1.33%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 182.013μs | ±0.70%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.962mb  | 15.906ms  | ±0.85%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.947mb | 142.385ms | ±2.12%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.463mb | 696.865ms | ±1.83%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```