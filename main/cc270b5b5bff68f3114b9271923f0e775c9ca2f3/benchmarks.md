# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-07 23:39:19 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 10.538ms | 1.924ms | 2.096ms | 3.617ms | 5.395ms |
| FPDF | 644.571μs | 712.095μs | 774.714μs | 1.254ms | 1.826ms |
| TCPDF | 7.784ms | 8.442ms | 9.214ms | 14.934ms | 21.908ms |
| mPDF | 19.807ms | 22.575ms | 25.196ms | 46.599ms | 73.058ms |
| Dompdf | 8.529ms | 11.662ms | 15.423ms | 50.742ms | 114.903ms |

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
| phpdftk | 7.322ms | 2.678ms | 2.897ms | 4.986ms | 6.480ms |
| FPDF | 1.385ms | 916.913μs | 1.010ms | 1.540ms | 2.308ms |
| TCPDF | 11.366ms | 12.201ms | 14.775ms | 19.678ms | 27.509ms |

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
| Pdf (Level 3) | 2.602ms | 3.288ms | 9.346ms |
| PdfDoc (Level 2) | 2.001ms | 2.349ms | 5.688ms |
| PdfWriter (Level 1) | 1.758ms | 2.091ms | 5.336ms |

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
| Pdf (Level 3) | 3.262ms | 9.059ms | 35.281ms |
| PdfDoc (Level 2) | 2.865ms | 7.631ms | — |

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
| Pdf (Level 3) | 3.052ms | 8.847ms | 34.786ms |
| PdfDoc (Level 2) | 2.474ms | 5.767ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.939mb | 6.491mb | 8.935mb |
| PdfDoc (Level 2) | 5.728mb | 6.222mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 4.703ms | 1.274ms | 4.705ms |
| smalot/pdfparser | 1.532ms | 1.796ms | 4.251ms |
| setasign/fpdi | 1.465ms | 2.049ms | 21.948ms |

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
| phpdftk | 1.553ms | 1.033ms |
| smalot/pdfparser | FAIL | 1.461ms |
| setasign/fpdi | 2.215ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.207mb  | 964.435μs | ±1.46%   |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.207mb  | 1.274ms   | ±0.54%   |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.564mb  | 4.705ms   | ±1.17%   |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.207mb  | 1.553ms   | ±0.77%   |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.207mb  | 1.033ms   | ±1.16%   |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.713mb  | 1.532ms   | ±0.24%   |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.814mb  | 1.796ms   | ±3.08%   |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.571mb  | 4.251ms   | ±0.84%   |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.207mb  | 430.896μs | ±0.46%   |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.709mb  | 1.461ms   | ±0.30%   |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.741mb  | 1.465ms   | ±1.57%   |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.741mb  | 2.049ms   | ±0.75%   |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.488mb  | 21.948ms  | ±1.43%   |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.836mb  | 2.215ms   | ±1.49%   |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.769mb  | 1.154ms   | ±0.79%   |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.986mb  | 5.458ms   | ±0.20%   |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.958mb  | 4.142ms   | ±0.26%   |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.937mb  | 2.939ms   | ±0.73%   |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.207mb  | 3.073μs   | ±16.64%  |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.310mb  | 4.703ms   | ±1.30%   |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.912mb  | 2.732ms   | ±0.56%   |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.400mb  | 4.097ms   | ±0.18%   |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.712mb  | 4.195ms   | ±0.54%   |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.680mb  | 3.601ms   | ±0.79%   |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.143mb  | 3.937ms   | ±0.23%   |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.208mb  | 3.853ms   | ±0.12%   |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.066mb  | 6.515ms   | ±0.38%   |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.618mb  | 2.163ms   | ±0.71%   |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.741mb | 49.078ms  | ±1.97%   |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.651mb | 209.455ms | ±1.09%   |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.271mb | 818.443ms | ±0.44%   |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.066mb | 151.966ms | ±0.31%   |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.398mb | 130.852ms | ±0.74%   |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.526mb | 97.064ms  | ±2.14%   |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.564mb | 129.336ms | ±0.89%   |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.120mb | 102.107ms | ±0.59%   |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.599mb | 198.511ms | ±0.86%   |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.200mb | 30.606ms  | ±0.86%   |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.186mb | 26.891ms  | ±0.48%   |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.116mb | 26.498ms  | ±0.76%   |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.351mb | 83.793ms  | ±4.32%   |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.108mb | 28.362ms  | ±0.53%   |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.386mb | 35.634ms  | ±0.23%   |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.802mb | 52.780ms  | ±1.41%   |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.081mb | 22.846ms  | ±0.65%   |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.635mb | 29.082ms  | ±1.09%   |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.763mb | 128.395ms | ±0.85%   |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.647mb | 84.751ms  | ±1.66%   |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.464mb | 35.657ms  | ±35.25%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.061mb | 69.053ms  | ±0.66%   |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.742mb | 785.016ms | ±0.16%   |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.768mb | 18.180ms  | ±1.54%   |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.728mb | 40.861ms  | ±1.44%   |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.539mb | 356.636ms | ±1.91%   |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.395mb | 48.488ms  | ±9.42%   |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.049mb | 64.247ms  | ±1.26%   |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.818mb | 511.433ms | ±0.54%   |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.750mb | 13.726ms  | ±8.67%   |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.750mb | 31.576ms  | ±0.39%   |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.392mb | 213.765ms | ±0.40%   |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.307mb  | 3.262ms   | ±6.81%   |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.102mb  | 9.059ms   | ±0.85%   |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.511mb | 35.281ms  | ±0.60%   |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.113mb  | 2.865ms   | ±131.47% |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.929mb  | 7.631ms   | ±1.47%   |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.701mb  | 6.714ms   | ±0.32%   |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.516mb  | 6.904ms   | ±37.41%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.725mb  | 6.438ms   | ±38.28%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.334mb  | 7.322ms   | ±104.72% |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.381mb  | 2.678ms   | ±1.20%   |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.440mb  | 2.897ms   | ±0.33%   |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.933mb  | 4.986ms   | ±58.37%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.531mb  | 6.480ms   | ±11.53%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.456mb | 11.366ms  | ±0.43%   |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.456mb | 12.201ms  | ±0.28%   |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.456mb | 14.775ms  | ±29.12%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.456mb | 19.678ms  | ±0.51%   |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.457mb | 27.509ms  | ±0.17%   |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.378mb  | 1.385ms   | ±108.27% |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.378mb  | 916.913μs | ±2.46%   |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.378mb  | 1.010ms   | ±0.31%   |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.407mb  | 1.540ms   | ±0.81%   |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.467mb  | 2.308ms   | ±36.98%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.939mb  | 3.052ms   | ±0.43%   |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.491mb  | 8.847ms   | ±6.95%   |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.935mb  | 34.786ms  | ±0.81%   |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.728mb  | 2.474ms   | ±65.42%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.222mb  | 5.767ms   | ±17.88%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.206mb  | 33.267μs  | ±0.93%   |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.467mb  | 182.648μs | ±0.68%   |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.350mb  | 1.758ms   | ±1.12%   |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.509mb  | 2.091ms   | ±46.27%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.084mb  | 5.336ms   | ±0.55%   |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.613mb  | 2.001ms   | ±1.67%   |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.771mb  | 2.349ms   | ±0.49%   |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.340mb  | 5.688ms   | ±55.25%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.956mb  | 2.602ms   | ±27.71%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.119mb  | 3.288ms   | ±4.29%   |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.796mb  | 9.346ms   | ±1.43%   |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.853mb  | 15.712ms  | ±0.81%   |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.839mb | 138.469ms | ±0.49%   |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.354mb | 678.796ms | ±0.48%   |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.847mb  | 1.755ms   | ±3.45%   |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.908mb  | 1.924ms   | ±0.31%   |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.994mb  | 2.096ms   | ±1.08%   |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.628mb  | 3.617ms   | ±0.32%   |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.451mb  | 5.395ms   | ±0.50%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.250mb  | 2.638ms   | ±0.50%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.303mb  | 2.907ms   | ±0.89%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.732mb  | 10.054ms  | ±21.85%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.293mb  | 2.808ms   | ±0.95%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.636mb  | 1.796ms   | ±0.50%   |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.616mb  | 489.220μs | ±121.16% |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.065mb  | 2.260ms   | ±1.27%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.160mb  | 2.744ms   | ±0.63%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.080mb  | 2.430ms   | ±0.61%   |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.136mb  | 160.467ms | ±18.23%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.197mb  | 2.769ms   | ±152.96% |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.808mb  | 4.511ms   | ±120.65% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.944mb  | 4.787ms   | ±20.72%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.881mb | 7.784ms   | ±0.32%   |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.881mb | 8.442ms   | ±0.57%   |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.881mb | 9.214ms   | ±0.36%   |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.881mb | 14.934ms  | ±0.46%   |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.881mb | 21.908ms  | ±0.16%   |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.043mb  | 644.571μs | ±1.29%   |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.043mb  | 712.095μs | ±2.21%   |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.043mb  | 774.714μs | ±1.23%   |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.043mb  | 1.254ms   | ±1.35%   |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.046mb  | 1.826ms   | ±0.77%   |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.593mb | 19.807ms  | ±2.10%   |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.652mb | 22.575ms  | ±0.59%   |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.691mb | 25.196ms  | ±12.69%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.983mb | 46.599ms  | ±0.58%   |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.345mb | 73.058ms  | ±2.59%   |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.327mb  | 8.529ms   | ±0.26%   |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.547mb  | 11.662ms  | ±0.79%   |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.868mb  | 15.423ms  | ±0.60%   |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.560mb | 50.742ms  | ±0.77%   |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.923mb | 114.903ms | ±1.26%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.010mb  | 3.788ms   | ±0.71%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.447mb  | 42.009ms  | ±1.20%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.616mb  | 1.322μs   | ±13.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.616mb  | 1.333μs   | ±15.81%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.616mb  | 1.463μs   | ±17.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.028mb  | 166.038ms | ±25.96%  |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.616mb  | 367.841μs | ±0.44%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.424mb  | 2.226ms   | ±1.19%   |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.021mb  | 2.430ms   | ±0.43%   |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.616mb  | 8.385ms   | ±4.58%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.616mb  | 63.240ms  | ±1.63%   |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.616mb  | 11.586ms  | ±1.75%   |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.616mb  | 20.076ms  | ±0.41%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.849mb  | 209.678ms | ±23.09%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.179mb  | 10.719ms  | ±0.79%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.152mb  | 10.699ms  | ±1.51%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.152mb  | 10.755ms  | ±0.96%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.176mb  | 10.893ms  | ±9.48%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.281mb  | 11.118ms  | ±0.53%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.957mb  | 2.342ms   | ±29.50%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.155mb  | 10.653ms  | ±1.25%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.214mb  | 10.863ms  | ±0.80%   |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.109mb  | 10.538ms  | ±1.13%   |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```