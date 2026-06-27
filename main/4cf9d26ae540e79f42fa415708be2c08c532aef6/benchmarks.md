# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-27 04:38:42 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.007ms | 2.523ms | 2.738ms | 4.746ms | 6.966ms |
| FPDF | 788.554μs | 885.226μs | 942.234μs | 1.495ms | 2.275ms |
| TCPDF | 9.907ms | 10.808ms | 11.918ms | 20.586ms | 31.271ms |
| mPDF | 25.146ms | 29.173ms | 33.446ms | 65.489ms | 105.241ms |
| Dompdf | 11.642ms | 16.266ms | 21.508ms | 73.545ms | 162.437ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.148mb | 5.939mb | 6.025mb | 6.659mb | 7.482mb |
| FPDF | 5.072mb | 5.073mb | 5.073mb | 5.073mb | 5.083mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.682mb | 17.721mb | 18.014mb | 18.375mb |
| Dompdf | 9.357mb | 9.577mb | 9.898mb | 12.591mb | 15.953mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.270ms | 3.494ms | 3.824ms | 5.800ms | 8.394ms |
| FPDF | 1.039ms | 1.099ms | 1.229ms | 1.881ms | 2.728ms |
| TCPDF | 16.930ms | 17.881ms | 19.256ms | 29.133ms | 41.555ms |

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
| Pdf (Level 3) | 3.271ms | 4.335ms | 12.877ms |
| PdfDoc (Level 2) | 2.619ms | 3.067ms | 7.393ms |
| PdfWriter (Level 1) | 2.269ms | 2.739ms | 6.910ms |

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
| Pdf (Level 3) | 4.303ms | 12.087ms | 47.069ms |
| PdfDoc (Level 2) | 3.707ms | 9.962ms | — |

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
| Pdf (Level 3) | 4.153ms | 11.556ms | 44.441ms |
| PdfDoc (Level 2) | 3.212ms | 7.227ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.210ms | 1.680ms | 6.048ms |
| smalot/pdfparser | 2.031ms | 2.434ms | 5.751ms |
| setasign/fpdi | 1.955ms | 2.829ms | 29.571ms |

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
| phpdftk | 2.020ms | 1.389ms |
| smalot/pdfparser | FAIL | 1.922ms |
| setasign/fpdi | 2.937ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.238ms   | ±1.52%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.680ms   | ±0.34%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.048ms   | ±0.41%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.020ms   | ±0.67%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.389ms   | ±1.03%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 2.031ms   | ±1.96%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.434ms   | ±5.18%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.751ms   | ±0.76%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 550.992μs | ±0.76%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.922ms   | ±0.36%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.955ms   | ±0.48%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.829ms   | ±0.81%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.571ms  | ±1.06%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.937ms   | ±0.59%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.504ms   | ±0.47%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 7.183ms   | ±0.90%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 5.388ms   | ±10.43% |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 3.835ms   | ±0.80%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.597μs   | ±17.80% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.210ms   | ±0.90%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 13.709mb | 50.904ms  | ±0.37%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 14.297mb | 104.849ms | ±0.42%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.784mb | 1.249s    | ±0.44%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.520mb | 26.736ms  | ±7.79%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 16.546mb | 60.212ms  | ±1.95%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 56.292mb | 557.345ms | ±0.36%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 28.148mb | 64.646ms  | ±10.20% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.802mb | 86.793ms  | ±1.62%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 31.571mb | 739.912ms | ±0.55%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.503mb | 18.778ms  | ±1.72%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.503mb | 43.776ms  | ±0.94%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.211mb | 326.076ms | ±0.11%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.303ms   | ±0.90%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 12.087ms  | ±1.03%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 47.069ms  | ±0.85%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.707ms   | ±1.17%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 9.962ms   | ±0.50%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.921mb | 75.957ms  | ±0.46%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.694mb | 330.964ms | ±0.61%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 43.313mb | 1.295s    | ±0.21%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 26.128mb | 231.147ms | ±0.44%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 30.459mb | 179.414ms | ±0.14%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 14.707mb | 146.290ms | ±0.33%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.743mb | 195.958ms | ±1.26%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 15.366mb | 160.947ms | ±1.49%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.779mb | 305.000ms | ±0.95%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 13.445mb | 47.266ms  | ±0.55%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 13.365mb | 40.804ms  | ±0.69%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 13.295mb | 38.983ms  | ±0.50%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 14.596mb | 130.457ms | ±0.16%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 13.353mb | 43.214ms  | ±0.33%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 13.566mb | 54.580ms  | ±0.45%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.981mb | 80.881ms  | ±1.47%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 13.260mb | 34.557ms  | ±0.31%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 13.678mb | 41.890ms  | ±0.57%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.806mb | 203.041ms | ±0.65%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.826mb | 164.676ms | ±1.36%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.912ms   | ±0.49%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 8.368ms   | ±0.78%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 8.352ms   | ±0.73%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.269ms   | ±0.72%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.739ms   | ±6.10%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.910ms   | ±0.93%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.619ms   | ±0.48%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.067ms   | ±0.79%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 7.393ms   | ±0.53%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.271ms   | ±1.15%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.335ms   | ±1.00%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 12.877ms  | ±2.24%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 4.153ms   | ±1.06%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 11.556ms  | ±0.97%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 44.441ms  | ±0.27%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.212ms   | ±0.95%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 7.227ms   | ±0.73%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.849μs   | ±18.25% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.786μs   | ±28.98% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.907mb | 14.490ms  | ±0.49%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.907mb | 14.267ms  | ±0.10%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.271ms   | ±1.00%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.523ms   | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.738ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.746ms   | ±1.41%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 6.966ms   | ±2.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.566ms   | ±1.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.875ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 12.376ms  | ±9.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.734ms   | ±1.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.343ms   | ±1.33%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 631.721μs | ±4.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.163ms   | ±1.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.622ms   | ±2.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 3.218ms   | ±2.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 217.476ms | ±11.13% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.558ms   | ±1.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 5.860ms   | ±34.29% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 6.028ms   | ±1.31%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.907ms   | ±0.87%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.808ms  | ±1.52%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.918ms  | ±0.85%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.586ms  | ±0.46%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.271ms  | ±0.55%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 788.554μs | ±1.73%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 885.226μs | ±3.65%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 942.234μs | ±2.14%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.495ms   | ±1.77%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.275ms   | ±0.64%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.146ms  | ±2.36%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 29.173ms  | ±0.57%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.446ms  | ±1.50%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 65.489ms  | ±0.68%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 105.241ms | ±1.19%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.642ms  | ±1.14%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.266ms  | ±3.61%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.508ms  | ±0.53%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 73.545ms  | ±0.21%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.953mb | 162.437ms | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 5.038ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.488mb  | 49.611ms  | ±0.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.334μs   | ±9.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 188.568ms | ±36.91% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 451.026μs | ±1.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 3.077ms   | ±1.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.346ms   | ±1.09%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.100ms  | ±5.84%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 84.568ms  | ±1.09%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.320ms  | ±2.40%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 24.911ms  | ±1.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 192.117ms | ±22.04% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 13.447ms  | ±0.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 13.236ms  | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 13.347ms  | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 13.395ms  | ±1.17%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 13.766ms  | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.874ms   | ±0.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 13.369ms  | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 13.280ms  | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 13.007ms  | ±0.39%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.270ms   | ±0.22%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.494ms   | ±1.79%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.824ms   | ±0.20%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.800ms   | ±0.31%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.394ms   | ±11.83% |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 16.930ms  | ±0.87%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 17.881ms  | ±0.86%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 19.256ms  | ±0.20%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 29.133ms  | ±2.97%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 41.555ms  | ±0.77%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.039ms   | ±2.14%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.099ms   | ±3.55%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.229ms   | ±0.48%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.881ms   | ±0.62%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.728ms   | ±0.69%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.129mb  | 9.842ms   | ±0.62%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.546mb  | 9.974ms   | ±0.41%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.766mb  | 11.433ms  | ±0.52%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.871mb  | 11.642ms  | ±0.39%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.290mb  | 10.704ms  | ±0.58%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.428mb  | 10.488ms  | ±2.90%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.609mb | 18.969ms  | ±0.49%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.685mb  | 2.893ms   | ±0.37%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 40.970μs  | ±0.36%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 239.354μs | ±1.00%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.227mb  | 24.379ms  | ±1.55%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.212mb | 220.685ms | ±0.61%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.728mb | 1.084s    | ±0.61%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```