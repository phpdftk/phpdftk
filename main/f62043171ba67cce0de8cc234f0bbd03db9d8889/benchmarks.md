# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-25 01:13:20 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.329ms | 2.557ms | 2.821ms | 4.810ms | 7.064ms |
| FPDF | 770.752μs | 857.151μs | 936.040μs | 1.543ms | 2.283ms |
| TCPDF | 10.095ms | 11.003ms | 12.126ms | 20.979ms | 31.937ms |
| mPDF | 25.497ms | 29.456ms | 33.599ms | 66.116ms | 106.960ms |
| Dompdf | 11.408ms | 16.142ms | 21.740ms | 73.950ms | 163.042ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.148mb | 5.939mb | 6.025mb | 6.659mb | 7.482mb |
| FPDF | 5.072mb | 5.073mb | 5.073mb | 5.073mb | 5.083mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.682mb | 17.721mb | 18.014mb | 18.375mb |
| Dompdf | 9.357mb | 9.577mb | 9.898mb | 12.591mb | 15.954mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.305ms | 3.568ms | 3.913ms | 5.855ms | 8.344ms |
| FPDF | 1.035ms | 1.111ms | 1.234ms | 1.931ms | 2.747ms |
| TCPDF | 17.257ms | 18.377ms | 19.684ms | 29.346ms | 42.018ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.365mb | 5.412mb | 5.471mb | 5.964mb | 6.562mb |
| FPDF | 4.455mb | 4.455mb | 4.455mb | 4.455mb | 4.504mb |
| TCPDF | 12.487mb | 12.487mb | 12.487mb | 12.487mb | 12.487mb |

## Writer Levels Comparison — `WriterLevelsBench`

Same workload (N pages with heading + body text) rendered through each
writer level, so the abstraction overhead is visible directly. Lower is
better; the higher-level APIs (`Pdf` → `PdfDoc` → `PdfWriter`) trade
some performance for ergonomics.

