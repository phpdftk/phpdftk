# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-09 18:07:01 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.625ms | 2.555ms | 2.800ms | 4.749ms | 7.052ms |
| FPDF | 833.559μs | 927.068μs | 986.885μs | 1.608ms | 2.361ms |
| TCPDF | 10.293ms | 11.118ms | 12.125ms | 20.859ms | 31.305ms |
| mPDF | 25.259ms | 29.467ms | 33.286ms | 65.270ms | 105.403ms |
| Dompdf | 11.416ms | 15.913ms | 21.312ms | 71.715ms | 160.877ms |

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
| phpdftk | 3.405ms | 3.665ms | 3.930ms | 5.967ms | 8.448ms |
| FPDF | 1.149ms | 1.250ms | 1.325ms | 2.005ms | 2.872ms |
| TCPDF | 18.101ms | 19.175ms | 20.192ms | 29.835ms | 42.610ms |

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
| Pdf (Level 3) | 3.292ms | 4.417ms | 12.648ms |
| PdfDoc (Level 2) | 2.637ms | 3.145ms | 7.447ms |
| PdfWriter (Level 1) | 2.320ms | 2.771ms | 7.031ms |

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
| Pdf (Level 3) | 4.340ms | 12.040ms | 48.292ms |
| PdfDoc (Level 2) | 3.698ms | 9.924ms | — |

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
| Pdf (Level 3) | 3.998ms | 11.536ms | 44.890ms |
| PdfDoc (Level 2) | 3.245ms | 7.283ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.942mb | 6.494mb | 8.938mb |
| PdfDoc (Level 2) | 5.731mb | 6.225mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.144ms | 1.664ms | 5.951ms |
| smalot/pdfparser | 2.009ms | 2.391ms | 5.772ms |
| setasign/fpdi | 1.945ms | 2.831ms | 29.958ms |

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
| phpdftk | 2.066ms | 1.372ms |
| smalot/pdfparser | FAIL | 1.998ms |
| setasign/fpdi | 2.974ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.210mb  | 1.255ms   | ±1.23%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.210mb  | 1.664ms   | ±1.07%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.567mb  | 5.951ms   | ±1.16%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.210mb  | 2.066ms   | ±1.48%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.210mb  | 1.372ms   | ±1.91%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.715mb  | 2.009ms   | ±1.57%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.816mb  | 2.391ms   | ±0.39%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.573mb  | 5.772ms   | ±15.78% |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.210mb  | 565.398μs | ±14.03% |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.711mb  | 1.998ms   | ±11.88% |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.744mb  | 1.945ms   | ±1.10%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.744mb  | 2.831ms   | ±1.50%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.491mb  | 29.958ms  | ±0.82%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.839mb  | 2.974ms   | ±1.39%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.771mb  | 1.496ms   | ±1.17%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.989mb  | 7.249ms   | ±0.76%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.961mb  | 5.450ms   | ±1.22%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.939mb  | 3.837ms   | ±0.97%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.210mb  | 3.146μs   | ±29.77% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.313mb  | 6.144ms   | ±0.95%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.475mb | 45.128ms  | ±0.18%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.072mb | 93.184ms  | ±0.31%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.753mb | 1.090s    | ±0.22%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.775mb | 25.850ms  | ±3.01%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.736mb | 57.519ms  | ±0.93%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.547mb | 538.814ms | ±0.33%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.403mb | 64.692ms  | ±8.84%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.057mb | 86.230ms  | ±1.36%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.826mb | 725.739ms | ±0.70%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.757mb | 18.881ms  | ±1.12%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.757mb | 43.060ms  | ±0.11%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.400mb | 323.319ms | ±0.48%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.310mb  | 4.340ms   | ±0.76%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.105mb  | 12.040ms  | ±0.44%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.513mb | 48.292ms  | ±1.24%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.116mb  | 3.698ms   | ±0.64%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.931mb  | 9.924ms   | ±0.45%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.752mb | 67.056ms  | ±0.05%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.662mb | 289.566ms | ±0.54%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.282mb | 1.134s    | ±0.33%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.077mb | 210.098ms | ±0.18%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.409mb | 171.264ms | ±0.38%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.537mb | 130.407ms | ±0.64%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.575mb | 175.453ms | ±0.39%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.131mb | 142.172ms | ±0.41%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.610mb | 276.904ms | ±0.60%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.211mb | 42.129ms  | ±0.87%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.197mb | 36.708ms  | ±0.42%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.127mb | 35.665ms  | ±0.90%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.362mb | 115.726ms | ±0.55%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.183mb | 39.513ms  | ±0.56%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.397mb | 48.843ms  | ±0.49%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.813mb | 71.583ms  | ±0.44%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.092mb | 31.520ms  | ±1.12%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.646mb | 39.549ms  | ±2.28%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.774mb | 181.791ms | ±0.30%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.657mb | 112.118ms | ±0.33%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.704mb  | 8.827ms   | ±0.44%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.518mb  | 8.403ms   | ±1.31%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.727mb  | 8.518ms   | ±0.39%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.353mb  | 2.320ms   | ±2.82%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.512mb  | 2.771ms   | ±12.45% |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.087mb  | 7.031ms   | ±0.35%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.616mb  | 2.637ms   | ±0.58%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.774mb  | 3.145ms   | ±1.57%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.342mb  | 7.447ms   | ±0.42%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.959mb  | 3.292ms   | ±0.91%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.122mb  | 4.417ms   | ±1.26%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.799mb  | 12.648ms  | ±0.66%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.942mb  | 3.998ms   | ±0.47%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.494mb  | 11.536ms  | ±2.00%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.938mb  | 44.890ms  | ±0.31%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.731mb  | 3.245ms   | ±2.18%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.225mb  | 7.283ms   | ±1.29%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.850mb  | 2.298ms   | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.910mb  | 2.555ms   | ±2.07%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.996mb  | 2.800ms   | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.631mb  | 4.749ms   | ±2.21%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.453mb  | 7.052ms   | ±1.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.252mb  | 3.523ms   | ±0.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.306mb  | 3.890ms   | ±0.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.734mb  | 12.546ms  | ±1.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.295mb  | 3.752ms   | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.638mb  | 2.370ms   | ±2.61%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.618mb  | 649.583μs | ±4.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.068mb  | 3.027ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.162mb  | 3.704ms   | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.082mb  | 3.244ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.138mb  | 247.797ms | ±34.55% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.200mb  | 3.602ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.811mb  | 5.808ms   | ±1.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.947mb  | 6.090ms   | ±1.78%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.884mb | 10.293ms  | ±1.69%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.884mb | 11.118ms  | ±0.91%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.884mb | 12.125ms  | ±1.45%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.884mb | 20.859ms  | ±1.44%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.884mb | 31.305ms  | ±0.86%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.045mb  | 833.559μs | ±1.50%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.046mb  | 927.068μs | ±0.62%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.046mb  | 986.885μs | ±1.01%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.046mb  | 1.608ms   | ±4.61%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.048mb  | 2.361ms   | ±0.90%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.596mb | 25.259ms  | ±2.09%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.655mb | 29.467ms  | ±0.83%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.693mb | 33.286ms  | ±0.42%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.986mb | 65.270ms  | ±0.25%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.348mb | 105.403ms | ±0.57%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.329mb  | 11.416ms  | ±1.54%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.549mb  | 15.913ms  | ±0.48%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.870mb  | 21.312ms  | ±0.88%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.563mb | 71.715ms  | ±0.57%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.926mb | 160.877ms | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.012mb  | 5.059ms   | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.449mb  | 50.729ms  | ±2.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.618mb  | 1.142μs   | ±22.36% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.618mb  | 1.334μs   | ±9.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.618mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.031mb  | 140.155ms | ±21.81% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.618mb  | 457.248μs | ±2.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.426mb  | 2.972ms   | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.024mb  | 3.285ms   | ±0.66%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.618mb  | 13.338ms  | ±3.59%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.618mb  | 86.502ms  | ±0.93%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.618mb  | 14.161ms  | ±0.88%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.618mb  | 24.732ms  | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.851mb  | 150.878ms | ±28.56% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.181mb  | 13.557ms  | ±0.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.154mb  | 13.527ms  | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.155mb  | 13.608ms  | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.179mb  | 13.855ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.283mb  | 14.262ms  | ±2.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.959mb  | 2.983ms   | ±0.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.158mb  | 13.736ms  | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.217mb  | 13.903ms  | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.111mb  | 13.625ms  | ±1.73%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.337mb  | 3.405ms   | ±0.83%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.383mb  | 3.665ms   | ±0.98%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.443mb  | 3.930ms   | ±0.98%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.935mb  | 5.967ms   | ±0.84%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.534mb  | 8.448ms   | ±0.47%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.459mb | 18.101ms  | ±0.48%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.459mb | 19.175ms  | ±1.89%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.459mb | 20.192ms  | ±1.05%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.459mb | 29.835ms  | ±1.00%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.460mb | 42.610ms  | ±0.54%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.380mb  | 1.149ms   | ±0.36%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.380mb  | 1.250ms   | ±1.04%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.380mb  | 1.325ms   | ±0.42%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.409mb  | 2.005ms   | ±0.51%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.469mb  | 2.872ms   | ±1.17%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.925mb  | 3.714ms   | ±0.43%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.413mb  | 5.810ms   | ±0.22%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.733mb  | 5.851ms   | ±0.79%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.692mb  | 4.940ms   | ±1.65%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.155mb  | 5.455ms   | ±0.39%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.220mb  | 5.437ms   | ±0.96%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.078mb  | 9.440ms   | ±0.38%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.631mb  | 2.929ms   | ±0.63%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.209mb  | 41.669μs  | ±1.70%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.469mb  | 247.641μs | ±1.24%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.856mb  | 21.279ms  | ±1.23%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.841mb | 190.864ms | ±0.72%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.357mb | 943.039ms | ±0.93%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```