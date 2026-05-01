<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Profile;

use ApprLabs\Pdf\Core\PdfVersion;

/**
 * PDF/R conformance profiles (ISO 23504).
 *
 * PDF/R is designed for raster image transport — scanned document
 * exchange with minimal structural requirements.
 *
 * PDF/R-1 (ISO 23504-1): based on PDF 2.0
 */
enum PdfRProfile: string implements ConformanceProfile
{
    case R1 = 'R-1';

    public function getFamily(): string
    {
        return 'PDF/R';
    }

    public function getLevel(): string
    {
        return $this->value;
    }

    public function getPdfVersion(): PdfVersion
    {
        return PdfVersion::V2_0;
    }

    public function getXmpNamespace(): string
    {
        return 'http://www.pdfa.org/pdfr/ns/id/';
    }

    public function getXmpPrefix(): string
    {
        return 'pdfrid';
    }

    public function getXmpProperties(): array
    {
        return ['part' => '1'];
    }
}
