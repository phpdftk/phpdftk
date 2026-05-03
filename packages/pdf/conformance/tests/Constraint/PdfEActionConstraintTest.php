<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\PdfEActionConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfEProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Pdf\Core\Action\GoToAction;
use Phpdftk\Pdf\Core\Action\JavaScriptAction;
use Phpdftk\Pdf\Core\Action\LaunchAction;
use Phpdftk\Pdf\Core\Action\URIAction;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class PdfEActionConstraintTest extends TestCase
{
    public function testNoActionsPasses(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new PdfEActionConstraint();

        self::assertEmpty($constraint->check($inspector, PdfEProfile::E1));
    }

    public function testGoToActionPasses(): void
    {
        $action = new GoToAction(new PdfName('dest'));
        $action->objectNumber = 1;
        $action->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$action]);
        $constraint = new PdfEActionConstraint();

        self::assertEmpty($constraint->check($inspector, PdfEProfile::E1));
    }

    public function testUriActionPasses(): void
    {
        $action = new URIAction(new PdfString('https://example.com'));
        $action->objectNumber = 1;
        $action->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$action]);
        $constraint = new PdfEActionConstraint();

        self::assertEmpty($constraint->check($inspector, PdfEProfile::E1));
    }

    public function testJavaScriptActionFails(): void
    {
        $action = new JavaScriptAction(new PdfString('alert("hi")'));
        $action->objectNumber = 1;
        $action->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$action]);
        $constraint = new PdfEActionConstraint();

        $violations = $constraint->check($inspector, PdfEProfile::E1);
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
        $constraint = new PdfEActionConstraint();

        $violations = $constraint->check($inspector, PdfEProfile::E1);
        self::assertCount(1, $violations);
        self::assertSame('6.6', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
        self::assertStringContainsString('Launch', $violations[0]->message);
    }

    public function testMultipleProhibitedActionsReportsAll(): void
    {
        $js = new JavaScriptAction(new PdfString('alert("hi")'));
        $js->objectNumber = 1;
        $js->generationNumber = 0;

        $launch = new LaunchAction();
        $launch->objectNumber = 2;
        $launch->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$js, $launch]);
        $constraint = new PdfEActionConstraint();

        $violations = $constraint->check($inspector, PdfEProfile::E1);
        self::assertCount(2, $violations);
    }
}
