<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Signature;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;

/**
 * Abstract base for signature transform parameter dictionaries —
 * ISO 32000-2 §12.8.2.
 *
 * Subclasses: DocMDPTransformParams, FieldMDPTransformParams,
 * UR3TransformParams. Identity transform has no params.
 */
abstract class TransformParams extends PdfObject
{
    public const PDF_TYPE = 'TransformParams';

    protected function baseDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        return $dict;
    }
}