### Generation Time

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 3.339ms | 4.455ms | 12.682ms |
| PdfDoc (Level 2) | 2.645ms | 3.135ms | 7.403ms |
| PdfWriter (Level 1) | 2.329ms | 2.814ms | 6.942ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 5.987mb | 6.150mb | 7.827mb |
| PdfDoc (Level 2) | 5.644mb | 5.802mb | 7.371mb |
| PdfWriter (Level 1) | 5.381mb | 5.540mb | 7.115mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 4.375ms | 12.131ms | 47.303ms |
| PdfDoc (Level 2) | 3.734ms | 10.095ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.338mb | 9.133mb | 21.542mb |
| PdfDoc (Level 2) | 6.144mb | 8.960mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 4.075ms | 11.515ms | 45.121ms |
| PdfDoc (Level 2) | 3.245ms | 7.280ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.258ms | 1.696ms | 6.027ms |
| smalot/pdfparser | 2.044ms | 2.394ms | 5.833ms |
| setasign/fpdi | 1.981ms | 2.888ms | 30.016ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.341mb | 4.243mb | 4.594mb |
| smalot/pdfparser | 4.807mb | 4.891mb | 6.601mb |
| setasign/fpdi | 4.742mb | 4.769mb | 5.526mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 2.044ms | 1.371ms |
| smalot/pdfparser | FAIL | 2.000ms |
| setasign/fpdi | 2.999ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.264ms   | ±1.14%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.696ms   | ±0.24%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.027ms   | ±1.01%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.044ms   | ±1.38%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.371ms   | ±1.42%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 2.044ms   | ±2.55%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.394ms   | ±1.44%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.833ms   | ±0.73%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 563.537μs | ±2.17%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 2.000ms   | ±1.17%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.981ms   | ±0.52%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.888ms   | ±1.47%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.016ms  | ±0.59%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.999ms   | ±0.53%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.557ms   | ±0.97%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 7.455ms   | ±0.34%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 5.486ms   | ±0.73%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 3.898ms   | ±1.10%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.432μs   | ±24.83% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.258ms   | ±0.87%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 13.673mb | 51.069ms  | ±0.12%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 14.195mb | 105.509ms | ±0.85%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.748mb | 1.256s    | ±0.34%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.484mb | 26.097ms  | ±3.04%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 16.445mb | 58.833ms  | ±0.70%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 56.256mb | 556.201ms | ±1.12%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 28.112mb | 65.050ms  | ±9.10%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.766mb | 86.588ms  | ±1.54%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 31.535mb | 743.636ms | ±1.03%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.466mb | 18.947ms  | ±0.87%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.466mb | 43.900ms  | ±0.15%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.174mb | 331.205ms | ±0.89%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.375ms   | ±0.71%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 12.131ms  | ±4.28%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 47.303ms  | ±0.55%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.734ms   | ±0.39%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 10.095ms  | ±0.71%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.884mb | 75.829ms  | ±0.17%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.657mb | 331.629ms | ±0.65%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 43.277mb | 1.302s    | ±1.22%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 26.092mb | 233.744ms | ±0.39%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 30.423mb | 182.874ms | ±0.45%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 14.670mb | 147.624ms | ±0.35%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.707mb | 197.279ms | ±0.63%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 15.264mb | 161.629ms | ±0.18%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.743mb | 311.141ms | ±0.41%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 13.409mb | 47.440ms  | ±0.57%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 13.329mb | 41.128ms  | ±0.50%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 13.259mb | 38.989ms  | ±0.13%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 14.560mb | 130.446ms | ±0.25%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 13.317mb | 43.016ms  | ±0.32%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 13.530mb | 54.630ms  | ±0.57%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.945mb | 80.908ms  | ±0.38%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 13.224mb | 34.675ms  | ±0.35%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 13.641mb | 42.811ms  | ±0.53%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.770mb | 202.413ms | ±0.15%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.790mb | 166.063ms | ±0.94%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.874ms   | ±0.97%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 8.373ms   | ±0.12%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 8.294ms   | ±0.28%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.329ms   | ±1.31%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.814ms   | ±0.73%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.942ms   | ±0.48%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.645ms   | ±3.96%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.135ms   | ±2.51%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 7.403ms   | ±1.46%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.339ms   | ±0.85%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.455ms   | ±0.51%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 12.682ms  | ±0.88%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 4.075ms   | ±0.57%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 11.515ms  | ±0.80%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 45.121ms  | ±10.31% |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.245ms   | ±1.13%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 7.280ms   | ±0.37%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.960μs   | ±15.93% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.986μs   | ±26.50% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.871mb | 14.574ms  | ±4.68%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.871mb | 14.621ms  | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.351ms   | ±1.26%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.557ms   | ±1.00%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.821ms   | ±1.13%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.810ms   | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 7.064ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.523ms   | ±0.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.910ms   | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 12.370ms  | ±17.85% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.696ms   | ±0.39%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.399ms   | ±1.74%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 675.023μs | ±4.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.249ms   | ±1.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.755ms   | ±1.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 3.280ms   | ±1.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 172.020ms | ±24.59% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.593ms   | ±0.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 5.841ms   | ±19.62% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 6.080ms   | ±4.21%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.095ms  | ±0.63%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.003ms  | ±1.13%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.126ms  | ±0.73%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.979ms  | ±0.55%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.937ms  | ±1.52%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 770.752μs | ±1.89%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 857.151μs | ±2.12%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 936.040μs | ±2.17%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.543ms   | ±0.69%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.283ms   | ±0.53%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.497ms  | ±1.50%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 29.456ms  | ±0.22%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.599ms  | ±2.06%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 66.116ms  | ±0.61%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 106.960ms | ±0.43%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.408ms  | ±0.47%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.142ms  | ±0.41%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.740ms  | ±2.66%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 73.950ms  | ±0.91%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 163.042ms | ±0.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 5.073ms   | ±1.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.488mb  | 50.700ms  | ±1.28%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.463μs   | ±17.82% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.624μs   | ±18.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 215.990ms | ±18.12% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 455.744μs | ±1.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 3.007ms   | ±0.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.501ms   | ±1.18%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 14.518ms  | ±4.18%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 84.199ms  | ±1.38%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.151ms  | ±0.62%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 24.516ms  | ±2.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 187.039ms | ±28.01% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 13.528ms  | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 13.474ms  | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 13.433ms  | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 13.508ms  | ±0.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 13.813ms  | ±0.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.951ms   | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 13.455ms  | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 13.537ms  | ±0.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 13.329ms  | ±0.45%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.305ms   | ±1.06%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.568ms   | ±1.59%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.913ms   | ±1.50%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.855ms   | ±0.85%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.344ms   | ±0.84%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 17.257ms  | ±0.62%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 18.377ms  | ±2.43%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 19.684ms  | ±0.56%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 29.346ms  | ±0.95%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 42.018ms  | ±0.97%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.035ms   | ±3.99%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.111ms   | ±2.28%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.234ms   | ±1.44%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.931ms   | ±0.88%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.747ms   | ±1.44%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.129mb  | 10.077ms  | ±0.45%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.546mb  | 10.027ms  | ±0.86%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.766mb  | 11.491ms  | ±0.40%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.871mb  | 11.796ms  | ±0.36%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.290mb  | 10.691ms  | ±0.54%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.428mb  | 10.251ms  | ±0.49%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.609mb | 19.078ms  | ±0.59%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.685mb  | 3.004ms   | ±0.49%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 42.248μs  | ±1.18%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 240.594μs | ±0.75%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.209mb  | 24.718ms  | ±0.89%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.194mb | 217.952ms | ±0.76%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.710mb | 1.072s    | ±0.47%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```