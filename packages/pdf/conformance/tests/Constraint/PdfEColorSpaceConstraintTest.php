<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\PdfEColorSpaceConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfEProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use PHPUnit\Framework\TestCase;

class PdfEColorSpaceConstraintTest extends TestCase
{
    public function testOutputIntentWithIccProfilePasses(): void
    {
        $inspector = new MockDocumentInspector(
            hasOutputIntents: true,
            hasOutputIntentWithIccProfile: true,
        );
        $constraint = new PdfEColorSpaceConstraint();

        self::assertEmpty($constraint->check($inspector, PdfEProfile::E1));
    }

    public function testOutputIntentWithoutIccProfilePasses(): void
    {
        // OutputIntent exists but without ICC profile — still acceptable
        // (the constraint only warns when there are no OutputIntents at all)
        $inspector = new MockDocumentInspector(
            hasOutputIntents: true,
            hasOutputIntentWithIccProfile: false,
        );
        $constraint = new PdfEColorSpaceConstraint();

        self::assertEmpty($constraint->check($inspector, PdfEProfile::E1));
    }

    public function testNoOutputIntentWarns(): void
    {
        $inspector = new MockDocumentInspector(
            hasOutputIntents: false,
            hasOutputIntentWithIccProfile: false,
        );
        $constraint = new PdfEColorSpaceConstraint();

        $violations = $constraint->check($inspector, PdfEProfile::E1);
        self::assertCount(1, $violations);
        self::assertSame('6.2.2', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Warning, $violations[0]->severity);
        self::assertStringContainsString('OutputIntent', $violations[0]->message);
    }
}
