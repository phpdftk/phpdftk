# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-25 23:20:40 UTC
PHP: 8.4.26
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.647ms | 2.079ms | 2.289ms | 3.830ms | 5.590ms |
| FPDF | 754.967μs | 817.166μs | 871.403μs | 1.330ms | 1.903ms |
| TCPDF | 7.861ms | 8.608ms | 9.376ms | 14.977ms | 22.079ms |
| mPDF | 19.928ms | 22.494ms | 25.194ms | 46.693ms | 72.902ms |
| Dompdf | 8.826ms | 12.091ms | 15.742ms | 51.489ms | 115.221ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.218mb | 5.947mb | 6.033mb | 6.667mb | 7.490mb |
| FPDF | 5.072mb | 5.072mb | 5.072mb | 5.072mb | 5.084mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.683mb | 17.721mb | 18.014mb | 18.376mb |
| Dompdf | 9.381mb | 9.600mb | 9.921mb | 12.614mb | 15.977mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.711ms | 2.909ms | 3.216ms | 4.608ms | 6.592ms |
| FPDF | 1.151ms | 1.027ms | 1.124ms | 1.621ms | 2.250ms |
| TCPDF | 11.482ms | 12.152ms | 13.379ms | 19.443ms | 27.657ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.374mb | 5.420mb | 5.479mb | 5.972mb | 6.571mb |
| FPDF | 4.455mb | 4.455mb | 4.455mb | 4.455mb | 4.505mb |
| TCPDF | 12.487mb | 12.487mb | 12.487mb | 12.487mb | 12.488mb |

## Writer Levels Comparison — `WriterLevelsBench`

Same workload (N pages with heading + body text) rendered through each
writer level, so the abstraction overhead is visible directly. Lower is
better; the higher-level APIs (`Pdf` → `PdfDoc` → `PdfWriter`) trade
some performance for ergonomics.

