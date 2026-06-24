# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-24 03:17:09 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.521ms | 2.603ms | 2.788ms | 4.847ms | 7.144ms |
| FPDF | 818.214μs | 850.980μs | 932.250μs | 1.533ms | 2.292ms |
| TCPDF | 10.261ms | 11.527ms | 12.228ms | 21.096ms | 31.548ms |
| mPDF | 26.229ms | 30.214ms | 33.957ms | 67.579ms | 106.335ms |
| Dompdf | 11.558ms | 16.389ms | 21.880ms | 74.049ms | 163.684ms |

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
| phpdftk | 3.408ms | 3.636ms | 3.965ms | 5.951ms | 8.511ms |
| FPDF | 1.038ms | 1.103ms | 1.264ms | 1.958ms | 2.857ms |
| TCPDF | 18.099ms | 18.672ms | 19.990ms | 29.337ms | 42.427ms |

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
| Pdf (Level 3) | 3.310ms | 4.389ms | 12.804ms |
| PdfDoc (Level 2) | 2.658ms | 3.076ms | 7.378ms |
| PdfWriter (Level 1) | 2.414ms | 2.821ms | 6.870ms |

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
| Pdf (Level 3) | 4.316ms | 11.983ms | 46.913ms |
| PdfDoc (Level 2) | 3.783ms | 10.054ms | — |

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
| Pdf (Level 3) | 4.023ms | 11.607ms | 45.646ms |
| PdfDoc (Level 2) | 3.255ms | 7.275ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.138ms | 1.667ms | 6.006ms |
| smalot/pdfparser | 2.063ms | 2.429ms | 5.916ms |
| setasign/fpdi | 1.952ms | 2.840ms | 29.594ms |

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
| phpdftk | 2.065ms | 1.390ms |
| smalot/pdfparser | FAIL | 1.936ms |
| setasign/fpdi | 2.990ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.261ms   | ±0.91%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.667ms   | ±1.58%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.006ms   | ±2.50%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.065ms   | ±1.26%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.390ms   | ±2.39%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 2.063ms   | ±2.01%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.429ms   | ±2.37%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.916ms   | ±0.57%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 562.515μs | ±1.46%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.936ms   | ±1.90%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.952ms   | ±0.55%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.840ms   | ±0.91%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 29.594ms  | ±0.42%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.990ms   | ±0.92%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.535ms   | ±1.02%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 7.255ms   | ±0.67%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 5.516ms   | ±0.70%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 3.874ms   | ±1.37%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.519μs   | ±17.58% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.138ms   | ±1.89%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 13.661mb | 51.673ms  | ±0.43%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 14.184mb | 105.049ms | ±0.69%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.736mb | 1.260s    | ±0.49%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.473mb | 26.635ms  | ±2.15%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 16.433mb | 59.961ms  | ±0.99%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 56.244mb | 558.021ms | ±0.47%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 28.100mb | 65.167ms  | ±10.24% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.754mb | 86.726ms  | ±0.93%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 31.523mb | 735.847ms | ±1.08%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.455mb | 18.901ms  | ±0.70%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.455mb | 43.674ms  | ±0.54%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.163mb | 324.018ms | ±0.31%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.316ms   | ±0.85%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 11.983ms  | ±0.42%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 46.913ms  | ±0.74%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.783ms   | ±1.12%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 10.054ms  | ±1.45%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.873mb | 76.604ms  | ±0.53%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.646mb | 334.224ms | ±0.58%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 43.266mb | 1.291s    | ±0.36%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 26.080mb | 233.177ms | ±0.20%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 30.411mb | 181.942ms | ±0.64%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 14.659mb | 146.943ms | ±0.18%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.696mb | 196.753ms | ±0.53%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 15.253mb | 160.126ms | ±1.54%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.731mb | 310.452ms | ±0.19%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 13.397mb | 47.634ms  | ±0.32%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 13.317mb | 41.027ms  | ±0.59%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 13.248mb | 39.951ms  | ±0.60%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 14.549mb | 130.920ms | ±0.10%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 13.306mb | 43.312ms  | ±0.77%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 13.518mb | 54.902ms  | ±0.88%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.934mb | 80.873ms  | ±0.56%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 13.213mb | 35.466ms  | ±4.25%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 13.630mb | 42.948ms  | ±0.75%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.758mb | 204.795ms | ±1.03%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.778mb | 164.347ms | ±0.49%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.912ms   | ±0.93%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 8.372ms   | ±0.54%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 8.397ms   | ±0.77%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.414ms   | ±1.65%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.821ms   | ±0.74%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.870ms   | ±0.93%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.658ms   | ±6.10%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.076ms   | ±0.52%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 7.378ms   | ±1.39%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.310ms   | ±2.44%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.389ms   | ±0.99%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 12.804ms  | ±0.91%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 4.023ms   | ±15.47% |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 11.607ms  | ±1.00%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 45.646ms  | ±1.14%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.255ms   | ±0.86%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 7.275ms   | ±1.37%  |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.849μs   | ±18.25% |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.873μs   | ±25.71% |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.860mb | 14.520ms  | ±0.73%  |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.860mb | 14.385ms  | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.375ms   | ±14.35% |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.603ms   | ±1.51%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.788ms   | ±0.83%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.847ms   | ±1.22%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 7.144ms   | ±1.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.624ms   | ±1.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.896ms   | ±1.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 12.566ms  | ±25.85% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.767ms   | ±1.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.458ms   | ±5.22%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 691.108μs | ±7.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.231ms   | ±1.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.681ms   | ±1.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 3.293ms   | ±2.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 172.328ms | ±32.33% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.725ms   | ±1.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 5.829ms   | ±21.76% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 6.094ms   | ±1.57%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.261ms  | ±1.85%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.527ms  | ±3.23%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.228ms  | ±1.48%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 21.096ms  | ±1.52%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.548ms  | ±0.83%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 818.214μs | ±2.45%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 850.980μs | ±1.58%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 932.250μs | ±2.80%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.533ms   | ±1.83%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.292ms   | ±1.06%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 26.229ms  | ±1.43%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 30.214ms  | ±1.47%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.957ms  | ±1.89%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 67.579ms  | ±0.84%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 106.335ms | ±0.27%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.558ms  | ±3.46%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.389ms  | ±1.59%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.880ms  | ±0.86%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 74.049ms  | ±0.96%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 163.684ms | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 5.080ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.488mb  | 50.580ms  | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.644μs   | ±23.33% |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.644μs   | ±23.33% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.666μs   | ±12.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 262.363ms | ±27.74% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 455.872μs | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 3.115ms   | ±1.32%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.521ms   | ±5.88%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 13.698ms  | ±5.36%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 83.006ms  | ±0.86%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.556ms  | ±1.57%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 24.653ms  | ±1.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 191.568ms | ±25.26% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 13.644ms  | ±1.25%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 13.436ms  | ±1.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 13.462ms  | ±0.58%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 13.537ms  | ±0.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 13.935ms  | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.966ms   | ±11.46% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 13.560ms  | ±0.87%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 13.601ms  | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 13.521ms  | ±0.45%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.408ms   | ±7.26%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.636ms   | ±0.84%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.965ms   | ±2.70%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.951ms   | ±0.66%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.511ms   | ±0.63%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 18.099ms  | ±2.62%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 18.672ms  | ±1.88%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 19.990ms  | ±1.62%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 29.337ms  | ±1.36%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 42.427ms  | ±1.33%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.038ms   | ±1.40%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.103ms   | ±2.44%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.264ms   | ±3.21%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.958ms   | ±4.26%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.857ms   | ±0.56%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.129mb  | 10.018ms  | ±0.18%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.546mb  | 9.929ms   | ±0.38%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.766mb  | 11.644ms  | ±0.85%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.871mb  | 11.732ms  | ±0.45%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.290mb  | 10.855ms  | ±0.87%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.428mb  | 10.265ms  | ±1.38%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.609mb | 19.303ms  | ±3.31%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.685mb  | 2.983ms   | ±1.08%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.521μs  | ±2.96%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 242.542μs | ±1.21%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.209mb  | 24.493ms  | ±0.56%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.194mb | 215.421ms | ±0.40%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.710mb | 1.068s    | ±1.27%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```