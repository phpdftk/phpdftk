# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-13 13:10:28 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 11.767ms | 2.299ms | 2.527ms | 4.509ms | 6.513ms |
| FPDF | 807.080μs | 816.671μs | 907.228μs | 1.479ms | 2.233ms |
| TCPDF | 10.330ms | 11.351ms | 12.275ms | 19.964ms | 29.200ms |
| mPDF | 25.220ms | 28.454ms | 31.939ms | 58.556ms | 91.041ms |
| Dompdf | 11.002ms | 15.421ms | 20.148ms | 63.980ms | 140.946ms |

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
| phpdftk | 3.031ms | 3.253ms | 3.471ms | 5.338ms | 7.644ms |
| FPDF | 1.028ms | 1.106ms | 1.213ms | 1.907ms | 2.752ms |
| TCPDF | 16.850ms | 18.033ms | 19.308ms | 27.641ms | 38.914ms |

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
| Pdf (Level 3) | 3.092ms | 4.276ms | 11.558ms |
| PdfDoc (Level 2) | 2.440ms | 2.849ms | 6.947ms |
| PdfWriter (Level 1) | 2.178ms | 2.525ms | 6.288ms |

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
| Pdf (Level 3) | 4.050ms | 10.994ms | 42.625ms |
| PdfDoc (Level 2) | 3.451ms | 9.175ms | — |

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
| Pdf (Level 3) | 3.684ms | 10.265ms | 38.647ms |
| PdfDoc (Level 2) | 2.965ms | 6.570ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.100ms | 1.436ms | 4.953ms |
| smalot/pdfparser | 1.907ms | 2.261ms | 5.347ms |
| setasign/fpdi | 1.764ms | 2.477ms | 23.983ms |

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
| phpdftk | 1.757ms | 1.183ms |
| smalot/pdfparser | FAIL | 1.816ms |
| setasign/fpdi | 2.593ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.078ms   | ±0.92%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.436ms   | ±26.50% |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 4.953ms   | ±1.12%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.757ms   | ±1.05%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.183ms   | ±0.93%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.907ms   | ±1.87%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.261ms   | ±3.64%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.347ms   | ±0.45%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 532.091μs | ±1.70%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.816ms   | ±0.89%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.764ms   | ±0.62%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.477ms   | ±0.80%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 23.983ms  | ±0.95%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.593ms   | ±1.16%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.428ms   | ±0.60%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 6.395ms   | ±0.63%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 4.860ms   | ±0.87%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 3.514ms   | ±1.27%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.819μs   | ±26.98% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 5.100ms   | ±0.56%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.849mb | 38.081ms  | ±0.57%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.437mb | 76.229ms  | ±0.34%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.088mb | 863.260ms | ±0.57%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.893mb | 24.262ms  | ±3.00%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.919mb | 52.381ms  | ±0.94%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.730mb | 463.034ms | ±0.20%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.521mb | 59.817ms  | ±9.68%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.240mb | 77.471ms  | ±1.09%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 31.009mb | 650.035ms | ±0.25%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.940mb | 18.764ms  | ±0.95%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.940mb | 39.825ms  | ±0.19%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.583mb | 294.362ms | ±0.76%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.050ms   | ±1.03%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 10.994ms  | ±0.65%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 42.625ms  | ±0.21%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.451ms   | ±0.48%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 9.175ms   | ±0.50%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.126mb | 55.243ms  | ±0.50%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.932mb | 230.391ms | ±1.19%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.551mb | 894.634ms | ±0.27%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.366mb | 170.434ms | ±0.55%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.697mb | 140.727ms | ±0.69%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.912mb | 105.438ms | ±0.15%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.949mb | 140.117ms | ±0.59%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.505mb | 111.945ms | ±0.06%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.985mb | 217.203ms | ±0.29%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.651mb | 35.025ms  | ±0.07%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.571mb | 30.664ms  | ±0.24%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.501mb | 30.138ms  | ±0.52%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.802mb | 92.481ms  | ±0.31%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.559mb | 32.799ms  | ±0.85%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.771mb | 40.073ms  | ±0.27%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.187mb | 57.593ms  | ±0.33%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.466mb | 26.451ms  | ±0.29%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.916mb | 32.519ms  | ±0.65%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.043mb | 142.895ms | ±0.27%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.032mb | 93.136ms  | ±0.79%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.011ms   | ±0.58%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 7.488ms   | ±0.56%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 7.478ms   | ±0.88%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.178ms   | ±3.64%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.525ms   | ±0.60%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.288ms   | ±0.58%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.440ms   | ±2.18%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 2.849ms   | ±1.11%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 6.947ms   | ±0.56%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.092ms   | ±0.49%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.276ms   | ±0.89%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 11.558ms  | ±0.60%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.684ms   | ±0.62%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 10.265ms  | ±0.57%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 38.647ms  | ±0.87%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 2.965ms   | ±1.60%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 6.570ms   | ±0.55%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.049μs   | ±29.77% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.279μs   | ±85.60% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.113mb | 12.361ms  | ±0.09%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.113mb | 12.416ms  | ±2.32%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.131ms   | ±1.02%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.299ms   | ±3.63%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.527ms   | ±0.45%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.509ms   | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 6.513ms   | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.343ms   | ±1.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.610ms   | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 10.941ms  | ±14.61% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.566ms   | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.158ms   | ±0.51%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 565.721μs | ±71.17% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.048ms   | ±1.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.436ms   | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 3.099ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 239.387ms | ±38.87% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.371ms   | ±2.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 5.269ms   | ±15.75% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 5.362ms   | ±1.01%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.330ms  | ±3.10%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.351ms  | ±0.81%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.275ms  | ±1.01%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.964ms  | ±0.78%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 29.200ms  | ±0.91%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 807.080μs | ±4.23%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 816.671μs | ±1.47%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 907.228μs | ±2.94%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.479ms   | ±1.20%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.233ms   | ±1.22%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.220ms  | ±2.01%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 28.454ms  | ±0.34%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 31.939ms  | ±0.55%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 58.556ms  | ±0.35%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 91.041ms  | ±0.34%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.002ms  | ±1.57%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.421ms  | ±6.79%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 20.148ms  | ±0.24%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 63.980ms  | ±0.45%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 140.946ms | ±0.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 4.756ms   | ±6.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.486mb  | 41.287ms  | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.204μs   | ±19.69% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.011μs   | ±14.41% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.344μs   | ±11.13% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 196.798ms | ±28.19% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 402.945μs | ±1.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.690ms   | ±1.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.238ms   | ±0.46%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 7.969ms   | ±6.26%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 69.235ms  | ±1.03%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.691ms  | ±0.57%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.349ms  | ±1.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 187.904ms | ±23.03% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 12.121ms  | ±5.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 11.874ms  | ±0.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 12.062ms  | ±1.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 12.011ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 12.131ms  | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.651ms   | ±0.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 12.079ms  | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 11.851ms  | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 11.767ms  | ±1.72%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.031ms   | ±1.43%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.253ms   | ±0.95%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.471ms   | ±0.37%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.338ms   | ±1.07%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 7.644ms   | ±0.82%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 16.850ms  | ±0.87%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 18.033ms  | ±0.47%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 19.308ms  | ±1.31%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 27.641ms  | ±0.32%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 38.914ms  | ±0.13%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.028ms   | ±1.73%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.106ms   | ±0.14%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.213ms   | ±1.54%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.907ms   | ±0.29%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.752ms   | ±0.93%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 7.770mb  | 8.408ms   | ±0.43%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.252mb  | 8.962ms   | ±0.57%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.494mb  | 9.956ms   | ±0.35%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.518mb  | 10.005ms  | ±0.69%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 8.996mb  | 8.999ms   | ±0.35%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.134mb  | 8.994ms   | ±0.69%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 9.987mb  | 15.720ms  | ±0.62%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.665mb  | 2.699ms   | ±0.71%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 36.040μs  | ±0.63%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 217.472μs | ±0.62%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.919mb  | 16.938ms  | ±0.10%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.904mb | 147.969ms | ±0.32%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.420mb | 735.212ms | ±0.13%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```