# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-20 16:58:31 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.478ms | 2.561ms | 2.842ms | 4.782ms | 7.105ms |
| FPDF | 804.588μs | 850.454μs | 935.072μs | 1.534ms | 2.298ms |
| TCPDF | 10.307ms | 11.500ms | 12.138ms | 20.963ms | 31.550ms |
| mPDF | 26.088ms | 29.573ms | 33.984ms | 66.662ms | 106.425ms |
| Dompdf | 11.533ms | 16.187ms | 21.909ms | 74.726ms | 163.315ms |

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
| phpdftk | 3.491ms | 3.707ms | 3.943ms | 6.115ms | 8.529ms |
| FPDF | 1.158ms | 1.230ms | 1.325ms | 1.943ms | 2.821ms |
| TCPDF | 15.524ms | 16.973ms | 17.645ms | 27.927ms | 39.818ms |

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
| Pdf (Level 3) | 3.502ms | 4.655ms | 13.039ms |
| PdfDoc (Level 2) | 2.795ms | 3.311ms | 7.758ms |
| PdfWriter (Level 1) | 2.415ms | 2.931ms | 7.298ms |

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
| Pdf (Level 3) | 4.403ms | 12.300ms | 47.521ms |
| PdfDoc (Level 2) | 3.809ms | 10.117ms | — |

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
| Pdf (Level 3) | 4.167ms | 11.802ms | 45.433ms |
| PdfDoc (Level 2) | 3.335ms | 7.415ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.403ms | 1.707ms | 6.097ms |
| smalot/pdfparser | 2.164ms | 2.518ms | 6.230ms |
| setasign/fpdi | 2.039ms | 2.950ms | 30.265ms |

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
| phpdftk | 2.122ms | 1.472ms |
| smalot/pdfparser | FAIL | 2.050ms |
| setasign/fpdi | 3.154ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.491ms   | ±3.35%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.707ms   | ±2.00%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.943ms   | ±0.99%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 6.115ms   | ±0.93%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.529ms   | ±0.32%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.524ms  | ±1.98%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.973ms  | ±2.13%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.645ms  | ±0.67%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 27.927ms  | ±1.97%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 39.818ms  | ±0.06%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.158ms   | ±3.60%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.230ms   | ±1.26%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.325ms   | ±2.17%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.943ms   | ±0.23%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.821ms   | ±1.05%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.415ms   | ±2.62%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.931ms   | ±1.25%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 7.298ms   | ±1.29%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.795ms   | ±0.94%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.311ms   | ±2.52%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.758ms   | ±2.52%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.502ms   | ±1.40%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.655ms   | ±1.57%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 13.039ms  | ±0.51%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.635mb | 90.946ms  | ±0.89%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 26.869mb | 395.531ms | ±0.24%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 63.909mb | 1.554s    | ±0.91%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.849mb | 278.652ms | ±0.89%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.614mb | 214.295ms | ±0.57%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.534mb | 170.652ms | ±0.72%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 20.754mb | 233.926ms | ±0.27%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 19.987mb | 193.602ms | ±0.39%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.185mb | 367.391ms | ±1.38%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.026mb | 57.210ms  | ±0.79%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.003mb | 49.238ms  | ±0.65%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.930mb | 46.629ms  | ±1.46%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.338mb | 149.541ms | ±0.30%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.927mb | 49.855ms  | ±0.84%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.219mb | 63.993ms  | ±0.32%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.682mb | 95.253ms  | ±0.11%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.887mb | 41.645ms  | ±1.16%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.822mb | 49.510ms  | ±1.09%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.850mb | 50.552ms  | ±1.33%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.818mb | 48.903ms  | ±0.16%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.840mb | 48.442ms  | ±0.31%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.762mb | 74.745ms  | ±0.11%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.871mb | 47.147ms  | ±0.80%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.484mb | 42.234ms  | ±0.54%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.787mb | 45.611ms  | ±0.79%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.808mb | 51.345ms  | ±0.76%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.801mb | 50.215ms  | ±1.75%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.032mb | 45.852ms  | ±0.90%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.203mb | 243.023ms | ±0.97%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.458mb | 179.247ms | ±0.43%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.318mb | 60.294ms  | ±2.45%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.924mb | 123.919ms | ±0.26%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 64.682mb | 1.477s    | ±1.82%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.209mb | 28.576ms  | ±3.85%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.235mb | 64.084ms  | ±0.78%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.981mb | 574.070ms | ±0.72%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.493mb | 68.410ms  | ±9.07%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.146mb | 93.082ms  | ±1.73%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.915mb | 749.683ms | ±0.61%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.257mb | 19.549ms  | ±1.34%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.257mb | 44.682ms  | ±0.75%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.900mb | 325.646ms | ±0.57%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.321ms   | ±0.88%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.707ms   | ±1.41%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.097ms   | ±1.33%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.122ms   | ±1.20%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.472ms   | ±2.59%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.164ms   | ±1.63%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.518ms   | ±1.33%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 6.230ms   | ±0.60%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 586.734μs | ±3.36%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 2.050ms   | ±1.31%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 2.039ms   | ±1.53%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.950ms   | ±1.05%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.265ms  | ±0.83%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.154ms   | ±1.02%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.658ms   | ±2.42%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.584ms   | ±1.11%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.678ms   | ±1.14%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 4.083ms   | ±1.37%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.885μs   | ±15.47% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.403ms   | ±0.34%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.764mb  | 28.461ms  | ±0.38%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.201mb | 252.693ms | ±0.37%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.726mb | 1.258s    | ±1.30%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.209mb | 197.977ms | ±0.31%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 27.044mb | 1.007s    | ±0.59%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 24.131mb | 812.535ms | ±0.58%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.167ms   | ±1.08%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.802ms  | ±2.36%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.433ms  | ±0.52%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.335ms   | ±1.05%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.415ms   | ±1.01%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.342ms   | ±1.43%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.561ms   | ±1.63%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.842ms   | ±1.09%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.782ms   | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.105ms   | ±3.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.669ms   | ±1.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.835ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.471ms  | ±14.66% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.699ms   | ±0.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.459ms   | ±1.87%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 633.736μs | ±2.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.233ms   | ±2.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.728ms   | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.229ms   | ±1.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 232.701ms | ±23.96% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.713ms   | ±1.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.945ms   | ±18.85% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.088ms   | ±1.25%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.307ms  | ±1.10%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.500ms  | ±2.87%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.138ms  | ±1.81%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.963ms  | ±0.94%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.550ms  | ±0.55%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 804.588μs | ±2.46%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 850.454μs | ±2.54%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 935.072μs | ±1.34%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.534ms   | ±0.93%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.298ms   | ±0.81%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 26.088ms  | ±3.11%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.573ms  | ±0.44%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.984ms  | ±1.44%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 66.662ms  | ±2.36%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 106.425ms | ±0.63%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.533ms  | ±1.57%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.187ms  | ±1.05%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.909ms  | ±0.68%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 74.726ms  | ±2.05%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 163.315ms | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.133ms   | ±4.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 53.513ms  | ±3.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 160.610ms | ±5.49%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 465.435μs | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.021ms   | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.429ms   | ±0.61%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.420ms  | ±5.18%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 86.561ms  | ±2.13%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.124ms  | ±1.23%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.503ms  | ±0.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 213.900ms | ±26.04% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.900ms  | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.437ms  | ±1.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.772ms  | ±2.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.932ms  | ±1.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.082ms  | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.335ms   | ±1.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.663ms  | ±1.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.742ms  | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.478ms  | ±2.18%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.697mb  | 14.205ms  | ±0.28%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.498mb  | 12.782ms  | ±0.60%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.818mb  | 20.413ms  | ±1.23%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.733mb  | 18.299ms  | ±0.27%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.888mb  | 24.722ms  | ±0.42%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.924mb  | 13.328ms  | ±0.35%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.124mb  | 16.842ms  | ±0.33%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 11.764mb | 37.592ms  | ±0.61%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.846mb  | 3.259ms   | ±1.60%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.430μs  | ±0.90%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 247.187μs | ±1.04%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.403ms   | ±1.32%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.300ms  | ±1.03%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.521ms  | ±0.61%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.809ms   | ±1.51%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 10.117ms  | ±0.81%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.970ms   | ±0.56%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.715ms   | ±0.86%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.765ms   | ±2.59%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.160μs   | ±14.57% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.032μs   | ±38.20% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.396mb | 18.360ms  | ±0.29%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.396mb | 18.334ms  | ±0.10%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```