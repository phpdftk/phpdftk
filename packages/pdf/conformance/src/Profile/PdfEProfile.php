<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Profile;

use ApprLabs\Pdf\Core\PdfVersion;

/**
 * PDF/E conformance profiles (ISO 24517).
 *
 * PDF/E is designed for engineering document exchange, supporting
 * 3D content, geospatial data, and other engineering workflows.
 *
 * PDF/E-1 (ISO 24517-1): based on PDF 1.6
 */
enum PdfEProfile: string implements ConformanceProfile
{
    case E1 = 'E-1';

    public function getFamily(): string
    {
        return 'PDF/E';
    }

    public function getLevel(): string
    {
        return $this->value;
    }

    public function getPdfVersion(): PdfVersion
    {
        return PdfVersion::V1_6;
    }

    public function getXmpNamespace(): string
    {
        return 'http://www.aiim.org/pdfe/ns/id/';
    }

    public function getXmpPrefix(): string
    {
        return 'pdfeid';
    }

    public function getXmpProperties(): array
    {
        return ['part' => '1'];
    }
}
