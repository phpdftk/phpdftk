<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\AnnotationConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfUaProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Pdf\Core\Annotation\LinkAnnotation;
use Phpdftk\Pdf\Core\Annotation\PopupAnnotation;
use Phpdftk\Pdf\Core\Annotation\TextAnnotation;
use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class AnnotationConstraintTest extends TestCase
{
    private function makeRect(): PdfArray
    {
        return new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(100),
        ]);
    }

    public function testNoAnnotationsPasses(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new AnnotationConstraint();

        self::assertEmpty($constraint->check($inspector, PdfUaProfile::UA1));
    }

    public function testAnnotationWithContentsPasses(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->generationNumber = 0;
        $annot->contents = new PdfString('This is a note');

        $inspector = new MockDocumentInspector(registeredObjects: [$annot]);
        $constraint = new AnnotationConstraint();

        self::assertEmpty($constraint->check($inspector, PdfUaProfile::UA1));
    }

    public function testAnnotationWithoutContentsFails(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->generationNumber = 0;
        // No /Contents

        $inspector = new MockDocumentInspector(registeredObjects: [$annot]);
        $constraint = new AnnotationConstraint();

        $violations = $constraint->check($inspector, PdfUaProfile::UA1);
        self::assertCount(1, $violations);
        self::assertSame('7.18.1', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
        self::assertStringContainsString('Text', $violations[0]->message);
    }

    public function testAnnotationWithEmptyContentsFails(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->generationNumber = 0;
        $annot->contents = new PdfString('');

        $inspector = new MockDocumentInspector(registeredObjects: [$annot]);
        $constraint = new AnnotationConstraint();

        $violations = $constraint->check($inspector, PdfUaProfile::UA1);
        self::assertCount(1, $violations);
    }

    public function testWidgetAnnotationIsExempt(): void
    {
        $widget = new WidgetAnnotation($this->makeRect());
        $widget->objectNumber = 1;
        $widget->generationNumber = 0;
        // No /Contents — should still pass

        $inspector = new MockDocumentInspector(registeredObjects: [$widget]);
        $constraint = new AnnotationConstraint();

        self::assertEmpty($constraint->check($inspector, PdfUaProfile::UA1));
    }

    public function testPopupAnnotationIsExempt(): void
    {
        $popup = new PopupAnnotation($this->makeRect());
        $popup->objectNumber = 1;
        $popup->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$popup]);
        $constraint = new AnnotationConstraint();

        self::assertEmpty($constraint->check($inspector, PdfUaProfile::UA1));
    }

    public function testLinkAnnotationWithoutContentsFails(): void
    {
        $link = new LinkAnnotation($this->makeRect());
        $link->objectNumber = 1;
        $link->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$link]);
        $constraint = new AnnotationConstraint();

        $violations = $constraint->check($inspector, PdfUaProfile::UA1);
        self::assertCount(1, $violations);
        self::assertStringContainsString('Link', $violations[0]->message);
    }

    public function testMultipleAnnotationsReportsAll(): void
    {
        $a1 = new TextAnnotation($this->makeRect());
        $a1->objectNumber = 1;
        $a1->generationNumber = 0;

        $a2 = new LinkAnnotation($this->makeRect());
        $a2->objectNumber = 2;
        $a2->generationNumber = 0;

        $inspector = new MockDocumentInspector(registeredObjects: [$a1, $a2]);
        $constraint = new AnnotationConstraint();

        $violations = $constraint->check($inspector, PdfUaProfile::UA1);
        self::assertCount(2, $violations);
    }
}
