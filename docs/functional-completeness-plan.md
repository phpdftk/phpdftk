# Plan: PDF Layer Functional Completeness

## Context

The object model is 98% complete (457/467 spec fields), but the **functional pipelines** — wiring objects into end-to-end workflows — have critical gaps. This plan addresses every gap identified in the audit, ordered by dependencies and impact. The focus is on the PDF spec layer, not developer-facing convenience APIs.

---

## Status

| Phase | Status | Tests | Notes |
|---|---|---|---|
| 1. Predictor Filters + LZW | **DONE** | 21 new | PredictorFilter (PNG+TIFF), LzwFilter (EarlyChange param), StreamParser wiring |
| 2. Stream Compression | **DONE** | 5 new | FlateDecode auto-applied on write, `compressStreams` option |
| 3. Hydration Layer | **DONE** | 15 new | PdfHydrator (reflection + type coercion), getTypedCatalog/Page/Pages |
| 4. Encryption Pipeline | **DONE** | 11 new | RC4-128 + AES-128 read/write, PdfDecryptor + PdfEncryptor, password auth |
| 5. Content Stream Parsing | **DONE** | 17 new | ContentStreamParser + TextExtractor, extractText/extractAllText, ToUnicode + WinAnsi + UTF-8 passthrough |
| 6. Font Subsetting | **DONE** | 8 new | TrueTypeSubsetter, glyf/loca/hmtx/cmap rebuild, composite glyph resolution |
| 7. Unicode/CID Fonts | **DONE** | 6 new | Type0FontFactory, CID font stack, Identity-H encoding, 2-byte ToUnicode CMap, showTextHex |
| 8. XMP Metadata Integration | **DONE** | 3 new | PdfWriter::setMetadata(), MetadataStream wiring to Catalog |
| 9. XRef Stream Output | **DONE** | 6 new | PdfFileWriter useXRefStream option, CrossReferenceStream with FlateDecode |
| 10. Form Appearances | **DONE** | 18 new | AppearanceGenerator (text/checkbox/radio/push/choice), FormXObject rendering, AppearanceDict builders |
| 11. OpenType/CFF Fonts | **DONE** | 14 new | OpenTypeParser (CFF table extraction), addOpenTypeFont, CFFFontFile embedding, CIDFontType0 stack |
| 12. Incremental Update | **DONE** | 9 new | IncrementalWriter, /Prev chain, modified/new objects, subsection xref |
| 13. ICC Profiles | **DONE** | 7 new | JPEG APP2 + PNG iCCP extraction, ICCBased color space in addImage(), /N auto-detection |
| 14. Halftone/CMap Gaps | **DONE** | 17 new | HalftoneType 1/5/6/10/16, spec-coverage updated (457→467 ✓) |
| 15. Error Tolerance | **DONE** | 6 new | Lenient mode, expanded startxref search, xref entry recovery, header tolerance, getParseWarnings() |

**Total: 1144 tests, 2911 assertions, 0 phpstan errors — ALL 15 PHASES COMPLETE + 21/22 LIMITATIONS FIXED**

---

## Phases 1-4: Implementation Details & Known Limitations

### Phase 1: Predictor Filters + LZW — DONE

**What shipped:**
- `PredictorFilter` — PNG predictors (None/Sub/Up/Average/Paeth), TIFF Predictor 2, both encode and decode
- `LzwFilter` — full encode/decode with MSB-first bit packing, variable 9-12 bit codes, `$earlyChange` parameter
- `StreamParser` — wired `/DecodeParms` extraction, predictor params, LZW+EarlyChange

**Known limitations:**
- `/DecodeParms` as indirect reference (`PdfReference`) not resolved — would need ObjectResolver passed to StreamParser
- No real-world LZW PDFs in test suite (round-trip tests only)
- Partial final rows in TIFF predictor silently truncated (no validation)
- PNG predictor doesn't validate stride alignment on incomplete data

### Phase 2: Stream Compression on Write — DONE

**What shipped:**
- `PdfFileWriter` auto-applies FlateDecode to unfiltered streams
- `$compressStreams` constructor param (default true) propagated through `PdfWriter` and `Pdf`
- Idempotent: `generate()` can be called multiple times safely (guard on `has('Filter')`)
- When encryption is active, compression is applied on clones in correct spec order (compress → encrypt)

**Known limitations:**
- No predictor encoding on write (uses raw FlateDecode without PNG predictors)
- Small streams may be slightly larger after compression (Flate overhead)

