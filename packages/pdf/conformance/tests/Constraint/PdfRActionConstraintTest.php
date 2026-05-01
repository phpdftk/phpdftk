<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\PdfRActionConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfRProfile;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\Action\GoToAction;
use ApprLabs\Pdf\Core\Action\JavaScriptAction;
use ApprLabs\Pdf\Core\Action\LaunchAction;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class PdfRActionConstraintTest extends TestCase
{
    public function testNoActionsPasses(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new PdfRActionConstraint();

        self::assertEmpty($constraint->check($inspector, PdfRProfile::R1));
    }

    public function testGoToActionPasses(): void
    {
        $action = new GoToAction(new PdfName('dest'));
        $action->objectNumber = 1;
        $action->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$action]);
        $constraint = new PdfRActionConstraint();

        self::assertEmpty($constraint->check($inspector, PdfRProfile::R1));
    }

    public function testJavaScriptActionFails(): void
    {
        $action = new JavaScriptAction(new PdfString('alert("hi")'));
        $action->objectNumber = 1;
        $action->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$action]);
        $constraint = new PdfRActionConstraint();

        $violations = $constraint->check($inspector, PdfRProfile::R1);
        self::assertCount(1, $violations);
        self::assertSame('6.6', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
        self::assertStringContainsString('JavaScript', $violations[0]->message);
    }

    public function testLaunchActionFails(): void
    {
        $action = new LaunchAction();
        $action->objectNumber = 1;
        $action->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$action]);
        $constraint = new PdfRActionConstraint();

        $violations = $constraint->check($inspector, PdfRProfile::R1);
        self::assertCount(1, $violations);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
        self::assertStringContainsString('Launch', $violations[0]->message);
    }
}
