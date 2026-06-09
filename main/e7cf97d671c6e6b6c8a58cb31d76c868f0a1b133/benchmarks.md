# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-09 18:10:27 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 11.613ms | 2.248ms | 2.434ms | 4.373ms | 6.364ms |
| FPDF | 822.269μs | 861.646μs | 958.354μs | 1.519ms | 2.262ms |
| TCPDF | 10.256ms | 11.155ms | 12.432ms | 19.741ms | 29.294ms |
| mPDF | 24.706ms | 28.293ms | 31.223ms | 56.816ms | 90.204ms |
| Dompdf | 10.980ms | 15.022ms | 19.463ms | 62.696ms | 138.830ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.111mb | 5.910mb | 5.996mb | 6.631mb | 7.453mb |
| FPDF | 5.045mb | 5.046mb | 5.046mb | 5.046mb | 5.048mb |
| TCPDF | 12.884mb | 12.884mb | 12.884mb | 12.884mb | 12.884mb |
| mPDF | 17.596mb | 17.655mb | 17.693mb | 17.986mb | 18.348mb |
| Dompdf | 9.329mb | 9.549mb | 9.870mb | 12.563mb | 15.926mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 2.992ms | 3.184ms | 3.421ms | 5.236ms | 7.538ms |
| FPDF | 1.158ms | 1.144ms | 1.251ms | 1.920ms | 2.726ms |
| TCPDF | 15.248ms | 15.962ms | 17.040ms | 25.863ms | 36.786ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.337mb | 5.383mb | 5.443mb | 5.935mb | 6.534mb |
| FPDF | 4.380mb | 4.380mb | 4.380mb | 4.409mb | 4.469mb |
| TCPDF | 12.459mb | 12.459mb | 12.459mb | 12.459mb | 12.460mb |

## Writer Levels Comparison — `WriterLevelsBench`

Same workload (N pages with heading + body text) rendered through each
writer level, so the abstraction overhead is visible directly. Lower is
better; the higher-level APIs (`Pdf` → `PdfDoc` → `PdfWriter`) trade
some performance for ergonomics.

