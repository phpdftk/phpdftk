<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\DisplayDocTitleConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfUaProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Pdf\Core\Document\ViewerPreferences;
use PHPUnit\Framework\TestCase;

class DisplayDocTitleConstraintTest extends TestCase
{
    public function testMissingViewerPreferencesFails(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new DisplayDocTitleConstraint();

        $violations = $constraint->check($inspector, PdfUaProfile::UA1);
        self::assertCount(1, $violations);
        self::assertSame('7.18.1', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
    }

    public function testDisplayDocTitleFalseFails(): void
    {
        $vp = new ViewerPreferences();
        $vp->objectNumber = 1;
        $vp->generationNumber = 0;
        $vp->displayDocTitle = false;

        $inspector = new MockDocumentInspector(registeredObjects: [$vp]);
        $constraint = new DisplayDocTitleConstraint();

        $violations = $constraint->check($inspector, PdfUaProfile::UA1);
        self::assertCount(1, $violations);
    }

    public function testDisplayDocTitleTruePasses(): void
    {
        $vp = new ViewerPreferences();
        $vp->objectNumber = 1;
        $vp->generationNumber = 0;
        $vp->displayDocTitle = true;

        $inspector = new MockDocumentInspector(registeredObjects: [$vp]);
        $constraint = new DisplayDocTitleConstraint();

        self::assertEmpty($constraint->check($inspector, PdfUaProfile::UA1));
    }
}
