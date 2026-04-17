<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Filter;

use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\Serializable;

/**
 * CCITTFaxDecode parameters — ISO 32000-2 §7.4.6, Table 11.
 */
class CCITTFaxDecodeParams implements Serializable
{
    public ?int $k = null;                      // /K
    public ?bool $endOfLine = null;             // /EndOfLine
    public ?bool $encodedByteAlign = null;      // /EncodedByteAlign
    public ?int $columns = null;                // /Columns
    public ?int $rows = null;                   // /Rows
    public ?bool $endOfBlock = null;            // /EndOfBlock
    public ?bool $blackIs1 = null;              // /BlackIs1
    public ?int $damagedRowsBeforeError = null; // /DamagedRowsBeforeError

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->k !== null) {
            $dict->set('K', new PdfNumber($this->k));
        }
        if ($this->endOfLine !== null) {
            $dict->set('EndOfLine', new PdfBoolean($this->endOfLine));
        }
        if ($this->encodedByteAlign !== null) {
            $dict->set('EncodedByteAlign', new PdfBoolean($this->encodedByteAlign));
        }
        if ($this->columns !== null) {
            $dict->set('Columns', new PdfNumber($this->columns));
        }
        if ($this->rows !== null) {
            $dict->set('Rows', new PdfNumber($this->rows));
        }
        if ($this->endOfBlock !== null) {
            $dict->set('EndOfBlock', new PdfBoolean($this->endOfBlock));
        }
        if ($this->blackIs1 !== null) {
            $dict->set('BlackIs1', new PdfBoolean($this->blackIs1));
        }
        if ($this->damagedRowsBeforeError !== null) {
            $dict->set('DamagedRowsBeforeError', new PdfNumber($this->damagedRowsBeforeError));
        }
        return $dict->toPdf();
    }
}
