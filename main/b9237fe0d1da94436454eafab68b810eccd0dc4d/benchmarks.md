# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-20 16:13:27 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.491ms | 2.608ms | 2.782ms | 4.846ms | 7.173ms |
| FPDF | 769.292μs | 861.834μs | 964.256μs | 1.541ms | 2.302ms |
| TCPDF | 10.196ms | 11.106ms | 12.124ms | 20.912ms | 31.621ms |
| mPDF | 26.003ms | 30.219ms | 34.235ms | 67.283ms | 106.257ms |
| Dompdf | 11.403ms | 16.180ms | 22.288ms | 73.932ms | 163.565ms |

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
| phpdftk | 3.418ms | 3.586ms | 3.912ms | 5.851ms | 8.349ms |
| FPDF | 1.088ms | 1.137ms | 1.238ms | 1.938ms | 2.790ms |
| TCPDF | 15.797ms | 16.183ms | 16.962ms | 26.723ms | 39.726ms |

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
| Pdf (Level 3) | 3.397ms | 4.518ms | 12.716ms |
| PdfDoc (Level 2) | 2.727ms | 3.253ms | 7.525ms |
| PdfWriter (Level 1) | 2.402ms | 2.835ms | 6.961ms |

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
| Pdf (Level 3) | 4.488ms | 12.385ms | 47.335ms |
| PdfDoc (Level 2) | 3.876ms | 10.053ms | — |

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
| Pdf (Level 3) | 4.104ms | 11.749ms | 45.341ms |
| PdfDoc (Level 2) | 3.339ms | 7.378ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.313ms | 1.712ms | 5.948ms |
| smalot/pdfparser | 2.035ms | 2.445ms | 5.844ms |
| setasign/fpdi | 1.997ms | 2.880ms | 30.104ms |

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
| phpdftk | 2.060ms | 1.399ms |
| smalot/pdfparser | FAIL | 1.940ms |
| setasign/fpdi | 3.016ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.418ms   | ±0.95%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.586ms   | ±0.97%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.912ms   | ±0.95%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.851ms   | ±0.72%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 8.349ms   | ±2.59%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.797ms  | ±4.90%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.183ms  | ±9.39%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.962ms  | ±0.45%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.723ms  | ±0.04%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 39.726ms  | ±1.34%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.088ms   | ±3.39%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.137ms   | ±1.39%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.238ms   | ±0.23%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.938ms   | ±1.07%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.790ms   | ±1.05%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.402ms   | ±2.07%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.835ms   | ±0.89%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.961ms   | ±1.63%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.727ms   | ±0.57%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.253ms   | ±0.83%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.525ms   | ±0.71%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.397ms   | ±1.71%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.518ms   | ±1.13%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.716ms  | ±0.73%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.635mb | 90.345ms  | ±0.66%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 26.869mb | 392.019ms | ±0.49%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 63.909mb | 1.540s    | ±0.17%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.849mb | 270.118ms | ±1.10%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.614mb | 207.997ms | ±1.01%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.534mb | 167.125ms | ±1.10%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 20.754mb | 229.791ms | ±0.39%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 19.987mb | 191.842ms | ±0.62%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.185mb | 357.381ms | ±0.76%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.026mb | 55.032ms  | ±0.67%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.003mb | 48.105ms  | ±3.47%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.930mb | 44.045ms  | ±0.93%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.338mb | 146.124ms | ±0.47%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.927mb | 49.995ms  | ±0.65%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.219mb | 62.864ms  | ±0.91%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.682mb | 94.707ms  | ±0.22%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.887mb | 40.257ms  | ±0.54%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.822mb | 48.244ms  | ±1.32%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.850mb | 50.368ms  | ±0.47%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.818mb | 49.277ms  | ±0.25%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.840mb | 47.672ms  | ±0.42%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.762mb | 74.747ms  | ±0.72%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.871mb | 46.521ms  | ±1.59%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.484mb | 41.144ms  | ±0.42%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.787mb | 45.571ms  | ±1.56%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.808mb | 49.988ms  | ±0.15%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.801mb | 49.681ms  | ±0.91%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.032mb | 44.745ms  | ±3.03%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.203mb | 239.836ms | ±0.05%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.458mb | 177.223ms | ±1.53%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.318mb | 59.623ms  | ±1.07%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.924mb | 122.799ms | ±0.23%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 64.682mb | 1.474s    | ±2.10%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.209mb | 27.037ms  | ±3.81%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.235mb | 60.409ms  | ±0.61%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.981mb | 553.245ms | ±0.82%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.493mb | 65.852ms  | ±10.23% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.146mb | 89.576ms  | ±1.33%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.915mb | 752.191ms | ±0.97%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.257mb | 19.121ms  | ±0.69%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.257mb | 44.132ms  | ±0.64%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.900mb | 328.461ms | ±0.42%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.272ms   | ±0.80%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.712ms   | ±0.55%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.948ms   | ±0.63%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.060ms   | ±1.24%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.399ms   | ±2.31%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.035ms   | ±1.47%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.445ms   | ±1.52%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.844ms   | ±1.84%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 553.224μs | ±2.41%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.940ms   | ±10.46% |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.997ms   | ±1.14%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.880ms   | ±1.63%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.104ms  | ±0.67%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.016ms   | ±1.13%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.501ms   | ±1.17%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.290ms   | ±0.43%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.527ms   | ±2.23%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.873ms   | ±1.75%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.597μs   | ±17.80% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.313ms   | ±0.99%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.764mb  | 28.246ms  | ±0.37%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.201mb | 254.638ms | ±1.03%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.726mb | 1.252s    | ±0.21%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.209mb | 197.466ms | ±0.87%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 27.044mb | 1.007s    | ±0.25%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 24.131mb | 820.192ms | ±0.40%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.104ms   | ±1.32%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.749ms  | ±1.31%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.341ms  | ±1.34%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.339ms   | ±1.80%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.378ms   | ±0.40%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.339ms   | ±0.79%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.608ms   | ±1.86%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.782ms   | ±0.13%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.846ms   | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.173ms   | ±4.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.692ms   | ±1.24%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.875ms   | ±1.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.343ms  | ±13.02% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.658ms   | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.425ms   | ±1.18%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 651.119μs | ±5.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 3.248ms   | ±2.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.680ms   | ±0.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.240ms   | ±3.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 244.831ms | ±22.44% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.630ms   | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.894ms   | ±16.88% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.065ms   | ±4.32%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.196ms  | ±0.71%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.106ms  | ±4.80%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.124ms  | ±6.68%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.912ms  | ±0.84%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.621ms  | ±1.37%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 769.292μs | ±2.62%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 861.834μs | ±2.43%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 964.256μs | ±5.92%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.541ms   | ±1.76%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.302ms   | ±0.84%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 26.003ms  | ±2.78%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 30.219ms  | ±4.70%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 34.235ms  | ±2.01%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 67.283ms  | ±0.46%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 106.257ms | ±1.71%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.403ms  | ±2.36%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.180ms  | ±0.66%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 22.288ms  | ±1.69%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 73.932ms  | ±0.97%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 163.565ms | ±0.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.148ms   | ±2.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.046ms  | ±1.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 207.341ms | ±28.42% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 458.386μs | ±1.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.014ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.504ms   | ±5.81%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 14.403ms  | ±2.67%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 85.845ms  | ±0.84%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.064ms  | ±0.29%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.011ms  | ±2.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 221.571ms | ±23.56% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.565ms  | ±1.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.450ms  | ±2.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 13.677ms  | ±1.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.965ms  | ±1.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.158ms  | ±0.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.234ms   | ±1.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.772ms  | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 13.737ms  | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.491ms  | ±0.85%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.697mb  | 14.378ms  | ±1.18%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.498mb  | 12.721ms  | ±0.28%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.818mb  | 20.598ms  | ±0.45%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.733mb  | 18.799ms  | ±1.41%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.888mb  | 24.990ms  | ±0.40%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.924mb  | 13.456ms  | ±1.38%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.124mb  | 16.934ms  | ±0.85%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 11.764mb | 37.993ms  | ±0.56%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.846mb  | 3.279ms   | ±0.89%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.718μs  | ±5.03%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 248.210μs | ±1.18%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.488ms   | ±0.81%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.385ms  | ±1.67%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.335ms  | ±0.77%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.876ms   | ±0.90%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 10.053ms  | ±1.47%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 9.039ms   | ±19.28% |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.619ms   | ±0.77%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.656ms   | ±0.62%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.917μs   | ±23.39% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.121μs   | ±35.36% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.396mb | 18.796ms  | ±3.69%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.396mb | 18.125ms  | ±0.26%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```