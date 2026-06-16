# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-16 23:06:28 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 14.845ms | 2.753ms | 2.825ms | 4.950ms | 7.322ms |
| FPDF | 916.369μs | 924.731μs | 1.030ms | 1.585ms | 2.293ms |
| TCPDF | 11.904ms | 12.448ms | 13.323ms | 20.957ms | 30.122ms |
| mPDF | 29.137ms | 32.900ms | 36.793ms | 66.215ms | 105.230ms |
| Dompdf | 14.292ms | 19.385ms | 25.089ms | 72.078ms | 166.313ms |

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
| phpdftk | 3.402ms | 3.663ms | 3.890ms | 6.042ms | 8.663ms |
| FPDF | 1.126ms | 1.211ms | 1.475ms | 2.248ms | 2.892ms |
| TCPDF | 19.455ms | 20.518ms | 21.742ms | 30.289ms | 40.737ms |

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
| Pdf (Level 3) | 3.485ms | 4.504ms | 12.736ms |
| PdfDoc (Level 2) | 2.750ms | 3.316ms | 7.870ms |
| PdfWriter (Level 1) | 2.399ms | 2.866ms | 7.125ms |

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
| Pdf (Level 3) | 4.502ms | 12.366ms | 47.188ms |
| PdfDoc (Level 2) | 4.095ms | 10.742ms | — |

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
| Pdf (Level 3) | 4.271ms | 11.857ms | 45.716ms |
| PdfDoc (Level 2) | 3.492ms | 7.447ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.387ms | 1.716ms | 6.211ms |
| smalot/pdfparser | 2.137ms | 2.565ms | 6.046ms |
| setasign/fpdi | 2.020ms | 2.909ms | 29.721ms |

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
| phpdftk | 2.087ms | 1.387ms |
| smalot/pdfparser | FAIL | 1.961ms |
| setasign/fpdi | 3.011ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.278ms   | ±1.77%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.716ms   | ±1.71%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.211ms   | ±21.30% |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.087ms   | ±1.40%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.387ms   | ±1.97%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 2.137ms   | ±2.91%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.565ms   | ±0.51%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 6.046ms   | ±3.38%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 566.519μs | ±0.68%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.961ms   | ±2.69%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 2.020ms   | ±0.64%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.909ms   | ±1.33%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.721ms  | ±4.69%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.011ms   | ±1.02%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.595ms   | ±1.78%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 7.484ms   | ±1.13%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 5.807ms   | ±13.13% |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 4.134ms   | ±1.46%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 4.819μs   | ±20.59% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.387ms   | ±1.72%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.992mb | 46.110ms  | ±1.22%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.580mb | 94.520ms  | ±3.39%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.230mb | 1.063s    | ±0.84%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.091mb | 28.092ms  | ±1.37%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 16.051mb | 60.058ms  | ±3.73%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.862mb | 527.379ms | ±0.96%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.718mb | 69.339ms  | ±10.00% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.372mb | 91.097ms  | ±0.97%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 31.141mb | 746.699ms | ±3.13%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.073mb | 20.117ms  | ±0.85%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.073mb | 44.228ms  | ±0.31%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.715mb | 287.735ms | ±1.20%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.502ms   | ±1.72%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 12.366ms  | ±1.10%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 47.188ms  | ±0.53%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 4.095ms   | ±3.22%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 10.742ms  | ±0.84%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.269mb | 68.981ms  | ±0.87%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.074mb | 288.526ms | ±0.89%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.694mb | 1.115s    | ±0.55%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.508mb | 218.943ms | ±3.72%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.839mb | 182.936ms | ±1.46%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 14.056mb | 133.112ms | ±1.70%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.092mb | 176.423ms | ±0.89%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.648mb | 137.441ms | ±0.96%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.128mb | 267.035ms | ±0.73%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.794mb | 42.600ms  | ±0.84%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.714mb | 37.155ms  | ±0.64%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.644mb | 37.263ms  | ±1.60%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.945mb | 112.247ms | ±3.93%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.702mb | 39.238ms  | ±0.08%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.915mb | 48.789ms  | ±1.14%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.330mb | 71.604ms  | ±3.65%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.609mb | 31.661ms  | ±0.96%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 13.058mb | 39.895ms  | ±0.54%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.186mb | 177.104ms | ±0.34%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.175mb | 114.070ms | ±0.54%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 9.144ms   | ±0.40%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 8.651ms   | ±0.87%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 8.723ms   | ±15.68% |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.399ms   | ±3.65%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.866ms   | ±1.15%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 7.125ms   | ±0.72%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.750ms   | ±2.52%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.316ms   | ±1.73%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 7.870ms   | ±0.66%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.485ms   | ±2.39%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.504ms   | ±1.89%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 12.736ms  | ±0.59%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 4.271ms   | ±2.40%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 11.857ms  | ±1.85%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 45.716ms  | ±0.52%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.492ms   | ±3.11%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 7.447ms   | ±1.67%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 2.333μs   | ±16.66% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 2.182μs   | ±47.14% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.256mb | 15.218ms  | ±0.33%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.256mb | 15.121ms  | ±0.02%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.456ms   | ±3.02%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.753ms   | ±2.05%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.825ms   | ±2.11%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.950ms   | ±2.31%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 7.322ms   | ±1.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.692ms   | ±3.34%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.951ms   | ±0.94%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 13.519ms  | ±1.32%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.873ms   | ±2.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.538ms   | ±1.40%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 676.219μs | ±4.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.159ms   | ±1.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.756ms   | ±2.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 3.357ms   | ±1.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 204.348ms | ±42.05% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.798ms   | ±1.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 6.328ms   | ±24.95% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 6.572ms   | ±0.57%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 11.904ms  | ±1.47%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 12.448ms  | ±2.82%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 13.323ms  | ±2.19%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.957ms  | ±0.49%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 30.122ms  | ±0.24%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 916.369μs | ±4.61%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 924.731μs | ±3.21%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 1.030ms   | ±2.72%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.585ms   | ±1.86%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.293ms   | ±1.97%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 29.137ms  | ±1.67%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 32.900ms  | ±0.71%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 36.793ms  | ±0.59%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 66.215ms  | ±1.27%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 105.230ms | ±0.90%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 14.292ms  | ±3.62%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 19.385ms  | ±2.35%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 25.089ms  | ±3.71%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 72.078ms  | ±3.33%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 166.313ms | ±3.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 5.582ms   | ±3.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.488mb  | 56.566ms  | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.710μs   | ±14.29% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 2.404μs   | ±21.96% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.957μs   | ±29.11% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 200.926ms | ±29.26% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 480.138μs | ±1.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 3.006ms   | ±3.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.402ms   | ±0.99%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.460ms  | ±7.05%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 82.187ms  | ±1.50%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.153ms  | ±1.31%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.098ms  | ±1.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 200.274ms | ±37.09% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 15.059ms  | ±1.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 14.864ms  | ±1.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 14.785ms  | ±7.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 15.236ms  | ±1.16%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 15.821ms  | ±1.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 3.380ms   | ±1.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 15.621ms  | ±1.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 15.207ms  | ±0.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 14.845ms  | ±1.67%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.402ms   | ±1.07%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.663ms   | ±1.25%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.890ms   | ±1.10%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 6.042ms   | ±0.54%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.663ms   | ±0.31%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 19.455ms  | ±0.21%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 20.518ms  | ±2.02%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 21.742ms  | ±0.37%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 30.289ms  | ±0.88%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 40.737ms  | ±0.08%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.126ms   | ±1.33%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.211ms   | ±3.50%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.475ms   | ±5.47%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 2.248ms   | ±3.53%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.892ms   | ±4.04%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 7.798mb  | 9.894ms   | ±1.58%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.280mb  | 10.848ms  | ±0.18%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.517mb  | 12.326ms  | ±0.63%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.540mb  | 11.883ms  | ±1.36%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.024mb  | 11.322ms  | ±1.99%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.162mb  | 10.539ms  | ±3.32%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.015mb | 18.730ms  | ±0.94%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.685mb  | 3.372ms   | ±4.88%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 45.177μs  | ±1.55%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 255.333μs | ±2.90%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.939mb  | 21.803ms  | ±1.30%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.925mb | 188.660ms | ±0.79%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.440mb | 914.182ms | ±1.18%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```