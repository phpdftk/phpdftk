# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-24 22:55:24 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.089ms | 2.508ms | 2.713ms | 4.673ms | 6.892ms |
| FPDF | 820.660μs | 902.085μs | 3.266ms | 1.578ms | 2.340ms |
| TCPDF | 9.841ms | 10.840ms | 11.923ms | 20.490ms | 31.094ms |
| mPDF | 24.851ms | 28.963ms | 33.016ms | 65.033ms | 105.307ms |
| Dompdf | 11.212ms | 15.842ms | 21.388ms | 72.568ms | 160.352ms |

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
| phpdftk | 3.290ms | 3.495ms | 3.772ms | 5.763ms | 8.462ms |
| FPDF | 1.060ms | 1.161ms | 1.254ms | 1.932ms | 2.803ms |
| TCPDF | 14.441ms | 15.590ms | 16.617ms | 26.377ms | 38.590ms |

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
| Pdf (Level 3) | 3.232ms | 4.117ms | 12.541ms |
| PdfDoc (Level 2) | 2.523ms | 3.035ms | 7.196ms |
| PdfWriter (Level 1) | 2.260ms | 2.726ms | 6.932ms |

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
| Pdf (Level 3) | 4.189ms | 11.765ms | 45.415ms |
| PdfDoc (Level 2) | 3.607ms | 9.662ms | — |

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
| Pdf (Level 3) | 3.880ms | 11.326ms | 44.165ms |
| PdfDoc (Level 2) | 3.151ms | 7.094ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.893mb | 6.445mb | 8.889mb |
| PdfDoc (Level 2) | 5.689mb | 6.183mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.130ms | 1.675ms | 6.091ms |
| smalot/pdfparser | 1.997ms | 2.368ms | 5.725ms |
| setasign/fpdi | 1.896ms | 2.784ms | 29.701ms |

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
| phpdftk | 2.034ms | 1.358ms |
| smalot/pdfparser | FAIL | 1.915ms |
| setasign/fpdi | 2.933ms | FAIL |

---

## Raw phpbench Output

