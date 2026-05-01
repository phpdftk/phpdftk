<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\ActionConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\Action\GoToAction;
use ApprLabs\Pdf\Core\Action\JavaScriptAction;
use ApprLabs\Pdf\Core\Action\LaunchAction;
use ApprLabs\Pdf\Core\Action\MovieAction;
use ApprLabs\Pdf\Core\Action\SoundAction;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class ActionConstraintTest extends TestCase
{
    public function testNoActionsPasses(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new ActionConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1b));
    }

    public function testJavaScriptActionFailsAllLevels(): void
    {
        $js = new JavaScriptAction(new PdfString('alert("hi")'));
        $js->objectNumber = 1;
        $js->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$js]);
        $constraint = new ActionConstraint();

        // Fails for PDF/A-1b
        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertNotEmpty($violations);
        self::assertSame('6.6.1', $violations[0]->clause);

        // Also fails for PDF/A-2b
        $violations = $constraint->check($inspector, PdfAProfile::A2b);
        self::assertNotEmpty($violations);
    }

    public function testLaunchActionFails(): void
    {
        $launch = new LaunchAction();
        $launch->objectNumber = 1;
        $launch->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$launch]);
        $constraint = new ActionConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A2b);
        self::assertNotEmpty($violations);
    }

    public function testMovieActionFailsForA1Only(): void
    {
        $movie = new MovieAction();
        $movie->objectNumber = 1;
        $movie->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$movie]);
        $constraint = new ActionConstraint();

        // Fails for A-1
        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertNotEmpty($violations);

        // Passes for A-2 (movie actions not restricted in A-2+)
        $violations = $constraint->check($inspector, PdfAProfile::A2b);
        self::assertEmpty($violations);
    }

    public function testGoToActionIsAllowed(): void
    {
        $goto = new GoToAction(new PdfName('dest1'));
        $goto->objectNumber = 1;
        $goto->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$goto]);
        $constraint = new ActionConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1b));
    }
}
