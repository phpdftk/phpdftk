# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-25 00:31:43 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.614ms | 1.933ms | 2.111ms | 3.645ms | 5.350ms |
| FPDF | 643.922μs | 680.049μs | 1.183ms | 1.197ms | 1.796ms |
| TCPDF | 7.838ms | 9.619ms | 9.235ms | 15.026ms | 21.986ms |
| mPDF | 21.680ms | 22.808ms | 25.674ms | 47.070ms | 73.736ms |
| Dompdf | 8.578ms | 11.865ms | 15.493ms | 51.539ms | 115.724ms |

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
| phpdftk | 2.576ms | 6.138ms | 10.042ms | 4.467ms | 6.392ms |
| FPDF | 829.935μs | 880.639μs | 968.314μs | 1.514ms | 2.138ms |
| TCPDF | 15.565ms | 14.227ms | 14.944ms | 21.544ms | 29.844ms |

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
| Pdf (Level 3) | 2.959ms | 4.139ms | 10.314ms |
| PdfDoc (Level 2) | 2.013ms | 2.589ms | 5.678ms |
| PdfWriter (Level 1) | 1.839ms | 2.155ms | 5.639ms |

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
| Pdf (Level 3) | 3.709ms | 10.861ms | 35.999ms |
| PdfDoc (Level 2) | 4.281ms | 22.616ms | — |

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
| Pdf (Level 3) | 3.080ms | 8.959ms | 35.173ms |
| PdfDoc (Level 2) | 2.557ms | 5.601ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.805ms | 1.281ms | 4.708ms |
| smalot/pdfparser | 1.556ms | 1.816ms | 4.289ms |
| setasign/fpdi | 1.472ms | 2.095ms | 22.093ms |

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
| phpdftk | 1.563ms | 1.030ms |
| smalot/pdfparser | FAIL | 1.480ms |
| setasign/fpdi | 2.226ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 962.109μs | ±3.82%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.281ms   | ±0.89%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 4.708ms   | ±0.30%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.563ms   | ±0.87%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.030ms   | ±1.14%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.556ms   | ±0.90%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 1.816ms   | ±0.63%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.289ms   | ±0.57%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 441.916μs | ±0.55%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.480ms   | ±0.45%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.472ms   | ±0.61%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.095ms   | ±0.37%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 22.093ms  | ±0.31%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.226ms   | ±0.88%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.165ms   | ±1.49%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 5.460ms   | ±1.14%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 4.126ms   | ±0.29%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 2.969ms   | ±0.58%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.073μs   | ±16.64%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.805ms   | ±1.09%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 13.670mb | 36.937ms  | ±0.31%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 14.192mb | 75.274ms  | ±0.30%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.745mb | 905.722ms | ±1.79%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.481mb | 21.347ms  | ±58.88%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 16.441mb | 40.935ms  | ±6.23%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 56.253mb | 363.370ms | ±0.53%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 28.109mb | 57.914ms  | ±39.39%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.763mb | 63.779ms  | ±1.52%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 31.532mb | 511.462ms | ±3.15%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.463mb | 13.765ms  | ±0.74%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.463mb | 32.023ms  | ±0.20%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.171mb | 215.385ms | ±0.30%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 3.709ms   | ±125.75% |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 10.861ms  | ±39.47%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 35.999ms  | ±0.36%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 4.281ms   | ±103.33% |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 22.616ms  | ±50.73%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.881mb | 55.171ms  | ±0.47%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.654mb | 231.833ms | ±0.40%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 43.274mb | 913.581ms | ±0.37%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 26.088mb | 165.346ms | ±1.61%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 30.420mb | 136.535ms | ±0.42%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 14.667mb | 105.208ms | ±0.45%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.704mb | 141.845ms | ±1.15%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 15.261mb | 113.216ms | ±0.04%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.740mb | 219.964ms | ±0.19%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 13.406mb | 34.059ms  | ±0.20%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 13.326mb | 29.583ms  | ±0.56%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 13.256mb | 28.739ms  | ±1.42%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 14.557mb | 91.954ms  | ±0.36%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 13.314mb | 30.758ms  | ±0.90%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 13.526mb | 39.109ms  | ±0.43%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.942mb | 57.615ms  | ±0.38%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 13.221mb | 25.186ms  | ±0.18%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 13.638mb | 30.896ms  | ±0.10%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.767mb | 144.490ms | ±0.30%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.787mb | 122.326ms | ±1.38%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 6.797ms   | ±0.98%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 6.408ms   | ±0.60%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 6.412ms   | ±28.43%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 1.839ms   | ±148.06% |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.155ms   | ±20.11%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 5.639ms   | ±33.38%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.013ms   | ±2.17%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 2.589ms   | ±43.58%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 5.678ms   | ±1.63%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 2.959ms   | ±32.35%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.139ms   | ±84.79%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 10.314ms  | ±36.27%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.080ms   | ±19.17%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 8.959ms   | ±0.41%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 35.173ms  | ±1.33%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 2.557ms   | ±46.24%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 5.601ms   | ±0.39%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.559μs   | ±14.97%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.597μs   | ±35.59%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.868mb | 11.001ms  | ±0.33%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.868mb | 11.004ms  | ±0.45%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 1.860ms   | ±123.04% |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 1.933ms   | ±8.50%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.111ms   | ±4.80%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 3.645ms   | ±2.16%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 5.350ms   | ±13.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.288ms   | ±111.75% |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 2.879ms   | ±0.54%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 10.002ms  | ±9.77%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 5.233ms   | ±91.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.701ms   | ±52.70%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 3.305ms   | ±127.75% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 2.788ms   | ±73.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 2.790ms   | ±119.72% |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 2.461ms   | ±44.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 231.526ms | ±37.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.612ms   | ±88.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 9.055ms   | ±98.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 4.631ms   | ±19.86%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.838ms   | ±0.11%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 9.619ms   | ±44.81%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.235ms   | ±25.18%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 15.026ms  | ±3.26%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 21.986ms  | ±29.01%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 643.922μs | ±22.22%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 680.049μs | ±69.14%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 1.183ms   | ±42.20%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.197ms   | ±0.66%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 1.796ms   | ±15.76%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 21.680ms  | ±4.94%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 22.808ms  | ±19.23%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 25.674ms  | ±50.93%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 47.070ms  | ±0.51%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 73.736ms  | ±0.17%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 8.578ms   | ±0.75%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 11.865ms  | ±0.72%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 15.493ms  | ±17.75%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 51.539ms  | ±0.31%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 115.724ms | ±0.34%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 6.482ms   | ±94.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.488mb  | 41.554ms  | ±1.05%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.333μs   | ±10.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.344μs   | ±11.13%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.011μs   | ±14.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 136.071ms | ±29.72%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 361.585μs | ±0.86%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.245ms   | ±0.20%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 2.642ms   | ±126.18% |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 9.085ms   | ±45.10%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 63.021ms  | ±1.32%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.578ms  | ±1.75%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.203ms  | ±0.80%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 184.257ms | ±10.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 10.816ms  | ±3.19%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 10.716ms  | ±6.76%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 10.895ms  | ±0.96%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 10.797ms  | ±0.53%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 11.066ms  | ±0.58%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.466ms   | ±54.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 10.792ms  | ±0.62%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 10.736ms  | ±13.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 10.614ms  | ±51.33%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 2.576ms   | ±5.88%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 6.138ms   | ±123.52% |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 10.042ms  | ±77.84%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 4.467ms   | ±0.52%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 6.392ms   | ±0.53%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.565ms  | ±67.94%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 14.227ms  | ±0.61%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 14.944ms  | ±0.16%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 21.544ms  | ±0.27%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 29.844ms  | ±0.66%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 829.935μs | ±2.90%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 880.639μs | ±0.45%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 968.314μs | ±1.42%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.514ms   | ±1.16%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.138ms   | ±0.56%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.129mb  | 7.207ms   | ±0.39%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.546mb  | 7.308ms   | ±0.34%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.766mb  | 8.532ms   | ±0.57%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.871mb  | 8.419ms   | ±0.10%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.290mb  | 8.267ms   | ±0.58%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.428mb  | 7.371ms   | ±0.26%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.609mb | 13.313ms  | ±0.28%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.685mb  | 2.263ms   | ±1.01%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 32.988μs  | ±0.90%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 183.142μs | ±0.23%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.209mb  | 17.435ms  | ±0.41%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.194mb | 153.845ms | ±0.86%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.710mb | 760.240ms | ±0.60%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```