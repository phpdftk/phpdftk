# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-10 12:45:16 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.706ms | 2.014ms | 3.900ms | 6.909ms | 21.046ms |
| FPDF | 1.142ms | 1.047ms | 726.557μs | 1.201ms | 1.743ms |
| TCPDF | 8.270ms | 8.718ms | 9.365ms | 15.350ms | 21.905ms |
| mPDF | 20.629ms | 23.187ms | 25.461ms | 47.176ms | 74.271ms |
| Dompdf | 8.663ms | 11.875ms | 15.504ms | 51.092ms | 115.254ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.139mb | 5.938mb | 6.024mb | 6.658mb | 7.481mb |
| FPDF | 5.072mb | 5.073mb | 5.073mb | 5.073mb | 5.083mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.682mb | 17.721mb | 18.014mb | 18.375mb |
| Dompdf | 9.357mb | 9.577mb | 9.898mb | 12.591mb | 15.954mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.977ms | 2.750ms | 3.129ms | 4.460ms | 6.441ms |
| FPDF | 827.154μs | 895.307μs | 958.146μs | 1.500ms | 2.111ms |
| TCPDF | 11.517ms | 12.234ms | 13.399ms | 19.801ms | 27.580ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.365mb | 5.411mb | 5.470mb | 5.963mb | 6.562mb |
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
| Pdf (Level 3) | 2.797ms | 3.461ms | 10.297ms |
| PdfDoc (Level 2) | 6.598ms | 2.408ms | 5.847ms |
| PdfWriter (Level 1) | 2.142ms | 2.188ms | 5.376ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 5.986mb | 6.150mb | 7.826mb |
| PdfDoc (Level 2) | 5.643mb | 5.802mb | 7.370mb |
| PdfWriter (Level 1) | 5.381mb | 5.539mb | 7.115mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 4.606ms | 9.189ms | 38.422ms |
| PdfDoc (Level 2) | 2.883ms | 8.252ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.337mb | 9.132mb | 21.541mb |
| PdfDoc (Level 2) | 6.144mb | 8.959mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 3.289ms | 9.021ms | 37.341ms |
| PdfDoc (Level 2) | 2.479ms | 5.606ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.969mb | 6.521mb | 8.965mb |
| PdfDoc (Level 2) | 5.758mb | 6.252mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.779ms | 1.285ms | 4.729ms |
| smalot/pdfparser | 1.557ms | 1.808ms | 4.254ms |
| setasign/fpdi | 1.482ms | 2.085ms | 22.005ms |

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
| phpdftk | 1.580ms | 1.037ms |
| smalot/pdfparser | FAIL | 1.498ms |
| setasign/fpdi | 2.217ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 944.776μs | ±1.47%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.285ms   | ±0.82%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 4.729ms   | ±0.81%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.580ms   | ±1.01%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.037ms   | ±1.27%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.557ms   | ±1.07%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 1.808ms   | ±0.20%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.254ms   | ±1.65%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 450.090μs | ±0.71%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.498ms   | ±1.64%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.482ms   | ±1.04%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.085ms   | ±1.22%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 22.005ms  | ±0.84%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.217ms   | ±0.59%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.165ms   | ±0.59%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.951mb  | 5.481ms   | ±0.90%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.923mb  | 4.212ms   | ±1.49%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.967mb  | 2.970ms   | ±1.09%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.032μs   | ±27.59%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.779ms   | ±0.48%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.953mb  | 2.781ms   | ±0.63%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.441mb  | 4.149ms   | ±0.83%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.760mb  | 4.275ms   | ±0.74%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.720mb  | 3.678ms   | ±1.07%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.183mb  | 4.188ms   | ±1.35%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.248mb  | 3.906ms   | ±0.89%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.106mb  | 6.733ms   | ±1.22%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.659mb  | 2.202ms   | ±0.75%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.865mb | 50.006ms  | ±0.23%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.725mb | 212.701ms | ±0.78%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.345mb | 841.550ms | ±0.97%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.150mb | 156.518ms | ±0.46%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.481mb | 131.992ms | ±0.97%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.585mb | 97.606ms  | ±1.59%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.622mb | 130.421ms | ±0.72%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.244mb | 102.630ms | ±0.53%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.724mb | 200.225ms | ±1.41%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.324mb | 31.414ms  | ±1.02%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.310mb | 27.515ms  | ±0.43%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.240mb | 26.546ms  | ±0.16%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.476mb | 84.642ms  | ±0.52%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.232mb | 28.788ms  | ±0.98%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.445mb | 35.773ms  | ±1.54%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.861mb | 52.976ms  | ±0.72%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.140mb | 23.404ms  | ±0.64%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.709mb | 29.177ms  | ±2.40%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.837mb | 130.911ms | ±0.36%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.705mb | 85.958ms  | ±0.53%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.588mb | 34.278ms  | ±0.67%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.176mb | 69.461ms  | ±1.41%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.815mb | 795.731ms | ±1.40%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.742mb | 18.775ms  | ±2.48%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.768mb | 40.933ms  | ±1.92%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.579mb | 380.372ms | ±1.04%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.370mb | 65.824ms  | ±45.92%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.089mb | 65.213ms  | ±0.77%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.858mb | 513.279ms | ±0.92%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.790mb | 13.873ms  | ±0.88%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.790mb | 33.934ms  | ±39.38%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.432mb | 215.872ms | ±0.28%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.337mb  | 4.606ms   | ±94.98%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.132mb  | 9.189ms   | ±93.81%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.541mb | 38.422ms  | ±30.23%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 2.883ms   | ±130.20% |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.959mb  | 8.252ms   | ±128.81% |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.731mb  | 7.240ms   | ±57.09%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.546mb  | 6.417ms   | ±1.13%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.755mb  | 6.441ms   | ±7.65%   |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 10.977ms  | ±133.95% |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.411mb  | 2.750ms   | ±1.16%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.470mb  | 3.129ms   | ±33.79%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.963mb  | 4.460ms   | ±0.28%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 6.441ms   | ±0.45%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 11.517ms  | ±2.50%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 12.234ms  | ±1.53%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 13.399ms  | ±1.27%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 19.801ms  | ±2.12%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 27.580ms  | ±0.71%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 827.154μs | ±3.62%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 895.307μs | ±2.20%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 958.146μs | ±1.47%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.500ms   | ±0.97%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.111ms   | ±0.26%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.969mb  | 3.289ms   | ±177.25% |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.521mb  | 9.021ms   | ±122.98% |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.965mb  | 37.341ms  | ±39.16%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.758mb  | 2.479ms   | ±1.31%   |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.252mb  | 5.606ms   | ±1.29%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 33.256μs  | ±1.01%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.497mb  | 183.291μs | ±0.53%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.142ms   | ±69.19%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.539mb  | 2.188ms   | ±119.74% |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 5.376ms   | ±1.54%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.643mb  | 6.598ms   | ±129.64% |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 2.408ms   | ±0.63%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.370mb  | 5.847ms   | ±152.29% |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.986mb  | 2.797ms   | ±165.93% |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 3.461ms   | ±120.50% |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.826mb  | 10.297ms  | ±140.05% |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.883mb  | 15.787ms  | ±0.50%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.869mb | 139.165ms | ±0.33%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.384mb | 683.082ms | ±0.38%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.605ms   | ±89.44%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.938mb  | 2.014ms   | ±160.43% |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.024mb  | 3.900ms   | ±155.76% |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.658mb  | 6.909ms   | ±108.88% |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.481mb  | 21.046ms  | ±105.97% |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.280mb  | 2.655ms   | ±0.61%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.333mb  | 3.003ms   | ±128.67% |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.762mb  | 14.287ms  | ±55.94%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.323mb  | 2.868ms   | ±71.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.666mb  | 1.921ms   | ±143.87% |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 495.174μs | ±34.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 2.415ms   | ±26.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 2.783ms   | ±0.67%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.110mb  | 2.462ms   | ±0.87%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.166mb  | 223.682ms | ±23.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.227mb  | 2.720ms   | ±0.72%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.839mb  | 4.623ms   | ±3.52%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.974mb  | 4.869ms   | ±146.58% |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 8.270ms   | ±1.53%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 8.718ms   | ±0.73%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.365ms   | ±1.57%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 15.350ms  | ±28.75%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 21.905ms  | ±1.18%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 1.142ms   | ±106.07% |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 1.047ms   | ±174.71% |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 726.557μs | ±1.85%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.201ms   | ±3.67%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 1.743ms   | ±0.69%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 20.629ms  | ±2.00%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 23.187ms  | ±1.46%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 25.461ms  | ±0.87%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 47.176ms  | ±1.09%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 74.271ms  | ±2.62%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 8.663ms   | ±0.76%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 11.875ms  | ±0.76%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 15.504ms  | ±0.94%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 51.092ms  | ±0.33%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 115.254ms | ±0.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.040mb  | 3.837ms   | ±2.66%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.477mb  | 41.216ms  | ±0.76%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.333μs   | ±0.00%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.204μs   | ±19.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.344μs   | ±11.13%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 198.410ms | ±29.85%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 373.868μs | ±1.09%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.454mb  | 2.278ms   | ±0.42%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.051mb  | 2.610ms   | ±1.07%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 9.684ms   | ±21.37%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 66.482ms  | ±1.63%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.618ms  | ±2.10%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.106ms  | ±2.21%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.887mb  | 203.053ms | ±30.20%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.209mb  | 10.808ms  | ±1.16%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.182mb  | 10.766ms  | ±0.81%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.182mb  | 10.871ms  | ±1.29%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.206mb  | 11.008ms  | ±2.26%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.311mb  | 11.175ms  | ±0.58%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.987mb  | 2.287ms   | ±10.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.194mb  | 10.739ms  | ±0.48%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.245mb  | 10.962ms  | ±1.80%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.139mb  | 10.706ms  | ±1.11%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```