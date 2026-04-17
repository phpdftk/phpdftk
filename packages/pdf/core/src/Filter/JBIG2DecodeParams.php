<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Filter;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\Serializable;

/**
 * JBIG2Decode parameters — ISO 32000-2 §7.4.7, Table 12.
 */
class JBIG2DecodeParams implements Serializable
{
    public ?PdfReference $jbig2Globals = null;  // /JBIG2Globals

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->jbig2Globals !== null) {
            $dict->set('JBIG2Globals', $this->jbig2Globals);
        }
        return $dict->toPdf();
    }
}
