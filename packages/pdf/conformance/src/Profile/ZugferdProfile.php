<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Profile;

use ApprLabs\Pdf\Core\PdfVersion;

/**
 * ZUGFeRD / Factur-X conformance profiles.
 *
 * E-invoicing standard built on PDF/A-3 with embedded XML invoice.
 * Each level specifies increasing invoice data requirements.
 *
 * @see https://www.ferd-net.de/
 * @see https://fnfe-mpe.org/factur-x/
 */
enum ZugferdProfile: string implements ConformanceProfile
{
    case MINIMUM   = 'MINIMUM';
    case BASIC_WL  = 'BASIC WL';
    case BASIC     = 'BASIC';
    case EN16931   = 'EN 16931';
    case EXTENDED  = 'EXTENDED';
    case XRECHNUNG = 'XRECHNUNG';

    public function getFamily(): string
    {
        return 'Factur-X';
    }

    public function getLevel(): string
    {
        return $this->value;
    }

    public function getPdfVersion(): PdfVersion
    {
        return PdfVersion::V1_7; // PDF/A-3b base
    }

    public function getXmpNamespace(): string
    {
        return 'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#';
    }

    public function getXmpPrefix(): string
    {
        return 'fx';
    }

    public function getXmpProperties(): array
    {
        return [
            'ConformanceLevel' => $this->value,
            'DocumentType' => 'INVOICE',
            'DocumentFileName' => 'factur-x.xml',
        ];
    }

    /** The base PDF/A profile required for ZUGFeRD. */
    public function getBaseProfile(): PdfAProfile
    {
        return PdfAProfile::A3b;
    }
}
