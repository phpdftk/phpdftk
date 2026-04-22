<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\XObject;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\DeprecatedPdfFeature;

/**
 * PostScript XObject (/Subtype /PS).
 * ISO 32000-2 section 8.8.2. Deprecated since PDF 1.7.1.
 * The stream data contains the PostScript language code.
 */
#[DeprecatedPdfFeature(since: '1.7.1')]
class PostScriptXObject extends PdfStream
{
    public const PDF_TYPE    = 'XObject';
    public const PDF_SUBTYPE = 'PS';

    public ?PdfReference $level1 = null; // /Level1 - Level 1 PostScript fallback stream

    public function __construct(string $data = '')
    {
        parent::__construct(new PdfDictionary(), $data);
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        $this->dictionary->set('Subtype', new PdfName(self::PDF_SUBTYPE));

        if ($this->level1 !== null) {
            $this->dictionary->set('Level1', $this->level1);
        }

        return parent::toPdf();
    }
}
