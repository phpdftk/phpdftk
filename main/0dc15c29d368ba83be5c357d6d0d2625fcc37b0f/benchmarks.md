# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-08 12:40:39 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.769ms | 2.520ms | 2.754ms | 4.745ms | 6.929ms |
| FPDF | 839.667μs | 935.558μs | 1.035ms | 1.622ms | 2.323ms |
| TCPDF | 10.282ms | 11.170ms | 12.060ms | 19.751ms | 28.464ms |
| mPDF | 26.111ms | 29.852ms | 33.378ms | 60.648ms | 95.261ms |
| Dompdf | 11.166ms | 15.172ms | 20.103ms | 66.436ms | 148.455ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.109mb | 5.908mb | 5.994mb | 6.628mb | 7.451mb |
| FPDF | 5.043mb | 5.043mb | 5.043mb | 5.043mb | 5.046mb |
| TCPDF | 12.881mb | 12.881mb | 12.881mb | 12.881mb | 12.881mb |
| mPDF | 17.593mb | 17.652mb | 17.691mb | 17.983mb | 18.345mb |
| Dompdf | 9.327mb | 9.547mb | 9.868mb | 12.560mb | 15.923mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.413ms | 3.624ms | 3.853ms | 5.807ms | 8.384ms |
| FPDF | 1.145ms | 1.219ms | 1.344ms | 2.021ms | 2.823ms |
| TCPDF | 14.965ms | 15.969ms | 17.068ms | 25.720ms | 36.721ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.334mb | 5.381mb | 5.440mb | 5.933mb | 6.531mb |
| FPDF | 4.378mb | 4.378mb | 4.378mb | 4.407mb | 4.467mb |
| TCPDF | 12.456mb | 12.456mb | 12.456mb | 12.456mb | 12.457mb |

## Writer Levels Comparison — `WriterLevelsBench`

Same workload (N pages with heading + body text) rendered through each
writer level, so the abstraction overhead is visible directly. Lower is
better; the higher-level APIs (`Pdf` → `PdfDoc` → `PdfWriter`) trade
some performance for ergonomics.

