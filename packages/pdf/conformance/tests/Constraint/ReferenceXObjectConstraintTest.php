<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\ReferenceXObjectConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfXProfile;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class ReferenceXObjectConstraintTest extends TestCase
{
    public function testNoOpForNonX5Profiles(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new ReferenceXObjectConstraint();

        // Should return empty for X-4 profile
        self::assertEmpty($constraint->check($inspector, PdfXProfile::X4));
        self::assertEmpty($constraint->check($inspector, PdfXProfile::X1a2003));
        self::assertEmpty($constraint->check($inspector, PdfXProfile::X32003));
    }

    public function testNoReferenceXObjectsPasses(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new ReferenceXObjectConstraint();

        self::assertEmpty($constraint->check($inspector, PdfXProfile::X5g));
    }

    public function testValidReferenceXObjectPasses(): void
    {
        $bbox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(100),
        ]);
        $formXObject = new FormXObject($bbox);
        $formXObject->objectNumber = 1;
        $formXObject->generationNumber = 0;
        $formXObject->ref = new PdfReference(2, 0);

        $inspector = new MockDocumentInspector(referenceXObjects: [$formXObject]);
        $constraint = new ReferenceXObjectConstraint();

        self::assertEmpty($constraint->check($inspector, PdfXProfile::X5g));
        self::assertEmpty($constraint->check($inspector, PdfXProfile::X5pg));
        self::assertEmpty($constraint->check($inspector, PdfXProfile::X5n));
    }

    public function testAllX5ProfilesApply(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new ReferenceXObjectConstraint();

        // All X-5 profiles should be processed (even if no violations)
        self::assertEmpty($constraint->check($inspector, PdfXProfile::X5g));
        self::assertEmpty($constraint->check($inspector, PdfXProfile::X5pg));
        self::assertEmpty($constraint->check($inspector, PdfXProfile::X5n));
    }
}
