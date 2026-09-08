# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-08 15:39:23 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.545ms | 2.547ms | 2.783ms | 4.795ms | 7.042ms |
| FPDF | 769.175μs | 863.426μs | 917.646μs | 1.529ms | 2.273ms |
| TCPDF | 10.259ms | 10.879ms | 12.019ms | 20.407ms | 31.309ms |
| mPDF | 25.178ms | 29.037ms | 33.065ms | 65.402ms | 105.685ms |
| Dompdf | 11.316ms | 16.178ms | 21.888ms | 74.206ms | 164.208ms |

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
| phpdftk | 3.433ms | 3.699ms | 3.905ms | 6.015ms | 8.652ms |
| FPDF | 1.053ms | 1.172ms | 1.274ms | 1.932ms | 28.353ms |
| TCPDF | 15.321ms | 16.074ms | 17.225ms | 27.079ms | 39.479ms |

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
| Pdf (Level 3) | 3.463ms | 4.510ms | 12.769ms |
| PdfDoc (Level 2) | 2.767ms | 3.199ms | 7.618ms |
| PdfWriter (Level 1) | 3.382ms | 2.785ms | 7.007ms |

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
| Pdf (Level 3) | 4.633ms | 12.522ms | 47.538ms |
| PdfDoc (Level 2) | 3.811ms | 10.116ms | — |

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
| Pdf (Level 3) | 4.137ms | 11.823ms | 45.535ms |
| PdfDoc (Level 2) | 3.340ms | 7.389ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.137ms | 1.697ms | 5.968ms |
| smalot/pdfparser | 2.006ms | 2.382ms | 5.732ms |
| setasign/fpdi | 1.968ms | 2.822ms | 29.768ms |

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
| phpdftk | 2.019ms | 1.371ms |
| smalot/pdfparser | FAIL | 1.915ms |
| setasign/fpdi | 2.956ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.433ms   | ±3.28%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.699ms   | ±1.66%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.905ms   | ±2.37%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 6.015ms   | ±0.36%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.652ms   | ±0.72%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.321ms  | ±1.56%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.074ms  | ±1.71%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.225ms  | ±1.94%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 27.079ms  | ±0.97%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 39.479ms  | ±1.27%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.053ms   | ±2.09%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.172ms   | ±2.65%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.274ms   | ±0.39%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.932ms   | ±1.63%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 28.353ms  | ±61.74%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 3.382ms   | ±125.06% |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.785ms   | ±1.10%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 7.007ms   | ±0.37%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.767ms   | ±1.54%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.199ms   | ±1.42%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.618ms   | ±0.63%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.463ms   | ±1.93%   |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.510ms   | ±1.12%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.769ms  | ±0.70%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.515mb | 86.182ms  | ±1.21%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.407mb | 380.334ms | ±0.50%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.469mb | 1.510s    | ±0.70%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.582mb | 267.906ms | ±0.67%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.478mb | 200.544ms | ±0.67%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.324mb | 165.721ms | ±0.39%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.395mb | 225.129ms | ±0.55%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.932mb | 186.614ms | ±0.72%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.487mb | 356.287ms | ±2.39%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.027mb | 54.470ms  | ±1.33%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.943mb | 47.219ms  | ±1.32%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.869mb | 43.942ms  | ±1.43%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.211mb | 147.918ms | ±0.88%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.932mb | 48.921ms  | ±0.99%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.150mb | 61.128ms  | ±0.78%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.578mb | 92.118ms  | ±0.32%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.836mb | 39.594ms  | ±1.23%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.773mb | 47.966ms  | ±0.99%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.801mb | 49.589ms  | ±1.09%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.769mb | 48.052ms  | ±0.96%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.857mb | 46.299ms  | ±0.32%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.710mb | 72.533ms  | ±0.45%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.817mb | 44.273ms  | ±0.44%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.436mb | 40.049ms  | ±0.57%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.738mb | 44.958ms  | ±0.14%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.759mb | 50.398ms  | ±1.02%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.752mb | 50.012ms  | ±1.22%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 18.984mb | 44.720ms  | ±1.15%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.188mb | 236.948ms | ±0.94%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.407mb | 174.713ms | ±2.33%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.214mb | 60.215ms  | ±0.89%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.821mb | 119.648ms | ±0.64%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.889mb | 1.444s    | ±1.59%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.275mb | 27.061ms  | ±2.68%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.301mb | 58.841ms  | ±0.51%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.112mb | 544.495ms | ±0.26%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.558mb | 64.810ms  | ±9.60%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.278mb | 87.327ms  | ±0.66%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.046mb | 741.568ms | ±0.77%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.323mb | 19.338ms  | ±2.08%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.323mb | 44.796ms  | ±0.53%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.965mb | 330.189ms | ±1.31%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.260ms   | ±0.66%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.697ms   | ±0.70%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.968ms   | ±0.75%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.019ms   | ±1.07%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.371ms   | ±1.26%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.006ms   | ±2.06%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.382ms   | ±0.96%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.732ms   | ±1.61%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 538.880μs | ±1.15%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.915ms   | ±1.01%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.968ms   | ±1.33%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.822ms   | ±9.54%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.768ms  | ±0.27%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.956ms   | ±0.94%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.529ms   | ±1.13%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.284ms   | ±0.36%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.421ms   | ±0.94%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.817ms   | ±0.93%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.386μs   | ±16.56%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.137ms   | ±0.92%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.511mb  | 26.413ms  | ±0.83%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.680mb | 240.858ms | ±0.44%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.008mb | 1.206s    | ±1.56%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.137ms   | ±0.93%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.823ms  | ±1.57%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.535ms  | ±0.47%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.340ms   | ±1.03%   |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.389ms   | ±0.73%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.325ms   | ±9.85%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.547ms   | ±0.73%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.783ms   | ±0.33%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.795ms   | ±1.59%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.042ms   | ±0.88%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.590ms   | ±0.85%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.830ms   | ±1.32%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.407ms  | ±1.96%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.613ms   | ±2.63%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.356ms   | ±1.34%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 645.411μs | ±8.08%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.162ms   | ±1.48%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.634ms   | ±0.68%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.230ms   | ±0.84%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 230.509ms | ±11.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.745ms   | ±0.46%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.210ms   | ±19.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.066ms   | ±1.04%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.259ms  | ±3.48%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.879ms  | ±2.32%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.019ms  | ±0.49%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.407ms  | ±0.18%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.309ms  | ±0.59%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 769.175μs | ±1.36%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 863.426μs | ±1.57%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 917.646μs | ±0.93%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.529ms   | ±2.25%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.273ms   | ±0.78%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.178ms  | ±1.93%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.037ms  | ±1.15%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.065ms  | ±1.42%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 65.402ms  | ±1.11%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 105.685ms | ±0.53%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.316ms  | ±3.27%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.178ms  | ±1.00%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.888ms  | ±1.86%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 74.206ms  | ±3.04%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 164.208ms | ±1.87%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.096ms   | ±1.07%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.777ms  | ±1.32%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 174.340ms | ±14.09%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 460.337μs | ±1.89%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.027ms   | ±0.78%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.380ms   | ±0.92%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 14.129ms  | ±13.35%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 85.922ms  | ±0.91%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.354ms  | ±7.31%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.105ms  | ±0.74%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 166.572ms | ±22.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.684ms  | ±2.74%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.522ms  | ±0.62%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.844ms  | ±2.28%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.853ms  | ±1.22%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.987ms  | ±2.36%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.215ms   | ±6.56%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.802ms  | ±11.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 14.102ms  | ±2.38%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.545ms  | ±2.37%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 10.768ms  | ±1.25%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 10.884ms  | ±3.08%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 12.352ms  | ±0.52%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 12.468ms  | ±1.71%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 11.445ms  | ±1.28%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 10.818ms  | ±4.39%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 20.102ms  | ±2.57%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 3.219ms   | ±1.91%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.016μs  | ±1.08%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 248.602μs | ±1.52%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.633ms   | ±5.41%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.522ms  | ±1.34%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.538ms  | ±0.65%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.811ms   | ±1.27%   |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 10.116ms  | ±0.39%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 9.110ms   | ±0.88%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.515ms   | ±0.86%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.559ms   | ±1.30%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.160μs   | ±14.57%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.073μs   | ±23.57%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.461mb | 16.273ms  | ±0.27%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.461mb | 16.683ms  | ±1.43%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```