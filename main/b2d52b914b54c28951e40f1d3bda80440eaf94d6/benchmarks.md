# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-25 13:44:49 UTC
PHP: 8.4.26
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 11.642ms | 2.607ms | 2.769ms | 4.745ms | 7.007ms |
| FPDF | 1.041ms | 1.104ms | 1.188ms | 1.826ms | 2.640ms |
| TCPDF | 10.758ms | 11.615ms | 12.475ms | 20.120ms | 29.428ms |
| mPDF | 26.241ms | 28.704ms | 32.405ms | 56.984ms | 88.029ms |
| Dompdf | 11.533ms | 15.644ms | 20.014ms | 61.715ms | 135.588ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.218mb | 5.947mb | 6.033mb | 6.667mb | 7.490mb |
| FPDF | 5.072mb | 5.072mb | 5.072mb | 5.072mb | 5.084mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.683mb | 17.721mb | 18.014mb | 18.376mb |
| Dompdf | 9.381mb | 9.600mb | 9.921mb | 12.614mb | 15.977mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.378ms | 3.653ms | 3.796ms | 6.409ms | 8.187ms |
| FPDF | 1.312ms | 1.434ms | 1.950ms | 2.237ms | 3.175ms |
| TCPDF | 16.092ms | 16.368ms | 17.541ms | 26.175ms | 36.891ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.374mb | 5.420mb | 5.479mb | 5.972mb | 6.571mb |
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
| Pdf (Level 3) | 3.346ms | 4.598ms | 11.449ms |
| PdfDoc (Level 2) | 2.778ms | 3.207ms | 7.357ms |
| PdfWriter (Level 1) | 2.443ms | 2.857ms | 6.801ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 6.057mb | 6.220mb | 7.897mb |
| PdfDoc (Level 2) | 5.714mb | 5.872mb | 7.441mb |
| PdfWriter (Level 1) | 5.389mb | 5.548mb | 7.124mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 4.403ms | 11.250ms | 41.255ms |
| PdfDoc (Level 2) | 3.793ms | 9.295ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.408mb | 9.203mb | 21.611mb |
| PdfDoc (Level 2) | 6.214mb | 9.030mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 3.987ms | 10.434ms | 37.137ms |
| PdfDoc (Level 2) | 3.221ms | 6.818ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.960ms | 1.416ms | 4.735ms |
| smalot/pdfparser | 1.952ms | 2.274ms | 5.342ms |
| setasign/fpdi | 1.765ms | 2.490ms | 23.094ms |

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
| phpdftk | 1.712ms | 1.206ms |
| smalot/pdfparser | FAIL | 1.816ms |
| setasign/fpdi | 2.589ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.095ms   | ±1.66%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.416ms   | ±9.06%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 4.735ms   | ±1.00%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.712ms   | ±0.99%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.206ms   | ±1.78%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.952ms   | ±2.23%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.274ms   | ±0.68%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.342ms   | ±1.11%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 563.065μs | ±1.87%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.816ms   | ±0.32%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.765ms   | ±1.82%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.490ms   | ±1.54%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 23.094ms  | ±0.83%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.589ms   | ±0.50%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.432ms   | ±1.41%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 6.222ms   | ±1.21%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 4.822ms   | ±0.96%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.512ms   | ±1.51%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 2.933μs   | ±29.35% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.960ms   | ±1.10%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.249μs   | ±25.71% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.282μs   | ±66.27% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.717mb | 17.840ms  | ±0.69%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.717mb | 17.817ms  | ±0.19%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.966mb  | 13.595ms  | ±0.43%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.773mb  | 12.615ms  | ±0.66%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 10.104mb | 18.797ms  | ±1.06%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 10.110mb | 17.441ms  | ±0.15%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 11.287mb | 45.010ms  | ±0.24%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 9.540mb  | 13.611ms  | ±1.09%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.751mb  | 16.086ms  | ±1.43%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 12.813mb | 57.131ms  | ±0.28%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 7.913mb  | 6.832ms   | ±0.49%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 34.462μs  | ±1.69%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 214.412μs | ±1.55%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.374mb  | 3.378ms   | ±4.75%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.653ms   | ±4.18%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.796ms   | ±2.57%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 6.409ms   | ±54.05% |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.571mb  | 8.187ms   | ±5.69%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 16.092ms  | ±13.46% |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 16.368ms  | ±0.25%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.541ms  | ±1.73%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.175ms  | ±1.01%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 36.891ms  | ±1.05%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.312ms   | ±8.16%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.434ms   | ±9.88%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.950ms   | ±95.90% |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 2.237ms   | ±29.50% |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 3.175ms   | ±21.95% |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.543mb | 49.882ms  | ±0.41%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 18.154mb | 100.724ms | ±10.21% |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 65.171mb | 1.140s    | ±0.52%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.422mb | 29.070ms  | ±14.02% |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.448mb | 67.350ms  | ±25.97% |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 58.259mb | 458.958ms | ±0.78%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.691mb | 59.728ms  | ±9.67%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.410mb | 76.871ms  | ±1.60%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 34.113mb | 640.923ms | ±0.16%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.448mb | 18.833ms  | ±0.86%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.448mb | 39.400ms  | ±0.31%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 31.090mb | 275.464ms | ±0.35%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.126ms   | ±1.24%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 7.783ms   | ±7.47%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 7.905ms   | ±2.15%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.834mb  | 21.059ms  | ±0.34%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.313mb | 186.911ms | ±0.31%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 50.017mb | 919.155ms | ±0.31%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.310mb | 147.491ms | ±0.11%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 27.144mb | 774.803ms | ±0.41%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 24.225mb | 627.402ms | ±0.32%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.403ms   | ±1.31%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 11.250ms  | ±1.40%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 41.255ms  | ±0.34%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.793ms   | ±27.25% |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.030mb  | 9.295ms   | ±0.91%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 3.987ms   | ±1.60%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 10.434ms  | ±1.42%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 37.137ms  | ±0.49%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.221ms   | ±2.39%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 6.818ms   | ±2.14%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.443ms   | ±1.90%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.857ms   | ±3.28%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.124mb  | 6.801ms   | ±1.52%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.778ms   | ±1.18%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.207ms   | ±3.07%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.357ms   | ±6.57%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.346ms   | ±45.61% |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.598ms   | ±37.97% |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 11.449ms  | ±10.39% |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.863mb | 71.512ms  | ±0.47%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 27.168mb | 303.742ms | ±0.15%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 64.380mb | 1.191s    | ±0.21%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 29.195mb | 208.802ms | ±0.44%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.945mb | 157.225ms | ±0.15%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.796mb | 130.466ms | ±0.13%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 21.045mb | 177.896ms | ±0.16%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 20.326mb | 148.197ms | ±0.19%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.706mb | 275.949ms | ±0.28%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.315mb | 44.327ms  | ±0.23%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.227mb | 38.531ms  | ±0.35%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 17.154mb | 36.674ms  | ±0.29%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.605mb | 116.457ms | ±0.20%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 17.216mb | 40.418ms  | ±0.43%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.443mb | 50.516ms  | ±0.09%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.908mb | 73.818ms  | ±0.29%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 17.110mb | 33.604ms  | ±0.19%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 17.111mb | 39.644ms  | ±0.95%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 17.138mb | 41.314ms  | ±0.56%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 17.107mb | 40.197ms  | ±0.06%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 17.129mb | 39.051ms  | ±0.23%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 32.079mb | 62.635ms  | ±0.75%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 19.121mb | 39.057ms  | ±0.77%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.800mb | 34.560ms  | ±0.21%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 17.075mb | 37.273ms  | ±0.12%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 17.096mb | 41.095ms  | ±0.55%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 17.089mb | 41.409ms  | ±0.67%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.282mb | 37.724ms  | ±0.62%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.559mb | 187.018ms | ±0.18%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.684mb | 137.692ms | ±0.04%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.887mb  | 2.331ms   | ±85.94% |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.607ms   | ±3.45%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.769ms   | ±15.72% |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.745ms   | ±1.90%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.007ms   | ±7.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.351mb  | 3.658ms   | ±22.96% |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.878ms   | ±1.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 10.750ms  | ±19.80% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.685ms   | ±5.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.449ms   | ±2.28%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 823.062μs | ±9.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.105mb  | 3.392ms   | ±2.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.199mb  | 3.735ms   | ±2.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.331ms   | ±8.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 180.199ms | ±31.21% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.689ms   | ±2.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.278ms   | ±20.80% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.248ms   | ±4.14%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.758ms  | ±33.24% |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.615ms  | ±1.13%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.475ms  | ±0.67%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.120ms  | ±0.88%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 29.428ms  | ±1.49%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 1.041ms   | ±9.48%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 1.104ms   | ±7.77%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 1.188ms   | ±4.46%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.826ms   | ±75.61% |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.640ms   | ±10.93% |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 26.241ms  | ±2.10%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 28.704ms  | ±1.04%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.405ms  | ±0.96%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 56.984ms  | ±0.61%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 88.029ms  | ±0.52%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.381mb  | 11.533ms  | ±1.07%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.600mb  | 15.644ms  | ±1.03%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.921mb  | 20.014ms  | ±2.93%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.614mb | 61.715ms  | ±0.42%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.977mb | 135.588ms | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.936ms   | ±1.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 40.014ms  | ±0.32%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.333μs   | ±10.53% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.334μs   | ±9.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.463μs   | ±17.82% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 167.945ms | ±15.76% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 398.473μs | ±1.20%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.692ms   | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.513ms   | ±47.97% |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 15.887ms  | ±20.50% |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 66.172ms  | ±0.34%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.469ms  | ±0.41%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.024ms  | ±0.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 176.105ms | ±15.66% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 11.639ms  | ±2.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 11.565ms  | ±0.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.270mb  | 11.764ms  | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 11.899ms  | ±3.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 12.119ms  | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.312ms   | ±3.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.273mb  | 11.748ms  | ±1.14%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.324mb  | 11.832ms  | ±1.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 11.642ms  | ±0.61%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```