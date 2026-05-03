<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Form;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Signature field lock dictionary — ISO 32000-2 §12.7.5.5, Table 233.
 *
 * Referenced from `SignatureField::$lock`. When the signature is
 * applied, the viewer locks the listed fields (/Action + /Fields) and
 * optionally applies `/P` (MDP permission level: 1=no changes,
 * 2=form fill+sign, 3=everything above plus annotation edits).
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class SigFieldLock extends PdfObject
{
    public const PDF_TYPE = 'SigFieldLock';

    public PdfName $action;               // /Action  All|Include|Exclude
    public ?PdfArray $fields = null;      // /Fields  required unless Action=All
    public ?int $p = null;                // /P       permission level

    public function __construct(string $action = 'All', ?PdfArray $fields = null)
    {
        $this->action = new PdfName($action);
        $this->fields = $fields;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('Action', $this->action);
        if ($this->fields !== null) {
            $dict->set('Fields', $this->fields);
        }
        if ($this->p !== null) {
            $dict->set('P', new PdfNumber($this->p));
        }
        return $dict->toPdf();
    }
}
