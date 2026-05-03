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
 * PDF Object Reference dictionary (ISO 32000-2 Table 326).
 *
 * References a PDF object (such as an annotation or XObject) from
 * the structure tree.
 *
 * Example:
 *   $objRef = new ObjectRef();
 *   $objRef->pg = new PdfReference($page->objectNumber);
 *   $objRef->obj = new PdfReference($annotation->objectNumber);
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class ObjectRef extends PdfObject
{
    public const PDF_TYPE = 'OBJR';

    public ?PdfReference $pg = null;   // /Pg - page
    public ?PdfReference $obj = null;  // /Obj - referenced object

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->pg !== null) {
            $dict->set('Pg', $this->pg);
        }
        if ($this->obj !== null) {
            $dict->set('Obj', $this->obj);
        }

        return $dict->toPdf();
    }
}
