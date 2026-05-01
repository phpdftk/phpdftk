<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\TrappedConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfXProfile;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\PdfName;
use PHPUnit\Framework\TestCase;

class TrappedConstraintTest extends TestCase
{
    public function testNoInfoFails(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new TrappedConstraint();

        $violations = $constraint->check($inspector, PdfXProfile::X1a2003);
        self::assertCount(1, $violations);
        self::assertSame('6.3', $violations[0]->clause);
    }

    public function testTrappedNullFails(): void
    {
        $info = new Info();
        $info->objectNumber = 1;
        $info->generationNumber = 0;
        // trapped not set

        $inspector = new MockDocumentInspector(info: $info);
        $constraint = new TrappedConstraint();

        $violations = $constraint->check($inspector, PdfXProfile::X4);
        self::assertCount(1, $violations);
    }

    public function testTrappedUnknownFails(): void
    {
        $info = new Info();
        $info->objectNumber = 1;
        $info->generationNumber = 0;
        $info->trapped = new PdfName('Unknown');

        $inspector = new MockDocumentInspector(info: $info);
        $constraint = new TrappedConstraint();

        $violations = $constraint->check($inspector, PdfXProfile::X1a2003);
        self::assertCount(1, $violations);
        self::assertStringContainsString('Unknown', $violations[0]->message);
    }

    public function testTrappedTruePasses(): void
    {
        $info = new Info();
        $info->objectNumber = 1;
        $info->generationNumber = 0;
        $info->trapped = new PdfName('True');

        $inspector = new MockDocumentInspector(info: $info);
        $constraint = new TrappedConstraint();

        self::assertEmpty($constraint->check($inspector, PdfXProfile::X1a2003));
    }

    public function testTrappedFalsePasses(): void
    {
        $info = new Info();
        $info->objectNumber = 1;
        $info->generationNumber = 0;
        $info->trapped = new PdfName('False');

        $inspector = new MockDocumentInspector(info: $info);
        $constraint = new TrappedConstraint();

        self::assertEmpty($constraint->check($inspector, PdfXProfile::X4));
    }
}
