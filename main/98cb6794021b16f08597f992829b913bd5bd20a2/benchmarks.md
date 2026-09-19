# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-19 06:57:47 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 8.205ms | 1.822ms | 1.811ms | 3.075ms | 4.486ms |
| FPDF | 841.447μs | 841.554μs | 942.537μs | 1.184ms | 1.562ms |
| TCPDF | 7.046ms | 7.591ms | 8.485ms | 13.546ms | 20.148ms |
| mPDF | 17.210ms | 19.686ms | 22.002ms | 39.229ms | 61.084ms |
| Dompdf | 7.398ms | 10.078ms | 13.202ms | 42.590ms | 95.585ms |

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
| phpdftk | 2.376ms | 2.490ms | 2.632ms | 3.624ms | 5.324ms |
| FPDF | 940.330μs | 921.733μs | 1.088ms | 1.363ms | 1.902ms |
| TCPDF | 10.810ms | 10.974ms | 11.460ms | 17.315ms | 24.667ms |

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
| Pdf (Level 3) | 2.467ms | 2.866ms | 7.513ms |
| PdfDoc (Level 2) | 2.024ms | 2.006ms | 4.864ms |
| PdfWriter (Level 1) | 1.729ms | 1.835ms | 4.412ms |

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
| Pdf (Level 3) | 2.779ms | 7.414ms | 28.871ms |
| PdfDoc (Level 2) | 2.353ms | 6.064ms | — |

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
| Pdf (Level 3) | 2.554ms | 6.964ms | 25.616ms |
| PdfDoc (Level 2) | 2.280ms | 4.367ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 3.360ms | 953.348μs | 3.315ms |
| smalot/pdfparser | 1.299ms | 1.501ms | 3.624ms |
| setasign/fpdi | 1.191ms | 1.651ms | 16.221ms |

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
| phpdftk | 1.147ms | 778.462μs |
| smalot/pdfparser | FAIL | 1.195ms |
| setasign/fpdi | 1.774ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 2.376ms   | ±5.36%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 2.490ms   | ±5.67%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 2.632ms   | ±6.68%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 3.624ms   | ±0.45%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 5.324ms   | ±0.56%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 10.810ms  | ±3.37%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 10.974ms  | ±2.03%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 11.460ms  | ±0.15%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 17.315ms  | ±0.45%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 24.667ms  | ±0.45%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 940.330μs | ±14.19% |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 921.733μs | ±13.08% |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.088ms   | ±11.07% |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.363ms   | ±7.00%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 1.902ms   | ±1.17%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 1.729ms   | ±5.38%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 1.835ms   | ±4.86%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 4.412ms   | ±0.66%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.024ms   | ±1.16%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.006ms   | ±4.79%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 4.864ms   | ±0.94%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 2.467ms   | ±4.24%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 2.866ms   | ±0.95%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 7.513ms   | ±0.50%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.119mb | 48.734ms  | ±0.35%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 22.282mb | 205.790ms | ±0.15%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 47.130mb | 808.023ms | ±0.13%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.306mb | 144.192ms | ±0.22%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.118mb | 113.480ms | ±0.30%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.934mb | 89.153ms  | ±0.05%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 19.065mb | 120.958ms | ±0.15%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.546mb | 99.903ms  | ±0.08%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 21.275mb | 188.767ms | ±0.26%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.594mb | 29.913ms  | ±0.38%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.506mb | 26.379ms  | ±0.90%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.438mb | 24.958ms  | ±0.72%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.799mb | 78.839ms  | ±0.16%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.495mb | 27.477ms  | ±0.07%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.721mb | 34.099ms  | ±0.39%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.172mb | 49.640ms  | ±0.35%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.392mb | 22.482ms  | ±0.11%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.395mb | 26.937ms  | ±0.55%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.423mb | 27.889ms  | ±0.77%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.391mb | 27.361ms  | ±0.48%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.414mb | 26.366ms  | ±0.20%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.289mb | 42.602ms  | ±0.70%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.398mb | 26.372ms  | ±0.93%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.011mb | 23.369ms  | ±0.47%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.360mb | 24.924ms  | ±0.50%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.381mb | 27.945ms  | ±0.37%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.374mb | 27.755ms  | ±0.30%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.559mb | 25.821ms  | ±0.33%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 20.006mb | 130.760ms | ±1.47%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.955mb | 97.531ms  | ±0.23%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.880mb | 32.745ms  | ±0.44%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.407mb | 65.746ms  | ±0.19%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 45.101mb | 773.703ms | ±0.23%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.800mb | 15.799ms  | ±4.22%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.760mb | 34.391ms  | ±0.57%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.571mb | 297.356ms | ±0.23%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.084mb | 40.406ms  | ±8.92%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.738mb | 52.083ms  | ±1.52%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.506mb | 443.732ms | ±0.21%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.783mb | 12.556ms  | ±0.91%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.783mb | 26.959ms  | ±0.11%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.491mb | 187.536ms | ±0.07%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 721.366μs | ±4.71%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 953.348μs | ±1.08%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 3.315ms   | ±0.28%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.147ms   | ±1.05%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 778.462μs | ±1.93%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.299ms   | ±2.34%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.501ms   | ±1.40%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 3.624ms   | ±5.07%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 356.335μs | ±1.52%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.195ms   | ±1.61%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.191ms   | ±1.29%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 1.651ms   | ±0.98%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 16.221ms  | ±0.93%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 1.774ms   | ±0.49%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 936.205μs | ±1.74%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 4.253ms   | ±0.90%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 3.221ms   | ±0.63%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.356ms   | ±0.89%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 1.519μs   | ±36.42% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 3.360ms   | ±1.21%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.662mb  | 14.865ms  | ±0.10%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.060mb | 131.698ms | ±0.35%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.406mb | 651.886ms | ±0.15%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.086mb | 104.434ms | ±0.17%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 2.554ms   | ±2.84%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 6.964ms   | ±0.63%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 25.616ms  | ±0.60%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.280ms   | ±1.44%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 4.367ms   | ±1.75%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 1.782ms   | ±4.72%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 1.822ms   | ±5.91%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 1.811ms   | ±1.93%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 3.075ms   | ±1.87%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 4.486ms   | ±1.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 2.473ms   | ±4.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 2.452ms   | ±1.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 7.656ms   | ±3.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.284ms   | ±2.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.734ms   | ±7.06%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 857.900μs | ±16.94% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.144ms   | ±4.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 2.309ms   | ±3.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 2.223ms   | ±3.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 141.380ms | ±14.64% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 2.317ms   | ±1.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 3.512ms   | ±90.86% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 3.561ms   | ±1.04%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.046ms   | ±2.61%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 7.591ms   | ±2.83%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 8.485ms   | ±2.38%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 13.546ms  | ±0.84%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 20.148ms  | ±0.84%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 841.447μs | ±9.60%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 841.554μs | ±2.86%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 942.537μs | ±9.87%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.184ms   | ±3.24%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.562ms   | ±0.44%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 17.210ms  | ±3.05%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 19.686ms  | ±1.52%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 22.002ms  | ±1.06%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 39.229ms  | ±0.62%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 61.084ms  | ±0.42%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 7.398ms   | ±1.79%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 10.078ms  | ±1.38%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 13.202ms  | ±0.78%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 42.590ms  | ±0.74%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 95.585ms  | ±0.15%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 3.147ms   | ±1.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 30.019ms  | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.656μs   | ±30.62% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 0.476μs   | ±44.72% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 0.666μs   | ±22.22% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 140.019ms | ±21.53% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 271.494μs | ±1.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 1.907ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 2.362ms   | ±2.96%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 10.876ms  | ±26.52% |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 47.395ms  | ±0.08%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 8.198ms   | ±0.13%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 14.375ms  | ±0.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 137.268ms | ±19.71% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 8.446ms   | ±1.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 8.144ms   | ±1.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 8.423ms   | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 8.463ms   | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 8.539ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.233ms   | ±5.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 8.458ms   | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 8.287ms   | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 8.205ms   | ±0.46%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.351mb  | 6.786ms   | ±1.90%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.245mb  | 6.560ms   | ±0.55%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.397mb  | 7.528ms   | ±0.16%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.095mb  | 7.762ms   | ±0.99%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.577mb  | 6.969ms   | ±0.43%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.651mb  | 6.641ms   | ±0.40%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.896mb | 12.009ms  | ±0.87%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.791mb  | 1.916ms   | ±0.84%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 23.638μs  | ±3.76%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 150.690μs | ±1.49%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 2.779ms   | ±1.45%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 7.414ms   | ±2.00%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 28.871ms  | ±1.37%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 2.353ms   | ±4.33%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 6.064ms   | ±1.21%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 5.268ms   | ±1.07%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 5.075ms   | ±0.88%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 5.147ms   | ±2.13%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 0.824μs   | ±20.20% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 0.873μs   | ±47.14% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.972mb | 11.229ms  | ±1.19%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.972mb | 10.967ms  | ±1.08%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```