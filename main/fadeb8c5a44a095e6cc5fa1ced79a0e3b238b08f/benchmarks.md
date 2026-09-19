# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-19 17:05:23 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 11.281ms | 2.098ms | 2.426ms | 6.521ms | 6.067ms |
| FPDF | 693.534μs | 774.744μs | 1.000ms | 1.432ms | 1.961ms |
| TCPDF | 9.313ms | 54.125ms | 10.998ms | 17.497ms | 24.419ms |
| mPDF | 23.123ms | 24.816ms | 27.381ms | 55.224ms | 85.537ms |
| Dompdf | 8.784ms | 15.745ms | 17.996ms | 57.880ms | 131.362ms |

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
| phpdftk | 2.639ms | 2.963ms | 3.533ms | 4.705ms | 6.892ms |
| FPDF | 835.358μs | 941.733μs | 1.126ms | 1.610ms | 2.606ms |
| TCPDF | 13.915ms | 13.765ms | 13.305ms | 20.559ms | 30.329ms |

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
| Pdf (Level 3) | 3.044ms | 3.589ms | 10.515ms |
| PdfDoc (Level 2) | 2.181ms | 2.633ms | 7.650ms |
| PdfWriter (Level 1) | 1.922ms | 6.066ms | 14.594ms |

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
| Pdf (Level 3) | 3.673ms | 10.280ms | 37.784ms |
| PdfDoc (Level 2) | 8.732ms | 10.788ms | — |

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
| Pdf (Level 3) | 3.417ms | 9.566ms | 36.642ms |
| PdfDoc (Level 2) | 3.082ms | 5.887ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.110ms | 1.457ms | 4.987ms |
| smalot/pdfparser | 1.735ms | 1.957ms | 4.800ms |
| setasign/fpdi | 1.591ms | 2.339ms | 24.850ms |

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
| phpdftk | 1.763ms | 1.065ms |
| smalot/pdfparser | FAIL | 1.606ms |
| setasign/fpdi | 2.456ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 2.639ms   | ±1.94%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 2.963ms   | ±8.12%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.533ms   | ±7.75%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 4.705ms   | ±22.98%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 6.892ms   | ±4.96%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 13.915ms  | ±40.62%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 13.765ms  | ±4.54%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 13.305ms  | ±1.96%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 20.559ms  | ±5.48%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 30.329ms  | ±7.12%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 835.358μs | ±4.63%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 941.733μs | ±2.28%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.126ms   | ±3.38%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.610ms   | ±3.63%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.606ms   | ±8.46%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 1.922ms   | ±133.63% |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 6.066ms   | ±130.23% |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 14.594ms  | ±39.45%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.181ms   | ±116.58% |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.633ms   | ±57.17%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.650ms   | ±144.20% |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.044ms   | ±10.70%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 3.589ms   | ±2.61%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 10.515ms  | ±4.02%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.571mb | 74.079ms  | ±4.64%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 26.805mb | 292.635ms | ±1.28%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 63.844mb | 1.159s    | ±0.31%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.785mb | 211.635ms | ±4.83%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.550mb | 162.888ms | ±4.21%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.469mb | 129.174ms | ±2.37%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 20.690mb | 178.084ms | ±3.22%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 19.923mb | 143.276ms | ±4.76%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.121mb | 278.213ms | ±2.40%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.961mb | 42.091ms  | ±7.72%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.937mb | 40.232ms  | ±3.86%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.866mb | 38.088ms  | ±2.17%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.273mb | 115.695ms | ±1.61%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.862mb | 40.153ms  | ±2.62%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.089mb | 49.870ms  | ±1.51%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.552mb | 74.788ms  | ±1.68%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.757mb | 32.025ms  | ±1.29%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.758mb | 37.665ms  | ±2.08%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.785mb | 40.087ms  | ±0.84%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.754mb | 38.852ms  | ±2.75%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.776mb | 37.147ms  | ±2.97%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.698mb | 60.853ms  | ±3.46%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.806mb | 38.672ms  | ±4.56%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.420mb | 32.222ms  | ±2.71%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.722mb | 36.870ms  | ±2.42%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.743mb | 43.244ms  | ±8.12%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.736mb | 40.178ms  | ±2.88%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.967mb | 38.218ms  | ±1.12%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.139mb | 191.281ms | ±1.46%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.392mb | 137.050ms | ±3.97%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.253mb | 48.375ms  | ±9.06%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.859mb | 93.253ms  | ±4.90%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 64.618mb | 1.120s    | ±0.89%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.146mb | 23.362ms  | ±48.77%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.172mb | 42.448ms  | ±1.24%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.917mb | 408.767ms | ±2.21%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.429mb | 52.076ms  | ±16.16%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.083mb | 71.587ms  | ±0.69%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.852mb | 559.546ms | ±1.03%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.194mb | 15.393ms  | ±7.59%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.194mb | 35.602ms  | ±3.66%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.837mb | 242.069ms | ±0.77%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.026ms   | ±3.40%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.457ms   | ±5.96%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 4.987ms   | ±7.15%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.763ms   | ±6.42%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.065ms   | ±3.71%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.735ms   | ±4.44%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.957ms   | ±6.51%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.800ms   | ±4.01%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 503.489μs | ±3.25%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.606ms   | ±3.77%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.591ms   | ±4.55%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.339ms   | ±2.18%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 24.850ms  | ±4.44%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.456ms   | ±4.79%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.310ms   | ±2.74%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 5.826ms   | ±2.89%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 4.589ms   | ±5.30%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.086ms   | ±7.58%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.324μs   | ±20.67%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 5.110ms   | ±2.11%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.720mb  | 21.059ms  | ±2.87%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.158mb | 231.323ms | ±7.49%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.683mb | 968.340ms | ±8.35%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.165mb | 151.761ms | ±0.49%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.417ms   | ±8.63%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 9.566ms   | ±4.38%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 36.642ms  | ±4.64%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.082ms   | ±145.75% |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 5.887ms   | ±2.47%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.010ms   | ±82.63%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.098ms   | ±7.54%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.426ms   | ±134.12% |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 6.521ms   | ±75.92%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.067ms   | ±81.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 2.991ms   | ±7.28%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.097ms   | ±2.99%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 11.052ms  | ±1.75%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.933ms   | ±3.39%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.135ms   | ±135.99% |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 522.384μs | ±4.62%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.684ms   | ±3.51%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.333ms   | ±16.21%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.866ms   | ±26.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 217.854ms | ±30.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 2.972ms   | ±55.24%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.773ms   | ±55.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.200ms   | ±11.02%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.313ms   | ±4.44%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 54.125ms  | ±60.12%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 10.998ms  | ±27.48%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 17.497ms  | ±51.67%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 24.419ms  | ±2.95%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 693.534μs | ±5.60%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 774.744μs | ±57.47%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 1.000ms   | ±140.10% |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.432ms   | ±154.51% |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.961ms   | ±1.57%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 23.123ms  | ±24.13%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 24.816ms  | ±4.24%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 27.381ms  | ±2.75%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 55.224ms  | ±16.05%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 85.537ms  | ±2.34%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 8.784ms   | ±2.56%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.745ms  | ±44.32%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 17.996ms  | ±5.93%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 57.880ms  | ±4.50%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 131.362ms | ±3.98%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.171ms   | ±80.97%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 45.412ms  | ±12.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.463μs   | ±17.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 194.401ms | ±37.57%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 455.768μs | ±6.08%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.332ms   | ±12.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 2.966ms   | ±145.84% |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 10.654ms  | ±6.33%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 70.317ms  | ±1.81%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.948ms  | ±4.77%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 22.687ms  | ±2.51%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 205.143ms | ±22.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 11.080ms  | ±0.94%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 11.224ms  | ±6.87%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 11.351ms  | ±3.85%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 11.861ms  | ±2.15%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 12.017ms  | ±4.32%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.499ms   | ±8.68%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 11.502ms  | ±2.90%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 11.820ms  | ±60.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 11.281ms  | ±27.19%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.402mb  | 7.928ms   | ±7.47%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.299mb  | 8.359ms   | ±8.79%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.449mb  | 10.003ms  | ±0.78%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.147mb  | 9.577ms   | ±7.19%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.629mb  | 9.113ms   | ±7.30%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.706mb  | 8.570ms   | ±2.39%   |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 8.841mb  | 10.498ms  | ±4.73%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.950mb | 14.802ms  | ±6.38%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.839mb  | 2.582ms   | ±2.79%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 35.308μs  | ±2.76%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 186.704μs | ±4.25%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 3.673ms   | ±156.98% |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 10.280ms  | ±3.53%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 37.784ms  | ±3.42%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 8.732ms   | ±97.85%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 10.788ms  | ±80.41%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 7.251ms   | ±20.28%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 7.168ms   | ±4.85%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 7.062ms   | ±2.03%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.061μs   | ±20.20%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.197μs   | ±27.38%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.332mb | 15.201ms  | ±4.93%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.332mb | 14.229ms  | ±5.82%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```