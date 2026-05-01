# Compliance Report Card

> **Auto-generated.** Run `scripts/compliance` from the repo root to update this file.

Generated: 2026-05-01 19:57:51 UTC
PHP: 8.4.19

---

## Summary

**Overall: PASS** | 275 tests | 275 passed | 0 failed | 0 skipped | 344.96s

| Suite | Status | Tests | Passed | Failed | Skipped | Time |
|---|---|---|---|---|---|---|
| &#x2705; QPDF | PASS | 236 | 236 | 0 | 0 | 56.27s |
| &#x2705; Arlington PDF Model | PASS | 6 | 6 | 0 | 0 | 3.35s |
| &#x2705; veraPDF | PASS | 2 | 2 | 0 | 0 | 85.86s |
| &#x2753; Test Corpora | NO TESTS | 0 | 0 | 0 | 0 | <1ms |
| &#x2705; Matterhorn (PDF/UA) | PASS | 6 | 6 | 0 | 0 | 123.22s |
| &#x2705; JHOVE + PDF 2.0 + Security + Preflight | PASS | 25 | 25 | 0 | 0 | 76.26s |

---

## Tier 1 — Integrated

These validation tools run as part of the test suite. See [docs/validations/](validations/) for integration details.

### QPDF — Structural integrity (xref, page tree, streams, linearization, encryption)

**236 tests** | 236 passed | 0 failed | 0 skipped | 56.27s

