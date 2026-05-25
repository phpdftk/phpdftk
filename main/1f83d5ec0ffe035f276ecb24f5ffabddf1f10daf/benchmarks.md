# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-25 00:42:21 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.524ms | 2.443ms | 2.688ms | 4.614ms | 6.909ms |
| FPDF | 819.241μs | 906.858μs | 995.631μs | 1.606ms | 2.312ms |
| TCPDF | 9.986ms | 10.894ms | 11.815ms | 19.139ms | 28.241ms |
| mPDF | 25.493ms | 29.217ms | 32.518ms | 60.243ms | 95.259ms |
| Dompdf | 11.100ms | 15.202ms | 19.919ms | 65.645ms | 147.314ms |

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
| phpdftk | 3.267ms | 3.420ms | 3.707ms | 5.625ms | 8.282ms |
| FPDF | 1.099ms | 1.167ms | 1.285ms | 1.962ms | 2.810ms |
| TCPDF | 14.586ms | 15.603ms | 16.598ms | 25.190ms | 35.277ms |

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
| Pdf (Level 3) | 3.191ms | 4.095ms | 12.009ms |
| PdfDoc (Level 2) | 2.503ms | 2.987ms | 7.129ms |
| PdfWriter (Level 1) | 2.241ms | 2.660ms | 6.848ms |

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
| Pdf (Level 3) | 4.116ms | 11.423ms | 44.339ms |
| PdfDoc (Level 2) | 3.575ms | 9.485ms | — |

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
| Pdf (Level 3) | 3.910ms | 11.398ms | 44.743ms |
| PdfDoc (Level 2) | 3.095ms | 7.136ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.893mb | 6.445mb | 8.889mb |
| PdfDoc (Level 2) | 5.689mb | 6.183mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.106ms | 1.666ms | 6.075ms |
| smalot/pdfparser | 1.981ms | 2.319ms | 5.461ms |
| setasign/fpdi | 1.855ms | 2.657ms | 27.825ms |

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
| phpdftk | 2.008ms | 1.340ms |
| smalot/pdfparser | FAIL | 1.880ms |
| setasign/fpdi | 2.806ms | FAIL |

---

## Raw phpbench Output

