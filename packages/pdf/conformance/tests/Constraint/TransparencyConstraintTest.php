<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\TransparencyConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use PHPUnit\Framework\TestCase;

class TransparencyConstraintTest extends TestCase
{
    public function testNoTransparencyPasses(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new TransparencyConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1b));
    }

    public function testTransparencyFailsForA1(): void
    {
        $inspector = new MockDocumentInspector(hasTransparency: true);
        $constraint = new TransparencyConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertCount(1, $violations);
        self::assertSame('6.4', $violations[0]->clause);
    }

    public function testTransparencyAllowedForA2(): void
    {
        $inspector = new MockDocumentInspector(hasTransparency: true);
        $constraint = new TransparencyConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A2b));
    }
}
