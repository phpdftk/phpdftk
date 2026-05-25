# Compliance Report Card

> **Auto-generated.** Run `scripts/compliance` from the repo root to update this file.

Generated: 2026-05-25 05:02:41 UTC
PHP: 8.4.21

---

## Summary

**Overall: PASS** | 1662 tests | 1656 passed | 0 failed | 6 skipped | 90.27s

| Suite | Status | Tests | Passed | Failed | Skipped | Time |
|---|---|---|---|---|---|---|
| &#x2705; QPDF | PASS | 454 | 450 | 0 | 4 | 31.72s |
| &#x2705; Arlington PDF Model | PASS | 6 | 6 | 0 | 0 | 1.23s |
| &#x2705; veraPDF | PASS | 4 | 3 | 0 | 1 | 18.66s |
| &#x2705; Test Corpora | PASS | 1167 | 1167 | 0 | 0 | 18.19s |
| &#x2705; Matterhorn (PDF/UA) | PASS | 6 | 6 | 0 | 0 | 12.06s |
| &#x2705; JHOVE + PDF 2.0 + Security + Preflight | PASS | 25 | 24 | 0 | 1 | 8.41s |

---

## Tier 1 — Integrated

These validation tools run as part of the test suite. See [About the Suites](/standards/validation/about/) for integration details.

### QPDF — Structural integrity (xref, page tree, streams, linearization, encryption)

**454 tests** | 450 passed | 0 failed | 4 skipped | 31.72s

