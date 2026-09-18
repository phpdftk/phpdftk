# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-18 03:20:29 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.615ms | 1.547ms | 1.691ms | 3.163ms | 4.527ms |
| FPDF | 592.113μs | 607.018μs | 634.264μs | 1.056ms | 1.573ms |
| TCPDF | 7.065ms | 7.828ms | 8.241ms | 13.501ms | 19.906ms |
| mPDF | 17.472ms | 19.381ms | 21.702ms | 38.950ms | 61.306ms |
| Dompdf | 7.578ms | 10.083ms | 13.262ms | 42.592ms | 95.687ms |

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
| phpdftk | 2.304ms | 2.196ms | 2.378ms | 3.685ms | 5.461ms |
| FPDF | 12.254ms | 757.795μs | 839.453μs | 1.318ms | 1.902ms |
| TCPDF | 10.750ms | 21.625ms | 12.120ms | 25.844ms | 25.199ms |

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
| Pdf (Level 3) | 2.120ms | 3.126ms | 7.646ms |
| PdfDoc (Level 2) | 3.582ms | 1.927ms | 4.870ms |
| PdfWriter (Level 1) | 1.482ms | 1.676ms | 6.658ms |

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
| Pdf (Level 3) | 2.808ms | 7.597ms | 28.905ms |
| PdfDoc (Level 2) | 2.406ms | 6.366ms | — |

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
| Pdf (Level 3) | 2.501ms | 6.971ms | 25.901ms |
| PdfDoc (Level 2) | 2.007ms | 4.524ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 3.427ms | 949.102μs | 3.339ms |
| smalot/pdfparser | 1.286ms | 1.506ms | 3.582ms |
| setasign/fpdi | 1.203ms | 1.698ms | 16.268ms |

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
| phpdftk | 1.155ms | 806.316μs |
| smalot/pdfparser | FAIL | 1.197ms |
| setasign/fpdi | 1.780ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 2.304ms   | ±5.77%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 2.196ms   | ±1.96%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 2.378ms   | ±1.58%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 3.685ms   | ±1.22%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 5.461ms   | ±0.50%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 10.750ms  | ±3.95%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 21.625ms  | ±119.83% |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 12.120ms  | ±2.66%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 25.844ms  | ±100.96% |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 25.199ms  | ±0.62%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 12.254ms  | ±139.87% |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 757.795μs | ±2.67%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 839.453μs | ±7.29%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.318ms   | ±0.08%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 1.902ms   | ±0.71%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 1.482ms   | ±2.71%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 1.676ms   | ±0.62%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.658ms   | ±164.36% |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 3.582ms   | ±107.77% |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 1.927ms   | ±3.93%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 4.870ms   | ±0.60%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 2.120ms   | ±0.95%   |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 3.126ms   | ±180.38% |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 7.646ms   | ±56.03%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.702mb | 47.384ms  | ±0.51%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.659mb | 217.272ms | ±6.21%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.858mb | 799.332ms | ±2.87%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.868mb | 142.140ms | ±0.42%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.731mb | 113.827ms | ±5.87%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.514mb | 102.409ms | ±0.29%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.616mb | 138.697ms | ±0.23%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.130mb | 99.124ms  | ±0.45%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.754mb | 186.830ms | ±0.17%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.203mb | 29.014ms  | ±0.17%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.119mb | 25.561ms  | ±0.57%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.043mb | 24.448ms  | ±0.24%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.402mb | 78.279ms  | ±0.92%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.108mb | 26.918ms  | ±0.18%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.326mb | 33.259ms  | ±1.13%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.762mb | 49.304ms  | ±0.53%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.010mb | 21.958ms  | ±0.42%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.013mb | 26.506ms  | ±0.28%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.040mb | 27.424ms  | ±0.15%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.009mb | 26.992ms  | ±0.48%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.031mb | 25.966ms  | ±0.21%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.950mb | 42.042ms  | ±0.68%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.991mb | 25.420ms  | ±0.68%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.676mb | 22.782ms  | ±0.57%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.978mb | 24.536ms  | ±0.31%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.998mb | 27.412ms  | ±0.65%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.992mb | 27.203ms  | ±0.45%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.158mb | 25.190ms  | ±0.41%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.389mb | 125.097ms | ±0.24%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.589mb | 95.517ms  | ±0.92%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.396mb | 31.880ms  | ±0.20%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.010mb | 63.886ms  | ±0.04%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 43.255mb | 764.157ms | ±0.41%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.511mb | 16.141ms  | ±2.56%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.471mb | 34.844ms  | ±0.27%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.282mb | 298.412ms | ±0.35%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.794mb | 40.862ms  | ±8.77%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.448mb | 52.803ms  | ±1.15%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.217mb | 443.796ms | ±0.38%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.493mb | 12.520ms  | ±0.19%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.493mb | 26.775ms  | ±0.39%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.136mb | 188.039ms | ±0.44%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 716.072μs | ±2.93%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 949.102μs | ±1.45%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 3.339ms   | ±0.73%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.155ms   | ±0.60%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 806.316μs | ±2.11%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.286ms   | ±1.61%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.506ms   | ±1.08%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 3.582ms   | ±1.36%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 363.139μs | ±1.06%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.197ms   | ±1.52%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.203ms   | ±2.65%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 1.698ms   | ±6.96%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 16.268ms  | ±0.83%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 1.780ms   | ±0.68%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 969.491μs | ±1.84%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 4.334ms   | ±0.88%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 3.301ms   | ±0.53%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.420ms   | ±2.78%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 1.524μs   | ±39.92%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 3.427ms   | ±0.43%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.545mb  | 14.619ms  | ±0.38%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.731mb | 128.760ms | ±0.30%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.136mb | 646.951ms | ±0.46%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.834mb | 103.116ms | ±0.14%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 2.501ms   | ±2.10%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 6.971ms   | ±2.58%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 25.901ms  | ±26.51%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.007ms   | ±1.32%   |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 4.524ms   | ±1.39%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 1.479ms   | ±1.50%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 1.547ms   | ±1.79%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 1.691ms   | ±1.73%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 3.163ms   | ±1.72%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 4.527ms   | ±0.41%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 2.270ms   | ±3.16%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 2.398ms   | ±1.96%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 9.026ms   | ±2.50%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.652ms   | ±0.84%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.730ms   | ±0.80%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 653.489μs | ±19.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.446ms   | ±4.66%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 2.649ms   | ±1.14%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.384ms   | ±1.66%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 158.107ms | ±19.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 2.297ms   | ±3.86%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 3.612ms   | ±46.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 3.630ms   | ±4.92%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.065ms   | ±2.33%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 7.828ms   | ±2.56%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 8.241ms   | ±1.00%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 13.501ms  | ±1.27%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 19.906ms  | ±2.02%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 592.113μs | ±3.22%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 607.018μs | ±4.72%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 634.264μs | ±3.08%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.056ms   | ±1.04%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.573ms   | ±1.01%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 17.472ms  | ±3.22%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 19.381ms  | ±1.16%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 21.702ms  | ±1.04%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 38.950ms  | ±0.79%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 61.306ms  | ±0.51%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 7.578ms   | ±1.21%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 10.083ms  | ±0.74%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 13.262ms  | ±0.30%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 42.592ms  | ±0.55%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 95.687ms  | ±0.47%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 3.172ms   | ±1.56%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 30.148ms  | ±0.28%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.667μs   | ±31.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 0.356μs   | ±54.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 0.796μs   | ±34.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 197.112ms | ±16.35%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 272.843μs | ±0.80%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 1.892ms   | ±1.09%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 2.236ms   | ±1.80%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 11.973ms  | ±10.62%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 47.633ms  | ±0.26%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 8.213ms   | ±0.13%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 14.412ms  | ±0.55%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 92.382ms  | ±27.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 9.735ms   | ±0.63%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 9.532ms   | ±0.91%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 9.778ms   | ±0.38%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 9.812ms   | ±28.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 9.886ms   | ±1.59%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.427ms   | ±85.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 10.054ms  | ±17.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 9.633ms   | ±33.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 9.615ms   | ±1.35%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 7.609ms   | ±1.27%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 7.595ms   | ±1.62%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 8.840ms   | ±2.05%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 8.820ms   | ±0.43%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 8.192ms   | ±0.83%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 7.011ms   | ±5.01%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 12.000ms  | ±0.99%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 1.959ms   | ±0.78%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 23.672μs  | ±2.59%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 150.543μs | ±1.02%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 2.808ms   | ±1.20%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 7.597ms   | ±0.79%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 28.905ms  | ±1.06%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 2.406ms   | ±0.70%   |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 6.366ms   | ±3.15%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 5.346ms   | ±0.78%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 5.169ms   | ±1.08%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 5.178ms   | ±0.96%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 0.824μs   | ±20.20%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 0.797μs   | ±59.32%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.634mb | 10.726ms  | ±0.58%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.634mb | 10.558ms  | ±1.40%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```