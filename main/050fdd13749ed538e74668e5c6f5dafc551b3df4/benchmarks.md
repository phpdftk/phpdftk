# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-20 15:29:43 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.123ms | 2.516ms | 2.779ms | 4.748ms | 7.050ms |
| FPDF | 785.932μs | 847.742μs | 929.968μs | 1.525ms | 2.292ms |
| TCPDF | 9.914ms | 10.870ms | 11.956ms | 20.467ms | 31.240ms |
| mPDF | 25.108ms | 28.931ms | 33.128ms | 65.375ms | 105.359ms |
| Dompdf | 11.282ms | 15.889ms | 21.419ms | 73.819ms | 161.639ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.218mb | 5.947mb | 6.033mb | 6.667mb | 7.490mb |
| FPDF | 5.072mb | 5.072mb | 5.072mb | 5.072mb | 5.084mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.683mb | 17.721mb | 18.014mb | 18.376mb |
| Dompdf | 9.357mb | 9.577mb | 9.898mb | 12.591mb | 15.954mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.350ms | 3.610ms | 3.815ms | 5.876ms | 8.411ms |
| FPDF | 1.032ms | 1.114ms | 1.206ms | 1.920ms | 2.763ms |
| TCPDF | 14.397ms | 15.524ms | 16.709ms | 26.758ms | 38.585ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.373mb | 5.420mb | 5.479mb | 5.972mb | 6.570mb |
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
| Pdf (Level 3) | 3.373ms | 4.433ms | 12.490ms |
| PdfDoc (Level 2) | 2.694ms | 3.200ms | 7.473ms |
| PdfWriter (Level 1) | 2.300ms | 2.743ms | 6.909ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 6.057mb | 6.220mb | 7.897mb |
| PdfDoc (Level 2) | 5.714mb | 5.872mb | 7.441mb |
| PdfWriter (Level 1) | 5.389mb | 5.548mb | 7.123mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 4.406ms | 12.191ms | 46.647ms |
| PdfDoc (Level 2) | 3.790ms | 9.950ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.408mb | 9.203mb | 21.611mb |
| PdfDoc (Level 2) | 6.214mb | 9.029mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 4.112ms | 11.706ms | 45.246ms |
| PdfDoc (Level 2) | 3.297ms | 7.408ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.139ms | 1.649ms | 5.983ms |
| smalot/pdfparser | 2.003ms | 2.351ms | 5.718ms |
| setasign/fpdi | 1.934ms | 2.863ms | 29.524ms |

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
| phpdftk | 2.027ms | 1.355ms |
| smalot/pdfparser | FAIL | 1.906ms |
| setasign/fpdi | 2.989ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.350ms   | ±3.97%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.610ms   | ±3.63%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.815ms   | ±1.74%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.876ms   | ±0.23%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.411ms   | ±0.37%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.397ms  | ±1.73%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.524ms  | ±3.56%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.709ms  | ±1.00%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.758ms  | ±0.91%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 38.585ms  | ±0.95%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.032ms   | ±2.79%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.114ms   | ±7.61%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.206ms   | ±1.52%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.920ms   | ±1.58%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.763ms   | ±0.37%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.300ms   | ±1.81%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.743ms   | ±1.05%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.909ms   | ±0.59%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.694ms   | ±1.57%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.200ms   | ±0.84%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.473ms   | ±1.42%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.373ms   | ±1.10%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.433ms   | ±27.88% |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.490ms  | ±0.59%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.617mb | 89.222ms  | ±1.48%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 26.851mb | 384.984ms | ±0.38%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 63.891mb | 1.527s    | ±0.49%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.831mb | 267.677ms | ±0.85%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.596mb | 202.990ms | ±0.48%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.516mb | 165.506ms | ±0.21%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 20.736mb | 229.493ms | ±0.48%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 19.969mb | 190.803ms | ±0.88%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.167mb | 355.338ms | ±0.67%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.008mb | 54.728ms  | ±0.36%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.985mb | 47.726ms  | ±0.94%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.912mb | 44.311ms  | ±1.20%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.320mb | 146.874ms | ±0.27%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.909mb | 49.295ms  | ±0.07%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.201mb | 62.046ms  | ±0.64%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.664mb | 92.222ms  | ±0.09%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.869mb | 39.563ms  | ±0.39%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.804mb | 47.401ms  | ±1.49%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.832mb | 49.422ms  | ±0.53%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.800mb | 48.155ms  | ±0.49%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.822mb | 46.514ms  | ±0.52%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.744mb | 73.416ms  | ±0.72%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.853mb | 45.495ms  | ±0.55%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.466mb | 41.295ms  | ±1.07%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.769mb | 44.364ms  | ±0.19%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.790mb | 49.201ms  | ±0.25%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.783mb | 49.195ms  | ±0.34%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.014mb | 44.241ms  | ±1.24%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.185mb | 237.908ms | ±0.51%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.440mb | 173.755ms | ±1.13%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.300mb | 59.300ms  | ±0.44%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.906mb | 121.148ms | ±0.38%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 64.665mb | 1.462s    | ±0.53%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.192mb | 25.907ms  | ±1.92%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.218mb | 59.199ms  | ±1.32%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.964mb | 527.798ms | ±0.25%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.476mb | 64.044ms  | ±9.56%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.130mb | 87.144ms  | ±1.26%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.899mb | 735.589ms | ±0.30%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.241mb | 18.873ms  | ±0.89%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.241mb | 43.260ms  | ±0.12%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.883mb | 324.723ms | ±0.49%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.250ms   | ±1.04%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.649ms   | ±0.84%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.983ms   | ±0.73%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.027ms   | ±1.02%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.355ms   | ±1.58%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.003ms   | ±0.75%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.351ms   | ±0.68%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.718ms   | ±0.73%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 538.588μs | ±0.99%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.906ms   | ±0.61%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.934ms   | ±1.51%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.863ms   | ±1.40%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.524ms  | ±0.72%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.989ms   | ±0.91%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.516ms   | ±1.10%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.241ms   | ±0.27%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.412ms   | ±0.85%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.869ms   | ±0.48%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.366μs   | ±12.00% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.139ms   | ±0.69%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.764mb  | 27.945ms  | ±0.98%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.201mb | 250.504ms | ±0.73%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.726mb | 1.240s    | ±0.60%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.209mb | 195.416ms | ±0.24%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 27.044mb | 1.017s    | ±0.64%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 24.131mb | 812.527ms | ±0.70%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.112ms   | ±1.10%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.706ms  | ±1.30%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.246ms  | ±0.55%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.297ms   | ±0.60%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.408ms   | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.304ms   | ±1.14%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.516ms   | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.779ms   | ±1.25%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.748ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.050ms   | ±0.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.555ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.829ms   | ±1.29%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.263ms  | ±1.17%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.631ms   | ±1.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.388ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 657.402μs | ±2.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.172ms   | ±1.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.650ms   | ±0.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.189ms   | ±1.21%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 183.453ms | ±29.22% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.664ms   | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.804ms   | ±35.76% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.024ms   | ±1.05%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.914ms   | ±0.69%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.870ms  | ±1.00%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.956ms  | ±0.67%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.467ms  | ±1.13%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.240ms  | ±0.53%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 785.932μs | ±1.87%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 847.742μs | ±1.02%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 929.968μs | ±1.45%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.525ms   | ±0.79%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.292ms   | ±1.54%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.108ms  | ±1.61%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 28.931ms  | ±2.53%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.128ms  | ±1.38%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 65.375ms  | ±1.19%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 105.359ms | ±2.06%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.282ms  | ±0.56%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.889ms  | ±0.23%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.419ms  | ±1.08%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 73.819ms  | ±1.00%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 161.639ms | ±0.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.062ms   | ±3.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 49.687ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 228.658ms | ±38.88% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 453.038μs | ±1.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.010ms   | ±0.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.396ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.911ms  | ±6.04%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 85.345ms  | ±0.40%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.135ms  | ±0.96%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 24.945ms  | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 211.976ms | ±12.32% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.317ms  | ±2.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.170ms  | ±0.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.613ms  | ±0.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.675ms  | ±0.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.838ms  | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.129ms   | ±1.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.487ms  | ±0.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.522ms  | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.123ms  | ±0.36%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.697mb  | 13.785ms  | ±0.52%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.498mb  | 12.510ms  | ±0.56%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.818mb  | 20.100ms  | ±0.27%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.733mb  | 18.198ms  | ±0.68%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.888mb  | 24.301ms  | ±0.36%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.924mb  | 13.188ms  | ±0.34%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.124mb  | 16.434ms  | ±0.17%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 11.764mb | 37.155ms  | ±0.40%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.846mb  | 3.194ms   | ±0.59%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.156μs  | ±0.95%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 241.931μs | ±0.69%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.406ms   | ±0.69%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.191ms  | ±0.91%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 46.647ms  | ±0.82%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.790ms   | ±0.71%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.950ms   | ±0.95%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.969ms   | ±0.65%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.458ms   | ±0.42%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.552ms   | ±0.46%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.959μs   | ±12.07% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.061μs   | ±20.20% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.378mb | 18.131ms  | ±2.45%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.378mb | 17.874ms  | ±0.63%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```