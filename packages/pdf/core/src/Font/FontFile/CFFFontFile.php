<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Font\FontFile;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * CFF / Type 1C / CIDFontType0C font program stream — ISO 32000-2 §9.9,
 * Table 124. Referenced from `FontDescriptor::$fontFile3`. The /Subtype
 * entry distinguishes the flavor:
 *
 *   /Type1C         — bare CFF font program (Type 1 compatible)
 *   /CIDFontType0C  — CFF font program for CID-keyed fonts
 *   /OpenType       — OpenType font file (CFF or TTF outlines)
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
class CFFFontFile extends PdfStream
{
    public PdfName $subtype;
    public ?PdfReference $metadata = null;

    public function __construct(string $bytes, string $subtype = 'Type1C')
    {
        parent::__construct(new PdfDictionary(), $bytes);
        $this->subtype = new PdfName($subtype);
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Subtype', $this->subtype);
        if ($this->metadata !== null) {
            $this->dictionary->set('Metadata', $this->metadata);
        }
        return parent::toPdf();
    }
}