### Phase 3: Hydration Layer — DONE

**What shipped:**
- `PdfHydrator` — reflection-based, type registry, camelCase key mapping, property overrides
- Type coercion: `PdfNumber→int/float`, `PdfArray→array`, `PdfReference→array`, `PdfBoolean→bool`
- TypeError safety: incompatible assignments silently skipped
- `PdfReader` convenience: `getTypedCatalog()`, `getTypedPage()`, `getTypedPages()`, `getTypedObject()`

**Known limitations:**
- Readonly properties silently skipped (caught as TypeError)
- Only Catalog, Page, PageTree fully tested; other registered types (Outline, OutputIntent, etc.) have overrides but no dedicated tests
- Nested objects not recursively hydrated (e.g., Resources inside Page stays as PdfDictionary)
- Static cache (`$typeMap`, `$keyMapCache`) shared across process — safe for sequential tests, not for parallel mutation
- Not all typed classes have override entries (many work via ucfirst convention, but edge cases may exist)

### Phase 4: Encryption Pipeline — DONE

**What shipped:**
- `PdfDecryptor` — decrypts strings/streams per-object, Standard handler R=2/3/4 (RC4 + AES-128)
- `PdfEncryptor` — factory methods `rc4128()` and `aes128()`, permission constants, generates EncryptDictionary
- `PdfKeyDerivation` — `computeFileEncryptionKey()`, `computeUserKey()`, `authenticateUserPassword()`, `authenticateOwnerPassword()`
- `PdfFileWriter::setEncryption()` — registers encrypt dict, wires into trailer
- `generate()` idempotency: objects cloned before encryption, originals untouched
- Correct spec order: compress → encrypt (on clones)
- V=5/R=6 (AES-256): clear error message when encountered

