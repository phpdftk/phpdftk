# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-19 16:31:13 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.474ms | 1.622ms | 2.002ms | 6.634ms | 4.655ms |
| FPDF | 687.845μs | 4.782ms | 683.282μs | 1.094ms | 1.728ms |
| TCPDF | 7.505ms | 8.408ms | 12.213ms | 14.712ms | 20.903ms |
| mPDF | 18.079ms | 20.512ms | 22.772ms | 40.511ms | 68.908ms |
| Dompdf | 7.664ms | 10.342ms | 13.613ms | 43.491ms | 96.728ms |

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
| phpdftk | 4.439ms | 2.257ms | 2.451ms | 4.324ms | 5.386ms |
| FPDF | 810.582μs | 4.098ms | 920.096μs | 1.460ms | 2.131ms |
| TCPDF | 10.871ms | 11.514ms | 12.554ms | 22.052ms | 27.294ms |

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
| Pdf (Level 3) | 2.099ms | 3.608ms | 7.652ms |
| PdfDoc (Level 2) | 1.823ms | 2.022ms | 4.992ms |
| PdfWriter (Level 1) | 2.022ms | 5.019ms | 4.864ms |

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
| Pdf (Level 3) | 3.029ms | 9.094ms | 33.446ms |
| PdfDoc (Level 2) | 2.446ms | 6.586ms | — |

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
| Pdf (Level 3) | 2.533ms | 7.018ms | 25.988ms |
| PdfDoc (Level 2) | 2.160ms | 4.522ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 3.418ms | 962.262μs | 3.364ms |
| smalot/pdfparser | 1.306ms | 1.550ms | 3.667ms |
| setasign/fpdi | 1.209ms | 1.693ms | 16.324ms |

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
| phpdftk | 1.181ms | 818.576μs |
| smalot/pdfparser | FAIL | 1.255ms |
| setasign/fpdi | 1.781ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 4.439ms   | ±115.42% |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 2.257ms   | ±0.70%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 2.451ms   | ±1.67%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 4.324ms   | ±65.11%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 5.386ms   | ±1.44%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 10.871ms  | ±2.26%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 11.514ms  | ±1.14%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 12.554ms  | ±6.00%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 22.052ms  | ±4.01%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 27.294ms  | ±1.62%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 810.582μs | ±4.52%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 4.098ms   | ±128.19% |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 920.096μs | ±1.25%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.460ms   | ±0.88%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.131ms   | ±0.28%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.022ms   | ±158.32% |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 5.019ms   | ±106.00% |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 4.864ms   | ±109.93% |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 1.823ms   | ±167.39% |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.022ms   | ±1.27%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 4.992ms   | ±34.28%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 2.099ms   | ±1.43%   |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 3.608ms   | ±116.61% |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 7.652ms   | ±0.53%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.461mb | 52.970ms  | ±4.02%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 26.761mb | 209.733ms | ±0.59%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 63.801mb | 826.954ms | ±0.79%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.741mb | 153.926ms | ±3.15%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.506mb | 113.893ms | ±0.36%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.425mb | 90.462ms  | ±0.23%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 20.646mb | 123.677ms | ±0.34%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 19.879mb | 101.341ms | ±0.20%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.077mb | 191.851ms | ±0.36%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.917mb | 30.250ms  | ±0.13%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.829mb | 26.583ms  | ±0.33%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.822mb | 25.372ms  | ±0.38%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.229mb | 79.881ms  | ±0.14%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.818mb | 27.879ms  | ±0.13%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.045mb | 34.326ms  | ±0.26%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.508mb | 50.179ms  | ±0.39%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.713mb | 23.130ms  | ±0.55%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.714mb | 27.304ms  | ±0.14%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.741mb | 28.387ms  | ±3.34%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.710mb | 27.565ms  | ±0.14%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.732mb | 26.872ms  | ±0.25%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.654mb | 43.119ms  | ±0.36%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.762mb | 29.984ms  | ±4.55%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.376mb | 24.781ms  | ±0.14%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.679mb | 25.884ms  | ±8.50%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.699mb | 30.863ms  | ±0.58%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.693mb | 29.913ms  | ±2.96%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.924mb | 25.886ms  | ±0.23%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.095mb | 129.421ms | ±0.18%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.285mb | 98.562ms  | ±6.26%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.209mb | 33.747ms  | ±5.36%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.816mb | 66.771ms  | ±0.13%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 64.574mb | 796.558ms | ±0.33%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.102mb | 17.147ms  | ±17.74%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.128mb | 34.994ms  | ±0.97%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.874mb | 304.797ms | ±0.49%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.385mb | 40.606ms  | ±9.42%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.039mb | 52.517ms  | ±1.94%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.808mb | 443.712ms | ±0.53%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.150mb | 12.559ms  | ±0.41%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.150mb | 26.995ms  | ±0.47%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.793mb | 189.726ms | ±0.19%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 739.957μs | ±2.30%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 962.262μs | ±1.86%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 3.364ms   | ±1.21%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.181ms   | ±0.98%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 818.576μs | ±1.14%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.306ms   | ±1.91%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.550ms   | ±1.36%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 3.667ms   | ±1.53%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 370.815μs | ±2.14%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.255ms   | ±1.50%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.209ms   | ±1.48%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 1.693ms   | ±0.85%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 16.324ms  | ±0.17%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 1.781ms   | ±0.53%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 959.794μs | ±1.44%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 4.309ms   | ±0.68%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 3.277ms   | ±1.05%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.391ms   | ±1.35%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 1.386μs   | ±35.67%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 3.418ms   | ±0.75%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.685mb  | 14.977ms  | ±0.15%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.123mb | 132.789ms | ±0.25%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.648mb | 676.910ms | ±4.29%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.130mb | 108.551ms | ±4.02%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 2.533ms   | ±1.63%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 7.018ms   | ±1.03%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 25.988ms  | ±0.35%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.160ms   | ±102.43% |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 4.522ms   | ±0.88%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 5.754ms   | ±107.64% |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 1.622ms   | ±154.97% |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.002ms   | ±156.73% |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 6.634ms   | ±115.89% |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 4.655ms   | ±6.11%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 2.631ms   | ±114.68% |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 2.538ms   | ±74.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 8.013ms   | ±44.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.486ms   | ±105.44% |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.540ms   | ±157.99% |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 453.717μs | ±17.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.179ms   | ±1.91%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 2.450ms   | ±37.34%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.543ms   | ±158.32% |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 133.081ms | ±20.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 2.378ms   | ±1.42%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.409ms   | ±103.32% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 3.858ms   | ±123.79% |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.505ms   | ±2.19%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 8.408ms   | ±104.68% |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.213ms  | ±82.65%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 14.712ms  | ±4.39%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 20.903ms  | ±55.92%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 687.845μs | ±58.51%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 4.782ms   | ±90.29%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 683.282μs | ±136.41% |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.094ms   | ±74.85%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.728ms   | ±166.24% |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 18.079ms  | ±2.27%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 20.512ms  | ±5.13%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 22.772ms  | ±24.30%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 40.511ms  | ±0.86%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 68.908ms  | ±6.17%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 7.664ms   | ±0.40%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 10.342ms  | ±0.87%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 13.613ms  | ±0.52%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 43.491ms  | ±4.61%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 96.728ms  | ±2.81%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 6.199ms   | ±123.33% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 30.496ms  | ±13.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.376μs   | ±50.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 0.796μs   | ±34.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 0.796μs   | ±34.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 147.844ms | ±36.05%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 280.333μs | ±1.35%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 1.964ms   | ±1.21%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 5.216ms   | ±113.06% |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.429ms  | ±77.23%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 47.633ms  | ±0.11%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 8.283ms   | ±6.35%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 14.534ms  | ±0.52%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 151.495ms | ±21.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 8.562ms   | ±10.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 8.603ms   | ±1.19%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 8.797ms   | ±25.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 8.887ms   | ±11.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 8.981ms   | ±0.45%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.649ms   | ±101.01% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 9.698ms   | ±11.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 9.736ms   | ±8.24%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 9.474ms   | ±1.88%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.402mb  | 8.006ms   | ±1.48%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.299mb  | 7.904ms   | ±0.80%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.449mb  | 9.147ms   | ±0.23%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.147mb  | 8.224ms   | ±5.17%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.629mb  | 7.570ms   | ±0.86%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.706mb  | 7.188ms   | ±0.74%   |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 8.841mb  | 8.623ms   | ±0.81%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.950mb | 12.581ms  | ±1.13%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.839mb  | 2.082ms   | ±3.35%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 24.435μs  | ±2.39%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 155.728μs | ±3.03%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 3.029ms   | ±123.30% |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 9.094ms   | ±52.31%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 33.446ms  | ±7.13%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 2.446ms   | ±15.77%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 6.586ms   | ±1.63%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 5.667ms   | ±12.69%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 5.278ms   | ±16.67%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 5.366ms   | ±29.03%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 0.849μs   | ±35.36%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 0.986μs   | ±46.37%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.288mb | 11.815ms  | ±0.67%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.288mb | 11.667ms  | ±1.68%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```