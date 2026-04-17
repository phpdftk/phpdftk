<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * PDF Structure Tree Root (ISO 32000-2 Table 323).
 *
 * Root of the structure tree for tagged PDF documents.
 *
 * Example:
 *   $root = new StructTreeRoot();
 *   $root->k = new PdfReference($rootElem->objectNumber);
 *   $root->roleMap = new PdfDictionary(['Figure' => new PdfName('Span')]);
 */
class StructTreeRoot extends PdfObject
{
    public const PDF_TYPE = 'StructTreeRoot';

    public PdfReference|PdfArray|null $k = null;     // /K - structure element(s)
    public ?PdfReference $idTree = null;              // /IDTree - name tree of IDs
    public ?PdfReference $parentTree = null;          // /ParentTree - number tree
    public ?int $parentTreeNextKey = null;            // /ParentTreeNextKey
    public RoleMap|PdfDictionary|null $roleMap = null;    // /RoleMap
    public ClassMap|PdfDictionary|null $classMap = null;  // /ClassMap

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->k !== null) {
            $dict->set('K', $this->k);
        }
        if ($this->idTree !== null) {
            $dict->set('IDTree', $this->idTree);
        }
        if ($this->parentTree !== null) {
            $dict->set('ParentTree', $this->parentTree);
        }
        if ($this->parentTreeNextKey !== null) {
            $dict->set('ParentTreeNextKey', new PdfNumber($this->parentTreeNextKey));
        }
        if ($this->roleMap !== null) {
            $dict->set('RoleMap', $this->roleMap);
        }
        if ($this->classMap !== null) {
            $dict->set('ClassMap', $this->classMap);
        }

        return $dict->toPdf();
    }
}
