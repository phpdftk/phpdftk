<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\RasterContentConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfRProfile;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use PHPUnit\Framework\TestCase;

class RasterContentConstraintTest extends TestCase
{
    public function testRasterOnlyContentPasses(): void
    {
        $inspector = new MockDocumentInspector(hasRasterOnlyContent: true);
        $constraint = new RasterContentConstraint();

        self::assertEmpty($constraint->check($inspector, PdfRProfile::R1));
    }

    public function testNonRasterContentWarns(): void
    {
        $inspector = new MockDocumentInspector(hasRasterOnlyContent: false);
        $constraint = new RasterContentConstraint();

        $violations = $constraint->check($inspector, PdfRProfile::R1);
        self::assertCount(1, $violations);
        self::assertSame('6.1', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Warning, $violations[0]->severity);
        self::assertStringContainsString('raster', $violations[0]->message);
    }
}
