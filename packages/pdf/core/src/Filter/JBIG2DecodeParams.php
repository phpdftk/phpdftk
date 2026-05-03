<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Filter;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * JBIG2Decode parameters — ISO 32000-2 §7.4.7, Table 12.
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
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
