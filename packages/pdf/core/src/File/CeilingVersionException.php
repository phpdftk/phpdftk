<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\File;

use Phpdftk\Pdf\Core\PdfVersion;

/**
 * Thrown when ceiling version mode is active and an object's class-level
 * version requirement exceeds the ceiling. Property-level incompatibilities
 * are stripped silently; class-level ones cannot be.
 */
class CeilingVersionException extends \RuntimeException
{
    public function __construct(
        public readonly string $objectClass,
        public readonly PdfVersion $requiredVersion,
        public readonly PdfVersion $ceilingVersion,
    ) {
        parent::__construct(sprintf(
            '%s requires PDF %s, which exceeds the ceiling version %s. '
            . 'This object cannot be included — remove it or raise the ceiling.',
            $objectClass,
            $requiredVersion->value,
            $ceilingVersion->value,
        ));
    }
}
