<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Form;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Seed value dictionary — ISO 32000-2 §12.7.5.5, Table 234.
 *
 * Seeds constrain and configure how a signature field is signed:
 * acceptable filter/subfilter, digest method, legal attestation,
 * whether a timestamp is required, and whether the viewer is expected
 * to lock the document after signing.
 *
 * Referenced from `SignatureField::$sv`.
 */
class SeedValueDictionary extends PdfObject
{
    public const PDF_TYPE = 'SV';

    public ?int $ff = null;                      // /Ff  - flags
    public ?PdfName $filter = null;              // /Filter
    public ?PdfArray $subFilter = null;          // /SubFilter  array of names
    public ?PdfArray $digestMethod = null;       // /DigestMethod  array of names
    public ?float $v = null;                     // /V   seed dict version
    public ?PdfDictionary $cert = null;          // /Cert certificate seed value dict
    public ?PdfArray $reasons = null;            // /Reasons  array of strings
    public ?PdfDictionary $mdp = null;           // /MDP
    public ?PdfDictionary $timeStamp = null;     // /TimeStamp  time-stamp server dict
    public ?PdfArray $legalAttestation = null;   // /LegalAttestation array of strings
    public ?bool $addRevInfo = null;             // /AddRevInfo
    #[RequiresPdfVersion(PdfVersion::V2_0)]
    public ?bool $lockDocument = null;           // /LockDocument (PDF 2.0)
    #[RequiresPdfVersion(PdfVersion::V2_0)]
    public ?PdfString $appearanceFilter = null;  // /AppearanceFilter (PDF 2.0)

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        if ($this->ff !== null) {
            $dict->set('Ff', new PdfNumber($this->ff));
        }
        if ($this->filter !== null) {
            $dict->set('Filter', $this->filter);
        }
        if ($this->subFilter !== null) {
            $dict->set('SubFilter', $this->subFilter);
        }
        if ($this->digestMethod !== null) {
            $dict->set('DigestMethod', $this->digestMethod);
        }
        if ($this->v !== null) {
            $dict->set('V', new PdfNumber($this->v));
        }
        if ($this->cert !== null) {
            $dict->set('Cert', $this->cert);
        }
        if ($this->reasons !== null) {
            $dict->set('Reasons', $this->reasons);
        }
        if ($this->mdp !== null) {
            $dict->set('MDP', $this->mdp);
        }
        if ($this->timeStamp !== null) {
            $dict->set('TimeStamp', $this->timeStamp);
        }
        if ($this->legalAttestation !== null) {
            $dict->set('LegalAttestation', $this->legalAttestation);
        }
        if ($this->addRevInfo !== null) {
            $dict->set('AddRevInfo', new PdfBoolean($this->addRevInfo));
        }
        if ($this->lockDocument !== null) {
            $dict->set('LockDocument', new PdfBoolean($this->lockDocument));
        }
        if ($this->appearanceFilter !== null) {
            $dict->set('AppearanceFilter', $this->appearanceFilter);
        }
        return $dict->toPdf();
    }
}
