# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-10 17:40:51 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.721ms | 2.525ms | 2.739ms | 4.716ms | 6.926ms |
| FPDF | 797.727μs | 857.031μs | 947.468μs | 1.560ms | 2.294ms |
| TCPDF | 10.246ms | 11.264ms | 12.123ms | 19.403ms | 28.559ms |
| mPDF | 26.323ms | 29.926ms | 33.181ms | 61.354ms | 96.019ms |
| Dompdf | 11.307ms | 15.365ms | 20.106ms | 66.482ms | 149.302ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.139mb | 5.939mb | 6.025mb | 6.659mb | 7.481mb |
| FPDF | 5.072mb | 5.073mb | 5.073mb | 5.073mb | 5.083mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.682mb | 17.721mb | 18.014mb | 18.375mb |
| Dompdf | 9.357mb | 9.577mb | 9.898mb | 12.591mb | 15.954mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.414ms | 3.587ms | 3.872ms | 5.836ms | 8.282ms |
| FPDF | 1.093ms | 1.196ms | 1.278ms | 1.946ms | 2.794ms |
| TCPDF | 15.000ms | 15.854ms | 16.922ms | 25.523ms | 36.185ms |

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
| Pdf (Level 3) | 3.340ms | 4.317ms | 12.364ms |
| PdfDoc (Level 2) | 2.645ms | 3.074ms | 7.434ms |
| PdfWriter (Level 1) | 2.328ms | 2.767ms | 6.897ms |

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
| Pdf (Level 3) | 4.285ms | 11.675ms | 45.797ms |
| PdfDoc (Level 2) | 3.711ms | 9.849ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.338mb | 9.133mb | 21.541mb |
| PdfDoc (Level 2) | 6.144mb | 8.959mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 4.027ms | 11.567ms | 45.164ms |
| PdfDoc (Level 2) | 3.232ms | 7.375ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.080ms | 1.664ms | 6.076ms |
| smalot/pdfparser | 1.993ms | 2.356ms | 5.501ms |
| setasign/fpdi | 1.903ms | 2.718ms | 28.134ms |

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
| phpdftk | 2.016ms | 1.349ms |
| smalot/pdfparser | FAIL | 1.895ms |
| setasign/fpdi | 2.882ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.225ms   | ±1.64%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.664ms   | ±1.07%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.076ms   | ±0.51%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.016ms   | ±0.96%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.349ms   | ±0.86%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.993ms   | ±1.51%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.356ms   | ±0.73%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.501ms   | ±0.72%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 564.380μs | ±1.32%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.895ms   | ±0.35%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.903ms   | ±6.75%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.718ms   | ±0.64%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.134ms  | ±0.57%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.882ms   | ±0.81%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.504ms   | ±0.83%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 7.079ms   | ±0.89%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 5.363ms   | ±0.65%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.967mb  | 3.815ms   | ±0.96%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.685μs   | ±16.23% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.080ms   | ±0.45%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.953mb  | 3.602ms   | ±1.04%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.441mb  | 5.362ms   | ±0.92%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.761mb  | 5.505ms   | ±2.31%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.721mb  | 4.695ms   | ±2.75%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.184mb  | 5.153ms   | ±0.21%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.249mb  | 5.046ms   | ±0.17%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.107mb  | 8.572ms   | ±1.69%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.659mb  | 2.820ms   | ±0.55%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.866mb | 64.570ms  | ±3.42%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.725mb | 272.141ms | ±0.23%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.345mb | 1.062s    | ±1.23%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.150mb | 196.694ms | ±0.31%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.482mb | 170.299ms | ±3.09%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.586mb | 124.470ms | ±0.37%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.623mb | 165.179ms | ±0.06%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.245mb | 132.314ms | ±2.91%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.724mb | 257.662ms | ±0.42%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.325mb | 40.640ms  | ±1.84%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.311mb | 35.403ms  | ±0.54%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.241mb | 34.578ms  | ±0.47%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.476mb | 108.160ms | ±0.25%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.233mb | 37.192ms  | ±0.36%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.446mb | 46.659ms  | ±4.08%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.861mb | 67.686ms  | ±0.50%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.140mb | 29.960ms  | ±0.47%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.709mb | 37.863ms  | ±0.23%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.837mb | 168.168ms | ±0.22%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.706mb | 110.546ms | ±0.57%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.589mb | 43.698ms  | ±0.59%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.177mb | 91.022ms  | ±0.61%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.816mb | 1.030s    | ±0.38%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.742mb | 24.325ms  | ±1.81%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.768mb | 53.156ms  | ±1.03%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.579mb | 482.468ms | ±0.23%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.370mb | 64.751ms  | ±9.19%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.089mb | 82.520ms  | ±1.59%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.858mb | 665.390ms | ±0.72%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.790mb | 18.242ms  | ±0.73%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.790mb | 41.469ms  | ±0.35%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.432mb | 280.557ms | ±0.36%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.285ms   | ±1.09%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 11.675ms  | ±1.16%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.541mb | 45.797ms  | ±0.56%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.711ms   | ±0.60%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.959mb  | 9.849ms   | ±0.66%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.821ms   | ±0.50%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.546mb  | 8.290ms   | ±1.02%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.755mb  | 8.286ms   | ±0.21%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.414ms   | ±7.22%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.587ms   | ±0.41%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.872ms   | ±0.27%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.836ms   | ±0.45%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.282ms   | ±0.72%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.000ms  | ±1.31%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.854ms  | ±0.61%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.922ms  | ±1.90%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 25.523ms  | ±0.75%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 36.185ms  | ±0.74%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.093ms   | ±0.94%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.196ms   | ±0.95%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.278ms   | ±2.90%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.946ms   | ±0.70%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.794ms   | ±1.14%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 4.027ms   | ±0.44%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 11.567ms  | ±0.41%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 45.164ms  | ±0.85%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.232ms   | ±0.71%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 7.375ms   | ±0.74%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.953μs  | ±1.31%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 237.003μs | ±0.78%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.328ms   | ±1.02%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.767ms   | ±5.89%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.897ms   | ±1.09%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.645ms   | ±0.30%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.074ms   | ±0.83%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 7.434ms   | ±0.54%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.340ms   | ±11.28% |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.317ms   | ±0.95%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 12.364ms  | ±1.00%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.883mb  | 20.651ms  | ±0.75%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.869mb | 177.455ms | ±1.80%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.384mb | 896.488ms | ±1.07%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.294ms   | ±1.24%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.525ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.739ms   | ±1.31%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.716ms   | ±0.43%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.481mb  | 6.926ms   | ±0.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.455ms   | ±1.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.789ms   | ±0.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.762mb  | 13.048ms  | ±57.83% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.656ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.369ms   | ±1.14%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 599.190μs | ±2.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.113ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.576ms   | ±2.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.110mb  | 3.183ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 236.362ms | ±13.89% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.575ms   | ±1.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.839mb  | 5.912ms   | ±53.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 6.023ms   | ±0.98%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.246ms  | ±0.92%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.264ms  | ±0.95%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.123ms  | ±0.80%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.403ms  | ±0.50%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.559ms  | ±0.53%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 797.727μs | ±9.65%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 857.031μs | ±0.91%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 947.468μs | ±1.73%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.560ms   | ±1.24%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.294ms   | ±1.11%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 26.323ms  | ±3.91%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 29.926ms  | ±1.07%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.181ms  | ±0.54%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 61.354ms  | ±1.07%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 96.019ms  | ±0.23%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.307ms  | ±0.26%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.365ms  | ±0.35%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 20.106ms  | ±0.36%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 66.482ms  | ±0.39%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 149.302ms | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.040mb  | 5.007ms   | ±1.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.478mb  | 53.715ms  | ±0.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 150.857ms | ±31.46% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 468.068μs | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.923ms   | ±0.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.370ms   | ±0.71%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.837ms  | ±4.37%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 81.229ms  | ±1.09%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.078ms  | ±1.28%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.878ms  | ±2.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 353.046ms | ±27.61% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.210mb  | 14.073ms  | ±0.24%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.182mb  | 13.900ms  | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.183mb  | 14.145ms  | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.207mb  | 13.981ms  | ±0.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.311mb  | 14.693ms  | ±0.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.904ms   | ±0.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.194mb  | 14.103ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.245mb  | 14.007ms  | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.139mb  | 13.721ms  | ±0.74%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```