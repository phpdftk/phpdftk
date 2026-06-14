# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-14 13:14:55 UTC
PHP: 8.4.22
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.767ms | 2.443ms | 2.668ms | 4.682ms | 6.885ms |
| FPDF | 802.374μs | 843.836μs | 919.198μs | 1.521ms | 2.277ms |
| TCPDF | 10.046ms | 11.006ms | 11.978ms | 19.276ms | 28.120ms |
| mPDF | 25.597ms | 28.967ms | 32.531ms | 60.308ms | 94.656ms |
| Dompdf | 11.144ms | 15.160ms | 20.028ms | 67.132ms | 150.147ms |

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
| phpdftk | 3.335ms | 3.539ms | 3.787ms | 5.736ms | 8.163ms |
| FPDF | 1.042ms | 1.131ms | 1.240ms | 1.929ms | 2.713ms |
| TCPDF | 17.362ms | 18.286ms | 19.218ms | 27.644ms | 37.823ms |

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
| Pdf (Level 3) | 3.230ms | 4.248ms | 12.223ms |
| PdfDoc (Level 2) | 2.603ms | 3.057ms | 7.358ms |
| PdfWriter (Level 1) | 2.277ms | 2.716ms | 6.797ms |

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
| Pdf (Level 3) | 4.229ms | 11.681ms | 45.592ms |
| PdfDoc (Level 2) | 3.623ms | 9.785ms | — |

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
| Pdf (Level 3) | 3.960ms | 11.436ms | 45.527ms |
| PdfDoc (Level 2) | 3.156ms | 7.166ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.970mb | 6.522mb | 8.966mb |
| PdfDoc (Level 2) | 5.759mb | 6.253mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.082ms | 1.655ms | 6.065ms |
| smalot/pdfparser | 2.006ms | 2.341ms | 5.559ms |
| setasign/fpdi | 1.870ms | 2.700ms | 28.817ms |

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
| phpdftk | 2.017ms | 1.331ms |
| smalot/pdfparser | FAIL | 1.935ms |
| setasign/fpdi | 2.853ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.243mb  | 1.204ms   | ±1.40%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.243mb  | 1.655ms   | ±0.96%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.594mb  | 6.065ms   | ±0.32%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.243mb  | 2.017ms   | ±0.22%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.243mb  | 1.331ms   | ±0.68%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.807mb  | 2.006ms   | ±1.05%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.891mb  | 2.341ms   | ±2.90%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.601mb  | 5.559ms   | ±0.61%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.243mb  | 556.701μs | ±1.37%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.801mb  | 1.935ms   | ±0.84%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.742mb  | 1.870ms   | ±1.28%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.769mb  | 2.700ms   | ±1.34%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.526mb  | 28.817ms  | ±0.29%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.874mb  | 2.853ms   | ±0.35%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.670mb  | 1.500ms   | ±1.23%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.952mb  | 7.092ms   | ±0.56%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.924mb  | 5.335ms   | ±0.87%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.968mb  | 3.777ms   | ±0.71%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.243mb  | 4.079μs   | ±40.66%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.341mb  | 6.082ms   | ±0.54%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.922mb | 44.706ms  | ±0.30%   |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.510mb | 90.694ms  | ±0.19%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 40.161mb | 1.041s    | ±0.51%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 14.032mb | 24.008ms  | ±2.68%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.992mb | 52.831ms  | ±1.20%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.804mb | 477.536ms | ±0.70%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.660mb | 64.138ms  | ±8.85%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.313mb | 82.923ms  | ±1.51%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 31.082mb | 663.469ms | ±0.72%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 16.014mb | 17.734ms  | ±0.78%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 16.014mb | 40.950ms  | ±0.32%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.656mb | 278.507ms | ±0.56%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.338mb  | 4.229ms   | ±13.90%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.133mb  | 11.681ms  | ±0.70%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.542mb | 45.592ms  | ±0.94%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.144mb  | 3.623ms   | ±0.30%   |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.960mb  | 9.785ms   | ±0.21%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 13.199mb | 64.302ms  | ±0.33%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 19.005mb | 274.109ms | ±0.22%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.625mb | 1.088s    | ±2.01%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.439mb | 199.601ms | ±0.64%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.770mb | 171.077ms | ±0.05%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.986mb | 125.915ms | ±0.35%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 15.022mb | 168.884ms | ±0.27%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.578mb | 133.241ms | ±0.35%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 17.058mb | 263.100ms | ±1.16%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.724mb | 40.381ms  | ±0.57%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.644mb | 35.465ms  | ±0.77%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.574mb | 34.878ms  | ±0.01%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.875mb | 109.660ms | ±0.32%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.632mb | 37.305ms  | ±0.10%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.845mb | 46.761ms  | ±0.36%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 13.260mb | 67.747ms  | ±0.21%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.539mb | 30.063ms  | ±0.08%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.989mb | 38.146ms  | ±0.12%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 16.117mb | 171.132ms | ±5.01%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 13.105mb | 110.579ms | ±0.50%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.732mb  | 8.702ms   | ±0.49%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.547mb  | 8.210ms   | ±0.86%   |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.756mb  | 8.295ms   | ±0.72%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.381mb  | 2.277ms   | ±4.81%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.540mb  | 2.716ms   | ±0.36%   |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.115mb  | 6.797ms   | ±0.70%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.644mb  | 2.603ms   | ±0.53%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.802mb  | 3.057ms   | ±0.31%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.371mb  | 7.358ms   | ±0.76%   |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.987mb  | 3.230ms   | ±0.77%   |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.150mb  | 4.248ms   | ±0.37%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.827mb  | 12.223ms  | ±0.54%   |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.970mb  | 3.960ms   | ±0.51%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.522mb  | 11.436ms  | ±0.72%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.966mb  | 45.527ms  | ±1.77%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.759mb  | 3.156ms   | ±0.28%   |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.253mb  | 7.166ms   | ±1.14%   |
| FontFaceLoadBench           | benchLoadOpenTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 2.049μs   | ±16.64%  |
| FontFaceLoadBench           | benchLoadTrueTypeFromBytes                       |     | 5    | 3   | 4.242mb  | 2.121μs   | ±35.36%  |
| FontFaceLoadBench           | benchOpenTypeFontFaceRender                      |     | 5    | 3   | 12.186mb | 13.381ms  | ±0.27%   |
| FontFaceLoadBench           | benchTrueTypeFontFaceRender                      |     | 5    | 3   | 12.186mb | 13.384ms  | ±0.50%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.878mb  | 2.249ms   | ±0.75%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.939mb  | 2.443ms   | ±0.50%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 6.025mb  | 2.668ms   | ±0.72%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.659mb  | 4.682ms   | ±0.27%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.482mb  | 6.885ms   | ±0.70%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.281mb  | 3.400ms   | ±0.36%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.334mb  | 3.717ms   | ±0.44%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.771mb  | 12.899ms  | ±1.56%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.324mb  | 3.605ms   | ±0.79%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.667mb  | 2.308ms   | ±0.80%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.646mb  | 582.234μs | ±4.67%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.096mb  | 3.081ms   | ±0.74%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.190mb  | 3.516ms   | ±0.64%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.111mb  | 3.121ms   | ±0.58%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.167mb  | 233.708ms | ±32.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.228mb  | 3.488ms   | ±0.56%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.843mb  | 5.711ms   | ±19.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.975mb  | 5.949ms   | ±0.50%   |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.912mb | 10.046ms  | ±1.23%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.912mb | 11.006ms  | ±0.83%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.912mb | 11.978ms  | ±0.56%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.912mb | 19.276ms  | ±0.27%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.912mb | 28.120ms  | ±2.23%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.072mb  | 802.374μs | ±117.66% |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.073mb  | 843.836μs | ±1.40%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.073mb  | 919.198μs | ±2.08%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.073mb  | 1.521ms   | ±0.61%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.083mb  | 2.277ms   | ±1.09%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.624mb | 25.597ms  | ±2.02%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.682mb | 28.967ms  | ±0.09%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.721mb | 32.531ms  | ±0.49%   |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 18.014mb | 60.308ms  | ±0.47%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.375mb | 94.656ms  | ±0.28%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.357mb  | 11.144ms  | ±0.61%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.577mb  | 15.160ms  | ±0.34%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.898mb  | 20.028ms  | ±0.47%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.591mb | 67.132ms  | ±0.68%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.954mb | 150.147ms | ±1.03%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.041mb  | 4.941ms   | ±0.40%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.486mb  | 54.014ms  | ±0.70%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.646mb  | 1.667μs   | ±0.00%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.646mb  | 1.666μs   | ±8.33%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.058mb  | 231.014ms | ±24.04%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.646mb  | 471.617μs | ±2.19%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.455mb  | 2.926ms   | ±0.47%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.052mb  | 3.313ms   | ±0.14%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.646mb  | 10.985ms  | ±6.31%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.646mb  | 82.982ms  | ±2.86%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.646mb  | 16.846ms  | ±5.43%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.646mb  | 26.251ms  | ±0.96%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.888mb  | 240.810ms | ±16.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.218mb  | 14.014ms  | ±1.09%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.191mb  | 13.714ms  | ±0.81%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.200mb  | 14.077ms  | ±0.86%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.216mb  | 14.034ms  | ±3.59%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.320mb  | 14.336ms  | ±0.37%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.988mb  | 2.818ms   | ±1.14%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.203mb  | 14.018ms  | ±65.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.254mb  | 14.024ms  | ±3.59%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.148mb  | 13.767ms  | ±0.83%   |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.365mb  | 3.335ms   | ±0.50%   |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.412mb  | 3.539ms   | ±1.27%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.471mb  | 3.787ms   | ±1.46%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.964mb  | 5.736ms   | ±1.15%   |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.562mb  | 8.163ms   | ±0.85%   |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.487mb | 17.362ms  | ±1.53%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.487mb | 18.286ms  | ±1.09%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.487mb | 19.218ms  | ±0.17%   |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.487mb | 27.644ms  | ±0.74%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.487mb | 37.823ms  | ±0.22%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.455mb  | 1.042ms   | ±1.22%   |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.455mb  | 1.131ms   | ±1.25%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.455mb  | 1.240ms   | ±0.60%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.455mb  | 1.929ms   | ±0.15%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.504mb  | 2.713ms   | ±0.40%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 7.770mb  | 8.467ms   | ±0.63%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 8.252mb  | 8.859ms   | ±1.19%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 8.494mb  | 9.954ms   | ±0.12%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 8.518mb  | 9.970ms   | ±0.56%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 8.996mb  | 9.368ms   | ±0.18%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 8.134mb  | 8.833ms   | ±0.02%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 9.987mb  | 15.586ms  | ±0.58%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.665mb  | 2.806ms   | ±0.52%   |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.242mb  | 43.653μs  | ±1.06%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.498mb  | 233.001μs | ±0.75%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.919mb  | 20.644ms  | ±0.49%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.904mb | 180.858ms | ±1.05%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.420mb | 902.646ms | ±0.84%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```