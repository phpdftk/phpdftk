<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\FormConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfMailProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use PHPUnit\Framework\TestCase;

class FormConstraintTest extends TestCase
{
    public function testNoFormsPasses(): void
    {
        $inspector = new MockDocumentInspector(hasInteractiveForms: false);
        $constraint = new FormConstraint();

        self::assertEmpty($constraint->check($inspector, PdfMailProfile::Mail1));
    }

    public function testAcroFormPresentFails(): void
    {
        $inspector = new MockDocumentInspector(hasInteractiveForms: true);
        $constraint = new FormConstraint();

        $violations = $constraint->check($inspector, PdfMailProfile::Mail1);
        self::assertCount(1, $violations);
        self::assertSame('6.7', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
        self::assertStringContainsString('AcroForm', $violations[0]->message);
        self::assertStringContainsString('PDF/mail', $violations[0]->message);
    }
}