| Test | Class | Status | Time |
|---|---|---|---|
| testGeneratesAnnotationSubtypesPdf | AnnotationSubtypesTest | PASS | 829ms |
| testGeneratesAnnotationsPdf | AnnotationsTest | PASS | 445ms |
| testGeneratesBookmarksPdf | BookmarksTest | PASS | 425ms |
| testGeneratesFormWithCustomFontAppearances | CustomFontFormAppearancesTest | PASS | 472ms |
| testGeneratesDocumentFeaturesPdf | DocumentFeaturesTest | PASS | 301ms |
| testGeneratesPdfWithEmbeddedTrueTypeFont | DocumentFeaturesTest | PASS | 328ms |
| testGeneratesPdfWithEmbeddedFont | EmbeddedFontsTest | PASS | 600ms |
| testEmbeddedFontHasFontDescriptor | EmbeddedFontsTest | PASS | 12ms |
| testEmbeddedFontHasToUnicode | EmbeddedFontsTest | PASS | 10ms |
| testEmbeddedFontWidthsArray | EmbeddedFontsTest | PASS | 2ms |
| testFromFileReturnsType1Font | EmbeddedType1FontTest | PASS | 3ms |
| testFromFileSetsBaseFont | EmbeddedType1FontTest | PASS | <1ms |
| testFromFileSetsWidths | EmbeddedType1FontTest | PASS | <1ms |
| testEmbeddingCreatesDescriptor | EmbeddedType1FontTest | PASS | <1ms |
| testEmbeddingCreatesToUnicode | EmbeddedType1FontTest | PASS | <1ms |
| testGeneratesPdfWithEmbeddedType1 | EmbeddedType1FontTest | PASS | 631ms |
| testPdfOutputContainsFontData | EmbeddedType1FontTest | PASS | <1ms |
| testGeneratesExtGStatePdf | ExtGStateIntegrationTest | PASS | 302ms |
| testGeneratesFormWithAppearances | FormAppearancesIntegrationTest | PASS | 322ms |
| testGeneratesFormPdf | FormFieldsTest | PASS | 292ms |
| testGeneratesGraphicsPipelinePdf | GraphicsPipelineIntegrationTest | PASS | 261ms |
| testGeneratesGraphicsPdf | GraphicsTest | PASS | 564ms |
| testGeneratesSignedPdfWithDss | LtvSignedPdfIntegrationTest | PASS | 408ms |
| testDssContainsCertificateStreams | LtvSignedPdfIntegrationTest | PASS | 19ms |
| testVriDictionaryMatchesSignatureHash | LtvSignedPdfIntegrationTest | PASS | 18ms |
| testOriginalSignatureRemainsVerifiable | LtvSignedPdfIntegrationTest | PASS | 36ms |
| testGeneratesPdfWithMarkupFields | MarkupAnnotationsIntegrationTest | PASS | 288ms |
| testGeneratesMultiPageComplexPdf | MultiPageComplexTest | PASS | 398ms |
| testGeneratesMultimediaAnd3DPdf | MultimediaAndThreeDIntegrationTest | PASS | 360ms |
| testGeneratesPdfWithOpenTypeCff | OpenTypeFontIntegrationTest | PASS | 302ms |
| testOpenTypeFontEmbedsCffBytes | OpenTypeFontIntegrationTest | PASS | 5ms |
| testGeneratesPageLabelsPdf | PageLabelsTest | PASS | 231ms |
| testGeneratesSignatureFieldPdf | SignatureFieldIntegrationTest | PASS | 284ms |
| testGeneratesAndVerifiesSignedPdf | SignedPdfIntegrationTest | PASS | 302ms |
| testGeneratesSimpleTextPdf | SimpleTextTest | PASS | 235ms |
| testGeneratesPdfWithType3Font | Type3FontIntegrationTest | PASS | 221ms |
| testGeneratesPdfWithXrefAndObjectStreams | XRefStreamIntegrationTest | PASS | 270ms |
| testGeneratePdfWithKernedOpenTypeFont | KerningIntegrationTest | PASS | 321ms |
| testKernedPdfContainsTjOperator | KerningIntegrationTest | PASS | 271ms |
| testGeneratesHighLevelDocument | PdfIntegrationTest | PASS | 455ms |
| testEmptyDocumentHasNoPages | PdfTest | PASS | 434ms |
| testAddTextCreatesFirstPageAutomatically | PdfTest | PASS | 232ms |
| testAddHeadingEmitsLargerText | PdfTest | PASS | 224ms |
| testExplicitNewPageCreatesSecondPage | PdfTest | PASS | 233ms |
| testLongTextAutoPaginates | PdfTest | PASS | 495ms |
| testAddSpacerConsumesVerticalSpace | PdfTest | PASS | 279ms |
| testAddRuleDrawsStroke | PdfTest | PASS | 259ms |
| testAlignmentCenterEmitsCenteredText | PdfTest | PASS | 266ms |
| testSetFontSwitchesFontFamily | PdfTest | PASS | 301ms |
| testBoldAndItalicResolveToCorrectPostScriptName | PdfTest | PASS | 295ms |
| testUnknownFamilyIsRejected | PdfTest | PASS | 1ms |
| testCustomTheme | PdfTest | PASS | 284ms |
| testSaveWritesFile | PdfTest | PASS | 254ms |
| testWriteToStream | PdfTest | PASS | 311ms |
| testWriteToRejectsNonResource | PdfTest | PASS | <1ms |
| testToBytesProducesValidPdfOnEachCall | PdfTest | PASS | 660ms |
| testEscapeHatchReturnsWriter | PdfTest | PASS | <1ms |
| testType0FontFactoryCreatesValidStack | UnicodeFontTest | PASS | 5ms |
| testAddCompositeFontGeneratesValidPdf | UnicodeFontTest | PASS | 327ms |
| testAddCompositeFontSavesToFile | UnicodeFontTest | PASS | 300ms |
| testCompositeFontAppearsInFontList | UnicodeFontTest | PASS | 8ms |
| testShowTextHexOperator | UnicodeFontTest | PASS | <1ms |
| testCompositeFontPerPage | UnicodeFontTest | PASS | 206ms |
| testPdfWriterGeneratesValidPdfHeader | WriterTest | PASS | 320ms |
| testPdfWriterGeneratesWithEndMarker | WriterTest | PASS | <1ms |
| testPdfWriterContainsCatalog | WriterTest | PASS | <1ms |
| testPdfWriterContainsPageTree | WriterTest | PASS | <1ms |
| testPdfWriterContainsPage | WriterTest | PASS | 366ms |
| testPdfWriterAddFont | WriterTest | PASS | <1ms |
| testPdfWriterMultipleFontsIncrement | WriterTest | PASS | <1ms |
| testPdfWriterGetFonts | WriterTest | PASS | <1ms |
| testPdfWriterAddContentStream | WriterTest | PASS | 615ms |
| testPdfWriterGetContentStreams | WriterTest | PASS | 2ms |
| testPdfWriterWithInfo | WriterTest | PASS | 281ms |
| testPdfWriterGetCatalog | WriterTest | PASS | <1ms |
| testPdfWriterGetPageTree | WriterTest | PASS | <1ms |
| testPdfWriterSavesToFile | WriterTest | PASS | 210ms |
| testPdfWriterContainsXref | WriterTest | PASS | 265ms |
| testPdfWriterContainsTrailer | WriterTest | PASS | <1ms |
| testPdfWriterAddPageWithRectangle | WriterTest | PASS | 214ms |
| testPdfWriterRegisterObject | WriterTest | PASS | 254ms |
| testPdfWriterFontAddedToPage | WriterTest | PASS | 367ms |
| testSetNamedDestinations | WriterTest | PASS | 222ms |
| testSetEncryptionProducesEncryptedPdf | WriterTest | PASS | 251ms |
| testSetEncryptionAes256RoundTrip | WriterTest | PASS | 259ms |
| testSetMetadataAddsStreamToPdf | XmpMetadataTest | PASS | 402ms |
| testMetadataStreamContainsXmp | XmpMetadataTest | PASS | 367ms |
| testMetadataRoundTrip | XmpMetadataTest | PASS | 404ms |
| testSyncInfoToMetadata | XmpMetadataTest | PASS | 621ms |
| testSyncInfoToMetadataNoInfoIsNoOp | XmpMetadataTest | PASS | 423ms |
| testFlattenAll | AnnotationFlattenerTest | PASS | 269ms |
| testNoOpsReturnsOriginal | AnnotationFlattenerTest | PASS | <1ms |
| testPageCount | AnnotationFlattenerTest | PASS | <1ms |
| testEscapeHatch | AnnotationFlattenerTest | PASS | <1ms |
| testSaveToFile | AnnotationFlattenerTest | PASS | 365ms |
| testSetBookmarks | BookmarkEditorTest | PASS | 618ms |
| testSetBookmarksWithChildren | BookmarkEditorTest | PASS | 283ms |
| testAddBookmark | BookmarkEditorTest | PASS | 1.29s |
| testHasBookmarksReturnsFalseForCleanPdf | BookmarkEditorTest | PASS | 1ms |
| testRemoveBookmarks | BookmarkEditorTest | PASS | 913ms |
| testGetPageCount | BookmarkEditorTest | PASS | 1ms |
| testGetReader | BookmarkEditorTest | PASS | <1ms |
| testSaveToFile | BookmarkEditorTest | PASS | 350ms |
| testNoBytesChangedWhenNoOperations | BookmarkEditorTest | PASS | <1ms |
| testReplaceExistingBookmarks | BookmarkEditorTest | PASS | 298ms |
| testOpenString | FormFillerTest | PASS | 3ms |
| testGetFieldNames | FormFillerTest | PASS | 1ms |
| testHasField | FormFillerTest | PASS | 1ms |
| testGetFieldInfo | FormFillerTest | PASS | 2ms |
| testGetFieldInfoReturnsNullForMissing | FormFillerTest | PASS | 1ms |
| testGetFieldValues | FormFillerTest | PASS | 1ms |
| testFillTextField | FormFillerTest | PASS | 258ms |
| testFillManyFields | FormFillerTest | PASS | 295ms |
| testCheckCheckbox | FormFillerTest | PASS | 431ms |
| testUncheckCheckbox | FormFillerTest | PASS | 731ms |
| testSelectChoiceField | FormFillerTest | PASS | 410ms |
| testFillMultipleFieldTypes | FormFillerTest | PASS | 397ms |
| testFillThrowsForUnknownField | FormFillerTest | PASS | 3ms |
| testCheckThrowsForUnknownField | FormFillerTest | PASS | 1ms |
| testSelectThrowsForUnknownField | FormFillerTest | PASS | 2ms |
| testToBytesWithNoChangesReturnsOriginal | FormFillerTest | PASS | 2ms |
| testSaveWritesFile | FormFillerTest | PASS | 416ms |
| testEscapeHatch | FormFillerTest | PASS | 2ms |
| testRoundTripPreservesOtherFields | FormFillerTest | PASS | 320ms |
| testReadExistingMetadata | MetadataEditorTest | PASS | 2ms |
| testReadNoMetadata | MetadataEditorTest | PASS | <1ms |
| testGetAll | MetadataEditorTest | PASS | 12ms |
| testSetTitleRoundTrip | MetadataEditorTest | PASS | 269ms |
| testSetMultipleFieldsRoundTrip | MetadataEditorTest | PASS | 221ms |
| testSetMetadataOnPdfWithoutInfo | MetadataEditorTest | PASS | 348ms |
| testNoBytesChangedWithoutModifications | MetadataEditorTest | PASS | <1ms |
| testPageCount | MetadataEditorTest | PASS | <1ms |
| testEscapeHatch | MetadataEditorTest | PASS | <1ms |
| testCustomField | MetadataEditorTest | PASS | 299ms |
| testSaveToFile | MetadataEditorTest | PASS | 256ms |
| testSetLabelsWithArabic | PageLabelerTest | PASS | 277ms |
| testSetRomanNumerals | PageLabelerTest | PASS | 386ms |
| testSetRomanNumeralsUppercase | PageLabelerTest | PASS | 519ms |
| testSetAlphabetic | PageLabelerTest | PASS | 280ms |
| testSetArabicWithStartNumber | PageLabelerTest | PASS | 266ms |
| testSetLabelsWithPrefix | PageLabelerTest | PASS | 224ms |
| testRemoveLabels | PageLabelerTest | PASS | 230ms |
| testMultipleRanges | PageLabelerTest | PASS | 235ms |
| testGetPageCount | PageLabelerTest | PASS | <1ms |
| testGetReader | PageLabelerTest | PASS | <1ms |
| testSaveToFile | PageLabelerTest | PASS | 304ms |
| testNoBytesChangedWhenNoOperations | PageLabelerTest | PASS | <1ms |
| testOpenFromFile | PageLabelerTest | PASS | 1ms |
| testKeepPages | PageSlicerTest | PASS | 353ms |
| testKeepRange | PageSlicerTest | PASS | 195ms |
| testRemovePages | PageSlicerTest | PASS | 225ms |
| testReorder | PageSlicerTest | PASS | 347ms |
| testReverse | PageSlicerTest | PASS | 367ms |
| testSplit | PageSlicerTest | PASS | 453ms |
| testKeepWithPageSelector | PageSlicerTest | PASS | 437ms |
| testNoOpsKeepsAllPages | PageSlicerTest | PASS | 417ms |
| testPageCount | PageSlicerTest | PASS | <1ms |
| testEscapeHatch | PageSlicerTest | PASS | <1ms |
| testRotateAllPages | PageTransformerTest | PASS | 286ms |
| testRotate180 | PageTransformerTest | PASS | 442ms |
| testRotate270 | PageTransformerTest | PASS | 248ms |
| testRotateSpecificPages | PageTransformerTest | PASS | 304ms |
| testRotateCumulative | PageTransformerTest | PASS | 383ms |
| testRotateInvalidAngle | PageTransformerTest | PASS | <1ms |
| testSetCropBox | PageTransformerTest | PASS | 249ms |
| testSetCropBoxSpecificPage | PageTransformerTest | PASS | 251ms |
| testSetMediaBox | PageTransformerTest | PASS | 388ms |
| testSetTrimBox | PageTransformerTest | PASS | 227ms |
| testSetBleedBox | PageTransformerTest | PASS | 237ms |
| testScale | PageTransformerTest | PASS | 368ms |
| testScaleSpecificPages | PageTransformerTest | PASS | 216ms |
| testScaleInvalidFactor | PageTransformerTest | PASS | <1ms |
| testScaleTo | PageTransformerTest | PASS | 276ms |
| testScaleToNonUniform | PageTransformerTest | PASS | 224ms |
| testMultipleOperations | PageTransformerTest | PASS | 290ms |
| testNoBytesChangedWithoutOperations | PageTransformerTest | PASS | <1ms |
| testEvenPages | PageTransformerTest | PASS | 306ms |
| testOddPages | PageTransformerTest | PASS | 268ms |
| testRange | PageTransformerTest | PASS | 258ms |
| testGetReader | PageTransformerTest | PASS | <1ms |
| testGetPageCount | PageTransformerTest | PASS | <1ms |
| testSaveToFile | PageTransformerTest | PASS | 211ms |
| testEncryptAes128 | PdfEncryptTest | PASS | 265ms |
| testEncryptAes256 | PdfEncryptTest | PASS | 389ms |
| testDecrypt | PdfEncryptTest | PASS | 282ms |
| testIsEncrypted | PdfEncryptTest | PASS | <1ms |
| testNoOpsReturnsOriginal | PdfEncryptTest | PASS | <1ms |
| testPageCount | PdfEncryptTest | PASS | <1ms |
| testEscapeHatch | PdfEncryptTest | PASS | <1ms |
| testMergeTwoPdfs | PdfMergerTest | PASS | 419ms |
| testMergeWithPageSelection | PdfMergerTest | PASS | 647ms |
| testSourceCount | PdfMergerTest | PASS | 1ms |
| testTotalPageCount | PdfMergerTest | PASS | 1ms |
| testNoSourcesThrows | PdfMergerTest | PASS | <1ms |
| testSaveToFile | PdfMergerTest | PASS | 297ms |
| testStampText | PdfStamperTest | PASS | 286ms |
| testWatermark | PdfStamperTest | PASS | 281ms |
| testWatermarkWithStyle | PdfStamperTest | PASS | 212ms |
| testPageNumbers | PdfStamperTest | PASS | 289ms |
| testStampOnSpecificPages | PdfStamperTest | PASS | 533ms |
| testHeaderAndFooter | PdfStamperTest | PASS | 292ms |
| testStampWithOpacity | PdfStamperTest | PASS | 387ms |
| testNoOpsReturnsOriginal | PdfStamperTest | PASS | <1ms |
| testPageCount | PdfStamperTest | PASS | <1ms |
| testEscapeHatch | PdfStamperTest | PASS | <1ms |
| testSaveToFile | PdfStamperTest | PASS | 1.11s |
| testStampImageJpeg | PdfStamperTest | PASS | 776ms |
| testStampImagePng | PdfStamperTest | PASS | 276ms |
| testStampImageWithScaleWidth | PdfStamperTest | PASS | 411ms |
| testStampImageWithScaleHeight | PdfStamperTest | PASS | 478ms |
| testStampImageWithExplicitDimensions | PdfStamperTest | PASS | 332ms |
| testStampImageWithOpacity | PdfStamperTest | PASS | 264ms |
| testStampImageOnSpecificPages | PdfStamperTest | PASS | 251ms |
| testStampImageCombinedWithText | PdfStamperTest | PASS | 367ms |
| testStampImageThrowsOnMissingFile | PdfStamperTest | PASS | 1ms |
| testStampImageThrowsOnUnsupportedFormat | PdfStamperTest | PASS | 4ms |
| testStampPdf | PdfStamperTest | PASS | 564ms |
| testStampPdfWithScaling | PdfStamperTest | PASS | 347ms |
| testStampPdfWithOpacity | PdfStamperTest | PASS | 284ms |
| testStampPdfSpecificPage | PdfStamperTest | PASS | 284ms |
| testStampPdfOnSelectedPages | PdfStamperTest | PASS | 890ms |
| testStampPdfDefaultsToCenter | PdfStamperTest | PASS | 725ms |
| testStampPdfThrowsOnMissingFile | PdfStamperTest | PASS | 1ms |
| testStampPdfThrowsOnInvalidPageIndex | PdfStamperTest | PASS | 7ms |
| testStampPdfThrowsOnNegativePageIndex | PdfStamperTest | PASS | 3ms |
| testImageStampStyleDefaults | PdfStamperTest | PASS | <1ms |
| testRedactArea | TextRedactorTest | PASS | 676ms |
| testRedactMultipleAreas | TextRedactorTest | PASS | 564ms |
| testRedactByText | TextRedactorTest | PASS | 549ms |
| testRedactByPattern | TextRedactorTest | PASS | 1ms |
| testCustomRedactionColor | TextRedactorTest | PASS | 639ms |
| testApplyRequiredBeforeToBytes | TextRedactorTest | PASS | 2ms |
| testNoRedactionsReturnsOriginal | TextRedactorTest | PASS | <1ms |
| testPageCount | TextRedactorTest | PASS | <1ms |
| testEscapeHatch | TextRedactorTest | PASS | <1ms |
| testSaveToFile | TextRedactorTest | PASS | 392ms |

