<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Profile;

use Phpdftk\Pdf\Core\PdfVersion;

/**
 * PDF/VT conformance profiles (ISO 16612).
 *
 * PDF/VT is designed for variable and transactional printing workflows.
 * All levels build on PDF/X-4 and add DPartRoot requirements.
 *
 * PDF/VT-1 (ISO 16612-2): single-file exchange, based on PDF/X-4 + PDF 2.0
 * PDF/VT-2 (ISO 16612-2): multi-file streaming, based on PDF/X-4 + PDF 2.0
 * PDF/VT-2s (ISO 16612-3): streamed subset, based on PDF/X-4 + PDF 2.0
 */
enum PdfVtProfile: string implements ConformanceProfile
{
    case VT1  = 'VT-1';
    case VT2  = 'VT-2';
    case VT2s = 'VT-2s';

    public function getFamily(): string
    {
        return 'PDF/VT';
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
        return 'http://www.npes.org/pdfvt/ns/id/';
    }

    public function getXmpPrefix(): string
    {
        return 'pdfvtid';
    }

    public function getXmpProperties(): array
    {
        return ['GTS_PDFVTVersion' => match ($this) {
            self::VT1  => 'PDF/VT-1',
            self::VT2  => 'PDF/VT-2',
            self::VT2s => 'PDF/VT-2s',
        }];
    }
}
