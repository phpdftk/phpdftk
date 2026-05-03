<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\EncryptionConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use PHPUnit\Framework\TestCase;

class EncryptionConstraintTest extends TestCase
{
    public function testNoEncryptionPasses(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new EncryptionConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertEmpty($violations);
    }

    public function testEncryptionFails(): void
    {
        $inspector = new MockDocumentInspector(hasEncryption: true);
        $constraint = new EncryptionConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertCount(1, $violations);
        self::assertSame('6.6', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
    }
}
