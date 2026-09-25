# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-25 21:45:39 UTC
PHP: 8.4.26
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.301ms | 1.857ms | 1.968ms | 6.624ms | 7.984ms |
| FPDF | 1.235ms | 1.974ms | 1.043ms | 1.297ms | 9.297ms |
| TCPDF | 7.751ms | 10.078ms | 9.072ms | 14.560ms | 20.803ms |
| mPDF | 20.303ms | 21.714ms | 22.765ms | 40.403ms | 62.812ms |
| Dompdf | 8.050ms | 11.012ms | 16.102ms | 43.846ms | 97.762ms |

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
| phpdftk | 2.554ms | 4.063ms | 5.245ms | 5.792ms | 9.556ms |
| FPDF | 25.434ms | 1.090ms | 1.088ms | 1.613ms | 2.152ms |
| TCPDF | 11.366ms | 12.619ms | 17.791ms | 22.939ms | 26.129ms |

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
| Pdf (Level 3) | 2.664ms | 3.179ms | 7.998ms |
| PdfDoc (Level 2) | 3.072ms | 2.853ms | 52.210ms |
| PdfWriter (Level 1) | 2.040ms | 2.431ms | 4.902ms |

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
| Pdf (Level 3) | 3.203ms | 8.171ms | 32.323ms |
| PdfDoc (Level 2) | 3.146ms | 7.642ms | — |

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
| Pdf (Level 3) | 5.658ms | 7.423ms | 27.774ms |
| PdfDoc (Level 2) | 7.925ms | 5.709ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 3.432ms | 1.074ms | 3.489ms |
| smalot/pdfparser | 1.409ms | 1.630ms | 3.979ms |
| setasign/fpdi | 1.235ms | 1.708ms | 16.218ms |

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
| phpdftk | 1.228ms | 863.549μs |
| smalot/pdfparser | FAIL | 1.459ms |
| setasign/fpdi | 1.823ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 785.749μs | ±0.80%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.074ms   | ±2.85%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 3.489ms   | ±0.91%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.228ms   | ±0.93%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 863.549μs | ±2.14%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.409ms   | ±1.85%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 1.630ms   | ±1.10%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 3.979ms   | ±1.42%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 406.014μs | ±2.42%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.459ms   | ±5.71%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.235ms   | ±0.73%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 1.708ms   | ±1.40%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 16.218ms  | ±0.47%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 1.823ms   | ±0.62%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.005ms   | ±0.71%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 4.362ms   | ±0.65%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 3.348ms   | ±0.52%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 2.474ms   | ±0.48%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 1.685μs   | ±31.93%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 3.432ms   | ±0.77%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 0.766μs   | ±43.20%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 0.976μs   | ±41.44%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.857mb | 12.598ms  | ±0.46%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.857mb | 12.520ms  | ±0.49%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 9.381mb  | 10.264ms  | ±1.08%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.985mb  | 9.319ms   | ±1.31%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 10.198mb | 13.933ms  | ±0.56%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 10.201mb | 13.005ms  | ±0.77%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 11.772mb | 36.043ms  | ±0.15%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 9.633mb  | 9.749ms   | ±1.66%   |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.847mb  | 12.001ms  | ±1.01%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 12.992mb | 51.862ms  | ±5.79%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 8.045mb  | 5.661ms   | ±0.98%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 26.880μs  | ±1.71%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 168.985μs | ±2.18%   |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.374mb  | 2.554ms   | ±4.89%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 4.063ms   | ±104.91% |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 5.245ms   | ±118.41% |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.792ms   | ±99.73%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.571mb  | 9.556ms   | ±108.78% |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 11.366ms  | ±1.03%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 12.619ms  | ±29.69%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 17.791ms  | ±59.47%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 22.939ms  | ±80.15%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 26.129ms  | ±0.82%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 25.434ms  | ±77.51%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.090ms   | ±14.65%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.088ms   | ±9.82%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.613ms   | ±8.82%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.152ms   | ±6.13%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.694mb | 34.477ms  | ±0.78%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 18.302mb | 69.061ms  | ±0.32%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 65.481mb | 827.943ms | ±1.41%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.521mb | 18.944ms  | ±61.44%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.547mb | 36.988ms  | ±26.87%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 58.358mb | 314.959ms | ±4.40%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.790mb | 42.728ms  | ±9.11%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.510mb | 59.090ms  | ±19.44%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 34.278mb | 475.730ms | ±2.90%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.547mb | 13.048ms  | ±0.51%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.547mb | 27.536ms  | ±0.25%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 31.189mb | 193.756ms | ±0.73%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 6.419ms   | ±1.37%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 5.685ms   | ±42.53%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 5.714ms   | ±65.94%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.937mb  | 14.810ms  | ±0.21%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.461mb | 131.563ms | ±0.50%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 50.370mb | 654.247ms | ±0.99%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.433mb | 103.634ms | ±0.15%   |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 28.602mb | 604.975ms | ±1.09%   |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 25.406mb | 487.902ms | ±1.23%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 3.203ms   | ±39.28%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 8.171ms   | ±3.11%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 32.323ms  | ±30.41%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.146ms   | ±93.86%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.030mb  | 7.642ms   | ±7.07%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 5.658ms   | ±105.10% |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 7.423ms   | ±53.16%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 27.774ms  | ±26.84%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 7.925ms   | ±132.50% |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 5.709ms   | ±54.52%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.040ms   | ±105.20% |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.431ms   | ±88.95%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.124mb  | 4.902ms   | ±89.24%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 3.072ms   | ±96.41%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 2.853ms   | ±150.00% |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 52.210ms  | ±75.78%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 2.664ms   | ±163.68% |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 3.179ms   | ±2.50%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 7.998ms   | ±1.75%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 18.009mb | 50.473ms  | ±0.10%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 27.431mb | 216.277ms | ±0.49%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 64.817mb | 850.712ms | ±1.23%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 29.360mb | 148.748ms | ±1.19%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 34.091mb | 118.029ms | ±1.69%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 19.008mb | 91.930ms  | ±0.14%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 21.303mb | 125.559ms | ±0.25%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 20.493mb | 103.783ms | ±0.51%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.943mb | 196.031ms | ±0.13%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.460mb | 31.154ms  | ±0.19%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.375mb | 27.330ms  | ±0.15%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 17.294mb | 25.882ms  | ±0.79%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.746mb | 81.889ms  | ±0.18%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 17.363mb | 28.976ms  | ±0.35%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.589mb | 39.721ms  | ±2.41%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 18.058mb | 55.259ms  | ±1.03%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 17.258mb | 25.068ms  | ±1.83%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 17.251mb | 29.368ms  | ±1.24%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 17.278mb | 30.419ms  | ±2.51%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 17.247mb | 32.187ms  | ±1.11%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 17.269mb | 30.858ms  | ±2.74%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 32.219mb | 47.563ms  | ±3.74%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 19.262mb | 27.319ms  | ±0.59%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.941mb | 24.365ms  | ±0.56%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 17.215mb | 26.069ms  | ±0.11%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 17.236mb | 28.910ms  | ±0.24%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 17.229mb | 29.070ms  | ±0.42%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.423mb | 26.706ms  | ±1.62%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.725mb | 132.992ms | ±0.12%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.827mb | 99.103ms  | ±0.22%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.887mb  | 1.737ms   | ±10.70%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 1.857ms   | ±1.11%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 1.968ms   | ±64.02%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 6.624ms   | ±109.31% |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.984ms   | ±132.96% |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.351mb  | 3.614ms   | ±51.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 2.782ms   | ±26.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 15.313ms  | ±65.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.125ms   | ±183.98% |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 8.373ms   | ±111.72% |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 1.057ms   | ±195.93% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.105mb  | 2.429ms   | ±134.31% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.199mb  | 2.745ms   | ±147.50% |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 4.139ms   | ±140.80% |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 129.772ms | ±19.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.948ms   | ±93.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 5.851ms   | ±145.25% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 19.789ms  | ±20.47%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.751ms   | ±42.89%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.078ms  | ±72.09%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.072ms   | ±100.93% |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 14.560ms  | ±120.06% |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 20.803ms  | ±7.16%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 1.235ms   | ±98.26%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 1.974ms   | ±114.06% |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 1.043ms   | ±13.78%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.297ms   | ±8.53%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 9.297ms   | ±67.73%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 20.303ms  | ±4.27%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 21.714ms  | ±9.78%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 22.765ms  | ±55.83%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 40.403ms  | ±7.45%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 62.812ms  | ±0.52%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.381mb  | 8.050ms   | ±1.02%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.600mb  | 11.012ms  | ±12.31%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.921mb  | 16.102ms  | ±44.44%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.614mb | 43.846ms  | ±19.47%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.977mb | 97.762ms  | ±6.11%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 4.585ms   | ±75.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 30.198ms  | ±18.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 0.667μs   | ±31.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 0.796μs   | ±34.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 0.667μs   | ±44.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 98.392ms  | ±33.75%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 274.841μs | ±1.87%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 1.941ms   | ±2.11%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 11.828ms  | ±59.04%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 10.343ms  | ±53.69%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 52.009ms  | ±3.34%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 8.505ms   | ±1.13%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 14.919ms  | ±3.03%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 122.191ms | ±42.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 11.312ms  | ±86.17%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 8.605ms   | ±1.58%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.270mb  | 10.883ms  | ±69.80%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 8.970ms   | ±0.98%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 9.018ms   | ±69.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 2.531ms   | ±155.03% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.273mb  | 9.145ms   | ±63.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.324mb  | 9.912ms   | ±6.67%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 9.301ms   | ±31.07%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```