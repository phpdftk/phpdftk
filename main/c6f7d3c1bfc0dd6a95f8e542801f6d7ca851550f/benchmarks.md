# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-21 14:31:42 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.209ms | 2.488ms | 2.769ms | 4.768ms | 7.091ms |
| FPDF | 758.811μs | 855.619μs | 928.159μs | 1.513ms | 2.253ms |
| TCPDF | 9.905ms | 10.976ms | 11.921ms | 20.379ms | 30.780ms |
| mPDF | 24.952ms | 28.802ms | 32.796ms | 64.907ms | 105.719ms |
| Dompdf | 11.307ms | 15.924ms | 21.319ms | 72.980ms | 162.436ms |

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
| phpdftk | 3.325ms | 3.529ms | 3.812ms | 5.924ms | 8.240ms |
| FPDF | 1.028ms | 1.100ms | 1.215ms | 1.865ms | 2.742ms |
| TCPDF | 14.372ms | 15.280ms | 16.466ms | 26.083ms | 38.405ms |

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
| Pdf (Level 3) | 3.339ms | 4.363ms | 12.661ms |
| PdfDoc (Level 2) | 2.673ms | 3.152ms | 7.499ms |
| PdfWriter (Level 1) | 2.276ms | 2.746ms | 6.834ms |

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
| Pdf (Level 3) | 4.336ms | 12.045ms | 46.764ms |
| PdfDoc (Level 2) | 3.755ms | 9.878ms | — |

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
| Pdf (Level 3) | 4.005ms | 11.557ms | 45.156ms |
| PdfDoc (Level 2) | 3.281ms | 7.270ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.127ms | 1.654ms | 5.957ms |
| smalot/pdfparser | 1.974ms | 2.347ms | 5.672ms |
| setasign/fpdi | 1.935ms | 2.844ms | 30.013ms |

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
| phpdftk | 2.011ms | 1.357ms |
| smalot/pdfparser | FAIL | 1.874ms |
| setasign/fpdi | 2.983ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.325ms   | ±0.14%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.529ms   | ±0.51%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.812ms   | ±1.64%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.924ms   | ±3.42%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.240ms   | ±0.49%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.372ms  | ±1.01%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.280ms  | ±0.30%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.466ms  | ±0.49%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.083ms  | ±0.84%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 38.405ms  | ±0.47%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.028ms   | ±1.45%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.100ms   | ±0.43%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.215ms   | ±1.14%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.865ms   | ±1.10%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.742ms   | ±0.96%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.276ms   | ±0.45%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.746ms   | ±1.21%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.834ms   | ±0.51%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.673ms   | ±0.91%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.152ms   | ±0.79%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.499ms   | ±0.28%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.339ms   | ±0.52%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.363ms   | ±0.08%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.661ms  | ±0.62%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.635mb | 88.883ms  | ±0.63%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 26.869mb | 383.222ms | ±1.46%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 63.909mb | 1.516s    | ±0.40%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.849mb | 265.513ms | ±0.49%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.614mb | 206.943ms | ±2.58%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.534mb | 165.713ms | ±0.43%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 20.754mb | 228.022ms | ±0.53%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 19.987mb | 186.185ms | ±1.06%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.185mb | 353.275ms | ±0.90%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.026mb | 53.424ms  | ±0.30%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.003mb | 46.556ms  | ±0.75%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.930mb | 43.912ms  | ±0.72%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.338mb | 145.284ms | ±0.28%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.927mb | 49.526ms  | ±0.94%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.219mb | 62.358ms  | ±0.41%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.682mb | 91.961ms  | ±0.68%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.887mb | 39.413ms  | ±0.94%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.822mb | 47.350ms  | ±0.34%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.850mb | 49.332ms  | ±0.64%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.818mb | 48.077ms  | ±1.13%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.840mb | 46.806ms  | ±1.13%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.762mb | 73.614ms  | ±1.81%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.871mb | 45.140ms  | ±0.75%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.484mb | 40.018ms  | ±0.80%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.787mb | 43.731ms  | ±0.90%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.808mb | 49.347ms  | ±0.44%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.801mb | 48.787ms  | ±0.19%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.032mb | 43.555ms  | ±0.45%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.203mb | 238.662ms | ±0.51%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.458mb | 174.721ms | ±0.29%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.318mb | 58.985ms  | ±0.36%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.924mb | 121.181ms | ±0.76%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 64.682mb | 1.464s    | ±0.79%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.209mb | 25.834ms  | ±1.68%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.235mb | 58.499ms  | ±0.23%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.981mb | 528.041ms | ±1.91%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.493mb | 64.971ms  | ±9.75%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.146mb | 86.021ms  | ±1.48%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.915mb | 728.527ms | ±0.27%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.257mb | 18.518ms  | ±4.06%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.257mb | 42.599ms  | ±0.30%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.900mb | 319.593ms | ±0.65%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.252ms   | ±2.05%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.654ms   | ±0.28%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.957ms   | ±1.15%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.011ms   | ±0.73%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.357ms   | ±0.21%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.974ms   | ±0.75%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.347ms   | ±0.51%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.672ms   | ±0.25%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 546.548μs | ±1.76%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.874ms   | ±0.82%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.935ms   | ±1.32%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.844ms   | ±0.69%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.013ms  | ±0.45%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.983ms   | ±0.91%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.490ms   | ±0.68%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.268ms   | ±0.51%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.388ms   | ±0.52%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.803ms   | ±0.88%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.397μs   | ±18.73% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.127ms   | ±0.69%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.764mb  | 27.846ms  | ±0.20%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.201mb | 249.565ms | ±0.77%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.726mb | 1.242s    | ±0.47%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.209mb | 194.369ms | ±0.82%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 27.044mb | 1.004s    | ±0.34%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 24.131mb | 809.508ms | ±0.24%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.005ms   | ±0.62%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.557ms  | ±1.32%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.156ms  | ±0.54%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.281ms   | ±2.46%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.270ms   | ±0.20%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.308ms   | ±1.25%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.488ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.769ms   | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.768ms   | ±1.08%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.091ms   | ±1.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.589ms   | ±1.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.814ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.190ms  | ±7.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.587ms   | ±0.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.350ms   | ±0.43%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 637.135μs | ±4.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.181ms   | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.675ms   | ±0.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.237ms   | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 161.473ms | ±22.59% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.571ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.884ms   | ±1.17%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.093ms   | ±0.62%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.905ms   | ±0.94%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.976ms  | ±10.49% |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.921ms  | ±0.52%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.379ms  | ±0.33%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 30.780ms  | ±0.82%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 758.811μs | ±4.13%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 855.619μs | ±2.14%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 928.159μs | ±6.02%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.513ms   | ±0.42%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.253ms   | ±0.66%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 24.952ms  | ±1.73%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 28.802ms  | ±0.70%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.796ms  | ±0.73%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 64.907ms  | ±0.70%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 105.719ms | ±0.92%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.307ms  | ±0.73%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.924ms  | ±0.62%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.319ms  | ±0.70%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 72.980ms  | ±0.58%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 162.436ms | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.992ms   | ±0.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.413ms  | ±1.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.665μs   | ±17.39% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 183.140ms | ±28.54% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 453.724μs | ±1.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.969ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.385ms   | ±0.35%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.792ms  | ±1.98%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 85.790ms  | ±0.91%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.192ms  | ±7.65%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.021ms  | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 255.123ms | ±31.59% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.439ms  | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.492ms  | ±0.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.484ms  | ±1.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.416ms  | ±0.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.811ms  | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.215ms   | ±0.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.600ms  | ±2.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.606ms  | ±2.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.209ms  | ±0.51%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.697mb  | 13.845ms  | ±0.96%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.498mb  | 12.413ms  | ±0.55%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.818mb  | 20.099ms  | ±0.36%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.733mb  | 18.107ms  | ±4.03%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.888mb  | 24.431ms  | ±1.65%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.924mb  | 13.232ms  | ±1.14%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.124mb  | 16.427ms  | ±0.30%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 11.764mb | 37.245ms  | ±1.04%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.846mb  | 3.122ms   | ±0.08%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.375μs  | ±0.75%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 241.719μs | ±0.46%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.336ms   | ±0.51%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.045ms  | ±0.52%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 46.764ms  | ±0.45%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.755ms   | ±0.29%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.878ms   | ±1.13%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.823ms   | ±0.83%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.460ms   | ±0.27%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.458ms   | ±0.63%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.049μs   | ±16.64% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.976μs   | ±23.16% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.396mb | 17.771ms  | ±0.89%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.396mb | 17.738ms  | ±0.16%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```