```
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark         | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| WriterLevelsBench | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.332mb  | 2.260ms   | ±1.41%   |
| WriterLevelsBench | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.491mb  | 2.726ms   | ±1.55%   |
| WriterLevelsBench | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.066mb  | 6.932ms   | ±0.59%   |
| WriterLevelsBench | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.574mb  | 2.523ms   | ±1.15%   |
| WriterLevelsBench | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.732mb  | 3.035ms   | ±0.90%   |
| WriterLevelsBench | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.301mb  | 7.196ms   | ±0.81%   |
| WriterLevelsBench | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.910mb  | 3.232ms   | ±0.86%   |
| WriterLevelsBench | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.074mb  | 4.117ms   | ±0.98%   |
| WriterLevelsBench | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.750mb  | 12.541ms  | ±0.31%   |
| EncodingBench     | benchEncodeParagraph                             |     | 50   | 5   | 4.203mb  | 41.592μs  | ±1.29%   |
| EncodingBench     | benchShowTextThroughContentStream                |     | 50   | 5   | 6.448mb  | 240.739μs | ±1.09%   |
| StylingBench      | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.655mb  | 8.696ms   | ±0.60%   |
| StylingBench      | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.470mb  | 8.182ms   | ±0.46%   |
| StylingBench      | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.679mb  | 8.392ms   | ±0.45%   |
| BoxGeneratorBench | benchSmallBlogPost                               |     | 5    | 3   | 6.309mb  | 8.923ms   | ±0.61%   |
| BoxGeneratorBench | benchMediumArticle                               |     | 5    | 3   | 10.270mb | 71.785ms  | ±0.82%   |
| BoxGeneratorBench | benchLargeDocumentationPage                      |     | 5    | 3   | 28.275mb | 353.985ms | ±1.23%   |
| MemoryBench       | benchPhpdftk1Page                                |     | 2    | 3   | 5.316mb  | 3.290ms   | ±0.89%   |
| MemoryBench       | benchPhpdftk5Pages                               |     | 2    | 3   | 5.362mb  | 3.495ms   | ±0.65%   |
| MemoryBench       | benchPhpdftk10Pages                              |     | 2    | 3   | 5.422mb  | 3.772ms   | ±0.41%   |
| MemoryBench       | benchPhpdftk50Pages                              |     | 2    | 3   | 5.914mb  | 5.763ms   | ±0.19%   |
| MemoryBench       | benchPhpdftk100Pages                             |     | 2    | 3   | 6.513mb  | 8.462ms   | ±0.60%   |
| MemoryBench       | benchTcpdf1Page                                  |     | 2    | 3   | 12.453mb | 14.441ms  | ±1.43%   |
| MemoryBench       | benchTcpdf5Pages                                 |     | 2    | 3   | 12.453mb | 15.590ms  | ±1.14%   |
| MemoryBench       | benchTcpdf10Pages                                |     | 2    | 3   | 12.453mb | 16.617ms  | ±0.15%   |
| MemoryBench       | benchTcpdf50Pages                                |     | 2    | 3   | 12.453mb | 26.377ms  | ±0.93%   |
| MemoryBench       | benchTcpdf100Pages                               |     | 2    | 3   | 12.453mb | 38.590ms  | ±0.20%   |
| MemoryBench       | benchFpdf1Page                                   |     | 2    | 3   | 4.374mb  | 1.060ms   | ±1.27%   |
| MemoryBench       | benchFpdf5Pages                                  |     | 2    | 3   | 4.374mb  | 1.161ms   | ±0.73%   |
| MemoryBench       | benchFpdf10Pages                                 |     | 2    | 3   | 4.374mb  | 1.254ms   | ±0.67%   |
| MemoryBench       | benchFpdf50Pages                                 |     | 2    | 3   | 4.403mb  | 1.932ms   | ±0.36%   |
| MemoryBench       | benchFpdf100Pages                                |     | 2    | 3   | 4.463mb  | 2.803ms   | ±0.46%   |
| TablesBench       | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.261mb  | 4.189ms   | ±0.65%   |
| TablesBench       | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.056mb  | 11.765ms  | ±0.40%   |
| TablesBench       | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.465mb | 45.415ms  | ±0.44%   |
| TablesBench       | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.074mb  | 3.607ms   | ±0.42%   |
| TablesBench       | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.889mb  | 9.662ms   | ±0.85%   |
| GeneratePdfBench  | benchPhpdftk1Page                                |     | 3    | 5   | 5.829mb  | 2.251ms   | ±0.84%   |
| GeneratePdfBench  | benchPhpdftk5Pages                               |     | 3    | 5   | 5.889mb  | 2.508ms   | ±0.47%   |
| GeneratePdfBench  | benchPhpdftk10Pages                              |     | 3    | 5   | 5.975mb  | 2.713ms   | ±1.24%   |
| GeneratePdfBench  | benchPhpdftk50Pages                              |     | 3    | 5   | 6.610mb  | 4.673ms   | ±9.24%   |
| GeneratePdfBench  | benchPhpdftk100Pages                             |     | 3    | 5   | 7.432mb  | 6.892ms   | ±0.54%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.210mb  | 3.456ms   | ±0.95%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.285mb  | 3.801ms   | ±1.00%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.713mb  | 12.383ms  | ±15.19%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.254mb  | 3.601ms   | ±0.88%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.617mb  | 2.338ms   | ±0.77%   |
| GeneratePdfBench  | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.612mb  | 641.226μs | ±2.55%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.047mb  | 2.934ms   | ±0.62%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.141mb  | 3.608ms   | ±0.47%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.061mb  | 2.984ms   | ±0.34%   |
| GeneratePdfBench  | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.117mb  | 228.957ms | ±22.68%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.179mb  | 3.522ms   | ±1.26%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.790mb  | 5.755ms   | ±16.12%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.926mb  | 5.988ms   | ±0.66%   |
| GeneratePdfBench  | benchTcpdf1Page                                  |     | 3    | 5   | 12.878mb | 9.841ms   | ±0.36%   |
| GeneratePdfBench  | benchTcpdf5Pages                                 |     | 3    | 5   | 12.878mb | 10.840ms  | ±0.61%   |
| GeneratePdfBench  | benchTcpdf10Pages                                |     | 3    | 5   | 12.878mb | 11.923ms  | ±0.75%   |
| GeneratePdfBench  | benchTcpdf50Pages                                |     | 3    | 5   | 12.878mb | 20.490ms  | ±0.88%   |
| GeneratePdfBench  | benchTcpdf100Pages                               |     | 3    | 5   | 12.878mb | 31.094ms  | ±0.36%   |
| GeneratePdfBench  | benchFpdf1Page                                   |     | 3    | 5   | 5.040mb  | 820.660μs | ±1.63%   |
| GeneratePdfBench  | benchFpdf5Pages                                  |     | 3    | 5   | 5.040mb  | 902.085μs | ±9.90%   |
| GeneratePdfBench  | benchFpdf10Pages                                 |     | 3    | 5   | 5.040mb  | 3.266ms   | ±141.85% |
| GeneratePdfBench  | benchFpdf50Pages                                 |     | 3    | 5   | 5.040mb  | 1.578ms   | ±1.18%   |
| GeneratePdfBench  | benchFpdf100Pages                                |     | 3    | 5   | 5.042mb  | 2.340ms   | ±0.78%   |
| GeneratePdfBench  | benchMpdf1Page                                   |     | 3    | 5   | 17.590mb | 24.851ms  | ±2.26%   |
| GeneratePdfBench  | benchMpdf5Pages                                  |     | 3    | 5   | 17.649mb | 28.963ms  | ±0.62%   |
| GeneratePdfBench  | benchMpdf10Pages                                 |     | 3    | 5   | 17.687mb | 33.016ms  | ±0.59%   |
| GeneratePdfBench  | benchMpdf50Pages                                 |     | 3    | 5   | 17.980mb | 65.033ms  | ±0.35%   |
| GeneratePdfBench  | benchMpdf100Pages                                |     | 3    | 5   | 18.342mb | 105.307ms | ±0.77%   |
| GeneratePdfBench  | benchDompdf1Page                                 |     | 3    | 5   | 9.323mb  | 11.212ms  | ±2.96%   |
| GeneratePdfBench  | benchDompdf5Pages                                |     | 3    | 5   | 9.543mb  | 15.842ms  | ±0.46%   |
| GeneratePdfBench  | benchDompdf10Pages                               |     | 3    | 5   | 9.864mb  | 21.388ms  | ±4.20%   |
| GeneratePdfBench  | benchDompdf50Pages                               |     | 3    | 5   | 12.557mb | 72.568ms  | ±1.11%   |
| GeneratePdfBench  | benchDompdf100Pages                              |     | 3    | 5   | 15.920mb | 160.352ms | ±0.23%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.991mb  | 4.952ms   | ±0.70%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.428mb  | 50.335ms  | ±1.21%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.612mb  | 1.333μs   | ±15.81%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.612mb  | 1.322μs   | ±13.61%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.612mb  | 1.333μs   | ±15.81%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.024mb  | 201.929ms | ±24.93%  |
| GeneratePdfBench  | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.612mb  | 447.003μs | ±0.65%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.405mb  | 2.966ms   | ±0.73%   |
| GeneratePdfBench  | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.003mb  | 3.151ms   | ±0.54%   |
| GeneratePdfBench  | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.612mb  | 11.934ms  | ±7.53%   |
| GeneratePdfBench  | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.612mb  | 84.400ms  | ±0.77%   |
| GeneratePdfBench  | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.612mb  | 14.131ms  | ±0.91%   |
| GeneratePdfBench  | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.612mb  | 24.693ms  | ±0.21%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.831mb  | 152.505ms | ±27.63%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.139mb  | 13.363ms  | ±0.47%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.112mb  | 13.188ms  | ±0.56%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.113mb  | 13.258ms  | ±0.45%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.137mb  | 13.335ms  | ±0.36%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.254mb  | 13.774ms  | ±0.59%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.917mb  | 2.834ms   | ±0.87%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.116mb  | 13.267ms  | ±0.56%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.175mb  | 13.383ms  | ±0.54%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.069mb  | 13.089ms  | ±0.76%   |
| ListsBench        | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.893mb  | 3.880ms   | ±0.29%   |
| ListsBench        | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.445mb  | 11.326ms  | ±0.18%   |
| ListsBench        | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.889mb  | 44.165ms  | ±0.61%   |
| ListsBench        | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.689mb  | 3.151ms   | ±0.75%   |
| ListsBench        | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.183mb  | 7.094ms   | ±3.32%   |
| RendererBench     | benchShortDocument                               |     | 3    | 3   | 9.474mb  | 33.986ms  | ±2.94%   |
| RendererBench     | benchMediumArticle                               |     | 3    | 3   | 13.676mb | 133.825ms | ±1.12%   |
| RendererBench     | benchLongReport                                  |     | 3    | 3   | 29.548mb | 517.654ms | ±0.89%   |
| RendererBench     | benchRealFaceMatching                            |     | 3    | 3   | 21.511mb | 118.557ms | ±0.07%   |
| RendererBench     | benchPageMarginBoxes                             |     | 3    | 3   | 26.640mb | 132.707ms | ±1.07%   |
| RendererBench     | benchFloats                                      |     | 3    | 3   | 9.945mb  | 68.449ms  | ±0.69%   |
| RendererBench     | benchMultiColumn                                 |     | 3    | 3   | 10.380mb | 87.777ms  | ±0.52%   |
| RendererBench     | benchRichTypography                              |     | 3    | 3   | 11.756mb | 127.967ms | ±0.69%   |
| ReadPdfBench      | benchPhpdftk1Page                                |     | 3    | 5   | 4.203mb  | 1.235ms   | ±0.88%   |
| ReadPdfBench      | benchPhpdftk10Pages                              |     | 3    | 5   | 4.203mb  | 1.675ms   | ±0.59%   |
| ReadPdfBench      | benchPhpdftk100Pages                             |     | 3    | 5   | 4.561mb  | 6.091ms   | ±0.86%   |
| ReadPdfBench      | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.204mb  | 2.034ms   | ±1.00%   |
| ReadPdfBench      | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.203mb  | 1.358ms   | ±0.90%   |
| ReadPdfBench      | benchSmalot1Page                                 |     | 3    | 5   | 4.709mb  | 1.997ms   | ±0.40%   |
| ReadPdfBench      | benchSmalot10Pages                               |     | 3    | 5   | 4.810mb  | 2.368ms   | ±0.65%   |
| ReadPdfBench      | benchSmalot100Pages                              |     | 3    | 5   | 6.567mb  | 5.725ms   | ±0.50%   |
| ReadPdfBench      | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.204mb  | 554.817μs | ±1.34%   |
| ReadPdfBench      | benchSmalotXrefStream                            |     | 3    | 5   | 4.705mb  | 1.915ms   | ±1.11%   |
| ReadPdfBench      | benchFpdi1Page                                   |     | 3    | 5   | 4.812mb  | 1.896ms   | ±0.91%   |
| ReadPdfBench      | benchFpdi10Pages                                 |     | 3    | 5   | 4.813mb  | 2.784ms   | ±0.70%   |
| ReadPdfBench      | benchFpdi100Pages                                |     | 3    | 5   | 5.484mb  | 29.701ms  | ±1.12%   |
| ReadPdfBench      | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.832mb  | 2.933ms   | ±0.52%   |
| ReadPdfBench      | benchFpdiXrefStream                              |     | 3    | 5   | 4.765mb  | 1.484ms   | ±0.90%   |
| ReadPdfBench      | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.968mb  | 7.222ms   | ±1.76%   |
| ReadPdfBench      | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.940mb  | 5.380ms   | ±0.66%   |
| ReadPdfBench      | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.918mb  | 3.818ms   | ±0.34%   |
| ReadPdfBench      | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.204mb  | 3.376μs   | ±5.77%   |
| ReadPdfBench      | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.307mb  | 6.130ms   | ±0.70%   |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```