### Arlington PDF Model — Dictionary-level spec conformance (keys, types, required fields, version constraints)

**6 tests** | 6 passed | 0 failed | 0 skipped | 3.35s

| Test | Class | Status | Time |
|---|---|---|---|
| testGeneratesBookmarksPdf | BookmarksTest | PASS | 921ms |
| testGeneratesDocumentFeaturesPdf | DocumentFeaturesTest | PASS | 691ms |
| testGeneratesPdfWithEmbeddedTrueTypeFont | DocumentFeaturesTest | PASS | 406ms |
| testGeneratesFormPdf | FormFieldsTest | PASS | 376ms |
| testGeneratesMultiPageComplexPdf | MultiPageComplexTest | PASS | 320ms |
| testGeneratesSimpleTextPdf | SimpleTextTest | PASS | 640ms |

### veraPDF — PDF/A archival conformance (ISO 19005)

**2 tests** | 2 passed | 0 failed | 0 skipped | 85.86s

| Test | Class | Status | Time |
|---|---|---|---|
| testMinimalPdfWithOutputIntent | PdfAConformanceTest | PASS | 57.82s |
| testVeraPdfToolchainWorks | PdfAConformanceTest | PASS | 28.05s |


---

## Tier 2 — Test Corpora

PDF test file collections from major PDF implementations for stress-testing reader error tolerance and edge-case handling.

