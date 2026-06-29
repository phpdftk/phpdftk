# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-29 14:19:19 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.081ms | 2.484ms | 2.736ms | 4.726ms | 7.035ms |
| FPDF | 792.464μs | 823.523μs | 947.856μs | 1.510ms | 2.267ms |
| TCPDF | 9.846ms | 10.795ms | 11.891ms | 20.339ms | 31.068ms |
| mPDF | 24.948ms | 29.221ms | 32.718ms | 64.938ms | 104.901ms |
| Dompdf | 11.276ms | 15.995ms | 21.395ms | 73.823ms | 161.774ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.148mb | 5.938mb | 6.024mb | 6.659mb | 7.481mb |
| FPDF | 5.072mb | 5.073mb | 5.073mb | 5.073mb | 5.083mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.682mb | 17.721mb | 18.014mb | 18.375mb |
| Dompdf | 9.357mb | 9.577mb | 9.898mb | 12.591mb | 15.953mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.365ms | 3.544ms | 3.804ms | 5.752ms | 8.327ms |
| FPDF | 1.031ms | 1.102ms | 1.194ms | 1.917ms | 2.739ms |
| TCPDF | 16.901ms | 17.947ms | 19.324ms | 29.201ms | 41.220ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.365mb | 5.411mb | 5.470mb | 5.963mb | 6.562mb |
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
| Pdf (Level 3) | 3.242ms | 4.298ms | 12.549ms |
| PdfDoc (Level 2) | 2.577ms | 3.034ms | 7.358ms |
| PdfWriter (Level 1) | 2.273ms | 2.720ms | 6.827ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 5.986mb | 6.150mb | 7.826mb |
| PdfDoc (Level 2) | 5.643mb | 5.802mb | 7.370mb |
| PdfWriter (Level 1) | 5.381mb | 5.540mb | 7.115mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 4.296ms | 12.020ms | 46.714ms |
| PdfDoc (Level 2) | 3.671ms | 9.987ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.337mb | 9.133mb | 21.541mb |
| PdfDoc (Level 2) | 6.144mb | 8.959mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 3.962ms | 11.456ms | 45.006ms |
| PdfDoc (Level 2) | 3.197ms | 7.185ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.521mb | 8.965mb |
| PdfDoc (Level 2) | 5.758mb | 6.252mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.169ms | 1.670ms | 5.980ms |
| smalot/pdfparser | 1.982ms | 2.348ms | 5.791ms |
| setasign/fpdi | 1.929ms | 2.822ms | 29.701ms |

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
| phpdftk | 1.995ms | 1.353ms |
| smalot/pdfparser | FAIL | 1.908ms |
| setasign/fpdi | 3.004ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.247ms   | ±1.57%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.670ms   | ±0.82%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 5.980ms   | ±0.39%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.995ms   | ±0.61%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.353ms   | ±1.32%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.982ms   | ±0.92%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.348ms   | ±0.81%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.791ms   | ±1.04%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 553.635μs | ±1.55%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.908ms   | ±0.70%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.929ms   | ±1.06%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.822ms   | ±0.51%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.701ms  | ±0.27%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.004ms   | ±1.05%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.504ms   | ±0.66%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.951mb  | 7.248ms   | ±1.05%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.923mb  | 5.424ms   | ±0.50%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.967mb  | 3.806ms   | ±0.52%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.397μs   | ±18.73% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.169ms   | ±0.57%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 13.732mb | 50.538ms  | ±0.19%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 14.320mb | 103.957ms | ±0.14%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.807mb | 1.249s    | ±0.41%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.543mb | 26.054ms  | ±7.42%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 16.569mb | 60.080ms  | ±1.60%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 56.315mb | 525.221ms | ±0.80%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 28.171mb | 63.476ms  | ±9.33%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.825mb | 85.750ms  | ±1.35%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 32.249mb | 733.068ms | ±0.65%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.525mb | 18.739ms  | ±0.30%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.525mb | 42.921ms  | ±0.59%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.233mb | 324.337ms | ±0.61%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.337mb  | 4.296ms   | ±7.18%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 12.020ms  | ±0.68%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.541mb | 46.714ms  | ±1.23%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.671ms   | ±2.26%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.959mb  | 9.987ms   | ±2.71%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.943mb | 75.874ms  | ±0.41%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.716mb | 330.301ms | ±0.27%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 43.336mb | 1.293s    | ±1.26%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 26.150mb | 232.633ms | ±0.44%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 30.482mb | 180.193ms | ±0.17%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 14.729mb | 146.433ms | ±0.46%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.766mb | 195.492ms | ±0.31%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 15.389mb | 159.883ms | ±0.56%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.801mb | 310.764ms | ±0.30%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 13.468mb | 46.966ms  | ±0.41%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 13.388mb | 40.458ms  | ±0.45%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 13.318mb | 38.900ms  | ±0.04%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 14.619mb | 131.053ms | ±0.63%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 13.376mb | 42.788ms  | ±0.67%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 13.588mb | 54.316ms  | ±0.48%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 14.004mb | 80.462ms  | ±0.71%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 13.283mb | 34.414ms  | ±0.24%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 13.700mb | 41.833ms  | ±0.24%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.829mb | 201.877ms | ±0.16%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.849mb | 165.425ms | ±0.92%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.879ms   | ±0.61%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.546mb  | 8.307ms   | ±1.06%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.755mb  | 8.457ms   | ±1.05%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.273ms   | ±0.63%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.720ms   | ±1.14%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.827ms   | ±0.82%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.643mb  | 2.577ms   | ±0.77%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.034ms   | ±0.98%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.370mb  | 7.358ms   | ±0.64%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.986mb  | 3.242ms   | ±0.64%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.298ms   | ±0.57%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.826mb  | 12.549ms  | ±0.45%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.962ms   | ±0.58%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.521mb  | 11.456ms  | ±1.00%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.965mb  | 45.006ms  | ±0.58%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.758mb  | 3.197ms   | ±0.94%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.252mb  | 7.185ms   | ±0.55%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.933μs   | ±19.89% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.819μs   | ±38.54% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.930mb | 14.373ms  | ±1.05%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.930mb | 14.264ms  | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.273ms   | ±1.32%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.938mb  | 2.484ms   | ±1.09%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.024mb  | 2.736ms   | ±3.14%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.726ms   | ±1.22%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.481mb  | 7.035ms   | ±0.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.280mb  | 3.463ms   | ±0.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.797ms   | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 12.317ms  | ±12.45% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.323mb  | 3.681ms   | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.666mb  | 2.359ms   | ±0.22%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 683.613μs | ±53.95% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.147ms   | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.612ms   | ±0.94%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.110mb  | 3.202ms   | ±1.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.166mb  | 229.033ms | ±22.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.227mb  | 3.574ms   | ±1.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.842mb  | 5.802ms   | ±15.31% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.974mb  | 5.983ms   | ±0.93%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.846ms   | ±0.65%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.795ms  | ±0.43%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.891ms  | ±9.12%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.339ms  | ±0.42%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.068ms  | ±1.03%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 792.464μs | ±39.66% |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 823.523μs | ±11.87% |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 947.856μs | ±14.45% |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.510ms   | ±0.99%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.267ms   | ±0.25%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 24.948ms  | ±1.63%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 29.221ms  | ±0.70%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.718ms  | ±0.75%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 64.938ms  | ±0.27%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 104.901ms | ±0.44%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.276ms  | ±0.60%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.995ms  | ±0.29%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.395ms  | ±0.69%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 73.823ms  | ±0.60%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.953mb | 161.774ms | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.040mb  | 5.032ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.487mb  | 49.499ms  | ±1.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.344μs   | ±11.13% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.344μs   | ±11.13% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 181.723ms | ±32.91% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 447.921μs | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.454mb  | 2.947ms   | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.355ms   | ±0.61%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.399ms  | ±4.91%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 83.138ms  | ±0.62%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.515ms  | ±0.77%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.161ms  | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.887mb  | 168.725ms | ±12.48% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 13.363ms  | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.190mb  | 13.218ms  | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.199mb  | 13.320ms  | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.215mb  | 13.418ms  | ±0.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 13.830ms  | ±0.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.987mb  | 2.886ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.202mb  | 13.253ms  | ±0.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.253mb  | 13.422ms  | ±0.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 13.081ms  | ±1.39%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.365ms   | ±0.85%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.411mb  | 3.544ms   | ±1.00%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.470mb  | 3.804ms   | ±0.36%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.963mb  | 5.752ms   | ±0.26%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.327ms   | ±0.44%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 16.901ms  | ±0.23%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 17.947ms  | ±0.26%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 19.324ms  | ±1.16%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 29.201ms  | ±0.70%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 41.220ms  | ±1.82%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.031ms   | ±3.30%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.102ms   | ±3.06%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.194ms   | ±1.29%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.917ms   | ±0.28%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.739ms   | ±0.20%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.127mb  | 9.897ms   | ±0.42%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.545mb  | 9.954ms   | ±0.48%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.764mb  | 11.521ms  | ±0.25%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.870mb  | 11.669ms  | ±0.90%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.289mb  | 10.821ms  | ±0.75%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.426mb  | 9.984ms   | ±0.31%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.607mb | 18.864ms  | ±0.42%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.685mb  | 2.883ms   | ±2.26%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.362μs  | ±2.93%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.497mb  | 242.507μs | ±0.92%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.227mb  | 24.295ms  | ±0.72%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.212mb | 219.209ms | ±1.16%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.728mb | 1.080s    | ±0.46%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```