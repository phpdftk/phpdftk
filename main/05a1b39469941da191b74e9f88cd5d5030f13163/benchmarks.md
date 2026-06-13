# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-13 07:33:35 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.773ms | 1.930ms | 2.090ms | 3.642ms | 5.379ms |
| FPDF | 615.001μs | 659.500μs | 751.869μs | 1.200ms | 1.770ms |
| TCPDF | 7.832ms | 8.517ms | 9.175ms | 14.907ms | 21.935ms |
| mPDF | 19.856ms | 22.403ms | 25.398ms | 46.742ms | 73.615ms |
| Dompdf | 8.600ms | 14.202ms | 15.486ms | 51.269ms | 115.053ms |

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
| phpdftk | 2.501ms | 2.699ms | 2.920ms | 4.427ms | 6.360ms |
| FPDF | 852.340μs | 880.831μs | 968.246μs | 1.492ms | 2.128ms |
| TCPDF | 13.258ms | 14.400ms | 14.915ms | 21.437ms | 29.447ms |

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
| Pdf (Level 3) | 2.531ms | 3.292ms | 9.428ms |
| PdfDoc (Level 2) | 1.986ms | 2.355ms | 5.624ms |
| PdfWriter (Level 1) | 1.759ms | 2.088ms | 5.340ms |

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
| Pdf (Level 3) | 3.252ms | 9.133ms | 35.668ms |
| PdfDoc (Level 2) | 2.846ms | 7.610ms | — |

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
| Pdf (Level 3) | 3.058ms | 8.876ms | 34.797ms |
| PdfDoc (Level 2) | 2.429ms | 5.566ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.781ms | 1.267ms | 4.760ms |
| smalot/pdfparser | 1.535ms | 1.800ms | 4.311ms |
| setasign/fpdi | 1.442ms | 2.076ms | 22.257ms |

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
| phpdftk | 1.550ms | 1.035ms |
| smalot/pdfparser | FAIL | 1.457ms |
| setasign/fpdi | 2.220ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 934.998μs | ±1.12%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.267ms   | ±1.41%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 4.760ms   | ±0.84%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.550ms   | ±1.71%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.035ms   | ±0.67%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.535ms   | ±1.85%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 1.800ms   | ±0.57%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.311ms   | ±0.87%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 420.978μs | ±0.87%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.457ms   | ±0.80%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.442ms   | ±0.36%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.076ms   | ±0.50%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 22.257ms  | ±0.25%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.220ms   | ±1.20%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.148ms   | ±2.44%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 5.481ms   | ±0.69%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 4.126ms   | ±0.51%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 2.916ms   | ±0.76%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.061μs   | ±14.14% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.781ms   | ±1.72%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.781mb | 41.339ms  | ±63.37% |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.369mb | 69.344ms  | ±1.27%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.954mb | 800.575ms | ±0.28%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.851mb | 18.421ms  | ±2.35%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.877mb | 40.369ms  | ±1.01%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.688mb | 367.406ms | ±0.08%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.479mb | 48.952ms  | ±9.79%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.198mb | 63.683ms  | ±1.26%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.967mb | 512.033ms | ±0.20%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.899mb | 13.843ms  | ±0.78%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.899mb | 32.017ms  | ±0.87%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.541mb | 214.483ms | ±0.80%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 3.252ms   | ±0.84%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 9.133ms   | ±0.31%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 35.668ms  | ±0.55%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 2.846ms   | ±0.58%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 7.610ms   | ±0.52%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.058mb | 49.692ms  | ±0.42%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.864mb | 216.209ms | ±0.69%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.483mb | 830.888ms | ±0.29%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.298mb | 153.569ms | ±0.65%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.629mb | 132.272ms | ±0.31%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.844mb | 96.801ms  | ±0.56%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.881mb | 129.232ms | ±0.62%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.437mb | 103.501ms | ±0.32%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.917mb | 200.900ms | ±0.10%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.583mb | 31.545ms  | ±1.25%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.503mb | 27.515ms  | ±0.38%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.433mb | 26.712ms  | ±0.43%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.669mb | 84.211ms  | ±0.23%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.491mb | 28.900ms  | ±0.26%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.703mb | 36.270ms  | ±0.28%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.119mb | 53.251ms  | ±0.53%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.398mb | 23.367ms  | ±0.37%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.848mb | 29.434ms  | ±0.45%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.975mb | 131.301ms | ±0.68%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.964mb | 86.042ms  | ±0.63%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 6.691ms   | ±1.26%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 6.350ms   | ±0.54%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 6.312ms   | ±0.35%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 1.759ms   | ±0.95%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.088ms   | ±0.95%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 5.340ms   | ±2.09%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 1.986ms   | ±0.46%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 2.355ms   | ±56.91% |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 5.624ms   | ±0.73%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 2.531ms   | ±22.67% |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 3.292ms   | ±0.61%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 9.428ms   | ±0.45%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.058ms   | ±0.66%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 8.876ms   | ±0.96%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 34.797ms  | ±0.46%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 2.429ms   | ±0.68%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 5.566ms   | ±0.34%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.560μs   | ±19.61% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.685μs   | ±31.93% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 11.980mb | 10.193ms  | ±0.62%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 11.980mb | 10.197ms  | ±0.09%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 1.726ms   | ±1.43%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 1.930ms   | ±93.63% |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.090ms   | ±1.08%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 3.642ms   | ±8.90%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 5.379ms   | ±1.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 2.640ms   | ±0.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 2.870ms   | ±1.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 10.102ms  | ±84.07% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 2.785ms   | ±85.21% |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 1.795ms   | ±6.38%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 460.089μs | ±2.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 2.451ms   | ±1.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 2.736ms   | ±6.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 2.445ms   | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 125.791ms | ±20.77% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 2.700ms   | ±45.95% |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 4.454ms   | ±82.06% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 4.581ms   | ±0.77%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.832ms   | ±0.62%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 8.517ms   | ±0.59%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.175ms   | ±0.62%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 14.907ms  | ±0.71%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 21.935ms  | ±0.16%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 615.001μs | ±29.46% |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 659.500μs | ±0.78%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 751.869μs | ±13.30% |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.200ms   | ±37.42% |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 1.770ms   | ±1.63%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 19.856ms  | ±1.81%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 22.403ms  | ±0.66%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 25.398ms  | ±0.39%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 46.742ms  | ±0.51%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 73.615ms  | ±0.40%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 8.600ms   | ±1.42%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 14.202ms  | ±65.74% |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 15.486ms  | ±0.64%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 51.269ms  | ±0.70%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 115.053ms | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 3.831ms   | ±0.24%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.486mb  | 41.911ms  | ±0.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.333μs   | ±15.81% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.305μs   | ±28.33% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 161.336ms | ±24.56% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 369.821μs | ±1.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.260ms   | ±0.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 2.559ms   | ±1.02%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 8.342ms   | ±0.68%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 64.396ms  | ±0.89%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.734ms  | ±3.59%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.458ms  | ±1.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 129.083ms | ±34.27% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 10.831ms  | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 10.693ms  | ±0.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 10.778ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 10.893ms  | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 11.085ms  | ±1.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.217ms   | ±1.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 10.836ms  | ±0.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 10.715ms  | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 10.773ms  | ±1.16%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 2.501ms   | ±1.94%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 2.699ms   | ±0.27%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 2.920ms   | ±0.15%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 4.427ms   | ±0.95%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 6.360ms   | ±0.66%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 13.258ms  | ±0.37%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 14.400ms  | ±15.42% |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 14.915ms  | ±0.23%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 21.437ms  | ±0.28%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 29.447ms  | ±0.49%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 852.340μs | ±2.36%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 880.831μs | ±2.23%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 968.246μs | ±0.97%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.492ms   | ±0.18%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.128ms   | ±0.43%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 7.770mb  | 6.563ms   | ±0.33%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.252mb  | 6.875ms   | ±0.48%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.494mb  | 7.707ms   | ±0.38%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.518mb  | 7.744ms   | ±0.70%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 8.996mb  | 7.330ms   | ±0.77%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.134mb  | 6.894ms   | ±0.65%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 9.987mb  | 12.130ms  | ±0.15%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.665mb  | 2.181ms   | ±0.46%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 34.088μs  | ±0.88%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 181.094μs | ±0.67%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.918mb  | 15.960ms  | ±0.66%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.904mb | 141.019ms | ±0.65%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.419mb | 701.469ms | ±0.37%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```