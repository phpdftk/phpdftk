# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-10 17:34:59 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.643ms | 2.483ms | 2.706ms | 4.690ms | 6.860ms |
| FPDF | 774.973μs | 864.817μs | 942.073μs | 1.558ms | 2.274ms |
| TCPDF | 10.039ms | 10.844ms | 11.835ms | 19.207ms | 28.367ms |
| mPDF | 25.641ms | 29.090ms | 32.491ms | 60.596ms | 95.106ms |
| Dompdf | 11.102ms | 15.139ms | 19.895ms | 66.076ms | 148.461ms |

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
| phpdftk | 3.290ms | 3.487ms | 3.797ms | 5.773ms | 8.242ms |
| FPDF | 1.054ms | 1.140ms | 1.244ms | 1.939ms | 2.734ms |
| TCPDF | 14.720ms | 15.652ms | 16.797ms | 24.968ms | 35.412ms |

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
| Pdf (Level 3) | 3.272ms | 4.288ms | 12.132ms |
| PdfDoc (Level 2) | 2.622ms | 3.033ms | 7.322ms |
| PdfWriter (Level 1) | 2.302ms | 2.725ms | 6.761ms |

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
| Pdf (Level 3) | 4.187ms | 11.589ms | 45.263ms |
| PdfDoc (Level 2) | 3.633ms | 9.714ms | — |

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
| Pdf (Level 3) | 3.973ms | 11.458ms | 44.887ms |
| PdfDoc (Level 2) | 3.206ms | 7.158ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.049ms | 1.651ms | 6.099ms |
| smalot/pdfparser | 1.989ms | 2.339ms | 5.480ms |
| setasign/fpdi | 1.883ms | 2.689ms | 28.543ms |

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
| phpdftk | 2.006ms | 1.338ms |
| smalot/pdfparser | FAIL | 1.900ms |
| setasign/fpdi | 2.854ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.209ms   | ±1.27%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.651ms   | ±0.65%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.099ms   | ±1.24%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.006ms   | ±1.05%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.338ms   | ±0.99%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.989ms   | ±0.70%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.339ms   | ±0.91%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.480ms   | ±0.55%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 559.525μs | ±1.27%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.900ms   | ±0.46%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.883ms   | ±1.06%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.689ms   | ±1.15%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.543ms  | ±0.99%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.854ms   | ±0.51%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.494ms   | ±1.04%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 7.028ms   | ±0.61%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 5.336ms   | ±0.54%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.967mb  | 3.799ms   | ±0.69%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.870μs   | ±19.64% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.049ms   | ±0.73%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.953mb  | 3.535ms   | ±0.53%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.441mb  | 5.318ms   | ±0.84%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.761mb  | 5.429ms   | ±0.48%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.721mb  | 4.676ms   | ±0.07%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.184mb  | 5.164ms   | ±0.50%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.249mb  | 4.990ms   | ±1.28%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.107mb  | 8.570ms   | ±0.90%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.659mb  | 2.823ms   | ±2.61%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.866mb | 66.771ms  | ±2.35%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.725mb | 272.315ms | ±1.09%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.345mb | 1.057s    | ±1.45%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.150mb | 198.119ms | ±1.64%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.482mb | 170.279ms | ±0.33%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.586mb | 123.828ms | ±0.33%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.623mb | 165.888ms | ±0.81%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.245mb | 131.331ms | ±0.66%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.724mb | 257.276ms | ±0.17%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.325mb | 40.791ms  | ±1.05%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.311mb | 35.457ms  | ±1.22%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.241mb | 34.475ms  | ±1.96%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.476mb | 107.890ms | ±0.36%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.233mb | 37.239ms  | ±0.29%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.446mb | 46.197ms  | ±5.23%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.861mb | 67.185ms  | ±0.77%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.140mb | 30.059ms  | ±0.64%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.709mb | 37.995ms  | ±3.35%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.837mb | 166.772ms | ±0.16%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.706mb | 111.126ms | ±0.55%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.589mb | 44.170ms  | ±2.15%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.177mb | 90.193ms  | ±0.04%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.816mb | 1.025s    | ±1.17%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.742mb | 24.201ms  | ±1.83%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.768mb | 51.557ms  | ±0.83%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.579mb | 469.809ms | ±2.41%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.370mb | 63.105ms  | ±9.62%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.089mb | 81.713ms  | ±1.21%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.858mb | 656.631ms | ±0.49%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.790mb | 17.707ms  | ±0.57%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.790mb | 40.994ms  | ±0.55%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.432mb | 274.747ms | ±0.31%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.187ms   | ±0.86%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 11.589ms  | ±0.74%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.541mb | 45.263ms  | ±2.05%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.633ms   | ±0.53%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.959mb  | 9.714ms   | ±0.40%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.784ms   | ±0.71%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.546mb  | 8.180ms   | ±0.20%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.755mb  | 8.325ms   | ±0.78%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.290ms   | ±0.80%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.487ms   | ±0.69%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.797ms   | ±0.42%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.773ms   | ±0.55%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.242ms   | ±0.21%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.720ms  | ±0.61%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.652ms  | ±0.16%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.797ms  | ±0.69%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 24.968ms  | ±0.19%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 35.412ms  | ±0.23%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.054ms   | ±2.78%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.140ms   | ±0.35%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.244ms   | ±0.91%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.939ms   | ±1.31%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.734ms   | ±0.54%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.973ms   | ±8.66%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 11.458ms  | ±0.86%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 44.887ms  | ±0.24%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.206ms   | ±0.58%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 7.158ms   | ±0.87%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.528μs  | ±0.91%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 234.586μs | ±0.81%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.302ms   | ±0.68%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.725ms   | ±0.41%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.761ms   | ±0.85%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.622ms   | ±3.12%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.033ms   | ±1.11%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 7.322ms   | ±8.43%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.272ms   | ±0.63%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.288ms   | ±4.31%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 12.132ms  | ±0.36%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.883mb  | 20.458ms  | ±0.72%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.869mb | 179.919ms | ±0.48%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.384mb | 898.391ms | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.263ms   | ±1.39%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.483ms   | ±4.27%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.706ms   | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.690ms   | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.481mb  | 6.860ms   | ±2.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.421ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.716ms   | ±1.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.762mb  | 13.044ms  | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.592ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.366ms   | ±0.46%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 628.482μs | ±3.20%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.079ms   | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.527ms   | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.110mb  | 3.123ms   | ±0.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 293.325ms | ±25.31% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.504ms   | ±1.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.839mb  | 5.737ms   | ±0.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 5.934ms   | ±0.96%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.039ms  | ±0.60%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.844ms  | ±0.62%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.835ms  | ±0.38%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.207ms  | ±0.62%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.367ms  | ±2.24%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 774.973μs | ±0.75%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 864.817μs | ±1.82%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 942.073μs | ±1.12%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.558ms   | ±1.05%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.274ms   | ±0.97%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.641ms  | ±1.91%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 29.090ms  | ±0.57%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.491ms  | ±0.46%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 60.596ms  | ±0.25%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 95.106ms  | ±0.30%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.102ms  | ±0.47%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.139ms  | ±1.05%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 19.895ms  | ±1.79%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 66.076ms  | ±0.34%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 148.461ms | ±1.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.040mb  | 4.887ms   | ±0.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.478mb  | 54.021ms  | ±1.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 205.996ms | ±37.09% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 466.027μs | ±2.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.881ms   | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.296ms   | ±0.36%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.587ms  | ±8.86%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 81.311ms  | ±1.10%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.809ms  | ±0.62%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.577ms  | ±0.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 194.106ms | ±25.07% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.210mb  | 13.993ms  | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.182mb  | 13.840ms  | ±0.34%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.183mb  | 13.979ms  | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.207mb  | 13.884ms  | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.311mb  | 14.604ms  | ±0.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.870ms   | ±0.94%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.194mb  | 13.819ms  | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.245mb  | 13.880ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.139mb  | 13.643ms  | ±0.57%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```