| Suite | Source | Status |
|---|---|---|
| Poppler Test Files | gitlab.freedesktop.org/poppler/test | Integrated |
| QPDF Test Suite | github.com/qpdf/qpdf | Integrated |
| veraPDF Corpus (Isartor/Bavaria) | github.com/veraPDF/veraPDF-corpus | Integrated |
| PDFium Test Resources | github.com/chromium/pdfium | Integrated |
| Apache PDFBox Test Files | github.com/apache/pdfbox | Integrated |

### Test Corpora — Reader robustness against Poppler, QPDF, PDFium, PDFBox, and veraPDF corpus PDFs

**0 tests** | 0 passed | 0 failed | 0 skipped | <1ms

_No test results available._



---

## Tier 3 — Accessibility Compliance

Tools and test suites for PDF/UA (Universal Accessibility) and WCAG compliance.

| Suite | What it validates | Status |
|---|---|---|
| Matterhorn Protocol (via veraPDF) | PDF/UA-1 failure conditions | Integrated |
| PAC (PDF Accessibility Checker) | PDF/UA-1, PDF/UA-2, WCAG 2.1 | N/A (Windows only) |
| W3C PDF Techniques | 23 WCAG 2.x techniques (PDF1-PDF23) | N/A (reference docs) |
| PDF/UA-2 Test Resources | ISO 14289-2:2024 (PDF 2.0 based) | N/A (emerging standard) |

