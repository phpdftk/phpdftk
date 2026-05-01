<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\OutputIntentConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use PHPUnit\Framework\TestCase;

class OutputIntentConstraintTest extends TestCase
{
    public function testNoOutputIntentFails(): void
    {
        $inspector = new MockDocumentInspector(hasOutputIntents: false);
        $constraint = new OutputIntentConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertCount(1, $violations);
        self::assertSame('6.2.2', $violations[0]->clause);
    }

    public function testOutputIntentWithIccPasses(): void
    {
        $inspector = new MockDocumentInspector(
            hasOutputIntents: true,
            hasOutputIntentWithIccProfile: true,
        );
        $constraint = new OutputIntentConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1b));
    }

    public function testOutputIntentWithoutIccFails(): void
    {
        $inspector = new MockDocumentInspector(
            hasOutputIntents: true,
            hasOutputIntentWithIccProfile: false,
        );
        $constraint = new OutputIntentConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertCount(1, $violations);
        self::assertStringContainsString('DestOutputProfile', $violations[0]->message);
    }
}
