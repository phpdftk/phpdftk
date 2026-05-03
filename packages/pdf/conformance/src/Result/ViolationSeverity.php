<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Result;

/**
 * Severity levels for conformance violations.
 *
 * Error means the document is non-compliant and will fail strict validation.
 * Warning is advisory -- the document may still be accepted by some validators
 * but the practice is discouraged by the relevant ISO standard.
 */
enum ViolationSeverity: string
{
    /** Hard failure — the document does not conform. */
    case Error = 'error';

    /** Advisory — potential conformance issue that may pass some validators. */
    case Warning = 'warning';
}
