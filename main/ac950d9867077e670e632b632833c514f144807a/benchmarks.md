# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-24 23:51:44 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.035ms | 2.489ms | 2.716ms | 4.724ms | 6.917ms |
| FPDF | 807.607μs | 894.907μs | 984.590μs | 1.578ms | 2.327ms |
| TCPDF | 9.983ms | 10.895ms | 11.907ms | 20.518ms | 31.208ms |
| mPDF | 25.031ms | 29.023ms | 33.174ms | 65.644ms | 105.165ms |
| Dompdf | 11.231ms | 15.810ms | 21.389ms | 73.280ms | 162.317ms |

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
| phpdftk | 44.081ms | 4.867ms | 3.796ms | 5.858ms | 8.521ms |
| FPDF | 1.085ms | 1.168ms | 1.284ms | 1.945ms | 2.788ms |
| TCPDF | 14.528ms | 15.513ms | 16.764ms | 26.618ms | 38.564ms |

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
| Pdf (Level 3) | 3.192ms | 4.099ms | 12.538ms |
| PdfDoc (Level 2) | 2.552ms | 3.031ms | 7.236ms |
| PdfWriter (Level 1) | 2.312ms | 2.738ms | 6.933ms |

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
| Pdf (Level 3) | 4.227ms | 11.683ms | 45.515ms |
| PdfDoc (Level 2) | 3.631ms | 9.641ms | — |

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
| Pdf (Level 3) | 3.890ms | 11.452ms | 44.619ms |
| PdfDoc (Level 2) | 3.152ms | 7.091ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.893mb | 6.445mb | 8.889mb |
| PdfDoc (Level 2) | 5.689mb | 6.183mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.184ms | 1.688ms | 5.952ms |
| smalot/pdfparser | 2.009ms | 2.367ms | 5.770ms |
| setasign/fpdi | 1.920ms | 2.812ms | 29.937ms |

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
| phpdftk | 2.039ms | 1.377ms |
| smalot/pdfparser | FAIL | 1.922ms |
| setasign/fpdi | 2.956ms | FAIL |

---

## Raw phpbench Output

