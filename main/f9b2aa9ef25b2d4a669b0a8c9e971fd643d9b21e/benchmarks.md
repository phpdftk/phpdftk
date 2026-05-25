# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-25 00:24:26 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.045ms | 2.495ms | 2.721ms | 4.738ms | 6.998ms |
| FPDF | 821.290μs | 898.928μs | 988.046μs | 1.591ms | 2.321ms |
| TCPDF | 9.946ms | 10.866ms | 12.021ms | 20.502ms | 31.188ms |
| mPDF | 24.929ms | 28.979ms | 32.989ms | 65.218ms | 105.309ms |
| Dompdf | 11.426ms | 15.913ms | 21.411ms | 73.697ms | 159.911ms |

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
| phpdftk | 3.293ms | 3.487ms | 3.767ms | 5.775ms | 14.809ms |
| FPDF | 1.086ms | 1.171ms | 1.265ms | 1.943ms | 2.871ms |
| TCPDF | 29.200ms | 16.081ms | 16.697ms | 26.774ms | 38.601ms |

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
| Pdf (Level 3) | 3.202ms | 4.085ms | 12.566ms |
| PdfDoc (Level 2) | 2.582ms | 3.004ms | 7.151ms |
| PdfWriter (Level 1) | 2.291ms | 2.701ms | 6.945ms |

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
| Pdf (Level 3) | 4.222ms | 11.693ms | 45.661ms |
| PdfDoc (Level 2) | 3.644ms | 9.697ms | — |

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
| Pdf (Level 3) | 3.871ms | 11.308ms | 44.256ms |
| PdfDoc (Level 2) | 3.112ms | 7.089ms | — |

### Peak Memory

| Library | 10 items | 100 items | 500 items |
|---|---|---|---|
| Pdf (Level 3) | 5.893mb | 6.445mb | 8.889mb |
| PdfDoc (Level 2) | 5.689mb | 6.183mb | — |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.121ms | 1.658ms | 5.947ms |
| smalot/pdfparser | 1.979ms | 2.359ms | 5.658ms |
| setasign/fpdi | 1.910ms | 2.771ms | 29.444ms |

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
| phpdftk | 2.118ms | 1.359ms |
| smalot/pdfparser | FAIL | 1.901ms |
| setasign/fpdi | 2.945ms | FAIL |

---

## Raw phpbench Output

