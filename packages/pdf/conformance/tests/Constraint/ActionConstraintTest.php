<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\ActionConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Pdf\Core\Action\GoToAction;
use Phpdftk\Pdf\Core\Action\JavaScriptAction;
use Phpdftk\Pdf\Core\Action\LaunchAction;
use Phpdftk\Pdf\Core\Action\MovieAction;
use Phpdftk\Pdf\Core\Action\SoundAction;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;
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

    public function testSoundActionFailsForA1Only(): void
    {
        $sound = new \Phpdftk\Pdf\Core\Action\SoundAction(new \Phpdftk\Pdf\Core\PdfReference(99));
        $sound->objectNumber = 1;

        $inspector = new MockDocumentInspector(registeredObjects: [$sound]);
        $constraint = new ActionConstraint();
        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertNotEmpty($violations);

        // Not restricted in A-2+
        $this->assertEmpty($constraint->check($inspector, PdfAProfile::A2b));
    }

    public function testRenditionActionFailsForA1Only(): void
    {
        $rendition = new \Phpdftk\Pdf\Core\Action\RenditionAction(0);
        $rendition->objectNumber = 1;

        $inspector = new MockDocumentInspector(registeredObjects: [$rendition]);
        $constraint = new ActionConstraint();
        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertNotEmpty($violations);

        $this->assertEmpty($constraint->check($inspector, PdfAProfile::A2b));
    }

    public function testRichMediaExecuteActionFailsForA1Only(): void
    {
        $rm = new \Phpdftk\Pdf\Core\Action\RichMediaExecuteAction();
        $rm->objectNumber = 1;

        $inspector = new MockDocumentInspector(registeredObjects: [$rm]);
        $constraint = new ActionConstraint();
        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertNotEmpty($violations);

        $this->assertEmpty($constraint->check($inspector, PdfAProfile::A2b));
    }
}
