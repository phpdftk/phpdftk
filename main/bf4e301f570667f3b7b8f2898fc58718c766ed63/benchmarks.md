# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-24 22:03:16 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.091ms | 2.501ms | 2.727ms | 4.766ms | 6.947ms |
| FPDF | 816.943μs | 885.491μs | 995.260μs | 1.580ms | 2.321ms |
| TCPDF | 9.929ms | 10.885ms | 12.106ms | 20.633ms | 31.216ms |
| mPDF | 24.977ms | 28.838ms | 33.191ms | 65.301ms | 105.500ms |
| Dompdf | 11.231ms | 15.871ms | 21.427ms | 73.268ms | 162.102ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 9.069mb | 5.889mb | 5.975mb | 6.610mb | 7.432mb |
| FPDF | 5.040mb | 5.040mb | 5.040mb | 5.040mb | 5.042mb |
| TCPDF | 12.878mb | 12.878mb | 12.878mb | 12.878mb | 12.878mb |
| mPDF | 17.590mb | 17.649mb | 17.687mb | 17.980mb | 18.342mb |
| Dompdf | 9.323mb | 9.543mb | 9.864mb | 12.557mb | 15.920mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.258ms | 3.523ms | 3.762ms | 5.724ms | 8.735ms |
| FPDF | 1.065ms | 1.140ms | 1.295ms | 1.960ms | 2.848ms |
| TCPDF | 14.928ms | 15.554ms | 16.715ms | 26.500ms | 38.550ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.316mb | 5.362mb | 5.422mb | 5.914mb | 6.513mb |
| FPDF | 4.374mb | 4.374mb | 4.374mb | 4.403mb | 4.463mb |
| TCPDF | 12.453mb | 12.453mb | 12.453mb | 12.453mb | 12.453mb |

## Writer Levels Comparison — `WriterLevelsBench`

Same workload (N pages with heading + body text) rendered through each
writer level, so the abstraction overhead is visible directly. Lower is
better; the higher-level APIs (`Pdf` → `PdfDoc` → `PdfWriter`) trade
some performance for ergonomics.

### Generation Time

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 3.237ms | 4.090ms | 12.498ms |
| PdfDoc (Level 2) | 2.543ms | 2.999ms | 7.155ms |
| PdfWriter (Level 1) | 2.268ms | 2.691ms | 7.001ms |

### Peak Memory

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| Pdf (Level 3) | 5.910mb | 6.074mb | 7.750mb |
| PdfDoc (Level 2) | 5.574mb | 5.732mb | 7.301mb |
| PdfWriter (Level 1) | 5.332mb | 5.491mb | 7.066mb |

## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 4.265ms | 11.700ms | 46.063ms |
| PdfDoc (Level 2) | 3.623ms | 9.858ms | — |

### Peak Memory

| Library | 10 rows | 100 rows | 500 rows |
|---|---|---|---|
| Pdf (Level 3) | 6.261mb | 9.056mb | 21.465mb |
| PdfDoc (Level 2) | 6.074mb | 8.889mb | — |

## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 3.903ms | 11.375ms | 44.529ms |
| PdfDoc (Level 2) | 3.120ms | 7.099ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.893mb | 6.445mb | 8.889mb |
| PdfDoc (Level 2) | 5.689mb | 6.183mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.207ms | 1.666ms | 6.093ms |
| smalot/pdfparser | 2.030ms | 2.354ms | 5.732ms |
| setasign/fpdi | 1.901ms | 2.829ms | 29.436ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.307mb | 4.203mb | 4.561mb |
| smalot/pdfparser | 4.709mb | 4.810mb | 6.567mb |
| setasign/fpdi | 4.812mb | 4.813mb | 5.484mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 2.030ms | 1.379ms |
| smalot/pdfparser | FAIL | 1.906ms |
| setasign/fpdi | 2.996ms | FAIL |

---

## Raw phpbench Output