```
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark         | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| WriterLevelsBench | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.332mb  | 2.312ms   | ±2.28%   |
| WriterLevelsBench | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.491mb  | 2.738ms   | ±5.25%   |
| WriterLevelsBench | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.066mb  | 6.933ms   | ±0.79%   |
| WriterLevelsBench | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.574mb  | 2.552ms   | ±0.93%   |
| WriterLevelsBench | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.732mb  | 3.031ms   | ±1.29%   |
| WriterLevelsBench | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.301mb  | 7.236ms   | ±1.12%   |
| WriterLevelsBench | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.910mb  | 3.192ms   | ±7.49%   |
| WriterLevelsBench | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.074mb  | 4.099ms   | ±0.72%   |
| WriterLevelsBench | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.750mb  | 12.538ms  | ±0.64%   |
| EncodingBench     | benchEncodeParagraph                             |     | 50   | 5   | 4.203mb  | 41.576μs  | ±1.55%   |
| EncodingBench     | benchShowTextThroughContentStream                |     | 50   | 5   | 6.448mb  | 242.342μs | ±3.67%   |
| StylingBench      | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.655mb  | 8.710ms   | ±0.35%   |
| StylingBench      | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.470mb  | 8.277ms   | ±0.41%   |
| StylingBench      | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.679mb  | 8.404ms   | ±0.82%   |
| BoxGeneratorBench | benchSmallBlogPost                               |     | 5    | 3   | 6.310mb  | 9.090ms   | ±1.10%   |
| BoxGeneratorBench | benchMediumArticle                               |     | 5    | 3   | 10.270mb | 72.372ms  | ±0.66%   |
| BoxGeneratorBench | benchLargeDocumentationPage                      |     | 5    | 3   | 28.276mb | 355.882ms | ±0.71%   |
| MemoryBench       | benchPhpdftk1Page                                |     | 2    | 3   | 5.316mb  | 44.081ms  | ±70.71%  |
| MemoryBench       | benchPhpdftk5Pages                               |     | 2    | 3   | 5.362mb  | 4.867ms   | ±96.97%  |
| MemoryBench       | benchPhpdftk10Pages                              |     | 2    | 3   | 5.422mb  | 3.796ms   | ±0.90%   |
| MemoryBench       | benchPhpdftk50Pages                              |     | 2    | 3   | 5.914mb  | 5.858ms   | ±1.10%   |
| MemoryBench       | benchPhpdftk100Pages                             |     | 2    | 3   | 6.513mb  | 8.521ms   | ±0.69%   |
| MemoryBench       | benchTcpdf1Page                                  |     | 2    | 3   | 12.453mb | 14.528ms  | ±2.05%   |
| MemoryBench       | benchTcpdf5Pages                                 |     | 2    | 3   | 12.453mb | 15.513ms  | ±0.52%   |
| MemoryBench       | benchTcpdf10Pages                                |     | 2    | 3   | 12.453mb | 16.764ms  | ±0.47%   |
| MemoryBench       | benchTcpdf50Pages                                |     | 2    | 3   | 12.453mb | 26.618ms  | ±1.33%   |
| MemoryBench       | benchTcpdf100Pages                               |     | 2    | 3   | 12.453mb | 38.564ms  | ±0.27%   |
| MemoryBench       | benchFpdf1Page                                   |     | 2    | 3   | 4.374mb  | 1.085ms   | ±2.60%   |
| MemoryBench       | benchFpdf5Pages                                  |     | 2    | 3   | 4.374mb  | 1.168ms   | ±0.66%   |
| MemoryBench       | benchFpdf10Pages                                 |     | 2    | 3   | 4.374mb  | 1.284ms   | ±2.05%   |
| MemoryBench       | benchFpdf50Pages                                 |     | 2    | 3   | 4.403mb  | 1.945ms   | ±0.38%   |
| MemoryBench       | benchFpdf100Pages                                |     | 2    | 3   | 4.463mb  | 2.788ms   | ±1.13%   |
| TablesBench       | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.261mb  | 4.227ms   | ±0.50%   |
| TablesBench       | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.056mb  | 11.683ms  | ±0.40%   |
| TablesBench       | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.465mb | 45.515ms  | ±1.44%   |
| TablesBench       | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.074mb  | 3.631ms   | ±0.75%   |
| TablesBench       | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.889mb  | 9.641ms   | ±0.82%   |
| GeneratePdfBench  | benchPhpdftk1Page                                |     | 3    | 5   | 5.829mb  | 2.284ms   | ±0.81%   |
| GeneratePdfBench  | benchPhpdftk5Pages                               |     | 3    | 5   | 5.889mb  | 2.489ms   | ±7.53%   |
| GeneratePdfBench  | benchPhpdftk10Pages                              |     | 3    | 5   | 5.975mb  | 2.716ms   | ±0.57%   |
| GeneratePdfBench  | benchPhpdftk50Pages                              |     | 3    | 5   | 6.610mb  | 4.724ms   | ±0.59%   |
| GeneratePdfBench  | benchPhpdftk100Pages                             |     | 3    | 5   | 7.432mb  | 6.917ms   | ±0.73%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.210mb  | 3.422ms   | ±0.60%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.285mb  | 3.765ms   | ±1.04%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.713mb  | 12.236ms  | ±10.70%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.254mb  | 3.631ms   | ±1.01%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.617mb  | 2.321ms   | ±0.89%   |
| GeneratePdfBench  | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.612mb  | 644.911μs | ±3.60%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.047mb  | 2.951ms   | ±0.72%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.141mb  | 3.589ms   | ±1.21%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.061mb  | 2.984ms   | ±0.74%   |
| GeneratePdfBench  | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.117mb  | 238.183ms | ±25.76%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.179mb  | 3.829ms   | ±165.59% |
| GeneratePdfBench  | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.790mb  | 5.811ms   | ±75.21%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.926mb  | 5.979ms   | ±1.30%   |
| GeneratePdfBench  | benchTcpdf1Page                                  |     | 3    | 5   | 12.878mb | 9.983ms   | ±0.33%   |
| GeneratePdfBench  | benchTcpdf5Pages                                 |     | 3    | 5   | 12.878mb | 10.895ms  | ±0.33%   |
| GeneratePdfBench  | benchTcpdf10Pages                                |     | 3    | 5   | 12.878mb | 11.907ms  | ±0.63%   |
| GeneratePdfBench  | benchTcpdf50Pages                                |     | 3    | 5   | 12.878mb | 20.518ms  | ±0.72%   |
| GeneratePdfBench  | benchTcpdf100Pages                               |     | 3    | 5   | 12.878mb | 31.208ms  | ±0.42%   |
| GeneratePdfBench  | benchFpdf1Page                                   |     | 3    | 5   | 5.040mb  | 807.607μs | ±1.29%   |
| GeneratePdfBench  | benchFpdf5Pages                                  |     | 3    | 5   | 5.040mb  | 894.907μs | ±1.90%   |
| GeneratePdfBench  | benchFpdf10Pages                                 |     | 3    | 5   | 5.040mb  | 984.590μs | ±1.51%   |
| GeneratePdfBench  | benchFpdf50Pages                                 |     | 3    | 5   | 5.040mb  | 1.578ms   | ±0.95%   |
| GeneratePdfBench  | benchFpdf100Pages                                |     | 3    | 5   | 5.042mb  | 2.327ms   | ±0.73%   |
| GeneratePdfBench  | benchMpdf1Page                                   |     | 3    | 5   | 17.590mb | 25.031ms  | ±2.04%   |
| GeneratePdfBench  | benchMpdf5Pages                                  |     | 3    | 5   | 17.649mb | 29.023ms  | ±0.88%   |
| GeneratePdfBench  | benchMpdf10Pages                                 |     | 3    | 5   | 17.687mb | 33.174ms  | ±0.51%   |
| GeneratePdfBench  | benchMpdf50Pages                                 |     | 3    | 5   | 17.980mb | 65.644ms  | ±0.70%   |
| GeneratePdfBench  | benchMpdf100Pages                                |     | 3    | 5   | 18.342mb | 105.165ms | ±0.67%   |
| GeneratePdfBench  | benchDompdf1Page                                 |     | 3    | 5   | 9.323mb  | 11.231ms  | ±3.34%   |
| GeneratePdfBench  | benchDompdf5Pages                                |     | 3    | 5   | 9.543mb  | 15.810ms  | ±0.33%   |
| GeneratePdfBench  | benchDompdf10Pages                               |     | 3    | 5   | 9.864mb  | 21.389ms  | ±0.34%   |
| GeneratePdfBench  | benchDompdf50Pages                               |     | 3    | 5   | 12.557mb | 73.280ms  | ±1.50%   |
| GeneratePdfBench  | benchDompdf100Pages                              |     | 3    | 5   | 15.920mb | 162.317ms | ±0.63%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.991mb  | 4.982ms   | ±0.97%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.428mb  | 50.542ms  | ±0.98%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.612mb  | 1.656μs   | ±10.65%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.612mb  | 1.666μs   | ±8.33%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.612mb  | 1.322μs   | ±13.61%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.024mb  | 221.171ms | ±14.74%  |
| GeneratePdfBench  | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.612mb  | 451.025μs | ±1.23%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.405mb  | 2.966ms   | ±1.03%   |
| GeneratePdfBench  | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.003mb  | 3.219ms   | ±0.72%   |
| GeneratePdfBench  | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.612mb  | 14.152ms  | ±7.44%   |
| GeneratePdfBench  | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.612mb  | 84.567ms  | ±1.33%   |
| GeneratePdfBench  | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.612mb  | 14.386ms  | ±1.08%   |
| GeneratePdfBench  | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.612mb  | 24.660ms  | ±1.61%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.831mb  | 150.384ms | ±38.28%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.139mb  | 13.329ms  | ±3.25%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.112mb  | 13.257ms  | ±0.43%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.113mb  | 13.332ms  | ±0.41%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.137mb  | 13.412ms  | ±0.33%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.254mb  | 13.823ms  | ±0.77%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.917mb  | 2.852ms   | ±0.98%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.116mb  | 13.367ms  | ±0.48%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.175mb  | 13.537ms  | ±0.59%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.069mb  | 13.035ms  | ±0.77%   |
| ListsBench        | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.893mb  | 3.890ms   | ±2.55%   |
| ListsBench        | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.445mb  | 11.452ms  | ±0.59%   |
| ListsBench        | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.889mb  | 44.619ms  | ±0.51%   |
| ListsBench        | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.689mb  | 3.152ms   | ±0.62%   |
| ListsBench        | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.183mb  | 7.091ms   | ±0.52%   |
| RendererBench     | benchShortDocument                               |     | 3    | 3   | 10.072mb | 34.935ms  | ±0.28%   |
| RendererBench     | benchMediumArticle                               |     | 3    | 3   | 14.018mb | 138.096ms | ±0.68%   |
| RendererBench     | benchLongReport                                  |     | 3    | 3   | 29.891mb | 540.758ms | ±0.80%   |
| RendererBench     | benchRealFaceMatching                            |     | 3    | 3   | 21.526mb | 121.499ms | ±0.21%   |
| RendererBench     | benchPageMarginBoxes                             |     | 3    | 3   | 26.656mb | 135.012ms | ±0.15%   |
| RendererBench     | benchFloats                                      |     | 3    | 3   | 9.948mb  | 70.250ms  | ±0.56%   |
| RendererBench     | benchMultiColumn                                 |     | 3    | 3   | 10.395mb | 89.377ms  | ±0.40%   |
| RendererBench     | benchRichTypography                              |     | 3    | 3   | 12.098mb | 132.038ms | ±1.35%   |
| ReadPdfBench      | benchPhpdftk1Page                                |     | 3    | 5   | 4.203mb  | 1.246ms   | ±0.67%   |
| ReadPdfBench      | benchPhpdftk10Pages                              |     | 3    | 5   | 4.203mb  | 1.688ms   | ±1.23%   |
| ReadPdfBench      | benchPhpdftk100Pages                             |     | 3    | 5   | 4.561mb  | 5.952ms   | ±1.00%   |
| ReadPdfBench      | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.204mb  | 2.039ms   | ±0.47%   |
| ReadPdfBench      | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.203mb  | 1.377ms   | ±0.74%   |
| ReadPdfBench      | benchSmalot1Page                                 |     | 3    | 5   | 4.709mb  | 2.009ms   | ±0.88%   |
| ReadPdfBench      | benchSmalot10Pages                               |     | 3    | 5   | 4.810mb  | 2.367ms   | ±0.50%   |
| ReadPdfBench      | benchSmalot100Pages                              |     | 3    | 5   | 6.567mb  | 5.770ms   | ±0.73%   |
| ReadPdfBench      | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.204mb  | 550.427μs | ±1.09%   |
| ReadPdfBench      | benchSmalotXrefStream                            |     | 3    | 5   | 4.705mb  | 1.922ms   | ±1.07%   |
| ReadPdfBench      | benchFpdi1Page                                   |     | 3    | 5   | 4.812mb  | 1.920ms   | ±1.16%   |
| ReadPdfBench      | benchFpdi10Pages                                 |     | 3    | 5   | 4.813mb  | 2.812ms   | ±1.36%   |
| ReadPdfBench      | benchFpdi100Pages                                |     | 3    | 5   | 5.484mb  | 29.937ms  | ±0.43%   |
| ReadPdfBench      | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.832mb  | 2.956ms   | ±0.21%   |
| ReadPdfBench      | benchFpdiXrefStream                              |     | 3    | 5   | 4.765mb  | 1.495ms   | ±0.62%   |
| ReadPdfBench      | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.968mb  | 7.201ms   | ±0.74%   |
| ReadPdfBench      | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.940mb  | 5.419ms   | ±0.40%   |
| ReadPdfBench      | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.918mb  | 3.792ms   | ±1.20%   |
| ReadPdfBench      | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.204mb  | 3.212μs   | ±2.89%   |
| ReadPdfBench      | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.307mb  | 6.184ms   | ±0.56%   |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```