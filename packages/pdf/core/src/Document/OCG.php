<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * PDF Optional Content Group (ISO 32000-2 Table 96).
 *
 * Represents a layer in the PDF document that can be shown or hidden.
 *
 * Example:
 *   $ocg = new OCG('Watermark');
 *   $writer->register($ocg);
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class OCG extends PdfObject
{
    public const PDF_TYPE = 'OCG';

    public PdfName $name;              // /Name - required
    public ?PdfName $intent = null;    // /Intent
    public ?PdfDictionary $usage = null; // /Usage

    public function __construct(string $name)
    {
        $this->name = new PdfName($name);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('Name', $this->name);

        if ($this->intent !== null) {
            $dict->set('Intent', $this->intent);
        }
        if ($this->usage !== null) {
            $dict->set('Usage', $this->usage);
        }

        return $dict->toPdf();
    }
}