```
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark         | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| WriterLevelsBench | benchLevel1PdfWriter1Page                        |     | 3    | 5   | 5.332mb  | 2.291ms   | ±0.57%   |
| WriterLevelsBench | benchLevel1PdfWriter10Pages                      |     | 3    | 5   | 5.491mb  | 2.701ms   | ±2.78%   |
| WriterLevelsBench | benchLevel1PdfWriter100Pages                     |     | 3    | 5   | 7.066mb  | 6.945ms   | ±0.24%   |
| WriterLevelsBench | benchLevel2PdfDoc1Page                           |     | 3    | 5   | 5.574mb  | 2.582ms   | ±1.45%   |
| WriterLevelsBench | benchLevel2PdfDoc10Pages                         |     | 3    | 5   | 5.732mb  | 3.004ms   | ±0.65%   |
| WriterLevelsBench | benchLevel2PdfDoc100Pages                        |     | 3    | 5   | 7.301mb  | 7.151ms   | ±0.93%   |
| WriterLevelsBench | benchLevel3Pdf1Page                              |     | 3    | 5   | 5.910mb  | 3.202ms   | ±0.68%   |
| WriterLevelsBench | benchLevel3Pdf10Pages                            |     | 3    | 5   | 6.074mb  | 4.085ms   | ±0.89%   |
| WriterLevelsBench | benchLevel3Pdf100Pages                           |     | 3    | 5   | 7.750mb  | 12.566ms  | ±0.32%   |
| EncodingBench     | benchEncodeParagraph                             |     | 50   | 5   | 4.203mb  | 42.341μs  | ±8.38%   |
| EncodingBench     | benchShowTextThroughContentStream                |     | 50   | 5   | 6.448mb  | 240.518μs | ±0.64%   |
| StylingBench      | benchLevel3PdfUnderlined50Items                  |     | 3    | 5   | 6.655mb  | 8.600ms   | ±0.85%   |
| StylingBench      | benchLevel3PdfBlockquote50Items                  |     | 3    | 5   | 6.470mb  | 8.552ms   | ±17.96%  |
| StylingBench      | benchLevel3PdfCallout50Items                     |     | 3    | 5   | 6.679mb  | 8.411ms   | ±0.88%   |
| BoxGeneratorBench | benchSmallBlogPost                               |     | 5    | 3   | 6.316mb  | 9.309ms   | ±0.92%   |
| BoxGeneratorBench | benchMediumArticle                               |     | 5    | 3   | 10.277mb | 75.621ms  | ±0.70%   |
| BoxGeneratorBench | benchLargeDocumentationPage                      |     | 5    | 3   | 28.282mb | 376.583ms | ±0.67%   |
| MemoryBench       | benchPhpdftk1Page                                |     | 2    | 3   | 5.316mb  | 3.293ms   | ±0.48%   |
| MemoryBench       | benchPhpdftk5Pages                               |     | 2    | 3   | 5.362mb  | 3.487ms   | ±2.17%   |
| MemoryBench       | benchPhpdftk10Pages                              |     | 2    | 3   | 5.422mb  | 3.767ms   | ±1.24%   |
| MemoryBench       | benchPhpdftk50Pages                              |     | 2    | 3   | 5.914mb  | 5.775ms   | ±0.37%   |
| MemoryBench       | benchPhpdftk100Pages                             |     | 2    | 3   | 6.513mb  | 14.809ms  | ±36.59%  |
| MemoryBench       | benchTcpdf1Page                                  |     | 2    | 3   | 12.453mb | 29.200ms  | ±109.26% |
| MemoryBench       | benchTcpdf5Pages                                 |     | 2    | 3   | 12.453mb | 16.081ms  | ±0.67%   |
| MemoryBench       | benchTcpdf10Pages                                |     | 2    | 3   | 12.453mb | 16.697ms  | ±1.21%   |
| MemoryBench       | benchTcpdf50Pages                                |     | 2    | 3   | 12.453mb | 26.774ms  | ±0.51%   |
| MemoryBench       | benchTcpdf100Pages                               |     | 2    | 3   | 12.453mb | 38.601ms  | ±0.39%   |
| MemoryBench       | benchFpdf1Page                                   |     | 2    | 3   | 4.374mb  | 1.086ms   | ±1.79%   |
| MemoryBench       | benchFpdf5Pages                                  |     | 2    | 3   | 4.374mb  | 1.171ms   | ±1.23%   |
| MemoryBench       | benchFpdf10Pages                                 |     | 2    | 3   | 4.374mb  | 1.265ms   | ±0.71%   |
| MemoryBench       | benchFpdf50Pages                                 |     | 2    | 3   | 4.403mb  | 1.943ms   | ±0.20%   |
| MemoryBench       | benchFpdf100Pages                                |     | 2    | 3   | 4.463mb  | 2.871ms   | ±7.32%   |
| TablesBench       | benchLevel3PdfTable10Rows                        |     | 3    | 5   | 6.261mb  | 4.222ms   | ±1.27%   |
| TablesBench       | benchLevel3PdfTable100Rows                       |     | 3    | 5   | 9.056mb  | 11.693ms  | ±1.23%   |
| TablesBench       | benchLevel3PdfTable500Rows                       |     | 3    | 5   | 21.465mb | 45.661ms  | ±0.51%   |
| TablesBench       | benchLevel2PdfDocTable10Rows                     |     | 3    | 5   | 6.074mb  | 3.644ms   | ±1.01%   |
| TablesBench       | benchLevel2PdfDocTable100Rows                    |     | 3    | 5   | 8.889mb  | 9.697ms   | ±0.53%   |
| GeneratePdfBench  | benchPhpdftk1Page                                |     | 3    | 5   | 5.829mb  | 2.276ms   | ±26.17%  |
| GeneratePdfBench  | benchPhpdftk5Pages                               |     | 3    | 5   | 5.889mb  | 2.495ms   | ±1.29%   |
| GeneratePdfBench  | benchPhpdftk10Pages                              |     | 3    | 5   | 5.975mb  | 2.721ms   | ±1.39%   |
| GeneratePdfBench  | benchPhpdftk50Pages                              |     | 3    | 5   | 6.610mb  | 4.738ms   | ±0.37%   |
| GeneratePdfBench  | benchPhpdftk100Pages                             |     | 3    | 5   | 7.432mb  | 6.998ms   | ±0.58%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 6.210mb  | 3.445ms   | ±0.44%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.285mb  | 3.761ms   | ±0.77%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.713mb  | 12.205ms  | ±22.95%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 6.254mb  | 3.611ms   | ±10.13%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.617mb  | 2.351ms   | ±0.74%   |
| GeneratePdfBench  | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.612mb  | 636.658μs | ±3.51%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 6.047mb  | 2.980ms   | ±6.71%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.141mb  | 3.664ms   | ±0.79%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 6.061mb  | 3.051ms   | ±1.43%   |
| GeneratePdfBench  | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.117mb  | 148.290ms | ±32.35%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.179mb  | 3.547ms   | ±0.60%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.790mb  | 5.791ms   | ±22.79%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.926mb  | 6.014ms   | ±0.75%   |
| GeneratePdfBench  | benchTcpdf1Page                                  |     | 3    | 5   | 12.878mb | 9.946ms   | ±0.58%   |
| GeneratePdfBench  | benchTcpdf5Pages                                 |     | 3    | 5   | 12.878mb | 10.866ms  | ±0.34%   |
| GeneratePdfBench  | benchTcpdf10Pages                                |     | 3    | 5   | 12.878mb | 12.021ms  | ±0.46%   |
| GeneratePdfBench  | benchTcpdf50Pages                                |     | 3    | 5   | 12.878mb | 20.502ms  | ±0.52%   |
| GeneratePdfBench  | benchTcpdf100Pages                               |     | 3    | 5   | 12.878mb | 31.188ms  | ±0.40%   |
| GeneratePdfBench  | benchFpdf1Page                                   |     | 3    | 5   | 5.040mb  | 821.290μs | ±0.85%   |
| GeneratePdfBench  | benchFpdf5Pages                                  |     | 3    | 5   | 5.040mb  | 898.928μs | ±0.91%   |
| GeneratePdfBench  | benchFpdf10Pages                                 |     | 3    | 5   | 5.040mb  | 988.046μs | ±0.71%   |
| GeneratePdfBench  | benchFpdf50Pages                                 |     | 3    | 5   | 5.040mb  | 1.591ms   | ±0.73%   |
| GeneratePdfBench  | benchFpdf100Pages                                |     | 3    | 5   | 5.042mb  | 2.321ms   | ±0.68%   |
| GeneratePdfBench  | benchMpdf1Page                                   |     | 3    | 5   | 17.590mb | 24.929ms  | ±1.98%   |
| GeneratePdfBench  | benchMpdf5Pages                                  |     | 3    | 5   | 17.649mb | 28.979ms  | ±0.95%   |
| GeneratePdfBench  | benchMpdf10Pages                                 |     | 3    | 5   | 17.687mb | 32.989ms  | ±0.37%   |
| GeneratePdfBench  | benchMpdf50Pages                                 |     | 3    | 5   | 17.980mb | 65.218ms  | ±0.29%   |
| GeneratePdfBench  | benchMpdf100Pages                                |     | 3    | 5   | 18.342mb | 105.309ms | ±0.82%   |
| GeneratePdfBench  | benchDompdf1Page                                 |     | 3    | 5   | 9.323mb  | 11.426ms  | ±4.71%   |
| GeneratePdfBench  | benchDompdf5Pages                                |     | 3    | 5   | 9.543mb  | 15.913ms  | ±0.50%   |
| GeneratePdfBench  | benchDompdf10Pages                               |     | 3    | 5   | 9.864mb  | 21.411ms  | ±7.67%   |
| GeneratePdfBench  | benchDompdf50Pages                               |     | 3    | 5   | 12.557mb | 73.697ms  | ±5.34%   |
| GeneratePdfBench  | benchDompdf100Pages                              |     | 3    | 5   | 15.920mb | 159.911ms | ±0.29%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.991mb  | 8.237ms   | ±121.14% |
| GeneratePdfBench  | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.428mb  | 49.600ms  | ±0.49%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.612mb  | 1.656μs   | ±10.65%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.612mb  | 1.344μs   | ±11.13%  |
| GeneratePdfBench  | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.612mb  | 1.333μs   | ±0.00%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.024mb  | 194.710ms | ±40.81%  |
| GeneratePdfBench  | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.612mb  | 458.189μs | ±1.77%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.405mb  | 2.970ms   | ±0.64%   |
| GeneratePdfBench  | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 6.003mb  | 3.193ms   | ±0.59%   |
| GeneratePdfBench  | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.612mb  | 13.406ms  | ±5.42%   |
| GeneratePdfBench  | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.612mb  | 84.478ms  | ±0.37%   |
| GeneratePdfBench  | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.612mb  | 14.265ms  | ±2.42%   |
| GeneratePdfBench  | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.612mb  | 25.124ms  | ±1.02%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.831mb  | 158.111ms | ±7.89%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 9.139mb  | 13.300ms  | ±0.32%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 9.112mb  | 13.181ms  | ±0.35%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 9.113mb  | 13.304ms  | ±0.32%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 9.137mb  | 13.356ms  | ±0.25%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 9.254mb  | 13.921ms  | ±0.63%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.917mb  | 2.812ms   | ±0.33%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 9.116mb  | 13.256ms  | ±0.90%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 9.175mb  | 13.453ms  | ±2.75%   |
| GeneratePdfBench  | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 9.069mb  | 13.045ms  | ±0.55%   |
| ListsBench        | benchLevel3PdfList10Items                        |     | 3    | 5   | 5.893mb  | 3.871ms   | ±0.80%   |
| ListsBench        | benchLevel3PdfList100Items                       |     | 3    | 5   | 6.445mb  | 11.308ms  | ±0.80%   |
| ListsBench        | benchLevel3PdfList500Items                       |     | 3    | 5   | 8.889mb  | 44.256ms  | ±0.71%   |
| ListsBench        | benchLevel2PdfDocList10Items                     |     | 3    | 5   | 5.689mb  | 3.112ms   | ±0.92%   |
| ListsBench        | benchLevel2PdfDocList100Items                    |     | 3    | 5   | 6.183mb  | 7.089ms   | ±2.89%   |
| RendererBench     | benchShortDocument                               |     | 3    | 3   | 10.096mb | 36.920ms  | ±5.27%   |
| RendererBench     | benchMediumArticle                               |     | 3    | 3   | 14.063mb | 145.229ms | ±0.86%   |
| RendererBench     | benchLongReport                                  |     | 3    | 3   | 29.935mb | 569.232ms | ±0.48%   |
| RendererBench     | benchRealFaceMatching                            |     | 3    | 3   | 21.572mb | 126.241ms | ±1.72%   |
| RendererBench     | benchPageMarginBoxes                             |     | 3    | 3   | 26.700mb | 136.525ms | ±0.14%   |
| RendererBench     | benchFloats                                      |     | 3    | 3   | 9.975mb  | 72.884ms  | ±1.81%   |
| RendererBench     | benchMultiColumn                                 |     | 3    | 3   | 10.444mb | 92.575ms  | ±0.23%   |
| RendererBench     | benchRichTypography                              |     | 3    | 3   | 12.143mb | 138.128ms | ±0.84%   |
| ReadPdfBench      | benchPhpdftk1Page                                |     | 3    | 5   | 4.203mb  | 1.232ms   | ±2.12%   |
| ReadPdfBench      | benchPhpdftk10Pages                              |     | 3    | 5   | 4.203mb  | 1.658ms   | ±0.84%   |
| ReadPdfBench      | benchPhpdftk100Pages                             |     | 3    | 5   | 4.561mb  | 5.947ms   | ±1.70%   |
| ReadPdfBench      | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.204mb  | 2.118ms   | ±23.07%  |
| ReadPdfBench      | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.203mb  | 1.359ms   | ±0.57%   |
| ReadPdfBench      | benchSmalot1Page                                 |     | 3    | 5   | 4.709mb  | 1.979ms   | ±1.40%   |
| ReadPdfBench      | benchSmalot10Pages                               |     | 3    | 5   | 4.810mb  | 2.359ms   | ±0.69%   |
| ReadPdfBench      | benchSmalot100Pages                              |     | 3    | 5   | 6.567mb  | 5.658ms   | ±0.67%   |
| ReadPdfBench      | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.204mb  | 542.112μs | ±1.53%   |
| ReadPdfBench      | benchSmalotXrefStream                            |     | 3    | 5   | 4.705mb  | 1.901ms   | ±0.91%   |
| ReadPdfBench      | benchFpdi1Page                                   |     | 3    | 5   | 4.812mb  | 1.910ms   | ±1.83%   |
| ReadPdfBench      | benchFpdi10Pages                                 |     | 3    | 5   | 4.813mb  | 2.771ms   | ±0.26%   |
| ReadPdfBench      | benchFpdi100Pages                                |     | 3    | 5   | 5.484mb  | 29.444ms  | ±0.44%   |
| ReadPdfBench      | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.832mb  | 2.945ms   | ±1.95%   |
| ReadPdfBench      | benchFpdiXrefStream                              |     | 3    | 5   | 4.765mb  | 1.479ms   | ±0.16%   |
| ReadPdfBench      | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.968mb  | 7.251ms   | ±0.60%   |
| ReadPdfBench      | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.940mb  | 5.401ms   | ±0.64%   |
| ReadPdfBench      | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.918mb  | 3.786ms   | ±0.81%   |
| ReadPdfBench      | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.204mb  | 3.012μs   | ±3.07%   |
| ReadPdfBench      | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.307mb  | 6.121ms   | ±0.65%   |
+-------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```