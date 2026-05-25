# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-25 15:19:13 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.129ms | 2.463ms | 2.698ms | 4.717ms | 6.926ms |
| FPDF | 824.881μs | 897.642μs | 992.080μs | 1.589ms | 2.331ms |
| TCPDF | 9.938ms | 10.809ms | 12.007ms | 20.460ms | 31.263ms |
| mPDF | 25.113ms | 28.922ms | 32.922ms | 64.738ms | 104.623ms |
| Dompdf | 11.272ms | 15.847ms | 21.321ms | 72.587ms | 161.302ms |

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
| phpdftk | 3.282ms | 3.464ms | 3.766ms | 5.814ms | 8.466ms |
| FPDF | 1.090ms | 1.169ms | 1.276ms | 1.930ms | 2.805ms |
| TCPDF | 14.492ms | 15.826ms | 16.672ms | 26.583ms | 38.541ms |

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
| Pdf (Level 3) | 3.221ms | 4.127ms | 15.230ms |
| PdfDoc (Level 2) | 2.579ms | 3.034ms | 7.267ms |
| PdfWriter (Level 1) | 2.262ms | 2.724ms | 6.974ms |

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
| Pdf (Level 3) | 4.177ms | 11.668ms | 45.882ms |
| PdfDoc (Level 2) | 3.618ms | 9.607ms | — |

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
| Pdf (Level 3) | 3.919ms | 11.359ms | 44.501ms |
| PdfDoc (Level 2) | 3.139ms | 7.139ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.893mb | 6.445mb | 8.889mb |
| PdfDoc (Level 2) | 5.689mb | 6.183mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.214ms | 1.675ms | 6.033ms |
| smalot/pdfparser | 1.991ms | 2.359ms | 5.745ms |
| setasign/fpdi | 1.923ms | 2.786ms | 29.672ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.307mb | 4.203mb | 4.561mb |
| smalot/pdfparser | 4.709mb | 4.810mb | 6.567mb |
| setasign/fpdi | 4.738mb | 4.738mb | 5.485mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 2.036ms | 1.366ms |
| smalot/pdfparser | FAIL | 1.902ms |
| setasign/fpdi | 2.949ms | FAIL |

---

## Raw phpbench Output

