<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

/**
 * PDF Output Intent dictionary (ISO 32000-2 Table 365).
 *
 * Describes the intended output conditions for a document, required
 * for PDF/X compliance.
 */
class OutputIntent extends PdfObject
{
    public const PDF_TYPE = 'OutputIntent';

    public PdfName $s;                                // /S - required subtype
    public ?PdfString $outputCondition = null;        // /OutputCondition
    public PdfString $outputConditionIdentifier;      // /OutputConditionIdentifier - required
    public ?PdfString $registryName = null;           // /RegistryName
    public ?PdfString $info = null;                   // /Info
    public ?PdfReference $destOutputProfile = null;   // /DestOutputProfile - ICC profile stream
    public ?PdfReference $destOutputProfileRef = null; // /DestOutputProfileRef - external ICC ref (PDF/A-3+)
    public ?PdfDictionary $mixingHints = null;         // /MixingHints
    public ?PdfDictionary $spectralData = null;        // /SpectralData

    public function __construct(string $subtype, string $outputConditionIdentifier)
    {
        $this->s = new PdfName($subtype);
        $this->outputConditionIdentifier = new PdfString($outputConditionIdentifier);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', $this->s);
        $dict->set('OutputConditionIdentifier', $this->outputConditionIdentifier);

        if ($this->outputCondition !== null) {
            $dict->set('OutputCondition', $this->outputCondition);
        }
        if ($this->registryName !== null) {
            $dict->set('RegistryName', $this->registryName);
        }
        if ($this->info !== null) {
            $dict->set('Info', $this->info);
        }
        if ($this->destOutputProfile !== null) {
            $dict->set('DestOutputProfile', $this->destOutputProfile);
        }
        if ($this->destOutputProfileRef !== null) {
            $dict->set('DestOutputProfileRef', $this->destOutputProfileRef);
        }
        if ($this->mixingHints !== null) {
            $dict->set('MixingHints', $this->mixingHints);
        }
        if ($this->spectralData !== null) {
            $dict->set('SpectralData', $this->spectralData);
        }

        return $dict->toPdf();
    }
}
