# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-13 07:09:00 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.741ms | 2.444ms | 2.701ms | 4.643ms | 6.838ms |
| FPDF | 770.305μs | 828.390μs | 910.902μs | 1.515ms | 2.222ms |
| TCPDF | 10.011ms | 10.814ms | 11.782ms | 19.077ms | 28.124ms |
| mPDF | 25.529ms | 28.924ms | 32.561ms | 60.260ms | 94.626ms |
| Dompdf | 11.114ms | 15.150ms | 19.941ms | 66.305ms | 149.422ms |

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
| phpdftk | 3.243ms | 3.450ms | 3.734ms | 5.672ms | 8.152ms |
| FPDF | 1.055ms | 1.119ms | 1.223ms | 1.903ms | 2.733ms |
| TCPDF | 17.007ms | 18.092ms | 19.141ms | 27.447ms | 38.007ms |

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
| Pdf (Level 3) | 3.246ms | 4.227ms | 12.104ms |
| PdfDoc (Level 2) | 2.563ms | 3.008ms | 7.206ms |
| PdfWriter (Level 1) | 2.239ms | 2.702ms | 6.807ms |

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
| Pdf (Level 3) | 4.156ms | 11.713ms | 45.497ms |
| PdfDoc (Level 2) | 3.616ms | 9.691ms | — |

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
| Pdf (Level 3) | 3.957ms | 11.365ms | 44.678ms |
| PdfDoc (Level 2) | 3.144ms | 7.140ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.060ms | 1.629ms | 6.044ms |
| smalot/pdfparser | 1.999ms | 2.314ms | 5.466ms |
| setasign/fpdi | 1.867ms | 2.665ms | 28.485ms |

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
| phpdftk | 2.003ms | 1.319ms |
| smalot/pdfparser | FAIL | 1.863ms |
| setasign/fpdi | 2.875ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.217ms   | ±3.19%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.629ms   | ±0.84%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.044ms   | ±0.38%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.003ms   | ±1.08%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.319ms   | ±2.53%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.999ms   | ±0.78%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.314ms   | ±0.49%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.466ms   | ±0.52%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 544.000μs | ±0.77%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.863ms   | ±1.03%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.867ms   | ±0.87%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.665ms   | ±0.52%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.485ms  | ±1.14%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.875ms   | ±0.32%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.485ms   | ±1.00%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 7.054ms   | ±0.38%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 5.314ms   | ±0.65%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 3.801ms   | ±6.35%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.873μs   | ±13.47% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.060ms   | ±1.01%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.764mb | 44.282ms  | ±0.70%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.352mb | 89.780ms  | ±0.39%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.937mb | 1.037s    | ±0.13%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.837mb | 23.653ms  | ±2.62%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.863mb | 51.479ms  | ±0.71%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.674mb | 462.483ms | ±0.64%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.465mb | 62.765ms  | ±10.11% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.184mb | 83.346ms  | ±1.12%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.953mb | 650.942ms | ±0.26%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.885mb | 19.616ms  | ±53.06% |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.885mb | 40.680ms  | ±0.10%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.527mb | 273.356ms | ±0.11%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.156ms   | ±0.19%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 11.713ms  | ±2.33%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 45.497ms  | ±0.22%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.616ms   | ±0.58%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 9.691ms   | ±0.46%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.041mb | 64.918ms  | ±0.44%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.846mb | 275.757ms | ±0.22%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.466mb | 1.079s    | ±0.24%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.280mb | 198.385ms | ±0.35%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.612mb | 169.606ms | ±0.56%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.826mb | 125.466ms | ±0.34%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.864mb | 166.813ms | ±0.89%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.420mb | 133.336ms | ±0.46%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.899mb | 260.341ms | ±0.52%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.500mb | 40.296ms  | ±0.57%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.486mb | 35.422ms  | ±0.18%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.416mb | 34.556ms  | ±0.55%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.651mb | 108.519ms | ±0.62%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.474mb | 37.134ms  | ±0.73%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.686mb | 46.767ms  | ±0.40%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.102mb | 67.905ms  | ±0.19%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.381mb | 30.150ms  | ±0.49%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.830mb | 37.725ms  | ±0.29%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.958mb | 169.747ms | ±0.43%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.946mb | 110.617ms | ±0.35%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.580ms   | ±0.62%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 8.148ms   | ±1.13%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 8.112ms   | ±0.93%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.239ms   | ±3.17%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.702ms   | ±1.16%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.807ms   | ±0.73%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.563ms   | ±0.82%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.008ms   | ±0.83%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 7.206ms   | ±0.69%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.246ms   | ±9.97%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.227ms   | ±0.43%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 12.104ms  | ±0.64%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.957ms   | ±1.61%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 11.365ms  | ±1.29%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 44.678ms  | ±0.51%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.144ms   | ±0.30%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 7.140ms   | ±0.64%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 2.049μs   | ±16.64% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 2.097μs   | ±29.77% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 11.962mb | 13.124ms  | ±0.15%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 11.962mb | 13.095ms  | ±0.23%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.252ms   | ±1.13%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.444ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.701ms   | ±0.96%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.643ms   | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 6.838ms   | ±0.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.355ms   | ±0.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.721ms   | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 12.966ms  | ±9.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.574ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.301ms   | ±0.92%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 612.836μs | ±2.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.071ms   | ±3.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.496ms   | ±0.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 3.125ms   | ±1.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 234.570ms | ±20.64% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.454ms   | ±0.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 5.711ms   | ±24.35% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 5.920ms   | ±0.85%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.011ms  | ±0.69%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.814ms  | ±0.50%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.782ms  | ±0.38%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.077ms  | ±0.35%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.124ms  | ±0.34%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 770.305μs | ±1.99%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 828.390μs | ±1.68%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 910.902μs | ±0.56%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.515ms   | ±2.17%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.222ms   | ±5.12%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.529ms  | ±5.81%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 28.924ms  | ±0.83%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.561ms  | ±0.85%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 60.260ms  | ±0.21%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 94.626ms  | ±0.35%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.114ms  | ±0.32%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.150ms  | ±0.58%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 19.941ms  | ±0.25%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 66.305ms  | ±0.46%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 149.422ms | ±0.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 4.876ms   | ±0.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.486mb  | 54.083ms  | ±1.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 257.553ms | ±22.91% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 468.712μs | ±0.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.888ms   | ±0.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.285ms   | ±0.80%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 11.719ms  | ±6.03%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 82.241ms  | ±0.70%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.103ms  | ±0.95%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.275ms  | ±1.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 169.530ms | ±37.28% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 13.986ms  | ±3.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 13.799ms  | ±1.06%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 13.989ms  | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 14.013ms  | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 14.410ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.831ms   | ±0.94%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 13.840ms  | ±0.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 14.038ms  | ±0.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 13.741ms  | ±2.47%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.243ms   | ±0.26%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.450ms   | ±1.17%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.734ms   | ±0.19%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.672ms   | ±0.78%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.152ms   | ±0.28%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 17.007ms  | ±1.38%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 18.092ms  | ±1.63%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 19.141ms  | ±0.20%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 27.447ms  | ±0.07%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 38.007ms  | ±0.40%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.055ms   | ±0.98%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.119ms   | ±1.29%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.223ms   | ±0.27%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.903ms   | ±0.58%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.733ms   | ±0.48%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 7.770mb  | 8.376ms   | ±0.23%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.252mb  | 8.807ms   | ±0.29%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.494mb  | 9.990ms   | ±0.34%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.518mb  | 9.986ms   | ±0.60%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 8.996mb  | 9.282ms   | ±0.56%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.134mb  | 8.861ms   | ±0.36%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 9.987mb  | 15.638ms  | ±0.23%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.665mb  | 2.807ms   | ±0.36%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 44.202μs  | ±1.38%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 233.902μs | ±0.66%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.912mb  | 20.527ms  | ±0.99%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.898mb | 182.998ms | ±0.24%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.413mb | 897.143ms | ±3.04%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```