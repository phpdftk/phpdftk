# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-08 17:55:22 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.787ms | 2.518ms | 2.722ms | 4.656ms | 6.885ms |
| FPDF | 844.231μs | 915.755μs | 1.016ms | 1.611ms | 2.324ms |
| TCPDF | 10.088ms | 10.919ms | 11.922ms | 19.169ms | 28.381ms |
| mPDF | 25.710ms | 29.058ms | 32.579ms | 60.807ms | 95.764ms |
| Dompdf | 11.204ms | 15.138ms | 20.005ms | 66.202ms | 147.918ms |

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
| phpdftk | 3.286ms | 3.521ms | 3.871ms | 5.760ms | 8.163ms |
| FPDF | 1.131ms | 1.247ms | 1.316ms | 2.000ms | 2.816ms |
| TCPDF | 14.677ms | 15.684ms | 16.682ms | 25.256ms | 35.570ms |

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
| Pdf (Level 3) | 3.326ms | 4.281ms | 12.153ms |
| PdfDoc (Level 2) | 2.616ms | 3.047ms | 7.343ms |
| PdfWriter (Level 1) | 2.277ms | 2.725ms | 6.899ms |

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
| Pdf (Level 3) | 4.265ms | 11.746ms | 45.605ms |
| PdfDoc (Level 2) | 3.665ms | 9.760ms | — |

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
| Pdf (Level 3) | 3.976ms | 11.514ms | 45.083ms |
| PdfDoc (Level 2) | 3.197ms | 7.177ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.939mb | 6.491mb | 8.935mb |
| PdfDoc (Level 2) | 5.728mb | 6.222mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.055ms | 1.664ms | 6.012ms |
| smalot/pdfparser | 2.012ms | 2.352ms | 5.479ms |
| setasign/fpdi | 1.897ms | 2.663ms | 28.381ms |

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
| phpdftk | 2.025ms | 1.338ms |
| smalot/pdfparser | FAIL | 1.910ms |
| setasign/fpdi | 2.849ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.207mb  | 1.218ms   | ±1.53%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.207mb  | 1.664ms   | ±0.68%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.564mb  | 6.012ms   | ±1.15%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.207mb  | 2.025ms   | ±0.45%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.207mb  | 1.338ms   | ±0.86%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.713mb  | 2.012ms   | ±1.09%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.814mb  | 2.352ms   | ±0.47%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.571mb  | 5.479ms   | ±0.33%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.207mb  | 554.695μs | ±0.99%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.709mb  | 1.910ms   | ±5.13%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.741mb  | 1.897ms   | ±1.00%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.741mb  | 2.663ms   | ±0.83%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.488mb  | 28.381ms  | ±1.44%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.836mb  | 2.849ms   | ±1.13%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.769mb  | 1.486ms   | ±1.17%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.986mb  | 7.091ms   | ±1.07%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.958mb  | 5.331ms   | ±0.96%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.937mb  | 3.782ms   | ±3.50%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.207mb  | 3.883μs   | ±24.89% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.310mb  | 6.055ms   | ±0.68%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.912mb  | 3.498ms   | ±0.59%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.400mb  | 5.321ms   | ±0.48%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.712mb  | 5.445ms   | ±0.18%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.680mb  | 4.639ms   | ±0.13%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.143mb  | 5.135ms   | ±1.29%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.208mb  | 4.976ms   | ±0.66%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.066mb  | 8.481ms   | ±0.42%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.618mb  | 2.838ms   | ±0.62%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.741mb | 63.632ms  | ±1.07%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.651mb | 271.409ms | ±0.33%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.271mb | 1.070s    | ±0.09%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.066mb | 197.975ms | ±0.67%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.398mb | 172.673ms | ±1.67%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.526mb | 124.683ms | ±0.34%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.564mb | 167.875ms | ±2.93%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.120mb | 133.802ms | ±1.00%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.599mb | 257.264ms | ±0.17%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.200mb | 40.226ms  | ±0.23%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.186mb | 35.190ms  | ±0.77%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.116mb | 34.249ms  | ±1.04%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.351mb | 107.463ms | ±0.14%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.108mb | 37.254ms  | ±0.47%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.386mb | 46.781ms  | ±1.10%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.802mb | 67.810ms  | ±0.23%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.081mb | 29.818ms  | ±0.28%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.635mb | 37.985ms  | ±0.71%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.763mb | 166.466ms | ±0.31%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.647mb | 109.600ms | ±0.23%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.464mb | 43.679ms  | ±0.41%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.061mb | 90.817ms  | ±0.60%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.742mb | 1.024s    | ±1.62%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.768mb | 23.659ms  | ±2.84%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.728mb | 51.553ms  | ±1.25%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.539mb | 469.966ms | ±0.72%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.395mb | 63.247ms  | ±10.58% |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.049mb | 83.040ms  | ±1.10%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.818mb | 655.466ms | ±0.85%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.750mb | 18.003ms  | ±1.12%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.750mb | 41.045ms  | ±0.57%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.392mb | 278.144ms | ±1.29%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.307mb  | 4.265ms   | ±0.59%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.102mb  | 11.746ms  | ±2.22%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.511mb | 45.605ms  | ±0.28%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.113mb  | 3.665ms   | ±1.38%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.929mb  | 9.760ms   | ±1.06%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.701mb  | 8.736ms   | ±0.93%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.516mb  | 8.266ms   | ±0.52%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.725mb  | 8.386ms   | ±0.36%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.334mb  | 3.286ms   | ±0.45%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.381mb  | 3.521ms   | ±2.43%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.440mb  | 3.871ms   | ±11.91% |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.933mb  | 5.760ms   | ±0.55%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.531mb  | 8.163ms   | ±0.22%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.456mb | 14.677ms  | ±0.54%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.456mb | 15.684ms  | ±0.77%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.456mb | 16.682ms  | ±0.43%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.456mb | 25.256ms  | ±0.60%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.457mb | 35.570ms  | ±0.25%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.378mb  | 1.131ms   | ±0.66%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.378mb  | 1.247ms   | ±0.75%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.378mb  | 1.316ms   | ±0.77%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.407mb  | 2.000ms   | ±0.69%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.467mb  | 2.816ms   | ±0.40%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.939mb  | 3.976ms   | ±0.86%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.491mb  | 11.514ms  | ±3.39%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.935mb  | 45.083ms  | ±0.71%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.728mb  | 3.197ms   | ±0.40%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.222mb  | 7.177ms   | ±1.06%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.206mb  | 43.343μs  | ±0.56%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.467mb  | 235.595μs | ±0.96%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.350mb  | 2.277ms   | ±0.91%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.509mb  | 2.725ms   | ±1.44%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.084mb  | 6.899ms   | ±0.32%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.613mb  | 2.616ms   | ±1.39%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.771mb  | 3.047ms   | ±1.84%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.340mb  | 7.343ms   | ±0.74%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.956mb  | 3.326ms   | ±11.88% |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.119mb  | 4.281ms   | ±2.03%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.796mb  | 12.153ms  | ±7.74%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.853mb  | 20.247ms  | ±0.96%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.839mb | 183.006ms | ±0.48%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.354mb | 897.813ms | ±0.95%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.847mb  | 2.277ms   | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.908mb  | 2.518ms   | ±1.67%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.994mb  | 2.722ms   | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.628mb  | 4.656ms   | ±0.86%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.451mb  | 6.885ms   | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.250mb  | 3.397ms   | ±0.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.303mb  | 3.725ms   | ±0.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.732mb  | 12.955ms  | ±13.31% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.293mb  | 3.599ms   | ±0.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.636mb  | 2.363ms   | ±0.82%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.616mb  | 589.462μs | ±2.53%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.065mb  | 2.978ms   | ±0.77%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.160mb  | 3.513ms   | ±0.92%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.080mb  | 3.154ms   | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.136mb  | 230.372ms | ±20.07% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.197mb  | 3.487ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.808mb  | 5.757ms   | ±24.14% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.944mb  | 5.948ms   | ±0.36%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.881mb | 10.088ms  | ±0.76%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.881mb | 10.919ms  | ±0.29%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.881mb | 11.922ms  | ±0.11%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.881mb | 19.169ms  | ±0.34%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.881mb | 28.381ms  | ±0.72%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.043mb  | 844.231μs | ±2.15%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.043mb  | 915.755μs | ±0.80%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.043mb  | 1.016ms   | ±0.78%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.043mb  | 1.611ms   | ±0.38%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.046mb  | 2.324ms   | ±0.39%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.593mb | 25.710ms  | ±2.69%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.652mb | 29.058ms  | ±1.44%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.691mb | 32.579ms  | ±0.34%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.983mb | 60.807ms  | ±0.60%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.345mb | 95.764ms  | ±0.37%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.327mb  | 11.204ms  | ±0.94%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.547mb  | 15.138ms  | ±0.36%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.868mb  | 20.005ms  | ±0.48%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.560mb | 66.202ms  | ±0.44%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.923mb | 147.918ms | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.010mb  | 4.945ms   | ±0.57%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.447mb  | 53.185ms  | ±1.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.616mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.616mb  | 1.665μs   | ±17.39% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.616mb  | 1.656μs   | ±10.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.028mb  | 200.535ms | ±13.95% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.616mb  | 468.930μs | ±3.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.424mb  | 2.910ms   | ±0.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.021mb  | 3.194ms   | ±0.57%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.616mb  | 12.166ms  | ±6.68%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.616mb  | 80.983ms  | ±1.22%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.616mb  | 15.244ms  | ±2.25%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.616mb  | 26.073ms  | ±1.17%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.849mb  | 328.713ms | ±28.15% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.179mb  | 13.936ms  | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.152mb  | 13.935ms  | ±0.47%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.152mb  | 14.087ms  | ±0.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.176mb  | 13.912ms  | ±0.54%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.281mb  | 15.017ms  | ±1.76%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.957mb  | 2.864ms   | ±0.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.155mb  | 14.069ms  | ±0.22%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.214mb  | 14.098ms  | ±1.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.109mb  | 13.787ms  | ±0.69%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```