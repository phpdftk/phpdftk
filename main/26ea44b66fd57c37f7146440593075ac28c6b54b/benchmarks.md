# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-25 11:41:33 UTC
PHP: 8.4.26
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.838ms | 2.693ms | 2.892ms | 4.921ms | 7.214ms |
| FPDF | 880.329μs | 959.241μs | 1.066ms | 1.637ms | 2.396ms |
| TCPDF | 11.172ms | 12.273ms | 13.256ms | 20.395ms | 29.646ms |
| mPDF | 28.006ms | 31.362ms | 34.681ms | 62.689ms | 97.320ms |
| Dompdf | 11.622ms | 15.733ms | 20.955ms | 68.027ms | 151.411ms |

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
| phpdftk | 3.395ms | 3.624ms | 3.919ms | 5.826ms | 8.342ms |
| FPDF | 1.133ms | 1.215ms | 1.311ms | 1.980ms | 2.822ms |
| TCPDF | 14.700ms | 15.554ms | 18.741ms | 25.023ms | 35.350ms |

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
| Pdf (Level 3) | 3.456ms | 4.512ms | 12.342ms |
| PdfDoc (Level 2) | 2.776ms | 3.241ms | 7.548ms |
| PdfWriter (Level 1) | 2.376ms | 2.820ms | 6.959ms |

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
| Pdf (Level 3) | 4.438ms | 12.051ms | 46.006ms |
| PdfDoc (Level 2) | 3.838ms | 10.019ms | — |

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
| Pdf (Level 3) | 4.150ms | 11.687ms | 45.418ms |
| PdfDoc (Level 2) | 3.392ms | 7.392ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.059ms | 1.666ms | 6.030ms |
| smalot/pdfparser | 1.984ms | 2.366ms | 5.500ms |
| setasign/fpdi | 1.897ms | 2.697ms | 28.416ms |

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
| phpdftk | 2.009ms | 1.338ms |
| smalot/pdfparser | FAIL | 1.917ms |
| setasign/fpdi | 2.873ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.227ms   | ±1.67%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.666ms   | ±1.71%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 6.030ms   | ±1.27%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.009ms   | ±0.59%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.338ms   | ±2.03%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.984ms   | ±0.64%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.366ms   | ±1.05%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.500ms   | ±0.21%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 551.249μs | ±1.15%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.917ms   | ±0.78%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.897ms   | ±0.46%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.697ms   | ±0.44%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.416ms  | ±0.83%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.873ms   | ±0.62%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.486ms   | ±0.90%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.072ms   | ±1.12%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.439ms   | ±0.59%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.812ms   | ±0.93%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.724μs   | ±18.67% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.059ms   | ±1.12%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.073μs   | ±23.57% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.121μs   | ±35.36% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.717mb | 18.724ms  | ±0.22%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.717mb | 18.638ms  | ±0.29%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.966mb  | 14.135ms  | ±0.81%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.773mb  | 12.768ms  | ±0.26%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 10.104mb | 20.068ms  | ±0.07%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 10.110mb | 17.863ms  | ±4.56%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 11.287mb | 50.000ms  | ±0.64%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 9.540mb  | 13.894ms  | ±0.47%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.751mb  | 16.421ms  | ±0.17%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 12.813mb | 62.612ms  | ±0.30%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 7.913mb  | 6.557ms   | ±0.41%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.161μs  | ±1.23%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 238.336μs | ±0.81%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.374mb  | 3.395ms   | ±0.90%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.624ms   | ±1.28%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.919ms   | ±2.27%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.826ms   | ±1.16%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.571mb  | 8.342ms   | ±0.82%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.700ms  | ±1.05%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.554ms  | ±0.44%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 18.741ms  | ±27.42% |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 25.023ms  | ±0.16%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 35.350ms  | ±0.66%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.133ms   | ±4.35%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.215ms   | ±4.43%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.311ms   | ±3.71%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.980ms   | ±3.25%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.822ms   | ±1.69%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.543mb | 57.483ms  | ±0.61%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 18.154mb | 116.758ms | ±0.57%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 65.171mb | 1.370s    | ±0.14%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.399mb | 24.387ms  | ±2.28%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.425mb | 51.750ms  | ±0.88%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 58.236mb | 480.017ms | ±1.42%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.691mb | 62.958ms  | ±9.77%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.410mb | 81.747ms  | ±1.56%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 34.113mb | 652.364ms | ±0.10%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.448mb | 17.904ms  | ±1.14%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.448mb | 41.020ms  | ±0.16%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 31.090mb | 276.058ms | ±0.16%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 8.878ms   | ±4.08%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.505ms   | ±0.59%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.472ms   | ±0.38%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.834mb  | 25.034ms  | ±0.38%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.313mb | 225.543ms | ±0.90%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 50.017mb | 1.102s    | ±1.15%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.310mb | 174.254ms | ±0.89%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 27.144mb | 930.590ms | ±0.34%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 24.225mb | 758.299ms | ±0.43%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.438ms   | ±3.69%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.051ms  | ±0.96%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 46.006ms  | ±0.60%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.838ms   | ±0.97%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.030mb  | 10.019ms  | ±0.72%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.150ms   | ±0.29%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.687ms  | ±0.47%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.418ms  | ±0.79%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.392ms   | ±1.21%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.392ms   | ±0.72%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.376ms   | ±3.94%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.820ms   | ±1.70%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.124mb  | 6.959ms   | ±0.70%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.776ms   | ±0.89%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.241ms   | ±1.16%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.548ms   | ±0.94%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.456ms   | ±0.98%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.512ms   | ±0.61%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.342ms  | ±0.47%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.863mb | 86.026ms  | ±0.38%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 27.168mb | 363.202ms | ±0.51%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 64.380mb | 1.424s    | ±0.46%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 29.195mb | 254.818ms | ±1.53%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.945mb | 204.171ms | ±0.46%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.796mb | 159.553ms | ±0.88%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 21.045mb | 215.523ms | ±0.37%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 20.326mb | 176.523ms | ±0.68%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.706mb | 337.073ms | ±0.64%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.315mb | 52.885ms  | ±0.51%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.227mb | 46.896ms  | ±0.38%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 17.154mb | 44.616ms  | ±0.95%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.605mb | 141.363ms | ±0.64%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 17.216mb | 49.045ms  | ±0.07%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.443mb | 61.208ms  | ±0.51%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.908mb | 90.352ms  | ±1.10%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 17.110mb | 39.911ms  | ±0.16%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 17.111mb | 48.424ms  | ±0.25%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 17.138mb | 50.416ms  | ±0.36%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 17.107mb | 49.643ms  | ±0.76%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 17.129mb | 47.438ms  | ±0.68%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 32.079mb | 74.184ms  | ±1.09%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 19.121mb | 47.109ms  | ±0.32%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.800mb | 41.604ms  | ±0.32%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 17.075mb | 45.443ms  | ±0.73%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 17.096mb | 50.337ms  | ±0.35%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 17.089mb | 50.646ms  | ±0.76%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.282mb | 47.142ms  | ±0.39%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.559mb | 228.491ms | ±0.53%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.684mb | 170.422ms | ±1.36%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.887mb  | 2.458ms   | ±1.87%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.693ms   | ±2.10%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.892ms   | ±1.31%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.921ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.214ms   | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.351mb  | 3.719ms   | ±1.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.927ms   | ±5.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 13.122ms  | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.769ms   | ±0.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.483ms   | ±1.30%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 685.736μs | ±4.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.105mb  | 3.299ms   | ±1.32%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.199mb  | 3.741ms   | ±1.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.356ms   | ±1.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 294.293ms | ±25.51% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.740ms   | ±1.16%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.270ms   | ±26.64% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.337ms   | ±1.83%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 11.172ms  | ±1.63%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 12.273ms  | ±0.61%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 13.256ms  | ±1.03%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.395ms  | ±1.30%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 29.646ms  | ±0.26%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 880.329μs | ±3.07%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 959.241μs | ±1.64%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 1.066ms   | ±2.35%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.637ms   | ±1.56%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.396ms   | ±1.76%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 28.006ms  | ±2.45%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 31.362ms  | ±2.84%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 34.681ms  | ±1.28%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 62.689ms  | ±0.53%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 97.320ms  | ±0.56%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.622ms  | ±0.61%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.733ms  | ±0.93%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 20.955ms  | ±1.55%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 68.027ms  | ±0.64%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 151.411ms | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.086ms   | ±1.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 53.847ms  | ±1.16%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.678μs   | ±9.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.678μs   | ±9.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.678μs   | ±9.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 267.991ms | ±21.98% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 496.210μs | ±3.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.012ms   | ±0.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.489ms   | ±1.28%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.753ms  | ±5.54%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 82.298ms  | ±1.02%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 15.260ms  | ±1.39%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.078ms  | ±1.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 229.172ms | ±34.13% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.848ms  | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.884ms  | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.270mb  | 14.159ms  | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 14.282ms  | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.317ms  | ±1.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.228ms   | ±2.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.273mb  | 14.092ms  | ±0.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.324mb  | 14.468ms  | ±1.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.838ms  | ±0.32%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```