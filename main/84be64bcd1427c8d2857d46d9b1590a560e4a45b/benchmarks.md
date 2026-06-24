# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-24 04:07:08 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.546ms | 23.434ms | 2.123ms | 3.632ms | 5.349ms |
| FPDF | 623.260μs | 790.382μs | 724.661μs | 1.771ms | 1.759ms |
| TCPDF | 7.836ms | 8.451ms | 9.200ms | 14.902ms | 22.119ms |
| mPDF | 19.807ms | 22.611ms | 25.347ms | 46.932ms | 73.671ms |
| Dompdf | 8.664ms | 11.833ms | 15.428ms | 51.286ms | 114.870ms |

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
| phpdftk | 2.579ms | 2.723ms | 2.899ms | 4.457ms | 6.292ms |
| FPDF | 14.698ms | 874.981μs | 967.810μs | 1.486ms | 2.154ms |
| TCPDF | 13.414ms | 14.915ms | 15.033ms | 21.506ms | 29.826ms |

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
| Pdf (Level 3) | 2.544ms | 3.628ms | 9.387ms |
| PdfDoc (Level 2) | 2.034ms | 2.346ms | 5.662ms |
| PdfWriter (Level 1) | 1.857ms | 2.113ms | 5.262ms |

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
| Pdf (Level 3) | 3.330ms | 10.237ms | 36.215ms |
| PdfDoc (Level 2) | 5.636ms | 14.216ms | — |

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
| Pdf (Level 3) | 3.076ms | 9.056ms | 34.486ms |
| PdfDoc (Level 2) | 2.481ms | 5.542ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.729ms | 1.287ms | 4.681ms |
| smalot/pdfparser | 1.551ms | 1.818ms | 4.234ms |
| setasign/fpdi | 1.474ms | 2.084ms | 22.031ms |

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
| phpdftk | 1.574ms | 1.030ms |
| smalot/pdfparser | FAIL | 1.478ms |
| setasign/fpdi | 2.226ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 949.444μs | ±2.83%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.287ms   | ±1.85%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 4.681ms   | ±0.76%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 1.574ms   | ±0.57%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.030ms   | ±0.21%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 1.551ms   | ±1.34%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 1.818ms   | ±1.06%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 4.234ms   | ±0.72%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 442.501μs | ±1.04%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.478ms   | ±1.52%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.474ms   | ±1.12%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.084ms   | ±0.90%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 22.031ms  | ±0.42%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.226ms   | ±0.60%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.166ms   | ±1.68%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 5.481ms   | ±0.56%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 4.146ms   | ±0.32%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 2.941ms   | ±0.61%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.097μs   | ±21.35%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 4.729ms   | ±0.56%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 13.661mb | 37.424ms  | ±0.51%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 14.184mb | 77.029ms  | ±15.53%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.737mb | 886.764ms | ±1.15%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.473mb | 18.383ms  | ±1.23%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 16.433mb | 40.020ms  | ±0.97%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 56.244mb | 352.273ms | ±0.14%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 28.101mb | 52.823ms  | ±47.03%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.754mb | 64.128ms  | ±1.28%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 31.523mb | 512.146ms | ±1.22%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.455mb | 14.730ms  | ±11.79%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.455mb | 48.505ms  | ±20.10%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.163mb | 214.411ms | ±0.29%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 3.330ms   | ±85.12%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 10.237ms  | ±69.84%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 36.215ms  | ±24.95%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 5.636ms   | ±52.58%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 14.216ms  | ±108.32% |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.873mb | 54.765ms  | ±0.64%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.646mb | 233.527ms | ±0.52%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 43.266mb | 907.907ms | ±0.23%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 26.080mb | 165.939ms | ±0.16%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 30.411mb | 137.361ms | ±1.00%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 14.659mb | 105.510ms | ±0.72%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.696mb | 142.030ms | ±0.20%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 15.253mb | 113.874ms | ±1.17%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.731mb | 218.183ms | ±0.35%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 13.398mb | 33.900ms  | ±1.01%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 13.318mb | 29.767ms  | ±0.94%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 13.248mb | 29.046ms  | ±0.40%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 14.549mb | 91.916ms  | ±0.16%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 13.306mb | 31.072ms  | ±0.47%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 13.518mb | 39.280ms  | ±0.36%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.934mb | 57.826ms  | ±0.64%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 13.213mb | 25.161ms  | ±0.54%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 13.630mb | 30.910ms  | ±0.64%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.759mb | 144.356ms | ±0.70%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.778mb | 122.361ms | ±0.25%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 6.757ms   | ±18.84%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 6.361ms   | ±1.27%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 6.445ms   | ±98.05%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 1.857ms   | ±156.12% |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.113ms   | ±0.56%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 5.262ms   | ±5.05%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.034ms   | ±0.95%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 2.346ms   | ±5.59%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 5.662ms   | ±0.34%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 2.544ms   | ±20.43%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 3.628ms   | ±183.90% |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 9.387ms   | ±0.40%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.076ms   | ±0.34%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 9.056ms   | ±113.42% |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 34.486ms  | ±13.46%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 2.481ms   | ±54.14%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 5.542ms   | ±20.75%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.661μs   | ±24.38%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.719μs   | ±32.90%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.860mb | 11.032ms  | ±0.12%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.860mb | 11.032ms  | ±0.71%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 1.771ms   | ±0.35%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 23.434ms  | ±66.33%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.123ms   | ±25.12%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 3.632ms   | ±0.51%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 5.349ms   | ±15.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 2.654ms   | ±0.68%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 2.905ms   | ±78.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 10.197ms  | ±26.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 2.833ms   | ±20.96%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 1.816ms   | ±2.78%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 498.085μs | ±4.27%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 2.395ms   | ±40.37%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 2.824ms   | ±133.34% |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 2.421ms   | ±0.67%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 217.398ms | ±14.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 2.711ms   | ±51.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 4.513ms   | ±18.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 4.614ms   | ±0.28%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 7.836ms   | ±1.28%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 8.451ms   | ±0.47%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 9.200ms   | ±1.38%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 14.902ms  | ±4.16%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 22.119ms  | ±0.26%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 623.260μs | ±1.18%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 790.382μs | ±86.71%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 724.661μs | ±79.61%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.771ms   | ±62.87%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 1.759ms   | ±3.17%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 19.807ms  | ±1.97%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 22.611ms  | ±2.52%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 25.347ms  | ±1.23%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 46.932ms  | ±0.38%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 73.671ms  | ±0.35%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 8.664ms   | ±0.80%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 11.833ms  | ±7.47%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 15.428ms  | ±0.35%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 51.286ms  | ±0.75%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 114.870ms | ±1.46%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 3.776ms   | ±0.62%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.488mb  | 41.732ms  | ±0.56%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.333μs   | ±15.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.332μs   | ±22.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.204μs   | ±19.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 207.851ms | ±34.66%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 367.915μs | ±1.71%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.245ms   | ±0.29%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 2.574ms   | ±0.23%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 8.621ms   | ±6.34%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 63.737ms  | ±1.04%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 11.665ms  | ±1.35%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 20.171ms  | ±1.12%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 188.974ms | ±22.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 10.757ms  | ±0.49%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 10.774ms  | ±0.75%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 10.768ms  | ±5.88%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 10.722ms  | ±3.19%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 10.995ms  | ±0.56%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.254ms   | ±9.89%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 10.781ms  | ±0.89%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 10.835ms  | ±7.96%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 10.546ms  | ±0.82%   |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 2.579ms   | ±0.59%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 2.723ms   | ±0.14%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 2.899ms   | ±0.86%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 4.457ms   | ±0.22%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 6.292ms   | ±1.05%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 13.414ms  | ±0.71%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 14.915ms  | ±34.70%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 15.033ms  | ±0.55%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 21.506ms  | ±0.23%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 29.826ms  | ±0.11%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 14.698ms  | ±69.72%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 874.981μs | ±0.79%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 967.810μs | ±1.12%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.486ms   | ±0.41%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.154ms   | ±1.35%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.129mb  | 7.189ms   | ±0.44%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.546mb  | 7.311ms   | ±0.46%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.766mb  | 8.456ms   | ±0.38%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.871mb  | 8.383ms   | ±0.44%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.290mb  | 8.086ms   | ±0.57%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.428mb  | 7.360ms   | ±0.32%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.609mb | 13.284ms  | ±0.59%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.685mb  | 2.221ms   | ±0.23%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 33.224μs  | ±0.83%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 182.478μs | ±0.42%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.209mb  | 17.584ms  | ±0.95%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.194mb | 154.212ms | ±1.63%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.710mb | 758.261ms | ±0.66%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```