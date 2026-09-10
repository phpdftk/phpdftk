# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-10 00:56:18 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.675ms | 2.476ms | 2.721ms | 4.653ms | 6.816ms |
| FPDF | 763.735μs | 844.471μs | 931.114μs | 1.523ms | 2.234ms |
| TCPDF | 10.019ms | 10.851ms | 11.813ms | 19.391ms | 28.213ms |
| mPDF | 25.464ms | 29.000ms | 32.603ms | 60.639ms | 96.144ms |
| Dompdf | 11.087ms | 15.117ms | 19.854ms | 65.986ms | 149.912ms |

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
| phpdftk | 3.305ms | 3.518ms | 3.822ms | 5.703ms | 8.267ms |
| FPDF | 1.053ms | 1.133ms | 1.224ms | 1.907ms | 2.712ms |
| TCPDF | 14.494ms | 15.422ms | 16.498ms | 24.992ms | 35.444ms |

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
| Pdf (Level 3) | 3.326ms | 4.345ms | 12.702ms |
| PdfDoc (Level 2) | 2.672ms | 3.104ms | 7.344ms |
| PdfWriter (Level 1) | 2.288ms | 2.736ms | 6.754ms |

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
| Pdf (Level 3) | 4.293ms | 11.962ms | 45.220ms |
| PdfDoc (Level 2) | 3.681ms | 9.866ms | — |

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
| Pdf (Level 3) | 4.038ms | 11.478ms | 44.278ms |
| PdfDoc (Level 2) | 3.240ms | 7.245ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.065ms | 1.642ms | 6.053ms |
| smalot/pdfparser | 1.980ms | 2.339ms | 5.429ms |
| setasign/fpdi | 1.891ms | 2.724ms | 28.650ms |

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
| phpdftk | 2.014ms | 1.329ms |
| smalot/pdfparser | FAIL | 1.896ms |
| setasign/fpdi | 2.857ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.305ms   | ±1.49%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.518ms   | ±1.44%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.822ms   | ±0.77%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.703ms   | ±0.49%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.267ms   | ±0.92%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.494ms  | ±1.78%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.422ms  | ±0.13%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.498ms  | ±0.62%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 24.992ms  | ±0.05%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 35.444ms  | ±0.49%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.053ms   | ±1.11%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.133ms   | ±0.80%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.224ms   | ±1.41%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.907ms   | ±0.34%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.712ms   | ±0.51%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.288ms   | ±2.64%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.736ms   | ±0.79%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.754ms   | ±0.67%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.672ms   | ±0.94%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.104ms   | ±0.60%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.344ms   | ±3.87%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.326ms   | ±0.72%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.345ms   | ±0.98%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.702ms  | ±45.27% |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.539mb | 79.781ms  | ±3.85%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.455mb | 357.169ms | ±2.02%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.488mb | 1.331s    | ±0.07%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.605mb | 240.621ms | ±3.87%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.567mb | 194.651ms | ±0.82%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.348mb | 150.065ms | ±0.40%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.419mb | 205.586ms | ±2.96%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 17.956mb | 167.356ms | ±0.34%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.510mb | 338.768ms | ±2.89%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.051mb | 52.550ms  | ±3.59%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 15.967mb | 44.994ms  | ±2.64%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 15.893mb | 40.939ms  | ±2.59%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.236mb | 132.449ms | ±4.68%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 15.956mb | 44.927ms  | ±0.71%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.174mb | 56.478ms  | ±2.18%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.602mb | 84.513ms  | ±0.77%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 15.860mb | 38.309ms  | ±0.81%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 15.862mb | 44.495ms  | ±1.61%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 15.890mb | 46.112ms  | ±2.10%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 15.859mb | 45.194ms  | ±0.53%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 15.881mb | 43.274ms  | ±1.18%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.734mb | 70.974ms  | ±1.46%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.840mb | 43.173ms  | ±0.66%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.460mb | 37.765ms  | ±0.40%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.762mb | 41.293ms  | ±0.49%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.848mb | 46.012ms  | ±0.40%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.841mb | 45.944ms  | ±1.98%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.008mb | 42.182ms  | ±0.89%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.213mb | 212.400ms | ±0.54%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.431mb | 162.693ms | ±0.58%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.238mb | 53.847ms  | ±2.52%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 16.845mb | 109.972ms | ±2.71%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 42.894mb | 1.393s    | ±0.58%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.363mb | 23.704ms  | ±2.07%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.324mb | 52.358ms  | ±0.81%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.135mb | 460.266ms | ±0.57%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.581mb | 63.731ms  | ±9.04%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.301mb | 81.751ms  | ±1.42%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.070mb | 657.826ms | ±0.11%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.346mb | 17.543ms  | ±0.32%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.346mb | 41.368ms  | ±0.80%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.988mb | 274.424ms | ±0.58%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.190ms   | ±1.28%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.642ms   | ±3.12%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.053ms   | ±0.64%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.014ms   | ±1.20%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.329ms   | ±0.88%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.980ms   | ±1.39%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.339ms   | ±0.66%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.429ms   | ±0.75%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 554.169μs | ±0.74%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.896ms   | ±0.55%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.891ms   | ±0.74%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.724ms   | ±0.66%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.650ms  | ±0.93%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.857ms   | ±0.49%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.482ms   | ±0.66%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.020ms   | ±0.95%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.318ms   | ±0.58%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.816ms   | ±1.10%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 4.254μs   | ±23.95% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.065ms   | ±0.85%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.538mb  | 24.915ms  | ±1.07%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.707mb | 228.379ms | ±7.77%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.035mb | 1.097s    | ±0.38%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.816mb | 175.133ms | ±7.14%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.038ms   | ±0.58%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.478ms  | ±0.77%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 44.278ms  | ±0.72%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.240ms   | ±0.38%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.245ms   | ±4.03%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.281ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.476ms   | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.721ms   | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.653ms   | ±0.25%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.816ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.467ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.716ms   | ±0.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.806ms  | ±20.37% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.537ms   | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.332ms   | ±0.40%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 588.958μs | ±0.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.144ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.548ms   | ±1.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.103ms   | ±1.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 232.701ms | ±30.02% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.471ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.751ms   | ±45.21% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.986ms   | ±0.66%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.019ms  | ±0.51%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.851ms  | ±0.62%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.813ms  | ±0.13%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.391ms  | ±0.39%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.213ms  | ±0.41%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 763.735μs | ±2.39%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 844.471μs | ±2.35%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 931.114μs | ±1.37%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.523ms   | ±1.02%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.234ms   | ±0.44%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.464ms  | ±1.93%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.000ms  | ±0.22%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.603ms  | ±0.54%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 60.639ms  | ±0.80%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 96.144ms  | ±0.65%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.087ms  | ±0.63%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.117ms  | ±0.28%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 19.854ms  | ±0.90%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 65.986ms  | ±0.85%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 149.912ms | ±0.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.873ms   | ±0.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 53.624ms  | ±0.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.673μs   | ±57.14% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.763μs   | ±88.39% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 143.826ms | ±49.60% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 499.818μs | ±1.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.875ms   | ±0.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.267ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 10.527ms  | ±4.07%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 82.774ms  | ±1.98%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.023ms  | ±0.66%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.324ms  | ±1.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 242.833ms | ±15.57% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.923ms  | ±0.32%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.632ms  | ±1.21%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.824ms  | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.859ms  | ±0.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.206ms  | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.081ms   | ±1.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.864ms  | ±0.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.985ms  | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.675ms  | ±0.70%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 9.691ms   | ±0.31%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 9.739ms   | ±1.13%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 11.631ms  | ±0.51%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 11.232ms  | ±0.79%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 10.921ms  | ±0.72%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 9.827ms   | ±0.58%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 17.861ms  | ±0.25%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 3.030ms   | ±0.21%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.886μs  | ±1.98%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 233.335μs | ±0.14%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.293ms   | ±1.10%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.962ms  | ±0.70%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 45.220ms  | ±0.67%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.681ms   | ±0.86%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.866ms   | ±1.00%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.743ms   | ±0.32%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.323ms   | ±3.73%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.340ms   | ±0.78%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.061μs   | ±20.20% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.219μs   | ±32.90% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.485mb | 16.182ms  | ±0.67%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.485mb | 16.064ms  | ±0.31%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```