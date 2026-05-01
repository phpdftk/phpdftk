<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Font;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * CMap stream (/Type /CMap) — ISO 32000-2 §9.7.5.4.
 *
 * Maps character codes (bytes or multi-byte sequences) to CIDs for a
 * CIDFont, or (in ToUnicode form) to Unicode code points. The stream
 * body holds the CMap program text.
 */
#[RequiresPdfVersion(PdfVersion::V1_4)]
class CMapStream extends PdfStream
{
    public const PDF_TYPE = 'CMap';

    public ?PdfName $cMapName = null;                       // /CMapName
    public ?CIDSystemInfo $cidSystemInfo = null;            // /CIDSystemInfo
    public ?int $wMode = null;                              // /WMode 0=horizontal 1=vertical
    public PdfName|PdfReference|null $useCMap = null;       // /UseCMap

    public function __construct(string $cMapProgram = '')
    {
        parent::__construct(new PdfDictionary(), $cMapProgram);
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        if ($this->cMapName !== null) {
            $this->dictionary->set('CMapName', $this->cMapName);
        }
        if ($this->cidSystemInfo !== null) {
            $this->dictionary->set('CIDSystemInfo', $this->cidSystemInfo);
        }
        if ($this->wMode !== null) {
            $this->dictionary->set('WMode', new PdfNumber($this->wMode));
        }
        if ($this->useCMap !== null) {
            $this->dictionary->set('UseCMap', $this->useCMap);
        }
        return parent::toPdf();
    }
}