| Test | Class | Status | Time |
|---|---|---|---|
| testGeneratesAnnotationSubtypesPdf | AnnotationSubtypesTest | PASS | 331ms |
| testGeneratesAnnotationsPdf | AnnotationsTest | PASS | 184ms |
| testGeneratesBookmarksPdf | BookmarksTest | PASS | 229ms |
| testGeneratesFormWithCustomFontAppearances | CustomFontFormAppearancesTest | PASS | 207ms |
| testGeneratesDocumentFeaturesPdf | DocumentFeaturesTest | PASS | 191ms |
| testGeneratesPdfWithEmbeddedTrueTypeFont | DocumentFeaturesTest | PASS | 203ms |
| testGeneratesPdfWithEmbeddedFont | EmbeddedFontsTest | PASS | 188ms |
| testEmbeddedFontHasFontDescriptor | EmbeddedFontsTest | PASS | 8ms |
| testEmbeddedFontHasToUnicode | EmbeddedFontsTest | PASS | 8ms |
| testEmbeddedFontWidthsArray | EmbeddedFontsTest | PASS | 2ms |
| testFromFileReturnsType1Font | EmbeddedType1FontTest | PASS | 1ms |
| testFromFileSetsBaseFont | EmbeddedType1FontTest | PASS | <1ms |
| testFromFileSetsWidths | EmbeddedType1FontTest | PASS | <1ms |
| testEmbeddingCreatesDescriptor | EmbeddedType1FontTest | PASS | <1ms |
| testEmbeddingCreatesToUnicode | EmbeddedType1FontTest | PASS | <1ms |
| testGeneratesPdfWithEmbeddedType1 | EmbeddedType1FontTest | PASS | 191ms |
| testPdfOutputContainsFontData | EmbeddedType1FontTest | PASS | <1ms |
| testGeneratesExtGStatePdf | ExtGStateIntegrationTest | PASS | 183ms |
| testGeneratesFormWithAppearances | FormAppearancesIntegrationTest | PASS | 192ms |
| testGeneratesFormPdf | FormFieldsTest | PASS | 194ms |
| testGeneratesGraphicsPipelinePdf | GraphicsPipelineIntegrationTest | PASS | 181ms |
| testGeneratesGraphicsPdf | GraphicsTest | PASS | 188ms |
| testGeneratesSignedPdfWithDss | LtvSignedPdfIntegrationTest | PASS | 598ms |
| testDssContainsCertificateStreams | LtvSignedPdfIntegrationTest | PASS | 291ms |
| testVriDictionaryMatchesSignatureHash | LtvSignedPdfIntegrationTest | PASS | 130ms |
| testOriginalSignatureRemainsVerifiable | LtvSignedPdfIntegrationTest | PASS | 361ms |
| testGeneratesPdfWithMarkupFields | MarkupAnnotationsIntegrationTest | PASS | 191ms |
| testGeneratesMultiPageComplexPdf | MultiPageComplexTest | PASS | 188ms |
| testGeneratesMultimediaAnd3DPdf | MultimediaAndThreeDIntegrationTest | PASS | 185ms |
| testGeneratesPdfWithOpenTypeCff | OpenTypeFontIntegrationTest | SKIP | <1ms |
| testOpenTypeFontEmbedsCffBytes | OpenTypeFontIntegrationTest | SKIP | <1ms |
| testGeneratesPageLabelsPdf | PageLabelsTest | PASS | 185ms |
| testGeneratesSignatureFieldPdf | SignatureFieldIntegrationTest | PASS | 182ms |
| testGeneratesAndVerifiesSignedPdf | SignedPdfIntegrationTest | PASS | 336ms |
| testGeneratesSimpleTextPdf | SimpleTextTest | PASS | 181ms |
| testGeneratesPdfWithType3Font | Type3FontIntegrationTest | PASS | 177ms |
| testGeneratesPdfWithXrefAndObjectStreams | XRefStreamIntegrationTest | PASS | 175ms |
| testEnableOutlineThenHeadingRegistersOutlinesEntry | AutoOutlineTest | PASS | 187ms |
| testHeadingDestinationUsesXyzWithCurrentPageRef | AutoOutlineTest | PASS | <1ms |
| testHierarchyParentsH2UnderPriorH1 | AutoOutlineTest | PASS | <1ms |
| testOutlineCountReflectsTotalEntries | AutoOutlineTest | PASS | <1ms |
| testDisablingOutlineStopsRecordingFurtherHeadings | AutoOutlineTest | PASS | <1ms |
| testEnablingOutlineAfterFirstHeadingMissesEarlierHeadings | AutoOutlineTest | PASS | <1ms |
| testEnableOutlineIsIdempotent | AutoOutlineTest | PASS | <1ms |
| testHeadingsWithoutEnableOutlineProduceNoOutlinesEntry | AutoOutlineTest | PASS | <1ms |
| testSiblingChainAtSameLevelLinksPrevAndNext | AutoOutlineTest | PASS | <1ms |
| testPdfDocCreateBarcodeReturnsFormXObject | BarcodeAdapterTest | PASS | 1ms |
| testWriterPageDrawBarcodeEmitsFillRectangles | BarcodeAdapterTest | PASS | <1ms |
| testPdfAddBarcodeFlowsAtCursor | BarcodeAdapterTest | PASS | <1ms |
| testPdfAddBarcodeRespectsAlignmentAndCustomOptions | BarcodeAdapterTest | PASS | <1ms |
| testEmptyDataBubblesUpAsInvalidArgument | BarcodeAdapterTest | PASS | <1ms |
| testDataMatrixAdapterEmits2DGrid | BarcodeAdapterTest | PASS | 1ms |
| testQrCodeAdapterPlacesA2dMatrix | BarcodeAdapterTest | PASS | 7ms |
| testAddQuoteRendersItalicTextByDefault | BlockquoteTest | PASS | <1ms |
| testAddQuoteDrawsLeftBarStroke | BlockquoteTest | PASS | <1ms |
| testAddQuoteIndentsTextByThemeQuoteIndent | BlockquoteTest | PASS | <1ms |
| testAddQuoteCanOverrideItalicViaTextStyle | BlockquoteTest | PASS | <1ms |
| testAddQuoteHonoursTextStyleColor | BlockquoteTest | PASS | <1ms |
| testAddQuoteAutoPaginatesAndDrawsBarOnEachPage | BlockquoteTest | PASS | 8ms |
| testEmptyQuoteStillEmitsBarSegment | BlockquoteTest | PASS | <1ms |
| testThemeQuoteBarWidthAndColorAreHonoured | BlockquoteTest | PASS | <1ms |
| testWriterPageDrawQuoteRendersExplicitlyPositioned | BlockquoteTest | PASS | <1ms |
| testAddQuoteFollowedByAddTextResumesUnindentedFlow | BlockquoteTest | PASS | <1ms |
| testNoteCalloutEmitsLabelAndBody | CalloutTest | PASS | 185ms |
| testEachTypeUsesItsOwnBarColor | CalloutTest | PASS | <1ms |
| testCalloutEmitsBackgroundFillRectangle | CalloutTest | PASS | <1ms |
| testShowLabelFalseSkipsTitleRow | CalloutTest | PASS | <1ms |
| testLabelOverrideReplacesDefaultLabel | CalloutTest | PASS | <1ms |
| testStyleOverridesBarAndBackgroundColors | CalloutTest | PASS | <1ms |
| testCalloutAutoBreaksToNewPageWhenItWontFit | CalloutTest | PASS | 1ms |
| testCalloutTallerThanPageThrows | CalloutTest | PASS | 21ms |
| testWriterPageDrawCalloutRendersPositioned | CalloutTest | PASS | <1ms |
| testThemeColorIsUsedForBodyTextWhenStyleHasNoOverride | CalloutTest | PASS | <1ms |
| testCalloutTypeDefaultLabelMatchesEnumValue | CalloutTest | PASS | <1ms |
| testWinAnsiRoundTripFromShowText | EncodingIntegrationTest | PASS | 181ms |
| testHighLevelPdfRoundTrip | EncodingIntegrationTest | PASS | 4ms |
| testAddTextWithLinkStyleProducesLinkAnnotation | InlineLinkTest | PASS | 186ms |
| testLinkSpansEveryWrappedLine | InlineLinkTest | PASS | <1ms |
| testAddTextWithoutLinkStyleDoesNotEmitLinkAnnotation | InlineLinkTest | PASS | <1ms |
| testLinkAnnotationsLandOnTheCorrectPage | InlineLinkTest | PASS | <1ms |
| testEmptyLinkedTextDoesNotEmitAnnotation | InlineLinkTest | PASS | <1ms |
| testLinkStyleCanCombineWithOtherStyleFields | InlineLinkTest | PASS | <1ms |
| testGeneratePdfWithKernedOpenTypeFont | KerningIntegrationTest | SKIP | <1ms |
| testKernedPdfContainsTjOperator | KerningIntegrationTest | SKIP | <1ms |
| testBulletListRendersEachItem | ListTest | PASS | 184ms |
| testBulletGlyphIsEncodedIntoOutput | ListTest | PASS | <1ms |
| testNumberedListEmitsRunningIndex | ListTest | PASS | <1ms |
| testNumberedListNumberSuffixIsConfigurable | ListTest | PASS | <1ms |
| testLongItemWrapsToMultipleLines | ListTest | PASS | <1ms |
| testLongListAutoPaginates | ListTest | PASS | 8ms |
| testEmptyListIsNoOp | ListTest | PASS | <1ms |
| testListStyleBulletAtCyclesByDepth | ListTest | PASS | <1ms |
| testListStyleBulletAtHandlesEmptyGlyphList | ListTest | PASS | <1ms |
| testWriterPageDrawListPlacesItemsExplicitly | ListTest | PASS | <1ms |
| testCustomIndentAffectsBulletPosition | ListTest | PASS | <1ms |
| testNumberedListNumberingIsIndependentPerCall | ListTest | PASS | <1ms |
| testSetColumnsRequiresAtLeastOne | MultiColumnTest | PASS | <1ms |
| testSetColumnsRejectsNegativeGutter | MultiColumnTest | PASS | <1ms |
| testSingleColumnPreservesExistingBehavior | MultiColumnTest | PASS | <1ms |
| testTwoColumnLayoutShiftsSecondColumnRightOfFirst | MultiColumnTest | PASS | <1ms |
| testColumnAdvancesBeforeNewPage | MultiColumnTest | PASS | 2ms |
| testThreeColumnsFillsAllBeforePaging | MultiColumnTest | PASS | 1ms |
| testPageBreakAfterLastColumnResetsToColumnZero | MultiColumnTest | PASS | 7ms |
| testRuleStaysInsideCurrentColumn | MultiColumnTest | PASS | <1ms |
| testAddPageResetsCurrentColumnToZero | MultiColumnTest | PASS | 1ms |
| testTableInColumnRespectsColumnWidth | MultiColumnTest | PASS | <1ms |
| testSetColumnsBackToOneResumesFullWidth | MultiColumnTest | PASS | <1ms |
| testHeaderClosureRunsOncePerPage | PageDecoratorTest | PASS | <1ms |
| testFooterClosureReceivesTotalPages | PageDecoratorTest | PASS | <1ms |
| testStringWatermarkRendersOnEveryPage | PageDecoratorTest | PASS | <1ms |
| testClosureWatermarkReceivesPageContext | PageDecoratorTest | PASS | <1ms |
| testBodyRegionShrinksByHeaderAndFooterHeight | PageDecoratorTest | PASS | <1ms |
| testDecoratorsAreIdempotentAcrossOutputCalls | PageDecoratorTest | PASS | <1ms |
| testDecoratorPassWithNoPagesIsSafe | PageDecoratorTest | PASS | <1ms |
| testDecoratorPassWithNoDecoratorsIsSkipped | PageDecoratorTest | PASS | <1ms |
| testReplacingHeaderClosureKeepsLastRegistration | PageDecoratorTest | PASS | <1ms |
| testClosureWatermarkAcrossDifferentPageSizes | PageDecoratorTest | PASS | <1ms |
| testDefaultWatermarkUsesNonBlackFillColor | PageDecoratorTest | PASS | <1ms |
| testPagesAddedAfterFirstOutputDoNotRetroactivelyDecorate | PageDecoratorTest | PASS | <1ms |
| testShowPageNumbersDefaultFormat | PageNumbersTest | PASS | 184ms |
| testCustomFormatUsesSprintfPlaceholders | PageNumbersTest | PASS | <1ms |
| testShowPageNumbersCalledTwiceKeepsLastFormat | PageNumbersTest | PASS | <1ms |
| testShowPageNumbersWorksWithZeroFooterHeight | PageNumbersTest | PASS | <1ms |
| testShowPageNumbersRespectsExplicitFooterHeight | PageNumbersTest | PASS | <1ms |
| testShowPageNumbersAcceptsCustomAlignment | PageNumbersTest | PASS | <1ms |
| testPdfDocExposesUnderlyingWriter | PdfDocTest | PASS | <1ms |
| testWrapReusesExistingWriter | PdfDocTest | PASS | <1ms |
| testAddPageDelegatesToWriter | PdfDocTest | PASS | 180ms |
| testSetInfoAttachesInfoDictionary | PdfDocTest | PASS | <1ms |
| testSetOutlineAndAddOutlineItemWireUpCatalog | PdfDocTest | PASS | <1ms |
| testSetPageLabelsRegistersNumberTree | PdfDocTest | PASS | <1ms |
| testSetNamedDestinationsRegistersNameTree | PdfDocTest | PASS | <1ms |
| testSetMetadataAttachesXmpStream | PdfDocTest | PASS | <1ms |
| testSyncInfoToMetadataMirrorsInfoIntoXmp | PdfDocTest | PASS | <1ms |
| testPdfLevelOneExposesPdfDocAccessor | PdfDocTest | PASS | <1ms |
| testFluentMetadataSettersPopulateInfoDict | PdfDocTest | PASS | <1ms |
| testMetadataSettersOnPdfForwardToDoc | PdfDocTest | PASS | <1ms |
| testAddLinkWithUriBuildsActionAnnotation | PdfDocTest | PASS | <1ms |
| testAddLinkWithDestinationFillsDestField | PdfDocTest | PASS | <1ms |
| testAddLinkAttachesBorderStyle | PdfDocTest | PASS | <1ms |
| testWrapPreservesPreConfiguredWriterState | PdfDocTest | PASS | <1ms |
| testFluentMetadataChainMutatesSameInfoDict | PdfDocTest | PASS | <1ms |
| testFluentSetterCreatesInfoDictIfAbsent | PdfDocTest | PASS | <1ms |
| testSyncInfoToMetadataIsNoOpWithoutInfoDict | PdfDocTest | PASS | <1ms |
| testSetPageLabelsWithEmptyArrayStillProducesTree | PdfDocTest | PASS | <1ms |
| testSetNamedDestinationsWithEmptyArrayStillRegisters | PdfDocTest | PASS | <1ms |
| testAddLinkWithNamedDestinationReferenceUsesPdfReference | PdfDocTest | PASS | <1ms |
| testAddLinkAcceptsCorePageDirectly | PdfDocTest | PASS | <1ms |
| testAddLinkWithZeroSizeRectStillProducesAnnotation | PdfDocTest | PASS | 5ms |
| testPdfDocWriterAndPdfWriterReferenceMatch | PdfDocTest | PASS | <1ms |
| testPdfLevel3DocAccessorIsStable | PdfDocTest | PASS | <1ms |
| testDeprecatedPdfWriterForwardersStillWork | PdfDocTest | PASS | <1ms |
| testGeneratesHighLevelDocument | PdfIntegrationTest | PASS | 377ms |
| testAddHtmlRendersIntoPdf | PdfTest | PASS | 26ms |
| testEmptyDocumentHasNoPages | PdfTest | PASS | 191ms |
| testAddTextCreatesFirstPageAutomatically | PdfTest | PASS | 184ms |
| testAddHeadingEmitsLargerText | PdfTest | PASS | 191ms |
| testExplicitNewPageCreatesSecondPage | PdfTest | PASS | 185ms |
| testLongTextAutoPaginates | PdfTest | PASS | 182ms |
| testAddSpacerConsumesVerticalSpace | PdfTest | PASS | 184ms |
| testAddRuleDrawsStroke | PdfTest | PASS | 181ms |
| testAlignmentCenterEmitsCenteredText | PdfTest | PASS | 188ms |
| testSetFontSwitchesFontFamily | PdfTest | PASS | 178ms |
| testBoldAndItalicResolveToCorrectPostScriptName | PdfTest | PASS | 186ms |
| testUnknownFamilyIsRejected | PdfTest | PASS | <1ms |
| testCustomTheme | PdfTest | PASS | 176ms |
| testSaveWritesFile | PdfTest | PASS | 174ms |
| testWriteToStream | PdfTest | PASS | 183ms |
| testWriteToRejectsNonResource | PdfTest | PASS | <1ms |
| testToBytesProducesValidPdfOnEachCall | PdfTest | PASS | 391ms |
| testAddImageNaturalDimensions | PdfTest | PASS | 1ms |
| testAddImageScaledByWidthPreservesAspect | PdfTest | PASS | <1ms |
| testAddImageScaledByHeightPreservesAspect | PdfTest | PASS | <1ms |
| testAddImageExplicitDimensions | PdfTest | PASS | <1ms |
| testAddImageAlignmentCenter | PdfTest | PASS | <1ms |
| testAddImageAlignmentRight | PdfTest | PASS | <1ms |
| testAddImagePaginatesWhenTooTall | PdfTest | PASS | <1ms |
| testEscapeHatchReturnsWriter | PdfTest | PASS | <1ms |
| testCreateTemplateRegistersFormXObject | Phase4Batch2Test | PASS | <1ms |
| testDrawTemplateScalesByGivenWidth | Phase4Batch2Test | PASS | <1ms |
| testDrawTemplateReusesResourceForRepeatedCalls | Phase4Batch2Test | PASS | <1ms |
| testRegisterSpotColorReturnsSpotHandle | Phase4Batch2Test | PASS | <1ms |
| testSpotColorRegistersTintFunctionAndSeparationCanBeUsed | Phase4Batch2Test | PASS | <1ms |
| testSpotColorReusesResourceAcrossUseCalls | Phase4Batch2Test | PASS | <1ms |
| testLinearGradientRegistersShadingPattern | Phase4Batch2Test | PASS | <1ms |
| testRadialGradientRegistersShadingType3 | Phase4Batch2Test | PASS | <1ms |
| testGradientUsesRgbColorSpace | Phase4Batch2Test | PASS | <1ms |
| testAddSoundAnnotationRegistersAndLinks | Phase4Batch2Test | PASS | <1ms |
| testAddMovieAnnotationRegistersAndLinks | Phase4Batch2Test | PASS | <1ms |
| testAdd3DAnnotationAttachesStream | Phase4Batch2Test | PASS | <1ms |
| testAddTextFieldCreatesAcroFormAndWidget | Phase4Batch2Test | PASS | <1ms |
| testTextFieldOptionsSetFlagsAndMaxLen | Phase4Batch2Test | PASS | <1ms |
| testAddCheckboxDefaultsToOff | Phase4Batch2Test | PASS | <1ms |
| testAddCheckboxDefaultCheckedUsesOnValue | Phase4Batch2Test | PASS | <1ms |
| testAddChoiceFieldComboWithLabels | Phase4Batch2Test | PASS | <1ms |
| testAddSignatureFieldSetsSigFlagsOnForm | Phase4Batch2Test | PASS | <1ms |
| testMultipleFieldsShareTheSameAcroForm | Phase4Batch2Test | PASS | <1ms |
| testFieldOptionsRequiredAndReadOnlyFlags | Phase4Batch2Test | PASS | <1ms |
| testSetRotationStoresAngleOnPage | Phase4WrappingTest | PASS | <1ms |
| testSetRotationNormalisesNegativeAngle | Phase4WrappingTest | PASS | <1ms |
| testSetRotationRejectsNonMultipleOf90 | Phase4WrappingTest | PASS | <1ms |
| testSetPageBoxesPopulatesDictEntries | Phase4WrappingTest | PASS | <1ms |
| testSetViewerPreferencesClosureMutatesFreshInstance | Phase4WrappingTest | PASS | <1ms |
| testSetViewerPreferencesAcceptsPreBuiltInstance | Phase4WrappingTest | PASS | <1ms |
| testPdfForwarderForViewerPreferences | Phase4WrappingTest | PASS | <1ms |
| testRotateEmitsConcatMatrix | Phase4WrappingTest | PASS | <1ms |
| testScaleAndTranslateEmitMatrices | Phase4WrappingTest | PASS | <1ms |
| testWithTransformWrapsClosureInQ_Q | Phase4WrappingTest | PASS | <1ms |
| testSetOpacityRegistersExtGStateAndEmitsGs | Phase4WrappingTest | PASS | <1ms |
| testSetOpacityClampsOutOfRangeValues | Phase4WrappingTest | PASS | <1ms |
| testSetOpacityReusesExtGStateAcrossCalls | Phase4WrappingTest | PASS | <1ms |
| testAttachFileFromDiskCreatesFileSpecAndEmbeddedFile | Phase4WrappingTest | PASS | <1ms |
| testAttachFileBytesInMemoryUsesProvidedName | Phase4WrappingTest | PASS | <1ms |
| testMultipleAttachmentsAccumulateInAfArray | Phase4WrappingTest | PASS | <1ms |
| testPdfForwarderForAttachFile | Phase4WrappingTest | PASS | <1ms |
| testAddLayerCreatesOCGAndOCProperties | Phase4WrappingTest | PASS | <1ms |
| testAddLayerVisibilityRoutesToOnAndOffLists | Phase4WrappingTest | PASS | <1ms |
| testInLayerWrapsContentStreamWithMarkedContent | Phase4WrappingTest | PASS | <1ms |
| testActionUriBuildsUriActionWithGivenUrl | Phase4WrappingTest | PASS | <1ms |
| testActionJavascriptWrapsCodeInJsAction | Phase4WrappingTest | PASS | <1ms |
| testActionNamedUsesProvidedName | Phase4WrappingTest | PASS | <1ms |
| testActionResetFormWithoutFieldsResetsAll | Phase4WrappingTest | PASS | <1ms |
| testActionResetFormWithFieldsListsThem | Phase4WrappingTest | PASS | <1ms |
| testSetOpenActionRegistersAndWiresToCatalog | Phase4WrappingTest | PASS | <1ms |
| testAddStickyNoteEmitsTextAnnotation | Phase4WrappingTest | PASS | <1ms |
| testAddHighlightUsesQuadPointsAndDerivedRect | Phase4WrappingTest | PASS | <1ms |
| testAddInkAcceptsMultipleStrokes | Phase4WrappingTest | PASS | <1ms |
| testAddLineAnnotationSetsLEndpointsAndRect | Phase4WrappingTest | PASS | <1ms |
| testAddPolygonRecordsVerticesAndComputesBoundingRect | Phase4WrappingTest | PASS | <1ms |
| testAddSquareCircleStampWatermark | Phase4WrappingTest | PASS | <1ms |
| testAddHighlightRequiresAtLeastOneQuad | Phase4WrappingTest | PASS | <1ms |
| testAddPolygonRequiresAtLeastOnePoint | Phase4WrappingTest | PASS | <1ms |
| testAnnotationAttachesToPageAnnots | Phase4WrappingTest | PASS | <1ms |
| testAddTableRendersAllCellValues | TableTest | PASS | 176ms |
| testAddTableWithHeaderEmitsBoldVariantAndBackground | TableTest | PASS | <1ms |
| testAddTableUsesExplicitColumnWidthsWhenProvided | TableTest | PASS | <1ms |
| testAddTableAutoPaginatesAndRepeatsHeader | TableTest | PASS | 3ms |
| testAddTableEmptyRowsIsNoOp | TableTest | PASS | <1ms |
| testAddTableWrapsLongCellContentAcrossMultipleLines | TableTest | PASS | <1ms |
| testAddTableHonoursPerColumnAlignment | TableTest | PASS | <1ms |
| testAddTableWithBorderWidthZeroSkipsBorderOps | TableTest | PASS | <1ms |
| testWriterPageDrawTableRendersExplicitlyPositionedTable | TableTest | PASS | <1ms |
| testWriterPageDrawTableThrowsWithoutColumnWidths | TableTest | PASS | <1ms |
| testTableColumnCountDerivesFromHeaderWhenPresent | TableTest | PASS | <1ms |
| testTableColumnCountDerivesFromWidestRowWhenNoHeader | TableTest | PASS | <1ms |
| testTableColumnCountReturnsZeroForEmptyTable | TableTest | PASS | <1ms |
| testTableStyleAlignmentForReturnsLeftForMissingIndex | TableTest | PASS | <1ms |
| testAddTableWithNullHeaderBackgroundOmitsFill | TableTest | PASS | <1ms |
| testUnderlineEmitsStrokeBelowBaseline | TextDecorationTest | PASS | 190ms |
| testStrikethroughEmitsStroke | TextDecorationTest | PASS | <1ms |
| testUnderlineAndStrikethroughBothApplied | TextDecorationTest | PASS | <1ms |
| testNoDecorationByDefaultEmitsNoStroke | TextDecorationTest | PASS | <1ms |
| testUnderlineSpansEveryWrappedLine | TextDecorationTest | PASS | <1ms |
| testDecorationUsesTextFillColor | TextDecorationTest | PASS | <1ms |
| testWriterPageDrawTextUnderline | TextDecorationTest | PASS | <1ms |
| testWriterPageDrawTextStrikethroughWithColor | TextDecorationTest | PASS | <1ms |
| testWriterPageDrawTextWithEmptyStringIsNoOp | TextDecorationTest | PASS | <1ms |
| testType0FontFactoryCreatesValidStack | UnicodeFontTest | PASS | 5ms |
| testAddCompositeFontGeneratesValidPdf | UnicodeFontTest | PASS | 190ms |
| testAddCompositeFontSavesToFile | UnicodeFontTest | PASS | 195ms |
| testCompositeFontRoundTripsThroughReader | UnicodeFontTest | PASS | 188ms |
| testCompositeFontAppearsInFontList | UnicodeFontTest | PASS | 5ms |
| testShowTextHexOperator | UnicodeFontTest | PASS | <1ms |
| testCompositeFontPerPage | UnicodeFontTest | PASS | 192ms |
| testPdfWriterGeneratesValidPdfHeader | WriterTest | PASS | 186ms |
| testPdfWriterGeneratesWithEndMarker | WriterTest | PASS | <1ms |
| testPdfWriterContainsCatalog | WriterTest | PASS | <1ms |
| testPdfWriterContainsPageTree | WriterTest | PASS | <1ms |
| testPdfWriterContainsPage | WriterTest | PASS | 180ms |
| testPdfWriterAddFont | WriterTest | PASS | <1ms |
| testPdfWriterMultipleFontsIncrement | WriterTest | PASS | <1ms |
| testPdfWriterGetFonts | WriterTest | PASS | <1ms |
| testPdfWriterAddContentStream | WriterTest | PASS | 175ms |
| testPdfWriterGetContentStreams | WriterTest | PASS | <1ms |
| testPdfWriterWithInfo | WriterTest | PASS | 180ms |
| testPdfWriterGetCatalog | WriterTest | PASS | <1ms |
| testPdfWriterGetPageTree | WriterTest | PASS | <1ms |
| testPdfWriterSavesToFile | WriterTest | PASS | 177ms |
| testPdfWriterContainsXref | WriterTest | PASS | 175ms |
| testPdfWriterContainsTrailer | WriterTest | PASS | <1ms |
| testPdfWriterAddPageWithRectangle | WriterTest | PASS | 182ms |
| testPdfWriterRegisterObject | WriterTest | PASS | 177ms |
| testPdfWriterFontAddedToPage | WriterTest | PASS | 188ms |
| testSetNamedDestinations | WriterTest | PASS | 176ms |
| testSetEncryptionProducesEncryptedPdf | WriterTest | PASS | 184ms |
| testSetEncryptionAes256RoundTrip | WriterTest | PASS | 204ms |
| testSetMetadataAddsStreamToPdf | XmpMetadataTest | PASS | 185ms |
| testMetadataStreamContainsXmp | XmpMetadataTest | PASS | 185ms |
| testMetadataRoundTrip | XmpMetadataTest | PASS | 186ms |
| testSyncInfoToMetadata | XmpMetadataTest | PASS | 184ms |
| testSyncInfoToMetadataNoInfoIsNoOp | XmpMetadataTest | PASS | 175ms |
| testFlattenAll | AnnotationFlattenerTest | PASS | 190ms |
| testNoOpsReturnsOriginal | AnnotationFlattenerTest | PASS | <1ms |
| testPageCount | AnnotationFlattenerTest | PASS | <1ms |
| testEscapeHatch | AnnotationFlattenerTest | PASS | <1ms |
| testSaveToFile | AnnotationFlattenerTest | PASS | 193ms |
| testFlattenTypeFiltersBySubtype | AnnotationFlattenerTest | PASS | 1ms |
| testFlattenTypeIgnoresUnmatchedSubtypes | AnnotationFlattenerTest | PASS | <1ms |
| testFlattenAllWithPageSelector | AnnotationFlattenerTest | PASS | 1ms |
| testFlattenFormsHelper | AnnotationFlattenerTest | PASS | <1ms |
| testOpenFromDisk | AnnotationFlattenerTest | PASS | <1ms |
| testGetVersionWarningsIsEmptyInitially | AnnotationFlattenerTest | PASS | <1ms |
| testSetBookmarks | BookmarkEditorTest | PASS | 198ms |
| testSetBookmarksWithChildren | BookmarkEditorTest | PASS | 190ms |
| testAddBookmark | BookmarkEditorTest | PASS | 363ms |
| testHasBookmarksReturnsFalseForCleanPdf | BookmarkEditorTest | PASS | <1ms |
| testRemoveBookmarks | BookmarkEditorTest | PASS | 179ms |
| testGetPageCount | BookmarkEditorTest | PASS | <1ms |
| testGetReader | BookmarkEditorTest | PASS | <1ms |
| testSaveToFile | BookmarkEditorTest | PASS | 186ms |
| testNoBytesChangedWhenNoOperations | BookmarkEditorTest | PASS | <1ms |
| testReplaceExistingBookmarks | BookmarkEditorTest | PASS | 183ms |
| testOpenString | FormFillerTest | PASS | 2ms |
| testGetFieldNames | FormFillerTest | PASS | 1ms |
| testHasField | FormFillerTest | PASS | 1ms |
| testGetFieldInfo | FormFillerTest | PASS | 1ms |
| testGetFieldInfoReturnsNullForMissing | FormFillerTest | PASS | <1ms |
| testGetFieldValues | FormFillerTest | PASS | <1ms |
| testFillTextField | FormFillerTest | PASS | 178ms |
| testFillManyFields | FormFillerTest | PASS | 180ms |
| testCheckCheckbox | FormFillerTest | PASS | 181ms |
| testUncheckCheckbox | FormFillerTest | PASS | 194ms |
| testSelectChoiceField | FormFillerTest | PASS | 191ms |
| testFillMultipleFieldTypes | FormFillerTest | PASS | 183ms |
| testFillThrowsForUnknownField | FormFillerTest | PASS | 1ms |
| testCheckThrowsForUnknownField | FormFillerTest | PASS | <1ms |
| testSelectThrowsForUnknownField | FormFillerTest | PASS | <1ms |
| testToBytesWithNoChangesReturnsOriginal | FormFillerTest | PASS | <1ms |
| testSaveWritesFile | FormFillerTest | PASS | 180ms |
| testEscapeHatch | FormFillerTest | PASS | 1ms |
| testRoundTripPreservesOtherFields | FormFillerTest | PASS | 180ms |
| testReadExistingMetadata | MetadataEditorTest | PASS | 1ms |
| testReadNoMetadata | MetadataEditorTest | PASS | <1ms |
| testGetAll | MetadataEditorTest | PASS | 2ms |
| testSetTitleRoundTrip | MetadataEditorTest | PASS | 180ms |
| testSetMultipleFieldsRoundTrip | MetadataEditorTest | PASS | 176ms |
| testSetMetadataOnPdfWithoutInfo | MetadataEditorTest | PASS | 176ms |
| testSetCreatorProducerDatesAndTrappedRoundTrip | MetadataEditorTest | PASS | 1ms |
| testGetVersionWarningsIsArray | MetadataEditorTest | PASS | <1ms |
| testNoBytesChangedWithoutModifications | MetadataEditorTest | PASS | <1ms |
| testPageCount | MetadataEditorTest | PASS | <1ms |
| testEscapeHatch | MetadataEditorTest | PASS | <1ms |
| testCustomField | MetadataEditorTest | PASS | 180ms |
| testSaveToFile | MetadataEditorTest | PASS | 183ms |
| testSetLabelsWithArabic | PageLabelerTest | PASS | 183ms |
| testSetRomanNumerals | PageLabelerTest | PASS | 191ms |
| testSetRomanNumeralsUppercase | PageLabelerTest | PASS | 185ms |
| testSetAlphabetic | PageLabelerTest | PASS | 194ms |
| testSetArabicWithStartNumber | PageLabelerTest | PASS | 184ms |
| testSetLabelsWithPrefix | PageLabelerTest | PASS | 190ms |
| testRemoveLabels | PageLabelerTest | PASS | 191ms |
| testMultipleRanges | PageLabelerTest | PASS | 188ms |
| testGetPageCount | PageLabelerTest | PASS | <1ms |
| testGetReader | PageLabelerTest | PASS | <1ms |
| testSaveToFile | PageLabelerTest | PASS | 186ms |
| testNoBytesChangedWhenNoOperations | PageLabelerTest | PASS | 1ms |
| testOpenFromFile | PageLabelerTest | PASS | <1ms |
| testKeepPages | PageSlicerTest | PASS | 188ms |
| testKeepRange | PageSlicerTest | PASS | 184ms |
| testRemovePages | PageSlicerTest | PASS | 182ms |
| testReorder | PageSlicerTest | PASS | 179ms |
| testReverse | PageSlicerTest | PASS | 182ms |
| testSplit | PageSlicerTest | PASS | 361ms |
| testKeepWithPageSelector | PageSlicerTest | PASS | 185ms |
| testNoOpsKeepsAllPages | PageSlicerTest | PASS | 190ms |
| testPageCount | PageSlicerTest | PASS | <1ms |
| testEscapeHatch | PageSlicerTest | PASS | <1ms |
| testRotateAllPages | PageTransformerTest | PASS | 190ms |
| testRotate180 | PageTransformerTest | PASS | 174ms |
| testRotate270 | PageTransformerTest | PASS | 181ms |
| testRotateSpecificPages | PageTransformerTest | PASS | 180ms |
| testRotateCumulative | PageTransformerTest | PASS | 177ms |
| testRotateInvalidAngle | PageTransformerTest | PASS | <1ms |
| testSetCropBox | PageTransformerTest | PASS | 185ms |
| testSetCropBoxSpecificPage | PageTransformerTest | PASS | 185ms |
| testSetMediaBox | PageTransformerTest | PASS | 179ms |
| testSetTrimBox | PageTransformerTest | PASS | 178ms |
| testSetBleedBox | PageTransformerTest | PASS | 182ms |
| testScale | PageTransformerTest | PASS | 191ms |
| testScaleSpecificPages | PageTransformerTest | PASS | 186ms |
| testScaleInvalidFactor | PageTransformerTest | PASS | <1ms |
| testScaleTo | PageTransformerTest | PASS | 180ms |
| testScaleToNonUniform | PageTransformerTest | PASS | 178ms |
| testMultipleOperations | PageTransformerTest | PASS | 177ms |
| testNoBytesChangedWithoutOperations | PageTransformerTest | PASS | <1ms |
| testEvenPages | PageTransformerTest | PASS | 183ms |
| testOddPages | PageTransformerTest | PASS | 178ms |
| testRange | PageTransformerTest | PASS | 182ms |
| testGetReader | PageTransformerTest | PASS | <1ms |
| testGetPageCount | PageTransformerTest | PASS | <1ms |
| testSaveToFile | PageTransformerTest | PASS | 180ms |
| testEncryptAes128 | PdfEncryptTest | PASS | 180ms |
| testEncryptAes256 | PdfEncryptTest | PASS | 191ms |
| testDecrypt | PdfEncryptTest | PASS | 186ms |
| testIsEncrypted | PdfEncryptTest | PASS | <1ms |
| testNoOpsReturnsOriginal | PdfEncryptTest | PASS | <1ms |
| testPageCount | PdfEncryptTest | PASS | <1ms |
| testEscapeHatch | PdfEncryptTest | PASS | <1ms |
| testEncryptRc440 | PdfEncryptTest | PASS | 1ms |
| testEncryptRc4128 | PdfEncryptTest | PASS | 3ms |
| testChangePasswordsDefaultsToAes256 | PdfEncryptTest | PASS | 7ms |
| testSetPermissionsBeforeEncrypt | PdfEncryptTest | PASS | 3ms |
| testIsEncryptedTrueAfterEncryption | PdfEncryptTest | PASS | 3ms |
| testGetVersionWarningsEmptyBeforeToBytes | PdfEncryptTest | PASS | <1ms |
| testSaveWritesEncryptedFile | PdfEncryptTest | PASS | 2ms |
| testOpenFromDiskRoundTrip | PdfEncryptTest | PASS | <1ms |
| testEncryptThenChangePasswordsRetainsMethod | PdfEncryptTest | PASS | 3ms |
| testMergeTwoPdfs | PdfMergerTest | PASS | 187ms |
| testMergeWithPageSelection | PdfMergerTest | PASS | 176ms |
| testSourceCount | PdfMergerTest | PASS | 3ms |
| testTotalPageCount | PdfMergerTest | PASS | <1ms |
| testNoSourcesThrows | PdfMergerTest | PASS | <1ms |
| testSaveToFile | PdfMergerTest | PASS | 188ms |
| testStampText | PdfStamperTest | PASS | 182ms |
| testWatermark | PdfStamperTest | PASS | 185ms |
| testWatermarkWithStyle | PdfStamperTest | PASS | 189ms |
| testPageNumbers | PdfStamperTest | PASS | 182ms |
| testStampOnSpecificPages | PdfStamperTest | PASS | 186ms |
| testHeaderAndFooter | PdfStamperTest | PASS | 182ms |
| testStampWithOpacity | PdfStamperTest | PASS | 191ms |
| testNoOpsReturnsOriginal | PdfStamperTest | PASS | <1ms |
| testPageCount | PdfStamperTest | PASS | <1ms |
| testEscapeHatch | PdfStamperTest | PASS | <1ms |
| testSaveToFile | PdfStamperTest | PASS | 183ms |
| testStampImageJpeg | PdfStamperTest | PASS | 198ms |
| testStampImagePng | PdfStamperTest | PASS | 181ms |
| testStampImageWithScaleWidth | PdfStamperTest | PASS | 187ms |
| testStampImageWithScaleHeight | PdfStamperTest | PASS | 178ms |
| testStampImageWithExplicitDimensions | PdfStamperTest | PASS | 188ms |
| testStampImageWithOpacity | PdfStamperTest | PASS | 185ms |
| testStampImageOnSpecificPages | PdfStamperTest | PASS | 183ms |
| testStampImageCombinedWithText | PdfStamperTest | PASS | 182ms |
| testStampImageThrowsOnMissingFile | PdfStamperTest | PASS | <1ms |
| testStampImageThrowsOnUnsupportedFormat | PdfStamperTest | PASS | <1ms |
| testStampPdf | PdfStamperTest | PASS | 181ms |
| testStampPdfWithScaling | PdfStamperTest | PASS | 173ms |
| testStampPdfWithOpacity | PdfStamperTest | PASS | 185ms |
| testStampPdfSpecificPage | PdfStamperTest | PASS | 186ms |
| testStampPdfOnSelectedPages | PdfStamperTest | PASS | 188ms |
| testStampPdfDefaultsToCenter | PdfStamperTest | PASS | 180ms |
| testStampPdfThrowsOnMissingFile | PdfStamperTest | PASS | <1ms |
| testStampPdfThrowsOnInvalidPageIndex | PdfStamperTest | PASS | 1ms |
| testStampPdfThrowsOnNegativePageIndex | PdfStamperTest | PASS | <1ms |
| testImageStampStyleDefaults | PdfStamperTest | PASS | <1ms |
| testRedactArea | TextRedactorTest | PASS | 183ms |
| testRedactMultipleAreas | TextRedactorTest | PASS | 184ms |
| testRedactByText | TextRedactorTest | PASS | 190ms |
| testRedactByPattern | TextRedactorTest | PASS | <1ms |
| testCustomRedactionColor | TextRedactorTest | PASS | 183ms |
| testApplyRequiredBeforeToBytes | TextRedactorTest | PASS | <1ms |
| testNoRedactionsReturnsOriginal | TextRedactorTest | PASS | <1ms |
| testPageCount | TextRedactorTest | PASS | <1ms |
| testEscapeHatch | TextRedactorTest | PASS | <1ms |
| testSaveToFile | TextRedactorTest | PASS | 196ms |

