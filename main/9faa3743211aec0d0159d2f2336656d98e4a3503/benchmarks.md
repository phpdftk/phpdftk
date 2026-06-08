# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-08 17:39:52 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.701ms | 2.562ms | 2.697ms | 4.716ms | 6.848ms |
| FPDF | 833.783μs | 953.074μs | 1.019ms | 1.612ms | 2.317ms |
| TCPDF | 10.103ms | 10.962ms | 11.854ms | 19.094ms | 28.157ms |
| mPDF | 25.859ms | 29.264ms | 32.808ms | 60.014ms | 94.863ms |
| Dompdf | 11.147ms | 15.216ms | 19.937ms | 66.026ms | 148.585ms |

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
| phpdftk | 3.306ms | 3.499ms | 3.784ms | 5.729ms | 8.266ms |
| FPDF | 1.135ms | 1.202ms | 1.304ms | 1.977ms | 2.796ms |
| TCPDF | 14.670ms | 15.497ms | 16.704ms | 25.220ms | 35.450ms |

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
| Pdf (Level 3) | 3.246ms | 4.273ms | 12.191ms |
| PdfDoc (Level 2) | 2.623ms | 3.039ms | 7.303ms |
| PdfWriter (Level 1) | 2.301ms | 2.737ms | 6.940ms |

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
| Pdf (Level 3) | 4.253ms | 11.606ms | 45.059ms |
| PdfDoc (Level 2) | 3.682ms | 9.763ms | — |

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
| Pdf (Level 3) | 3.979ms | 11.496ms | 44.815ms |
| PdfDoc (Level 2) | 3.198ms | 7.275ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.939mb | 6.491mb | 8.935mb |
| PdfDoc (Level 2) | 5.728mb | 6.222mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.066ms | 1.669ms | 6.105ms |
| smalot/pdfparser | 1.994ms | 2.337ms | 5.489ms |
| setasign/fpdi | 1.873ms | 2.680ms | 28.173ms |

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
| phpdftk | 2.010ms | 1.355ms |
| smalot/pdfparser | FAIL | 1.893ms |
| setasign/fpdi | 2.813ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.207mb  | 1.237ms   | ±1.82%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.207mb  | 1.669ms   | ±0.76%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.564mb  | 6.105ms   | ±0.62%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.207mb  | 2.010ms   | ±0.34%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.207mb  | 1.355ms   | ±1.27%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.713mb  | 1.994ms   | ±2.48%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.814mb  | 2.337ms   | ±1.29%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.571mb  | 5.489ms   | ±1.37%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.207mb  | 549.965μs | ±1.32%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.709mb  | 1.893ms   | ±1.13%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.741mb  | 1.873ms   | ±0.48%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.741mb  | 2.680ms   | ±0.75%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.488mb  | 28.173ms  | ±1.98%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.836mb  | 2.813ms   | ±0.55%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.769mb  | 1.507ms   | ±1.48%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.986mb  | 7.077ms   | ±1.13%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.958mb  | 5.323ms   | ±0.20%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.937mb  | 3.798ms   | ±0.53%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.207mb  | 3.697μs   | ±18.25% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.310mb  | 6.066ms   | ±0.75%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.912mb  | 3.579ms   | ±0.46%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.400mb  | 5.392ms   | ±0.30%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.712mb  | 5.527ms   | ±0.44%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.680mb  | 4.721ms   | ±0.30%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.143mb  | 5.154ms   | ±0.48%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.208mb  | 5.030ms   | ±0.72%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.066mb  | 8.511ms   | ±0.14%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.618mb  | 2.832ms   | ±0.68%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.741mb | 63.431ms  | ±0.63%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.651mb | 271.153ms | ±1.30%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.271mb | 1.048s    | ±1.71%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.066mb | 196.358ms | ±0.37%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.398mb | 168.589ms | ±0.50%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.526mb | 124.391ms | ±3.91%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.564mb | 169.361ms | ±1.01%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.120mb | 131.796ms | ±1.44%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.599mb | 257.804ms | ±0.34%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.200mb | 39.709ms  | ±0.34%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.186mb | 34.926ms  | ±0.17%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.116mb | 34.000ms  | ±0.32%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.351mb | 107.752ms | ±0.21%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.108mb | 36.965ms  | ±0.29%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.386mb | 46.252ms  | ±0.82%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.802mb | 69.410ms  | ±1.69%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.081mb | 29.576ms  | ±0.45%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.635mb | 37.385ms  | ±0.09%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.763mb | 165.350ms | ±0.21%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.647mb | 109.708ms | ±0.95%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.464mb | 43.470ms  | ±0.12%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.061mb | 88.566ms  | ±0.37%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.742mb | 1.008s    | ±0.97%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.768mb | 23.768ms  | ±1.94%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.728mb | 51.625ms  | ±0.84%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.539mb | 453.521ms | ±0.73%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.395mb | 63.114ms  | ±9.57%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.049mb | 82.362ms  | ±1.02%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.818mb | 655.174ms | ±0.08%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.750mb | 17.633ms  | ±0.61%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.750mb | 40.687ms  | ±0.91%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.392mb | 275.370ms | ±1.91%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.307mb  | 4.253ms   | ±0.56%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.102mb  | 11.606ms  | ±1.37%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.511mb | 45.059ms  | ±0.66%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.113mb  | 3.682ms   | ±0.56%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.929mb  | 9.763ms   | ±2.35%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.701mb  | 8.704ms   | ±0.48%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.516mb  | 8.253ms   | ±0.94%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.725mb  | 8.345ms   | ±0.91%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.334mb  | 3.306ms   | ±0.92%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.381mb  | 3.499ms   | ±0.68%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.440mb  | 3.784ms   | ±1.06%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.933mb  | 5.729ms   | ±0.19%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.531mb  | 8.266ms   | ±0.67%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.456mb | 14.670ms  | ±0.33%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.456mb | 15.497ms  | ±0.48%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.456mb | 16.704ms  | ±0.52%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.456mb | 25.220ms  | ±0.85%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.457mb | 35.450ms  | ±2.51%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.378mb  | 1.135ms   | ±1.60%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.378mb  | 1.202ms   | ±1.17%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.378mb  | 1.304ms   | ±0.70%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.407mb  | 1.977ms   | ±0.77%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.467mb  | 2.796ms   | ±0.78%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.939mb  | 3.979ms   | ±0.42%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.491mb  | 11.496ms  | ±0.66%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.935mb  | 44.815ms  | ±1.22%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.728mb  | 3.198ms   | ±1.40%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.222mb  | 7.275ms   | ±0.21%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.206mb  | 42.836μs  | ±1.74%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.467mb  | 237.808μs | ±0.43%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.350mb  | 2.301ms   | ±0.91%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.509mb  | 2.737ms   | ±0.60%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.084mb  | 6.940ms   | ±0.30%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.613mb  | 2.623ms   | ±1.15%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.771mb  | 3.039ms   | ±0.94%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.340mb  | 7.303ms   | ±0.42%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.956mb  | 3.246ms   | ±0.85%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.119mb  | 4.273ms   | ±0.74%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.796mb  | 12.191ms  | ±0.95%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.853mb  | 20.079ms  | ±0.76%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.839mb | 178.850ms | ±1.26%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.354mb | 881.585ms | ±0.40%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.847mb  | 2.271ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.908mb  | 2.562ms   | ±1.90%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.994mb  | 2.697ms   | ±6.14%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.628mb  | 4.716ms   | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.451mb  | 6.848ms   | ±0.41%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.250mb  | 3.410ms   | ±0.49%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.303mb  | 3.796ms   | ±0.89%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.732mb  | 13.205ms  | ±50.26% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.293mb  | 3.617ms   | ±0.63%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.636mb  | 2.310ms   | ±1.41%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.616mb  | 605.855μs | ±3.03%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.065mb  | 2.955ms   | ±0.29%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.160mb  | 3.537ms   | ±0.62%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.080mb  | 3.119ms   | ±0.42%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.136mb  | 231.146ms | ±36.09% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.197mb  | 3.489ms   | ±0.48%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.808mb  | 5.800ms   | ±86.16% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.944mb  | 5.928ms   | ±0.54%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.881mb | 10.103ms  | ±0.83%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.881mb | 10.962ms  | ±0.78%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.881mb | 11.854ms  | ±0.85%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.881mb | 19.094ms  | ±0.47%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.881mb | 28.157ms  | ±0.47%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.043mb  | 833.783μs | ±1.74%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.043mb  | 953.074μs | ±26.10% |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.043mb  | 1.019ms   | ±1.48%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.043mb  | 1.612ms   | ±0.41%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.046mb  | 2.317ms   | ±0.49%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.593mb | 25.859ms  | ±1.49%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.652mb | 29.264ms  | ±2.25%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.691mb | 32.808ms  | ±1.64%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.983mb | 60.014ms  | ±0.41%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.345mb | 94.863ms  | ±0.19%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.327mb  | 11.147ms  | ±0.62%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.547mb  | 15.216ms  | ±8.17%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.868mb  | 19.937ms  | ±0.41%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.560mb | 66.026ms  | ±1.14%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.923mb | 148.585ms | ±1.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.010mb  | 4.935ms   | ±0.67%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.447mb  | 54.676ms  | ±0.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.616mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.616mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.616mb  | 1.678μs   | ±9.07%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.028mb  | 212.766ms | ±15.31% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.616mb  | 479.215μs | ±2.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.424mb  | 2.924ms   | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.021mb  | 3.215ms   | ±0.77%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.616mb  | 12.239ms  | ±6.46%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.616mb  | 83.274ms  | ±1.56%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.616mb  | 15.234ms  | ±1.86%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.616mb  | 25.979ms  | ±1.12%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.849mb  | 188.601ms | ±21.38% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.179mb  | 14.125ms  | ±1.15%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.152mb  | 13.970ms  | ±0.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.152mb  | 13.964ms  | ±1.16%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.176mb  | 13.997ms  | ±1.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.281mb  | 14.631ms  | ±7.35%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.957mb  | 2.889ms   | ±1.31%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.155mb  | 14.026ms  | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.214mb  | 13.893ms  | ±1.08%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.109mb  | 13.701ms  | ±0.65%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```