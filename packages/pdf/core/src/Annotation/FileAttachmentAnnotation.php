<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * FileAttachment annotation (/Subtype /FileAttachment).
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class FileAttachmentAnnotation extends MarkupAnnotation
{
    public ?PdfReference $fs = null;  // /FS - file specification
    public ?PdfName $name = null;     // /Name - icon name

    public function getSubtype(): string
    {
        return 'FileAttachment';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->fs !== null) {
            $dict->set('FS', $this->fs);
        }
        if ($this->name !== null) {
            $dict->set('Name', $this->name);
        }

        return $dict->toPdf();
    }
}
