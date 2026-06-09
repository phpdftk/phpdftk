# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-06-09 04:12:11 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.107ms | 2.467ms | 2.718ms | 4.720ms | 6.932ms |
| FPDF | 819.613μs | 899.279μs | 989.716μs | 1.600ms | 2.294ms |
| TCPDF | 9.903ms | 10.871ms | 12.056ms | 20.311ms | 31.096ms |
| mPDF | 24.904ms | 28.599ms | 32.710ms | 64.854ms | 104.157ms |
| Dompdf | 11.206ms | 15.885ms | 21.311ms | 71.766ms | 160.214ms |

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
| phpdftk | 3.259ms | 3.488ms | 3.774ms | 5.780ms | 8.298ms |
| FPDF | 1.067ms | 1.171ms | 1.248ms | 1.954ms | 2.783ms |
| TCPDF | 14.358ms | 15.393ms | 16.640ms | 26.571ms | 38.131ms |

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
| Pdf (Level 3) | 3.258ms | 4.357ms | 12.628ms |
| PdfDoc (Level 2) | 2.592ms | 3.073ms | 7.476ms |
| PdfWriter (Level 1) | 2.313ms | 2.746ms | 7.027ms |

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
| Pdf (Level 3) | 4.241ms | 11.836ms | 46.426ms |
| PdfDoc (Level 2) | 3.713ms | 9.827ms | — |

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
| Pdf (Level 3) | 3.985ms | 11.396ms | 44.738ms |
| PdfDoc (Level 2) | 3.206ms | 7.161ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.939mb | 6.491mb | 8.935mb |
| PdfDoc (Level 2) | 5.728mb | 6.222mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.088ms | 1.662ms | 6.007ms |
| smalot/pdfparser | 1.998ms | 2.344ms | 5.674ms |
| setasign/fpdi | 1.922ms | 2.798ms | 29.225ms |

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
| phpdftk | 2.001ms | 1.360ms |
| smalot/pdfparser | FAIL | 1.906ms |
| setasign/fpdi | 2.941ms | FAIL |

---

## Raw phpbench Output