### Arlington PDF Model — Dictionary-level spec conformance (keys, types, required fields, version constraints)

**6 tests** | 6 passed | 0 failed | 0 skipped | 1.23s

| Test | Class | Status | Time |
|---|---|---|---|
| testGeneratesBookmarksPdf | BookmarksTest | PASS | 296ms |
| testGeneratesDocumentFeaturesPdf | DocumentFeaturesTest | PASS | 185ms |
| testGeneratesPdfWithEmbeddedTrueTypeFont | DocumentFeaturesTest | PASS | 192ms |
| testGeneratesFormPdf | FormFieldsTest | PASS | 186ms |
| testGeneratesMultiPageComplexPdf | MultiPageComplexTest | PASS | 190ms |
| testGeneratesSimpleTextPdf | SimpleTextTest | PASS | 183ms |

### veraPDF — PDF/A archival conformance (ISO 19005)

**4 tests** | 3 passed | 0 failed | 1 skipped | 18.66s

| Test | Class | Status | Time |
|---|---|---|---|
| testMinimalPdfWithOutputIntent | PdfAConformanceTest | SKIP | 4ms |
| testVeraPdfToolchainWorks | PdfAConformanceTest | PASS | 2.22s |
| testPdfA1bCorpus | Tier2PdfACorpusTest | PASS | 6.95s |
| testPdfA2bCorpus | Tier2PdfACorpusTest | PASS | 9.48s |


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

