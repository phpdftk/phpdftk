# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-07 16:58:54 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.733ms | 2.479ms | 2.697ms | 4.707ms | 6.852ms |
| FPDF | 762.429μs | 837.889μs | 929.993μs | 1.513ms | 2.229ms |
| TCPDF | 10.002ms | 10.901ms | 11.818ms | 19.057ms | 28.121ms |
| mPDF | 25.511ms | 29.441ms | 32.458ms | 60.346ms | 95.246ms |
| Dompdf | 11.062ms | 15.114ms | 19.973ms | 66.244ms | 148.125ms |

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
| phpdftk | 3.275ms | 3.520ms | 3.757ms | 5.734ms | 8.129ms |
| FPDF | 1.042ms | 1.117ms | 1.230ms | 1.898ms | 2.697ms |
| TCPDF | 14.700ms | 15.500ms | 16.526ms | 24.968ms | 35.365ms |

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
| Pdf (Level 3) | 3.362ms | 4.326ms | 12.040ms |
| PdfDoc (Level 2) | 2.648ms | 3.092ms | 7.382ms |
| PdfWriter (Level 1) | 2.286ms | 2.693ms | 6.739ms |

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
| Pdf (Level 3) | 4.283ms | 11.901ms | 45.865ms |
| PdfDoc (Level 2) | 3.733ms | 9.804ms | — |

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
| Pdf (Level 3) | 4.041ms | 11.571ms | 44.441ms |
| PdfDoc (Level 2) | 3.250ms | 7.272ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.066ms | 1.639ms | 6.012ms |
| smalot/pdfparser | 1.976ms | 2.342ms | 5.419ms |
| setasign/fpdi | 1.898ms | 2.697ms | 28.541ms |

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
| phpdftk | 2.008ms | 1.329ms |
| smalot/pdfparser | FAIL | 1.892ms |
| setasign/fpdi | 2.847ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.275ms   | ±1.15%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.520ms   | ±0.11%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.757ms   | ±0.50%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.734ms   | ±1.01%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.129ms   | ±0.73%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.700ms  | ±0.71%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.500ms  | ±0.23%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.526ms  | ±0.26%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 24.968ms  | ±0.77%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 35.365ms  | ±0.78%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.042ms   | ±1.57%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.117ms   | ±0.31%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.230ms   | ±0.23%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.898ms   | ±0.79%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.697ms   | ±0.60%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.286ms   | ±1.53%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.693ms   | ±0.53%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.739ms   | ±0.86%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.648ms   | ±1.56%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.092ms   | ±1.28%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.382ms   | ±0.38%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.362ms   | ±0.31%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.326ms   | ±1.88%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.040ms  | ±0.46%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.506mb | 79.434ms  | ±2.85%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.398mb | 341.776ms | ±1.39%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.461mb | 1.450s    | ±0.63%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.572mb | 240.109ms | ±0.25%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.469mb | 196.144ms | ±1.71%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.315mb | 150.456ms | ±2.01%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.386mb | 203.542ms | ±0.67%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.923mb | 180.026ms | ±4.20%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.478mb | 334.689ms | ±2.10%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.018mb | 48.461ms  | ±0.22%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.935mb | 42.366ms  | ±0.68%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.860mb | 40.465ms  | ±0.18%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.203mb | 136.956ms | ±2.69%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.923mb | 44.711ms  | ±2.59%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.141mb | 59.221ms  | ±5.33%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.569mb | 89.346ms  | ±3.35%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.827mb | 38.126ms  | ±2.78%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.764mb | 43.977ms  | ±0.52%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.792mb | 46.013ms  | ±0.37%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.760mb | 44.678ms  | ±0.55%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.848mb | 43.271ms  | ±2.23%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.701mb | 69.251ms  | ±0.61%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.775mb | 43.012ms  | ±0.36%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.428mb | 38.904ms  | ±0.26%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.729mb | 41.680ms  | ±1.78%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.750mb | 46.572ms  | ±0.87%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.743mb | 45.520ms  | ±0.23%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 18.943mb | 41.788ms  | ±0.28%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.179mb | 211.639ms | ±1.76%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.398mb | 164.226ms | ±1.68%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.205mb | 53.650ms  | ±3.11%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.813mb | 113.358ms | ±0.92%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.880mb | 1.299s    | ±3.53%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.266mb | 23.812ms  | ±2.08%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.292mb | 52.329ms  | ±0.60%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.103mb | 454.458ms | ±0.63%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.549mb | 63.546ms  | ±9.36%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.269mb | 81.746ms  | ±1.48%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.038mb | 653.766ms | ±0.66%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.314mb | 17.593ms  | ±0.09%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.314mb | 41.030ms  | ±0.41%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.956mb | 277.698ms | ±0.91%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.217ms   | ±10.41% |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.639ms   | ±0.49%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.012ms   | ±0.14%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.008ms   | ±1.02%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.329ms   | ±0.73%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.976ms   | ±0.73%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.342ms   | ±0.62%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.419ms   | ±0.46%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 549.699μs | ±1.10%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.892ms   | ±1.04%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.898ms   | ±5.77%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.697ms   | ±1.27%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.541ms  | ±0.86%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.847ms   | ±0.52%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.490ms   | ±0.71%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.056ms   | ±0.85%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.341ms   | ±0.85%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.776ms   | ±0.64%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.933μs   | ±19.64% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.066ms   | ±0.74%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.503mb  | 24.638ms  | ±0.27%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.671mb | 223.198ms | ±0.79%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 47.999mb | 1.080s    | ±8.57%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.041ms   | ±0.50%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.571ms  | ±0.76%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 44.441ms  | ±0.30%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.250ms   | ±0.52%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.272ms   | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.262ms   | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.479ms   | ±0.94%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.697ms   | ±1.33%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.707ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.852ms   | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.508ms   | ±1.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.737ms   | ±0.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.836ms  | ±9.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.570ms   | ±0.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.322ms   | ±1.46%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 603.708μs | ±2.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.106ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.562ms   | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.131ms   | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 188.573ms | ±51.63% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.524ms   | ±0.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.803ms   | ±22.38% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.950ms   | ±0.61%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.002ms  | ±0.40%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.901ms  | ±0.51%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.818ms  | ±0.83%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.057ms  | ±1.08%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.121ms  | ±0.31%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 762.429μs | ±3.37%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 837.889μs | ±0.22%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 929.993μs | ±2.49%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.513ms   | ±1.66%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.229ms   | ±0.55%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.511ms  | ±1.77%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.441ms  | ±0.74%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.458ms  | ±0.33%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 60.346ms  | ±2.62%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 95.246ms  | ±0.26%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.062ms  | ±0.57%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.114ms  | ±1.03%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 19.973ms  | ±0.46%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 66.244ms  | ±0.76%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 148.125ms | ±0.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.883ms   | ±0.13%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 53.387ms  | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 175.649ms | ±26.86% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 486.288μs | ±1.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.886ms   | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.336ms   | ±0.40%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.853ms  | ±3.60%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 81.702ms  | ±0.89%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.125ms  | ±0.54%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.396ms  | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 182.577ms | ±17.17% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.848ms  | ±1.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.715ms  | ±0.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.924ms  | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.904ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.319ms  | ±0.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.082ms   | ±2.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.921ms  | ±0.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.902ms  | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.733ms  | ±0.95%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.308mb  | 9.662ms   | ±0.47%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.202mb  | 9.742ms   | ±0.56%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.353mb  | 11.387ms  | ±0.97%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.050mb  | 11.445ms  | ±0.49%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.535mb  | 10.801ms  | ±0.21%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.607mb  | 9.815ms   | ±2.85%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.852mb | 17.695ms  | ±0.66%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.752mb  | 2.990ms   | ±0.34%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 43.244μs  | ±1.71%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 234.054μs | ±0.88%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.283ms   | ±0.25%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.901ms  | ±0.42%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 45.865ms  | ±0.71%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.733ms   | ±0.17%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.804ms   | ±0.75%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.721ms   | ±0.68%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.333ms   | ±0.46%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.378ms   | ±0.61%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.166μs   | ±18.00% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.121μs   | ±35.36% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.452mb | 15.953ms  | ±2.15%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.452mb | 15.894ms  | ±0.55%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```