```
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark                   | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| ReadPdfBench                | benchPhpdftk1Page                                |     | 3    | 5   | 4.207mb  | 1.222ms   | ±1.30%  |
| ReadPdfBench                | benchPhpdftk10Pages                              |     | 3    | 5   | 4.207mb  | 1.662ms   | ±2.20%  |
| ReadPdfBench                | benchPhpdftk100Pages                             |     | 3    | 5   | 4.564mb  | 6.007ms   | ±1.34%  |
| ReadPdfBench                | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.207mb  | 2.001ms   | ±0.42%  |
| ReadPdfBench                | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.207mb  | 1.360ms   | ±0.75%  |
| ReadPdfBench                | benchSmalot1Page                                 |     | 3    | 5   | 4.713mb  | 1.998ms   | ±0.93%  |
| ReadPdfBench                | benchSmalot10Pages                               |     | 3    | 5   | 4.814mb  | 2.344ms   | ±0.67%  |
| ReadPdfBench                | benchSmalot100Pages                              |     | 3    | 5   | 6.571mb  | 5.674ms   | ±7.46%  |
| ReadPdfBench                | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.207mb  | 549.959μs | ±1.25%  |
| ReadPdfBench                | benchSmalotXrefStream                            |     | 3    | 5   | 4.709mb  | 1.906ms   | ±1.19%  |
| ReadPdfBench                | benchFpdi1Page                                   |     | 3    | 5   | 4.741mb  | 1.922ms   | ±0.88%  |
| ReadPdfBench                | benchFpdi10Pages                                 |     | 3    | 5   | 4.741mb  | 2.798ms   | ±0.39%  |
| ReadPdfBench                | benchFpdi100Pages                                |     | 3    | 5   | 5.488mb  | 29.225ms  | ±1.47%  |
| ReadPdfBench                | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.836mb  | 2.941ms   | ±0.98%  |
| ReadPdfBench                | benchFpdiXrefStream                              |     | 3    | 5   | 4.769mb  | 1.517ms   | ±1.16%  |
| ReadPdfBench                | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.986mb  | 7.266ms   | ±1.51%  |
| ReadPdfBench                | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.958mb  | 5.395ms   | ±0.68%  |
| ReadPdfBench                | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.937mb  | 3.834ms   | ±1.66%  |
| ReadPdfBench                | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.207mb  | 3.186μs   | ±17.50% |
| ReadPdfBench                | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.310mb  | 6.088ms   | ±0.81%  |
| SvgToPdfBench               | benchBasicShapes                                 |     | 3    | 3   | 5.912mb  | 3.558ms   | ±0.34%  |
| SvgToPdfBench               | benchPathHeavyDocument                           |     | 3    | 3   | 6.400mb  | 5.563ms   | ±0.42%  |
| SvgToPdfBench               | benchGradientHeavyDocument                       |     | 3    | 3   | 6.712mb  | 5.663ms   | ±0.39%  |
| SvgToPdfBench               | benchTextHeavyDocument                           |     | 3    | 3   | 6.680mb  | 4.692ms   | ±0.59%  |
| SvgToPdfBench               | benchUseSymbolExpansion                          |     | 3    | 3   | 7.143mb  | 5.431ms   | ±12.38% |
| SvgToPdfBench               | benchClipAndMaskHeavy                            |     | 3    | 3   | 6.208mb  | 5.261ms   | ±0.66%  |
| SvgToPdfBench               | benchRealisticIconAtlas                          |     | 3    | 3   | 8.066mb  | 9.173ms   | ±0.07%  |
| SvgToPdfBench               | benchTranslatorWithoutAdapter                    |     | 3    | 3   | 5.618mb  | 2.809ms   | ±0.62%  |
| RendererBench               | benchShortDocument                               |     | 3    | 3   | 12.749mb | 68.314ms  | ±0.22%  |
| RendererBench               | benchMediumArticle                               |     | 3    | 3   | 18.660mb | 293.164ms | ±0.46%  |
| RendererBench               | benchLongReport                                  |     | 3    | 3   | 42.280mb | 1.152s    | ±1.40%  |
| RendererBench               | benchRealFaceMatching                            |     | 3    | 3   | 25.075mb | 209.329ms | ±0.80%  |
| RendererBench               | benchPageMarginBoxes                             |     | 3    | 3   | 29.406mb | 170.301ms | ±0.62%  |
| RendererBench               | benchFloats                                      |     | 3    | 3   | 13.535mb | 131.647ms | ±2.33%  |
| RendererBench               | benchMultiColumn                                 |     | 3    | 3   | 14.572mb | 178.849ms | ±1.29%  |
| RendererBench               | benchFlex                                        |     | 3    | 3   | 14.128mb | 142.195ms | ±0.33%  |
| RendererBench               | benchRichTypography                              |     | 3    | 3   | 16.608mb | 276.646ms | ±1.40%  |
| RendererBench               | benchPhase2Grid                                  |     | 3    | 3   | 12.209mb | 42.462ms  | ±0.66%  |
| RendererBench               | benchPhase2GridAdvanced                          |     | 3    | 3   | 12.194mb | 36.922ms  | ±0.33%  |
| RendererBench               | benchPhase2Transform3d                           |     | 3    | 3   | 12.124mb | 35.524ms  | ±0.37%  |
| RendererBench               | benchPhase2TableAutoWidth                        |     | 3    | 3   | 13.360mb | 117.682ms | ±0.81%  |
| RendererBench               | benchPhase2GridAutoTracks                        |     | 3    | 3   | 12.180mb | 38.753ms  | ±0.38%  |
| RendererBench               | benchPhase2GridAutoFlow                          |     | 3    | 3   | 12.395mb | 48.483ms  | ±0.60%  |
| RendererBench               | benchPhase2GridImplicitRows                      |     | 3    | 3   | 12.810mb | 71.658ms  | ±0.10%  |
| RendererBench               | benchPhase2GridTemplateAreas                     |     | 3    | 3   | 12.089mb | 31.098ms  | ±0.91%  |
| RendererBench               | benchPhase2Gradients                             |     | 3    | 3   | 12.644mb | 39.034ms  | ±0.94%  |
| RendererBench               | benchPhase2BorderCollapseHeavy                   |     | 3    | 3   | 15.772mb | 180.786ms | ±0.24%  |
| RendererBench               | benchPhase2MediaQueriesScale                     |     | 3    | 3   | 12.655mb | 111.958ms | ±0.12%  |
| HtmlRendererComparisonBench | benchPhpdftkSmall                                |     | 3    | 3   | 12.472mb | 45.153ms  | ±0.30%  |
| HtmlRendererComparisonBench | benchPhpdftkMedium                               |     | 3    | 3   | 13.070mb | 92.873ms  | ±0.64%  |
| HtmlRendererComparisonBench | benchPhpdftkLong                                 |     | 3    | 3   | 39.750mb | 1.093s    | ±0.57%  |
| HtmlRendererComparisonBench | benchDompdfSmall                                 |     | 3    | 3   | 13.773mb | 25.681ms  | ±2.33%  |
| HtmlRendererComparisonBench | benchDompdfMedium                                |     | 3    | 3   | 15.733mb | 58.839ms  | ±2.01%  |
| HtmlRendererComparisonBench | benchDompdfLong                                  |     | 3    | 3   | 55.544mb | 515.456ms | ±0.29%  |
| HtmlRendererComparisonBench | benchMpdfSmall                                   |     | 3    | 3   | 27.400mb | 63.129ms  | ±9.33%  |
| HtmlRendererComparisonBench | benchMpdfMedium                                  |     | 3    | 3   | 22.054mb | 85.059ms  | ±0.90%  |
| HtmlRendererComparisonBench | benchMpdfLong                                    |     | 3    | 3   | 30.823mb | 730.361ms | ±0.19%  |
| HtmlRendererComparisonBench | benchTcpdfSmall                                  |     | 3    | 3   | 15.755mb | 18.631ms  | ±0.65%  |
| HtmlRendererComparisonBench | benchTcpdfMedium                                 |     | 3    | 3   | 15.755mb | 42.873ms  | ±0.49%  |
| HtmlRendererComparisonBench | benchTcpdfLong                                   |     | 3    | 3   | 28.397mb | 316.202ms | ±0.35%  |
| TablesBench                 | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.307mb  | 4.241ms   | ±0.56%  |
| TablesBench                 | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.102mb  | 11.836ms  | ±0.39%  |
| TablesBench                 | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.511mb | 46.426ms  | ±0.66%  |
| TablesBench                 | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.113mb  | 3.713ms   | ±9.60%  |
| TablesBench                 | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.929mb  | 9.827ms   | ±0.44%  |
| StylingBench                | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.701mb  | 8.734ms   | ±0.39%  |
| StylingBench                | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.516mb  | 8.304ms   | ±0.37%  |
| StylingBench                | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.725mb  | 8.386ms   | ±1.44%  |
| MemoryBench                 | benchPhpdftk1Page                                |     | 2    | 3   | 5.334mb  | 3.259ms   | ±1.52%  |
| MemoryBench                 | benchPhpdftk5Pages                               |     | 2    | 3   | 5.381mb  | 3.488ms   | ±0.71%  |
| MemoryBench                 | benchPhpdftk10Pages                              |     | 2    | 3   | 5.440mb  | 3.774ms   | ±0.24%  |
| MemoryBench                 | benchPhpdftk50Pages                              |     | 2    | 3   | 5.933mb  | 5.780ms   | ±0.65%  |
| MemoryBench                 | benchPhpdftk100Pages                             |     | 2    | 3   | 6.531mb  | 8.298ms   | ±0.94%  |
| MemoryBench                 | benchTcpdf1Page                                  |     | 2    | 3   | 12.456mb | 14.358ms  | ±0.25%  |
| MemoryBench                 | benchTcpdf5Pages                                 |     | 2    | 3   | 12.456mb | 15.393ms  | ±0.29%  |
| MemoryBench                 | benchTcpdf10Pages                                |     | 2    | 3   | 12.456mb | 16.640ms  | ±0.26%  |
| MemoryBench                 | benchTcpdf50Pages                                |     | 2    | 3   | 12.456mb | 26.571ms  | ±0.21%  |
| MemoryBench                 | benchTcpdf100Pages                               |     | 2    | 3   | 12.457mb | 38.131ms  | ±0.21%  |
| MemoryBench                 | benchFpdf1Page                                   |     | 2    | 3   | 4.378mb  | 1.067ms   | ±1.01%  |
| MemoryBench                 | benchFpdf5Pages                                  |     | 2    | 3   | 4.378mb  | 1.171ms   | ±1.68%  |
| MemoryBench                 | benchFpdf10Pages                                 |     | 2    | 3   | 4.378mb  | 1.248ms   | ±1.71%  |
| MemoryBench                 | benchFpdf50Pages                                 |     | 2    | 3   | 4.407mb  | 1.954ms   | ±0.74%  |
| MemoryBench                 | benchFpdf100Pages                                |     | 2    | 3   | 4.467mb  | 2.783ms   | ±0.34%  |
| ListsBench                  | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.939mb  | 3.985ms   | ±0.77%  |
| ListsBench                  | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.491mb  | 11.396ms  | ±0.73%  |
| ListsBench                  | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.935mb  | 44.738ms  | ±8.43%  |
| ListsBench                  | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.728mb  | 3.206ms   | ±1.64%  |
| ListsBench                  | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.222mb  | 7.161ms   | ±0.28%  |
| EncodingBench               | benchEncodeParagraph                             |     | 50   | 5   | 4.206mb  | 41.432μs  | ±0.63%  |
| EncodingBench               | benchShowTextThroughContentStream                |     | 50   | 5   | 6.467mb  | 242.328μs | ±0.79%  |
| WriterLevelsBench           | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.350mb  | 2.313ms   | ±5.42%  |
| WriterLevelsBench           | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.509mb  | 2.746ms   | ±0.53%  |
| WriterLevelsBench           | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.084mb  | 7.027ms   | ±0.64%  |
| WriterLevelsBench           | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.613mb  | 2.592ms   | ±0.24%  |
| WriterLevelsBench           | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.771mb  | 3.073ms   | ±12.79% |
| WriterLevelsBench           | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.340mb  | 7.476ms   | ±2.12%  |
| WriterLevelsBench           | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.956mb  | 3.258ms   | ±2.83%  |
| WriterLevelsBench           | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.119mb  | 4.357ms   | ±30.50% |
| WriterLevelsBench           | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.796mb  | 12.628ms  | ±0.34%  |
| BoxGeneratorBench           | benchSmallBlogPost                               |     | 5    | 3   | 7.853mb  | 21.535ms  | ±0.91%  |
| BoxGeneratorBench           | benchMediumArticle                               |     | 5    | 3   | 14.839mb | 192.099ms | ±0.64%  |
| BoxGeneratorBench           | benchLargeDocumentationPage                      |     | 5    | 3   | 46.354mb | 942.194ms | ±0.85%  |
| GeneratePdfBench            | benchPhpdftk1Page                                |     | 3    | 5   | 5.847mb  | 2.313ms   | ±8.56%  |
| GeneratePdfBench            | benchPhpdftk5Pages                               |     | 3    | 5   | 5.908mb  | 2.467ms   | ±0.95%  |
| GeneratePdfBench            | benchPhpdftk10Pages                              |     | 3    | 5   | 5.994mb  | 2.718ms   | ±0.81%  |
| GeneratePdfBench            | benchPhpdftk50Pages                              |     | 3    | 5   | 6.628mb  | 4.720ms   | ±0.13%  |
| GeneratePdfBench            | benchPhpdftk100Pages                             |     | 3    | 5   | 7.451mb  | 6.932ms   | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.250mb  | 3.483ms   | ±0.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.303mb  | 3.810ms   | ±0.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.732mb  | 12.676ms  | ±14.64% |
| GeneratePdfBench            | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.293mb  | 3.627ms   | ±1.40%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.636mb  | 2.370ms   | ±0.48%  |
| GeneratePdfBench            | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.616mb  | 641.853μs | ±4.11%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.065mb  | 2.962ms   | ±0.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.160mb  | 3.620ms   | ±0.56%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.080mb  | 3.177ms   | ±1.26%  |
| GeneratePdfBench            | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.136mb  | 207.773ms | ±22.42% |
| GeneratePdfBench            | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.197mb  | 3.528ms   | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.808mb  | 5.753ms   | ±16.47% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.944mb  | 6.037ms   | ±3.92%  |
| GeneratePdfBench            | benchTcpdf1Page                                  |     | 3    | 5   | 12.881mb | 9.903ms   | ±1.02%  |
| GeneratePdfBench            | benchTcpdf5Pages                                 |     | 3    | 5   | 12.881mb | 10.871ms  | ±2.89%  |
| GeneratePdfBench            | benchTcpdf10Pages                                |     | 3    | 5   | 12.881mb | 12.056ms  | ±0.47%  |
| GeneratePdfBench            | benchTcpdf50Pages                                |     | 3    | 5   | 12.881mb | 20.311ms  | ±1.03%  |
| GeneratePdfBench            | benchTcpdf100Pages                               |     | 3    | 5   | 12.881mb | 31.096ms  | ±0.52%  |
| GeneratePdfBench            | benchFpdf1Page                                   |     | 3    | 5   | 5.043mb  | 819.613μs | ±1.72%  |
| GeneratePdfBench            | benchFpdf5Pages                                  |     | 3    | 5   | 5.043mb  | 899.279μs | ±2.10%  |
| GeneratePdfBench            | benchFpdf10Pages                                 |     | 3    | 5   | 5.043mb  | 989.716μs | ±1.14%  |
| GeneratePdfBench            | benchFpdf50Pages                                 |     | 3    | 5   | 5.043mb  | 1.600ms   | ±0.60%  |
| GeneratePdfBench            | benchFpdf100Pages                                |     | 3    | 5   | 5.046mb  | 2.294ms   | ±0.29%  |
| GeneratePdfBench            | benchMpdf1Page                                   |     | 3    | 5   | 17.593mb | 24.904ms  | ±1.90%  |
| GeneratePdfBench            | benchMpdf5Pages                                  |     | 3    | 5   | 17.652mb | 28.599ms  | ±0.70%  |
| GeneratePdfBench            | benchMpdf10Pages                                 |     | 3    | 5   | 17.691mb | 32.710ms  | ±0.35%  |
| GeneratePdfBench            | benchMpdf50Pages                                 |     | 3    | 5   | 17.983mb | 64.854ms  | ±0.60%  |
| GeneratePdfBench            | benchMpdf100Pages                                |     | 3    | 5   | 18.345mb | 104.157ms | ±0.41%  |
| GeneratePdfBench            | benchDompdf1Page                                 |     | 3    | 5   | 9.327mb  | 11.206ms  | ±0.58%  |
| GeneratePdfBench            | benchDompdf5Pages                                |     | 3    | 5   | 9.547mb  | 15.885ms  | ±1.61%  |
| GeneratePdfBench            | benchDompdf10Pages                               |     | 3    | 5   | 9.868mb  | 21.311ms  | ±0.46%  |
| GeneratePdfBench            | benchDompdf50Pages                               |     | 3    | 5   | 12.560mb | 71.766ms  | ±0.53%  |
| GeneratePdfBench            | benchDompdf100Pages                              |     | 3    | 5   | 15.923mb | 160.214ms | ±1.45%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 7.010mb  | 4.984ms   | ±0.98%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.447mb  | 50.037ms  | ±0.73%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.616mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.616mb  | 1.344μs   | ±11.13% |
| GeneratePdfBench            | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.616mb  | 1.333μs   | ±15.81% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.028mb  | 135.452ms | ±34.23% |
| GeneratePdfBench            | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.616mb  | 438.861μs | ±1.78%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.424mb  | 2.989ms   | ±0.71%  |
| GeneratePdfBench            | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.021mb  | 3.177ms   | ±0.57%  |
| GeneratePdfBench            | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.616mb  | 12.429ms  | ±8.52%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.616mb  | 84.379ms  | ±1.52%  |
| GeneratePdfBench            | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.616mb  | 14.081ms  | ±0.54%  |
| GeneratePdfBench            | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.616mb  | 24.594ms  | ±0.61%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.849mb  | 197.218ms | ±25.44% |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.179mb  | 13.376ms  | ±0.17%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.152mb  | 13.251ms  | ±0.86%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.152mb  | 13.264ms  | ±1.44%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.176mb  | 13.524ms  | ±0.94%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.281mb  | 14.157ms  | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.957mb  | 2.883ms   | ±0.52%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.155mb  | 13.360ms  | ±0.82%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.214mb  | 13.504ms  | ±0.74%  |
| GeneratePdfBench            | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.109mb  | 13.107ms  | ±0.53%  |
+-----------------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```