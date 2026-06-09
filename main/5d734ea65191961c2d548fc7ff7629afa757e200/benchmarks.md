# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-09 23:53:23 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.765ms | 2.473ms | 2.730ms | 4.673ms | 6.912ms |
| FPDF | 827.261μs | 918.209μs | 1.013ms | 1.613ms | 2.336ms |
| TCPDF | 10.168ms | 11.127ms | 12.210ms | 19.401ms | 28.334ms |
| mPDF | 26.014ms | 29.531ms | 33.565ms | 61.355ms | 95.516ms |
| Dompdf | 11.180ms | 15.265ms | 20.255ms | 66.528ms | 149.097ms |

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
| phpdftk | 3.275ms | 3.514ms | 3.793ms | 5.773ms | 8.262ms |
| FPDF | 1.126ms | 1.218ms | 1.292ms | 1.994ms | 2.812ms |
| TCPDF | 14.751ms | 15.845ms | 16.775ms | 25.162ms | 35.975ms |

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
| Pdf (Level 3) | 3.272ms | 4.278ms | 12.106ms |
| PdfDoc (Level 2) | 2.592ms | 3.043ms | 7.343ms |
| PdfWriter (Level 1) | 2.280ms | 2.734ms | 6.899ms |

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
| Pdf (Level 3) | 4.238ms | 11.631ms | 46.182ms |
| PdfDoc (Level 2) | 3.670ms | 9.757ms | — |

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
| Pdf (Level 3) | 3.964ms | 11.381ms | 45.107ms |
| PdfDoc (Level 2) | 3.216ms | 7.189ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.942mb | 6.494mb | 8.938mb |
| PdfDoc (Level 2) | 5.731mb | 6.225mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.079ms | 1.665ms | 6.045ms |
| smalot/pdfparser | 2.026ms | 2.367ms | 5.518ms |
| setasign/fpdi | 1.883ms | 2.675ms | 28.393ms |

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
| phpdftk | 2.028ms | 1.357ms |
| smalot/pdfparser | FAIL | 1.905ms |
| setasign/fpdi | 2.841ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.210mb  | 1.218ms   | ±1.52%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.210mb  | 1.665ms   | ±0.90%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.567mb  | 6.045ms   | ±1.00%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.210mb  | 2.028ms   | ±0.42%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.210mb  | 1.357ms   | ±0.81%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.715mb  | 2.026ms   | ±0.32%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.816mb  | 2.367ms   | ±1.21%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.573mb  | 5.518ms   | ±0.65%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.210mb  | 553.215μs | ±1.59%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.711mb  | 1.905ms   | ±0.53%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.744mb  | 1.883ms   | ±1.06%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.744mb  | 2.675ms   | ±1.16%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.491mb  | 28.393ms  | ±2.86%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.839mb  | 2.841ms   | ±0.66%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.771mb  | 1.484ms   | ±1.20%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.989mb  | 7.096ms   | ±0.64%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.961mb  | 5.327ms   | ±0.95%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.939mb  | 3.800ms   | ±0.82%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.210mb  | 3.844μs   | ±24.34% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.313mb  | 6.079ms   | ±2.75%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.925mb  | 3.601ms   | ±1.59%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.413mb  | 5.395ms   | ±0.59%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.733mb  | 5.512ms   | ±0.69%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.692mb  | 4.712ms   | ±0.34%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.155mb  | 5.166ms   | ±0.37%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.220mb  | 5.086ms   | ±0.37%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.078mb  | 8.538ms   | ±0.23%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.631mb  | 2.893ms   | ±0.97%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.838mb | 64.474ms  | ±0.91%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.697mb | 273.988ms | ±0.27%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.317mb | 1.063s    | ±1.93%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.112mb | 203.376ms | ±0.69%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.444mb | 168.401ms | ±0.44%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.623mb | 126.659ms | ±1.03%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.660mb | 167.354ms | ±1.19%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.216mb | 133.048ms | ±0.66%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.696mb | 259.096ms | ±1.11%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.362mb | 40.467ms  | ±0.48%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.282mb | 35.410ms  | ±0.18%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.212mb | 34.902ms  | ±0.74%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.513mb | 108.108ms | ±0.45%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.270mb | 37.109ms  | ±0.62%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.483mb | 46.588ms  | ±2.72%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.899mb | 67.678ms  | ±0.70%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.178mb | 30.141ms  | ±0.48%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.681mb | 38.158ms  | ±0.65%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.809mb | 169.700ms | ±1.34%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.743mb | 110.823ms | ±0.03%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.560mb | 43.674ms  | ±0.54%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.149mb | 89.979ms  | ±0.23%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.853mb | 1.020s    | ±0.52%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.780mb | 23.972ms  | ±1.86%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.741mb | 52.670ms  | ±0.67%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.552mb | 484.884ms | ±0.97%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.408mb | 63.723ms  | ±9.96%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.062mb | 82.507ms  | ±1.32%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.830mb | 658.073ms | ±0.58%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.762mb | 17.780ms  | ±0.05%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.762mb | 41.273ms  | ±0.17%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.404mb | 275.936ms | ±0.46%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.310mb  | 4.238ms   | ±1.00%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.105mb  | 11.631ms  | ±2.37%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.513mb | 46.182ms  | ±0.20%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.116mb  | 3.670ms   | ±0.81%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.931mb  | 9.757ms   | ±0.26%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.704mb  | 8.702ms   | ±0.62%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.518mb  | 8.185ms   | ±0.62%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.727mb  | 8.337ms   | ±0.46%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.337mb  | 3.275ms   | ±2.73%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.383mb  | 3.514ms   | ±0.87%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.443mb  | 3.793ms   | ±0.93%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.935mb  | 5.773ms   | ±0.21%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.534mb  | 8.262ms   | ±0.65%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.459mb | 14.751ms  | ±0.18%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.459mb | 15.845ms  | ±0.95%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.459mb | 16.775ms  | ±0.53%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.459mb | 25.162ms  | ±0.47%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.460mb | 35.975ms  | ±0.69%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.380mb  | 1.126ms   | ±0.22%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.380mb  | 1.218ms   | ±1.09%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.380mb  | 1.292ms   | ±1.23%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.409mb  | 1.994ms   | ±1.76%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.469mb  | 2.812ms   | ±0.52%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.942mb  | 3.964ms   | ±0.71%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.494mb  | 11.381ms  | ±0.80%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.938mb  | 45.107ms  | ±0.72%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.731mb  | 3.216ms   | ±0.60%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.225mb  | 7.189ms   | ±0.87%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.209mb  | 42.347μs  | ±1.68%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.469mb  | 235.877μs | ±0.60%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.353mb  | 2.280ms   | ±0.65%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.512mb  | 2.734ms   | ±0.85%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.087mb  | 6.899ms   | ±0.92%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.616mb  | 2.592ms   | ±4.44%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.774mb  | 3.043ms   | ±1.07%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.342mb  | 7.343ms   | ±2.42%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.959mb  | 3.272ms   | ±1.04%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.122mb  | 4.278ms   | ±0.79%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.799mb  | 12.106ms  | ±0.68%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.856mb  | 20.123ms  | ±0.91%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.841mb | 180.813ms | ±0.49%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.357mb | 893.710ms | ±3.65%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.850mb  | 2.287ms   | ±2.70%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.910mb  | 2.473ms   | ±0.72%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.996mb  | 2.730ms   | ±1.43%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.631mb  | 4.673ms   | ±0.36%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.453mb  | 6.912ms   | ±0.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.252mb  | 3.432ms   | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.306mb  | 3.801ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.734mb  | 13.087ms  | ±6.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.295mb  | 3.605ms   | ±0.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.638mb  | 2.368ms   | ±1.44%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.618mb  | 601.523μs | ±4.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.068mb  | 2.951ms   | ±0.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.162mb  | 3.553ms   | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.082mb  | 3.159ms   | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.138mb  | 174.242ms | ±32.59% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.200mb  | 3.518ms   | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.811mb  | 5.762ms   | ±18.41% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.947mb  | 5.957ms   | ±1.00%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.884mb | 10.168ms  | ±0.86%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.884mb | 11.127ms  | ±0.99%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.884mb | 12.210ms  | ±1.53%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.884mb | 19.401ms  | ±0.50%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.884mb | 28.334ms  | ±0.89%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.045mb  | 827.261μs | ±2.51%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.046mb  | 918.209μs | ±1.16%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.046mb  | 1.013ms   | ±1.56%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.046mb  | 1.613ms   | ±0.65%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.048mb  | 2.336ms   | ±0.58%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.596mb | 26.014ms  | ±2.65%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.655mb | 29.531ms  | ±0.44%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.693mb | 33.565ms  | ±1.05%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.986mb | 61.355ms  | ±0.77%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.348mb | 95.516ms  | ±0.38%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.329mb  | 11.180ms  | ±0.38%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.549mb  | 15.265ms  | ±0.49%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.870mb  | 20.255ms  | ±0.61%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.563mb | 66.528ms  | ±0.59%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.926mb | 149.097ms | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.012mb  | 4.907ms   | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.449mb  | 53.955ms  | ±0.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.618mb  | 1.669μs   | ±21.43% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.618mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.618mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.031mb  | 161.883ms | ±15.99% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.618mb  | 475.680μs | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.426mb  | 2.915ms   | ±0.20%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.024mb  | 3.202ms   | ±0.75%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.618mb  | 10.663ms  | ±5.73%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.618mb  | 80.474ms  | ±1.30%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.618mb  | 14.746ms  | ±0.71%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.618mb  | 25.626ms  | ±1.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.851mb  | 172.788ms | ±26.37% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.181mb  | 13.995ms  | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.154mb  | 13.778ms  | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.155mb  | 14.055ms  | ±1.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.179mb  | 14.055ms  | ±1.04%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.283mb  | 14.441ms  | ±1.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.959mb  | 2.843ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.158mb  | 14.034ms  | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.217mb  | 14.151ms  | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.111mb  | 13.765ms  | ±0.90%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```