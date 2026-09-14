# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-14 17:20:14 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.800ms | 2.120ms | 2.335ms | 4.208ms | 6.866ms |
| FPDF | 682.429μs | 774.296μs | 789.292μs | 1.320ms | 1.960ms |
| TCPDF | 8.830ms | 9.502ms | 10.706ms | 16.888ms | 25.352ms |
| mPDF | 21.388ms | 24.068ms | 27.055ms | 49.548ms | 76.311ms |
| Dompdf | 9.706ms | 12.599ms | 16.852ms | 53.377ms | 117.789ms |

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
| phpdftk | 2.621ms | 2.836ms | 3.006ms | 4.706ms | 6.793ms |
| FPDF | 923.492μs | 945.485μs | 1.003ms | 1.603ms | 2.289ms |
| TCPDF | 12.782ms | 13.854ms | 14.523ms | 21.568ms | 31.157ms |

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
| Pdf (Level 3) | 2.647ms | 3.564ms | 9.470ms |
| PdfDoc (Level 2) | 2.075ms | 2.461ms | 6.114ms |
| PdfWriter (Level 1) | 1.804ms | 2.117ms | 5.528ms |

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
| Pdf (Level 3) | 3.715ms | 9.280ms | 35.230ms |
| PdfDoc (Level 2) | 2.985ms | 7.668ms | — |

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
| Pdf (Level 3) | 3.369ms | 8.945ms | 33.685ms |
| PdfDoc (Level 2) | 2.739ms | 5.932ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.636ms | 1.201ms | 4.090ms |
| smalot/pdfparser | 1.641ms | 1.929ms | 4.551ms |
| setasign/fpdi | 1.508ms | 2.087ms | 20.277ms |

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
| phpdftk | 1.434ms | 1.010ms |
| smalot/pdfparser | FAIL | 1.511ms |
| setasign/fpdi | 2.197ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 2.621ms   | ±1.94%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 2.836ms   | ±1.20%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.006ms   | ±6.36%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 4.706ms   | ±11.97% |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 6.793ms   | ±0.91%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 12.782ms  | ±2.52%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 13.854ms  | ±1.30%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 14.523ms  | ±0.87%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 21.568ms  | ±0.41%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 31.157ms  | ±0.08%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 923.492μs | ±7.96%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 945.485μs | ±9.07%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.003ms   | ±1.34%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.603ms   | ±0.48%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.289ms   | ±1.69%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 1.804ms   | ±2.97%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.117ms   | ±8.51%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 5.528ms   | ±18.26% |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.075ms   | ±1.99%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.461ms   | ±51.67% |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 6.114ms   | ±27.66% |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 2.647ms   | ±0.84%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 3.564ms   | ±7.88%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 9.470ms   | ±2.10%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.647mb | 58.679ms  | ±1.43%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.592mb | 252.552ms | ±0.42%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.747mb | 985.139ms | ±0.49%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.807mb | 175.976ms | ±0.33%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.675mb | 133.805ms | ±0.05%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.456mb | 108.869ms | ±1.47%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.555mb | 147.530ms | ±0.19%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.072mb | 122.048ms | ±0.39%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.688mb | 231.213ms | ±0.32%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.149mb | 35.870ms  | ±0.82%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.065mb | 31.547ms  | ±1.03%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.990mb | 30.404ms  | ±0.63%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.344mb | 96.962ms  | ±0.71%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.054mb | 33.844ms  | ±0.64%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.272mb | 42.348ms  | ±1.25%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.706mb | 61.722ms  | ±1.17%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.956mb | 27.472ms  | ±0.88%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.959mb | 32.916ms  | ±0.60%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.987mb | 34.512ms  | ±0.71%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.956mb | 33.457ms  | ±0.57%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.978mb | 32.481ms  | ±0.08%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.896mb | 53.412ms  | ±0.48%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.937mb | 31.916ms  | ±0.79%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.623mb | 29.196ms  | ±0.47%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.924mb | 30.822ms  | ±1.44%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.945mb | 34.645ms  | ±0.72%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.938mb | 34.271ms  | ±0.75%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.105mb | 31.616ms  | ±0.75%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.329mb | 158.324ms | ±0.74%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.535mb | 117.415ms | ±0.30%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.342mb | 39.980ms  | ±0.97%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.953mb | 80.936ms  | ±0.32%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 43.154mb | 956.275ms | ±0.16%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.459mb | 20.287ms  | ±3.20%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.419mb | 43.484ms  | ±1.03%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.230mb | 390.118ms | ±0.60%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.743mb | 51.187ms  | ±9.19%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.396mb | 65.059ms  | ±1.07%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.165mb | 549.498ms | ±0.40%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.441mb | 15.592ms  | ±1.71%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.441mb | 34.255ms  | ±0.83%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.084mb | 239.799ms | ±3.72%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 911.448μs | ±1.07%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.201ms   | ±0.81%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 4.090ms   | ±0.81%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.434ms   | ±1.13%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.010ms   | ±0.77%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.641ms   | ±24.96% |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.929ms   | ±6.60%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.551ms   | ±0.47%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 465.964μs | ±1.57%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.511ms   | ±2.22%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.508ms   | ±1.26%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.087ms   | ±1.16%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 20.277ms  | ±2.14%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.197ms   | ±0.86%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.203ms   | ±1.30%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 5.355ms   | ±1.88%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 4.454ms   | ±2.32%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.226ms   | ±1.81%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.073μs   | ±23.57% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.636ms   | ±0.57%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.542mb  | 19.138ms  | ±0.22%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.711mb | 167.597ms | ±1.13%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.039mb | 826.439ms | ±1.01%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.820mb | 132.708ms | ±0.20%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.369ms   | ±0.74%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 8.945ms   | ±2.03%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 33.685ms  | ±0.97%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.739ms   | ±1.67%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 5.932ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 1.942ms   | ±2.42%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.120ms   | ±3.81%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.335ms   | ±76.61% |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.208ms   | ±1.07%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.866ms   | ±88.68% |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 2.902ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.658ms   | ±81.79% |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 9.557ms   | ±62.95% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.945ms   | ±2.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.864ms   | ±3.47%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 540.614μs | ±4.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.608ms   | ±2.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 2.908ms   | ±1.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.778ms   | ±3.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 209.450ms | ±19.01% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 2.927ms   | ±2.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 4.560ms   | ±81.57% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 4.573ms   | ±2.18%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 8.830ms   | ±2.28%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 9.502ms   | ±2.62%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 10.706ms  | ±2.37%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 16.888ms  | ±1.01%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 25.352ms  | ±1.78%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 682.429μs | ±11.33% |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 774.296μs | ±5.12%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 789.292μs | ±5.56%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.320ms   | ±2.33%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.960ms   | ±1.75%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 21.388ms  | ±3.32%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 24.068ms  | ±0.95%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 27.055ms  | ±1.18%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 49.548ms  | ±0.94%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 76.311ms  | ±0.56%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 9.706ms   | ±1.79%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 12.599ms  | ±1.66%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 16.852ms  | ±1.31%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 53.377ms  | ±0.59%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 117.789ms | ±0.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.007ms   | ±1.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 35.943ms  | ±1.06%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.999μs   | ±14.29% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.000μs   | ±21.08% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.000μs   | ±21.08% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 179.381ms | ±27.03% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 335.388μs | ±1.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.331ms   | ±2.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 2.758ms   | ±2.23%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 16.419ms  | ±25.48% |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 60.122ms  | ±0.49%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 10.317ms  | ±1.20%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 18.081ms  | ±1.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 139.114ms | ±22.89% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 10.086ms  | ±1.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 9.858ms   | ±2.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 10.149ms  | ±2.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 10.283ms  | ±1.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 10.661ms  | ±1.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.613ms   | ±0.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 10.394ms  | ±2.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 9.997ms   | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 9.800ms   | ±1.27%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 8.360ms   | ±0.79%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 8.678ms   | ±9.21%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 9.560ms   | ±0.89%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 9.672ms   | ±0.57%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 8.870ms   | ±0.10%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 8.431ms   | ±0.53%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 15.076ms  | ±0.44%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 2.430ms   | ±0.63%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 29.560μs  | ±0.48%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 184.002μs | ±3.54%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 3.715ms   | ±2.95%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 9.280ms   | ±2.22%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 35.230ms  | ±0.51%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 2.985ms   | ±0.50%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 7.668ms   | ±1.69%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 6.629ms   | ±2.15%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 6.447ms   | ±0.71%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 6.479ms   | ±2.31%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 0.960μs   | ±29.99% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.008μs   | ±54.92% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.581mb | 13.103ms  | ±0.78%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.581mb | 13.176ms  | ±1.24%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```