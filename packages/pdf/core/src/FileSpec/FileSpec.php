<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\FileSpec;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * File specification dictionary (/Type /Filespec) — ISO 32000-2 §7.11.3.
 *
 * Identifies an external file or an embedded file stream. Used by
 * FileAttachment annotations, embedded-file name trees, and the Launch,
 * ImportData, SubmitForm, and GoToE actions.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class FileSpec extends PdfObject
{
    public const PDF_TYPE = 'Filespec';

    public ?PdfName $fs = null;              // /FS  file system (URL, etc.)
    public ?PdfString $f = null;             // /F   file spec (PDFDocEncoded)
    public ?PdfString $uf = null;            // /UF  Unicode file spec
    public ?PdfString $dos = null;           // /DOS legacy
    public ?PdfString $mac = null;           // /Mac legacy
    public ?PdfString $unix = null;          // /Unix legacy
    public ?PdfArray $id = null;             // /ID  file identifier
    public ?bool $volatile = null;           // /V
    public ?PdfDictionary $ef = null;        // /EF  embedded files dict
    public ?PdfDictionary $rf = null;        // /RF  related files dict
    public ?PdfString $desc = null;          // /Desc description
    public ?PdfReference $ci = null;         // /CI  collection item dict
    #[RequiresPdfVersion(PdfVersion::V2_0)]
    public ?PdfName $afRelationship = null;  // /AFRelationship PDF 2.0

    public function __construct(?string $fileName = null)
    {
        if ($fileName !== null) {
            $this->f = new PdfString($fileName);
            $this->uf = new PdfString($fileName);
        }
    }

    /**
     * Attach an embedded file stream under the /F key of /EF.
     */
    public function attachEmbeddedFile(PdfReference $stream): void
    {
        $this->ef = new PdfDictionary(['F' => $stream, 'UF' => $stream]);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->fs !== null) {
            $dict->set('FS', $this->fs);
        }
        if ($this->f !== null) {
            $dict->set('F', $this->f);
        }
        if ($this->uf !== null) {
            $dict->set('UF', $this->uf);
        }
        if ($this->dos !== null) {
            $dict->set('DOS', $this->dos);
        }
        if ($this->mac !== null) {
            $dict->set('Mac', $this->mac);
        }
        if ($this->unix !== null) {
            $dict->set('Unix', $this->unix);
        }
        if ($this->id !== null) {
            $dict->set('ID', $this->id);
        }
        if ($this->volatile !== null) {
            $dict->set('V', new PdfBoolean($this->volatile));
        }
        if ($this->ef !== null) {
            $dict->set('EF', $this->ef);
        }
        if ($this->rf !== null) {
            $dict->set('RF', $this->rf);
        }
        if ($this->desc !== null) {
            $dict->set('Desc', $this->desc);
        }
        if ($this->ci !== null) {
            $dict->set('CI', $this->ci);
        }
        if ($this->afRelationship !== null) {
            $dict->set('AFRelationship', $this->afRelationship);
        }

        return $dict->toPdf();
    }
}
