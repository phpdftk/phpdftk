# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-25 18:43:41 UTC
PHP: 8.4.26
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.120ms | 2.631ms | 2.847ms | 4.894ms | 7.135ms |
| FPDF | 873.196μs | 909.560μs | 1.009ms | 1.623ms | 2.346ms |
| TCPDF | 10.061ms | 11.020ms | 12.108ms | 20.565ms | 31.360ms |
| mPDF | 25.253ms | 29.225ms | 33.313ms | 64.884ms | 105.637ms |
| Dompdf | 11.411ms | 16.230ms | 21.614ms | 74.841ms | 163.742ms |

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
| phpdftk | 23.108ms | 3.633ms | 3.909ms | 5.922ms | 8.606ms |
| FPDF | 1.128ms | 1.198ms | 1.279ms | 1.963ms | 2.816ms |
| TCPDF | 14.530ms | 15.580ms | 16.842ms | 26.531ms | 38.770ms |

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
| Pdf (Level 3) | 3.458ms | 4.579ms | 12.913ms |
| PdfDoc (Level 2) | 2.786ms | 3.245ms | 7.593ms |
| PdfWriter (Level 1) | 2.378ms | 2.861ms | 7.048ms |

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
| Pdf (Level 3) | 4.482ms | 12.288ms | 47.254ms |
| PdfDoc (Level 2) | 3.866ms | 10.149ms | — |

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
| Pdf (Level 3) | 4.174ms | 11.789ms | 45.864ms |
| PdfDoc (Level 2) | 3.401ms | 7.468ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.223ms | 1.667ms | 5.943ms |
| smalot/pdfparser | 1.992ms | 2.366ms | 5.723ms |
| setasign/fpdi | 1.932ms | 2.867ms | 29.836ms |

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
| phpdftk | 2.021ms | 1.371ms |
| smalot/pdfparser | FAIL | 1.919ms |
| setasign/fpdi | 3.000ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.240ms   | ±1.67%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.667ms   | ±1.31%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.943ms   | ±0.81%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.021ms   | ±1.57%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.371ms   | ±0.81%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 1.992ms   | ±0.95%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.366ms   | ±1.07%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.723ms   | ±0.56%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 539.745μs | ±0.90%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.919ms   | ±0.53%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.932ms   | ±0.76%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.867ms   | ±4.94%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.836ms  | ±0.40%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.000ms   | ±0.83%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.490ms   | ±0.73%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.385ms   | ±0.53%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.492ms   | ±0.18%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.843ms   | ±0.93%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.515μs   | ±15.49%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.223ms   | ±0.48%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 1.836μs   | ±14.14%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.834μs   | ±16.62%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.857mb | 20.254ms  | ±4.88%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.857mb | 19.069ms  | ±2.31%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 9.381mb  | 16.389ms  | ±0.22%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.985mb  | 14.340ms  | ±0.34%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 10.198mb | 23.189ms  | ±1.00%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 10.201mb | 21.135ms  | ±0.48%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 11.772mb | 63.143ms  | ±0.70%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 9.633mb  | 15.607ms  | ±0.71%   |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.847mb  | 19.367ms  | ±0.58%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 12.992mb | 79.422ms  | ±0.75%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 8.045mb  | 6.897ms   | ±1.10%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.687μs  | ±1.09%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 246.107μs | ±3.48%   |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.374mb  | 23.108ms  | ±112.56% |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.633ms   | ±1.42%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.909ms   | ±0.66%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 5.922ms   | ±0.63%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.571mb  | 8.606ms   | ±2.22%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 14.530ms  | ±0.64%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.580ms  | ±0.62%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.842ms  | ±0.98%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.531ms  | ±0.30%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 38.770ms  | ±0.36%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.128ms   | ±5.89%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.198ms   | ±4.62%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.279ms   | ±3.47%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.963ms   | ±2.11%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.816ms   | ±1.39%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.694mb | 61.416ms  | ±0.36%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 18.302mb | 123.925ms | ±0.54%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 65.481mb | 1.499s    | ±0.38%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.521mb | 26.480ms  | ±1.85%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.547mb | 58.527ms  | ±0.76%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 58.358mb | 520.196ms | ±0.47%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.790mb | 63.308ms  | ±9.43%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.510mb | 86.097ms  | ±0.98%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 34.278mb | 727.707ms | ±0.72%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.547mb | 18.876ms  | ±0.38%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.547mb | 43.260ms  | ±0.38%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 31.189mb | 320.801ms | ±0.49%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 9.153ms   | ±1.03%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.680ms   | ±10.95%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.685ms   | ±0.59%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.937mb  | 27.683ms  | ±1.03%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.461mb | 249.578ms | ±0.54%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 50.370mb | 1.228s    | ±0.10%   |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.433mb | 195.495ms | ±0.66%   |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 28.602mb | 1.099s    | ±0.35%   |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 25.406mb | 885.627ms | ±0.27%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.482ms   | ±0.63%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.288ms  | ±2.12%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.254ms  | ±0.41%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.866ms   | ±0.67%   |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.030mb  | 10.149ms  | ±0.27%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.174ms   | ±0.67%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.789ms  | ±0.88%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.864ms  | ±0.27%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.401ms   | ±0.73%   |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.468ms   | ±12.89%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.378ms   | ±1.70%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 2.861ms   | ±1.13%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.124mb  | 7.048ms   | ±0.76%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.786ms   | ±1.66%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.245ms   | ±6.22%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.593ms   | ±0.44%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.458ms   | ±0.93%   |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.579ms   | ±0.97%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.913ms  | ±0.58%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 18.009mb | 92.095ms  | ±0.10%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 27.431mb | 400.804ms | ±0.70%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 64.817mb | 1.582s    | ±1.19%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 29.360mb | 271.604ms | ±0.70%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 34.091mb | 204.706ms | ±0.47%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 19.008mb | 169.348ms | ±1.07%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 21.303mb | 232.544ms | ±1.08%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 20.493mb | 193.439ms | ±0.69%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.943mb | 365.451ms | ±0.34%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.460mb | 56.108ms  | ±0.85%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.375mb | 48.736ms  | ±0.48%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 17.294mb | 45.290ms  | ±0.32%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.746mb | 149.993ms | ±0.26%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 17.363mb | 51.361ms  | ±0.46%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.589mb | 65.222ms  | ±1.12%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 18.058mb | 95.824ms  | ±0.04%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 17.258mb | 41.438ms  | ±0.37%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 17.251mb | 49.040ms  | ±0.11%   |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 17.278mb | 51.235ms  | ±0.40%   |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 17.247mb | 49.657ms  | ±0.31%   |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 17.269mb | 48.281ms  | ±0.50%   |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 32.219mb | 74.726ms  | ±0.33%   |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 19.262mb | 46.893ms  | ±0.67%   |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.941mb | 42.180ms  | ±0.54%   |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 17.215mb | 45.647ms  | ±0.36%   |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 17.236mb | 50.663ms  | ±0.60%   |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 17.229mb | 51.737ms  | ±0.61%   |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.423mb | 45.701ms  | ±0.32%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.725mb | 244.764ms | ±1.44%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.827mb | 178.931ms | ±0.26%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.887mb  | 2.399ms   | ±1.54%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.631ms   | ±46.89%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.847ms   | ±1.37%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 4.894ms   | ±1.00%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.135ms   | ±1.00%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.351mb  | 3.643ms   | ±0.98%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 3.939ms   | ±1.38%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.194ms  | ±15.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.708ms   | ±0.83%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.456ms   | ±1.12%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 713.868μs | ±4.79%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.105mb  | 3.346ms   | ±12.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.199mb  | 3.750ms   | ±0.93%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.334ms   | ±0.92%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 171.705ms | ±37.98%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.700ms   | ±0.79%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.219ms   | ±52.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.286ms   | ±1.32%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.061ms  | ±0.90%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.020ms  | ±0.67%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.108ms  | ±0.94%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.565ms  | ±0.51%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.360ms  | ±0.15%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 873.196μs | ±4.36%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 909.560μs | ±2.81%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 1.009ms   | ±3.04%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.623ms   | ±2.79%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.346ms   | ±1.56%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.253ms  | ±1.64%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 29.225ms  | ±1.08%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.313ms  | ±0.31%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 64.884ms  | ±0.30%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 105.637ms | ±0.42%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.381mb  | 11.411ms  | ±0.95%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.600mb  | 16.230ms  | ±2.92%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.921mb  | 21.614ms  | ±0.64%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.614mb | 74.841ms  | ±0.98%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.977mb | 163.742ms | ±1.22%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.241ms   | ±1.39%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 50.441ms  | ±1.95%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.463μs   | ±17.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 189.189ms | ±10.35%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 459.367μs | ±1.25%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 2.985ms   | ±0.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.469ms   | ±1.11%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.572ms  | ±3.51%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 83.097ms  | ±0.92%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.601ms  | ±1.60%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.156ms  | ±1.09%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 203.984ms | ±19.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.372ms  | ±1.15%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.138ms  | ±0.41%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.270mb  | 13.338ms  | ±1.47%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 13.425ms  | ±0.52%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 13.734ms  | ±0.29%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.221ms   | ±0.50%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.273mb  | 13.328ms  | ±1.95%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.324mb  | 13.346ms  | ±0.75%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.120ms  | ±0.91%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```