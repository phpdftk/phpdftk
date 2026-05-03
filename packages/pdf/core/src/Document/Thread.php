<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Article thread dictionary (ISO 32000-2 Table 160).
 *
 * Represents an article thread, linking together a sequence of beads
 * that form a logical reading order within the document.
 */
#[RequiresPdfVersion(PdfVersion::V1_1)]
class Thread extends PdfObject
{
    public const PDF_TYPE = 'Thread';

    public ?PdfDictionary $i = null;   // /I - thread info dict
    public ?PdfReference $f = null;    // /F - first bead

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->i !== null) {
            $dict->set('I', $this->i);
        }
        if ($this->f !== null) {
            $dict->set('F', $this->f);
        }

        return $dict->toPdf();
    }
}
