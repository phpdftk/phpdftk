# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-09 23:51:40 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.337ms | 2.506ms | 2.742ms | 4.742ms | 6.994ms |
| FPDF | 768.808μs | 839.553μs | 926.854μs | 1.524ms | 2.304ms |
| TCPDF | 9.977ms | 11.079ms | 12.069ms | 20.644ms | 31.135ms |
| mPDF | 25.182ms | 29.191ms | 33.331ms | 65.646ms | 105.262ms |
| Dompdf | 11.298ms | 16.106ms | 21.779ms | 73.719ms | 162.532ms |

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
| phpdftk | 3.373ms | 3.665ms | 3.879ms | 5.814ms | 8.273ms |
| FPDF | 1.021ms | 1.110ms | 1.238ms | 1.931ms | 2.747ms |
| TCPDF | 14.629ms | 15.516ms | 16.712ms | 26.493ms | 38.730ms |

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
| Pdf (Level 3) | 3.371ms | 4.444ms | 12.592ms |
| PdfDoc (Level 2) | 2.709ms | 3.154ms | 7.469ms |
| PdfWriter (Level 1) | 2.327ms | 2.776ms | 6.927ms |

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
| Pdf (Level 3) | 4.378ms | 12.150ms | 46.939ms |
| PdfDoc (Level 2) | 3.840ms | 10.044ms | — |

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
| Pdf (Level 3) | 4.086ms | 11.695ms | 45.111ms |
| PdfDoc (Level 2) | 3.271ms | 7.333ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.180ms | 1.670ms | 6.064ms |
| smalot/pdfparser | 1.977ms | 2.369ms | 5.728ms |
| setasign/fpdi | 1.938ms | 2.856ms | 29.941ms |

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
| phpdftk | 2.035ms | 1.371ms |
| smalot/pdfparser | FAIL | 1.913ms |
| setasign/fpdi | 2.987ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.373ms   | ±3.18%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.665ms   | ±2.14%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.879ms   | ±1.08%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.814ms   | ±1.37%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.273ms   | ±0.71%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.629ms  | ±1.71%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.516ms  | ±1.14%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.712ms  | ±1.40%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.493ms  | ±2.26%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 38.730ms  | ±0.98%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.021ms   | ±1.64%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.110ms   | ±2.08%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.238ms   | ±0.21%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.931ms   | ±9.72%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.747ms   | ±2.19%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.327ms   | ±0.57%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.776ms   | ±1.63%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.927ms   | ±1.10%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.709ms   | ±4.74%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.154ms   | ±1.33%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.469ms   | ±0.71%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.371ms   | ±0.25%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.444ms   | ±0.50%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.592ms  | ±0.54%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.539mb | 86.244ms  | ±0.69%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.455mb | 377.203ms | ±0.33%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.488mb | 1.474s    | ±0.36%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.605mb | 260.351ms | ±0.44%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.567mb | 198.686ms | ±0.72%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.348mb | 162.878ms | ±0.47%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.419mb | 221.500ms | ±1.49%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.956mb | 181.640ms | ±0.51%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.510mb | 347.099ms | ±0.15%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.051mb | 52.340ms  | ±0.13%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.967mb | 45.649ms  | ±0.57%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.893mb | 43.067ms  | ±0.39%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.236mb | 143.298ms | ±0.17%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.956mb | 47.600ms  | ±0.51%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.174mb | 60.975ms  | ±0.37%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.602mb | 90.205ms  | ±0.30%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.860mb | 38.475ms  | ±0.27%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.862mb | 47.391ms  | ±1.05%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.890mb | 48.785ms  | ±0.64%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.859mb | 47.110ms  | ±1.24%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.881mb | 46.250ms  | ±0.93%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.734mb | 72.229ms  | ±1.41%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.840mb | 44.638ms  | ±1.22%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.460mb | 39.733ms  | ±0.66%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.762mb | 43.493ms  | ±0.31%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.848mb | 48.694ms  | ±0.83%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.841mb | 47.857ms  | ±0.27%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.008mb | 43.178ms  | ±0.34%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.213mb | 230.469ms | ±0.56%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.431mb | 172.487ms | ±0.14%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.238mb | 57.204ms  | ±0.45%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.845mb | 116.783ms | ±0.28%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.894mb | 1.412s    | ±0.43%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.363mb | 25.939ms  | ±1.44%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.324mb | 58.106ms  | ±0.93%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.135mb | 531.508ms | ±0.76%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.581mb | 65.290ms  | ±8.83%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.301mb | 86.533ms  | ±1.26%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.070mb | 729.640ms | ±0.45%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.346mb | 18.860ms  | ±0.10%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.346mb | 43.090ms  | ±0.57%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.988mb | 323.574ms | ±0.83%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.227ms   | ±1.12%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.670ms   | ±13.52% |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.064ms   | ±1.33%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.035ms   | ±1.66%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.371ms   | ±0.97%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.977ms   | ±0.93%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.369ms   | ±0.21%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.728ms   | ±0.53%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 537.477μs | ±1.70%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.913ms   | ±0.51%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.938ms   | ±0.43%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.856ms   | ±0.82%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.941ms  | ±0.88%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.987ms   | ±1.22%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.519ms   | ±1.00%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.290ms   | ±0.72%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.417ms   | ±0.26%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.845ms   | ±1.19%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.519μs   | ±17.58% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.180ms   | ±0.90%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.538mb  | 27.591ms  | ±0.41%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.707mb | 245.555ms | ±0.26%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.035mb | 1.202s    | ±0.37%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.816mb | 191.490ms | ±0.54%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.086ms   | ±0.53%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.695ms  | ±5.72%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.111ms  | ±0.65%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.271ms   | ±0.24%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.333ms   | ±0.78%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.292ms   | ±0.90%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.506ms   | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.742ms   | ±1.63%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.742ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.994ms   | ±0.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.564ms   | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.817ms   | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.455ms  | ±1.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.663ms   | ±0.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.447ms   | ±1.54%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 666.438μs | ±3.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.296ms   | ±2.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.630ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.230ms   | ±2.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 226.036ms | ±44.46% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.569ms   | ±2.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.819ms   | ±15.51% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.058ms   | ±0.93%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.977ms   | ±1.04%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.079ms  | ±0.71%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.069ms  | ±0.84%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.644ms  | ±1.06%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.135ms  | ±0.74%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 768.808μs | ±4.30%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 839.553μs | ±2.63%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 926.854μs | ±14.61% |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.524ms   | ±59.54% |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.304ms   | ±0.76%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.182ms  | ±2.33%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.191ms  | ±0.71%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.331ms  | ±0.81%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 65.646ms  | ±0.54%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 105.262ms | ±0.60%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.298ms  | ±0.67%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.106ms  | ±0.54%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.779ms  | ±1.56%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 73.719ms  | ±0.93%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 162.532ms | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.066ms   | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.010ms  | ±0.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.619μs   | ±18.84% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 183.675ms | ±33.06% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 458.284μs | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.024ms   | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.402ms   | ±1.12%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.718ms  | ±4.74%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 87.164ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.264ms  | ±1.49%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.101ms  | ±0.32%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 179.073ms | ±20.84% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.385ms  | ±0.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.227ms  | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.475ms  | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.475ms  | ±0.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.857ms  | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.155ms   | ±1.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.501ms  | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.500ms  | ±0.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.337ms  | ±0.76%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 10.334ms  | ±1.41%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 10.310ms  | ±1.81%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 12.226ms  | ±1.19%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 12.218ms  | ±0.80%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 11.196ms  | ±0.31%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 10.461ms  | ±0.83%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 19.572ms  | ±1.56%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 3.057ms   | ±3.36%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.564μs  | ±1.04%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 245.495μs | ±0.64%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.378ms   | ±0.54%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.150ms  | ±1.18%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 46.939ms  | ±0.47%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.840ms   | ±0.86%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 10.044ms  | ±0.77%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.910ms   | ±0.73%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.471ms   | ±0.46%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.511ms   | ±0.94%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.959μs   | ±12.07% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.873μs   | ±25.71% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.485mb | 16.316ms  | ±0.37%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.485mb | 16.315ms  | ±2.52%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```