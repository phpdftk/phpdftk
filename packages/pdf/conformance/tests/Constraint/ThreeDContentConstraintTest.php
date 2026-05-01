<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\ThreeDContentConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfEProfile;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\Annotation\ThreeDAnnotation;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\ThreeD\ThreeDStream;
use ApprLabs\Pdf\Core\ThreeD\ThreeDView;
use PHPUnit\Framework\TestCase;

class ThreeDContentConstraintTest extends TestCase
{
    private function makeRect(): PdfArray
    {
        return new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(100),
        ]);
    }

    public function testNoThreeDContentPasses(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new ThreeDContentConstraint();

        self::assertEmpty($constraint->check($inspector, PdfEProfile::E1));
    }

    public function testValidU3dStreamWithViewsPasses(): void
    {
        $stream = new ThreeDStream('U3D', 'dummy-u3d-data');
        $stream->objectNumber = 1;
        $stream->generationNumber = 0;
        $view = new ThreeDView('Default');
        $view->objectNumber = 2;
        $view->generationNumber = 0;
        $stream->va = new PdfArray([new PdfReference(2, 0)]);

        $annot = new ThreeDAnnotation($this->makeRect());
        $annot->objectNumber = 3;
        $annot->generationNumber = 0;
        $annot->dd = new PdfReference(1, 0);

        $inspector = new MockDocumentInspector(
            registeredObjects: [$stream, $annot],
            threeDStreams: [$stream],
            hasThreeDAnnotations: true,
        );
        $constraint = new ThreeDContentConstraint();

        self::assertEmpty($constraint->check($inspector, PdfEProfile::E1));
    }

    public function testValidPrcStreamWithDefaultViewPasses(): void
    {
        $stream = new ThreeDStream('PRC', 'dummy-prc-data');
        $stream->objectNumber = 1;
        $stream->generationNumber = 0;
        $stream->dv = new PdfReference(2, 0);

        $inspector = new MockDocumentInspector(
            threeDStreams: [$stream],
        );
        $constraint = new ThreeDContentConstraint();

        self::assertEmpty($constraint->check($inspector, PdfEProfile::E1));
    }

    public function testAnnotationMissingDdFails(): void
    {
        $annot = new ThreeDAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->generationNumber = 0;
        // No $annot->dd set

        $inspector = new MockDocumentInspector(
            registeredObjects: [$annot],
            hasThreeDAnnotations: true,
        );
        $constraint = new ThreeDContentConstraint();

        $violations = $constraint->check($inspector, PdfEProfile::E1);
        self::assertCount(1, $violations);
        self::assertSame('13.6.3', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
        self::assertStringContainsString('/3DD', $violations[0]->message);
    }

    public function testInvalidSubtypeFails(): void
    {
        $stream = new ThreeDStream('STEP', 'dummy-data');
        $stream->objectNumber = 1;
        $stream->generationNumber = 0;
        $stream->va = new PdfArray([new PdfReference(2, 0)]);

        $inspector = new MockDocumentInspector(
            threeDStreams: [$stream],
        );
        $constraint = new ThreeDContentConstraint();

        $violations = $constraint->check($inspector, PdfEProfile::E1);
        self::assertCount(1, $violations);
        self::assertSame('13.6.3', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
        self::assertStringContainsString('STEP', $violations[0]->message);
    }

    public function testStreamWithNoViewsWarns(): void
    {
        $stream = new ThreeDStream('U3D', 'dummy-data');
        $stream->objectNumber = 1;
        $stream->generationNumber = 0;
        // No $stream->va or $stream->dv

        $inspector = new MockDocumentInspector(
            threeDStreams: [$stream],
        );
        $constraint = new ThreeDContentConstraint();

        $violations = $constraint->check($inspector, PdfEProfile::E1);
        self::assertCount(1, $violations);
        self::assertSame(ViolationSeverity::Warning, $violations[0]->severity);
        self::assertStringContainsString('views', $violations[0]->message);
    }

    public function testMultipleViolationsReported(): void
    {
        // Annotation without /3DD + stream with invalid subtype and no views
        $annot = new ThreeDAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->generationNumber = 0;

        $stream = new ThreeDStream('INVALID', 'dummy-data');
        $stream->objectNumber = 2;
        $stream->generationNumber = 0;

        $inspector = new MockDocumentInspector(
            registeredObjects: [$annot],
            threeDStreams: [$stream],
            hasThreeDAnnotations: true,
        );
        $constraint = new ThreeDContentConstraint();

        $violations = $constraint->check($inspector, PdfEProfile::E1);
        self::assertCount(3, $violations); // missing /3DD + invalid subtype + no views
    }
}
