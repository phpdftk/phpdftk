<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\ThreeD;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * 3D lighting scheme dictionary (/Type /3DLightingScheme) —
 * ISO 32000-2 §13.6.7.
 *
 * Subtype is one of the preset schemes: Artwork, None, White, Day, Night,
 * Hard, Primary, Blue, Red, Cube, CAD, Headlamp.
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
class ThreeDLightingScheme extends PdfObject
{
    public const PDF_TYPE = '3DLightingScheme';

    public PdfName $subtype;

    public function __construct(string $subtype = 'Artwork')
    {
        $this->subtype = new PdfName($subtype);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('Subtype', $this->subtype);
        return $dict->toPdf();
    }
}
