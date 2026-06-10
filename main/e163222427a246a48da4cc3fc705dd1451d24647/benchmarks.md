# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-10 02:38:48 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.637ms | 1.919ms | 2.430ms | 3.621ms | 5.370ms |
| FPDF | 636.784μs | 686.879μs | 730.978μs | 1.201ms | 1.812ms |
| TCPDF | 8.140ms | 8.726ms | 9.484ms | 15.044ms | 22.130ms |
| mPDF | 21.776ms | 24.222ms | 26.389ms | 47.471ms | 74.646ms |
| Dompdf | 8.691ms | 12.006ms | 15.690ms | 51.552ms | 116.787ms |

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
| phpdftk | 2.549ms | 2.751ms | 2.943ms | 4.484ms | 6.366ms |
| FPDF | 944.411μs | 892.357μs | 1.137ms | 1.515ms | 2.146ms |
| TCPDF | 11.628ms | 12.233ms | 13.122ms | 19.875ms | 27.849ms |

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
| Pdf (Level 3) | 3.723ms | 3.351ms | 9.492ms |
| PdfDoc (Level 2) | 2.026ms | 2.413ms | 6.906ms |
| PdfWriter (Level 1) | 1.795ms | 2.137ms | 5.334ms |

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
| Pdf (Level 3) | 3.318ms | 9.952ms | 40.635ms |
| PdfDoc (Level 2) | 2.903ms | 8.904ms | — |

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
| Pdf (Level 3) | 3.065ms | 8.911ms | 35.142ms |
| PdfDoc (Level 2) | 2.511ms | 9.311ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.969mb | 6.521mb | 8.965mb |
| PdfDoc (Level 2) | 5.758mb | 6.252mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.699ms | 1.293ms | 4.682ms |
| smalot/pdfparser | 1.531ms | 1.798ms | 4.230ms |
| setasign/fpdi | 1.482ms | 2.095ms | 22.106ms |

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
| phpdftk | 1.576ms | 1.048ms |
| smalot/pdfparser | FAIL | 1.456ms |
| setasign/fpdi | 2.226ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 954.008μs | ±0.53%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.293ms   | ±0.97%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 4.682ms   | ±1.17%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.576ms   | ±0.84%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.048ms   | ±1.18%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.531ms   | ±0.74%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 1.798ms   | ±2.27%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.230ms   | ±0.92%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 429.410μs | ±0.46%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.456ms   | ±0.81%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.482ms   | ±0.62%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.095ms   | ±0.78%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 22.106ms  | ±0.77%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.226ms   | ±0.96%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.166ms   | ±0.95%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.951mb  | 5.455ms   | ±0.71%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.923mb  | 4.118ms   | ±0.69%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.967mb  | 2.925ms   | ±0.87%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.061μs   | ±14.14%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.699ms   | ±1.32%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.953mb  | 2.742ms   | ±1.01%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.441mb  | 4.102ms   | ±1.30%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.760mb  | 4.189ms   | ±0.15%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.720mb  | 3.625ms   | ±0.45%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.183mb  | 3.992ms   | ±0.54%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.248mb  | 3.892ms   | ±0.25%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.106mb  | 6.606ms   | ±0.30%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.659mb  | 2.180ms   | ±0.86%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.865mb | 49.559ms  | ±0.50%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.725mb | 209.669ms | ±0.09%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.345mb | 819.191ms | ±0.41%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.140mb | 153.215ms | ±1.75%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.471mb | 131.192ms | ±0.88%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.585mb | 96.569ms  | ±0.27%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.622mb | 127.566ms | ±0.55%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.244mb | 101.529ms | ±0.39%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.724mb | 198.021ms | ±0.86%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.324mb | 31.572ms  | ±5.92%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.310mb | 27.233ms  | ±0.81%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.240mb | 26.900ms  | ±0.40%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.476mb | 84.139ms  | ±2.11%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.232mb | 28.524ms  | ±0.35%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.445mb | 35.836ms  | ±3.19%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.861mb | 52.791ms  | ±0.67%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.140mb | 22.994ms  | ±0.73%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.709mb | 29.431ms  | ±1.21%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.837mb | 131.767ms | ±0.88%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.705mb | 85.233ms  | ±0.21%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.588mb | 34.082ms  | ±0.71%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.176mb | 69.991ms  | ±0.97%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.815mb | 806.120ms | ±0.49%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.742mb | 18.379ms  | ±1.26%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.768mb | 40.376ms  | ±0.88%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.579mb | 366.923ms | ±0.62%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.370mb | 59.376ms  | ±12.29%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.089mb | 65.528ms  | ±2.13%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.858mb | 515.071ms | ±0.51%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.790mb | 13.740ms  | ±0.58%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.790mb | 32.183ms  | ±0.68%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.432mb | 216.846ms | ±0.08%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.337mb  | 3.318ms   | ±1.03%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.132mb  | 9.952ms   | ±60.83%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.541mb | 40.635ms  | ±49.64%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 2.903ms   | ±0.89%   |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.959mb  | 8.904ms   | ±22.50%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.731mb  | 6.765ms   | ±0.76%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.546mb  | 6.456ms   | ±93.52%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.755mb  | 6.405ms   | ±16.01%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 2.549ms   | ±1.05%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.411mb  | 2.751ms   | ±0.33%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.470mb  | 2.943ms   | ±0.50%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.963mb  | 4.484ms   | ±0.91%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 6.366ms   | ±0.45%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 11.628ms  | ±0.39%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 12.233ms  | ±0.41%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 13.122ms  | ±0.40%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 19.875ms  | ±7.07%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 27.849ms  | ±0.34%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 944.411μs | ±64.84%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 892.357μs | ±0.47%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.137ms   | ±63.49%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.515ms   | ±1.02%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.146ms   | ±1.38%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.969mb  | 3.065ms   | ±1.04%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.521mb  | 8.911ms   | ±0.41%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.965mb  | 35.142ms  | ±2.20%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.758mb  | 2.511ms   | ±17.79%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.252mb  | 9.311ms   | ±52.94%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 33.251μs  | ±0.99%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.497mb  | 184.100μs | ±0.74%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 1.795ms   | ±0.71%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.539mb  | 2.137ms   | ±0.41%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 5.334ms   | ±0.95%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.643mb  | 2.026ms   | ±49.32%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 2.413ms   | ±99.73%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.370mb  | 6.906ms   | ±31.19%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.986mb  | 3.723ms   | ±96.19%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 3.351ms   | ±1.01%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.826mb  | 9.492ms   | ±61.96%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.883mb  | 15.844ms  | ±1.23%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.869mb | 138.568ms | ±1.03%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.384mb | 680.555ms | ±0.48%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 1.779ms   | ±1.21%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.938mb  | 1.919ms   | ±1.35%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.024mb  | 2.430ms   | ±96.47%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.658mb  | 3.621ms   | ±1.32%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.481mb  | 5.370ms   | ±0.63%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.280mb  | 2.735ms   | ±149.46% |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.333mb  | 2.928ms   | ±95.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.762mb  | 11.229ms  | ±61.16%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.323mb  | 2.846ms   | ±61.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.666mb  | 1.816ms   | ±0.40%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 504.413μs | ±5.50%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 2.420ms   | ±1.68%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 2.738ms   | ±1.07%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.110mb  | 2.458ms   | ±18.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.166mb  | 184.704ms | ±23.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.227mb  | 2.745ms   | ±0.68%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.839mb  | 5.016ms   | ±69.13%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.974mb  | 7.507ms   | ±88.54%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 8.140ms   | ±72.48%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 8.726ms   | ±20.25%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.484ms   | ±4.35%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 15.044ms  | ±30.55%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 22.130ms  | ±7.31%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 636.784μs | ±8.94%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 686.879μs | ±55.50%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 730.978μs | ±1.18%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.201ms   | ±0.79%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 1.812ms   | ±154.45% |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 21.776ms  | ±28.08%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 24.222ms  | ±3.13%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 26.389ms  | ±1.37%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 47.471ms  | ±0.34%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 74.646ms  | ±0.77%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 8.691ms   | ±2.54%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 12.006ms  | ±0.91%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 15.690ms  | ±0.44%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 51.552ms  | ±1.06%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 116.787ms | ±0.52%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.040mb  | 3.880ms   | ±0.85%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.477mb  | 41.478ms  | ±1.26%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.333μs   | ±10.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.001μs   | ±12.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.333μs   | ±0.00%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 191.990ms | ±24.44%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 365.068μs | ±0.70%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.454mb  | 2.265ms   | ±0.68%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.051mb  | 2.639ms   | ±90.59%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 8.230ms   | ±1.06%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 63.424ms  | ±0.55%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.570ms  | ±0.37%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 19.831ms  | ±3.20%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.887mb  | 250.404ms | ±25.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.209mb  | 10.905ms  | ±0.85%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.182mb  | 10.842ms  | ±54.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.182mb  | 10.915ms  | ±0.66%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.206mb  | 10.901ms  | ±26.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.311mb  | 11.276ms  | ±0.97%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.987mb  | 2.317ms   | ±40.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.194mb  | 10.933ms  | ±28.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.245mb  | 10.855ms  | ±95.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.139mb  | 10.637ms  | ±18.19%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```