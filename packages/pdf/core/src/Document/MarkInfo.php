<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * PDF MarkInfo dictionary.
 *
 * Indicates whether and how the document is structured for accessibility.
 * Assigned to /MarkInfo in the document Catalog.
 *
 * Example:
 *   $markInfo = new MarkInfo();
 *   $markInfo->marked = true;
 *   $catalog->markInfo = $markInfo;
 */
class MarkInfo implements Serializable
{
    public ?bool $marked = null;          // /Marked - document contains marked content
    #[RequiresPdfVersion(PdfVersion::V1_6)]
    public ?bool $userProperties = null;  // /UserProperties - user properties attached to marked content (PDF 1.6+)
    #[RequiresPdfVersion(PdfVersion::V1_6)]
    public ?bool $suspects = null;        // /Suspects - structure may contain suspects (PDF 1.6+)

    public function toPdf(): string
    {
        $dict = new PdfDictionary();

        if ($this->marked !== null) {
            $dict->set('Marked', new PdfBoolean($this->marked));
        }
        if ($this->userProperties !== null) {
            $dict->set('UserProperties', new PdfBoolean($this->userProperties));
        }
        if ($this->suspects !== null) {
            $dict->set('Suspects', new PdfBoolean($this->suspects));
        }

        return $dict->toPdf();
    }
}
