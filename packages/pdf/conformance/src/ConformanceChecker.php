<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance;

use ApprLabs\Pdf\Conformance\Inspection\ReaderDocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceResult;
use ApprLabs\Pdf\Conformance\Validator\ConformanceValidator;
use ApprLabs\Pdf\Reader\PdfReader;

/**
 * High-level conformance checker for existing PDFs.
 *
 * Loads a PDF via PdfReader and validates it against one or more
 * conformance profiles, returning structured results.
 *
 * Usage:
 *   $results = ConformanceChecker::open('document.pdf')
 *       ->checkProfile(PdfAProfile::A1b);
 *
 *   $results = ConformanceChecker::openString($bytes)
 *       ->checkProfiles([PdfAProfile::A2b, PdfUaProfile::UA1]);
 */
final class ConformanceChecker
{
    private readonly ReaderDocumentInspector $inspector;
    private readonly ConformanceValidator $validator;

    private function __construct(
        private readonly PdfReader $reader,
    ) {
        $this->inspector = new ReaderDocumentInspector($reader);
        $this->validator = new ConformanceValidator();
    }

    /**
     * Open a PDF file for conformance checking.
     */
    public static function open(string $path, string $password = ''): self
    {
        $reader = PdfReader::fromFile($path, $password);
        return new self($reader);
    }

    /**
     * Open a PDF from a byte string for conformance checking.
     */
    public static function openString(string $bytes, string $password = ''): self
    {
        $reader = PdfReader::fromString($bytes, $password);
        return new self($reader);
    }

    /**
     * Check the PDF against a single conformance profile.
     */
    public function checkProfile(ConformanceProfile $profile): ConformanceResult
    {
        return $this->validator->validate($this->inspector, $profile);
    }

    /**
     * Check the PDF against multiple conformance profiles.
     *
     * @param ConformanceProfile[] $profiles
     * @return list<ConformanceResult>
     */
    public function checkProfiles(array $profiles): array
    {
        return $this->validator->validateAll($this->inspector, $profiles);
    }

    /**
     * Get the underlying PdfReader for additional inspection.
     */
    public function getReader(): PdfReader
    {
        return $this->reader;
    }
}