### Generation Time

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 3.287ms | 4.279ms | 12.128ms |
| PdfDoc (Level 2) | 2.578ms | 3.145ms | 7.339ms |
| PdfWriter (Level 1) | 2.280ms | 2.765ms | 6.922ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 5.956mb | 6.119mb | 7.796mb |
| PdfDoc (Level 2) | 5.613mb | 5.771mb | 7.340mb |
| PdfWriter (Level 1) | 5.350mb | 5.509mb | 7.084mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 4.310ms | 11.652ms | 45.343ms |
| PdfDoc (Level 2) | 3.655ms | 9.854ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.307mb | 9.102mb | 21.511mb |
| PdfDoc (Level 2) | 6.113mb | 8.929mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 4.003ms | 11.475ms | 45.233ms |
| PdfDoc (Level 2) | 3.245ms | 7.257ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.939mb | 6.491mb | 8.935mb |
| PdfDoc (Level 2) | 5.728mb | 6.222mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.163ms | 1.667ms | 6.193ms |
| smalot/pdfparser | 2.079ms | 2.355ms | 5.537ms |
| setasign/fpdi | 1.886ms | 2.677ms | 28.269ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.310mb | 4.207mb | 4.564mb |
| smalot/pdfparser | 4.713mb | 4.814mb | 6.571mb |
| setasign/fpdi | 4.741mb | 4.741mb | 5.488mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 2.057ms | 1.358ms |
| smalot/pdfparser | FAIL | 1.922ms |
| setasign/fpdi | 2.887ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.207mb  | 1.252ms   | ±0.94%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.207mb  | 1.667ms   | ±1.05%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.564mb  | 6.193ms   | ±1.48%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.207mb  | 2.057ms   | ±1.29%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.207mb  | 1.358ms   | ±2.12%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.713mb  | 2.079ms   | ±4.29%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.814mb  | 2.355ms   | ±1.71%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.571mb  | 5.537ms   | ±0.59%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.207mb  | 561.599μs | ±1.16%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.709mb  | 1.922ms   | ±0.64%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.741mb  | 1.886ms   | ±2.18%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.741mb  | 2.677ms   | ±0.78%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.488mb  | 28.269ms  | ±0.85%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.836mb  | 2.887ms   | ±1.19%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.769mb  | 1.500ms   | ±0.99%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.986mb  | 7.117ms   | ±1.95%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.958mb  | 5.397ms   | ±0.47%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.937mb  | 3.842ms   | ±0.84%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.207mb  | 3.844μs   | ±24.34% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.310mb  | 6.163ms   | ±0.99%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.912mb  | 3.551ms   | ±3.48%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.400mb  | 5.454ms   | ±1.48%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.712mb  | 5.498ms   | ±0.72%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.680mb  | 4.694ms   | ±1.34%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.143mb  | 5.108ms   | ±1.14%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.208mb  | 5.036ms   | ±0.68%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.066mb  | 8.512ms   | ±1.61%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.618mb  | 2.860ms   | ±2.79%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.741mb | 64.141ms  | ±0.95%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.651mb | 272.617ms | ±0.59%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.271mb | 1.061s    | ±0.98%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.066mb | 198.862ms | ±0.30%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.398mb | 170.956ms | ±0.76%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.526mb | 124.308ms | ±0.54%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.564mb | 166.374ms | ±0.39%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.120mb | 133.158ms | ±0.27%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.599mb | 252.902ms | ±0.96%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.200mb | 40.374ms  | ±1.74%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.186mb | 35.315ms  | ±2.71%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.116mb | 37.253ms  | ±3.68%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.351mb | 109.477ms | ±1.34%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.108mb | 37.493ms  | ±1.13%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.386mb | 47.074ms  | ±1.29%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.802mb | 69.189ms  | ±0.87%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.081mb | 30.363ms  | ±1.10%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.635mb | 38.644ms  | ±0.69%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.763mb | 168.200ms | ±0.21%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.647mb | 110.661ms | ±0.42%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.464mb | 45.061ms  | ±0.97%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.061mb | 90.418ms  | ±0.44%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.742mb | 1.014s    | ±0.71%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.768mb | 25.157ms  | ±1.16%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.728mb | 52.967ms  | ±2.58%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.539mb | 507.235ms | ±3.43%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.395mb | 62.677ms  | ±9.54%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.049mb | 82.907ms  | ±0.69%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.818mb | 659.909ms | ±0.42%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.750mb | 17.712ms  | ±0.84%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.750mb | 41.405ms  | ±0.19%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.392mb | 275.866ms | ±0.16%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.307mb  | 4.310ms   | ±0.75%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.102mb  | 11.652ms  | ±0.87%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.511mb | 45.343ms  | ±0.51%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.113mb  | 3.655ms   | ±1.64%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.929mb  | 9.854ms   | ±0.68%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.701mb  | 8.775ms   | ±0.68%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.516mb  | 8.271ms   | ±0.60%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.725mb  | 8.393ms   | ±0.59%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.334mb  | 3.413ms   | ±0.87%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.381mb  | 3.624ms   | ±0.71%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.440mb  | 3.853ms   | ±0.89%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.933mb  | 5.807ms   | ±0.90%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.531mb  | 8.384ms   | ±1.12%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.456mb | 14.965ms  | ±2.22%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.456mb | 15.969ms  | ±1.64%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.456mb | 17.068ms  | ±1.03%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.456mb | 25.720ms  | ±0.16%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.457mb | 36.721ms  | ±8.28%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.378mb  | 1.145ms   | ±1.48%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.378mb  | 1.219ms   | ±1.40%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.378mb  | 1.344ms   | ±2.27%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.407mb  | 2.021ms   | ±0.57%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.467mb  | 2.823ms   | ±0.81%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.939mb  | 4.003ms   | ±1.13%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.491mb  | 11.475ms  | ±0.64%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.935mb  | 45.233ms  | ±1.96%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.728mb  | 3.245ms   | ±0.87%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.222mb  | 7.257ms   | ±0.85%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.206mb  | 42.406μs  | ±1.75%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.467mb  | 237.789μs | ±0.64%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.350mb  | 2.280ms   | ±0.94%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.509mb  | 2.765ms   | ±0.32%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.084mb  | 6.922ms   | ±0.47%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.613mb  | 2.578ms   | ±1.64%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.771mb  | 3.145ms   | ±4.93%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.340mb  | 7.339ms   | ±0.49%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.956mb  | 3.287ms   | ±0.88%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.119mb  | 4.279ms   | ±0.33%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.796mb  | 12.128ms  | ±0.59%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.853mb  | 20.313ms  | ±0.48%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.839mb | 178.023ms | ±0.94%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.354mb | 884.858ms | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.847mb  | 2.274ms   | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.908mb  | 2.520ms   | ±0.70%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.994mb  | 2.754ms   | ±0.66%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.628mb  | 4.745ms   | ±0.79%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.451mb  | 6.929ms   | ±0.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.250mb  | 3.457ms   | ±1.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.303mb  | 3.759ms   | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.732mb  | 13.037ms  | ±18.23% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.293mb  | 3.621ms   | ±1.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.636mb  | 2.403ms   | ±0.90%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.616mb  | 597.099μs | ±2.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.065mb  | 2.987ms   | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.160mb  | 3.571ms   | ±0.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.080mb  | 3.175ms   | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.136mb  | 267.247ms | ±18.11% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.197mb  | 3.485ms   | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.808mb  | 5.819ms   | ±15.80% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.944mb  | 6.053ms   | ±1.06%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.881mb | 10.282ms  | ±2.29%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.881mb | 11.170ms  | ±2.47%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.881mb | 12.060ms  | ±1.92%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.881mb | 19.751ms  | ±0.58%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.881mb | 28.464ms  | ±0.44%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.043mb  | 839.667μs | ±2.10%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.043mb  | 935.558μs | ±0.98%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.043mb  | 1.035ms   | ±1.03%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.043mb  | 1.622ms   | ±0.91%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.046mb  | 2.323ms   | ±1.22%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.593mb | 26.111ms  | ±2.42%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.652mb | 29.852ms  | ±0.34%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.691mb | 33.378ms  | ±0.65%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.983mb | 60.648ms  | ±0.59%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.345mb | 95.261ms  | ±0.86%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.327mb  | 11.166ms  | ±0.53%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.547mb  | 15.172ms  | ±0.59%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.868mb  | 20.103ms  | ±1.28%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.560mb | 66.436ms  | ±0.38%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.923mb | 148.455ms | ±0.90%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.010mb  | 4.908ms   | ±0.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.447mb  | 54.227ms  | ±0.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.616mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.616mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.616mb  | 1.667μs   | ±7.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.028mb  | 300.730ms | ±19.97% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.616mb  | 479.247μs | ±0.99%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.424mb  | 2.908ms   | ±0.05%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.021mb  | 3.197ms   | ±0.68%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.616mb  | 11.533ms  | ±4.11%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.616mb  | 81.204ms  | ±1.71%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.616mb  | 14.839ms  | ±1.50%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.616mb  | 26.153ms  | ±0.88%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.849mb  | 246.409ms | ±2.95%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.179mb  | 14.116ms  | ±1.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.152mb  | 13.816ms  | ±0.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.152mb  | 13.918ms  | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.176mb  | 14.096ms  | ±1.10%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.281mb  | 14.521ms  | ±0.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.957mb  | 2.885ms   | ±1.19%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.155mb  | 13.930ms  | ±0.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.214mb  | 14.059ms  | ±1.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.109mb  | 13.769ms  | ±0.93%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```