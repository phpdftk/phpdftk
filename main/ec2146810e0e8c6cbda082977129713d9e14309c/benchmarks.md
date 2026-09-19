# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-19 16:00:56 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.203ms | 2.505ms | 2.755ms | 4.729ms | 6.986ms |
| FPDF | 760.314μs | 840.256μs | 914.606μs | 1.519ms | 2.266ms |
| TCPDF | 9.895ms | 10.938ms | 12.067ms | 20.472ms | 31.055ms |
| mPDF | 25.010ms | 28.903ms | 32.931ms | 64.655ms | 105.119ms |
| Dompdf | 11.308ms | 15.796ms | 21.498ms | 72.401ms | 161.919ms |

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
| phpdftk | 3.434ms | 3.536ms | 3.871ms | 5.864ms | 8.315ms |
| FPDF | 1.022ms | 1.130ms | 1.203ms | 1.886ms | 2.712ms |
| TCPDF | 14.306ms | 15.336ms | 16.755ms | 26.352ms | 39.022ms |

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
| Pdf (Level 3) | 3.350ms | 4.431ms | 12.673ms |
| PdfDoc (Level 2) | 2.676ms | 3.162ms | 7.530ms |
| PdfWriter (Level 1) | 2.272ms | 2.734ms | 6.880ms |

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
| Pdf (Level 3) | 4.296ms | 12.056ms | 47.052ms |
| PdfDoc (Level 2) | 3.773ms | 9.938ms | — |

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
| Pdf (Level 3) | 4.024ms | 11.554ms | 44.845ms |
| PdfDoc (Level 2) | 3.317ms | 7.237ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.146ms | 1.685ms | 5.972ms |
| smalot/pdfparser | 1.965ms | 2.339ms | 5.681ms |
| setasign/fpdi | 1.905ms | 2.837ms | 29.842ms |

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
| phpdftk | 2.013ms | 1.352ms |
| smalot/pdfparser | FAIL | 1.886ms |
| setasign/fpdi | 2.959ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.434ms   | ±3.98%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.536ms   | ±0.31%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.871ms   | ±1.33%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.864ms   | ±0.73%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.315ms   | ±0.47%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.306ms  | ±0.57%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.336ms  | ±0.37%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.755ms  | ±0.54%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.352ms  | ±0.33%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 39.022ms  | ±12.30% |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.022ms   | ±1.63%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.130ms   | ±1.65%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.203ms   | ±0.75%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.886ms   | ±0.44%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.712ms   | ±0.34%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.272ms   | ±1.05%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.734ms   | ±0.30%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.880ms   | ±1.18%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.676ms   | ±1.62%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.162ms   | ±3.91%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.530ms   | ±4.31%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.350ms   | ±0.76%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.431ms   | ±0.60%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.673ms  | ±0.58%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.281mb | 88.568ms  | ±0.40%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 22.490mb | 386.471ms | ±0.11%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 47.338mb | 1.518s    | ±1.13%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.515mb | 264.516ms | ±0.60%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.327mb | 205.725ms | ±2.12%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.096mb | 165.229ms | ±0.12%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 19.227mb | 226.419ms | ±0.30%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.772mb | 187.448ms | ±0.21%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 21.436mb | 355.885ms | ±0.16%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.757mb | 53.889ms  | ±0.33%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.670mb | 46.860ms  | ±0.87%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.599mb | 43.821ms  | ±0.70%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.961mb | 147.567ms | ±0.66%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.659mb | 49.081ms  | ±0.03%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.884mb | 62.594ms  | ±1.13%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.336mb | 92.597ms  | ±0.41%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.557mb | 39.960ms  | ±0.46%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.557mb | 47.257ms  | ±1.08%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.584mb | 49.154ms  | ±0.28%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.553mb | 48.055ms  | ±0.33%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.575mb | 46.781ms  | ±0.30%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.497mb | 73.662ms  | ±2.32%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.606mb | 44.979ms  | ±0.74%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.219mb | 40.142ms  | ±0.39%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.522mb | 44.306ms  | ±1.08%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.543mb | 49.410ms  | ±0.40%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.536mb | 49.496ms  | ±0.81%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.767mb | 44.113ms  | ±0.61%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 20.168mb | 234.635ms | ±0.54%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.117mb | 174.608ms | ±0.25%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.042mb | 58.069ms  | ±0.22%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.568mb | 120.175ms | ±0.74%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 45.309mb | 1.441s    | ±0.84%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.950mb | 25.652ms  | ±2.51%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.911mb | 58.190ms  | ±1.10%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.722mb | 519.896ms | ±0.28%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.234mb | 64.009ms  | ±8.97%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.888mb | 86.613ms  | ±1.18%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.657mb | 730.467ms | ±0.23%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.933mb | 18.610ms  | ±0.13%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.933mb | 42.748ms  | ±0.33%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.641mb | 318.160ms | ±0.47%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.224ms   | ±0.56%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.685ms   | ±0.88%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.972ms   | ±0.59%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.013ms   | ±0.99%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.352ms   | ±1.12%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.965ms   | ±0.58%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.339ms   | ±1.13%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.681ms   | ±0.78%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 541.425μs | ±1.55%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.886ms   | ±0.58%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.905ms   | ±1.06%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.837ms   | ±0.52%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.842ms  | ±0.75%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.959ms   | ±4.13%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.500ms   | ±0.34%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.320ms   | ±0.91%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.385ms   | ±0.67%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.832ms   | ±0.44%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.186μs   | ±17.50% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.146ms   | ±0.70%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.676mb  | 27.827ms  | ±0.03%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.073mb | 251.601ms | ±0.74%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.419mb | 1.243s    | ±0.43%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.099mb | 194.769ms | ±0.62%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.024ms   | ±0.69%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.554ms  | ±0.62%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 44.845ms  | ±0.17%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.317ms   | ±1.17%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.237ms   | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.278ms   | ±1.18%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.505ms   | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.755ms   | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.729ms   | ±0.95%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.986ms   | ±1.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.596ms   | ±1.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.810ms   | ±1.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.252ms  | ±2.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.627ms   | ±0.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.393ms   | ±2.43%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 665.047μs | ±3.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.196ms   | ±5.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.671ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.195ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 181.780ms | ±23.02% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.547ms   | ±0.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.819ms   | ±22.39% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.036ms   | ±1.01%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.895ms   | ±0.52%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.938ms  | ±0.71%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.067ms  | ±0.96%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.472ms  | ±0.61%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.055ms  | ±0.89%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 760.314μs | ±2.32%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 840.256μs | ±1.29%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 914.606μs | ±0.93%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.519ms   | ±0.95%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.266ms   | ±0.41%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.010ms  | ±2.30%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 28.903ms  | ±0.65%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.931ms  | ±0.35%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 64.655ms  | ±0.50%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 105.119ms | ±1.93%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.308ms  | ±0.88%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.796ms  | ±0.67%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.498ms  | ±0.85%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 72.401ms  | ±0.76%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 161.919ms | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.032ms   | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 49.768ms  | ±0.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 209.683ms | ±27.12% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 451.985μs | ±1.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.984ms   | ±0.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.394ms   | ±4.08%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.369ms  | ±6.33%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 85.406ms  | ±1.15%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.013ms  | ±0.76%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 24.850ms  | ±1.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 290.240ms | ±16.47% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.390ms  | ±1.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.122ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.394ms  | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.609ms  | ±0.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.808ms  | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.132ms   | ±22.24% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.403ms  | ±1.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.494ms  | ±0.24%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.203ms  | ±0.63%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.402mb  | 10.453ms  | ±0.38%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.299mb  | 10.329ms  | ±1.28%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.449mb  | 12.067ms  | ±0.24%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.147mb  | 12.191ms  | ±0.15%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.629mb  | 11.218ms  | ±0.73%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.706mb  | 10.542ms  | ±0.95%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 8.841mb  | 13.008ms  | ±0.69%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.950mb | 19.774ms  | ±0.46%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.839mb  | 3.120ms   | ±0.11%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.412μs  | ±1.94%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 242.149μs | ±0.34%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.296ms   | ±1.04%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.056ms  | ±0.47%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.052ms  | ±1.85%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.773ms   | ±1.71%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.938ms   | ±0.58%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.770ms   | ±1.31%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.426ms   | ±0.30%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.505ms   | ±0.43%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.766μs   | ±21.60% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.986μs   | ±26.50% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.134mb | 17.448ms  | ±0.37%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.134mb | 17.303ms  | ±0.95%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```