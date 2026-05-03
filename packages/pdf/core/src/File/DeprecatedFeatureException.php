<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\File;

use Phpdftk\Pdf\Core\DeprecatedPdfFeature;
use Phpdftk\Pdf\Core\PdfVersion;

/**
 * Thrown when strict deprecation mode (or ceiling mode) is active and a
 * feature marked with {@see DeprecatedPdfFeature::$removedIn} is registered
 * at or above its removal version.
 */
class DeprecatedFeatureException extends \RuntimeException
{
    public function __construct(
        public readonly string $objectClass,
        public readonly DeprecatedPdfFeature $deprecation,
        public readonly PdfVersion $targetVersion,
    ) {
        parent::__construct(sprintf(
            '%s was removed in PDF %s (deprecated since %s%s). '
            . 'Cannot include in a PDF %s document.',
            $objectClass,
            $deprecation->removedIn,
            $deprecation->since,
            $deprecation->replacement ? "; replacement: {$deprecation->replacement}" : '',
            $targetVersion->value,
        ));
    }
}
