# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-18 02:52:38 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 7.278ms | 1.414ms | 1.613ms | 3.115ms | 4.008ms |
| FPDF | 494.040μs | 529.423μs | 569.971μs | 895.540μs | 1.296ms |
| TCPDF | 5.879ms | 6.346ms | 6.907ms | 11.226ms | 16.657ms |
| mPDF | 14.439ms | 16.503ms | 18.467ms | 35.034ms | 55.140ms |
| Dompdf | 6.610ms | 8.799ms | 11.531ms | 40.561ms | 86.989ms |

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
| phpdftk | 2.007ms | 2.489ms | 2.196ms | 3.246ms | 4.572ms |
| FPDF | 648.271μs | 690.410μs | 755.246μs | 9.774ms | 70.163ms |
| TCPDF | 8.771ms | 9.096ms | 9.560ms | 14.463ms | 20.473ms |

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
| Pdf (Level 3) | 1.918ms | 2.480ms | 6.658ms |
| PdfDoc (Level 2) | 1.564ms | 1.845ms | 4.145ms |
| PdfWriter (Level 1) | 34.446ms | 2.361ms | 3.745ms |

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
| Pdf (Level 3) | 2.470ms | 11.545ms | 24.716ms |
| PdfDoc (Level 2) | 2.166ms | 30.709ms | — |

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
| Pdf (Level 3) | 2.325ms | 6.476ms | 24.191ms |
| PdfDoc (Level 2) | 54.266ms | 4.125ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 3.209ms | 910.663μs | 3.054ms |
| smalot/pdfparser | 1.173ms | 1.376ms | 3.263ms |
| setasign/fpdi | 1.101ms | 1.539ms | 14.422ms |

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
| phpdftk | 1.091ms | 772.950μs |
| smalot/pdfparser | FAIL | 1.096ms |
| setasign/fpdi | 1.627ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 2.007ms   | ±14.15%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 2.489ms   | ±70.46%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 2.196ms   | ±0.08%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 3.246ms   | ±0.53%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 4.572ms   | ±1.84%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 8.771ms   | ±1.21%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 9.096ms   | ±0.52%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 9.560ms   | ±1.85%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 14.463ms  | ±0.21%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 20.473ms  | ±0.08%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 648.271μs | ±2.24%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 690.410μs | ±1.02%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 755.246μs | ±1.94%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 9.774ms   | ±94.80%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 70.163ms  | ±63.66%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 34.446ms  | ±74.74%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.361ms   | ±138.19% |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 3.745ms   | ±0.53%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 1.564ms   | ±73.31%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 1.845ms   | ±48.64%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 4.145ms   | ±1.00%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 1.918ms   | ±1.75%   |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 2.480ms   | ±1.35%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 6.658ms   | ±9.23%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.695mb | 42.086ms  | ±0.29%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 21.652mb | 181.501ms | ±1.01%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 45.851mb | 727.202ms | ±0.70%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 27.861mb | 127.259ms | ±0.56%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.724mb | 98.431ms  | ±1.44%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.507mb | 79.083ms  | ±1.99%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.609mb | 107.025ms | ±0.85%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.123mb | 90.027ms  | ±0.55%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 20.746mb | 170.467ms | ±0.26%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.196mb | 25.738ms  | ±0.18%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.112mb | 22.631ms  | ±0.44%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.036mb | 21.901ms  | ±2.16%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.395mb | 70.681ms  | ±0.59%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.101mb | 23.891ms  | ±1.54%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.319mb | 29.553ms  | ±0.25%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.754mb | 43.431ms  | ±0.32%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.003mb | 19.354ms  | ±0.10%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.006mb | 23.543ms  | ±0.19%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.033mb | 24.723ms  | ±1.70%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.002mb | 24.253ms  | ±0.52%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.024mb | 23.601ms  | ±9.41%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 30.942mb | 36.692ms  | ±0.24%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 17.983mb | 23.467ms  | ±1.39%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.669mb | 20.141ms  | ±0.11%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 15.970mb | 21.908ms  | ±0.14%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 15.991mb | 24.460ms  | ±0.18%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 15.984mb | 24.327ms  | ±0.22%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.151mb | 22.703ms  | ±4.38%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.382mb | 115.128ms | ±7.14%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.582mb | 87.071ms  | ±4.05%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.389mb | 30.151ms  | ±13.49%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.002mb | 57.826ms  | ±1.07%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 43.248mb | 695.439ms | ±0.79%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.504mb | 13.819ms  | ±12.81%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.464mb | 29.807ms  | ±0.78%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.275mb | 262.808ms | ±5.19%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.787mb | 35.823ms  | ±8.35%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.441mb | 45.979ms  | ±1.03%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.210mb | 384.732ms | ±0.40%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.486mb | 10.234ms  | ±0.29%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.486mb | 22.886ms  | ±0.38%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.129mb | 159.887ms | ±0.78%   |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 709.770μs | ±1.52%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 910.663μs | ±1.09%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 3.054ms   | ±0.53%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.091ms   | ±0.73%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 772.950μs | ±1.47%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.173ms   | ±0.62%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.376ms   | ±0.90%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 3.263ms   | ±1.69%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 345.791μs | ±1.48%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.096ms   | ±1.53%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.101ms   | ±1.22%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 1.539ms   | ±3.08%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 14.422ms  | ±0.85%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 1.627ms   | ±0.93%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 886.900μs | ±3.00%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 3.832ms   | ±1.96%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 2.940ms   | ±0.92%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.133ms   | ±0.38%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.586μs   | ±21.08%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 3.209ms   | ±0.56%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.545mb  | 12.997ms  | ±0.31%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.731mb | 115.514ms | ±0.74%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 48.136mb | 598.027ms | ±1.31%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 14.834mb | 96.222ms  | ±2.24%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 2.325ms   | ±27.52%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 6.476ms   | ±0.96%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 24.191ms  | ±0.78%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 54.266ms  | ±79.08%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 4.125ms   | ±24.39%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 1.497ms   | ±140.52% |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 1.414ms   | ±29.87%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 1.613ms   | ±99.65%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 3.115ms   | ±91.89%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 4.008ms   | ±35.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 2.071ms   | ±1.10%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 2.094ms   | ±2.20%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 6.892ms   | ±93.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 2.079ms   | ±1.43%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 1.348ms   | ±1.09%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 545.025μs | ±195.78% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 1.830ms   | ±129.50% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 2.058ms   | ±155.02% |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 1.815ms   | ±0.51%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 122.215ms | ±51.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 2.005ms   | ±1.01%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 3.867ms   | ±75.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.034ms   | ±102.11% |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 5.879ms   | ±0.58%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 6.346ms   | ±0.92%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 6.907ms   | ±1.73%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 11.226ms  | ±1.09%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 16.657ms  | ±0.92%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 494.040μs | ±7.23%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 529.423μs | ±8.00%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 569.971μs | ±2.99%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 895.540μs | ±73.80%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 1.296ms   | ±0.94%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 14.439ms  | ±1.91%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 16.503ms  | ±0.42%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 18.467ms  | ±31.93%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 35.034ms  | ±19.87%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 55.140ms  | ±19.20%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 6.610ms   | ±65.05%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 8.799ms   | ±0.69%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 11.531ms  | ±0.77%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 40.561ms  | ±31.88%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 86.989ms  | ±11.21%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 6.415ms   | ±114.29% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 29.837ms  | ±46.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.989μs   | ±18.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.322μs   | ±13.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.322μs   | ±13.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 84.417ms  | ±11.26%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 258.026μs | ±1.30%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 1.574ms   | ±1.71%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 1.916ms   | ±1.23%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 6.556ms   | ±2.87%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 53.416ms  | ±2.47%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 7.008ms   | ±10.30%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 12.705ms  | ±2.59%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 129.616ms | ±18.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 7.502ms   | ±31.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 7.581ms   | ±107.76% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 9.571ms   | ±75.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 7.395ms   | ±28.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 7.550ms   | ±3.36%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 1.827ms   | ±0.85%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 7.343ms   | ±19.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 8.327ms   | ±62.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 7.278ms   | ±22.48%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.327mb  | 5.651ms   | ±0.81%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.221mb  | 5.863ms   | ±0.55%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.374mb  | 6.565ms   | ±0.39%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.070mb  | 6.491ms   | ±0.37%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.554mb  | 6.075ms   | ±0.20%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.628mb  | 5.777ms   | ±0.69%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.871mb | 10.266ms  | ±0.82%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.771mb  | 1.820ms   | ±1.61%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 22.298μs  | ±1.42%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 129.104μs | ±1.04%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 2.470ms   | ±56.90%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.545ms  | ±108.64% |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 24.716ms  | ±1.55%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 2.166ms   | ±94.63%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 30.709ms  | ±63.34%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 4.788ms   | ±1.43%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 4.563ms   | ±0.81%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 5.157ms   | ±146.15% |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.559μs   | ±14.97%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.473μs   | ±31.43%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.627mb | 9.052ms   | ±0.28%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.627mb | 9.284ms   | ±5.74%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```