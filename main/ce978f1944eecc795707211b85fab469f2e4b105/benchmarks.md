# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: 2026-05-16 02:25:35 UTC
PHP: 8.4.21
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 13.168ms | 2.357ms | 2.598ms | 4.518ms | 7.243ms |
| FPDF | 855.063μs | 914.464μs | 1.012ms | 1.627ms | 2.340ms |
| TCPDF | 10.403ms | 11.346ms | 12.406ms | 19.672ms | 28.872ms |
| mPDF | 27.122ms | 30.399ms | 34.452ms | 62.283ms | 96.266ms |
| Dompdf | 11.417ms | 15.325ms | 19.948ms | 65.796ms | 150.070ms |

## Peak Memory — `GeneratePdfBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 8.747mb | 5.806mb | 5.892mb | 6.527mb | 7.349mb |
| FPDF | 5.032mb | 5.032mb | 5.032mb | 5.032mb | 5.034mb |
| TCPDF | 12.870mb | 12.870mb | 12.870mb | 12.870mb | 12.870mb |
| mPDF | 17.582mb | 17.641mb | 17.679mb | 17.972mb | 18.334mb |
| Dompdf | 9.315mb | 9.535mb | 9.856mb | 12.549mb | 15.912mb |

## Generation Time — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 3.110ms | 3.296ms | 3.573ms | 5.624ms | 8.161ms |
| FPDF | 1.133ms | 1.223ms | 1.339ms | 2.003ms | 2.840ms |
| TCPDF | 17.862ms | 16.504ms | 17.478ms | 26.479ms | 36.307ms |

## Peak Memory — `MemoryBench`

| Library | 1 page | 5 pages | 10 pages | 50 pages | 100 pages |
|---|---|---|---|---|---|
| phpdftk | 5.232mb | 5.279mb | 5.338mb | 5.831mb | 6.429mb |
| FPDF | 4.366mb | 4.366mb | 4.366mb | 4.395mb | 4.455mb |
| TCPDF | 12.445mb | 12.445mb | 12.445mb | 12.445mb | 12.446mb |

## Parse Time — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 6.149ms | 1.672ms | 6.117ms |
| smalot/pdfparser | 2.016ms | 2.383ms | 5.693ms |
| setasign/fpdi | 1.875ms | 2.669ms | 28.586ms |

## Peak Memory — `ReadPdfBench`

| Library | 1 page | 10 pages | 100 pages |
|---|---|---|---|
| phpdftk | 5.299mb | 4.196mb | 4.553mb |
| smalot/pdfparser | 4.701mb | 4.802mb | 6.559mb |
| setasign/fpdi | 4.804mb | 4.805mb | 5.476mb |

## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

| Library | Spec-compliant xref (20-byte SP CR LF) | Cross-reference stream (PDF 1.5) |
|---|---|---|
| phpdftk | 2.036ms | 1.360ms |
| smalot/pdfparser | FAIL | 1.913ms |
| setasign/fpdi | 2.883ms | FAIL |

---

## Raw phpbench Output

