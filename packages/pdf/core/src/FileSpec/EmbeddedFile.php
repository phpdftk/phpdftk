<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\FileSpec;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Embedded file stream (/Type /EmbeddedFile) — ISO 32000-2 §7.11.4.
 *
 * Carries the bytes of a file embedded in the PDF. The /Subtype holds the
 * MIME type (e.g. `application/xml`) with solidus characters escaped as
 * `#2F` per the /Name escaping rules.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class EmbeddedFile extends PdfStream
{
    public const PDF_TYPE = 'EmbeddedFile';

    public ?PdfName $subtype = null;                 // /Subtype (MIME)
    public ?EmbeddedFileParams $params = null;       // /Params

    public function __construct(string $data = '', ?string $mimeType = null)
    {
        parent::__construct(new PdfDictionary(), $data);
        if ($mimeType !== null) {
            $this->subtype = new PdfName($mimeType);
        }
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        if ($this->subtype !== null) {
            $this->dictionary->set('Subtype', $this->subtype);
        }
        if ($this->params !== null) {
            $this->dictionary->set('Params', $this->params);
        }

        return parent::toPdf();
    }
}
