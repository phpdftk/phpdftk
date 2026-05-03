<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * Box color information dictionary — ISO 32000-2 §14.11.2.2, Table 336.
 *
 * Attaches {@see BoxStyle} display hints to the crop/bleed/trim/art
 * boxes of a page. Assigned inline to `Page::$boxColorInfo` /
 * `PageTree::$boxColorInfo`.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class BoxColorInfo implements Serializable
{
    public ?BoxStyle $cropBox = null;    // /CropBox
    public ?BoxStyle $bleedBox = null;   // /BleedBox
    public ?BoxStyle $trimBox = null;    // /TrimBox
    public ?BoxStyle $artBox = null;     // /ArtBox

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->cropBox !== null) {
            $dict->set('CropBox', $this->cropBox);
        }
        if ($this->bleedBox !== null) {
            $dict->set('BleedBox', $this->bleedBox);
        }
        if ($this->trimBox !== null) {
            $dict->set('TrimBox', $this->trimBox);
        }
        if ($this->artBox !== null) {
            $dict->set('ArtBox', $this->artBox);
        }
        return $dict->toPdf();
    }
}