```
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark         | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| WriterLevelsBench | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.332mb  | 2.268ms   | ±0.98%  |
| WriterLevelsBench | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.491mb  | 2.691ms   | ±1.17%  |
| WriterLevelsBench | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.066mb  | 7.001ms   | ±0.31%  |
| WriterLevelsBench | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.574mb  | 2.543ms   | ±1.37%  |
| WriterLevelsBench | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.732mb  | 2.999ms   | ±0.78%  |
| WriterLevelsBench | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.301mb  | 7.155ms   | ±0.79%  |
| WriterLevelsBench | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.910mb  | 3.237ms   | ±0.71%  |
| WriterLevelsBench | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.074mb  | 4.090ms   | ±2.21%  |
| WriterLevelsBench | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.750mb  | 12.498ms  | ±0.86%  |
| EncodingBench     | benchEncodeParagraph                             |     | 50   | 5   | 4.203mb  | 41.561μs  | ±21.19% |
| EncodingBench     | benchShowTextThroughContentStream                |     | 50   | 5   | 6.448mb  | 243.078μs | ±0.98%  |
| StylingBench      | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.655mb  | 8.719ms   | ±0.52%  |
| StylingBench      | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.470mb  | 8.318ms   | ±1.35%  |
| StylingBench      | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.679mb  | 8.320ms   | ±0.53%  |
| BoxGeneratorBench | benchSmallBlogPost                               |     | 5    | 3   | 6.298mb  | 8.798ms   | ±6.44%  |
| BoxGeneratorBench | benchMediumArticle                               |     | 5    | 3   | 10.259mb | 70.098ms  | ±0.79%  |
| BoxGeneratorBench | benchLargeDocumentationPage                      |     | 5    | 3   | 28.267mb | 338.719ms | ±0.67%  |
| MemoryBench       | benchPhpdftk1Page                                |     | 2    | 3   | 5.316mb  | 3.258ms   | ±0.34%  |
| MemoryBench       | benchPhpdftk5Pages                               |     | 2    | 3   | 5.362mb  | 3.523ms   | ±0.38%  |
| MemoryBench       | benchPhpdftk10Pages                              |     | 2    | 3   | 5.422mb  | 3.762ms   | ±1.17%  |
| MemoryBench       | benchPhpdftk50Pages                              |     | 2    | 3   | 5.914mb  | 5.724ms   | ±0.43%  |
| MemoryBench       | benchPhpdftk100Pages                             |     | 2    | 3   | 6.513mb  | 8.735ms   | ±2.04%  |
| MemoryBench       | benchTcpdf1Page                                  |     | 2    | 3   | 12.453mb | 14.928ms  | ±2.07%  |
| MemoryBench       | benchTcpdf5Pages                                 |     | 2    | 3   | 12.453mb | 15.554ms  | ±0.35%  |
| MemoryBench       | benchTcpdf10Pages                                |     | 2    | 3   | 12.453mb | 16.715ms  | ±0.32%  |
| MemoryBench       | benchTcpdf50Pages                                |     | 2    | 3   | 12.453mb | 26.500ms  | ±0.60%  |
| MemoryBench       | benchTcpdf100Pages                               |     | 2    | 3   | 12.453mb | 38.550ms  | ±0.54%  |
| MemoryBench       | benchFpdf1Page                                   |     | 2    | 3   | 4.374mb  | 1.065ms   | ±0.79%  |
| MemoryBench       | benchFpdf5Pages                                  |     | 2    | 3   | 4.374mb  | 1.140ms   | ±1.72%  |
| MemoryBench       | benchFpdf10Pages                                 |     | 2    | 3   | 4.374mb  | 1.295ms   | ±2.26%  |
| MemoryBench       | benchFpdf50Pages                                 |     | 2    | 3   | 4.403mb  | 1.960ms   | ±1.07%  |
| MemoryBench       | benchFpdf100Pages                                |     | 2    | 3   | 4.463mb  | 2.848ms   | ±9.50%  |
| TablesBench       | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.261mb  | 4.265ms   | ±1.16%  |
| TablesBench       | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.056mb  | 11.700ms  | ±0.78%  |
| TablesBench       | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.465mb | 46.063ms  | ±0.81%  |
| TablesBench       | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.074mb  | 3.623ms   | ±0.95%  |
| TablesBench       | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.889mb  | 9.858ms   | ±1.02%  |
| GeneratePdfBench  | benchPhpdftk1Page                                |     | 3    | 5   | 5.829mb  | 2.299ms   | ±1.06%  |
| GeneratePdfBench  | benchPhpdftk5Pages                               |     | 3    | 5   | 5.889mb  | 2.501ms   | ±1.65%  |
| GeneratePdfBench  | benchPhpdftk10Pages                              |     | 3    | 5   | 5.975mb  | 2.727ms   | ±2.02%  |
| GeneratePdfBench  | benchPhpdftk50Pages                              |     | 3    | 5   | 6.610mb  | 4.766ms   | ±0.63%  |
| GeneratePdfBench  | benchPhpdftk100Pages                             |     | 3    | 5   | 7.432mb  | 6.947ms   | ±1.12%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.210mb  | 3.487ms   | ±1.01%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.285mb  | 3.847ms   | ±0.57%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.713mb  | 12.726ms  | ±28.00% |
| GeneratePdfBench  | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.254mb  | 3.615ms   | ±0.28%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.617mb  | 2.342ms   | ±1.61%  |
| GeneratePdfBench  | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.612mb  | 656.381μs | ±2.61%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.047mb  | 2.970ms   | ±1.29%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.141mb  | 3.582ms   | ±0.62%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.061mb  | 2.944ms   | ±0.48%  |
| GeneratePdfBench  | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.117mb  | 217.117ms | ±25.76% |
| GeneratePdfBench  | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.179mb  | 3.565ms   | ±1.04%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.790mb  | 5.747ms   | ±37.39% |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.926mb  | 5.980ms   | ±0.64%  |
| GeneratePdfBench  | benchTcpdf1Page                                  |     | 3    | 5   | 12.878mb | 9.929ms   | ±0.47%  |
| GeneratePdfBench  | benchTcpdf5Pages                                 |     | 3    | 5   | 12.878mb | 10.885ms  | ±0.75%  |
| GeneratePdfBench  | benchTcpdf10Pages                                |     | 3    | 5   | 12.878mb | 12.106ms  | ±1.50%  |
| GeneratePdfBench  | benchTcpdf50Pages                                |     | 3    | 5   | 12.878mb | 20.633ms  | ±0.71%  |
| GeneratePdfBench  | benchTcpdf100Pages                               |     | 3    | 5   | 12.878mb | 31.216ms  | ±0.60%  |
| GeneratePdfBench  | benchFpdf1Page                                   |     | 3    | 5   | 5.040mb  | 816.943μs | ±1.02%  |
| GeneratePdfBench  | benchFpdf5Pages                                  |     | 3    | 5   | 5.040mb  | 885.491μs | ±1.55%  |
| GeneratePdfBench  | benchFpdf10Pages                                 |     | 3    | 5   | 5.040mb  | 995.260μs | ±12.67% |
| GeneratePdfBench  | benchFpdf50Pages                                 |     | 3    | 5   | 5.040mb  | 1.580ms   | ±1.39%  |
| GeneratePdfBench  | benchFpdf100Pages                                |     | 3    | 5   | 5.042mb  | 2.321ms   | ±0.78%  |
| GeneratePdfBench  | benchMpdf1Page                                   |     | 3    | 5   | 17.590mb | 24.977ms  | ±2.51%  |
| GeneratePdfBench  | benchMpdf5Pages                                  |     | 3    | 5   | 17.649mb | 28.838ms  | ±0.50%  |
| GeneratePdfBench  | benchMpdf10Pages                                 |     | 3    | 5   | 17.687mb | 33.191ms  | ±0.70%  |
| GeneratePdfBench  | benchMpdf50Pages                                 |     | 3    | 5   | 17.980mb | 65.301ms  | ±0.38%  |
| GeneratePdfBench  | benchMpdf100Pages                                |     | 3    | 5   | 18.342mb | 105.500ms | ±0.62%  |
| GeneratePdfBench  | benchDompdf1Page                                 |     | 3    | 5   | 9.323mb  | 11.231ms  | ±2.64%  |
| GeneratePdfBench  | benchDompdf5Pages                                |     | 3    | 5   | 9.543mb  | 15.871ms  | ±0.16%  |
| GeneratePdfBench  | benchDompdf10Pages                               |     | 3    | 5   | 9.864mb  | 21.427ms  | ±0.91%  |
| GeneratePdfBench  | benchDompdf50Pages                               |     | 3    | 5   | 12.557mb | 73.268ms  | ±0.90%  |
| GeneratePdfBench  | benchDompdf100Pages                              |     | 3    | 5   | 15.920mb | 162.102ms | ±0.50%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.991mb  | 5.032ms   | ±0.50%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.428mb  | 50.002ms  | ±1.36%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.612mb  | 1.142μs   | ±22.36% |
| GeneratePdfBench  | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.612mb  | 1.666μs   | ±8.33%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.612mb  | 1.344μs   | ±11.13% |
| GeneratePdfBench  | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.024mb  | 286.470ms | ±40.72% |
| GeneratePdfBench  | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.612mb  | 446.721μs | ±1.26%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.405mb  | 2.971ms   | ±0.40%  |
| GeneratePdfBench  | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.003mb  | 3.164ms   | ±0.54%  |
| GeneratePdfBench  | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.612mb  | 13.071ms  | ±8.40%  |
| GeneratePdfBench  | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.612mb  | 83.733ms  | ±0.58%  |
| GeneratePdfBench  | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.612mb  | 14.136ms  | ±1.17%  |
| GeneratePdfBench  | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.612mb  | 24.957ms  | ±1.92%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.831mb  | 241.033ms | ±20.71% |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.139mb  | 13.335ms  | ±0.41%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.112mb  | 13.256ms  | ±0.42%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.113mb  | 13.295ms  | ±0.61%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.137mb  | 13.437ms  | ±0.67%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.254mb  | 13.820ms  | ±0.90%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.917mb  | 2.836ms   | ±1.41%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.116mb  | 13.352ms  | ±0.39%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.175mb  | 13.537ms  | ±0.27%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.069mb  | 13.091ms  | ±0.47%  |
| ListsBench        | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.893mb  | 3.903ms   | ±1.09%  |
| ListsBench        | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.445mb  | 11.375ms  | ±0.54%  |
| ListsBench        | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.889mb  | 44.529ms  | ±0.37%  |
| ListsBench        | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.689mb  | 3.120ms   | ±0.72%  |
| ListsBench        | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.183mb  | 7.099ms   | ±0.44%  |
| RendererBench     | benchShortDocument                               |     | 3    | 3   | 9.403mb  | 33.412ms  | ±4.81%  |
| RendererBench     | benchMediumArticle                               |     | 3    | 3   | 13.605mb | 131.133ms | ±0.44%  |
| RendererBench     | benchLongReport                                  |     | 3    | 3   | 29.477mb | 518.263ms | ±0.84%  |
| RendererBench     | benchRealFaceMatching                            |     | 3    | 3   | 21.439mb | 118.278ms | ±0.36%  |
| RendererBench     | benchPageMarginBoxes                             |     | 3    | 3   | 26.569mb | 133.415ms | ±0.45%  |
| RendererBench     | benchFloats                                      |     | 3    | 3   | 9.874mb  | 66.787ms  | ±0.82%  |
| RendererBench     | benchMultiColumn                                 |     | 3    | 3   | 10.305mb | 85.193ms  | ±0.88%  |
| RendererBench     | benchRichTypography                              |     | 3    | 3   | 11.685mb | 126.596ms | ±0.46%  |
| ReadPdfBench      | benchPhpdftk1Page                                |     | 3    | 5   | 4.203mb  | 1.241ms   | ±1.21%  |
| ReadPdfBench      | benchPhpdftk10Pages                              |     | 3    | 5   | 4.203mb  | 1.666ms   | ±0.70%  |
| ReadPdfBench      | benchPhpdftk100Pages                             |     | 3    | 5   | 4.561mb  | 6.093ms   | ±1.38%  |
| ReadPdfBench      | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.204mb  | 2.030ms   | ±1.08%  |
| ReadPdfBench      | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.203mb  | 1.379ms   | ±1.50%  |
| ReadPdfBench      | benchSmalot1Page                                 |     | 3    | 5   | 4.709mb  | 2.030ms   | ±1.58%  |
| ReadPdfBench      | benchSmalot10Pages                               |     | 3    | 5   | 4.810mb  | 2.354ms   | ±0.29%  |
| ReadPdfBench      | benchSmalot100Pages                              |     | 3    | 5   | 6.567mb  | 5.732ms   | ±0.74%  |
| ReadPdfBench      | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.204mb  | 549.167μs | ±0.90%  |
| ReadPdfBench      | benchSmalotXrefStream                            |     | 3    | 5   | 4.705mb  | 1.906ms   | ±0.89%  |
| ReadPdfBench      | benchFpdi1Page                                   |     | 3    | 5   | 4.812mb  | 1.901ms   | ±1.05%  |
| ReadPdfBench      | benchFpdi10Pages                                 |     | 3    | 5   | 4.813mb  | 2.829ms   | ±0.87%  |
| ReadPdfBench      | benchFpdi100Pages                                |     | 3    | 5   | 5.484mb  | 29.436ms  | ±0.81%  |
| ReadPdfBench      | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.832mb  | 2.996ms   | ±0.93%  |
| ReadPdfBench      | benchFpdiXrefStream                              |     | 3    | 5   | 4.765mb  | 1.498ms   | ±0.77%  |
| ReadPdfBench      | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.968mb  | 7.205ms   | ±12.28% |
| ReadPdfBench      | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.940mb  | 5.429ms   | ±0.92%  |
| ReadPdfBench      | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.918mb  | 3.831ms   | ±1.16%  |
| ReadPdfBench      | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.204mb  | 3.212μs   | ±2.89%  |
| ReadPdfBench      | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.307mb  | 6.207ms   | ±0.56%  |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```