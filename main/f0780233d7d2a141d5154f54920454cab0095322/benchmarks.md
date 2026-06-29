# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-29 03:42:46 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.344ms | 2.574ms | 2.802ms | 4.835ms | 7.035ms |
| FPDF | 799.772μs | 858.750μs | 942.457μs | 1.547ms | 2.283ms |
| TCPDF | 9.906ms | 10.891ms | 12.043ms | 20.657ms | 31.699ms |
| mPDF | 25.143ms | 29.187ms | 33.087ms | 66.887ms | 107.204ms |
| Dompdf | 11.362ms | 16.281ms | 21.650ms | 74.577ms | 165.176ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.148mb | 5.938mb | 6.024mb | 6.659mb | 7.481mb |
| FPDF | 5.072mb | 5.073mb | 5.073mb | 5.073mb | 5.083mb |
| TCPDF | 12.912mb | 12.912mb | 12.912mb | 12.912mb | 12.912mb |
| mPDF | 17.624mb | 17.682mb | 17.721mb | 18.014mb | 18.375mb |
| Dompdf | 9.357mb | 9.577mb | 9.898mb | 12.591mb | 15.953mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.368ms | 3.576ms | 3.857ms | 5.916ms | 8.309ms |
| FPDF | 1.079ms | 1.110ms | 1.254ms | 1.929ms | 2.782ms |
| TCPDF | 16.870ms | 18.228ms | 19.263ms | 29.308ms | 41.780ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.365mb | 5.411mb | 5.470mb | 5.963mb | 6.562mb |
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
| Pdf (Level 3) | 3.353ms | 4.415ms | 12.816ms |
| PdfDoc (Level 2) | 2.686ms | 3.090ms | 7.539ms |
| PdfWriter (Level 1) | 2.322ms | 2.840ms | 7.003ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 5.986mb | 6.150mb | 7.826mb |
| PdfDoc (Level 2) | 5.643mb | 5.802mb | 7.370mb |
| PdfWriter (Level 1) | 5.381mb | 5.540mb | 7.115mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 4.308ms | 12.060ms | 47.031ms |
| PdfDoc (Level 2) | 3.771ms | 9.976ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.337mb | 9.133mb | 21.541mb |
| PdfDoc (Level 2) | 6.144mb | 8.959mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 4.024ms | 11.512ms | 45.105ms |
| PdfDoc (Level 2) | 3.237ms | 7.293ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.521mb | 8.965mb |
| PdfDoc (Level 2) | 5.758mb | 6.252mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.159ms | 1.694ms | 6.006ms |
| smalot/pdfparser | 2.035ms | 2.412ms | 5.801ms |
| setasign/fpdi | 1.971ms | 2.874ms | 30.042ms |

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
| phpdftk | 2.060ms | 1.386ms |
| smalot/pdfparser | FAIL | 1.970ms |
| setasign/fpdi | 3.041ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.258ms   | ±1.23%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.694ms   | ±3.14%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.006ms   | ±0.72%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.060ms   | ±0.93%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.386ms   | ±1.23%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 2.035ms   | ±0.92%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.412ms   | ±1.00%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.801ms   | ±0.25%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 561.087μs | ±1.30%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.970ms   | ±1.31%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.971ms   | ±6.95%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.874ms   | ±1.34%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 30.042ms  | ±0.67%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 3.041ms   | ±0.82%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.577ms   | ±1.86%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.951mb  | 7.332ms   | ±0.80%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.923mb  | 5.435ms   | ±1.25%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.967mb  | 3.880ms   | ±1.46%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 3.397μs   | ±18.73%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.159ms   | ±1.85%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 13.732mb | 50.719ms  | ±0.53%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 14.320mb | 105.445ms | ±0.32%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.807mb | 1.288s    | ±1.23%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.543mb | 26.416ms  | ±8.83%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 16.569mb | 59.532ms  | ±1.58%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 56.315mb | 531.561ms | ±1.36%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 28.171mb | 64.176ms  | ±10.33%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.825mb | 88.044ms  | ±1.33%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 32.249mb | 741.278ms | ±0.84%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.525mb | 18.951ms  | ±1.01%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.525mb | 43.923ms  | ±0.15%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 29.233mb | 325.936ms | ±0.47%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.337mb  | 4.308ms   | ±0.82%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 12.060ms  | ±4.20%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.541mb | 47.031ms  | ±1.06%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.771ms   | ±1.01%   |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.959mb  | 9.976ms   | ±0.87%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.943mb | 77.022ms  | ±0.69%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.716mb | 333.224ms | ±0.13%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 43.336mb | 1.306s    | ±0.34%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 26.150mb | 235.440ms | ±0.63%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 30.482mb | 183.126ms | ±1.87%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 14.729mb | 147.597ms | ±0.42%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.766mb | 199.653ms | ±0.34%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 15.389mb | 162.537ms | ±0.22%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.801mb | 312.446ms | ±0.72%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 13.468mb | 47.235ms  | ±0.82%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 13.388mb | 41.703ms  | ±0.73%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 13.318mb | 39.514ms  | ±0.50%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 14.619mb | 133.277ms | ±0.57%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 13.376mb | 43.531ms  | ±0.26%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 13.588mb | 54.561ms  | ±0.20%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 14.004mb | 80.889ms  | ±0.46%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 13.283mb | 34.779ms  | ±2.21%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 13.700mb | 42.444ms  | ±0.86%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.829mb | 205.953ms | ±0.38%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.849mb | 165.882ms | ±0.13%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.961ms   | ±0.76%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.546mb  | 8.408ms   | ±0.97%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.755mb  | 8.539ms   | ±0.72%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.322ms   | ±1.76%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.840ms   | ±1.37%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 7.003ms   | ±1.01%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.643mb  | 2.686ms   | ±2.34%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.090ms   | ±1.68%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.370mb  | 7.539ms   | ±0.82%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.986mb  | 3.353ms   | ±0.63%   |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.415ms   | ±0.80%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.826mb  | 12.816ms  | ±0.51%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 4.024ms   | ±0.44%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.521mb  | 11.512ms  | ±0.35%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.965mb  | 45.105ms  | ±2.00%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.758mb  | 3.237ms   | ±0.64%   |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.252mb  | 7.293ms   | ±0.34%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 1.959μs   | ±12.07%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 2.061μs   | ±20.20%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.930mb | 14.496ms  | ±0.88%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.930mb | 16.319ms  | ±6.75%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.306ms   | ±1.33%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.938mb  | 2.574ms   | ±1.01%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.024mb  | 2.802ms   | ±1.53%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.835ms   | ±1.13%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.481mb  | 7.035ms   | ±0.84%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.280mb  | 3.574ms   | ±1.46%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.927ms   | ±1.61%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 12.413ms  | ±17.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.323mb  | 3.750ms   | ±0.98%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.666mb  | 2.407ms   | ±1.06%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 686.399μs | ±4.28%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.189ms   | ±1.50%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.650ms   | ±0.94%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.110mb  | 3.226ms   | ±1.87%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.166mb  | 182.273ms | ±16.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.227mb  | 3.691ms   | ±0.94%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.842mb  | 5.882ms   | ±21.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.974mb  | 6.093ms   | ±0.89%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 9.906ms   | ±2.79%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 10.891ms  | ±0.87%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 12.043ms  | ±0.72%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 20.657ms  | ±1.43%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 31.699ms  | ±0.55%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 799.772μs | ±2.86%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 858.750μs | ±1.66%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 942.457μs | ±1.36%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.547ms   | ±1.03%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.283ms   | ±0.77%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.143ms  | ±1.57%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 29.187ms  | ±0.53%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 33.087ms  | ±0.80%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 66.887ms  | ±0.72%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 107.204ms | ±1.01%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.362ms  | ±0.57%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 16.281ms  | ±0.60%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 21.650ms  | ±0.71%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 74.577ms  | ±0.64%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.953mb | 165.176ms | ±0.58%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.040mb  | 5.088ms   | ±0.65%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.487mb  | 50.176ms  | ±0.96%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.656μs   | ±10.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.364μs   | ±120.33% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 254.538ms | ±24.98%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 452.251μs | ±2.87%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.454mb  | 3.043ms   | ±2.15%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.456ms   | ±1.94%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 14.294ms  | ±5.69%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 83.377ms  | ±1.22%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 14.430ms  | ±0.77%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 24.795ms  | ±1.20%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.887mb  | 203.861ms | ±23.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 13.514ms  | ±0.42%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.190mb  | 13.410ms  | ±1.40%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.199mb  | 13.454ms  | ±0.88%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.215mb  | 13.509ms  | ±0.59%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 14.059ms  | ±0.26%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.987mb  | 2.999ms   | ±1.17%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.202mb  | 13.460ms  | ±0.90%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.253mb  | 13.558ms  | ±0.32%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 13.344ms  | ±0.69%   |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.368ms   | ±7.55%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.411mb  | 3.576ms   | ±0.78%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.470mb  | 3.857ms   | ±1.45%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.963mb  | 5.916ms   | ±1.44%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.309ms   | ±1.02%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 16.870ms  | ±0.93%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 18.228ms  | ±0.13%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 19.263ms  | ±0.41%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 29.308ms  | ±0.82%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 41.780ms  | ±0.13%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.079ms   | ±1.65%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.110ms   | ±0.99%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.254ms   | ±1.25%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.929ms   | ±0.66%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.782ms   | ±0.90%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 8.127mb  | 9.988ms   | ±0.39%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.545mb  | 10.054ms  | ±1.09%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.764mb  | 11.515ms  | ±0.37%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.870mb  | 11.715ms  | ±0.68%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 9.289mb  | 10.743ms  | ±0.22%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.426mb  | 10.247ms  | ±0.83%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 10.607mb | 19.123ms  | ±0.25%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.685mb  | 2.774ms   | ±2.77%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 41.049μs  | ±0.90%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.497mb  | 242.815μs | ±0.61%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 8.227mb  | 24.413ms  | ±0.51%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 15.212mb | 219.577ms | ±1.58%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.728mb | 1.082s    | ±0.49%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```