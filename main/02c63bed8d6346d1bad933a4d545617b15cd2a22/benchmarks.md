# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-09 18:03:07 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.684ms | 2.495ms | 2.731ms | 4.685ms | 6.902ms |
| FPDF | 865.299μs | 940.887μs | 1.025ms | 1.629ms | 2.347ms |
| TCPDF | 10.093ms | 10.929ms | 12.110ms | 19.269ms | 28.538ms |
| mPDF | 26.482ms | 30.112ms | 33.829ms | 60.987ms | 95.586ms |
| Dompdf | 11.240ms | 15.242ms | 19.963ms | 65.949ms | 147.729ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.110mb | 5.910mb | 5.996mb | 6.630mb | 7.452mb |
| FPDF | 5.045mb | 5.045mb | 5.045mb | 5.045mb | 5.047mb |
| TCPDF | 12.883mb | 12.883mb | 12.883mb | 12.883mb | 12.883mb |
| mPDF | 17.595mb | 17.654mb | 17.692mb | 17.985mb | 18.347mb |
| Dompdf | 9.328mb | 9.548mb | 9.869mb | 12.562mb | 15.925mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.312ms | 3.536ms | 3.795ms | 5.822ms | 8.410ms |
| FPDF | 1.124ms | 1.213ms | 1.301ms | 1.993ms | 3.268ms |
| TCPDF | 14.834ms | 16.169ms | 16.950ms | 25.169ms | 35.497ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.336mb | 5.383mb | 5.442mb | 5.934mb | 6.533mb |
| FPDF | 4.379mb | 4.379mb | 4.379mb | 4.408mb | 4.469mb |
| TCPDF | 12.458mb | 12.458mb | 12.458mb | 12.458mb | 12.459mb |

## Writer Levels Comparison — `WriterLevelsBench`

Same workload (N pages with heading + body text) rendered through each
writer level, so the abstraction overhead is visible directly. Lower is
better; the higher-level APIs (`Pdf` → `PdfDoc` → `PdfWriter`) trade
some performance for ergonomics.

