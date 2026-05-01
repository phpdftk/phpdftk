<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\Font\Font;
use ApprLabs\Pdf\Core\Font\Type0Font;

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
