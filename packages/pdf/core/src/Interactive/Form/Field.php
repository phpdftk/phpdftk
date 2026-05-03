<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Form;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;

/**
 * Base class for all interactive form field types.
 * Maps to the common entries in the Field dictionary.
 */
abstract class Field extends PdfObject
{
    public ?PdfName $ft = null;        // /FT - field type (Btn/Tx/Ch/Sig)
    public ?PdfReference $parent = null; // /Parent
    /** @var array<int, PdfReference> */
    public array $kids = [];            // /Kids
    public ?PdfString $t = null;        // /T - partial name - required
    public ?PdfString $tu = null;       // /TU - user name
    public ?PdfString $tm = null;       // /TM - mapping name
    public int $ff = 0;                 // /Ff - field flags
    public mixed $v = null;             // /V - value
    public mixed $dv = null;            // /DV - default value
    public ?PdfDictionary $aa = null;   // /AA - additional actions
    public ?PdfString $da = null;       // /DA - default appearance (variable text, §12.7.4.3)
    public ?int $q = null;              // /Q  - quadding / justification (variable text)
    public ?PdfString $ds = null;       // /DS - default style (rich-text variable text)
    public ?PdfString $rv = null;       // /RV - rich-text value (variable text)

    /**
     * Build the common field dictionary entries.
     */
    protected function buildFieldDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();

        if ($this->ft !== null) {
            $dict->set('FT', $this->ft);
        }
        if ($this->parent !== null) {
            $dict->set('Parent', $this->parent);
        }
        if (!empty($this->kids)) {
            $dict->set('Kids', new PdfArray($this->kids));
        }
        if ($this->t !== null) {
            $dict->set('T', $this->t);
        }
        if ($this->tu !== null) {
            $dict->set('TU', $this->tu);
        }
        if ($this->tm !== null) {
            $dict->set('TM', $this->tm);
        }
        if ($this->ff !== 0) {
            $dict->set('Ff', new PdfNumber($this->ff));
        }
        if ($this->v !== null) {
            if ($this->v instanceof \Phpdftk\Pdf\Core\Serializable) {
                $dict->set('V', $this->v);
            } else {
                $dict->set('V', new PdfString((string) $this->v));
            }
        }
        if ($this->dv !== null) {
            if ($this->dv instanceof \Phpdftk\Pdf\Core\Serializable) {
                $dict->set('DV', $this->dv);
            } else {
                $dict->set('DV', new PdfString((string) $this->dv));
            }
        }
        if ($this->aa !== null) {
            $dict->set('AA', $this->aa);
        }
        if ($this->da !== null) {
            $dict->set('DA', $this->da);
        }
        if ($this->q !== null) {
            $dict->set('Q', new PdfNumber($this->q));
        }
        if ($this->ds !== null) {
            $dict->set('DS', $this->ds);
        }
        if ($this->rv !== null) {
            $dict->set('RV', $this->rv);
        }

        return $dict;
    }
}