```
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| benchmark        | subject                                          | set | revs | its | mem_peak | mode      | rstdev   |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+
| EncodingBench    | benchEncodeParagraph                             |     | 50   | 5   | 4.195mb  | 42.804μs  | ±1.58%   |
| EncodingBench    | benchShowTextThroughContentStream                |     | 50   | 5   | 6.365mb  | 231.425μs | ±2.25%   |
| MemoryBench      | benchPhpdftk1Page                                |     | 2    | 3   | 5.232mb  | 3.110ms   | ±0.68%   |
| MemoryBench      | benchPhpdftk5Pages                               |     | 2    | 3   | 5.279mb  | 3.296ms   | ±0.77%   |
| MemoryBench      | benchPhpdftk10Pages                              |     | 2    | 3   | 5.338mb  | 3.573ms   | ±1.15%   |
| MemoryBench      | benchPhpdftk50Pages                              |     | 2    | 3   | 5.831mb  | 5.624ms   | ±2.98%   |
| MemoryBench      | benchPhpdftk100Pages                             |     | 2    | 3   | 6.429mb  | 8.161ms   | ±0.33%   |
| MemoryBench      | benchTcpdf1Page                                  |     | 2    | 3   | 12.445mb | 17.862ms  | ±7.86%   |
| MemoryBench      | benchTcpdf5Pages                                 |     | 2    | 3   | 12.445mb | 16.504ms  | ±0.39%   |
| MemoryBench      | benchTcpdf10Pages                                |     | 2    | 3   | 12.445mb | 17.478ms  | ±0.88%   |
| MemoryBench      | benchTcpdf50Pages                                |     | 2    | 3   | 12.445mb | 26.479ms  | ±1.31%   |
| MemoryBench      | benchTcpdf100Pages                               |     | 2    | 3   | 12.446mb | 36.307ms  | ±0.43%   |
| MemoryBench      | benchFpdf1Page                                   |     | 2    | 3   | 4.366mb  | 1.133ms   | ±0.25%   |
| MemoryBench      | benchFpdf5Pages                                  |     | 2    | 3   | 4.366mb  | 1.223ms   | ±1.62%   |
| MemoryBench      | benchFpdf10Pages                                 |     | 2    | 3   | 4.366mb  | 1.339ms   | ±0.16%   |
| MemoryBench      | benchFpdf50Pages                                 |     | 2    | 3   | 4.395mb  | 2.003ms   | ±0.58%   |
| MemoryBench      | benchFpdf100Pages                                |     | 2    | 3   | 4.455mb  | 2.840ms   | ±0.76%   |
| GeneratePdfBench | benchPhpdftk1Page                                |     | 3    | 5   | 5.746mb  | 2.117ms   | ±0.94%   |
| GeneratePdfBench | benchPhpdftk5Pages                               |     | 3    | 5   | 5.806mb  | 2.357ms   | ±6.67%   |
| GeneratePdfBench | benchPhpdftk10Pages                              |     | 3    | 5   | 5.892mb  | 2.598ms   | ±0.58%   |
| GeneratePdfBench | benchPhpdftk50Pages                              |     | 3    | 5   | 6.527mb  | 4.518ms   | ±1.12%   |
| GeneratePdfBench | benchPhpdftk100Pages                             |     | 3    | 5   | 7.349mb  | 7.243ms   | ±48.14%  |
| GeneratePdfBench | benchPhpdftk10PagesWithBookmarksAndTransitions   |     | 3    | 5   | 5.953mb  | 2.861ms   | ±129.01% |
| GeneratePdfBench | benchPhpdftk10PagesWithAnnotations               |     | 3    | 5   | 6.202mb  | 3.616ms   | ±0.63%   |
| GeneratePdfBench | benchPhpdftk10PagesWithEmbeddedFont              |     | 3    | 5   | 8.630mb  | 12.964ms  | ±4.71%   |
| GeneratePdfBench | benchPhpdftk10PagesWithDocumentStructure         |     | 3    | 5   | 5.996mb  | 2.974ms   | ±0.66%   |
| GeneratePdfBench | benchPhpdftk10PagesWithType3Font                 |     | 3    | 5   | 5.534mb  | 2.184ms   | ±0.38%   |
| GeneratePdfBench | benchPhpdftkXRefAndObjectStreams                 |     | 3    | 5   | 4.604mb  | 604.463μs | ±9.34%   |
| GeneratePdfBench | benchPhpdftk10PagesWithShadingsAndPatterns       |     | 3    | 5   | 5.964mb  | 2.821ms   | ±1.19%   |
| GeneratePdfBench | benchPhpdftk10PagesWithMultimediaAnd3D           |     | 3    | 5   | 6.058mb  | 3.226ms   | ±3.07%   |
| GeneratePdfBench | benchPhpdftk10PagesWithSignatureField            |     | 3    | 5   | 5.978mb  | 2.845ms   | ±1.04%   |
| GeneratePdfBench | benchPhpdftk10PagesSigned                        |     | 3    | 5   | 6.034mb  | 290.323ms | ±13.59%  |
| GeneratePdfBench | benchPhpdftk10PagesWithMarkupAnnotations         |     | 3    | 5   | 6.095mb  | 3.379ms   | ±0.43%   |
| GeneratePdfBench | benchPhpdftk10PagesWithImageStamp                |     | 3    | 5   | 6.708mb  | 5.698ms   | ±23.20%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfStamp                  |     | 3    | 5   | 6.843mb  | 5.936ms   | ±2.71%   |
| GeneratePdfBench | benchTcpdf1Page                                  |     | 3    | 5   | 12.870mb | 10.403ms  | ±0.63%   |
| GeneratePdfBench | benchTcpdf5Pages                                 |     | 3    | 5   | 12.870mb | 11.346ms  | ±1.28%   |
| GeneratePdfBench | benchTcpdf10Pages                                |     | 3    | 5   | 12.870mb | 12.406ms  | ±1.15%   |
| GeneratePdfBench | benchTcpdf50Pages                                |     | 3    | 5   | 12.870mb | 19.672ms  | ±0.35%   |
| GeneratePdfBench | benchTcpdf100Pages                               |     | 3    | 5   | 12.870mb | 28.872ms  | ±1.53%   |
| GeneratePdfBench | benchFpdf1Page                                   |     | 3    | 5   | 5.032mb  | 855.063μs | ±1.27%   |
| GeneratePdfBench | benchFpdf5Pages                                  |     | 3    | 5   | 5.032mb  | 914.464μs | ±0.84%   |
| GeneratePdfBench | benchFpdf10Pages                                 |     | 3    | 5   | 5.032mb  | 1.012ms   | ±0.87%   |
| GeneratePdfBench | benchFpdf50Pages                                 |     | 3    | 5   | 5.032mb  | 1.627ms   | ±0.43%   |
| GeneratePdfBench | benchFpdf100Pages                                |     | 3    | 5   | 5.034mb  | 2.340ms   | ±0.43%   |
| GeneratePdfBench | benchMpdf1Page                                   |     | 3    | 5   | 17.582mb | 27.122ms  | ±1.75%   |
| GeneratePdfBench | benchMpdf5Pages                                  |     | 3    | 5   | 17.641mb | 30.399ms  | ±0.86%   |
| GeneratePdfBench | benchMpdf10Pages                                 |     | 3    | 5   | 17.679mb | 34.452ms  | ±0.78%   |
| GeneratePdfBench | benchMpdf50Pages                                 |     | 3    | 5   | 17.972mb | 62.283ms  | ±0.57%   |
| GeneratePdfBench | benchMpdf100Pages                                |     | 3    | 5   | 18.334mb | 96.266ms  | ±0.20%   |
| GeneratePdfBench | benchDompdf1Page                                 |     | 3    | 5   | 9.315mb  | 11.417ms  | ±4.75%   |
| GeneratePdfBench | benchDompdf5Pages                                |     | 3    | 5   | 9.535mb  | 15.325ms  | ±0.72%   |
| GeneratePdfBench | benchDompdf10Pages                               |     | 3    | 5   | 9.856mb  | 19.948ms  | ±0.73%   |
| GeneratePdfBench | benchDompdf50Pages                               |     | 3    | 5   | 12.549mb | 65.796ms  | ±0.62%   |
| GeneratePdfBench | benchDompdf100Pages                              |     | 3    | 5   | 15.912mb | 150.070ms | ±0.60%   |
| GeneratePdfBench | benchPhpdftk10PagesWithFormAppearances           |     | 3    | 5   | 6.908mb  | 5.001ms   | ±114.60% |
| GeneratePdfBench | benchPhpdftk10PagesWithCustomFontFormAppearances |     | 3    | 5   | 8.348mb  | 53.817ms  | ±2.93%   |
| GeneratePdfBench | benchPhpdftk10PagesWithOpenTypeCff               |     | 3    | 5   | 4.604mb  | 1.667μs   | ±0.00%   |
| GeneratePdfBench | benchPhpdftk10PagesWithCffSubsetting             |     | 3    | 5   | 4.604mb  | 1.667μs   | ±0.00%   |
| GeneratePdfBench | benchPhpdftk10PagesWithKernedText                |     | 3    | 5   | 4.604mb  | 1.667μs   | ±0.00%   |
| GeneratePdfBench | benchPhpdftk10PagesWithPublicKeyEncryption       |     | 3    | 5   | 5.014mb  | 279.103ms | ±20.70%  |
| GeneratePdfBench | benchPhpdftkTsaRequestBuildAndParse              |     | 3    | 5   | 4.604mb  | 469.054μs | ±0.67%   |
| GeneratePdfBench | benchPhpdftk10PagesWithVersionGating             |     | 5    | 3   | 7.322mb  | 2.816ms   | ±0.21%   |
| GeneratePdfBench | benchPhpdftk10PagesLinearized                    |     | 3    | 5   | 5.920mb  | 3.034ms   | ±0.80%   |
| GeneratePdfBench | benchPhpdftkType1FontParsing                     |     | 10   | 5   | 4.604mb  | 13.094ms  | ±8.28%   |
| GeneratePdfBench | benchPhpdftkCCITTFaxDecode                       |     | 10   | 5   | 4.604mb  | 86.593ms  | ±0.82%   |
| GeneratePdfBench | benchPhpdftkCCITTFaxEncode                       |     | 10   | 5   | 4.604mb  | 14.734ms  | ±0.32%   |
| GeneratePdfBench | benchPhpdftkJbig2Encode                          |     | 10   | 5   | 4.604mb  | 26.146ms  | ±0.53%   |
| GeneratePdfBench | benchPhpdftk10PagesWithLtvSignature              |     | 3    | 5   | 6.747mb  | 199.152ms | ±20.79%  |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfAConformance           |     | 3    | 5   | 8.874mb  | 13.493ms  | ±0.69%   |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfUaConformance          |     | 3    | 5   | 8.847mb  | 13.321ms  | ±0.75%   |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfXConformance           |     | 3    | 5   | 8.856mb  | 13.439ms  | ±0.65%   |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfVtConformance          |     | 3    | 5   | 8.871mb  | 13.410ms  | ±0.84%   |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfEConformance           |     | 3    | 5   | 8.997mb  | 14.149ms  | ±0.67%   |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfRConformance           |     | 3    | 5   | 5.662mb  | 2.424ms   | ±0.75%   |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfX5Conformance          |     | 3    | 5   | 8.859mb  | 13.446ms  | ±0.98%   |
| GeneratePdfBench | benchPhpdftk10PagesWithZugferdConformance        |     | 3    | 5   | 8.918mb  | 13.499ms  | ±0.74%   |
| GeneratePdfBench | benchPhpdftk10PagesWithPdfMailConformance        |     | 3    | 5   | 8.747mb  | 13.168ms  | ±0.77%   |
| ReadPdfBench     | benchPhpdftk1Page                                |     | 3    | 5   | 4.196mb  | 1.236ms   | ±1.16%   |
| ReadPdfBench     | benchPhpdftk10Pages                              |     | 3    | 5   | 4.196mb  | 1.672ms   | ±1.76%   |
| ReadPdfBench     | benchPhpdftk100Pages                             |     | 3    | 5   | 4.553mb  | 6.117ms   | ±0.49%   |
| ReadPdfBench     | benchPhpdftkSpecCompliantXref                    |     | 3    | 5   | 4.196mb  | 2.036ms   | ±0.90%   |
| ReadPdfBench     | benchPhpdftkXrefStream                           |     | 3    | 5   | 4.196mb  | 1.360ms   | ±0.69%   |
| ReadPdfBench     | benchSmalot1Page                                 |     | 3    | 5   | 4.701mb  | 2.016ms   | ±1.02%   |
| ReadPdfBench     | benchSmalot10Pages                               |     | 3    | 5   | 4.802mb  | 2.383ms   | ±0.40%   |
| ReadPdfBench     | benchSmalot100Pages                              |     | 3    | 5   | 6.559mb  | 5.693ms   | ±0.16%   |
| ReadPdfBench     | benchSmalotSpecCompliantXref                     |     | 3    | 5   | 4.196mb  | 564.737μs | ±1.44%   |
| ReadPdfBench     | benchSmalotXrefStream                            |     | 3    | 5   | 4.698mb  | 1.913ms   | ±1.01%   |
| ReadPdfBench     | benchFpdi1Page                                   |     | 3    | 5   | 4.804mb  | 1.875ms   | ±0.93%   |
| ReadPdfBench     | benchFpdi10Pages                                 |     | 3    | 5   | 4.805mb  | 2.669ms   | ±1.31%   |
| ReadPdfBench     | benchFpdi100Pages                                |     | 3    | 5   | 5.476mb  | 28.586ms  | ±1.12%   |
| ReadPdfBench     | benchFpdiSpecCompliantXref                       |     | 3    | 5   | 4.824mb  | 2.883ms   | ±1.08%   |
| ReadPdfBench     | benchFpdiXrefStream                              |     | 3    | 5   | 4.757mb  | 1.501ms   | ±1.51%   |
| ReadPdfBench     | benchPhpdftkTextExtractionWithFormXObjects       |     | 3    | 5   | 5.817mb  | 6.865ms   | ±0.63%   |
| ReadPdfBench     | benchPhpdftkPositionedTextExtraction             |     | 3    | 5   | 5.788mb  | 5.191ms   | ±0.95%   |
| ReadPdfBench     | benchPhpdftkLinearizedPdf                        |     | 3    | 5   | 5.835mb  | 3.776ms   | ±1.97%   |
| ReadPdfBench     | benchPhpdftkWoff2Parsing                         |     | 5    | 3   | 4.196mb  | 3.800μs   | ±4.30%   |
| ReadPdfBench     | benchPhpdftkConformanceChecker                   |     | 3    | 5   | 5.299mb  | 6.149ms   | ±0.54%   |
+------------------+--------------------------------------------------+-----+------+-----+----------+-----------+----------+

```