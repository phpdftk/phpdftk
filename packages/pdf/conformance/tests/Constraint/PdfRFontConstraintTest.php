<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\PdfRFontConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfRProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Font\StandardFont;
use PHPUnit\Framework\TestCase;

class PdfRFontConstraintTest extends TestCase
{
    public function testNoFontsPasses(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new PdfRFontConstraint();

        self::assertEmpty($constraint->check($inspector, PdfRProfile::R1));
    }

    public function testFontsPresentWarns(): void
    {
        $font = new Type1Font(StandardFont::Helvetica);
        $font->objectNumber = 1;
        $font->generationNumber = 0;

        $inspector = new MockDocumentInspector(fonts: [$font]);
        $constraint = new PdfRFontConstraint();

        $violations = $constraint->check($inspector, PdfRProfile::R1);
        self::assertCount(1, $violations);
        self::assertSame('6.3', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Warning, $violations[0]->severity);
        self::assertStringContainsString('font', $violations[0]->message);
    }
}
