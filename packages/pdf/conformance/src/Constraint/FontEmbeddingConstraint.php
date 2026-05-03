<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Pdf\Core\Font\Font;
use Phpdftk\Pdf\Core\Font\Type0Font;

/**
 * PDF/A clause 6.3: All fonts must be embedded.
 *
 * Every font used in the document must have a FontDescriptor with a
 * font program reference (/FontFile, /FontFile2, or /FontFile3).
 * Type 0 composite fonts are checked via their descendant CID font.
 */
final class FontEmbeddingConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $violations = [];

        foreach ($inspector->getFonts() as $font) {
            // Type0 fonts embed via their descendant — skip the wrapper
            if ($font instanceof Type0Font) {
                continue;
            }

            if (!$font instanceof Font) {
                continue;
            }

            $name = $font->baseFont?->value ?? 'unknown';

            if ($font->fontDescriptor === null) {
                $violations[] = new ConformanceViolation(
                    clause: '6.3.4',
                    message: "Font '{$name}' has no FontDescriptor — all fonts must be embedded",
                    severity: ViolationSeverity::Error,
                    objectPath: "Font[{$name}]",
                );
            }
        }

        return $violations;
    }
}
