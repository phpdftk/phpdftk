<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Profile;

use Phpdftk\Pdf\Core\PdfVersion;

/**
 * Represents a PDF subset conformance profile (e.g. PDF/A-1b, PDF/X-4, PDF/UA-1).
 *
 * Each profile maps to a specific ISO standard and conformance level, defines
 * the minimum PDF version required, and provides the XMP identification
 * properties that must appear in the document metadata.
 */
interface ConformanceProfile
{
    /** Standard family name (e.g. 'PDF/A', 'PDF/X', 'PDF/UA'). */
    public function getFamily(): string;

    /** Conformance level label (e.g. '1b', 'X-4', 'UA-1'). */
    public function getLevel(): string;

    /** Minimum PDF version required by this profile. */
    public function getPdfVersion(): PdfVersion;

    /** XMP namespace URI for the identification schema. */
    public function getXmpNamespace(): string;

    /** XMP namespace prefix (e.g. 'pdfaid', 'pdfxid'). */
    public function getXmpPrefix(): string;

    /**
     * XMP identification properties for this profile.
     *
     * Keys are the local property names within the identification namespace
     * (e.g. 'part', 'conformance'). Values are the property values.
     *
     * @return array<string, string>
     */
    public function getXmpProperties(): array;
}
