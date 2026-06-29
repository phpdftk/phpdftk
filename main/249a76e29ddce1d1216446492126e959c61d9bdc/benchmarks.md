# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-29 15:03:51 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 11.085ms | 2.176ms | 2.349ms | 4.313ms | 6.256ms |
| FPDF | 708.003μs | 763.768μs | 843.903μs | 1.421ms | 2.165ms |
| TCPDF | 9.410ms | 10.351ms | 11.534ms | 19.148ms | 29.210ms |
| mPDF | 24.614ms | 26.200ms | 30.161ms | 56.431ms | 88.665ms |
| Dompdf | 10.094ms | 14.268ms | 18.695ms | 61.784ms | 137.334ms |

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
| phpdftk | 2.850ms | 3.039ms | 3.226ms | 5.089ms | 7.297ms |
| FPDF | 983.954μs | 1.022ms | 1.152ms | 1.800ms | 2.595ms |
| TCPDF | 15.487ms | 16.497ms | 17.554ms | 26.442ms | 37.210ms |

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
| Pdf (Level 3) | 2.825ms | 3.841ms | 10.985ms |
| PdfDoc (Level 2) | 2.342ms | 2.622ms | 6.487ms |
| PdfWriter (Level 1) | 2.030ms | 2.399ms | 5.983ms |

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
| Pdf (Level 3) | 3.791ms | 10.464ms | 41.500ms |
| PdfDoc (Level 2) | 3.237ms | 8.770ms | — |

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
| Pdf (Level 3) | 3.426ms | 9.835ms | 37.758ms |
| PdfDoc (Level 2) | 2.797ms | 6.238ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.521mb | 8.965mb |
| PdfDoc (Level 2) | 5.758mb | 6.252mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.006ms | 1.388ms | 4.986ms |
| smalot/pdfparser | 1.816ms | 2.118ms | 5.133ms |
| setasign/fpdi | 1.715ms | 2.378ms | 24.517ms |

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
| phpdftk | 1.673ms | 1.138ms |
| smalot/pdfparser | FAIL | 1.746ms |
| setasign/fpdi | 2.509ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.034ms   | ±2.37%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.388ms   | ±0.86%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 4.986ms   | ±0.62%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.673ms   | ±0.45%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.138ms   | ±0.73%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.816ms   | ±0.96%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.118ms   | ±0.45%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.133ms   | ±1.23%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 516.695μs | ±0.70%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.746ms   | ±0.35%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.715ms   | ±1.54%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.378ms   | ±0.40%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 24.517ms  | ±0.30%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.509ms   | ±0.55%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.321ms   | ±1.08%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.951mb  | 6.163ms   | ±0.54%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.923mb  | 4.630ms   | ±0.98%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.967mb  | 3.288ms   | ±0.78%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.085μs   | ±26.76% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 5.006ms   | ±0.54%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 13.732mb | 40.831ms  | ±0.24%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 14.320mb | 83.217ms  | ±0.27%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.807mb | 990.005ms | ±0.48%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.543mb | 22.898ms  | ±7.68%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 16.569mb | 49.342ms  | ±0.84%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 56.315mb | 443.443ms | ±0.72%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 28.171mb | 56.541ms  | ±8.27%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.825mb | 74.614ms  | ±1.77%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 32.249mb | 623.537ms | ±0.22%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.525mb | 23.180ms  | ±87.34% |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.525mb | 38.192ms  | ±0.15%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.233mb | 286.897ms | ±0.03%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.337mb  | 3.791ms   | ±0.83%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 10.464ms  | ±0.09%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.541mb | 41.500ms  | ±0.57%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.237ms   | ±1.39%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.959mb  | 8.770ms   | ±3.14%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.943mb | 60.886ms  | ±0.23%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.716mb | 260.647ms | ±0.17%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 43.336mb | 1.020s    | ±0.19%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 26.150mb | 184.031ms | ±0.42%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 30.482mb | 145.436ms | ±0.18%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 14.729mb | 115.373ms | ±0.40%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.766mb | 155.146ms | ±0.10%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 15.389mb | 127.279ms | ±0.17%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.801mb | 241.057ms | ±0.16%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 13.468mb | 37.584ms  | ±0.59%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 13.388mb | 32.891ms  | ±0.62%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 13.318mb | 31.783ms  | ±2.06%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 14.619mb | 103.116ms | ±0.18%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 13.376mb | 34.552ms  | ±0.10%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 13.588mb | 44.001ms  | ±0.75%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 14.004mb | 65.047ms  | ±0.55%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 13.283mb | 28.036ms  | ±0.14%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 13.700mb | 33.889ms  | ±0.65%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.829mb | 160.924ms | ±0.13%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.849mb | 130.644ms | ±0.53%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 7.604ms   | ±0.62%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.546mb  | 7.156ms   | ±0.75%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.755mb  | 7.326ms   | ±0.33%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.030ms   | ±0.68%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.399ms   | ±1.44%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 5.983ms   | ±0.88%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.643mb  | 2.342ms   | ±1.48%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 2.622ms   | ±1.63%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.370mb  | 6.487ms   | ±1.40%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.986mb  | 2.825ms   | ±0.47%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 3.841ms   | ±0.65%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.826mb  | 10.985ms  | ±0.33%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.426ms   | ±0.64%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.521mb  | 9.835ms   | ±1.47%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.965mb  | 37.758ms  | ±0.48%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.758mb  | 2.797ms   | ±1.19%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.252mb  | 6.238ms   | ±0.52%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.200μs   | ±13.61% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.061μs   | ±35.36% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.930mb | 12.440ms  | ±0.32%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.930mb | 12.427ms  | ±0.08%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 1.976ms   | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.938mb  | 2.176ms   | ±0.32%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.024mb  | 2.349ms   | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.313ms   | ±1.30%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.481mb  | 6.256ms   | ±0.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.280mb  | 3.135ms   | ±0.32%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.353ms   | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 10.413ms  | ±13.43% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.323mb  | 3.257ms   | ±0.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.666mb  | 2.006ms   | ±0.46%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 538.558μs | ±3.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 2.824ms   | ±1.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.202ms   | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.110mb  | 2.864ms   | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.166mb  | 237.935ms | ±24.31% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.227mb  | 3.134ms   | ±1.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.842mb  | 4.976ms   | ±13.11% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.974mb  | 5.114ms   | ±6.20%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.410ms   | ±0.56%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.351ms  | ±2.77%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.534ms  | ±2.00%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.148ms  | ±1.49%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 29.210ms  | ±0.55%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 708.003μs | ±2.31%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 763.768μs | ±3.24%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 843.903μs | ±1.35%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.421ms   | ±1.04%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.165ms   | ±2.89%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 24.614ms  | ±1.46%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 26.200ms  | ±2.53%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 30.161ms  | ±1.95%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 56.431ms  | ±0.70%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 88.665ms  | ±1.37%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 10.094ms  | ±0.77%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 14.268ms  | ±0.55%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 18.695ms  | ±1.94%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 61.784ms  | ±0.65%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.953mb | 137.334ms | ±0.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.040mb  | 4.352ms   | ±0.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.487mb  | 40.866ms  | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.989μs   | ±18.84% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 0.689μs   | ±34.99% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 0.999μs   | ±14.29% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 151.911ms | ±25.09% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 404.721μs | ±0.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.454mb  | 2.573ms   | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.048ms   | ±1.05%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 6.999ms   | ±5.27%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 69.296ms  | ±1.25%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.578ms  | ±0.50%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 21.001ms  | ±2.21%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.887mb  | 146.083ms | ±29.97% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 11.379ms  | ±0.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.190mb  | 11.278ms  | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.199mb  | 11.559ms  | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.215mb  | 11.385ms  | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 11.828ms  | ±0.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.987mb  | 2.501ms   | ±1.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.202mb  | 11.618ms  | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.253mb  | 11.309ms  | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 11.085ms  | ±0.78%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 2.850ms   | ±1.75%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.411mb  | 3.039ms   | ±0.25%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.470mb  | 3.226ms   | ±0.51%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.963mb  | 5.089ms   | ±0.81%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 7.297ms   | ±0.70%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.487ms  | ±0.47%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.497ms  | ±0.19%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.554ms  | ±0.20%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.442ms  | ±0.32%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 37.210ms  | ±0.16%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 983.954μs | ±3.52%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.022ms   | ±0.70%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.152ms   | ±2.24%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.800ms   | ±0.90%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.595ms   | ±0.75%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.127mb  | 8.755ms   | ±0.46%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.545mb  | 8.788ms   | ±0.41%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.764mb  | 10.341ms  | ±0.58%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.870mb  | 10.556ms  | ±1.01%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.289mb  | 9.493ms   | ±0.23%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.426mb  | 8.887ms   | ±0.34%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.607mb | 16.316ms  | ±0.74%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.685mb  | 2.557ms   | ±0.50%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 35.600μs  | ±0.61%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.497mb  | 212.583μs | ±0.79%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.227mb  | 19.115ms  | ±0.27%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.212mb | 170.789ms | ±0.48%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.728mb | 846.366ms | ±0.38%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```