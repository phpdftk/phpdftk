# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-11 05:08:17 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.343ms | 2.552ms | 2.775ms | 4.728ms | 7.017ms |
| FPDF | 802.476μs | 859.900μs | 944.134μs | 1.543ms | 2.308ms |
| TCPDF | 10.189ms | 11.314ms | 12.192ms | 20.891ms | 31.592ms |
| mPDF | 26.054ms | 30.091ms | 34.269ms | 66.201ms | 106.792ms |
| Dompdf | 11.379ms | 16.224ms | 21.785ms | 73.962ms | 164.339ms |

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
| phpdftk | 3.336ms | 3.590ms | 3.868ms | 5.927ms | 8.314ms |
| FPDF | 1.113ms | 1.134ms | 1.266ms | 1.954ms | 2.777ms |
| TCPDF | 14.705ms | 16.213ms | 17.260ms | 26.831ms | 39.075ms |

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
| Pdf (Level 3) | 3.325ms | 4.440ms | 12.844ms |
| PdfDoc (Level 2) | 2.748ms | 3.158ms | 7.485ms |
| PdfWriter (Level 1) | 2.337ms | 2.795ms | 6.939ms |

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
| Pdf (Level 3) | 4.410ms | 12.036ms | 47.271ms |
| PdfDoc (Level 2) | 3.718ms | 9.931ms | — |

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
| Pdf (Level 3) | 4.032ms | 11.548ms | 44.866ms |
| PdfDoc (Level 2) | 3.243ms | 7.275ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.307ms | 1.705ms | 6.073ms |
| smalot/pdfparser | 2.035ms | 2.399ms | 5.781ms |
| setasign/fpdi | 1.980ms | 2.870ms | 30.250ms |

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
| phpdftk | 2.022ms | 1.375ms |
| smalot/pdfparser | FAIL | 1.934ms |
| setasign/fpdi | 3.001ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.246ms   | ±1.05%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.705ms   | ±4.60%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.073ms   | ±0.83%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.022ms   | ±1.08%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.375ms   | ±1.32%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 2.035ms   | ±0.94%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.399ms   | ±0.65%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.781ms   | ±3.30%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 555.439μs | ±3.10%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.934ms   | ±0.79%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.980ms   | ±0.90%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.870ms   | ±0.66%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.250ms  | ±0.83%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.001ms   | ±0.43%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.545ms   | ±0.76%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 7.304ms   | ±1.15%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 5.474ms   | ±6.78%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.967mb  | 3.880ms   | ±1.17%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.727μs   | ±36.89% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.307ms   | ±1.21%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.953mb  | 3.730ms   | ±0.37%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.441mb  | 5.689ms   | ±1.08%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.761mb  | 5.795ms   | ±0.92%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.721mb  | 4.897ms   | ±0.27%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.184mb  | 5.477ms   | ±0.64%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.249mb  | 5.450ms   | ±0.84%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.107mb  | 9.574ms   | ±0.98%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.659mb  | 2.893ms   | ±0.08%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.926mb | 68.343ms  | ±0.22%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.742mb | 295.789ms | ±1.35%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.362mb | 1.149s    | ±0.61%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.167mb | 212.160ms | ±0.51%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.498mb | 172.648ms | ±0.38%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.646mb | 132.104ms | ±1.32%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.749mb | 179.083ms | ±1.29%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.305mb | 143.571ms | ±5.13%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.785mb | 278.500ms | ±0.30%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.386mb | 43.022ms  | ±0.31%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.371mb | 37.425ms  | ±0.77%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.301mb | 36.552ms  | ±0.28%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.537mb | 116.803ms | ±0.17%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.294mb | 39.628ms  | ±0.23%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.506mb | 49.299ms  | ±0.41%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.922mb | 72.237ms  | ±0.09%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.201mb | 31.764ms  | ±1.32%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.726mb | 40.272ms  | ±0.73%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.854mb | 181.826ms | ±0.50%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.832mb | 113.981ms | ±0.27%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.649mb | 46.192ms  | ±0.44%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.237mb | 94.369ms  | ±0.47%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.832mb | 1.100s    | ±0.12%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.742mb | 26.760ms  | ±2.01%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.768mb | 59.539ms  | ±1.21%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.579mb | 562.667ms | ±0.44%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.370mb | 65.077ms  | ±10.19% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.089mb | 88.891ms  | ±1.09%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.858mb | 735.983ms | ±0.90%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.790mb | 19.319ms  | ±1.88%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.790mb | 43.787ms  | ±0.68%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.432mb | 327.603ms | ±0.16%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.410ms   | ±0.98%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 12.036ms  | ±0.08%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.541mb | 47.271ms  | ±0.52%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.718ms   | ±1.07%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.959mb  | 9.931ms   | ±0.96%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.903ms   | ±0.89%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.546mb  | 8.431ms   | ±0.70%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.755mb  | 8.528ms   | ±0.51%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.336ms   | ±1.03%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.590ms   | ±0.53%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.868ms   | ±0.33%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.927ms   | ±0.47%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.314ms   | ±0.48%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.705ms  | ±1.04%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.213ms  | ±1.26%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.260ms  | ±0.67%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.831ms  | ±0.58%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 39.075ms  | ±0.59%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.113ms   | ±2.15%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.134ms   | ±2.15%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.266ms   | ±0.82%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.954ms   | ±0.90%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.777ms   | ±0.71%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 4.032ms   | ±0.36%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 11.548ms  | ±0.63%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 44.866ms  | ±0.24%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.243ms   | ±1.85%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 7.275ms   | ±0.24%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.267μs  | ±0.86%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 242.448μs | ±1.06%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.337ms   | ±2.53%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.795ms   | ±1.00%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.939ms   | ±1.74%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.748ms   | ±2.55%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.158ms   | ±2.00%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 7.485ms   | ±0.91%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.325ms   | ±20.70% |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.440ms   | ±1.38%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 12.844ms  | ±0.43%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.883mb  | 22.074ms  | ±0.64%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.869mb | 190.723ms | ±1.89%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.384mb | 950.337ms | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.357ms   | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.552ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.775ms   | ±1.15%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.728ms   | ±1.64%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.481mb  | 7.017ms   | ±0.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.496ms   | ±1.29%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.850ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.762mb  | 12.372ms  | ±5.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.701ms   | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.390ms   | ±0.66%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 700.515μs | ±34.03% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.197ms   | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.670ms   | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.110mb  | 3.241ms   | ±2.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 188.052ms | ±35.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.654ms   | ±0.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.839mb  | 5.883ms   | ±22.77% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 6.079ms   | ±0.81%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.189ms  | ±1.77%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.314ms  | ±1.61%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.192ms  | ±0.85%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.891ms  | ±0.75%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.592ms  | ±0.28%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 802.476μs | ±1.57%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 859.900μs | ±2.63%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 944.134μs | ±2.05%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.543ms   | ±0.34%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.308ms   | ±0.23%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 26.054ms  | ±1.88%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 30.091ms  | ±0.66%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 34.269ms  | ±0.68%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 66.201ms  | ±0.54%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 106.792ms | ±0.45%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.379ms  | ±0.69%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.224ms  | ±0.63%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.785ms  | ±0.63%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 73.962ms  | ±0.41%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 164.339ms | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.040mb  | 5.066ms   | ±0.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.478mb  | 50.884ms  | ±3.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.665μs   | ±17.39% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.334μs   | ±9.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 170.130ms | ±20.46% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 456.781μs | ±1.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.989ms   | ±0.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.422ms   | ±3.67%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.724ms  | ±5.75%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 83.780ms  | ±1.98%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.076ms  | ±1.77%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 24.688ms  | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 151.079ms | ±29.60% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.210mb  | 13.689ms  | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.182mb  | 13.350ms  | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.183mb  | 13.505ms  | ±0.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.207mb  | 13.572ms  | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.311mb  | 14.163ms  | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.895ms   | ±1.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.194mb  | 13.417ms  | ±0.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.245mb  | 13.556ms  | ±0.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.139mb  | 13.343ms  | ±0.56%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```