**Known limitations:**
- No AES-256 (V=5 R=5/6) — throws clear error
- No RC4-40 (V=1 R=2) factory method on PdfEncryptor (decryptor handles it, but can't create new ones)
- No public-key encryption handler
- `/ID` values with special chars (parens, backslash) use literal strings — works via PdfString escaping but hex strings would be safer for trailer /ID
- Password handling limited to Latin-1 (no SASLprep/UTF-8 normalization per PDF 2.0)
- No test with externally-encrypted PDFs (only round-trip with own encryptor)

### Phase 5: Content Stream Parsing (Text Extraction) — DONE

**What shipped:**
- `ContentStreamParser` — tokenizes decoded content stream data into `ContentStreamOp` sequences (operands + operator). Handles all PDF token types: numbers, names, literal strings, hex strings, arrays, inline dicts, inline images, comments.
- `ContentStreamOp` — value object holding operands (raw strings) and operator keyword
- `TextExtractor` — walks parsed operations, tracks text state (font, position, spacing), converts character codes to Unicode. Supports:
  - `/ToUnicode` CMap (via `CMapParser`)
  - `/Encoding` with `/Differences` array
  - `/Encoding /WinAnsiEncoding` → GlyphList lookup
  - UTF-8 passthrough for multi-byte sequences (common in FPDF/modern producers)
  - WinAnsi → GlyphList fallback for standard fonts without explicit encoding
- `PdfReader::extractText(int $pageIndex)` — convenience for single-page extraction
- `PdfReader::extractAllText(string $separator)` — all pages concatenated

**Text positioning heuristics:**
- `Td`/`TD`: vertical movement > 0.5× font size → newline; horizontal > space width → space
- `Tm`: same heuristics based on coordinate change from previous position
- `T*`: always newline
- `TJ` arrays: numeric values < -100 → word space
- `'` and `"` operators: newline before text

**Known limitations:**
- No CID/Type 0 font text extraction (2-byte character codes not handled; requires Phase 7)
- No text positioning model (no absolute coordinates tracked; heuristic spacing only)
- UTF-8 passthrough heuristic may misidentify WinAnsi bytes > 127 as multi-byte UTF-8 when they happen to form valid UTF-8 sequences (rare edge case)
- No glyph width tracking for precise space detection (uses 0.25× font size estimate)
- No support for ActualText in marked content (structure tags)
- Inline images (`BI`/`ID`/`EI`) are parsed but image data is not interpreted
- No text extraction from Type 3 font glyph procs (custom glyph content streams)
- Content stream arrays (`/Contents [ref1 ref2 ...]`) are concatenated but inter-stream state is not preserved
- No right-to-left or vertical text layout handling

---

## Dependency Graph

```
Phase 1 (Filters) ─────────────────────────────── DONE ────┐
  │                                                         │
  ├─► Phase 2 (Stream Compression) ──────────── DONE       │
  │     │                                                   │
  │     ├─► Phase 9 (XRef Stream Output)                   │
  │     │                                                   │
  ├─► Phase 3 (Hydration) ──────────────────── DONE        │
  │     │                                                   │
  │     ├─► Phase 4 (Encryption Read + Write) ── DONE      │
  │     ├─► Phase 5 (Content Stream Parsing) ── DONE      │
  │     │     │                                             │
  │     │     └─► Phase 7 (Unicode/CID Fonts) ── DONE     │
  │     │           │                                       │
  │     │           ├─► Phase 10 (Form Appearances) ─ DONE  │
  │     │           └─► Phase 11 (OpenType/CFF) ─── DONE  │
  │     │                                                   │
  │     ├─► Phase 6 (Font Subsetting) ────────── DONE      │
  │     ├─► Phase 9 (XRef Stream Output) ─────── DONE     │
  │     └─► Phase 12 (Incremental Update) ──── DONE        │
  │                                                         │
  └─► Phase 8 (XMP Metadata) ───────────── DONE           │
      Phase 13 (ICC Profiles) ──────────── DONE           │
      Phase 14 (Halftone/CMap) ─────────── DONE ◄─────────┘

Phase 15 (Error Tolerance) ──────────────── DONE
```

Phases 5-15 are unblocked. Next natural steps:
- **Phase 5** (Content Stream Parsing / Text Extraction) — highest user-facing value
- **Phase 6** (Font Subsetting) — biggest file-size impact
- **Phases 8, 13, 14** — independent, can run in parallel with anything

---

### Phase 6: Font Subsetting — DONE

**What shipped:**
- `TrueTypeSubsetter` — takes raw TTF bytes + set of GIDs, produces minimal valid TTF
  - Always includes GID 0 (.notdef)
  - Recursively resolves composite glyph components (flag bit 5)
  - Rebuilds: `head`, `hhea`, `maxp`, `OS/2`, `name`, `post`, `cmap` (format 4), `hmtx`, `loca`, `glyf`
  - Correct table directory checksums and offsets
  - Pads glyph data to 4-byte boundaries
- `TrueTypeData` extended: `fullUnicodeToGid`, `glyphWidths` (GID-indexed), `unitsPerEm`
- `TrueTypeParser` populates the new fields from the existing cmap/hmtx parsing

**Known limitations:**
- Only cmap format 4 (BMP Unicode); no format 12 (supplementary planes > U+FFFF)
- No OpenType/CFF subsetting (TrueType glyf/loca only)
- checkSumAdjustment in head table set to 0 (not fully calculated)
- No name table subsetting (full name table copied)
- Composite glyph resolution is recursive but doesn't handle deeply nested composites (>10 levels)
- Not wired into `embedTrueTypeFont()` for WinAnsi path (only used via `addCompositeFont()` for CID fonts)

### Phase 7: Unicode/CID Font Pipeline — DONE

**What shipped:**
- `Type0FontFactory::fromTrueTypeData()` — builds complete composite font stack:
  - `Type0Font` → `CIDFontType2Font` → `FontDescriptor` → `FontFile2` (subset)
  - Identity-H encoding, `/CIDToGIDMap /Identity`
  - `/W` array with compact grouping for per-CID widths
  - ToUnicode CMap with `<0000> <FFFF>` codespace (2-byte GID → Unicode mapping)
  - Integrates `TrueTypeSubsetter` to embed only needed glyphs
- `PdfWriter::addCompositeFont()` — registers all objects in the CID font stack
- `ContentStream::showTextHex()` — emits `<hex> Tj` for 2-byte GID text
- `Type0Font::$encoding` now accepts `PdfName` directly (was `PdfReference` only)

**Known limitations:**
- No automatic text encoding — caller must convert Unicode codepoints to GID hex sequences manually
- No high-level `showUnicodeText(string $utf8)` convenience method on ContentStream
- No vertical writing mode (Identity-V)
- No CJK predefined CMaps (only Identity-H)
- CID font text extraction in reader requires the ToUnicode CMap (no fallback for missing CMap)
- No font fallback mechanism (missing glyph → .notdef)

### Phase 8: XMP Metadata Integration — DONE

**What shipped:**
- `PdfWriter::setMetadata(string $xmpXml)` — creates `MetadataStream`, registers it, wires to `Catalog::$metadata`
- Works with `XmpWriter::serialize()` output directly

**Known limitations:**
- No auto-sync between `Info` dict fields and XMP properties (manual only)
- No PDF/A XMP validation
- XMP is stored uncompressed in the metadata stream (per PDF/A requirement, this is actually correct)

### Phase 9: XRef Stream Output — DONE

**What shipped:**
- `PdfFileWriter` accepts `useXRefStream: true` constructor parameter
- Builds `CrossReferenceStream` instead of classic `xref` table + `trailer`
- Trailer entries (Root, Info, ID, Encrypt) stored in the xref stream dictionary
- FlateDecode compression applied via `setFilter()` (respects `CrossReferenceStream::toPdf()` dictionary rebuild)
- Round-trips correctly through `PdfReader` (which already handles xref streams)

**Known limitations:**
- No `/Index` array for sparse object numbering (assumes sequential 0..Size-1)
- Not combined with `ObjectStream` for maximum compression (objects still individually serialized)
- `/W` array fixed at `[1 4 2]` (7 bytes per entry) — could be optimized for small files

---

### Phase 10: Form Appearance Generation — DONE

**What shipped:**
- `AppearanceGenerator` — static factory methods generating `FormXObject` appearance streams:
  - `textField()` — bordered box with rendered value text, justification (left/center/right)
  - `checkbox()` — returns `{on, off}` FormXObjects; on = box + check mark lines, off = empty box
  - `radioButton()` — returns `{on, off}` FormXObjects; circle via Bézier approximation, filled dot for on
  - `pushButton()` — 3D-effect border with centered label text
  - `choiceField()` — same visual as text field with selected value
- `buildAppearanceDict()` — wraps a single normal appearance in AppearanceDict
- `buildStateAppearanceDict()` — wraps on/off state dict in AppearanceDict for checkbox/radio
- `Annotation::$ap` widened to `PdfDictionary|AppearanceDict|null`
- Integration test: full form with text field, checkbox, choice field — all with generated appearances, `NeedAppearances=false`
- New sample PDF: `docs/sample-pdfs/form_appearances.pdf`

**Known limitations:**
- No font width measurement for text centering/right-alignment (uses approximate positioning)
- No multi-line text field appearance
- No password field masking (dots/asterisks)
- No comb text field layout (equally-spaced characters)
- No rich text appearance rendering
- No appearance for signature fields
- Radio button Bézier circle is approximate (4-curve approximation, not pixel-perfect)
- Push button label centering is approximate without glyph width data

### Phase 11: OpenType/CFF Font Support — DONE

**What shipped:**
- `OpenTypeParser` — parses OpenType CFF fonts (sfVersion `0x4F54544F` / "OTTO"):
  - Extracts raw CFF table bytes for embedding
  - Parses same metric tables as TrueType (head, hhea, OS/2, maxp, hmtx, cmap, name, post)
  - Supports cmap format 4 (BMP Unicode) and format 12 (full Unicode range)
  - Builds `fullUnicodeToGid` + `glyphWidths` maps
- `OpenTypeData` — readonly data class (metrics, CFF bytes, full font bytes, glyph mappings)
- `PdfWriter::addOpenTypeFont()` — builds complete CID font stack for CFF:
  - `Type0Font` → `CIDFontType0Font` → `FontDescriptor` → `CFFFontFile` (/Subtype /CIDFontType0C)
  - Identity-H encoding, `/W` widths array, ToUnicode CMap
- New benchmark: `benchPhpdftk10PagesWithOpenTypeCff`
- New sample PDF: `docs/sample-pdfs/opentype_cff.pdf`

**Known limitations:**
- No CFF subsetting (full CFF table embedded) — CFF subsetting requires parsing CFF charstrings which is significantly more complex than TrueType glyf subsetting
- No OpenType layout features (GPOS/GSUB — ligatures, kerning, contextual alternates)
- No WOFF/WOFF2 decompression
- CFF embedding uses `/Subtype /CIDFontType0C` (CFF data only), not `/Subtype /OpenType` (full OTF file) — this is the more widely-supported option
- Tests skip if no OTF font found on the system (macOS-dependent fixture)
- No TrueType-flavored OpenType detection (sfVersion 0x00010000 with CFF table — rare but possible)

---

### Phase 12: Incremental Update on Write — DONE

**What shipped:**
- `IncrementalWriter` — appends modified/new objects to an existing PDF without rewriting
  - `fromReader()` factory extracts metadata (Size, startxref, Root, Info, ID, Encrypt) from reader
  - `addModifiedObject()` — replaces an existing object (preserves original object number)
  - `addNewObject()` — adds a new object (assigns next sequential number, returns PdfReference)
  - `generate()` — produces complete PDF: original bytes + appended objects + subsection xref + trailer with `/Prev`
  - `save()` — writes to file
- Subsection xref format: groups contiguous object numbers into `N M` subsection headers
- `/ID` array: first element preserved from original, second element updated (per spec)
- Stream compression applied to appended objects (optional, default on)
- Supports stacking: multiple incremental updates on top of each other

**Known limitations:**
- No xref stream mode for incremental updates (always classic xref table + trailer)
- No object deletion (free entries) — only modification and addition
- No encryption integration for incremental updates (encrypted PDFs need per-object key derivation for new objects)
- Does not verify that modified object numbers actually exist in the original
- No automatic page tree re-wiring when adding pages incrementally (caller must update PageTree manually)
- `/Prev` chain depth not validated (deeply stacked updates could be slow to parse)

---

### Phase 13: ICC Profile Embedding — DONE

**What shipped:**
- `ImageInfo` — added `?string $iccProfile` field (backward compatible)
- `JpegParser` — extracts ICC profiles from APP2 markers (0xE2), handles multi-chunk assembly with sequence numbers
- `PngParser` — extracts ICC profiles from `iCCP` chunks, decompresses via `gzuncompress()`
- `PdfWriter::addImage()` — when ICC profile present, creates profile stream with `/N` (1/3/4 based on color space), registers it, replaces `/ColorSpace` with `ICCBased` array reference

**Known limitations:**
- No ICC profile extraction from TIFF or WebP images
- No rendering intent passthrough from image metadata
- ICC profile not validated (assumed well-formed)
- No color space conversion (CMYK ICC profiles embedded as-is)

### Phase 14: Halftone Types + CMap Completeness — DONE

**What shipped:**
- `HalftoneType1` — dictionary-based: Frequency, Angle, SpotFunction, TransferFunction, AccurateScreens
- `HalftoneType5` — composite: Default halftone + colorant entries via PdfDictionary
- `HalftoneType6` — threshold array stream: Width, Height, TransferFunction
- `HalftoneType10` — threshold stream (PDF 1.3+)
- `HalftoneType16` — 16-bit threshold stream (PDF 2.0+)
- `docs/spec-coverage.md` updated: all 5 halftone types ✓, CMap stream ✓, font subsetting ✓, OpenType ✓

**Spec coverage now: 467/467 fields implemented (100%)**

**Known limitations:**
- Halftone types are object-model only — no automatic screening generation
- No halftone validation or optimization
- HalftoneType5 colorant entries must be manually constructed

### Phase 15: Reader Error Tolerance — DONE

**What shipped:**
- `PdfReader` — lenient mode via `$strict = false` parameter on all factory methods
- `findStartxref()` — progressive search: 1024 → 8192 → 65536 bytes from EOF
- Header tolerance — in lenient mode, scans first 1024 bytes for `%PDF-` (not just first 20)
- `XrefParser::parseClassicXref()` — in lenient mode, skips malformed xref entries with warnings instead of throwing
- `PdfReader::getParseWarnings()` — returns array of warning messages from lenient parsing
- Strict mode (default) preserves all existing behavior

**Known limitations:**
- No trailer reconstruction fallback (if trailer dict itself is malformed, still throws)
- No object-level repair (corrupted object bodies cause parse failure)
- No `/Prev` chain recovery (broken chain terminates, no scan-based fallback)
- Linearization output not implemented (object model only)
- PDF/A validation not implemented (would be a separate validator class)
- `scanForEndstream()` boundary validation not enhanced (existing heuristic preserved)

---

## All Phases Complete + Significant-Effort Items

### Completed

| Item | Status | Tests | Notes |
|---|---|---|---|
| 15 original phases | **DONE** | 1144 | 467/467 spec fields, 69/69 operators |
| 22 limitation fixes | **DONE** | +21 | 21/22 straightforward limitations resolved |
| Tier 1A: ActualText | **DONE** | +2 | Marked content stack, `/ActualText` extraction in TextExtractor |
| Tier 1B: cmap Format 12 | **DONE** | +2 | Parser + subsetter support for supplementary Unicode planes |
| Tier 1C: SASLprep | **DONE** | +13 | RFC 4013 password normalization (mapping, NFKC, prohibit, bidi) |
| Tier 2A: AES-256 (V=5 R=6) | **DONE** | +11 | Full R=6 key derivation (SHA-256/384/512 iterative hash), encrypt/decrypt, `aes256()` factory |
| Tier 2B: Trailer Reconstruction | **DONE** | +5 | ObjectScanner, lenient-mode fallback, corrupted xref/startxref recovery |

| Metric | Value |
|---|---|
| Total tests | 1177 |
| Total assertions | 2977 |
| PHPStan errors | 0 |
| Spec field coverage | 467/467 (100%) |
| Content stream operators | 69/69 (100%) |

---

### Tier 3: Very High Effort, Specialized — NOT YET IMPLEMENTED

#### 3A. CFF Font Subsetting

**Why:** OpenType CFF fonts currently embed the full CFF table (~100KB+). Subsetting would reduce to ~5-10KB for typical documents.

**What exists:**
- `OpenTypeParser` extracts raw CFF bytes from the `CFF` table
- `CFFFontFile` stores CFF bytes as a stream with `/Subtype /CIDFontType0C`
- No CFF parsing or charstring interpretation

**What's needed:**
- `packages/font-parser/src/CffParser.php` — parse CFF structure: Header, Name INDEX, Top DICT INDEX, String INDEX, Global Subr INDEX, CharStrings INDEX, Private DICT + Local Subr INDEX, Charset (GID → SID)
- `packages/font-parser/src/CffSubsetter.php` — rebuild CharStrings INDEX with only requested glyphs, rebuild Charset, preserve subroutines (can't remove without charstring analysis), rebuild Top DICT offsets
- Wire into `PdfWriter::addOpenTypeFont()` to subset before embedding
- CFF is a complex binary format (Adobe Type 2 spec); charstrings are stack-based bytecode

**Complexity:** Very high. CFF INDEX format uses variable-length offsets. Top DICT uses a compact binary encoding with operand stacking. Most PDF libraries either punt on CFF subsetting or use an external tool (e.g., fonttools).

**Known limitations that would remain:** No subroutine deduplication (all global/local subrs preserved). No charstring optimization.

#### 3B. OpenType Layout (GPOS) — Kerning Only

**Why:** Without kerning, text looks loose. Full GPOS/GSUB is a text shaper; kerning alone provides ~80% of the typographic value.

**What exists:** Nothing — no GPOS/GSUB table parsing anywhere.

**What's needed:**
- `packages/font-parser/src/KerningParser.php` — parse GPOS table for PairPos (lookup type 2) subtables, or fall back to legacy `kern` table. Extract pairs as `[leftGid, rightGid] => xAdvanceAdjust`.
- `TrueTypeData` + `OpenTypeData` — add optional `?array $kernPairs` field
- `TrueTypeParser` + `OpenTypeParser` — optionally parse kern data
- `packages/pdf/writer/src/TextShaper.php` — given a string + font data + kern pairs, compute per-glyph x-advances. Output a TJ array with positioning adjustments.

**Scope:** Kerning ONLY. Does NOT include ligature substitution, contextual alternates, mark positioning, or script-specific shaping (Arabic, Devanagari, etc.). Those require a full shaping engine (HarfBuzz).

**Complexity:** Very high. GPOS table structure is deeply nested (ScriptList → FeatureList → LookupList → subtables). PairPos subtables come in two formats (individual pairs vs class-based). The legacy `kern` table is simpler but not always present in modern fonts.

---

### Tier 4: Very High Effort, Niche — DEFERRED

#### 4A. Public-Key Encryption

**Why:** Used in enterprise document management systems. Rare in consumer PDFs.

**What exists:** `PublicKeyRecipient` object model is complete. `EncryptDictionary` has `/Recipients` field. No functional implementation.

**What's needed:**
- CMS/PKCS#7 library integration (wrapping `openssl_pkcs7_*` or a PHP CMS library)
- `PdfEncryptor::publicKey()` factory with certificate-based key generation
- `PdfDecryptor` support for `/Adobe.PubSec` filter + PKCS#7 envelope extraction
- Certificate chain validation, recipient key extraction

**Recommendation:** Defer unless there's a specific enterprise use case. The object model is ready; the crypto pipeline is the blocker.

---

### Remaining Future Work (convenience layer, not spec pipeline)

These are developer-facing features that build on the now-complete spec layer:
- **Tables/lists/headers-footers/page numbering** — high-level layout API in `pdf/writer`
- **HTML-to-PDF** — separate project scope (like Dompdf)
- **Form filling workflow (FDF/XFDF)** — import/export form data
- **Linearization output** — object model exists, not wired into PdfFileWriter
- **PDF/A validation** — dedicated validator checking conformance rules
- **Incremental update encryption** — new objects in incremental updates not encrypted

---

## Verification Summary

### Test Coverage

| Category | Tests | Assertions |
|---|---|---|
| Core object model (spec types) | 709 | 1469 |
| Writer (PdfWriter, Pdf, Theme) | 50+ | 135+ |
| Reader (parsing, extraction, hydration) | 80+ | 200+ |
| Encryption (RC4-40/128, AES-128/256) | 30+ | 60+ |
| Filters (Flate, LZW, Predictor, ASCII85, etc.) | 40+ | 80+ |
| Font parser (TrueType, OpenType, subsetting) | 25+ | 50+ |
| Support packages (color, geometry, encoding, XMP, crypt) | 60+ | 120+ |
| **Total** | **1177** | **2977** |

### How to verify

```bash
# Run all tests
vendor/bin/phpunit

# Run a single suite
vendor/bin/phpunit --testsuite core
vendor/bin/phpunit --testsuite writer
vendor/bin/phpunit --testsuite reader

# Run static analysis
scripts/analyse

# Run benchmarks (generates docs/benchmarks.md)
scripts/benchmark

# Run code coverage (generates docs/coverage-badge.svg)
scripts/coverage
```

### What the tests cover

**Writer round-trips:** Generate PDF → read back with PdfReader → verify page count, structure, metadata.

**Encryption round-trips:** Write encrypted (RC4-40, RC4-128, AES-128, AES-256) → read with correct password → verify content. Wrong password → verify rejection.

**Text extraction:** Read sample PDFs → extract text → verify known strings. Covers standard fonts, embedded TrueType, embedded OpenType CFF, multi-page, ToUnicode CMaps, WinAnsi fallback, UTF-8 passthrough, ActualText marked content.

**Font subsetting:** Parse font → subset to glyph set → verify output smaller → verify re-parseable. Covers TrueType glyf/loca, cmap format 4 and 12, composite glyph resolution, checkSumAdjustment.

**Stream compression:** Generate with/without FlateDecode → verify sizes → verify reader can decompress. Predictor filters (PNG Sub/Up/Average/Paeth, TIFF Predictor 2) encode/decode round-trips. LZW with EarlyChange=0 and 1.

**Incremental updates:** Generate base PDF → append objects → verify original bytes preserved → read back. Stacked updates (2 incremental revisions). Object deletion. Xref stream mode.

**Error tolerance:** Corrupted xref → lenient mode reconstruction from object scan. Displaced headers. Malformed xref entries. Expanded startxref search.

**Hydration:** Read PDF → hydrate to typed objects (Catalog, Page, PageTree) → re-serialize → verify output. Type coercion (PdfNumber→int, PdfArray→array, PdfBoolean→bool).

**External compatibility:** Reader benchmark PDFs generated by FPDF (classic xref). Spec-compliant 20-byte xref entries. Cross-reference streams (PDF 1.5+).

### Sample PDFs

All generated sample PDFs live in `docs/sample-pdfs/`:

| File | Feature exercised |
|---|---|
| `simple_text.pdf` | 3 pages, multiple fonts, basic text |
| `multi_page_complex.pdf` | 10 pages, headers/footers, code blocks |
| `embedded_fonts.pdf` | TrueType font embedding with subset |
| `form_fields.pdf` | AcroForm with TextField, ButtonField, ChoiceField |
| `form_appearances.pdf` | Generated appearances (NeedAppearances=false) |
| `opentype_cff.pdf` | OpenType CFF font via CIDFontType0 |
| `signed_pdf.pdf` | PKCS#7 digital signature |
| `xref_stream.pdf` | PDF 1.5 cross-reference stream + object stream |
| `high_level_pdf.pdf` | Pdf class auto-pagination, headings, rules |
| `bench_*.pdf` | FPDF-generated reference PDFs for benchmarking |
