<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Multimedia;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfString;

/**
 * Media clip object (/Type /MediaClip) — ISO 32000-2 §13.2.4.
 *
 * Abstract base for MediaClipData (MCD) and MediaClipSection (MCS).
 */
abstract class MediaClip extends PdfObject
{
    public const PDF_TYPE = 'MediaClip';

    public ?PdfString $n = null;   // /N  name

    /** Returns the /S (subtype) value: "MCD" or "MCS". */
    abstract public function getMediaClipSubtype(): string;

    protected function baseDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', new PdfName($this->getMediaClipSubtype()));
        if ($this->n !== null) {
            $dict->set('N', $this->n);
        }
        return $dict;
    }
}
