# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-11 11:35:40 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.566ms | 2.626ms | 2.863ms | 4.917ms | 7.333ms |
| FPDF | 819.644μs | 917.742μs | 969.367μs | 1.574ms | 2.361ms |
| TCPDF | 10.964ms | 11.827ms | 13.104ms | 21.504ms | 32.364ms |
| mPDF | 27.265ms | 31.328ms | 35.390ms | 68.735ms | 107.843ms |
| Dompdf | 11.903ms | 16.577ms | 22.456ms | 75.233ms | 166.575ms |

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
| phpdftk | 3.385ms | 3.603ms | 3.889ms | 5.876ms | 8.599ms |
| FPDF | 1.077ms | 1.184ms | 1.225ms | 1.952ms | 2.799ms |
| TCPDF | 15.159ms | 16.137ms | 17.068ms | 26.894ms | 39.125ms |

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
| Pdf (Level 3) | 3.387ms | 4.495ms | 12.708ms |
| PdfDoc (Level 2) | 2.735ms | 3.194ms | 7.649ms |
| PdfWriter (Level 1) | 2.336ms | 2.814ms | 6.988ms |

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
| Pdf (Level 3) | 4.560ms | 12.481ms | 47.821ms |
| PdfDoc (Level 2) | 3.910ms | 10.267ms | — |

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
| Pdf (Level 3) | 4.326ms | 12.006ms | 45.680ms |
| PdfDoc (Level 2) | 3.419ms | 7.619ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.254ms | 1.701ms | 6.053ms |
| smalot/pdfparser | 2.019ms | 2.465ms | 5.851ms |
| setasign/fpdi | 1.972ms | 2.887ms | 30.353ms |

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
| phpdftk | 2.056ms | 1.402ms |
| smalot/pdfparser | FAIL | 1.954ms |
| setasign/fpdi | 3.033ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.385ms   | ±0.91%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.603ms   | ±1.53%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.889ms   | ±0.69%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.876ms   | ±0.36%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.599ms   | ±0.61%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.159ms  | ±1.14%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.137ms  | ±1.91%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.068ms  | ±0.10%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.894ms  | ±1.02%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 39.125ms  | ±0.36%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.077ms   | ±3.13%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.184ms   | ±2.32%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.225ms   | ±1.35%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.952ms   | ±0.91%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.799ms   | ±0.46%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.336ms   | ±1.00%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.814ms   | ±1.13%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.988ms   | ±0.35%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.735ms   | ±0.69%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.194ms   | ±5.65%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.649ms   | ±0.75%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.387ms   | ±2.58%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.495ms   | ±0.63%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.708ms  | ±1.52%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.556mb | 87.869ms  | ±3.96%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.472mb | 387.329ms | ±1.63%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.505mb | 1.486s    | ±1.11%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.623mb | 267.835ms | ±1.22%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.585mb | 200.836ms | ±0.44%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.365mb | 163.456ms | ±0.52%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.436mb | 223.939ms | ±0.60%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.973mb | 186.694ms | ±0.67%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.528mb | 353.465ms | ±0.26%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.068mb | 53.393ms  | ±0.30%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.984mb | 46.462ms  | ±0.53%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.910mb | 43.033ms  | ±0.93%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.254mb | 146.304ms | ±0.54%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.973mb | 49.825ms  | ±0.34%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.191mb | 61.915ms  | ±0.40%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.619mb | 91.731ms  | ±0.49%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.877mb | 40.020ms  | ±1.87%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.880mb | 47.559ms  | ±0.37%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.907mb | 49.356ms  | ±0.35%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.876mb | 48.315ms  | ±0.20%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.898mb | 47.031ms  | ±0.58%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.751mb | 73.306ms  | ±0.41%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.858mb | 44.971ms  | ±0.74%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.477mb | 40.109ms  | ±0.89%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.779mb | 43.983ms  | ±0.23%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.865mb | 49.627ms  | ±0.84%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.859mb | 49.109ms  | ±4.68%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.025mb | 44.459ms  | ±0.87%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.230mb | 233.601ms | ±0.47%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.448mb | 172.807ms | ±0.24%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.255mb | 58.163ms  | ±0.76%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.862mb | 117.711ms | ±0.28%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.912mb | 1.421s    | ±0.15%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.381mb | 26.218ms  | ±2.29%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.341mb | 59.311ms  | ±0.82%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.152mb | 562.659ms | ±1.87%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.599mb | 64.639ms  | ±8.71%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.318mb | 88.229ms  | ±1.46%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.087mb | 742.692ms | ±0.60%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.363mb | 19.480ms  | ±1.77%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.363mb | 43.864ms  | ±0.44%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.006mb | 323.669ms | ±1.37%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.278ms   | ±0.85%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.701ms   | ±1.29%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.053ms   | ±0.58%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.056ms   | ±0.59%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.402ms   | ±1.25%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.019ms   | ±1.32%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.465ms   | ±0.67%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.851ms   | ±2.93%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 581.708μs | ±0.75%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.954ms   | ±1.72%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.972ms   | ±1.45%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.887ms   | ±1.45%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.353ms  | ±1.14%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.033ms   | ±1.13%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.530ms   | ±0.58%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.318ms   | ±1.03%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.501ms   | ±0.38%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.941ms   | ±0.69%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.619μs   | ±21.76% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.254ms   | ±0.66%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.542mb  | 27.397ms  | ±0.43%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.711mb | 249.184ms | ±0.79%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.039mb | 1.210s    | ±0.30%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.820mb | 197.336ms | ±1.31%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.326ms   | ±2.28%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 12.006ms  | ±1.21%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.680ms  | ±0.43%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.419ms   | ±1.08%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.619ms   | ±1.70%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.381ms   | ±2.57%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.626ms   | ±0.97%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.863ms   | ±1.55%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.917ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.333ms   | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.730ms   | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.997ms   | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.708ms  | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.751ms   | ±2.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.437ms   | ±1.78%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 692.583μs | ±4.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.340ms   | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.830ms   | ±1.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.414ms   | ±1.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 181.698ms | ±46.12% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.775ms   | ±1.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.143ms   | ±15.15% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.312ms   | ±2.04%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.964ms  | ±3.01%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.827ms  | ±1.55%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 13.104ms  | ±2.20%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 21.504ms  | ±1.01%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 32.364ms  | ±2.21%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 819.644μs | ±4.91%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 917.742μs | ±3.92%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 969.367μs | ±7.33%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.574ms   | ±1.52%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.361ms   | ±0.73%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 27.265ms  | ±3.46%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 31.328ms  | ±0.68%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 35.390ms  | ±0.93%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 68.735ms  | ±0.43%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 107.843ms | ±0.77%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.903ms  | ±1.52%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.577ms  | ±1.92%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 22.456ms  | ±1.05%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 75.233ms  | ±0.75%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 166.575ms | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.201ms   | ±1.17%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.736ms  | ±0.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 204.313ms | ±29.15% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 466.771μs | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.114ms   | ±0.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.497ms   | ±1.25%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 14.196ms  | ±6.85%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 84.524ms  | ±1.27%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.267ms  | ±0.62%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.067ms  | ±1.32%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 207.027ms | ±6.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.812ms  | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.620ms  | ±0.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.714ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.992ms  | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.175ms  | ±0.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.325ms   | ±0.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.860ms  | ±0.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 14.039ms  | ±1.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.566ms  | ±0.60%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 10.802ms  | ±1.77%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 10.698ms  | ±0.79%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 12.422ms  | ±0.37%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 12.780ms  | ±1.10%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 11.537ms  | ±0.74%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 10.804ms  | ±0.67%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 20.297ms  | ±0.10%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 3.138ms   | ±1.03%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.234μs  | ±1.28%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 249.203μs | ±0.91%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.560ms   | ±1.47%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.481ms  | ±0.93%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.821ms  | ±0.42%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.910ms   | ±1.82%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 10.267ms  | ±5.71%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 9.315ms   | ±0.87%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.802ms   | ±0.84%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.908ms   | ±1.03%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.049μs   | ±16.64% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.219μs   | ±32.90% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.502mb | 17.664ms  | ±0.68%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.502mb | 17.482ms  | ±1.66%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```