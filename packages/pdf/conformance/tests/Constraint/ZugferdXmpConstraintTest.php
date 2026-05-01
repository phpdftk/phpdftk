<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\ZugferdXmpConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Conformance\Profile\ZugferdProfile;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use PHPUnit\Framework\TestCase;

class ZugferdXmpConstraintTest extends TestCase
{
    public function testNoOpForNonZugferdProfile(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new ZugferdXmpConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A3b));
    }

    public function testMissingXmpFails(): void
    {
        $inspector = new MockDocumentInspector(
            hasXmpMetadata: false,
            xmpBytes: null,
        );
        $constraint = new ZugferdXmpConstraint();

        $violations = $constraint->check($inspector, ZugferdProfile::BASIC);
        self::assertCount(1, $violations);
        self::assertSame('A.1', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
    }

    public function testCorrectXmpPasses(): void
    {
        $xmp = '<fx:ConformanceLevel>BASIC</fx:ConformanceLevel>'
             . '<fx:DocumentType>INVOICE</fx:DocumentType>'
             . '<fx:DocumentFileName>factur-x.xml</fx:DocumentFileName>';

        $inspector = new MockDocumentInspector(
            hasXmpMetadata: true,
            xmpBytes: $xmp,
        );
        $constraint = new ZugferdXmpConstraint();

        self::assertEmpty($constraint->check($inspector, ZugferdProfile::BASIC));
    }

    public function testMissingConformanceLevelFails(): void
    {
        $xmp = '<fx:DocumentType>INVOICE</fx:DocumentType>'
             . '<fx:DocumentFileName>factur-x.xml</fx:DocumentFileName>';

        $inspector = new MockDocumentInspector(
            hasXmpMetadata: true,
            xmpBytes: $xmp,
        );
        $constraint = new ZugferdXmpConstraint();

        $violations = $constraint->check($inspector, ZugferdProfile::BASIC);
        self::assertCount(1, $violations);
        self::assertStringContainsString('ConformanceLevel', $violations[0]->message);
    }

    public function testMissingMultiplePropertiesFails(): void
    {
        $xmp = '<some:other>value</some:other>';

        $inspector = new MockDocumentInspector(
            hasXmpMetadata: true,
            xmpBytes: $xmp,
        );
        $constraint = new ZugferdXmpConstraint();

        $violations = $constraint->check($inspector, ZugferdProfile::EN16931);
        self::assertCount(3, $violations); // ConformanceLevel, DocumentType, DocumentFileName
    }
}