### Generation Time

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 3.277ms | 4.310ms | 12.140ms |
| PdfDoc (Level 2) | 2.604ms | 3.055ms | 7.339ms |
| PdfWriter (Level 1) | 2.270ms | 2.748ms | 6.958ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 5.958mb | 6.121mb | 7.798mb |
| PdfDoc (Level 2) | 5.615mb | 5.773mb | 7.342mb |
| PdfWriter (Level 1) | 5.352mb | 5.511mb | 7.086mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 4.254ms | 11.586ms | 45.366ms |
| PdfDoc (Level 2) | 3.592ms | 9.725ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.309mb | 9.104mb | 21.512mb |
| PdfDoc (Level 2) | 6.115mb | 8.930mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 3.965ms | 11.385ms | 44.970ms |
| PdfDoc (Level 2) | 3.193ms | 7.210ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.941mb | 6.493mb | 8.937mb |
| PdfDoc (Level 2) | 5.730mb | 6.224mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.104ms | 1.675ms | 6.112ms |
| smalot/pdfparser | 2.026ms | 2.387ms | 5.451ms |
| setasign/fpdi | 1.893ms | 2.692ms | 28.067ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.312mb | 4.209mb | 4.566mb |
| smalot/pdfparser | 4.714mb | 4.815mb | 6.573mb |
| setasign/fpdi | 4.743mb | 4.743mb | 5.490mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 2.063ms | 1.375ms |
| smalot/pdfparser | FAIL | 1.914ms |
| setasign/fpdi | 2.841ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.209mb  | 1.235ms   | ±1.59%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.209mb  | 1.675ms   | ±0.72%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.566mb  | 6.112ms   | ±6.64%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.209mb  | 2.063ms   | ±5.10%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.209mb  | 1.375ms   | ±0.89%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.714mb  | 2.026ms   | ±0.81%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.815mb  | 2.387ms   | ±0.14%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.573mb  | 5.451ms   | ±1.28%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.209mb  | 571.432μs | ±1.18%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.711mb  | 1.914ms   | ±6.77%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.743mb  | 1.893ms   | ±3.18%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.743mb  | 2.692ms   | ±0.42%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.490mb  | 28.067ms  | ±0.27%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.838mb  | 2.841ms   | ±0.60%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.770mb  | 1.548ms   | ±13.33% |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.988mb  | 7.339ms   | ±22.71% |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.960mb  | 5.571ms   | ±0.96%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.938mb  | 3.930ms   | ±1.40%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.209mb  | 5.597μs   | ±23.33% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.312mb  | 6.104ms   | ±0.46%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.914mb  | 3.657ms   | ±1.51%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.402mb  | 5.582ms   | ±1.78%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.713mb  | 5.631ms   | ±0.44%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.681mb  | 4.846ms   | ±0.98%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.144mb  | 5.325ms   | ±1.01%  |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.209mb  | 5.174ms   | ±0.83%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.067mb  | 8.519ms   | ±0.41%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.620mb  | 2.867ms   | ±1.02%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.751mb | 65.096ms  | ±1.31%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.661mb | 283.371ms | ±1.31%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.281mb | 1.071s    | ±0.13%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.077mb | 199.777ms | ±1.51%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.408mb | 170.668ms | ±0.51%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.536mb | 125.466ms | ±0.42%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.574mb | 166.039ms | ±0.33%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.130mb | 138.977ms | ±2.51%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.609mb | 261.229ms | ±0.57%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.210mb | 40.686ms  | ±0.18%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.196mb | 35.685ms  | ±2.87%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.126mb | 34.432ms  | ±3.48%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.361mb | 109.389ms | ±0.75%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.182mb | 36.990ms  | ±0.30%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.396mb | 46.659ms  | ±0.06%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.812mb | 67.351ms  | ±0.43%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.091mb | 30.910ms  | ±0.81%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.645mb | 37.987ms  | ±1.04%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.773mb | 169.012ms | ±0.39%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.657mb | 112.356ms | ±0.56%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.474mb | 44.461ms  | ±0.76%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.071mb | 90.864ms  | ±0.42%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.752mb | 1.030s    | ±0.22%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.775mb | 24.102ms  | ±2.32%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.735mb | 51.890ms  | ±0.65%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.546mb | 484.949ms | ±1.04%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.402mb | 63.437ms  | ±9.64%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.056mb | 82.404ms  | ±1.89%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.825mb | 659.145ms | ±0.52%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.756mb | 18.024ms  | ±0.68%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.756mb | 41.403ms  | ±0.33%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.399mb | 277.608ms | ±0.25%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.309mb  | 4.254ms   | ±5.62%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.104mb  | 11.586ms  | ±0.21%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.512mb | 45.366ms  | ±0.92%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.115mb  | 3.592ms   | ±0.94%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.930mb  | 9.725ms   | ±0.49%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.703mb  | 8.773ms   | ±0.31%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.517mb  | 8.231ms   | ±0.70%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.726mb  | 8.439ms   | ±0.83%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.336mb  | 3.312ms   | ±1.24%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.383mb  | 3.536ms   | ±1.53%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.442mb  | 3.795ms   | ±0.86%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.934mb  | 5.822ms   | ±0.39%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.533mb  | 8.410ms   | ±0.82%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.458mb | 14.834ms  | ±0.25%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.458mb | 16.169ms  | ±1.27%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.458mb | 16.950ms  | ±0.70%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.458mb | 25.169ms  | ±0.56%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.459mb | 35.497ms  | ±0.52%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.379mb  | 1.124ms   | ±1.19%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.379mb  | 1.213ms   | ±2.16%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.379mb  | 1.301ms   | ±0.99%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.408mb  | 1.993ms   | ±1.31%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.469mb  | 3.268ms   | ±11.68% |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.941mb  | 3.965ms   | ±1.39%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.493mb  | 11.385ms  | ±1.10%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.937mb  | 44.970ms  | ±1.03%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.730mb  | 3.193ms   | ±0.92%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.224mb  | 7.210ms   | ±2.61%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.208mb  | 42.841μs  | ±1.13%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.468mb  | 236.320μs | ±0.69%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.352mb  | 2.270ms   | ±0.52%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.511mb  | 2.748ms   | ±0.71%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.086mb  | 6.958ms   | ±0.29%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.615mb  | 2.604ms   | ±1.83%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.773mb  | 3.055ms   | ±0.48%  |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.342mb  | 7.339ms   | ±0.93%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.958mb  | 3.277ms   | ±0.85%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.121mb  | 4.310ms   | ±0.23%  |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.798mb  | 12.140ms  | ±0.60%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.855mb  | 20.291ms  | ±1.01%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.840mb | 181.918ms | ±0.87%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.356mb | 898.021ms | ±0.19%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.849mb  | 2.285ms   | ±0.84%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.910mb  | 2.495ms   | ±0.41%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.996mb  | 2.731ms   | ±0.46%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.630mb  | 4.685ms   | ±0.36%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.452mb  | 6.902ms   | ±1.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.251mb  | 3.461ms   | ±1.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.305mb  | 3.749ms   | ±0.79%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.733mb  | 13.085ms  | ±0.60%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.295mb  | 3.628ms   | ±48.18% |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.638mb  | 2.391ms   | ±0.90%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.618mb  | 584.721μs | ±3.18%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.067mb  | 2.990ms   | ±1.51%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.161mb  | 3.596ms   | ±0.93%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.081mb  | 3.176ms   | ±0.65%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.138mb  | 220.402ms | ±31.90% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.199mb  | 3.569ms   | ±1.02%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.810mb  | 5.798ms   | ±22.41% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.946mb  | 6.029ms   | ±0.44%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.883mb | 10.093ms  | ±1.01%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.883mb | 10.929ms  | ±0.51%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.883mb | 12.110ms  | ±0.90%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.883mb | 19.269ms  | ±0.42%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.883mb | 28.538ms  | ±1.19%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.045mb  | 865.299μs | ±1.51%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.045mb  | 940.887μs | ±12.68% |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.045mb  | 1.025ms   | ±2.25%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.045mb  | 1.629ms   | ±0.44%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.047mb  | 2.347ms   | ±0.35%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.595mb | 26.482ms  | ±2.10%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.654mb | 30.112ms  | ±0.90%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.692mb | 33.829ms  | ±1.90%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.985mb | 60.987ms  | ±1.06%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.347mb | 95.586ms  | ±0.31%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.328mb  | 11.240ms  | ±2.80%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.548mb  | 15.242ms  | ±1.65%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.869mb  | 19.963ms  | ±0.35%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.562mb | 65.949ms  | ±1.36%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.925mb | 147.729ms | ±0.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.011mb  | 4.945ms   | ±3.27%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.449mb  | 53.994ms  | ±0.59%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.618mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.618mb  | 1.668μs   | ±14.81% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.618mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.030mb  | 219.032ms | ±30.09% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.618mb  | 465.862μs | ±1.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.425mb  | 2.897ms   | ±0.72%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.023mb  | 3.176ms   | ±4.93%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.618mb  | 10.629ms  | ±9.53%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.618mb  | 83.527ms  | ±2.02%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.618mb  | 14.870ms  | ±1.34%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.618mb  | 25.992ms  | ±1.75%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.851mb  | 214.618ms | ±19.98% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.181mb  | 14.009ms  | ±1.30%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.153mb  | 13.939ms  | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.154mb  | 13.980ms  | ±0.36%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.178mb  | 14.063ms  | ±0.66%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.282mb  | 14.443ms  | ±0.69%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.959mb  | 2.842ms   | ±2.94%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.157mb  | 13.907ms  | ±0.68%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.216mb  | 14.051ms  | ±0.91%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.110mb  | 13.684ms  | ±0.50%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```