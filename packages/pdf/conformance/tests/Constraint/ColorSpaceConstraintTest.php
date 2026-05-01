<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\ColorSpaceConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use PHPUnit\Framework\TestCase;

class ColorSpaceConstraintTest extends TestCase
{
    public function testOutputIntentPresent_Passes(): void
    {
        $inspector = new MockDocumentInspector(hasOutputIntents: true);
        $constraint = new ColorSpaceConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1b));
    }

    public function testNoOutputIntent_WarnsAboutDeviceColor(): void
    {
        $inspector = new MockDocumentInspector(hasOutputIntents: false);
        $constraint = new ColorSpaceConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertCount(1, $violations);
        self::assertSame('6.2.3', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Warning, $violations[0]->severity);
    }
}
