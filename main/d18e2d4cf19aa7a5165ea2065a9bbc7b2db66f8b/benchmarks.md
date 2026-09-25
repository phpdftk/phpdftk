# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-09-25 12:53:42 UTC
PHP: 8.4.26
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.827ms | 2.722ms | 2.953ms | 5.027ms | 7.264ms |
| FPDF | 844.027μs | 962.796μs | 1.050ms | 1.622ms | 2.343ms |
| TCPDF | 10.383ms | 11.533ms | 12.544ms | 21.373ms | 31.605ms |
| mPDF | 26.666ms | 30.341ms | 34.796ms | 68.157ms | 108.436ms |
| Dompdf | 12.134ms | 16.508ms | 22.179ms | 75.987ms | 166.529ms |

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
| phpdftk | 3.524ms | 3.737ms | 3.998ms | 6.001ms | 8.494ms |
| FPDF | 1.119ms | 1.237ms | 1.342ms | 2.014ms | 2.851ms |
| TCPDF | 15.210ms | 15.772ms | 16.980ms | 26.516ms | 40.090ms |

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
| Pdf (Level 3) | 3.509ms | 4.638ms | 12.886ms |
| PdfDoc (Level 2) | 2.892ms | 3.328ms | 7.840ms |
| PdfWriter (Level 1) | 2.502ms | 3.034ms | 7.077ms |

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
| Pdf (Level 3) | 4.531ms | 12.442ms | 47.484ms |
| PdfDoc (Level 2) | 3.999ms | 10.315ms | — |

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
| Pdf (Level 3) | 4.266ms | 11.960ms | 45.871ms |
| PdfDoc (Level 2) | 3.423ms | 7.618ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 6.040mb | 6.592mb | 9.036mb |
| PdfDoc (Level 2) | 5.829mb | 6.323mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.248ms | 1.665ms | 5.947ms |
| smalot/pdfparser | 2.029ms | 2.380ms | 5.812ms |
| setasign/fpdi | 1.992ms | 2.864ms | 30.399ms |

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
| phpdftk | 2.052ms | 1.402ms |
| smalot/pdfparser | FAIL | 1.928ms |
| setasign/fpdi | 3.051ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.255ms   | ±13.62% |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.665ms   | ±1.89%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.595mb  | 5.947ms   | ±0.65%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.052ms   | ±13.77% |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.402ms   | ±14.08% |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.800mb  | 2.029ms   | ±0.90%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.884mb  | 2.380ms   | ±2.56%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.812ms   | ±0.51%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 557.543μs | ±1.59%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.794mb  | 1.928ms   | ±1.14%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.992ms   | ±10.04% |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.864ms   | ±0.71%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.399ms  | ±1.31%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.051ms   | ±1.70%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.576ms   | ±1.30%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.960mb  | 7.357ms   | ±12.95% |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.932mb  | 5.517ms   | ±0.48%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.976mb  | 3.919ms   | ±0.48%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.586μs   | ±15.72% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.248ms   | ±1.08%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.049μs   | ±16.64% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.243mb  | 2.097μs   | ±29.77% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 16.717mb | 19.130ms  | ±0.64%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 16.717mb | 19.648ms  | ±1.76%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.966mb  | 15.488ms  | ±0.44%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 9.773mb  | 13.434ms  | ±2.15%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 10.104mb | 21.506ms  | ±0.91%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 10.110mb | 19.546ms  | ±1.00%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 11.287mb | 56.031ms  | ±0.38%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 9.540mb  | 14.980ms  | ±0.85%  |
| SvgToPdfBench               | benchBasicShapeClipPathHeavy                     |     | 3    | 3   | 9.751mb  | 17.976ms  | ±1.39%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 12.813mb | 70.958ms  | ±1.60%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 7.913mb  | 6.754ms   | ±0.65%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.347μs  | ±0.57%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.506mb  | 248.417μs | ±0.77%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.374mb  | 3.524ms   | ±2.32%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.420mb  | 3.737ms   | ±0.48%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.479mb  | 3.998ms   | ±1.80%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.972mb  | 6.001ms   | ±1.99%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.571mb  | 8.494ms   | ±1.59%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 15.210ms  | ±2.31%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 15.772ms  | ±0.58%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 16.980ms  | ±1.01%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 26.516ms  | ±0.37%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.488mb | 40.090ms  | ±1.39%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.119ms   | ±4.34%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.237ms   | ±7.86%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.342ms   | ±5.43%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 2.014ms   | ±1.59%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.505mb  | 2.851ms   | ±1.71%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 17.543mb | 61.671ms  | ±0.35%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 18.154mb | 125.829ms | ±0.27%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 65.171mb | 1.517s    | ±0.31%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 16.399mb | 26.998ms  | ±2.47%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 18.425mb | 60.576ms  | ±1.05%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 58.236mb | 556.159ms | ±0.51%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 30.691mb | 66.161ms  | ±14.06% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 25.410mb | 89.010ms  | ±1.15%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 34.113mb | 740.334ms | ±0.69%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 18.448mb | 19.697ms  | ±1.79%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 18.448mb | 44.320ms  | ±0.18%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 31.090mb | 330.095ms | ±0.53%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.802mb  | 9.103ms   | ±0.56%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.616mb  | 8.759ms   | ±2.79%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.825mb  | 8.840ms   | ±1.28%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.834mb  | 27.904ms  | ±0.33%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 16.313mb | 250.453ms | ±0.07%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 50.017mb | 1.242s    | ±0.30%  |
| BoxGeneratorBench           | benchTableGrid                                   |     | 5    | 3   | 15.310mb | 197.915ms | ±0.39%  |
| BoxGeneratorBench           | benchInlineSvgUseSprites                         |     | 5    | 3   | 27.144mb | 1.026s    | ±0.79%  |
| BoxGeneratorBench           | benchInlineSvgWithoutUse                         |     | 5    | 3   | 24.225mb | 822.667ms | ±0.30%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.408mb  | 4.531ms   | ±1.39%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.203mb  | 12.442ms  | ±0.67%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.611mb | 47.484ms  | ±0.60%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.214mb  | 3.999ms   | ±2.01%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 9.030mb  | 10.315ms  | ±1.05%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 6.040mb  | 4.266ms   | ±1.13%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.592mb  | 11.960ms  | ±1.25%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 9.036mb  | 45.871ms  | ±0.51%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.829mb  | 3.423ms   | ±2.69%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.323mb  | 7.618ms   | ±7.82%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.389mb  | 2.502ms   | ±4.42%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.548mb  | 3.034ms   | ±1.77%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.124mb  | 7.077ms   | ±1.13%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.714mb  | 2.892ms   | ±1.73%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.872mb  | 3.328ms   | ±1.67%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.441mb  | 7.840ms   | ±2.14%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 6.057mb  | 3.509ms   | ±1.47%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.220mb  | 4.638ms   | ±1.01%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.897mb  | 12.886ms  | ±2.83%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 17.863mb | 92.574ms  | ±0.18%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 27.168mb | 405.549ms | ±0.38%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 64.380mb | 1.591s    | ±1.19%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 29.195mb | 275.454ms | ±0.56%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 33.945mb | 208.529ms | ±0.90%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 18.796mb | 173.011ms | ±1.10%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 21.045mb | 236.343ms | ±2.10%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 20.326mb | 195.609ms | ±0.10%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 25.706mb | 367.849ms | ±0.33%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 17.315mb | 57.512ms  | ±0.74%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 17.227mb | 50.588ms  | ±0.75%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 17.154mb | 46.927ms  | ±1.36%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 18.605mb | 151.883ms | ±0.41%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 17.216mb | 52.064ms  | ±0.55%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 17.443mb | 65.656ms  | ±0.33%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 17.908mb | 97.854ms  | ±0.30%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 17.110mb | 42.484ms  | ±0.95%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 17.111mb | 50.591ms  | ±1.69%  |
| RendererBench               | benchConicGradients                              |     | 3    | 3   | 17.138mb | 52.652ms  | ±0.87%  |
| RendererBench               | benchRadialGradients                             |     | 3    | 3   | 17.107mb | 50.783ms  | ±0.86%  |
| RendererBench               | benchCalcStopGradients                           |     | 3    | 3   | 17.129mb | 49.792ms  | ±0.29%  |
| RendererBench               | benchInterpolatedGradients                       |     | 3    | 3   | 32.079mb | 77.293ms  | ±0.40%  |
| RendererBench               | benchBorderImageRepeat                           |     | 3    | 3   | 19.121mb | 48.424ms  | ±0.47%  |
| RendererBench               | benchTiledGradients                              |     | 3    | 3   | 17.800mb | 42.363ms  | ±0.39%  |
| RendererBench               | benchColorMixResolution                          |     | 3    | 3   | 17.075mb | 46.633ms  | ±0.67%  |
| RendererBench               | benchTranslucentGradients                        |     | 3    | 3   | 17.096mb | 51.382ms  | ±0.69%  |
| RendererBench               | benchGradientMasks                               |     | 3    | 3   | 17.089mb | 52.379ms  | ±0.49%  |
| RendererBench               | benchBackgroundRepeatSpace                       |     | 3    | 3   | 20.282mb | 47.448ms  | ±2.33%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 23.559mb | 246.243ms | ±0.10%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 17.684mb | 180.172ms | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.887mb  | 2.482ms   | ±2.94%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.947mb  | 2.722ms   | ±3.40%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.033mb  | 2.953ms   | ±0.25%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.667mb  | 5.027ms   | ±3.94%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.490mb  | 7.264ms   | ±0.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.351mb  | 3.805ms   | ±8.06%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.342mb  | 4.029ms   | ±1.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.779mb  | 12.538ms  | ±20.11% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.394mb  | 3.842ms   | ±1.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.675mb  | 2.530ms   | ±1.46%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 760.739μs | ±6.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.105mb  | 3.412ms   | ±4.20%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.199mb  | 3.913ms   | ±1.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.119mb  | 3.436ms   | ±1.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.175mb  | 178.643ms | ±22.00% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.236mb  | 3.819ms   | ±1.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.851mb  | 6.130ms   | ±17.70% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.983mb  | 6.436ms   | ±1.17%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.383ms  | ±1.22%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.533ms  | ±1.41%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.544ms  | ±1.98%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 21.373ms  | ±1.19%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.605ms  | ±0.61%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 844.027μs | ±33.84% |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.072mb  | 962.796μs | ±5.17%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.072mb  | 1.050ms   | ±4.16%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.072mb  | 1.622ms   | ±6.52%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.084mb  | 2.343ms   | ±0.97%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 26.666ms  | ±1.04%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.683mb | 30.341ms  | ±2.64%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 34.796ms  | ±0.94%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 68.157ms  | ±1.07%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.376mb | 108.436ms | ±2.67%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 12.134ms  | ±1.99%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.508ms  | ±1.39%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 22.179ms  | ±1.29%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 75.987ms  | ±0.86%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 166.529ms | ±1.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.049mb  | 5.245ms   | ±1.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.496mb  | 49.949ms  | ±1.20%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.059mb  | 190.823ms | ±23.65% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 457.442μs | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.463mb  | 3.007ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.060mb  | 3.644ms   | ±1.93%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 12.243ms  | ±6.87%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 84.124ms  | ±2.36%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.587ms  | ±2.06%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 25.314ms  | ±1.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.896mb  | 174.180ms | ±25.88% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.288mb  | 13.886ms  | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.261mb  | 13.745ms  | ±1.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.270mb  | 13.949ms  | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.285mb  | 14.123ms  | ±6.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.390mb  | 14.237ms  | ±0.55%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 6.057mb  | 3.489ms   | ±0.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.273mb  | 14.090ms  | ±1.01%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.324mb  | 13.914ms  | ±1.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.218mb  | 13.827ms  | ±0.89%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```