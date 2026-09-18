# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-18 14:12:34 UTC
PHP: 8.4.25
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 12.015ms | 2.267ms | 2.485ms | 4.740ms | 6.504ms |
| FPDF | 734.233μs | 811.785μs | 878.815μs | 1.468ms | 2.212ms |
| TCPDF | 10.743ms | 10.974ms | 11.989ms | 19.541ms | 29.333ms |
| mPDF | 24.615ms | 27.864ms | 31.075ms | 57.058ms | 92.505ms |
| Dompdf | 10.706ms | 14.734ms | 19.495ms | 63.277ms | 140.201ms |

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
| phpdftk | 3.030ms | 3.225ms | 3.429ms | 5.246ms | 7.592ms |
| FPDF | 1.062ms | 1.142ms | 1.175ms | 1.865ms | 2.692ms |
| TCPDF | 14.983ms | 17.238ms | 17.000ms | 25.751ms | 36.791ms |

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
| Pdf (Level 3) | 3.127ms | 4.240ms | 11.334ms |
| PdfDoc (Level 2) | 2.461ms | 2.871ms | 6.942ms |
| PdfWriter (Level 1) | 2.076ms | 2.461ms | 6.225ms |

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
| Pdf (Level 3) | 4.076ms | 11.016ms | 42.158ms |
| PdfDoc (Level 2) | 3.493ms | 9.063ms | — |

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
| Pdf (Level 3) | 3.717ms | 10.380ms | 39.086ms |
| PdfDoc (Level 2) | 2.990ms | 7.141ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.140ms | 1.419ms | 5.130ms |
| smalot/pdfparser | 1.894ms | 2.177ms | 5.244ms |
| setasign/fpdi | 1.724ms | 2.435ms | 25.063ms |

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
| phpdftk | 1.750ms | 1.189ms |
| smalot/pdfparser | FAIL | 1.728ms |
| setasign/fpdi | 2.586ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.373mb  | 3.030ms   | ±1.94%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.225ms   | ±1.26%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.429ms   | ±1.12%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.246ms   | ±4.67%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.570mb  | 7.592ms   | ±0.30%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.983ms  | ±0.41%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 17.238ms  | ±3.19%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.000ms  | ±2.89%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 25.751ms  | ±1.24%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 36.791ms  | ±1.49%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.062ms   | ±2.10%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.142ms   | ±5.60%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.175ms   | ±0.61%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.865ms   | ±0.08%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.692ms   | ±1.68%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.076ms   | ±1.12%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.461ms   | ±1.03%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.123mb  | 6.225ms   | ±4.31%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.461ms   | ±1.83%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.871ms   | ±3.75%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 6.942ms   | ±3.88%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.127ms   | ±0.91%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.240ms   | ±6.80%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 11.334ms  | ±4.51%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 16.895mb | 71.608ms  | ±2.56%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 22.025mb | 303.197ms | ±0.10%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 46.754mb | 1.193s    | ±0.32%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 28.081mb | 212.251ms | ±0.18%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 32.881mb | 165.322ms | ±2.47%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 17.714mb | 130.776ms | ±1.80%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 18.838mb | 179.247ms | ±3.25%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 18.333mb | 147.161ms | ±0.05%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 21.035mb | 277.568ms | ±1.54%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 16.347mb | 44.153ms  | ±3.12%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 16.259mb | 38.332ms  | ±0.11%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 16.189mb | 35.989ms  | ±0.65%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 17.586mb | 116.632ms | ±0.28%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 16.248mb | 40.128ms  | ±1.15%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 16.474mb | 50.455ms  | ±0.46%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 16.958mb | 73.685ms  | ±2.75%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 16.146mb | 32.202ms  | ±0.42%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 16.149mb | 38.816ms  | ±0.67%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 16.176mb | 41.853ms  | ±0.73%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 16.145mb | 39.365ms  | ±1.14%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 16.167mb | 38.517ms  | ±0.29%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 31.076mb | 63.599ms  | ±4.01%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 18.119mb | 39.429ms  | ±1.48%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 16.798mb | 34.236ms  | ±3.60%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 16.114mb | 36.646ms  | ±0.91%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 16.134mb | 40.249ms  | ±0.05%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 16.128mb | 40.021ms  | ±0.37%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 19.280mb | 37.582ms  | ±2.59%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 19.793mb | 187.885ms | ±0.17%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 16.733mb | 139.329ms | ±0.41%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 16.563mb | 47.909ms  | ±0.22%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 17.181mb | 95.224ms  | ±0.43%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 44.865mb | 1.138s    | ±0.15%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 15.616mb | 23.712ms  | ±4.34%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 17.577mb | 50.514ms  | ±0.76%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 57.388mb | 454.417ms | ±1.73%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 29.900mb | 58.421ms  | ±9.09%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 24.554mb | 75.826ms  | ±4.32%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 33.323mb | 639.700ms | ±1.03%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 17.599mb | 17.782ms  | ±3.23%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 17.599mb | 38.782ms  | ±1.17%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 30.242mb | 289.389ms | ±0.40%  |
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.039ms   | ±1.35%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.419ms   | ±1.24%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.130ms   | ±1.62%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.750ms   | ±3.39%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.189ms   | ±11.05% |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.894ms   | ±4.03%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.177ms   | ±6.81%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.244ms   | ±0.96%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 515.318μs | ±7.25%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.728ms   | ±31.32% |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.724ms   | ±0.88%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.435ms   | ±0.58%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 25.063ms  | ±1.95%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.586ms   | ±2.85%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.358ms   | ±1.11%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 6.663ms   | ±3.10%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 4.742ms   | ±0.76%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.396ms   | ±1.98%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.397μs   | ±25.42% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 5.140ms   | ±0.39%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.635mb  | 21.757ms  | ±0.06%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.033mb | 194.231ms | ±0.75%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 49.378mb | 968.694ms | ±0.35%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.059mb | 152.969ms | ±0.84%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.717ms   | ±4.09%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 10.380ms  | ±2.81%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 39.086ms  | ±3.73%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 2.990ms   | ±0.74%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.141ms   | ±4.11%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.886mb  | 2.124ms   | ±4.02%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.267ms   | ±3.90%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.485ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.740ms   | ±3.86%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 6.504ms   | ±2.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.350mb  | 3.351ms   | ±0.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.506ms   | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 10.819ms  | ±15.10% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.258ms   | ±0.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.053ms   | ±0.26%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 547.487μs | ±2.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.104mb  | 2.960ms   | ±1.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.198mb  | 3.365ms   | ±2.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.048ms   | ±2.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 264.440ms | ±26.41% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.384ms   | ±7.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.117ms   | ±20.28% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 5.302ms   | ±0.84%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.743ms  | ±4.00%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.974ms  | ±4.39%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.989ms  | ±3.01%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.541ms  | ±0.74%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 29.333ms  | ±2.68%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 734.233μs | ±3.64%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 811.785μs | ±1.70%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 878.815μs | ±7.75%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.468ms   | ±3.58%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.212ms   | ±0.52%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 24.615ms  | ±1.13%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 27.864ms  | ±1.71%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 31.075ms  | ±1.09%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 57.058ms  | ±0.90%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 92.505ms  | ±1.35%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 10.706ms  | ±0.66%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 14.734ms  | ±4.75%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 19.495ms  | ±4.47%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 63.277ms  | ±1.46%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 140.201ms | ±1.06%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.551ms   | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 42.028ms  | ±2.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.290μs   | ±23.53% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.305μs   | ±28.33% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.011μs   | ±14.41% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 230.209ms | ±40.39% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 400.806μs | ±1.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.715ms   | ±7.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.256ms   | ±5.52%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 8.047ms   | ±6.68%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 70.980ms  | ±0.55%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.824ms  | ±3.09%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.590ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 178.982ms | ±32.85% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 11.758ms  | ±2.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 11.647ms  | ±4.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.269mb  | 12.102ms  | ±5.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 12.247ms  | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 12.209ms  | ±4.15%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.083ms   | ±1.34%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.272mb  | 13.090ms  | ±6.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.323mb  | 11.943ms  | ±0.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 12.015ms  | ±3.84%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.351mb  | 9.644ms   | ±0.57%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.245mb  | 9.616ms   | ±0.70%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 9.397mb  | 11.057ms  | ±0.48%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 9.095mb  | 11.409ms  | ±0.66%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.577mb  | 10.251ms  | ±0.88%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.651mb  | 9.784ms   | ±0.59%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.896mb | 17.989ms  | ±0.47%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.791mb  | 2.873ms   | ±0.66%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 36.687μs  | ±1.54%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 218.549μs | ±2.59%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.076ms   | ±0.70%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.016ms  | ±0.43%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 42.158ms  | ±3.40%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.493ms   | ±0.55%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.029mb  | 9.063ms   | ±0.49%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 7.904ms   | ±10.04% |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 7.605ms   | ±1.29%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 7.677ms   | ±0.89%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.387μs   | ±62.34% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.333μs   | ±48.01% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 15.758mb | 15.838ms  | ±1.14%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 15.758mb | 15.525ms  | ±0.37%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```