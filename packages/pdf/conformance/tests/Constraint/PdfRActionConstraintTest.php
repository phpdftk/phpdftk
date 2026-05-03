<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\PdfRActionConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfRProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Pdf\Core\Action\GoToAction;
use Phpdftk\Pdf\Core\Action\JavaScriptAction;
use Phpdftk\Pdf\Core\Action\LaunchAction;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;
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