**1167 tests** | 1167 passed | 0 failed | 0 skipped | 18.19s

| Test | Class | Status | Time |
|---|---|---|---|
| testPopplerCorpus with data set "poppler-test/tests/blend.pdf" | Tier2CorpusTest | PASS | 4ms |
| testPopplerCorpus with data set "poppler-test/tests/cropbox.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/tests/degenerate-path.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/tests/encoding.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/tests/fonts.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/tests/image.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/tests/inline-image.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/tests/jpeg.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/tests/mask-seams.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/tests/mask.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/tests/text.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/tests/type3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/tests/zero-width.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/A6EmbeddedFiles.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/ClarityOCGs.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/FullScreen.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/Gday garçon - open.pdf" | Tier2CorpusTest | PASS | 3ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/Gday garçon - owner.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/Issue637.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/NestedLayers.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/PasswordEncrypted.pdf" | Tier2CorpusTest | PASS | 2ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/PasswordEncryptedReconstructed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/UseAttachments.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/UseNone.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/UseOC.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/UseThumbs.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/WithActualText.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/WithAttachments.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/bug7063.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/checkbox_issue_159.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/deseret.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/doublepage.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/encrypted-256.pdf" | Tier2CorpusTest | PASS | 3ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/fakebold.pdf" | Tier2CorpusTest | PASS | 3ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/fieldWithUtf16Names.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/form_set_icon.pdf" | Tier2CorpusTest | PASS | 5ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/imageretrieve+attachment.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/latex-hyperref-checkbox-issue-655.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/orientation.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/pdf-signature-sample-2sigs-randompadded.pdf" | Tier2CorpusTest | PASS | 2ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/pdf-signature-sample-2sigs.pdf" | Tier2CorpusTest | PASS | 2ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/pdf20-utf8-test.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/russian.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/searchAcrossLines.pdf" | Tier2CorpusTest | PASS | 6ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/searchAcrossLinesDoubleColumn.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/shapes+attachments.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/digest_mismatch/detached_hash_mismatch.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/digest_mismatch/etsi_hash_mismatch.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/digest_mismatch/sha1_function_mismatch.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/digest_mismatch/sha1_hash_mismatch.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/detached_rsa_mismatch.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/etsi_esscertid_04.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/etsi_esscertid_05.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/etsi_esscertid_06.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/etsi_esscertid_07.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/etsi_esscertid_08.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/etsi_esscertid_09.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/etsi_esscertid_10.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/etsi_esscertid_11.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/etsi_rsa_mismatch.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/mismatch_detached_vs_sha1.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/mismatch_etsi_vs_sha1.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/mismatch_sha1_vs_detached.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/invalid/sha1_rsa_mismatch.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/valid/detached.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/valid/etsi.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/valid/etsi_esscertid_01.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/valid/etsi_esscertid_02.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/valid/etsi_esscertid_03.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/valid/sha1.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/signature/valid/sha1_mixed_functions.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/some-text-pgp_signed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/stroke-alpha-pattern.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/tooltip.pdf" | Tier2CorpusTest | PASS | 3ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/truetype.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/type3.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/utf16le-annot.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/vis_policy_test.pdf" | Tier2CorpusTest | PASS | 1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/xr01.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPopplerCorpus with data set "poppler-test/unittestcases/xr02.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/11-pages-with-labels.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/11-pages.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/20-pages.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/V4-aes-clearmeta.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/V4-aes.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/V4-clearmeta.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/V4.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/add-attachments-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/add-attachments-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/add-contents.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/annotation-no-resources-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/annotation-no-resources.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/annotations-foreign-file.out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/annotations-no-acroform-with-p.out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/annotations-no-acroform-with-p.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/annotations-no-acroform.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/annotations-rotated-180.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/annotations-rotated-270.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/annotations-rotated-90.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/annotations-same-file.out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/appearances-1-rotated.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/appearances-1.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/appearances-11.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/appearances-12.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/appearances-2.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/appearances-a-more.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/appearances-a-more2.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/appearances-a-more3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/appearances-a.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/appearances-b.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/appearances-quack.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/append-page-content-damaged.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/append-page-content.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/append-xref-loop-fixed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/append-xref-loop.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/attachment-fields.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad-data-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad-data.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad-direct-root.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad-encryption-length.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/bad-jpeg-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad-jpeg.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad-token-startxref.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad-xref-entry.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad-xref.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad10.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad11.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad12.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad13.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad14.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad15.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad16.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad17.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad18.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad19.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad20.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad21.pdf" | Tier2CorpusTest | PASS | 8ms |
| testQpdfCorpus with data set "qpdf/bad22.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad23.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad24.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad25.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad26.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad27.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad28.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad29.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad30.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad31.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad32.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad33.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad34.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad35.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad36.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad37.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad38.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad39.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad4.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad40.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad5.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad6.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad7.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad8.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/bad9.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/badlin1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/big-ostream.pdf" | Tier2CorpusTest | PASS | 6ms |
| testQpdfCorpus with data set "qpdf/boxes-flattened.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/boxes.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/boxes2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/broken-decode-parms-no-filter.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/broken-lzw.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/button-set-broken-out.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/button-set-broken.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/button-set-out.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/button-set.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/c-check-clear-in.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-check-warn-in.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-decrypt-R5-with-user.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-decrypt-R6-with-owner.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-decrypt-with-owner.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-decrypt-with-user.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-empty.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-foreign.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-ignore-xref-streams.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-indirect-objects-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-info-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-info2-in.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-linearized.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-new-stream.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-no-options.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-no-original-object-ids.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-normalized-content.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-object-handle-creation-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-object-handles-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-object-streams.pdf" | Tier2CorpusTest | PASS | 4ms |
| testQpdfCorpus with data set "qpdf/c-pages.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-qdf.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-r2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/c-r3.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/c-r4.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/c-r5-in.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/c-r6-in.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/c-uncompressed-streams.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/catalgg.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/coalesce-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/coalesce.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/collate-even.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/collate-odd.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/comment-annotation-direct-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/comment-annotation-direct.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/comment-annotation-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/comment-annotation.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/compress-objstm-xref-qdf.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/compress-objstm-xref.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/compressed-metadata-out-normal.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/compressed-metadata.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/content-stream-errors.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/copied-positive-P.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/copy-attachments-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/copy-foreign-objects-in.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/copy-foreign-objects-out1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/copy-foreign-objects-out2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/copy-foreign-objects-out3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/custom-pipeline.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/damaged-inline-image-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/damaged-inline-image.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/damaged-stream.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/dangling-bad-xref-dangling-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/dangling-bad-xref.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/dangling-refs-dangling-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/dangling-refs.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/decrypted-crypt-filter.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/decrypted-positive-P.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/deep-duplicate-pages.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/default-da-q-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/default-da-q.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/delete-and-reuse.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/deterministic-id-in.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/digitally-signed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/direct-dr-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/direct-dr.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/direct-outlines.pdf" | Tier2CorpusTest | PASS | 7ms |
| testQpdfCorpus with data set "qpdf/direct-pages-fixed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/direct-pages.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/dr-with-indirect-item-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/dr-with-indirect-item.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/duplicate-page-inherited-1-fixed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/duplicate-page-inherited-2-fixed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/duplicate-page-inherited.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/duplicate-pages.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/empty-decode-parms-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/empty-decode-parms.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/empty-object.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/empty-stream-compressed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/empty-stream-uncompressed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/empty.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/enc-R2,V1,O=master.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/enc-R2,V1,U=view,O=master.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/enc-R2,V1,U=view,O=view.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/enc-R2,V1.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/enc-R3,V2,O=master.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/enc-R3,V2,U=view,O=master.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/enc-R3,V2,U=view,O=view.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/enc-R3,V2.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/enc-XI-R6,V5,O=master.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/enc-XI-R6,V5,U=attachment,encrypted-attachments.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/enc-XI-R6,V5,U=view,O=master.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/enc-XI-R6,V5,U=view,attachments,cleartext-metadata.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/enc-XI-R6,V5,U=wwwww,O=wwwww.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/enc-XI-attachments-base.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/enc-XI-base.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/enc-XI-long-password.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/enc-base.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/enc-long-password.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/encrypted-40-bit-R3.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/encrypted-positive-P.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/encrypted-with-images.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/endobj-at-eol-fixed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/endobj-at-eol.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/eof-in-inline-image.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/eof-reading-token.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/eof-terminates-literal.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/erase-nntree-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/erase-nntree.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extensions-adbe-force-1.8.5.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extensions-adbe-other-force-1.8.5.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extensions-adbe-other.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extensions-adbe.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extensions-none-force-1.8.5.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extensions-other-force-1.8.5.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extensions-other.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extra-header-lin-newline.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extra-header-lin-no-newline.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extra-header-newline.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extra-header-no-newline.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/extract-duplicate-page.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fax-decode-parms.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/field-parse-errors-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/field-parse-errors.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/field-resource-conflict.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/field-types.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/fields-pages-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fields-split-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fields-split-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fields-two-pages.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/filter-abbreviation.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/filter-on-write-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/filter-on-write.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-bad-fields-array.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-document-defaults.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-empty-from-odt.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-errors.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-fields-and-annotations-shared.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-fields-and-annotations.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-filled-by-acrobat-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-filled-by-acrobat.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-filled-with-atril.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-mod1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-no-need-appearances-filled.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-no-need-appearances.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-xobjects-no-resources-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-xobjects-no-resources.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-xobjects-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-xobjects-some-resources1-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-xobjects-some-resources1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-xobjects-some-resources2-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/form-xobjects-some-resources2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/from-scratch-0.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fuzz-16214.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fx-overlay-56.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fx-overlay-57.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fx-overlay-58.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fx-overlay-59.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fx-overlay-64.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fx-overlay-65.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fx-overlay-66.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fx-overlay-67.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fxo-bigsmall.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fxo-blue.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fxo-green.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fxo-red.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/fxo-smallbig.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/gen1-no-dangling.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/gen1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/global.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/global_damaged.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good10.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good11.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good12.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good13.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good14.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good15.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good16.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good17-not-qdf.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good17-not-recompressed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good17.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good18.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good19.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good20.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good21.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good4.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good5.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good6.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good7.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good8.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/good9.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/hybrid-xref.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/image-streams-small.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/image-streams.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/incremental-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/incremental-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/incremental-3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/indirect-decode-parms-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/indirect-decode-parms.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/indirect-filter-out-0.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/indirect-filter-out-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/indirect-filter.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/indirect-r-arg.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/inherited-flattened.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/inherited-rotate.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/inline-image-colorspace-lookup-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/inline-image-colorspace-lookup.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/inline-images-ii-all.pdf" | Tier2CorpusTest | PASS | 18ms |
| testQpdfCorpus with data set "qpdf/inline-images-ii-some.pdf" | Tier2CorpusTest | PASS | 13ms |
| testQpdfCorpus with data set "qpdf/inline-images.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/inspect.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/invalid-id-xref.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/issue-100.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-101.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-106.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-117.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-119.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-120.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-141a.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-141b.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-143.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-146.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-147.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-148.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-149.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-150.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-1503.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-1688a.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-1688b.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-202.pdf" | Tier2CorpusTest | PASS | 62ms |
| testQpdfCorpus with data set "qpdf/issue-263.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-335a.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-335b.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-449.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-99.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/issue-99b.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/job-json-add-attachments.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/job-json-choice-match.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/job-json-copy-attachments.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/job-json-empty-input.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/job-json-encrypt-128.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/job-json-encrypt-40.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/job-json-input-file-password.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/job-json-misc-options.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/job-json-replace-input.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/job-json-underlay-overlay-password.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/job-json-underlay-overlay.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/jpeg-qstream.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/json-changed-form-fields-and-annotations.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/json-changed-good13.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/json-changed-need-appearances.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/kept-no-fields.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/kept-some-fields.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/labels-split-01-06.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/labels-split-07-11.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/large-inline-image-ii-all.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/large-inline-image-ii-some.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/large-inline-image.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/leading-junk.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/lin-delete-and-reuse.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/lin-special.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/lin0.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/lin1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/lin2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/lin3.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/lin4.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/lin5.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/lin6.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/lin7.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/lin8.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/lin9.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/linearization-bounds-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/linearization-bounds-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/linearization-large-vector-alloc.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/linearize-duplicate-page.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/linearized-and-warnings.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/link-annots.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/long-id-linearized.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/long-id.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/manual-appearances-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/manual-appearances-print-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/manual-appearances-screen-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/manual-appearances.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/manual-qpdf-json.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/many-nulls.pdf" | Tier2CorpusTest | PASS | 702ms |
| testQpdfCorpus with data set "qpdf/merge-dict.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/merge-dot-implicit-ranges.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/merge-implicit-ranges.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/merge-multiple-labels.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/merge-three-files-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/merge-three-files-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/metadata-crypt-filter.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/minimal-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/minimal-9.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/minimal-dangling-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/minimal-linearize-pass1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/minimal-linearized.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/minimal-nulls.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/minimal-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/minimal-rotated.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/minimal-signed-restricted.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/minimal-signed-restrictions-removed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/minimal.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/more-choices.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/name-pound-images.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/name-tree.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/need-appearances-more-out.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/need-appearances-more.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/need-appearances-more2.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/need-appearances-more3.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/need-appearances-out.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/need-appearances-utf8.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/need-appearances.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/need-appearances2.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/nested-form-xobjects-inline-images-ii-all.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/nested-form-xobjects-inline-images-ii-some.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/nested-form-xobjects-inline-images.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/nested-form-xobjects.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/nested-images.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/new-streams.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/newline-before-endstream-nl-objstm.pdf" | Tier2CorpusTest | PASS | 22ms |
| testQpdfCorpus with data set "qpdf/newline-before-endstream-nl-qdf.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/newline-before-endstream-nl.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/newline-before-endstream-qdf.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/no-contents-coalesce-contents.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/no-contents-none.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/no-contents-qdf.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/no-contents.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/no-pages-types-fixed.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/no-pages-types.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/no-space-compressed-object.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/no-space-in-xref.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/nontrivial-crypt-filter-decrypted.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/nontrivial-crypt-filter.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/number-tree.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/numeric-and-string-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/numeric-and-string-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/numeric-and-string-3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/obj0.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/object-stream-self-ref.out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/object-stream-self-ref.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/object-stream.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/object-types-os.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/object-types.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/other-file-first.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/outlines-split-01-10.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/outlines-split-11-20.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/outlines-split-21-30.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/outlines-with-actions.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/outlines-with-loop.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/outlines-with-old-root-dests-dict.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/outlines-with-old-root-dests.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/overlay-copy-annotations-p1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/overlay-copy-annotations-p2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/overlay-copy-annotations-p5.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/overlay-copy-annotations-p6.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/overlay-copy-annotations.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/overlay-no-resources.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/override-compressed-object.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/p1-a-p2-a.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/p1-a-p2-b.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/p1-a.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/p1-b.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page-inherit-mediabox-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page-inherit-mediabox.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page-labels-and-outlines.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page-labels-no-zero.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page-labels-num-tree-damaged.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page-labels-num-tree.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page-missing-mediabox-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page-no-content.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page-with-no-resources.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page_api_1-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page_api_1-out2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page_api_1-out3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page_api_1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/page_api_2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/pages-copy-encryption.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/pages-is-page-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/pages-is-page.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/pages-loop.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/pclm-in.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/pclm-out.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/png-filters-1-column.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/png-filters-decoded.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/png-filters-no-columns-decoded.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/png-filters-no-columns.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/png-filters.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/pound-in-name.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/qpdf-ctest-42-43.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/qpdf-ctest-44-45.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/qpdfjob-ctest-wide.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/qpdfjob-ctest1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/qpdfjob-ctest2.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/qpdfjob-ctest3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/qpdfjob-ctest4.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/qstream.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/really-shared-images-pages-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/recover-xref-stream-recovered.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/recover-xref-stream.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/remove-acroform-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/remove-info-no-moddate.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/remove-info.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/remove-labels.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/remove-metadata-no-moddate.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/remove-metadata.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/remove-multiple-attachments.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/remove-structure.out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/remove-structure.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/replace-input.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/replace-with-stream-updated.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/replaced-stream-data-flate.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/replaced-stream-data.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/reserved-objects.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/resolved-appearance-conflicts-generate.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/resolved-appearance-conflicts.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/resolved-field-conflicts.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/resource-from-dr-out.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/resource-from-dr.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/reuse-xref-stream.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/rotated-shared-annotations-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/rotated-shared-annotations-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/rotated.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/sample-form-out.pdf" | Tier2CorpusTest | PASS | 4ms |
| testQpdfCorpus with data set "qpdf/sample-form.pdf" | Tier2CorpusTest | PASS | 7ms |
| testQpdfCorpus with data set "qpdf/shallow_array-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shallow_array.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-font-xobject-split-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-font-xobject-split-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-font-xobject-split-3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-font-xobject-split-4.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-font-xobject.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-form-images-merged.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-form-images-xobject.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-form-images.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-form-split-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-form-split-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-form-split-3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-form-split-4.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-form-split-5.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-form-split-6.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-form-xobject-split-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-form-xobject-split-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-images-errors-1-3-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-images-errors-1-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-images-errors-2-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-images-errors.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-images-pages-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-images.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-split-01-04.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-split-05-08.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/shared-split-09-10.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/short-O-U.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/short-id-linearized.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/short-id.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/small-images.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-content-stream-errors.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-content-stream.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-01.Pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-02.Pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-03.Pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-04.Pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-05.Pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-06.Pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-07.Pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-08.Pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-09.Pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-10.Pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-11.Pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-group-01-05.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-group-06-10.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-exp-group-11-11.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-nntree-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-nntree.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-tokens-split-1-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/split-tokens.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/stream-data.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/stream-line-enders.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/streams-with-newlines.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/terminate-parsing.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test102.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test14-in.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test14-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test4-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test4-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test4-3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test4-4.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test4-5.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test76.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test77.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test78.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test79.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test80a1.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/test80a2.pdf" | Tier2CorpusTest | PASS | 1ms |
| testQpdfCorpus with data set "qpdf/test80b1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test80b2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/test84.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/three-files-2,3,4-collate-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/three-files-2-collate-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/three-files-collate-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/tiff-predictor.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/to-rotate.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/token-filters-out.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/tokenize-content-streams.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/tokens.pdf" | Tier2CorpusTest | PASS | 2ms |
| testQpdfCorpus with data set "qpdf/unfilterable-with-crypt.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/unfilterable.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/unique-resources.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/unreferenced-dropped.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/unreferenced-indirect-scalar.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/unreferenced-objects.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/unreferenced-preserved.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/unrotated.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/unsupported-optimization.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/uo-1.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/uo-2.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/uo-3.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/uo-4.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/uo-5.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/uo-6.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/uo-7.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/uo-8.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/update-stream-data-updated.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/update-stream-dict-only-updated.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/utf16le.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/various-updates-updated.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/warn-replace.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/weird-tokens.pdf" | Tier2CorpusTest | PASS | 3ms |
| testQpdfCorpus with data set "qpdf/xref-errors.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/xref-range.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/xref-with-short-size.pdf" | Tier2CorpusTest | PASS | <1ms |
| testQpdfCorpus with data set "qpdf/zero-offset.pdf" | Tier2CorpusTest | PASS | <1ms |
| testPdfA1bCorpus | Tier2PdfACorpusTest | PASS | 7.22s |
| testPdfA2bCorpus | Tier2PdfACorpusTest | PASS | 9.37s |
| testPdfBoxCorpus with data set "pdfbox/examples/src/main/resources/org/apache/pdfbox/examples/interactive/form/FillFormField.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/examples/src/main/resources/org/apache/pdfbox/examples/rendering/custom-render-demo.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/examples/src/test/resources/org/apache/pdfbox/examples/pdmodel/document.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/examples/src/test/resources/org/apache/pdfbox/examples/signature/sign_me.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/examples/src/test/resources/org/apache/pdfbox/examples/signature/sign_me_protected.pdf" | Tier2PdfBoxCorpusTest | PASS | 2ms |
| testPdfBoxCorpus with data set "pdfbox/examples/src/test/resources/org/apache/pdfbox/examples/signature/sign_me_tsa.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/examples/src/test/resources/org/apache/pdfbox/examples/signature/sign_me_visible.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/FC60_Times.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/Liste732004001452_001_0.pdf_0_.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-2984-rotations.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3025.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3038-001033-p2.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3042-003177-p2.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3044-010197-p5-ligatures.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3053-reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3061-092465-reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3062-002207-p1.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3062-005717-p1.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3062-N2MOQ7YZICIYGTPLQJAWJ4HLN6CCEMHZ-reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3067-negativeTf.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3110-poems-beads-cropbox.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3110-poems-beads.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3123-ADSFWTRB3HBZBZKEVESVTBRZC2MNKZF5_reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3127-RAU4G6QMOVRYBISJU7R6MOVZCRFUO7P4-VFont.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3195.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3498-Y5TLCWTIAE3FYDVJTV2TXRZGXLEDUNSW.pdf" | Tier2PdfBoxCorpusTest | PASS | 4ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-3833-reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-4322-Empty-ToUnicode-reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-4531-bidi-ligature-1.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-4531-bidi-ligature-2.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-4532-reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-5002.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-5350-JX57O5E5YG6XM4FZABPULQGTW4OXPCWA-p1-reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-5747-unicode-surrogate-with-diacritic-reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-5838-0024320-reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-5920-4MQTG6ZXOYSMTQ444KGQOVC6ZFQHWFNY-spaces-reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/PDFBOX-5920-Y5U2XZCKG2U6TO3FC36NCGOZECHQA2PY-p39-reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | 2ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/compression/acroform.pdf" | Tier2PdfBoxCorpusTest | PASS | 2ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/compression/attachment.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/compression/unencrypted.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/cweb.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/data-000001.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/eu-001.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/hello3.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFA3A.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBOX-4417-001031.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBOX-4417-054080.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBOX-5762-722238.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBOX-5792-240045.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBOX-5809-509329.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBOX-5811-362972.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBOX-5840-410609.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBOX-6018-099267-p9-OrphanPopups.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBox.GlobalResourceMergeTest.Doc01.decoded.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBox.GlobalResourceMergeTest.Doc01.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBox.GlobalResourceMergeTest.Doc02.decoded.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/PDFBox.GlobalResourceMergeTest.Doc02.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/jpegrgb.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/merge/multitiff.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/openoffice-test-document.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/rendering/4PP-Highlighting.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/rendering/PDFBOX-4372-2DAYCLVOFG3FTVO4RMAJJL3VTPNYDFRO-p4_reduced.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/rendering/source.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/rendering/survey.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/rendering/test-landscape2.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/rendering/tiger-as-form-xobject.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/rotation.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/sampleForSpec.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/sample_fonts_solidconvertor.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/simple-openoffice.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/input/yaddatest.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/encryption/AES128ExposedMeta.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/encryption/AES256ExposedMeta.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/encryption/AESkeylength128.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/encryption/AESkeylength256.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/encryption/Acroform-PDFBOX-2333.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/encryption/PasswordSample-128bit.pdf" | Tier2PdfBoxCorpusTest | PASS | 2ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/encryption/PasswordSample-256bit.pdf" | Tier2PdfBoxCorpusTest | PASS | 3ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/encryption/PasswordSample-40bit.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/encryption/preEnc_20141025_105451.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/encryption/test.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcroFormForMerge-DifferentExportValues.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcroFormForMerge-DifferentFieldType.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcroFormForMerge-DifferentOptions.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcroFormForMerge-SameNameNode.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcroFormForMerge-TextFieldsOnly.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcroFormForMerge.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcrobatMerge-DifferentExportValues-WasMaster.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcrobatMerge-DifferentExportValues.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcrobatMerge-DifferentFieldType-WasMaster.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcrobatMerge-DifferentFieldType.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcrobatMerge-DifferentOptions-WasMaster.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcrobatMerge-DifferentOptions.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcrobatMerge-SameMerged.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcrobatMerge-SameNameNode.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/AcrobatMerge-TextFieldsOnly-SameMerged.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/OverlayTestBaseRot0.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/Overlayed-with-rot0.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/Overlayed-with-rot180.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/Overlayed-with-rot270.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/Overlayed-with-rot90.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/PDFBOX-6049-ExpectedResult.pdf" | Tier2PdfBoxCorpusTest | PASS | 2ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/PDFBOX-6049-Overlay.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/PDFBOX-6049-Source.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/PDFBoxLegacyMerge-SameMerged.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/rot0.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/rot180.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/rot270.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/multipdf/rot90.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdfparser/MissingCatalog.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdfparser/PDFBOX-6041-example.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdfparser/SimpleForm2Fields.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdfparser/embedded_zip.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/PDFBOX-3068.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/PDFBOX-6040-nodeloop.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/badpagelabels.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/common/null_PDComplexFileSpecification.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/common/testPDF_multiFormatEmbFiles.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/documentinterchange/logicalstructure/PDFBOX-2725-878725.pdf" | Tier2PdfBoxCorpusTest | PASS | 2ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/font/F001u_3_7j.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/annotation/AnnotationTypes.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/annotation/PDFBOX-5797-SO79271803.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/annotation/PDSquareAnnotationTest.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/AcroFormsBasicFields.pdf" | Tier2PdfBoxCorpusTest | PASS | 2ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/AcroFormsRotation.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/AlignmentTests.pdf" | Tier2PdfBoxCorpusTest | PASS | 4ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/CombTest.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/ControlCharacters.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/DifferentDALevels.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/MultilineFields.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/PDFBOX-3656-SF1199AEG (Complete).pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/PDFBOX-3835-input-acrobat-wrap.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/PDFBOX-4958.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/PDFBOX-5784.pdf" | Tier2PdfBoxCorpusTest | PASS | 8ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/form/PDFBOX3812-acrobat-multiline-auto.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/interactive/pagenavigation/transitions_test.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/page_tree_multiple_levels.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/test.unc.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/test_pagelabels.pdf" | Tier2PdfBoxCorpusTest | PASS | 5ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/pdmodel/with_outline.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/pdfbox/src/test/resources/org/apache/pdfbox/text/BidiSample.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/tools/src/test/resources/input/ImageIOUtil/JBIG2Image.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/tools/src/test/resources/input/ImageIOUtil/JPXTestCMYK.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/tools/src/test/resources/input/ImageIOUtil/JPXTestGrey.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/tools/src/test/resources/input/ImageIOUtil/JPXTestRGB.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/tools/src/test/resources/input/ImageIOUtil/ccitt4-cib-test.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/tools/src/test/resources/input/ImageIOUtil/jpeg_demo.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/tools/src/test/resources/input/ImageIOUtil/png_demo.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/tools/src/test/resources/input/ImageIOUtil/raw_image_demo.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/tools/src/test/resources/org/apache/pdfbox/AngledExample.pdf" | Tier2PdfBoxCorpusTest | PASS | 1ms |
| testPdfBoxCorpus with data set "pdfbox/tools/src/test/resources/org/apache/pdfbox/hello3.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfBoxCorpus with data set "pdfbox/tools/src/test/resources/org/apache/pdfbox/testPDFPackage.pdf" | Tier2PdfBoxCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/344775293.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/363015187.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/about_blank.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/annot_javascript.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/annotation_fileattachment.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/annotation_highlight_long_content.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/annotation_highlight_rollover_ap.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/annotation_highlight_square_with_ap.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/annotation_ink_multiple.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/annotation_markup_multiline_no_ap.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/annotation_stamp_with_ap.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/annotiter.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/annots.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/annots_action_handling.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bad_annots_entry.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bad_dict_keys.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bad_page_type.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bigtable_mini.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/black.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bookmarks.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bookmarks_circular.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1029.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1055869.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1058653.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1124998.pdf" | Tier2PdfiumCorpusTest | PASS | 2ms |
| testPdfiumCorpus with data set "pdfium/bug_113.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1139.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1206.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1229106.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1265.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1296920.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1301.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1302355.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1302455.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1324189.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1324503.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1327884.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1328389.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1333298.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1388_2.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1396264.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1399.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1477093.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1506.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1549.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1558.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1574.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1591.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1646.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1658.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1768.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1769.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_182.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1893.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_1919.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_2034.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_213.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_2132.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_216.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_298.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_306123.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_325_a.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_325_b.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_343.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_343075986.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_344.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_355.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_360.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_378120423.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_384770169.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_399689604.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_402562387.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_420508260.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_42270471.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_42271133.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_424613308.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_425244539.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_431824298.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_451265.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_451830.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_452455.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_454695.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_455199.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_459580.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_481363.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_487928.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_507316.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_544880.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_547706.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_551248.pdf" | Tier2PdfiumCorpusTest | PASS | 3ms |
| testPdfiumCorpus with data set "pdfium/bug_551460.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_552046.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_555784.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_57.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_572871.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_583.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_601362.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_602650.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_620428.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_631912.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_634394.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_634716.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_642.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_644.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_650.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_664284.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_674771.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_679649.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_680376.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_707673.pdf" | Tier2PdfiumCorpusTest | PASS | 6ms |
| testPdfiumCorpus with data set "pdfium/bug_709793.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_713197.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_717.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_750568.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_757705.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_765384.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_773229.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_779.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_781804.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_782596.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_821454.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_828049.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_861842.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_889099.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_890322.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_896366.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_900552.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_901654.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_901654_2.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_905142.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_921.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_925981.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_972518.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/bug_xrefv4_loop.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/calculate.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/circular_viewer_ref.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/click_form.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/clip_path.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/combobox_form.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/control_characters.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/cropped_no_overlap.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/cropped_text.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/dashed_lines.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/docmdp.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/document_aactions.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/embedded_attachments.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/embedded_attachments_invalid_data.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/embedded_attachments_invalid_types.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/embedded_images.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/empty_xref.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/encrypted.pdf" | Tier2PdfiumCorpusTest | PASS | 2ms |
| testPdfiumCorpus with data set "pdfium/encrypted_hello_world_r2.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/encrypted_hello_world_r2_bad_okey.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/encrypted_hello_world_r3.pdf" | Tier2PdfiumCorpusTest | PASS | 2ms |
| testPdfiumCorpus with data set "pdfium/encrypted_hello_world_r3_bad_okey.pdf" | Tier2PdfiumCorpusTest | PASS | 2ms |
| testPdfiumCorpus with data set "pdfium/encrypted_hello_world_r5.pdf" | Tier2PdfiumCorpusTest | PASS | 2ms |
| testPdfiumCorpus with data set "pdfium/encrypted_hello_world_r6.pdf" | Tier2PdfiumCorpusTest | PASS | 3ms |
| testPdfiumCorpus with data set "pdfium/feature_linearized_loading.pdf" | Tier2PdfiumCorpusTest | PASS | 1ms |
| testPdfiumCorpus with data set "pdfium/find_text_consecutive.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/font_matrix.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/font_weight.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/form_object.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/form_object_with_image.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/form_object_with_path.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/form_object_with_text.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/freetext_annotation_without_da.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/get_page_aaction.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/goto_action.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/gotoe_action.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/hebrew_mirrored.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/hello_world.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/hello_world_2_pages.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/hello_world_2_pages_custom_object.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/hello_world_2_pages_shared_resources_dict.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/hello_world_2_pages_split_streams.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/hello_world_compressed_stream.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/hello_world_split_streams.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/ink_annot.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/javascript/xfa_specific/bug_1042915.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/javascript/xfa_specific/bug_1042956.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/javascript/xfa_specific/bug_1043510.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/javascript/xfa_specific/bug_1238_2.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/javascript/xfa_specific/resolve_nodes_1.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/javascript/xfa_specific/resolve_nodes_2.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/jpx_lzw.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/js.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/latin_extended.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/launch_action.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/line_annot.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/linearized.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/linearized_bug_1055.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/links_highlights_annots.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/listbox_form.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/many_rectangles.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/marked_content_id.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/matte.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/multiple_form_types.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/multiple_graphics_states.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/named_dests.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/named_dests_old_style.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/no_page_count.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/non_hex_file_id.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/nonesuch_action.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/page_labels.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/page_tree_empty_node.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/parser_rebuildxref_correct.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/parser_rebuildxref_error_notrailer.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_1087.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_1287409.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_1308.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_1484283.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_2122.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_2123.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_2152.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_304.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_332462378.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_335309995.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_345274934.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_349972030.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_42271010.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_440028542.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_512557.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_603518.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_828206.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_830221.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_867501.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/bug_925736.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/direct_content_stream.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/freetext_annotation_without_da.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/generation_numbers1.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/generation_numbers2.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/xfa_specific/barcode_test.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/xfa_specific/dynamic_list_box_allow_multiple_selection.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/xfa_specific/dynamic_password_field_background_fill.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/xfa_specific/dynamic_table_color_and_width.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/xfa_specific/resolve_nodes_0.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/xfa_specific/standard_symbols.pdf" | Tier2PdfiumCorpusTest | PASS | 1ms |
| testPdfiumCorpus with data set "pdfium/pixel/xfa_specific/static_list_box_caption.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/pixel/xfa_specific/static_password_field_rotate.pdf" | Tier2PdfiumCorpusTest | PASS | 1ms |
| testPdfiumCorpus with data set "pdfium/pixel/xfa_specific/xfa_node_caption.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/polygon_annot.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/rectangles.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/rectangles_double_flipped.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/rectangles_multi_page_xfa.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/rectangles_multi_pages.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/rectangles_object_zero.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/rectangles_with_leaky_ctm.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/redact_annot.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/redirect.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/repeat_viewer_ref.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/rotated_image.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/rotated_text.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/rotated_text_90.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/signature_no_sub_filter.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/signature_reason.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/simple_thumbnail.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/simple_xfa.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/split_streams.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/tagged_actual_text.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/tagged_alt_text.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/tagged_marked_content.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/tagged_mcr_multipage.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/tagged_mcr_objr.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/tagged_nested.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/tagged_table.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/tagged_table_bad_elem.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/tagged_table_bad_parent.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/text_color.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/text_font.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/text_form.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/text_form_color.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/text_form_multiline.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/text_form_multiple.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/text_form_negative_fontsize.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/text_in_page_marked.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/text_in_page_marked_indirect.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/text_render_mode.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/thumbnail_with_empty_stream.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/thumbnail_with_no_filters.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/trailer_as_hexstring.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/trailer_end_trailing_space.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/trailer_unterminated.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/two_signatures.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/unsupported_feature.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/uri_action.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/uri_action_nonascii.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/use_outlines.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/utf-8.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/version_in_catalog.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/vertical_text.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/viewer_pref_types.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/viewer_ref.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/weblinks.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/weblinks_across_lines.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/xfa/email_recommended.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/xfa/xfa_break_before_after.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/xfa/xfa_combobox.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/xfa/xfa_date_time_edit.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/xfa/xfa_image_edit.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/xfa/xfa_multiline_textfield.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |
| testPdfiumCorpus with data set "pdfium/zero_length_stream.pdf" | Tier2PdfiumCorpusTest | PASS | <1ms |



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

