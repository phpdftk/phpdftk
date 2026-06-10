# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-10 18:05:08 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 11.274ms | 2.163ms | 2.363ms | 4.258ms | 6.202ms |
| FPDF | 715.773μs | 770.806μs | 846.225μs | 1.448ms | 2.149ms |
| TCPDF | 9.631ms | 10.472ms | 11.360ms | 19.044ms | 28.635ms |
| mPDF | 23.703ms | 26.810ms | 30.096ms | 55.850ms | 89.515ms |
| Dompdf | 10.339ms | 14.236ms | 18.859ms | 62.425ms | 139.563ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.139mb | 5.939mb | 6.025mb | 6.659mb | 7.481mb |
| FPDF | 5.072mb | 5.073mb | 5.073mb | 5.073mb | 5.083mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.682mb | 17.721mb | 18.014mb | 18.375mb |
| Dompdf | 9.357mb | 9.577mb | 9.898mb | 12.591mb | 15.954mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.873ms | 3.065ms | 3.321ms | 5.095ms | 7.256ms |
| FPDF | 964.082μs | 1.037ms | 1.124ms | 1.779ms | 2.597ms |
| TCPDF | 16.085ms | 16.878ms | 17.948ms | 26.353ms | 37.875ms |

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
| Pdf (Level 3) | 2.881ms | 3.975ms | 10.989ms |
| PdfDoc (Level 2) | 2.298ms | 2.684ms | 6.606ms |
| PdfWriter (Level 1) | 1.996ms | 2.393ms | 5.977ms |

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
| Pdf (Level 3) | 3.840ms | 10.502ms | 40.993ms |
| PdfDoc (Level 2) | 3.271ms | 8.757ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.338mb | 9.133mb | 21.541mb |
| PdfDoc (Level 2) | 6.144mb | 8.959mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 3.451ms | 9.824ms | 37.480ms |
| PdfDoc (Level 2) | 2.791ms | 6.246ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.036ms | 1.391ms | 5.007ms |
| smalot/pdfparser | 1.825ms | 2.142ms | 5.195ms |
| setasign/fpdi | 1.734ms | 2.412ms | 24.644ms |

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
| phpdftk | 1.709ms | 1.140ms |
| smalot/pdfparser | FAIL | 1.731ms |
| setasign/fpdi | 2.540ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.041ms   | ±0.83%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.391ms   | ±0.70%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 5.007ms   | ±0.59%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.709ms   | ±0.28%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.140ms   | ±1.87%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.825ms   | ±1.27%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.142ms   | ±0.74%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.195ms   | ±0.66%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 517.440μs | ±1.92%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.731ms   | ±1.12%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.734ms   | ±1.62%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.412ms   | ±0.36%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 24.644ms  | ±0.50%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.540ms   | ±0.61%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.343ms   | ±0.55%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 6.179ms   | ±0.24%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 4.659ms   | ±0.58%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.967mb  | 3.298ms   | ±1.53%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.186μs   | ±24.40% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 5.036ms   | ±0.74%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.589mb | 37.185ms  | ±0.38%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.177mb | 74.510ms  | ±0.63%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.816mb | 860.340ms | ±0.15%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.742mb | 23.151ms  | ±1.82%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.768mb | 50.360ms  | ±0.81%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.579mb | 444.649ms | ±0.38%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.370mb | 56.469ms  | ±8.27%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.089mb | 74.234ms  | ±1.51%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.858mb | 627.479ms | ±0.13%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.790mb | 17.233ms  | ±0.72%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.790mb | 38.337ms  | ±0.30%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.432mb | 288.556ms | ±0.32%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 3.840ms   | ±0.73%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 10.502ms  | ±0.36%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.541mb | 40.993ms  | ±0.51%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.271ms   | ±0.42%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.959mb  | 8.757ms   | ±0.39%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.866mb | 54.012ms  | ±2.18%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.725mb | 231.136ms | ±0.44%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.345mb | 901.686ms | ±0.08%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.150mb | 167.708ms | ±0.50%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.482mb | 138.503ms | ±0.36%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.586mb | 103.430ms | ±0.26%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.623mb | 139.733ms | ±0.39%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.245mb | 111.199ms | ±0.42%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.724mb | 216.004ms | ±0.09%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.325mb | 33.916ms  | ±0.30%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.311mb | 29.747ms  | ±0.82%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.241mb | 29.066ms  | ±0.31%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.476mb | 90.871ms  | ±0.18%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.233mb | 31.603ms  | ±0.23%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.446mb | 39.556ms  | ±1.11%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.861mb | 56.761ms  | ±0.22%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.140mb | 25.289ms  | ±0.24%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.709mb | 31.494ms  | ±0.11%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.837mb | 141.421ms | ±0.03%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.706mb | 90.223ms  | ±0.51%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 7.712ms   | ±0.39%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.546mb  | 7.254ms   | ±0.60%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.755mb  | 7.162ms   | ±0.69%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 1.996ms   | ±1.36%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.393ms   | ±1.92%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 5.977ms   | ±1.23%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.298ms   | ±1.74%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 2.684ms   | ±0.51%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 6.606ms   | ±0.60%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 2.881ms   | ±0.34%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 3.975ms   | ±0.54%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 10.989ms  | ±0.26%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.451ms   | ±0.64%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 9.824ms   | ±0.80%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 37.480ms  | ±0.18%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 2.791ms   | ±1.09%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 6.246ms   | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 1.978ms   | ±1.17%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.163ms   | ±0.78%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.363ms   | ±0.17%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.258ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.481mb  | 6.202ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.184ms   | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.418ms   | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.762mb  | 10.569ms  | ±18.95% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.327ms   | ±0.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.047ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 552.072μs | ±9.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 2.904ms   | ±0.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.247ms   | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.110mb  | 2.888ms   | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 274.459ms | ±15.94% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.222ms   | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.839mb  | 5.043ms   | ±1.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 5.192ms   | ±0.40%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.631ms   | ±0.49%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.472ms  | ±0.54%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.360ms  | ±0.42%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.044ms  | ±0.67%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.635ms  | ±0.31%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 715.773μs | ±4.38%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 770.806μs | ±1.31%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 846.225μs | ±1.49%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.448ms   | ±1.25%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.149ms   | ±0.54%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 23.703ms  | ±1.50%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 26.810ms  | ±0.93%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 30.096ms  | ±1.02%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 55.850ms  | ±0.45%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 89.515ms  | ±0.80%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 10.339ms  | ±0.44%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 14.236ms  | ±0.65%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 18.859ms  | ±0.62%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 62.425ms  | ±0.24%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 139.563ms | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.040mb  | 4.414ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.478mb  | 41.035ms  | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.870μs   | ±26.73% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.191μs   | ±29.81% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.130μs   | ±23.39% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 199.461ms | ±18.39% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 392.925μs | ±1.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.612ms   | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.049ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 7.557ms   | ±8.59%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 69.213ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.630ms  | ±0.09%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.307ms  | ±0.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 190.146ms | ±28.98% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.210mb  | 11.442ms  | ±0.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.182mb  | 11.463ms  | ±0.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.183mb  | 11.575ms  | ±0.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.207mb  | 11.437ms  | ±0.34%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.311mb  | 12.263ms  | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.566ms   | ±1.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.194mb  | 11.462ms  | ±0.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.245mb  | 11.391ms  | ±0.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.139mb  | 11.274ms  | ±0.70%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 2.873ms   | ±2.54%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.065ms   | ±0.33%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.321ms   | ±0.57%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.095ms   | ±0.62%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 7.256ms   | ±0.67%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 16.085ms  | ±0.95%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.878ms  | ±1.10%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.948ms  | ±0.75%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.353ms  | ±0.15%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 37.875ms  | ±0.76%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 964.082μs | ±0.95%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.037ms   | ±1.20%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.124ms   | ±1.07%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.779ms   | ±0.91%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.597ms   | ±0.33%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.953mb  | 3.181ms   | ±0.47%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.441mb  | 5.071ms   | ±0.12%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.761mb  | 5.119ms   | ±0.24%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.721mb  | 4.309ms   | ±0.57%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.184mb  | 4.900ms   | ±1.15%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.249mb  | 4.789ms   | ±0.52%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.107mb  | 8.290ms   | ±0.25%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.659mb  | 2.516ms   | ±0.32%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 35.834μs  | ±1.05%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 212.616μs | ±0.58%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.883mb  | 16.825ms  | ±0.11%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.869mb | 149.600ms | ±0.04%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.384mb | 737.883ms | ±0.31%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```