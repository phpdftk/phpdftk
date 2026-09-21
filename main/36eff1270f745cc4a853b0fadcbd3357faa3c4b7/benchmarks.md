# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-21 15:15:12 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.738ms | 2.496ms | 2.741ms | 4.684ms | 6.914ms |
| FPDF | 789.110μs | 857.256μs | 930.700μs | 1.524ms | 2.259ms |
| TCPDF | 10.022ms | 10.953ms | 11.814ms | 19.103ms | 28.128ms |
| mPDF | 25.515ms | 29.211ms | 32.620ms | 60.253ms | 95.020ms |
| Dompdf | 11.099ms | 15.104ms | 20.002ms | 66.505ms | 149.490ms |

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
| phpdftk | 3.268ms | 3.498ms | 3.847ms | 5.753ms | 8.213ms |
| FPDF | 1.051ms | 1.126ms | 1.238ms | 1.906ms | 2.731ms |
| TCPDF | 14.698ms | 15.380ms | 18.361ms | 25.142ms | 35.257ms |

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
| Pdf (Level 3) | 3.341ms | 4.352ms | 12.607ms |
| PdfDoc (Level 2) | 2.667ms | 3.126ms | 7.379ms |
| PdfWriter (Level 1) | 2.285ms | 2.704ms | 6.742ms |

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
| Pdf (Level 3) | 4.275ms | 11.809ms | 45.290ms |
| PdfDoc (Level 2) | 3.751ms | 9.814ms | — |

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
| Pdf (Level 3) | 4.053ms | 11.498ms | 44.424ms |
| PdfDoc (Level 2) | 3.255ms | 7.265ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.102ms | 1.652ms | 6.051ms |
| smalot/pdfparser | 1.975ms | 2.319ms | 5.424ms |
| setasign/fpdi | 1.911ms | 2.702ms | 28.549ms |

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
| phpdftk | 2.044ms | 1.320ms |
| smalot/pdfparser | FAIL | 1.891ms |
| setasign/fpdi | 2.850ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.268ms   | ±1.33%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.498ms   | ±0.56%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.847ms   | ±6.27%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.753ms   | ±1.09%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.213ms   | ±0.43%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.698ms  | ±0.77%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.380ms  | ±0.56%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 18.361ms  | ±52.30% |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 25.142ms  | ±0.35%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 35.257ms  | ±0.67%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.051ms   | ±1.94%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.126ms   | ±0.38%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.238ms   | ±2.48%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.906ms   | ±0.18%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.731ms   | ±0.39%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.285ms   | ±0.80%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.704ms   | ±1.00%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.742ms   | ±0.76%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.667ms   | ±0.62%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.126ms   | ±0.29%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.379ms   | ±0.24%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.341ms   | ±0.42%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.352ms   | ±0.66%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.607ms  | ±39.27% |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.648mb | 82.220ms  | ±3.29%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 26.882mb | 349.955ms | ±0.63%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 63.922mb | 1.476s    | ±4.29%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.862mb | 251.573ms | ±2.57%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.627mb | 198.386ms | ±0.63%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.547mb | 155.437ms | ±2.51%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 20.767mb | 210.028ms | ±0.15%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 20.000mb | 181.138ms | ±3.40%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.198mb | 329.306ms | ±0.58%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.039mb | 50.254ms  | ±0.10%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.016mb | 44.819ms  | ±3.01%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.943mb | 41.928ms  | ±0.28%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.351mb | 136.578ms | ±1.05%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.940mb | 48.954ms  | ±3.17%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.232mb | 58.130ms  | ±3.83%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.695mb | 86.006ms  | ±1.78%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.900mb | 38.025ms  | ±2.20%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.835mb | 45.456ms  | ±1.80%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.863mb | 47.585ms  | ±1.93%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.831mb | 46.020ms  | ±0.72%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.853mb | 44.306ms  | ±0.24%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.775mb | 71.088ms  | ±0.59%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.884mb | 44.728ms  | ±0.68%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.497mb | 38.396ms  | ±2.01%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.800mb | 42.668ms  | ±6.71%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.821mb | 47.342ms  | ±3.37%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.814mb | 47.089ms  | ±0.35%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.045mb | 43.291ms  | ±0.32%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.216mb | 229.157ms | ±2.39%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.471mb | 168.158ms | ±1.11%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.331mb | 56.184ms  | ±3.10%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.937mb | 113.705ms | ±0.52%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 64.695mb | 1.343s    | ±0.11%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.222mb | 23.728ms  | ±2.29%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.248mb | 52.327ms  | ±2.99%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.994mb | 457.840ms | ±0.69%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.506mb | 63.301ms  | ±9.58%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.159mb | 82.581ms  | ±1.58%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.928mb | 659.087ms | ±0.32%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.270mb | 17.529ms  | ±0.33%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.270mb | 40.939ms  | ±0.17%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.913mb | 277.221ms | ±0.60%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.234ms   | ±1.00%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.652ms   | ±0.71%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.051ms   | ±0.52%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.044ms   | ±1.79%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.320ms   | ±1.27%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.975ms   | ±1.29%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.319ms   | ±1.43%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.424ms   | ±0.96%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 552.896μs | ±1.18%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.891ms   | ±0.58%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.911ms   | ±1.16%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.702ms   | ±0.41%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.549ms  | ±0.67%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.850ms   | ±0.53%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.496ms   | ±0.80%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.087ms   | ±0.46%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.322ms   | ±0.51%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.800ms   | ±0.37%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.819μs   | ±20.75% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.102ms   | ±0.99%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.764mb  | 25.929ms  | ±6.75%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.201mb | 227.798ms | ±0.37%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.726mb | 1.306s    | ±1.41%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.209mb | 199.295ms | ±6.32%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 27.044mb | 942.253ms | ±0.04%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 24.131mb | 768.419ms | ±0.41%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.053ms   | ±0.26%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.498ms  | ±2.93%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 44.424ms  | ±1.86%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.255ms   | ±0.68%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.265ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.281ms   | ±1.25%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.496ms   | ±0.72%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.741ms   | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.684ms   | ±0.78%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.914ms   | ±0.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.532ms   | ±0.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.742ms   | ±0.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.918ms  | ±5.17%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.550ms   | ±0.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.322ms   | ±1.13%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 608.995μs | ±3.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.118ms   | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.547ms   | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.143ms   | ±0.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 189.754ms | ±24.50% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.488ms   | ±0.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.777ms   | ±14.60% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.017ms   | ±1.09%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.022ms  | ±0.85%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.953ms  | ±0.37%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.814ms  | ±0.32%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.103ms  | ±0.24%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.128ms  | ±0.45%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 789.110μs | ±2.59%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 857.256μs | ±0.94%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 930.700μs | ±1.33%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.524ms   | ±0.74%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.259ms   | ±0.55%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.515ms  | ±1.79%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.211ms  | ±0.28%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.620ms  | ±0.65%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 60.253ms  | ±0.25%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 95.020ms  | ±0.19%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.099ms  | ±1.41%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.104ms  | ±0.86%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 20.002ms  | ±0.32%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 66.505ms  | ±0.42%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 149.490ms | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.942ms   | ±0.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 53.420ms  | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.678μs   | ±9.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 307.564ms | ±19.46% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 488.005μs | ±0.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.921ms   | ±0.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.330ms   | ±0.21%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.057ms  | ±3.02%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 80.982ms  | ±0.47%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.089ms  | ±0.61%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.398ms  | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 184.364ms | ±27.84% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.899ms  | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.669ms  | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.948ms  | ±0.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 14.105ms  | ±0.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.310ms  | ±0.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.118ms   | ±0.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.981ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.960ms  | ±0.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.738ms  | ±0.51%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.697mb  | 13.158ms  | ±0.97%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.498mb  | 11.883ms  | ±0.43%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.818mb  | 19.020ms  | ±4.19%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.733mb  | 16.779ms  | ±2.45%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.888mb  | 23.532ms  | ±3.65%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.924mb  | 12.499ms  | ±1.21%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.124mb  | 15.409ms  | ±2.00%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 11.764mb | 33.793ms  | ±0.54%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.846mb  | 3.155ms   | ±0.83%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.702μs  | ±1.34%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 236.560μs | ±0.66%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.275ms   | ±0.55%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.809ms  | ±0.42%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 45.290ms  | ±0.34%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.751ms   | ±0.71%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.814ms   | ±0.50%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.697ms   | ±0.54%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.324ms   | ±0.65%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.437ms   | ±0.55%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.939μs   | ±17.68% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.121μs   | ±35.36% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.409mb | 17.799ms  | ±0.55%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.409mb | 18.064ms  | ±1.91%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```