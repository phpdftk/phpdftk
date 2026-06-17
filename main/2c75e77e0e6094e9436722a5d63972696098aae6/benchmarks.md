# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-17 14:37:10 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.954ms | 2.502ms | 2.756ms | 4.768ms | 7.024ms |
| FPDF | 791.950μs | 859.552μs | 952.682μs | 1.547ms | 2.273ms |
| TCPDF | 10.428ms | 11.404ms | 12.352ms | 19.895ms | 28.499ms |
| mPDF | 27.875ms | 31.221ms | 34.048ms | 62.986ms | 96.836ms |
| Dompdf | 11.905ms | 15.568ms | 20.595ms | 68.562ms | 152.538ms |

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
| phpdftk | 3.376ms | 3.634ms | 3.883ms | 5.824ms | 8.279ms |
| FPDF | 1.094ms | 1.210ms | 1.317ms | 1.973ms | 2.818ms |
| TCPDF | 17.892ms | 18.692ms | 19.861ms | 28.300ms | 39.220ms |

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
| Pdf (Level 3) | 3.293ms | 4.309ms | 12.442ms |
| PdfDoc (Level 2) | 2.637ms | 3.120ms | 7.414ms |
| PdfWriter (Level 1) | 2.348ms | 2.763ms | 6.826ms |

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
| Pdf (Level 3) | 4.263ms | 12.049ms | 46.094ms |
| PdfDoc (Level 2) | 3.789ms | 9.874ms | — |

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
| Pdf (Level 3) | 3.991ms | 11.523ms | 45.132ms |
| PdfDoc (Level 2) | 3.265ms | 7.291ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.113ms | 1.670ms | 6.042ms |
| smalot/pdfparser | 1.997ms | 2.378ms | 5.608ms |
| setasign/fpdi | 1.896ms | 2.719ms | 28.856ms |

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
| phpdftk | 2.032ms | 1.339ms |
| smalot/pdfparser | FAIL | 1.897ms |
| setasign/fpdi | 2.874ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.207ms   | ±1.47%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.670ms   | ±0.50%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.042ms   | ±0.39%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.032ms   | ±0.94%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.339ms   | ±0.81%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.997ms   | ±1.49%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.378ms   | ±0.93%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.608ms   | ±0.84%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 562.952μs | ±1.51%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.897ms   | ±2.40%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.896ms   | ±0.98%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.719ms   | ±1.43%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.856ms  | ±1.04%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.874ms   | ±1.45%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.509ms   | ±1.12%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 7.069ms   | ±0.60%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 5.541ms   | ±1.49%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 3.808ms   | ±0.68%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.632μs   | ±23.65% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.113ms   | ±1.05%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 13.165mb | 46.352ms  | ±0.37%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.753mb | 94.446ms  | ±0.16%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.397mb | 1.103s    | ±0.43%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.229mb | 24.832ms  | ±1.54%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 16.189mb | 54.476ms  | ±2.57%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 56.000mb | 489.706ms | ±2.24%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.856mb | 64.070ms  | ±9.28%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.510mb | 84.702ms  | ±0.59%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 31.279mb | 665.150ms | ±0.62%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.211mb | 18.332ms  | ±0.55%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.211mb | 42.186ms  | ±0.33%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.919mb | 279.432ms | ±0.09%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.263ms   | ±0.56%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 12.049ms  | ±0.91%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 46.094ms  | ±0.57%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.789ms   | ±1.72%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 9.874ms   | ±0.69%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.442mb | 72.515ms  | ±4.18%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.241mb | 288.369ms | ±0.11%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.861mb | 1.122s    | ±0.20%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.675mb | 210.019ms | ±0.62%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 30.006mb | 176.197ms | ±0.22%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 14.228mb | 131.617ms | ±0.10%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.265mb | 176.477ms | ±0.50%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.821mb | 141.864ms | ±0.88%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.301mb | 274.656ms | ±0.18%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.967mb | 42.838ms  | ±0.12%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.887mb | 37.172ms  | ±0.30%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.817mb | 37.018ms  | ±0.40%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 14.118mb | 115.001ms | ±0.30%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.875mb | 39.448ms  | ±0.71%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 13.088mb | 49.000ms  | ±0.56%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.503mb | 71.808ms  | ±0.40%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.782mb | 32.268ms  | ±0.64%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 13.225mb | 39.847ms  | ±0.52%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.353mb | 180.532ms | ±0.38%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.348mb | 150.652ms | ±0.39%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.888ms   | ±1.09%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 8.302ms   | ±0.94%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 8.246ms   | ±0.37%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.348ms   | ±0.51%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.763ms   | ±0.90%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.826ms   | ±0.74%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.637ms   | ±0.64%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.120ms   | ±1.05%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 7.414ms   | ±0.41%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.293ms   | ±2.01%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.309ms   | ±0.93%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 12.442ms  | ±0.26%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.991ms   | ±1.12%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 11.523ms  | ±1.78%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 45.132ms  | ±0.91%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.265ms   | ±1.02%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 7.291ms   | ±0.83%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 2.160μs   | ±14.57% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 2.146μs   | ±40.41% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.429mb | 14.259ms  | ±0.74%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.429mb | 13.857ms  | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.314ms   | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.502ms   | ±0.33%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.756ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.768ms   | ±2.05%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 7.024ms   | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.509ms   | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.811ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 13.216ms  | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.650ms   | ±4.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.380ms   | ±1.06%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 618.845μs | ±2.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.097ms   | ±0.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.546ms   | ±0.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 3.138ms   | ±1.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 203.680ms | ±18.07% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.576ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 5.886ms   | ±44.20% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 5.991ms   | ±0.88%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.428ms  | ±1.79%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.404ms  | ±1.87%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.352ms  | ±1.48%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.895ms  | ±0.83%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.499ms  | ±0.53%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 791.950μs | ±2.10%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 859.552μs | ±1.59%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 952.682μs | ±0.72%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.547ms   | ±1.18%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.273ms   | ±0.43%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 27.875ms  | ±1.10%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 31.221ms  | ±1.65%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 34.048ms  | ±0.76%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 62.986ms  | ±0.52%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 96.836ms  | ±0.48%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.905ms  | ±2.41%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.568ms  | ±0.72%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 20.595ms  | ±0.72%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 68.562ms  | ±1.12%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 152.538ms | ±1.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 4.988ms   | ±1.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.488mb  | 54.139ms  | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.678μs   | ±9.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.668μs   | ±14.81% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.678μs   | ±9.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 231.256ms | ±11.86% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 479.110μs | ±1.13%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.960ms   | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.395ms   | ±1.01%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 10.640ms  | ±6.78%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 81.912ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.319ms  | ±5.09%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.454ms  | ±3.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 270.025ms | ±25.48% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 14.342ms  | ±0.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 13.821ms  | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 14.176ms  | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 14.220ms  | ±1.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 14.510ms  | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.919ms   | ±1.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 14.198ms  | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 14.110ms  | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 13.954ms  | ±1.20%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.376ms   | ±1.28%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.634ms   | ±0.92%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.883ms   | ±0.11%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.824ms   | ±0.50%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.279ms   | ±0.57%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 17.892ms  | ±1.47%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 18.692ms  | ±1.33%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 19.861ms  | ±0.61%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 28.300ms  | ±0.27%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 39.220ms  | ±1.32%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.094ms   | ±1.27%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.210ms   | ±4.25%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.317ms   | ±1.93%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.973ms   | ±1.31%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.818ms   | ±0.72%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 7.945mb  | 9.331ms   | ±0.50%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.427mb  | 9.725ms   | ±0.68%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.647mb  | 10.913ms  | ±0.86%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.687mb  | 10.992ms  | ±1.33%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.172mb  | 10.150ms  | ±0.44%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.309mb  | 9.690ms   | ±0.85%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.490mb | 17.041ms  | ±0.68%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.685mb  | 3.022ms   | ±1.49%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 44.508μs  | ±1.46%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 244.127μs | ±0.58%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.086mb  | 21.329ms  | ±0.53%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.072mb | 184.232ms | ±1.61%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.587mb | 904.867ms | ±0.77%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```