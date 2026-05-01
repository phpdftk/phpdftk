<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\DPartRootConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfVtProfile;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class DPartRootConstraintTest extends TestCase
{
    public function testMissingDPartRootFails(): void
    {
        $catalog = new Catalog();
        $inspector = new MockDocumentInspector(catalog: $catalog);
        $constraint = new DPartRootConstraint();

        $violations = $constraint->check($inspector, PdfVtProfile::VT1);
        self::assertCount(1, $violations);
        self::assertSame('6.1', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
        self::assertStringContainsString('DPartRoot', $violations[0]->message);
    }

    public function testDPartRootPresentPasses(): void
    {
        $catalog = new Catalog();
        $catalog->dPartRoot = new PdfReference(10);

        $inspector = new MockDocumentInspector(catalog: $catalog);
        $constraint = new DPartRootConstraint();

        self::assertEmpty($constraint->check($inspector, PdfVtProfile::VT1));
    }

    public function testAllVtProfilesChecked(): void
    {
        $catalog = new Catalog();
        $inspector = new MockDocumentInspector(catalog: $catalog);
        $constraint = new DPartRootConstraint();

        foreach (PdfVtProfile::cases() as $profile) {
            $violations = $constraint->check($inspector, $profile);
            self::assertCount(1, $violations, "DPartRoot should be required for {$profile->value}");
        }
    }
}