### Matterhorn (PDF/UA) — PDF/UA-1 accessibility validation via veraPDF ua1 profile

**6 tests** | 6 passed | 0 failed | 0 skipped | 123.22s

| Test | Class | Status | Time |
|---|---|---|---|
| testTaggedDocumentValidatesWithUa1 | Tier3MatterhornTest | PASS | 58.4s |
| testAnnotationWithContentsPassesClause718 | Tier3MatterhornTest | PASS | 27.88s |
| testUntaggedDocumentFailsUa1 | Tier3MatterhornTest | PASS | 9.35s |
| testMissingLangFailsUa1 | Tier3MatterhornTest | PASS | 9.11s |
| testMissingDisplayDocTitleFailsUa1 | Tier3MatterhornTest | PASS | 9.58s |
| testAnnotationWithoutContentsFailsUa1 | Tier3MatterhornTest | PASS | 8.9s |



---

## Tier 4 — Reference and Conformance Targets

General-purpose validation tools and reference document collections.

| Suite | What it validates | Status |
|---|---|---|
| JHOVE | Format validation and characterization | Integrated |
| PDF 2.0 Examples | Reference PDF 2.0 documents | Integrated |
| Didier Stevens' pdfid | Security analysis (JS, auto-open, etc.) | Integrated |
| Apache PDFBox Preflight | PDF/A-1b cross-validation | Integrated |
| pdfaPilot (Callas) | Commercial PDF/A, PDF/X, PDF/UA, PDF/VT | N/A (commercial license) |

