# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - Unreleased

### Added

- Full PDF object model mapping ISO 32000-2:2020 to PHP 8.4 classes
- Three-tier writer API:
  - `Pdf` — high-level cursor-based builder with auto-pagination and word wrap
  - `PdfWriter` — ergonomic object-model facade with font/image/outline management
  - `PdfFileWriter` — byte-level PDF emitter with xref and trailer generation
- PDF reader with typed hydration, text extraction, and encrypted PDF support
- Toolkit with 12 high-level pipelines: FormFiller, PdfStamper, PdfMerger, PageSlicer, PageTransformer, TextExtractor, TextRedactor, AnnotationFlattener, MetadataEditor, BookmarkEditor, PageLabeler, PdfEncrypt
- 26 annotation subtypes with full field coverage
- 20 action types
- 69 content stream operators via fluent API
- Digital signatures: PKCS#7 signing and RFC 3161 timestamping
- Encryption: RC4-128, AES-128, AES-256, and public-key (certificate-based)
- TrueType, OpenType CFF, and WOFF font parsing with subsetting
- 14 standard PDF fonts with AFM metrics
- Kerning (GPOS + legacy kern) and ligature (GSUB) support
- Color models: RGB, CMYK, Gray with conversions
- Stream filters: FlateDecode, ASCII85, ASCIIHex, RunLength
- XMP metadata read/write
- Image metadata parsing: JPEG, PNG, GIF, TIFF, WebP
- ICC profile extraction
- Cross-reference streams and object streams (PDF 1.5+)
- Incremental updates
- Zero external dependencies — only PHP standard library extensions
