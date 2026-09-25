# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-25 20:43:23 UTC
PHP: 8.4.26
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.216ms | 2.639ms | 2.866ms | 4.899ms | 7.141ms |
| FPDF | 842.753μs | 905.264μs | 1.017ms | 1.616ms | 2.364ms |
| TCPDF | 10.068ms | 10.953ms | 12.078ms | 20.707ms | 31.470ms |
| mPDF | 25.312ms | 29.157ms | 33.504ms | 65.599ms | 105.267ms |
| Dompdf | 11.589ms | 16.460ms | 21.680ms | 75.416ms | 165.555ms |

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
| phpdftk | 3.457ms | 3.617ms | 3.935ms | 5.909ms | 8.463ms |
| FPDF | 1.124ms | 1.226ms | 1.308ms | 1.993ms | 2.820ms |
| TCPDF | 14.658ms | 15.564ms | 17.046ms | 26.789ms | 38.952ms |

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
| Pdf (Level 3) | 3.454ms | 4.513ms | 12.694ms |
| PdfDoc (Level 2) | 2.763ms | 3.255ms | 7.721ms |
| PdfWriter (Level 1) | 2.399ms | 2.861ms | 6.977ms |

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
| Pdf (Level 3) | 4.443ms | 12.255ms | 47.207ms |
| PdfDoc (Level 2) | 3.882ms | 10.059ms | — |

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
| Pdf (Level 3) | 4.133ms | 11.813ms | 45.488ms |
| PdfDoc (Level 2) | 3.362ms | 7.421ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.146ms | 1.666ms | 5.959ms |
| smalot/pdfparser | 1.994ms | 2.361ms | 5.711ms |
| setasign/fpdi | 1.921ms | 2.867ms | 29.718ms |

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
| phpdftk | 2.007ms | 1.368ms |
| smalot/pdfparser | FAIL | 1.928ms |
| setasign/fpdi | 2.982ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.240ms   | ±2.27%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.666ms   | ±0.54%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.959ms   | ±0.86%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.007ms   | ±0.77%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.368ms   | ±0.56%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.994ms   | ±0.89%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.361ms   | ±1.38%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.711ms   | ±0.76%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 550.135μs | ±0.77%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.928ms   | ±0.84%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.921ms   | ±1.07%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.867ms   | ±1.10%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.718ms  | ±1.24%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.982ms   | ±0.56%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.513ms   | ±0.91%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.306ms   | ±5.10%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.494ms   | ±0.64%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.881ms   | ±0.48%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.297μs   | ±20.20% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.146ms   | ±0.66%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.960μs   | ±15.93% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.924μs   | ±33.07% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.857mb | 19.052ms  | ±0.47%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.857mb | 19.013ms  | ±0.10%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 9.381mb  | 16.266ms  | ±0.86%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.985mb  | 14.250ms  | ±0.42%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 10.198mb | 23.092ms  | ±3.13%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 10.201mb | 21.177ms  | ±0.97%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 11.772mb | 62.846ms  | ±0.83%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 9.633mb  | 15.568ms  | ±0.38%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.847mb  | 19.400ms  | ±2.51%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 12.992mb | 80.454ms  | ±1.74%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 8.045mb  | 6.845ms   | ±0.71%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.415μs  | ±1.00%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 244.756μs | ±3.14%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.374mb  | 3.457ms   | ±2.68%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.617ms   | ±1.31%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.935ms   | ±2.18%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.909ms   | ±0.70%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.571mb  | 8.463ms   | ±0.59%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.658ms  | ±0.90%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.564ms  | ±0.18%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.046ms  | ±0.94%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.789ms  | ±1.38%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 38.952ms  | ±0.32%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.124ms   | ±6.12%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.226ms   | ±5.71%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.308ms   | ±3.93%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.993ms   | ±2.83%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.820ms   | ±1.78%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.694mb | 60.985ms  | ±0.68%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 18.302mb | 126.129ms | ±0.46%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 65.481mb | 1.502s    | ±0.26%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.521mb | 26.274ms  | ±1.44%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.547mb | 59.284ms  | ±0.91%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 58.358mb | 545.120ms | ±0.95%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.790mb | 63.523ms  | ±9.72%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.510mb | 86.259ms  | ±1.38%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 34.278mb | 732.664ms | ±0.41%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.547mb | 18.810ms  | ±0.47%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.547mb | 43.645ms  | ±0.72%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 31.189mb | 320.974ms | ±1.00%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.959ms   | ±0.54%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.672ms   | ±1.49%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.671ms   | ±3.95%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.937mb  | 27.547ms  | ±1.08%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.461mb | 249.211ms | ±1.21%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 50.370mb | 1.238s    | ±0.98%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.433mb | 191.944ms | ±1.22%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 28.602mb | 1.092s    | ±1.06%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 25.406mb | 886.764ms | ±0.23%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.443ms   | ±0.68%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.255ms  | ±2.79%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.207ms  | ±1.21%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.882ms   | ±1.09%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.030mb  | 10.059ms  | ±0.47%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.133ms   | ±0.64%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.813ms  | ±0.91%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.488ms  | ±0.44%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.362ms   | ±30.18% |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.421ms   | ±1.96%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.399ms   | ±1.28%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.861ms   | ±1.61%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.124mb  | 6.977ms   | ±0.81%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.763ms   | ±1.41%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.255ms   | ±1.40%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.721ms   | ±1.23%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.454ms   | ±0.96%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.513ms   | ±0.64%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.694ms  | ±0.89%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 18.009mb | 92.332ms  | ±0.35%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 27.431mb | 399.212ms | ±0.64%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 64.817mb | 1.564s    | ±0.56%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 29.360mb | 273.149ms | ±0.62%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 34.091mb | 203.041ms | ±0.52%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 19.008mb | 170.482ms | ±0.10%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 21.303mb | 236.124ms | ±0.35%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 20.493mb | 193.615ms | ±0.68%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.943mb | 366.993ms | ±0.08%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.460mb | 56.247ms  | ±0.46%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.375mb | 49.082ms  | ±1.05%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 17.294mb | 45.482ms  | ±0.79%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.746mb | 151.175ms | ±0.85%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 17.363mb | 51.234ms  | ±1.14%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.589mb | 65.024ms  | ±0.31%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 18.058mb | 96.869ms  | ±0.25%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 17.258mb | 41.648ms  | ±0.54%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 17.251mb | 49.322ms  | ±0.45%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 17.278mb | 51.350ms  | ±0.09%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 17.247mb | 49.800ms  | ±0.20%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 17.269mb | 48.896ms  | ±0.55%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 32.219mb | 74.929ms  | ±0.54%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 19.262mb | 47.517ms  | ±0.66%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.941mb | 42.392ms  | ±0.72%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 17.215mb | 45.910ms  | ±1.18%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 17.236mb | 51.346ms  | ±0.88%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 17.229mb | 51.863ms  | ±1.25%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.423mb | 45.592ms  | ±0.55%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.725mb | 244.161ms | ±0.67%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.827mb | 178.371ms | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.887mb  | 2.396ms   | ±1.91%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.639ms   | ±1.87%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.866ms   | ±1.14%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.899ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.141ms   | ±0.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.351mb  | 3.658ms   | ±13.34% |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.966ms   | ±1.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.319ms  | ±25.22% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.715ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.508ms   | ±6.36%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 713.728μs | ±4.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.105mb  | 3.260ms   | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.199mb  | 3.736ms   | ±1.21%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.308ms   | ±0.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 186.311ms | ±25.55% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.691ms   | ±2.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.041ms   | ±20.03% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.228ms   | ±7.54%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.068ms  | ±0.93%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.953ms  | ±1.82%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.078ms  | ±1.33%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.707ms  | ±0.52%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.470ms  | ±0.72%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 842.753μs | ±2.33%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 905.264μs | ±27.83% |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 1.017ms   | ±3.14%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.616ms   | ±58.98% |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.364ms   | ±1.47%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.312ms  | ±2.20%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.157ms  | ±0.44%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.504ms  | ±0.48%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 65.599ms  | ±0.57%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 105.267ms | ±0.59%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.381mb  | 11.589ms  | ±3.70%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.600mb  | 16.460ms  | ±1.81%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.921mb  | 21.680ms  | ±1.03%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.614mb | 75.416ms  | ±0.77%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.977mb | 165.555ms | ±1.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.133ms   | ±1.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 49.382ms  | ±0.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.335μs   | ±18.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 164.290ms | ±28.23% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 452.125μs | ±1.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.005ms   | ±0.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.509ms   | ±0.77%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.704ms  | ±4.95%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 85.053ms  | ±0.96%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.800ms  | ±1.01%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.699ms  | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 186.922ms | ±20.97% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.360ms  | ±1.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.151ms  | ±1.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.270mb  | 13.354ms  | ±1.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.450ms  | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.827ms  | ±0.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.260ms   | ±1.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.273mb  | 13.394ms  | ±0.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.324mb  | 13.415ms  | ±0.29%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.216ms  | ±0.40%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```