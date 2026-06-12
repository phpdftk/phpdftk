# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-12 19:39:09 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 11.463ms | 2.240ms | 2.450ms | 4.341ms | 6.371ms |
| FPDF | 750.125μs | 830.412μs | 884.509μs | 1.457ms | 2.220ms |
| TCPDF | 10.142ms | 10.982ms | 11.945ms | 19.630ms | 29.338ms |
| mPDF | 24.556ms | 27.929ms | 31.253ms | 57.340ms | 89.994ms |
| Dompdf | 10.666ms | 14.567ms | 19.007ms | 62.299ms | 138.337ms |

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
| phpdftk | 2.981ms | 3.128ms | 3.422ms | 5.225ms | 7.515ms |
| FPDF | 1.001ms | 1.057ms | 1.166ms | 1.835ms | 2.662ms |
| TCPDF | 16.508ms | 17.408ms | 18.531ms | 27.188ms | 38.051ms |

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
| Pdf (Level 3) | 2.955ms | 4.110ms | 11.190ms |
| PdfDoc (Level 2) | 2.364ms | 2.782ms | 6.756ms |
| PdfWriter (Level 1) | 2.059ms | 2.442ms | 6.193ms |

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
| Pdf (Level 3) | 3.957ms | 10.849ms | 42.567ms |
| PdfDoc (Level 2) | 3.372ms | 9.134ms | — |

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
| Pdf (Level 3) | 3.544ms | 10.132ms | 38.022ms |
| PdfDoc (Level 2) | 2.830ms | 6.394ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.028ms | 1.412ms | 4.923ms |
| smalot/pdfparser | 1.897ms | 2.203ms | 5.308ms |
| setasign/fpdi | 1.753ms | 2.458ms | 23.928ms |

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
| phpdftk | 1.709ms | 1.181ms |
| smalot/pdfparser | FAIL | 1.806ms |
| setasign/fpdi | 2.557ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.075ms   | ±1.31%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.412ms   | ±0.75%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 4.923ms   | ±0.36%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.709ms   | ±0.67%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.181ms   | ±0.98%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.897ms   | ±1.64%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.203ms   | ±0.70%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.308ms   | ±0.59%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 510.049μs | ±1.46%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.806ms   | ±0.80%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.753ms   | ±3.99%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.458ms   | ±0.80%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 23.928ms  | ±0.40%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.557ms   | ±0.50%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.375ms   | ±0.89%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 6.332ms   | ±0.81%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 4.830ms   | ±0.80%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 3.423ms   | ±0.92%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.533μs   | ±33.10% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 5.028ms   | ±1.40%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.713mb | 38.058ms  | ±7.01%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.301mb | 75.380ms  | ±0.39%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.886mb | 855.700ms | ±0.29%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.788mb | 23.451ms  | ±3.24%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.814mb | 51.099ms  | ±1.10%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.625mb | 460.333ms | ±0.64%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.415mb | 58.012ms  | ±9.53%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.135mb | 76.024ms  | ±1.40%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.904mb | 641.095ms | ±0.74%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.835mb | 18.464ms  | ±1.76%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.835mb | 39.324ms  | ±0.27%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.478mb | 290.031ms | ±1.38%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 3.957ms   | ±0.51%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 10.849ms  | ±1.13%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 42.567ms  | ±0.56%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.372ms   | ±0.62%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 9.134ms   | ±0.94%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.990mb | 54.623ms  | ±0.30%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.796mb | 229.429ms | ±0.34%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.416mb | 900.050ms | ±0.17%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.229mb | 167.507ms | ±0.28%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.560mb | 139.693ms | ±0.12%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.776mb | 103.372ms | ±0.44%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.813mb | 139.607ms | ±0.40%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.369mb | 111.152ms | ±0.14%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.849mb | 216.330ms | ±0.35%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.450mb | 34.323ms  | ±0.72%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.435mb | 30.329ms  | ±0.25%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.365mb | 29.556ms  | ±0.13%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.601mb | 91.264ms  | ±0.05%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.358mb | 32.037ms  | ±0.30%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.636mb | 39.143ms  | ±0.13%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.051mb | 56.695ms  | ±0.39%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.330mb | 25.716ms  | ±0.32%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.780mb | 31.991ms  | ±0.15%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.908mb | 141.584ms | ±0.26%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.896mb | 91.873ms  | ±0.26%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 7.829ms   | ±0.75%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 7.426ms   | ±0.99%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 7.247ms   | ±0.69%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.059ms   | ±2.53%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.442ms   | ±0.81%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.193ms   | ±0.48%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.364ms   | ±1.15%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 2.782ms   | ±7.05%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 6.756ms   | ±9.56%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 2.955ms   | ±1.17%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.110ms   | ±0.81%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 11.190ms  | ±0.68%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.544ms   | ±3.58%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 10.132ms  | ±0.55%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 38.022ms  | ±1.42%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 2.830ms   | ±1.01%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 6.394ms   | ±0.34%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.159μs   | ±19.69% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.219μs   | ±51.89% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 11.912mb | 11.799ms  | ±0.17%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 11.912mb | 11.772ms  | ±1.33%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.064ms   | ±1.26%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.240ms   | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.450ms   | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.341ms   | ±0.55%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 6.371ms   | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.315ms   | ±0.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.558ms   | ±1.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 10.696ms  | ±20.93% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.449ms   | ±1.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.127ms   | ±1.00%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 531.740μs | ±3.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 2.930ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.310ms   | ±3.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 2.959ms   | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 257.365ms | ±21.70% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.297ms   | ±0.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 5.177ms   | ±27.35% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 5.268ms   | ±0.95%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.142ms  | ±1.00%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.982ms  | ±1.13%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.945ms  | ±1.31%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.630ms  | ±1.47%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 29.338ms  | ±0.35%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 750.125μs | ±5.45%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 830.412μs | ±3.17%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 884.509μs | ±0.63%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.457ms   | ±0.98%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.220ms   | ±0.90%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 24.556ms  | ±1.50%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 27.929ms  | ±1.19%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 31.253ms  | ±0.59%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 57.340ms  | ±0.53%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 89.994ms  | ±0.20%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 10.666ms  | ±0.77%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 14.567ms  | ±0.46%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 19.007ms  | ±0.69%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 62.299ms  | ±0.85%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 138.337ms | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 4.540ms   | ±7.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.486mb  | 41.031ms  | ±0.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.333μs   | ±10.53% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 0.999μs   | ±21.08% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 0.710μs   | ±30.77% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 236.064ms | ±24.11% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 393.010μs | ±7.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.621ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.147ms   | ±0.89%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 7.097ms   | ±9.09%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 69.445ms  | ±1.31%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.603ms  | ±0.33%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.232ms  | ±0.20%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 184.832ms | ±12.09% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 11.730ms  | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 11.498ms  | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 11.761ms  | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 11.630ms  | ±0.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 12.030ms  | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.588ms   | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 11.826ms  | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 11.591ms  | ±0.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 11.463ms  | ±0.89%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 2.981ms   | ±0.94%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.128ms   | ±1.49%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.422ms   | ±1.30%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.225ms   | ±1.42%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 7.515ms   | ±0.75%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 16.508ms  | ±1.66%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 17.408ms  | ±1.54%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 18.531ms  | ±0.71%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 27.188ms  | ±0.28%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 38.051ms  | ±0.82%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.001ms   | ±1.24%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.057ms   | ±0.62%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.166ms   | ±0.19%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.835ms   | ±0.48%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.662ms   | ±0.53%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 7.762mb  | 8.047ms   | ±0.92%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.244mb  | 8.731ms   | ±1.35%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.485mb  | 9.541ms   | ±1.19%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.510mb  | 9.714ms   | ±0.35%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 8.988mb  | 8.808ms   | ±1.09%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.125mb  | 8.818ms   | ±0.59%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 9.978mb  | 15.453ms  | ±0.52%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.665mb  | 2.594ms   | ±0.82%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 35.557μs  | ±0.95%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 214.404μs | ±0.44%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.904mb  | 16.849ms  | ±0.12%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.889mb | 148.459ms | ±0.23%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.405mb | 737.406ms | ±0.37%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```