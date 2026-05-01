<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Security;

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
 * Encryption dictionary (/Type /Encrypt) — ISO 32000-2 §7.6.
 *
 * This is the **object-model** representation only. It serializes to a
 * spec-compliant dictionary but is not wired into `PdfWriter`'s trailer
 * or into per-object string/stream encryption — callers implementing
 * actual encryption are responsible for driving the {@see
 * \ApprLabs\Crypt\PdfKeyDerivation} primitives and patching the writer
 * output themselves. This distinction is deliberate so the object model
 * stays usable for libraries that want to emit encryption metadata
 * without phpdftk taking over the crypto pipeline.
 *
 * Covers both the Standard security handler (Table 21) and the
 * Public-key handler (Table 26), plus the shared crypt filter entries.
 */
#[RequiresPdfVersion(PdfVersion::V1_1)]
class EncryptDictionary extends PdfObject
{
    public const PDF_TYPE = 'Encrypt';

    // ---------- Common entries (Table 20) ----------
    public PdfName $filter;                   // /Filter     required (e.g. Standard, Adobe.PubSec)
    public ?PdfName $subFilter = null;        // /SubFilter
    public int $v;                            // /V          algorithm version
    public ?int $length = null;               // /Length     key length in bits (multiple of 8)
    public ?PdfDictionary $cf = null;         // /CF         crypt filter dict
    public ?PdfName $stmF = null;             // /StmF
    public ?PdfName $strF = null;             // /StrF
    public ?PdfName $eff = null;              // /EFF        filter for embedded files

    // ---------- Standard handler (Table 21) ----------
    public ?int $r = null;                    // /R          revision (2..6)
    public ?PdfString $o = null;              // /O          owner password string
    public ?PdfString $u = null;              // /U          user password string
    public ?PdfString $oe = null;             // /OE         owner encryption key (R=6)
    public ?PdfString $ue = null;             // /UE         user encryption key (R=6)
    public ?int $p = null;                    // /P          permissions bitfield
    public ?PdfString $perms = null;          // /Perms      encrypted perms (R=6)
    public ?bool $encryptMetadata = null;     // /EncryptMetadata

    // ---------- Public-key handler (Table 26) ----------
    public ?PdfArray $recipients = null;      // /Recipients

    public function __construct(string $filter = 'Standard', int $v = 2)
    {
        $this->filter = new PdfName($filter);
        $this->v = $v;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Filter', $this->filter);
        if ($this->subFilter !== null) {
            $dict->set('SubFilter', $this->subFilter);
        }
        $dict->set('V', new PdfNumber($this->v));
        if ($this->length !== null) {
            $dict->set('Length', new PdfNumber($this->length));
        }
        if ($this->cf !== null) {
            $dict->set('CF', $this->cf);
        }
        if ($this->stmF !== null) {
            $dict->set('StmF', $this->stmF);
        }
        if ($this->strF !== null) {
            $dict->set('StrF', $this->strF);
        }
        if ($this->eff !== null) {
            $dict->set('EFF', $this->eff);
        }
        if ($this->r !== null) {
            $dict->set('R', new PdfNumber($this->r));
        }
        if ($this->o !== null) {
            $dict->set('O', $this->o);
        }
        if ($this->u !== null) {
            $dict->set('U', $this->u);
        }
        if ($this->oe !== null) {
            $dict->set('OE', $this->oe);
        }
        if ($this->ue !== null) {
            $dict->set('UE', $this->ue);
        }
        if ($this->p !== null) {
            $dict->set('P', new PdfNumber($this->p));
        }
        if ($this->perms !== null) {
            $dict->set('Perms', $this->perms);
        }
        if ($this->encryptMetadata !== null) {
            $dict->set('EncryptMetadata', new PdfBoolean($this->encryptMetadata));
        }
        if ($this->recipients !== null) {
            $dict->set('Recipients', $this->recipients);
        }
        return $dict->toPdf();
    }
}
