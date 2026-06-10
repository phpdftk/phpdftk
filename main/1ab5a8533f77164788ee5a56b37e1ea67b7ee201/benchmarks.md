# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-10 01:54:02 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.647ms | 2.464ms | 2.698ms | 4.684ms | 6.865ms |
| FPDF | 851.952μs | 919.950μs | 1.021ms | 1.626ms | 2.375ms |
| TCPDF | 10.095ms | 10.975ms | 11.875ms | 19.158ms | 28.167ms |
| mPDF | 26.387ms | 29.513ms | 32.309ms | 60.290ms | 94.771ms |
| Dompdf | 11.124ms | 15.153ms | 19.926ms | 65.775ms | 148.597ms |

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
| phpdftk | 3.263ms | 3.534ms | 3.737ms | 5.780ms | 8.136ms |
| FPDF | 1.114ms | 1.224ms | 1.317ms | 1.992ms | 2.807ms |
| TCPDF | 17.422ms | 18.235ms | 19.233ms | 27.795ms | 38.155ms |

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
| Pdf (Level 3) | 3.341ms | 4.274ms | 12.096ms |
| PdfDoc (Level 2) | 2.669ms | 3.073ms | 7.293ms |
| PdfWriter (Level 1) | 2.264ms | 2.740ms | 6.859ms |

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
| Pdf (Level 3) | 4.269ms | 11.509ms | 45.576ms |
| PdfDoc (Level 2) | 3.621ms | 9.776ms | — |

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
| Pdf (Level 3) | 3.977ms | 11.340ms | 44.492ms |
| PdfDoc (Level 2) | 3.197ms | 7.148ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.942mb | 6.494mb | 8.938mb |
| PdfDoc (Level 2) | 5.731mb | 6.225mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.149ms | 1.652ms | 6.008ms |
| smalot/pdfparser | 2.024ms | 2.350ms | 5.473ms |
| setasign/fpdi | 1.870ms | 2.659ms | 28.341ms |

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
| phpdftk | 2.031ms | 1.324ms |
| smalot/pdfparser | FAIL | 1.907ms |
| setasign/fpdi | 2.824ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.210mb  | 1.200ms   | ±1.18%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.210mb  | 1.652ms   | ±1.29%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.567mb  | 6.008ms   | ±0.77%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.210mb  | 2.031ms   | ±3.50%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.210mb  | 1.324ms   | ±1.16%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.715mb  | 2.024ms   | ±1.50%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.816mb  | 2.350ms   | ±0.82%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.573mb  | 5.473ms   | ±0.52%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.210mb  | 546.836μs | ±1.34%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.711mb  | 1.907ms   | ±0.87%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.744mb  | 1.870ms   | ±0.91%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.744mb  | 2.659ms   | ±0.84%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.491mb  | 28.341ms  | ±2.99%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.839mb  | 2.824ms   | ±5.06%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.771mb  | 1.462ms   | ±0.98%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.989mb  | 7.077ms   | ±0.47%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.961mb  | 5.344ms   | ±0.47%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.939mb  | 3.812ms   | ±0.50%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.210mb  | 3.619μs   | ±21.76% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.313mb  | 6.149ms   | ±0.86%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.560mb | 43.848ms  | ±0.23%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.149mb | 89.932ms  | ±0.51%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.853mb | 1.029s    | ±0.49%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.780mb | 23.889ms  | ±1.82%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.741mb | 51.829ms  | ±0.25%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.552mb | 456.157ms | ±1.14%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.408mb | 63.036ms  | ±9.38%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.062mb | 81.665ms  | ±1.36%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.830mb | 654.247ms | ±0.66%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.762mb | 17.560ms  | ±0.51%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.762mb | 41.013ms  | ±0.26%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.404mb | 273.224ms | ±1.47%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.310mb  | 4.269ms   | ±0.94%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.105mb  | 11.509ms  | ±0.61%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.513mb | 45.576ms  | ±0.58%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.116mb  | 3.621ms   | ±1.00%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.931mb  | 9.776ms   | ±0.91%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.838mb | 65.044ms  | ±0.52%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.697mb | 273.123ms | ±0.84%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.317mb | 1.067s    | ±1.05%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.112mb | 199.839ms | ±0.46%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.444mb | 169.797ms | ±0.51%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.623mb | 125.601ms | ±0.84%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.660mb | 166.508ms | ±0.37%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.216mb | 133.716ms | ±0.64%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.696mb | 256.339ms | ±0.25%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.362mb | 40.183ms  | ±0.43%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.282mb | 35.041ms  | ±0.49%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.212mb | 34.596ms  | ±1.58%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.513mb | 109.604ms | ±0.61%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.270mb | 37.160ms  | ±1.20%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.483mb | 46.101ms  | ±0.33%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.899mb | 67.955ms  | ±1.03%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.178mb | 29.923ms  | ±0.49%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.681mb | 37.560ms  | ±0.28%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.809mb | 166.607ms | ±0.13%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.743mb | 110.573ms | ±0.43%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.704mb  | 8.580ms   | ±0.44%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.518mb  | 8.189ms   | ±1.22%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.727mb  | 8.279ms   | ±0.34%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.353mb  | 2.264ms   | ±0.25%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.512mb  | 2.740ms   | ±0.88%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.087mb  | 6.859ms   | ±0.45%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.616mb  | 2.669ms   | ±1.53%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.774mb  | 3.073ms   | ±1.25%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.342mb  | 7.293ms   | ±1.03%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.959mb  | 3.341ms   | ±1.24%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.122mb  | 4.274ms   | ±2.79%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.799mb  | 12.096ms  | ±0.51%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.942mb  | 3.977ms   | ±1.46%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.494mb  | 11.340ms  | ±0.80%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.938mb  | 44.492ms  | ±0.75%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.731mb  | 3.197ms   | ±0.60%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.225mb  | 7.148ms   | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.850mb  | 2.272ms   | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.910mb  | 2.464ms   | ±1.24%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.996mb  | 2.698ms   | ±0.64%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.631mb  | 4.684ms   | ±3.56%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.453mb  | 6.865ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.252mb  | 3.438ms   | ±0.13%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.306mb  | 3.710ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.734mb  | 12.935ms  | ±28.56% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.295mb  | 3.580ms   | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.638mb  | 2.334ms   | ±0.16%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.618mb  | 588.116μs | ±3.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.068mb  | 2.935ms   | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.162mb  | 3.561ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.082mb  | 3.248ms   | ±1.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.138mb  | 269.393ms | ±18.10% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.200mb  | 3.475ms   | ±0.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.811mb  | 5.827ms   | ±53.55% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.947mb  | 5.962ms   | ±0.81%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.884mb | 10.095ms  | ±0.48%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.884mb | 10.975ms  | ±0.67%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.884mb | 11.875ms  | ±0.25%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.884mb | 19.158ms  | ±0.38%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.884mb | 28.167ms  | ±0.50%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.045mb  | 851.952μs | ±1.96%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.046mb  | 919.950μs | ±2.07%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.046mb  | 1.021ms   | ±1.69%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.046mb  | 1.626ms   | ±1.24%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.048mb  | 2.375ms   | ±1.59%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.596mb | 26.387ms  | ±2.47%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.655mb | 29.513ms  | ±0.82%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.693mb | 32.309ms  | ±0.69%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.986mb | 60.290ms  | ±0.54%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.348mb | 94.771ms  | ±0.46%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.329mb  | 11.124ms  | ±0.60%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.549mb  | 15.153ms  | ±1.65%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.870mb  | 19.926ms  | ±0.55%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.563mb | 65.775ms  | ±0.42%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.926mb | 148.597ms | ±1.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.012mb  | 4.985ms   | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.449mb  | 54.216ms  | ±1.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.618mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.618mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.618mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.031mb  | 204.468ms | ±17.59% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.618mb  | 479.473μs | ±10.01% |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.426mb  | 2.993ms   | ±13.50% |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.024mb  | 3.185ms   | ±0.95%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.618mb  | 10.850ms  | ±9.65%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.618mb  | 81.390ms  | ±1.95%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.618mb  | 14.841ms  | ±0.26%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.618mb  | 25.944ms  | ±1.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.851mb  | 217.981ms | ±16.61% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.181mb  | 13.900ms  | ±0.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.154mb  | 13.801ms  | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.155mb  | 13.991ms  | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.179mb  | 14.212ms  | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.283mb  | 14.923ms  | ±1.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.959mb  | 2.880ms   | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.158mb  | 13.882ms  | ±2.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.217mb  | 14.063ms  | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.111mb  | 13.647ms  | ±0.54%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.337mb  | 3.263ms   | ±0.26%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.383mb  | 3.534ms   | ±1.24%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.443mb  | 3.737ms   | ±0.77%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.935mb  | 5.780ms   | ±0.28%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.534mb  | 8.136ms   | ±0.81%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.459mb | 17.422ms  | ±0.53%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.459mb | 18.235ms  | ±0.69%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.459mb | 19.233ms  | ±0.55%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.459mb | 27.795ms  | ±0.33%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.460mb | 38.155ms  | ±0.77%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.380mb  | 1.114ms   | ±1.06%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.380mb  | 1.224ms   | ±1.37%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.380mb  | 1.317ms   | ±2.45%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.409mb  | 1.992ms   | ±0.12%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.469mb  | 2.807ms   | ±0.82%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.925mb  | 3.546ms   | ±0.39%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.413mb  | 5.307ms   | ±0.61%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.733mb  | 5.471ms   | ±0.26%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.692mb  | 4.702ms   | ±1.02%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.155mb  | 5.119ms   | ±0.92%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.220mb  | 4.984ms   | ±0.49%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.078mb  | 8.434ms   | ±0.55%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.631mb  | 2.803ms   | ±0.36%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.209mb  | 43.061μs  | ±1.25%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.469mb  | 237.586μs | ±0.60%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.856mb  | 20.141ms  | ±0.88%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.841mb | 181.799ms | ±0.33%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.357mb | 899.339ms | ±0.41%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```