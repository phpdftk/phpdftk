<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;

/**
 * PDF/A clause 6.6: Encryption is prohibited.
 */
final class EncryptionConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        if ($inspector->hasEncryption()) {
            return [new ConformanceViolation(
                clause: '6.6',
                message: 'Encryption is prohibited in ' . $profile->getFamily() . '-' . $profile->getLevel(),
                severity: ViolationSeverity::Error,
            )];
        }

        return [];
    }
}
