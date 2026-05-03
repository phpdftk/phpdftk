<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\MultimediaConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfMailProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use PHPUnit\Framework\TestCase;

class MultimediaConstraintTest extends TestCase
{
    public function testNoMultimediaPasses(): void
    {
        $inspector = new MockDocumentInspector(hasMultimediaContent: false);
        $constraint = new MultimediaConstraint();

        self::assertEmpty($constraint->check($inspector, PdfMailProfile::Mail1));
    }

    public function testMultimediaPresentFails(): void
    {
        $inspector = new MockDocumentInspector(hasMultimediaContent: true);
        $constraint = new MultimediaConstraint();

        $violations = $constraint->check($inspector, PdfMailProfile::Mail1);
        self::assertCount(1, $violations);
        self::assertSame('6.8', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
        self::assertStringContainsString('Multimedia', $violations[0]->message);
        self::assertStringContainsString('PDF/mail', $violations[0]->message);
    }
}