### JHOVE + PDF 2.0 + Security + Preflight — Format validation, PDF 2.0 reference parsing, security lint, PDF/A-1b cross-validation

**25 tests** | 25 passed | 0 failed | 0 skipped | 76.26s

| Test | Class | Status | Time |
|---|---|---|---|
| testMinimalPdfWellFormedAndValid | Tier4JhoveTest | PASS | 14.7s |
| testMultiPagePdfWellFormedAndValid | Tier4JhoveTest | PASS | 16.68s |
| testPdfWithMetadataWellFormedAndValid | Tier4JhoveTest | PASS | 13.78s |
| testPdfWithEmbeddedFontWellFormedAndValid | Tier4JhoveTest | PASS | 15.4s |
| testJhoveToolchainWorks | Tier4JhoveTest | PASS | 9.06s |
| testPdfA1bPassesPdfBoxPreflight | Tier4PdfBoxPreflightTest | PASS | 1.21s |
| testPdfBoxPreflightToolchainWorks | Tier4PdfBoxPreflightTest | PASS | 998ms |
| testMinimalPdfHasNoSuspiciousIndicators | Tier4PdfIdTest | PASS | 918ms |
| testPdfWithMetadataHasNoSuspiciousIndicators | Tier4PdfIdTest | PASS | 668ms |
| testPdfWithEmbeddedFontHasNoSuspiciousIndicators | Tier4PdfIdTest | PASS | 662ms |
| testPdfIdToolchainWorks | Tier4PdfIdTest | PASS | 376ms |
| testPdf20ExampleParseable with data set "pdf20/PDF 2.0 UTF-8 string and annotation.pdf" | Tier4Pdf20ExamplesTest | PASS | 4ms |
| testPdf20ExampleParseable with data set "pdf20/PDF 2.0 image with BPC.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleParseable with data set "pdf20/PDF 2.0 via incremental save.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleParseable with data set "pdf20/PDF 2.0 with offset start.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleParseable with data set "pdf20/PDF 2.0 with page level output intent.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleParseable with data set "pdf20/Simple PDF 2.0 file.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleParseable with data set "pdf20/pdf20-utf8-test.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleQpdfValid with data set "pdf20/PDF 2.0 UTF-8 string and annotation.pdf" | Tier4Pdf20ExamplesTest | PASS | 255ms |
| testPdf20ExampleQpdfValid with data set "pdf20/PDF 2.0 image with BPC.pdf" | Tier4Pdf20ExamplesTest | PASS | 239ms |
| testPdf20ExampleQpdfValid with data set "pdf20/PDF 2.0 via incremental save.pdf" | Tier4Pdf20ExamplesTest | PASS | 371ms |
| testPdf20ExampleQpdfValid with data set "pdf20/PDF 2.0 with offset start.pdf" | Tier4Pdf20ExamplesTest | PASS | 203ms |
| testPdf20ExampleQpdfValid with data set "pdf20/PDF 2.0 with page level output intent.pdf" | Tier4Pdf20ExamplesTest | PASS | 243ms |
| testPdf20ExampleQpdfValid with data set "pdf20/Simple PDF 2.0 file.pdf" | Tier4Pdf20ExamplesTest | PASS | 253ms |
| testPdf20ExampleQpdfValid with data set "pdf20/pdf20-utf8-test.pdf" | Tier4Pdf20ExamplesTest | PASS | 233ms |