**6 tests** | 6 passed | 0 failed | 0 skipped | 12.06s

| Test | Class | Status | Time |
|---|---|---|---|
| testTaggedDocumentValidatesWithUa1 | Tier3MatterhornTest | PASS | 2.12s |
| testAnnotationWithContentsPassesClause718 | Tier3MatterhornTest | PASS | 2.24s |
| testUntaggedDocumentFailsUa1 | Tier3MatterhornTest | PASS | 1.91s |
| testMissingLangFailsUa1 | Tier3MatterhornTest | PASS | 1.79s |
| testMissingDisplayDocTitleFailsUa1 | Tier3MatterhornTest | PASS | 1.94s |
| testAnnotationWithoutContentsFailsUa1 | Tier3MatterhornTest | PASS | 2.07s |



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

**25 tests** | 24 passed | 0 failed | 1 skipped | 8.41s

| Test | Class | Status | Time |
|---|---|---|---|
| testMinimalPdfWellFormedAndValid | Tier4JhoveTest | PASS | 966ms |
| testMultiPagePdfWellFormedAndValid | Tier4JhoveTest | PASS | 895ms |
| testPdfWithMetadataWellFormedAndValid | Tier4JhoveTest | PASS | 853ms |
| testPdfWithEmbeddedFontWellFormedAndValid | Tier4JhoveTest | PASS | 851ms |
| testJhoveToolchainWorks | Tier4JhoveTest | PASS | 683ms |
| testPdfA1bPassesPdfBoxPreflight | Tier4PdfBoxPreflightTest | SKIP | <1ms |
| testPdfBoxPreflightToolchainWorks | Tier4PdfBoxPreflightTest | PASS | 800ms |
| testMinimalPdfHasNoSuspiciousIndicators | Tier4PdfIdTest | PASS | 625ms |
| testPdfWithMetadataHasNoSuspiciousIndicators | Tier4PdfIdTest | PASS | 542ms |
| testPdfWithEmbeddedFontHasNoSuspiciousIndicators | Tier4PdfIdTest | PASS | 519ms |
| testPdfIdToolchainWorks | Tier4PdfIdTest | PASS | 344ms |
| testPdf20ExampleParseable with data set "pdf20/PDF 2.0 UTF-8 string and annotation.pdf" | Tier4Pdf20ExamplesTest | PASS | 3ms |
| testPdf20ExampleParseable with data set "pdf20/PDF 2.0 image with BPC.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleParseable with data set "pdf20/PDF 2.0 via incremental save.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleParseable with data set "pdf20/PDF 2.0 with offset start.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleParseable with data set "pdf20/PDF 2.0 with page level output intent.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleParseable with data set "pdf20/Simple PDF 2.0 file.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleParseable with data set "pdf20/pdf20-utf8-test.pdf" | Tier4Pdf20ExamplesTest | PASS | <1ms |
| testPdf20ExampleQpdfValid with data set "pdf20/PDF 2.0 UTF-8 string and annotation.pdf" | Tier4Pdf20ExamplesTest | PASS | 189ms |
| testPdf20ExampleQpdfValid with data set "pdf20/PDF 2.0 image with BPC.pdf" | Tier4Pdf20ExamplesTest | PASS | 189ms |
| testPdf20ExampleQpdfValid with data set "pdf20/PDF 2.0 via incremental save.pdf" | Tier4Pdf20ExamplesTest | PASS | 186ms |
| testPdf20ExampleQpdfValid with data set "pdf20/PDF 2.0 with offset start.pdf" | Tier4Pdf20ExamplesTest | PASS | 191ms |
| testPdf20ExampleQpdfValid with data set "pdf20/PDF 2.0 with page level output intent.pdf" | Tier4Pdf20ExamplesTest | PASS | 192ms |
| testPdf20ExampleQpdfValid with data set "pdf20/Simple PDF 2.0 file.pdf" | Tier4Pdf20ExamplesTest | PASS | 193ms |
| testPdf20ExampleQpdfValid with data set "pdf20/pdf20-utf8-test.pdf" | Tier4Pdf20ExamplesTest | PASS | 188ms |

