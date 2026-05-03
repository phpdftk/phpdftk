<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Document parts root (/Type /DPartRoot) — ISO 32000-2 §14.12.
 *
 * Referenced from `Catalog::$dPartRoot`. Defines the document-part
 * hierarchy and the names used for DPart metadata (e.g., for PDF/VT
 * variable-data printing).
 */
#[RequiresPdfVersion(PdfVersion::V2_0)]
class DPartRoot extends PdfObject
{
    public const PDF_TYPE = 'DPartRoot';

    public PdfReference $dPartRootNode;           // /DPartRootNode - required
    public ?PdfArray $nodeNameList = null;        // /NodeNameList
    public ?int $recordLevel = null;              // /RecordLevel
    public ?PdfArray $recordPropertiesList = null; // /RecordPropertiesList

    public function __construct(PdfReference $dPartRootNode)
    {
        $this->dPartRootNode = $dPartRootNode;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('DPartRootNode', $this->dPartRootNode);
        if ($this->nodeNameList !== null) {
            $dict->set('NodeNameList', $this->nodeNameList);
        }
        if ($this->recordLevel !== null) {
            $dict->set('RecordLevel', new PdfNumber($this->recordLevel));
        }
        if ($this->recordPropertiesList !== null) {
            $dict->set('RecordPropertiesList', $this->recordPropertiesList);
        }
        return $dict->toPdf();
    }
}