### Generation Time

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 3.039ms | 4.106ms | 11.262ms |
| PdfDoc (Level 2) | 2.404ms | 2.767ms | 6.750ms |
| PdfWriter (Level 1) | 2.074ms | 2.451ms | 6.356ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 5.959mb | 6.122mb | 7.799mb |
| PdfDoc (Level 2) | 5.616mb | 5.774mb | 7.342mb |
| PdfWriter (Level 1) | 5.353mb | 5.512mb | 7.087mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 3.961ms | 10.707ms | 42.540ms |
| PdfDoc (Level 2) | 3.481ms | 9.061ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.310mb | 9.105mb | 21.513mb |
| PdfDoc (Level 2) | 6.116mb | 8.931mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 3.569ms | 10.064ms | 37.661ms |
| PdfDoc (Level 2) | 2.929ms | 6.496ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.942mb | 6.494mb | 8.938mb |
| PdfDoc (Level 2) | 5.731mb | 6.225mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.144ms | 1.414ms | 5.025ms |
| smalot/pdfparser | 1.906ms | 2.204ms | 5.315ms |
| setasign/fpdi | 1.717ms | 2.420ms | 24.779ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.313mb | 4.210mb | 4.567mb |
| smalot/pdfparser | 4.715mb | 4.816mb | 6.573mb |
| setasign/fpdi | 4.744mb | 4.744mb | 5.491mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 1.739ms | 1.178ms |
| smalot/pdfparser | FAIL | 1.750ms |
| setasign/fpdi | 2.582ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.210mb  | 1.086ms   | ±1.69%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.210mb  | 1.414ms   | ±0.44%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.567mb  | 5.025ms   | ±0.21%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.210mb  | 1.739ms   | ±0.58%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.210mb  | 1.178ms   | ±0.81%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.715mb  | 1.906ms   | ±0.90%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.816mb  | 2.204ms   | ±1.38%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.573mb  | 5.315ms   | ±1.08%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.210mb  | 516.945μs | ±1.36%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.711mb  | 1.750ms   | ±0.59%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.744mb  | 1.717ms   | ±0.72%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.744mb  | 2.420ms   | ±1.86%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.491mb  | 24.779ms  | ±0.40%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.839mb  | 2.582ms   | ±0.91%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.771mb  | 1.374ms   | ±1.21%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.989mb  | 6.241ms   | ±1.15%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.961mb  | 4.748ms   | ±0.78%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.939mb  | 3.436ms   | ±1.23%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.210mb  | 2.333μs   | ±30.86% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.313mb  | 5.144ms   | ±1.02%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.925mb  | 3.331ms   | ±0.91%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.413mb  | 5.284ms   | ±1.91%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.733mb  | 5.318ms   | ±0.58%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.692mb  | 4.579ms   | ±0.50%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.155mb  | 5.079ms   | ±1.70%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.220mb  | 4.896ms   | ±0.47%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.078mb  | 8.424ms   | ±1.16%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.631mb  | 2.612ms   | ±0.12%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.752mb | 54.939ms  | ±0.16%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.662mb | 232.933ms | ±0.25%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.282mb | 914.633ms | ±0.54%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.077mb | 168.189ms | ±0.24%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.409mb | 139.800ms | ±0.31%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.537mb | 104.412ms | ±0.12%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.575mb | 140.215ms | ±0.28%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.131mb | 112.779ms | ±0.21%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.610mb | 217.632ms | ±0.12%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.211mb | 34.530ms  | ±0.24%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.197mb | 30.407ms  | ±0.07%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.127mb | 29.466ms  | ±1.32%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.362mb | 91.884ms  | ±0.22%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.183mb | 32.155ms  | ±0.46%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.397mb | 39.421ms  | ±0.08%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.813mb | 57.214ms  | ±0.38%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.092mb | 25.820ms  | ±0.51%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.646mb | 32.358ms  | ±0.63%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.774mb | 143.764ms | ±0.55%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.657mb | 91.132ms  | ±0.49%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.475mb | 37.442ms  | ±0.37%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.072mb | 75.393ms  | ±0.54%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.753mb | 860.065ms | ±0.02%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.775mb | 23.774ms  | ±2.61%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.736mb | 51.303ms  | ±0.45%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.547mb | 455.900ms | ±0.82%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.403mb | 57.663ms  | ±9.30%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.057mb | 76.427ms  | ±1.80%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.826mb | 635.070ms | ±0.79%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.757mb | 18.211ms  | ±0.43%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.757mb | 39.340ms  | ±0.25%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.400mb | 290.617ms | ±0.25%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.310mb  | 3.961ms   | ±1.23%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.105mb  | 10.707ms  | ±0.56%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.513mb | 42.540ms  | ±0.69%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.116mb  | 3.481ms   | ±1.38%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.931mb  | 9.061ms   | ±0.51%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.704mb  | 7.905ms   | ±0.45%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.518mb  | 7.351ms   | ±1.05%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.727mb  | 7.630ms   | ±3.34%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.337mb  | 2.992ms   | ±1.10%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.383mb  | 3.184ms   | ±0.23%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.443mb  | 3.421ms   | ±0.94%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.935mb  | 5.236ms   | ±0.74%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.534mb  | 7.538ms   | ±0.61%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.459mb | 15.248ms  | ±0.50%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.459mb | 15.962ms  | ±0.30%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.459mb | 17.040ms  | ±0.86%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.459mb | 25.863ms  | ±0.28%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.460mb | 36.786ms  | ±0.51%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.380mb  | 1.158ms   | ±4.59%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.380mb  | 1.144ms   | ±0.29%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.380mb  | 1.251ms   | ±1.00%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.409mb  | 1.920ms   | ±1.04%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.469mb  | 2.726ms   | ±0.72%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.942mb  | 3.569ms   | ±0.54%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.494mb  | 10.064ms  | ±0.56%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.938mb  | 37.661ms  | ±0.31%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.731mb  | 2.929ms   | ±0.85%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.225mb  | 6.496ms   | ±0.92%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.209mb  | 36.376μs  | ±2.10%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.469mb  | 218.819μs | ±0.66%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.353mb  | 2.074ms   | ±1.23%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.512mb  | 2.451ms   | ±0.60%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.087mb  | 6.356ms   | ±0.80%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.616mb  | 2.404ms   | ±1.18%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.774mb  | 2.767ms   | ±0.74%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.342mb  | 6.750ms   | ±0.77%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.959mb  | 3.039ms   | ±0.95%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.122mb  | 4.106ms   | ±0.88%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.799mb  | 11.262ms  | ±0.73%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.856mb  | 17.135ms  | ±0.39%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.841mb | 149.770ms | ±0.24%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.357mb | 743.312ms | ±0.12%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.850mb  | 2.081ms   | ±1.53%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.910mb  | 2.248ms   | ±3.83%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.996mb  | 2.434ms   | ±1.37%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.631mb  | 4.373ms   | ±1.28%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.453mb  | 6.364ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.252mb  | 3.321ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.306mb  | 3.529ms   | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.734mb  | 10.754ms  | ±21.86% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.295mb  | 3.490ms   | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.638mb  | 2.111ms   | ±0.91%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.618mb  | 605.295μs | ±5.98%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.068mb  | 2.643ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.162mb  | 3.398ms   | ±9.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.082mb  | 2.992ms   | ±1.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.138mb  | 207.334ms | ±17.61% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.200mb  | 3.324ms   | ±2.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.811mb  | 5.164ms   | ±28.84% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.947mb  | 5.349ms   | ±0.47%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.884mb | 10.256ms  | ±0.96%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.884mb | 11.155ms  | ±0.98%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.884mb | 12.432ms  | ±1.34%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.884mb | 19.741ms  | ±0.59%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.884mb | 29.294ms  | ±0.46%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.045mb  | 822.269μs | ±8.24%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.046mb  | 861.646μs | ±1.45%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.046mb  | 958.354μs | ±2.10%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.046mb  | 1.519ms   | ±1.48%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.048mb  | 2.262ms   | ±0.28%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.596mb | 24.706ms  | ±1.56%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.655mb | 28.293ms  | ±0.92%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.693mb | 31.223ms  | ±1.03%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.986mb | 56.816ms  | ±0.56%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.348mb | 90.204ms  | ±0.52%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.329mb  | 10.980ms  | ±2.44%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.549mb  | 15.022ms  | ±0.87%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.870mb  | 19.463ms  | ±0.25%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.563mb | 62.696ms  | ±0.41%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.926mb | 138.830ms | ±1.43%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.012mb  | 4.522ms   | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.449mb  | 41.571ms  | ±0.38%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.618mb  | 1.333μs   | ±15.81% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.618mb  | 1.290μs   | ±23.53% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.618mb  | 1.204μs   | ±19.69% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.031mb  | 191.849ms | ±44.27% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.618mb  | 410.894μs | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.426mb  | 2.651ms   | ±1.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.024mb  | 2.832ms   | ±0.57%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.618mb  | 7.746ms   | ±6.65%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.618mb  | 70.179ms  | ±0.84%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.618mb  | 11.708ms  | ±0.11%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.618mb  | 20.193ms  | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.851mb  | 204.368ms | ±11.23% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.181mb  | 11.853ms  | ±1.29%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.154mb  | 11.812ms  | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.155mb  | 11.977ms  | ±0.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.179mb  | 11.879ms  | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.283mb  | 12.211ms  | ±0.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.959mb  | 2.568ms   | ±1.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.158mb  | 11.832ms  | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.217mb  | 11.908ms  | ±0.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.111mb  | 11.613ms  | ±0.95%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```