```
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| benchmark         | subject                                          | set | revs | its | mem_peak | mode      | rstdev  |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+
| WriterLevelsBench | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.332mb  | 2.241ms   | ±1.36%  |
| WriterLevelsBench | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.491mb  | 2.660ms   | ±0.54%  |
| WriterLevelsBench | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.066mb  | 6.848ms   | ±0.45%  |
| WriterLevelsBench | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.574mb  | 2.503ms   | ±0.68%  |
| WriterLevelsBench | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.732mb  | 2.987ms   | ±0.72%  |
| WriterLevelsBench | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.301mb  | 7.129ms   | ±0.65%  |
| WriterLevelsBench | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.910mb  | 3.191ms   | ±0.68%  |
| WriterLevelsBench | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.074mb  | 4.095ms   | ±1.04%  |
| WriterLevelsBench | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.750mb  | 12.009ms  | ±0.40%  |
| EncodingBench     | benchEncodeParagraph                             |     | 50   | 5   | 4.203mb  | 42.427μs  | ±0.98%  |
| EncodingBench     | benchShowTextThroughContentStream                |     | 50   | 5   | 6.448mb  | 234.195μs | ±1.07%  |
| StylingBench      | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.655mb  | 8.526ms   | ±0.48%  |
| StylingBench      | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.470mb  | 8.152ms   | ±0.60%  |
| StylingBench      | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.679mb  | 8.220ms   | ±0.42%  |
| BoxGeneratorBench | benchSmallBlogPost                               |     | 5    | 3   | 6.321mb  | 9.330ms   | ±0.55%  |
| BoxGeneratorBench | benchMediumArticle                               |     | 5    | 3   | 10.282mb | 76.264ms  | ±0.23%  |
| BoxGeneratorBench | benchLargeDocumentationPage                      |     | 5    | 3   | 28.287mb | 377.756ms | ±1.08%  |
| MemoryBench       | benchPhpdftk1Page                                |     | 2    | 3   | 5.316mb  | 3.267ms   | ±0.73%  |
| MemoryBench       | benchPhpdftk5Pages                               |     | 2    | 3   | 5.362mb  | 3.420ms   | ±2.00%  |
| MemoryBench       | benchPhpdftk10Pages                              |     | 2    | 3   | 5.422mb  | 3.707ms   | ±0.70%  |
| MemoryBench       | benchPhpdftk50Pages                              |     | 2    | 3   | 5.914mb  | 5.625ms   | ±1.01%  |
| MemoryBench       | benchPhpdftk100Pages                             |     | 2    | 3   | 6.513mb  | 8.282ms   | ±0.26%  |
| MemoryBench       | benchTcpdf1Page                                  |     | 2    | 3   | 12.453mb | 14.586ms  | ±0.40%  |
| MemoryBench       | benchTcpdf5Pages                                 |     | 2    | 3   | 12.453mb | 15.603ms  | ±0.30%  |
| MemoryBench       | benchTcpdf10Pages                                |     | 2    | 3   | 12.453mb | 16.598ms  | ±0.42%  |
| MemoryBench       | benchTcpdf50Pages                                |     | 2    | 3   | 12.453mb | 25.190ms  | ±4.72%  |
| MemoryBench       | benchTcpdf100Pages                               |     | 2    | 3   | 12.453mb | 35.277ms  | ±0.35%  |
| MemoryBench       | benchFpdf1Page                                   |     | 2    | 3   | 4.374mb  | 1.099ms   | ±0.62%  |
| MemoryBench       | benchFpdf5Pages                                  |     | 2    | 3   | 4.374mb  | 1.167ms   | ±1.91%  |
| MemoryBench       | benchFpdf10Pages                                 |     | 2    | 3   | 4.374mb  | 1.285ms   | ±1.11%  |
| MemoryBench       | benchFpdf50Pages                                 |     | 2    | 3   | 4.403mb  | 1.962ms   | ±0.62%  |
| MemoryBench       | benchFpdf100Pages                                |     | 2    | 3   | 4.463mb  | 2.810ms   | ±0.77%  |
| TablesBench       | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.261mb  | 4.116ms   | ±0.11%  |
| TablesBench       | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.056mb  | 11.423ms  | ±0.77%  |
| TablesBench       | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.465mb | 44.339ms  | ±0.30%  |
| TablesBench       | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.074mb  | 3.575ms   | ±2.27%  |
| TablesBench       | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.889mb  | 9.485ms   | ±0.77%  |
| GeneratePdfBench  | benchPhpdftk1Page                                |     | 3    | 5   | 5.829mb  | 2.208ms   | ±1.09%  |
| GeneratePdfBench  | benchPhpdftk5Pages                               |     | 3    | 5   | 5.889mb  | 2.443ms   | ±1.04%  |
| GeneratePdfBench  | benchPhpdftk10Pages                              |     | 3    | 5   | 5.975mb  | 2.688ms   | ±0.56%  |
| GeneratePdfBench  | benchPhpdftk50Pages                              |     | 3    | 5   | 6.610mb  | 4.614ms   | ±0.96%  |
| GeneratePdfBench  | benchPhpdftk100Pages                             |     | 3    | 5   | 7.432mb  | 6.909ms   | ±0.88%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.210mb  | 3.335ms   | ±0.87%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.285mb  | 3.704ms   | ±0.59%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.713mb  | 13.013ms  | ±8.12%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.254mb  | 3.530ms   | ±0.55%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.617mb  | 2.304ms   | ±1.50%  |
| GeneratePdfBench  | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.612mb  | 591.948μs | ±0.83%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.047mb  | 2.901ms   | ±0.66%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.141mb  | 3.476ms   | ±0.96%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.061mb  | 2.940ms   | ±3.40%  |
| GeneratePdfBench  | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.117mb  | 175.771ms | ±27.04% |
| GeneratePdfBench  | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.179mb  | 3.429ms   | ±0.69%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.790mb  | 5.739ms   | ±32.75% |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.926mb  | 5.875ms   | ±0.59%  |
| GeneratePdfBench  | benchTcpdf1Page                                  |     | 3    | 5   | 12.878mb | 9.986ms   | ±0.62%  |
| GeneratePdfBench  | benchTcpdf5Pages                                 |     | 3    | 5   | 12.878mb | 10.894ms  | ±0.64%  |
| GeneratePdfBench  | benchTcpdf10Pages                                |     | 3    | 5   | 12.878mb | 11.815ms  | ±0.25%  |
| GeneratePdfBench  | benchTcpdf50Pages                                |     | 3    | 5   | 12.878mb | 19.139ms  | ±0.36%  |
| GeneratePdfBench  | benchTcpdf100Pages                               |     | 3    | 5   | 12.878mb | 28.241ms  | ±0.45%  |
| GeneratePdfBench  | benchFpdf1Page                                   |     | 3    | 5   | 5.040mb  | 819.241μs | ±1.84%  |
| GeneratePdfBench  | benchFpdf5Pages                                  |     | 3    | 5   | 5.040mb  | 906.858μs | ±1.10%  |
| GeneratePdfBench  | benchFpdf10Pages                                 |     | 3    | 5   | 5.040mb  | 995.631μs | ±1.07%  |
| GeneratePdfBench  | benchFpdf50Pages                                 |     | 3    | 5   | 5.040mb  | 1.606ms   | ±0.76%  |
| GeneratePdfBench  | benchFpdf100Pages                                |     | 3    | 5   | 5.042mb  | 2.312ms   | ±0.54%  |
| GeneratePdfBench  | benchMpdf1Page                                   |     | 3    | 5   | 17.590mb | 25.493ms  | ±2.09%  |
| GeneratePdfBench  | benchMpdf5Pages                                  |     | 3    | 5   | 17.649mb | 29.217ms  | ±8.16%  |
| GeneratePdfBench  | benchMpdf10Pages                                 |     | 3    | 5   | 17.687mb | 32.518ms  | ±0.38%  |
| GeneratePdfBench  | benchMpdf50Pages                                 |     | 3    | 5   | 17.980mb | 60.243ms  | ±0.53%  |
| GeneratePdfBench  | benchMpdf100Pages                                |     | 3    | 5   | 18.342mb | 95.259ms  | ±0.58%  |
| GeneratePdfBench  | benchDompdf1Page                                 |     | 3    | 5   | 9.323mb  | 11.100ms  | ±2.86%  |
| GeneratePdfBench  | benchDompdf5Pages                                |     | 3    | 5   | 9.543mb  | 15.202ms  | ±0.55%  |
| GeneratePdfBench  | benchDompdf10Pages                               |     | 3    | 5   | 9.864mb  | 19.919ms  | ±0.49%  |
| GeneratePdfBench  | benchDompdf50Pages                               |     | 3    | 5   | 12.557mb | 65.645ms  | ±1.39%  |
| GeneratePdfBench  | benchDompdf100Pages                              |     | 3    | 5   | 15.920mb | 147.314ms | ±0.86%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.991mb  | 4.821ms   | ±0.55%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.428mb  | 53.607ms  | ±0.62%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.612mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.612mb  | 1.667μs   | ±0.00%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.612mb  | 1.679μs   | ±86.36% |
| GeneratePdfBench  | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.024mb  | 337.657ms | ±37.76% |
| GeneratePdfBench  | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.612mb  | 467.315μs | ±1.50%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.405mb  | 2.913ms   | ±0.29%  |
| GeneratePdfBench  | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.003mb  | 3.141ms   | ±1.14%  |
| GeneratePdfBench  | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.612mb  | 12.534ms  | ±6.07%  |
| GeneratePdfBench  | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.612mb  | 81.244ms  | ±1.06%  |
| GeneratePdfBench  | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.612mb  | 14.785ms  | ±4.44%  |
| GeneratePdfBench  | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.612mb  | 25.856ms  | ±0.83%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.831mb  | 276.767ms | ±22.28% |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.139mb  | 13.820ms  | ±0.64%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.112mb  | 13.763ms  | ±0.79%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.113mb  | 13.933ms  | ±0.93%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.137mb  | 13.879ms  | ±0.49%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.254mb  | 14.277ms  | ±0.71%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.917mb  | 2.769ms   | ±0.74%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.116mb  | 13.871ms  | ±0.34%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.175mb  | 13.984ms  | ±0.29%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.069mb  | 13.524ms  | ±0.21%  |
| ListsBench        | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.893mb  | 3.910ms   | ±0.58%  |
| ListsBench        | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.445mb  | 11.398ms  | ±0.83%  |
| ListsBench        | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.889mb  | 44.743ms  | ±4.62%  |
| ListsBench        | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.689mb  | 3.095ms   | ±0.37%  |
| ListsBench        | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.183mb  | 7.136ms   | ±0.39%  |
| RendererBench     | benchShortDocument                               |     | 3    | 3   | 10.095mb | 36.402ms  | ±1.17%  |
| RendererBench     | benchMediumArticle                               |     | 3    | 3   | 14.072mb | 141.330ms | ±0.48%  |
| RendererBench     | benchLongReport                                  |     | 3    | 3   | 29.944mb | 551.401ms | ±0.59%  |
| RendererBench     | benchRealFaceMatching                            |     | 3    | 3   | 21.585mb | 122.833ms | ±0.39%  |
| RendererBench     | benchPageMarginBoxes                             |     | 3    | 3   | 26.714mb | 136.718ms | ±0.85%  |
| RendererBench     | benchFloats                                      |     | 3    | 3   | 9.984mb  | 71.723ms  | ±0.67%  |
| RendererBench     | benchMultiColumn                                 |     | 3    | 3   | 10.453mb | 91.316ms  | ±0.33%  |
| RendererBench     | benchRichTypography                              |     | 3    | 3   | 12.152mb | 135.206ms | ±0.39%  |
| ReadPdfBench      | benchPhpdftk1Page                                |     | 3    | 5   | 4.203mb  | 1.216ms   | ±1.05%  |
| ReadPdfBench      | benchPhpdftk10Pages                              |     | 3    | 5   | 4.203mb  | 1.666ms   | ±0.45%  |
| ReadPdfBench      | benchPhpdftk100Pages                             |     | 3    | 5   | 4.561mb  | 6.075ms   | ±1.15%  |
| ReadPdfBench      | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.204mb  | 2.008ms   | ±0.44%  |
| ReadPdfBench      | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.203mb  | 1.340ms   | ±0.98%  |
| ReadPdfBench      | benchSmalot1Page                                 |     | 3    | 5   | 4.709mb  | 1.981ms   | ±1.76%  |
| ReadPdfBench      | benchSmalot10Pages                               |     | 3    | 5   | 4.810mb  | 2.319ms   | ±0.72%  |
| ReadPdfBench      | benchSmalot100Pages                              |     | 3    | 5   | 6.567mb  | 5.461ms   | ±0.58%  |
| ReadPdfBench      | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.204mb  | 571.073μs | ±2.48%  |
| ReadPdfBench      | benchSmalotXrefStream                            |     | 3    | 5   | 4.705mb  | 1.880ms   | ±0.67%  |
| ReadPdfBench      | benchFpdi1Page                                   |     | 3    | 5   | 4.812mb  | 1.855ms   | ±0.84%  |
| ReadPdfBench      | benchFpdi10Pages                                 |     | 3    | 5   | 4.813mb  | 2.657ms   | ±0.54%  |
| ReadPdfBench      | benchFpdi100Pages                                |     | 3    | 5   | 5.484mb  | 27.825ms  | ±0.64%  |
| ReadPdfBench      | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.832mb  | 2.806ms   | ±0.43%  |
| ReadPdfBench      | benchFpdiXrefStream                              |     | 3    | 5   | 4.765mb  | 1.474ms   | ±2.06%  |
| ReadPdfBench      | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.968mb  | 7.028ms   | ±0.28%  |
| ReadPdfBench      | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.940mb  | 5.289ms   | ±0.55%  |
| ReadPdfBench      | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.918mb  | 3.751ms   | ±1.52%  |
| ReadPdfBench      | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.204mb  | 3.600μs   | ±4.54%  |
| ReadPdfBench      | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.307mb  | 6.106ms   | ±0.87%  |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+---------+

```