### Generation Time

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 2.740ms | 3.502ms | 9.522ms |
| PdfDoc (Level 2) | 2.214ms | 2.546ms | 5.966ms |
| PdfWriter (Level 1) | 1.905ms | 2.294ms | 5.449ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 6.057mb | 6.220mb | 7.897mb |
| PdfDoc (Level 2) | 5.714mb | 5.872mb | 7.441mb |
| PdfWriter (Level 1) | 5.389mb | 5.548mb | 7.124mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 3.494ms | 9.355ms | 36.108ms |
| PdfDoc (Level 2) | 3.073ms | 7.804ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.408mb | 9.203mb | 21.611mb |
| PdfDoc (Level 2) | 6.214mb | 9.030mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 3.274ms | 9.080ms | 34.979ms |
| PdfDoc (Level 2) | 2.697ms | 5.806ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.653ms | 1.281ms | 4.690ms |
| smalot/pdfparser | 1.550ms | 1.801ms | 4.216ms |
| setasign/fpdi | 1.467ms | 2.084ms | 21.993ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.341mb | 4.243mb | 4.595mb |
| smalot/pdfparser | 4.800mb | 4.884mb | 6.601mb |
| setasign/fpdi | 4.743mb | 4.769mb | 5.526mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 1.560ms | 1.030ms |
| smalot/pdfparser | FAIL | 1.459ms |
| setasign/fpdi | 2.199ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 938.455μs | ±0.94%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.281ms   | ±0.72%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 4.690ms   | ±2.36%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.560ms   | ±0.91%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.030ms   | ±1.48%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.550ms   | ±1.28%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.801ms   | ±0.48%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.216ms   | ±0.57%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 425.260μs | ±0.75%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.459ms   | ±0.65%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.467ms   | ±1.24%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.084ms   | ±0.80%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 21.993ms  | ±0.35%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.199ms   | ±0.46%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.149ms   | ±0.91%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 5.453ms   | ±0.80%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 4.206ms   | ±0.91%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.944ms   | ±0.66%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.176μs   | ±15.14% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.653ms   | ±0.88%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.760μs   | ±17.58% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.685μs   | ±31.93% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.857mb | 14.660ms  | ±0.33%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.857mb | 14.595ms  | ±0.37%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 9.381mb  | 11.775ms  | ±0.68%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.985mb  | 10.522ms  | ±0.74%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 10.198mb | 16.698ms  | ±0.63%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 10.202mb | 15.107ms  | ±0.41%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 11.773mb | 43.150ms  | ±0.83%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 9.633mb  | 11.342ms  | ±0.20%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.847mb  | 13.743ms  | ±0.56%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 12.992mb | 54.977ms  | ±1.76%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 8.045mb  | 5.264ms   | ±0.25%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 32.592μs  | ±1.52%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 183.120μs | ±0.86%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.374mb  | 2.711ms   | ±3.32%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 2.909ms   | ±3.76%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.216ms   | ±42.54% |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 4.608ms   | ±1.80%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.571mb  | 6.592ms   | ±1.32%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 11.482ms  | ±0.98%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 12.152ms  | ±0.37%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 13.379ms  | ±21.34% |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 19.443ms  | ±0.58%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 27.657ms  | ±0.42%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.151ms   | ±83.70% |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.027ms   | ±8.61%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.124ms   | ±11.03% |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.621ms   | ±5.06%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.250ms   | ±3.66%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.694mb | 44.141ms  | ±0.47%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 18.302mb | 90.721ms  | ±0.39%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 65.481mb | 1.053s    | ±0.22%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.522mb | 18.540ms  | ±2.42%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.548mb | 40.563ms  | ±0.93%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 58.359mb | 352.446ms | ±0.73%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.790mb | 66.290ms  | ±7.56%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.510mb | 72.929ms  | ±63.95% |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 34.279mb | 506.502ms | ±0.50%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.547mb | 13.807ms  | ±0.89%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.547mb | 31.724ms  | ±0.03%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 31.190mb | 212.773ms | ±0.16%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 6.872ms   | ±0.60%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 6.566ms   | ±2.32%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 6.618ms   | ±0.71%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.937mb  | 19.538ms  | ±1.28%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.461mb | 172.859ms | ±0.54%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 50.370mb | 844.041ms | ±0.50%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.433mb | 133.850ms | ±0.46%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 28.602mb | 790.612ms | ±0.26%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 25.406mb | 644.842ms | ±0.67%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 3.494ms   | ±1.04%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 9.355ms   | ±14.58% |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 36.108ms  | ±1.00%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.073ms   | ±1.62%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.030mb  | 7.804ms   | ±0.99%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.274ms   | ±51.10% |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 9.080ms   | ±9.83%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 34.979ms  | ±0.59%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.697ms   | ±2.13%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 5.806ms   | ±1.09%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 1.905ms   | ±2.59%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.294ms   | ±67.16% |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.124mb  | 5.449ms   | ±1.03%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.214ms   | ±2.02%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.546ms   | ±3.37%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 5.966ms   | ±1.01%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 2.740ms   | ±1.83%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 3.502ms   | ±0.91%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 9.522ms   | ±0.43%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 18.009mb | 65.613ms  | ±0.14%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 27.432mb | 279.971ms | ±0.88%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 64.817mb | 1.084s    | ±0.38%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 29.360mb | 192.404ms | ±0.14%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 34.092mb | 153.610ms | ±0.68%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 19.008mb | 122.199ms | ±0.72%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 21.303mb | 167.054ms | ±1.73%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 20.493mb | 134.664ms | ±1.86%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.943mb | 257.621ms | ±0.18%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.460mb | 40.053ms  | ±0.21%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.375mb | 35.309ms  | ±0.24%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 17.295mb | 33.579ms  | ±0.55%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.747mb | 106.962ms | ±0.49%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 17.363mb | 37.014ms  | ±0.13%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.589mb | 46.233ms  | ±0.28%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 18.058mb | 67.472ms  | ±0.44%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 17.259mb | 30.145ms  | ±0.20%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 17.251mb | 36.193ms  | ±0.22%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 17.278mb | 37.763ms  | ±0.25%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 17.247mb | 36.876ms  | ±2.00%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 17.269mb | 35.631ms  | ±0.41%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 32.220mb | 55.652ms  | ±0.41%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 19.262mb | 35.663ms  | ±0.53%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.941mb | 31.013ms  | ±0.05%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 17.216mb | 33.966ms  | ±0.32%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 17.237mb | 37.662ms  | ±0.40%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 17.230mb | 37.423ms  | ±0.28%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.423mb | 34.555ms  | ±0.17%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.726mb | 172.268ms | ±0.38%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.827mb | 129.949ms | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.887mb  | 1.914ms   | ±2.34%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.079ms   | ±2.08%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.289ms   | ±1.86%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 3.830ms   | ±2.42%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 5.590ms   | ±0.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.351mb  | 2.875ms   | ±1.29%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.072ms   | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 10.022ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.914ms   | ±1.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.961ms   | ±1.70%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 640.110μs | ±8.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.105mb  | 2.559ms   | ±1.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.199mb  | 2.878ms   | ±7.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.564ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 126.315ms | ±24.35% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 2.883ms   | ±1.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 4.914ms   | ±16.58% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.066ms   | ±2.52%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.861ms   | ±1.69%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 8.608ms   | ±0.55%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.376ms   | ±0.82%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 14.977ms  | ±0.43%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 22.079ms  | ±0.81%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 754.967μs | ±4.34%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 817.166μs | ±5.67%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 871.403μs | ±5.94%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.330ms   | ±2.96%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.903ms   | ±2.00%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 19.928ms  | ±1.57%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 22.494ms  | ±0.77%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 25.194ms  | ±0.45%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 46.693ms  | ±0.22%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 72.902ms  | ±0.69%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.381mb  | 8.826ms   | ±0.59%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.600mb  | 12.091ms  | ±0.54%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.921mb  | 15.742ms  | ±0.80%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.614mb | 51.489ms  | ±0.69%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.977mb | 115.221ms | ±0.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 3.988ms   | ±0.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 40.871ms  | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.204μs   | ±19.69% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.322μs   | ±13.61% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.334μs   | ±9.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 225.610ms | ±14.68% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 373.836μs | ±1.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.237ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 2.748ms   | ±2.22%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 8.911ms   | ±3.79%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 63.074ms  | ±0.46%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.801ms  | ±5.34%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.203ms  | ±0.94%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 165.802ms | ±24.41% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 10.824ms  | ±0.32%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 10.691ms  | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.270mb  | 10.859ms  | ±0.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 10.823ms  | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 11.166ms  | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.554ms   | ±1.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.273mb  | 10.900ms  | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.324mb  | 10.872ms  | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 10.647ms  | ±0.74%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```