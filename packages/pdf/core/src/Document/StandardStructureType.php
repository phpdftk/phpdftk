<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfVersion;

/**
 * Standard structure types — ISO 32000-2 §14.8.4.
 *
 * Constants for the standard structure element types. Use these in
 * `new StructElem(StandardStructureType::H1)` or similar. The library
 * does not enforce use of standard types; a `StructElem` can still be
 * constructed with any arbitrary type name.
 */
final class StandardStructureType
{
    // Grouping elements
    public const DOCUMENT = 'Document';
    public const DOCUMENT_FRAGMENT = 'DocumentFragment';  // PDF 2.0
    public const PART = 'Part';
    public const DIV = 'Div';
    public const ASIDE = 'Aside';                          // PDF 2.0
    public const SECT = 'Sect';
    public const NON_STRUCT = 'NonStruct';
    public const PRIVATE_ = 'Private';
    public const TITLE = 'Title';                          // PDF 2.0
    public const ART = 'Art';
    public const BLOCK_QUOTE = 'BlockQuote';
    public const CAPTION = 'Caption';
    public const TOC = 'TOC';
    public const TOCI = 'TOCI';
    public const INDEX = 'Index';

    // Paragraph-like (block level)
    public const P = 'P';
    public const H = 'H';
    public const H1 = 'H1';
    public const H2 = 'H2';
    public const H3 = 'H3';
    public const H4 = 'H4';
    public const H5 = 'H5';
    public const H6 = 'H6';

    // Lists
    public const L = 'L';
    public const LI = 'LI';
    public const LBL = 'Lbl';
    public const L_BODY = 'LBody';

    // Tables
    public const TABLE = 'Table';
    public const TR = 'TR';
    public const TH = 'TH';
    public const TD = 'TD';
    public const THEAD = 'THead';                          // PDF 2.0
    public const TBODY = 'TBody';                          // PDF 2.0
    public const TFOOT = 'TFoot';                          // PDF 2.0

    // Inline
    public const SPAN = 'Span';
    public const QUOTE = 'Quote';
    public const NOTE = 'Note';
    public const REFERENCE = 'Reference';
    public const BIB_ENTRY = 'BibEntry';
    public const CODE = 'Code';
    public const LINK = 'Link';
    public const ANNOT = 'Annot';
    public const RUBY = 'Ruby';
    public const WARICHU = 'Warichu';
    public const F_E_NOTE = 'FENote';                      // PDF 2.0

    // Illustration
    public const FIGURE = 'Figure';
    public const FORMULA = 'Formula';
    public const FORM = 'Form';
    public const ARTIFACT = 'Artifact';                    // PDF 2.0

    /** Structure types introduced in PDF 2.0. */
    private const PDF_2_0_TYPES = [
        self::DOCUMENT_FRAGMENT,
        self::ASIDE,
        self::TITLE,
        self::THEAD,
        self::TBODY,
        self::TFOOT,
        self::F_E_NOTE,
        self::ARTIFACT,
    ];

    /**
     * Return the minimum PDF version required by the given structure type,
     * or null if the type is not a recognized standard type or has no
     * version constraint beyond PDF 1.0.
     */
    public static function minimumVersion(string $type): ?PdfVersion
    {
        if (in_array($type, self::PDF_2_0_TYPES, true)) {
            return PdfVersion::V2_0;
        }

        return null;
    }
}
