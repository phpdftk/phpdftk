<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\ZugferdInvoiceConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Conformance\Profile\ZugferdProfile;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\FileSpec\FileSpec;
use ApprLabs\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class ZugferdInvoiceConstraintTest extends TestCase
{
    public function testNoOpForNonZugferdProfile(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new ZugferdInvoiceConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A3b));
    }

    public function testNoEmbeddedFilesFails(): void
    {
        $inspector = new MockDocumentInspector(hasEmbeddedFiles: false);
        $constraint = new ZugferdInvoiceConstraint();

        $violations = $constraint->check($inspector, ZugferdProfile::BASIC);
        self::assertCount(1, $violations);
        self::assertSame('A.2', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
    }

    public function testCorrectFilenamePasses(): void
    {
        $fileSpec = new FileSpec('factur-x.xml');
        $fileSpec->objectNumber = 1;
        $fileSpec->generationNumber = 0;

        $inspector = new MockDocumentInspector(
            hasEmbeddedFiles: true,
            registeredObjects: [$fileSpec],
        );
        $constraint = new ZugferdInvoiceConstraint();

        self::assertEmpty($constraint->check($inspector, ZugferdProfile::BASIC));
    }

    public function testZugferdFilenamePasses(): void
    {
        $fileSpec = new FileSpec('zugferd-invoice.xml');
        $fileSpec->objectNumber = 1;
        $fileSpec->generationNumber = 0;

        $inspector = new MockDocumentInspector(
            hasEmbeddedFiles: true,
            registeredObjects: [$fileSpec],
        );
        $constraint = new ZugferdInvoiceConstraint();

        self::assertEmpty($constraint->check($inspector, ZugferdProfile::MINIMUM));
    }

    public function testWrongFilenameFails(): void
    {
        $fileSpec = new FileSpec('invoice.pdf');
        $fileSpec->objectNumber = 1;
        $fileSpec->generationNumber = 0;

        $inspector = new MockDocumentInspector(
            hasEmbeddedFiles: true,
            registeredObjects: [$fileSpec],
        );
        $constraint = new ZugferdInvoiceConstraint();

        $violations = $constraint->check($inspector, ZugferdProfile::BASIC);
        self::assertCount(1, $violations);
        self::assertStringContainsString('factur-x.xml', $violations[0]->message);
    }

    public function testAllProfilesChecked(): void
    {
        $inspector = new MockDocumentInspector(hasEmbeddedFiles: false);
        $constraint = new ZugferdInvoiceConstraint();

        foreach (ZugferdProfile::cases() as $profile) {
            $violations = $constraint->check($inspector, $profile);
            self::assertCount(1, $violations);
        }
    }
}