```
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark         | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| WriterLevelsBench | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.332mb  | 2.262ms   | ±0.89%  |
| WriterLevelsBench | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.491mb  | 2.724ms   | ±0.80%  |
| WriterLevelsBench | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.066mb  | 6.974ms   | ±0.25%  |
| WriterLevelsBench | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.574mb  | 2.579ms   | ±1.18%  |
| WriterLevelsBench | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.732mb  | 3.034ms   | ±0.98%  |
| WriterLevelsBench | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.301mb  | 7.267ms   | ±0.46%  |
| WriterLevelsBench | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.910mb  | 3.221ms   | ±1.25%  |
| WriterLevelsBench | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.074mb  | 4.127ms   | ±1.08%  |
| WriterLevelsBench | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.750mb  | 15.230ms  | ±74.05% |
| EncodingBench     | benchEncodeParagraph                             |     | 50   | 5   | 4.203mb  | 41.416μs  | ±1.16%  |
| EncodingBench     | benchShowTextThroughContentStream                |     | 50   | 5   | 6.448mb  | 241.929μs | ±0.46%  |
| StylingBench      | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.655mb  | 8.669ms   | ±0.89%  |
| StylingBench      | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.470mb  | 8.233ms   | ±0.73%  |
| StylingBench      | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.679mb  | 8.374ms   | ±0.28%  |
| BoxGeneratorBench | benchSmallBlogPost                               |     | 5    | 3   | 6.373mb  | 9.792ms   | ±0.72%  |
| BoxGeneratorBench | benchMediumArticle                               |     | 5    | 3   | 10.333mb | 79.326ms  | ±1.46%  |
| BoxGeneratorBench | benchLargeDocumentationPage                      |     | 5    | 3   | 28.339mb | 396.695ms | ±0.34%  |
| MemoryBench       | benchPhpdftk1Page                                |     | 2    | 3   | 5.316mb  | 3.282ms   | ±1.13%  |
| MemoryBench       | benchPhpdftk5Pages                               |     | 2    | 3   | 5.362mb  | 3.464ms   | ±0.45%  |
| MemoryBench       | benchPhpdftk10Pages                              |     | 2    | 3   | 5.422mb  | 3.766ms   | ±1.35%  |
| MemoryBench       | benchPhpdftk50Pages                              |     | 2    | 3   | 5.914mb  | 5.814ms   | ±0.48%  |
| MemoryBench       | benchPhpdftk100Pages                             |     | 2    | 3   | 6.513mb  | 8.466ms   | ±0.42%  |
| MemoryBench       | benchTcpdf1Page                                  |     | 2    | 3   | 12.453mb | 14.492ms  | ±1.05%  |
| MemoryBench       | benchTcpdf5Pages                                 |     | 2    | 3   | 12.453mb | 15.826ms  | ±1.85%  |
| MemoryBench       | benchTcpdf10Pages                                |     | 2    | 3   | 12.453mb | 16.672ms  | ±0.58%  |
| MemoryBench       | benchTcpdf50Pages                                |     | 2    | 3   | 12.453mb | 26.583ms  | ±0.41%  |
| MemoryBench       | benchTcpdf100Pages                               |     | 2    | 3   | 12.453mb | 38.541ms  | ±0.30%  |
| MemoryBench       | benchFpdf1Page                                   |     | 2    | 3   | 4.374mb  | 1.090ms   | ±1.26%  |
| MemoryBench       | benchFpdf5Pages                                  |     | 2    | 3   | 4.374mb  | 1.169ms   | ±2.47%  |
| MemoryBench       | benchFpdf10Pages                                 |     | 2    | 3   | 4.374mb  | 1.276ms   | ±1.39%  |
| MemoryBench       | benchFpdf50Pages                                 |     | 2    | 3   | 4.403mb  | 1.930ms   | ±0.43%  |
| MemoryBench       | benchFpdf100Pages                                |     | 2    | 3   | 4.463mb  | 2.805ms   | ±0.75%  |
| TablesBench       | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.261mb  | 4.177ms   | ±0.51%  |
| TablesBench       | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.056mb  | 11.668ms  | ±0.32%  |
| TablesBench       | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.465mb | 45.882ms  | ±1.36%  |
| TablesBench       | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.074mb  | 3.618ms   | ±0.89%  |
| TablesBench       | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.889mb  | 9.607ms   | ±0.66%  |
| GeneratePdfBench  | benchPhpdftk1Page                                |     | 3    | 5   | 5.829mb  | 2.255ms   | ±0.88%  |
| GeneratePdfBench  | benchPhpdftk5Pages                               |     | 3    | 5   | 5.889mb  | 2.463ms   | ±6.91%  |
| GeneratePdfBench  | benchPhpdftk10Pages                              |     | 3    | 5   | 5.975mb  | 2.698ms   | ±0.53%  |
| GeneratePdfBench  | benchPhpdftk50Pages                              |     | 3    | 5   | 6.610mb  | 4.717ms   | ±0.56%  |
| GeneratePdfBench  | benchPhpdftk100Pages                             |     | 3    | 5   | 7.432mb  | 6.926ms   | ±0.69%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.210mb  | 3.420ms   | ±0.90%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.285mb  | 3.782ms   | ±0.64%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.713mb  | 12.307ms  | ±72.31% |
| GeneratePdfBench  | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.254mb  | 3.599ms   | ±0.81%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.617mb  | 2.363ms   | ±1.00%  |
| GeneratePdfBench  | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.612mb  | 632.673μs | ±8.25%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.047mb  | 2.959ms   | ±0.34%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.141mb  | 3.602ms   | ±0.73%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.061mb  | 3.057ms   | ±1.10%  |
| GeneratePdfBench  | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.117mb  | 220.279ms | ±19.11% |
| GeneratePdfBench  | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.179mb  | 3.544ms   | ±0.34%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.790mb  | 5.775ms   | ±29.90% |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.926mb  | 5.969ms   | ±0.68%  |
| GeneratePdfBench  | benchTcpdf1Page                                  |     | 3    | 5   | 12.878mb | 9.938ms   | ±0.47%  |
| GeneratePdfBench  | benchTcpdf5Pages                                 |     | 3    | 5   | 12.878mb | 10.809ms  | ±1.62%  |
| GeneratePdfBench  | benchTcpdf10Pages                                |     | 3    | 5   | 12.878mb | 12.007ms  | ±0.94%  |
| GeneratePdfBench  | benchTcpdf50Pages                                |     | 3    | 5   | 12.878mb | 20.460ms  | ±0.52%  |
| GeneratePdfBench  | benchTcpdf100Pages                               |     | 3    | 5   | 12.878mb | 31.263ms  | ±0.51%  |
| GeneratePdfBench  | benchFpdf1Page                                   |     | 3    | 5   | 5.040mb  | 824.881μs | ±1.54%  |
| GeneratePdfBench  | benchFpdf5Pages                                  |     | 3    | 5   | 5.040mb  | 897.642μs | ±0.98%  |
| GeneratePdfBench  | benchFpdf10Pages                                 |     | 3    | 5   | 5.040mb  | 992.080μs | ±1.68%  |
| GeneratePdfBench  | benchFpdf50Pages                                 |     | 3    | 5   | 5.040mb  | 1.589ms   | ±1.26%  |
| GeneratePdfBench  | benchFpdf100Pages                                |     | 3    | 5   | 5.042mb  | 2.331ms   | ±1.07%  |
| GeneratePdfBench  | benchMpdf1Page                                   |     | 3    | 5   | 17.590mb | 25.113ms  | ±3.32%  |
| GeneratePdfBench  | benchMpdf5Pages                                  |     | 3    | 5   | 17.649mb | 28.922ms  | ±0.37%  |
| GeneratePdfBench  | benchMpdf10Pages                                 |     | 3    | 5   | 17.687mb | 32.922ms  | ±0.79%  |
| GeneratePdfBench  | benchMpdf50Pages                                 |     | 3    | 5   | 17.980mb | 64.738ms  | ±0.84%  |
| GeneratePdfBench  | benchMpdf100Pages                                |     | 3    | 5   | 18.342mb | 104.623ms | ±0.68%  |
| GeneratePdfBench  | benchDompdf1Page                                 |     | 3    | 5   | 9.323mb  | 11.272ms  | ±3.42%  |
| GeneratePdfBench  | benchDompdf5Pages                                |     | 3    | 5   | 9.543mb  | 15.847ms  | ±0.28%  |
| GeneratePdfBench  | benchDompdf10Pages                               |     | 3    | 5   | 9.864mb  | 21.321ms  | ±0.22%  |
| GeneratePdfBench  | benchDompdf50Pages                               |     | 3    | 5   | 12.557mb | 72.587ms  | ±0.46%  |
| GeneratePdfBench  | benchDompdf100Pages                              |     | 3    | 5   | 15.920mb | 161.302ms | ±0.27%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.991mb  | 4.955ms   | ±1.08%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.428mb  | 49.588ms  | ±0.77%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.612mb  | 1.594μs   | ±78.36% |
| GeneratePdfBench  | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.612mb  | 1.334μs   | ±9.52%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.612mb  | 1.463μs   | ±17.82% |
| GeneratePdfBench  | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.024mb  | 226.370ms | ±25.33% |
| GeneratePdfBench  | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.612mb  | 446.557μs | ±1.24%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.405mb  | 2.960ms   | ±0.46%  |
| GeneratePdfBench  | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.003mb  | 3.151ms   | ±0.53%  |
| GeneratePdfBench  | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.612mb  | 13.990ms  | ±4.55%  |
| GeneratePdfBench  | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.612mb  | 84.841ms  | ±0.69%  |
| GeneratePdfBench  | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.612mb  | 14.384ms  | ±4.38%  |
| GeneratePdfBench  | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.612mb  | 24.893ms  | ±0.86%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.831mb  | 248.505ms | ±24.73% |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.139mb  | 13.295ms  | ±0.65%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.112mb  | 13.229ms  | ±0.61%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.113mb  | 13.206ms  | ±0.73%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.137mb  | 13.385ms  | ±0.71%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.254mb  | 13.765ms  | ±0.63%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.917mb  | 2.809ms   | ±0.64%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.116mb  | 13.344ms  | ±4.62%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.175mb  | 13.455ms  | ±0.40%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.069mb  | 13.129ms  | ±0.76%  |
| ListsBench        | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.893mb  | 3.919ms   | ±0.54%  |
| ListsBench        | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.445mb  | 11.359ms  | ±0.63%  |
| ListsBench        | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.889mb  | 44.501ms  | ±3.94%  |
| ListsBench        | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.689mb  | 3.139ms   | ±1.43%  |
| ListsBench        | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.183mb  | 7.139ms   | ±0.31%  |
| RendererBench     | benchShortDocument                               |     | 3    | 3   | 10.165mb | 39.260ms  | ±0.72%  |
| RendererBench     | benchMediumArticle                               |     | 3    | 3   | 14.258mb | 155.335ms | ±0.72%  |
| RendererBench     | benchLongReport                                  |     | 3    | 3   | 30.131mb | 600.744ms | ±0.55%  |
| RendererBench     | benchRealFaceMatching                            |     | 3    | 3   | 21.775mb | 130.956ms | ±0.73%  |
| RendererBench     | benchPageMarginBoxes                             |     | 3    | 3   | 26.969mb | 138.789ms | ±0.83%  |
| RendererBench     | benchFloats                                      |     | 3    | 3   | 10.581mb | 76.049ms  | ±1.00%  |
| RendererBench     | benchMultiColumn                                 |     | 3    | 3   | 11.153mb | 97.792ms  | ±0.72%  |
| RendererBench     | benchFlex                                        |     | 3    | 3   | 11.120mb | 78.229ms  | ±7.55%  |
| RendererBench     | benchRichTypography                              |     | 3    | 3   | 12.338mb | 145.286ms | ±0.51%  |
| ReadPdfBench      | benchPhpdftk1Page                                |     | 3    | 5   | 4.203mb  | 1.237ms   | ±0.39%  |
| ReadPdfBench      | benchPhpdftk10Pages                              |     | 3    | 5   | 4.203mb  | 1.675ms   | ±1.50%  |
| ReadPdfBench      | benchPhpdftk100Pages                             |     | 3    | 5   | 4.561mb  | 6.033ms   | ±1.01%  |
| ReadPdfBench      | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.204mb  | 2.036ms   | ±0.75%  |
| ReadPdfBench      | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.203mb  | 1.366ms   | ±0.68%  |
| ReadPdfBench      | benchSmalot1Page                                 |     | 3    | 5   | 4.709mb  | 1.991ms   | ±0.71%  |
| ReadPdfBench      | benchSmalot10Pages                               |     | 3    | 5   | 4.810mb  | 2.359ms   | ±0.39%  |
| ReadPdfBench      | benchSmalot100Pages                              |     | 3    | 5   | 6.567mb  | 5.745ms   | ±0.63%  |
| ReadPdfBench      | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.204mb  | 547.997μs | ±1.52%  |
| ReadPdfBench      | benchSmalotXrefStream                            |     | 3    | 5   | 4.705mb  | 1.902ms   | ±0.59%  |
| ReadPdfBench      | benchFpdi1Page                                   |     | 3    | 5   | 4.738mb  | 1.923ms   | ±1.13%  |
| ReadPdfBench      | benchFpdi10Pages                                 |     | 3    | 5   | 4.738mb  | 2.786ms   | ±1.00%  |
| ReadPdfBench      | benchFpdi100Pages                                |     | 3    | 5   | 5.485mb  | 29.672ms  | ±1.76%  |
| ReadPdfBench      | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.833mb  | 2.949ms   | ±0.97%  |
| ReadPdfBench      | benchFpdiXrefStream                              |     | 3    | 5   | 4.765mb  | 1.517ms   | ±0.67%  |
| ReadPdfBench      | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.968mb  | 7.254ms   | ±0.64%  |
| ReadPdfBench      | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.940mb  | 5.402ms   | ±0.57%  |
| ReadPdfBench      | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.918mb  | 3.810ms   | ±0.88%  |
| ReadPdfBench      | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.204mb  | 3.200μs   | ±5.10%  |
| ReadPdfBench      | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.307mb  | 6.214ms   | ±0.64%  |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```