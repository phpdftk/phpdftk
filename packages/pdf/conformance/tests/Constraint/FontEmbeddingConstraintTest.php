<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\FontEmbeddingConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Core\Font\Font;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class FontEmbeddingConstraintTest extends TestCase
{
    public function testEmbeddedFontPasses(): void
    {
        $font = new Font();
        $font->objectNumber = 1;
        $font->generationNumber = 0;
        $font->baseFont = new PdfName('ArialMT');
        $font->fontDescriptor = new PdfReference(2);

        $inspector = new MockDocumentInspector(fonts: [$font]);
        $constraint = new FontEmbeddingConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1b));
    }

    public function testUnembeddedFontFails(): void
    {
        $font = new Font();
        $font->objectNumber = 1;
        $font->generationNumber = 0;
        $font->baseFont = new PdfName('Helvetica');
        // No fontDescriptor — not embedded

        $inspector = new MockDocumentInspector(fonts: [$font]);
        $constraint = new FontEmbeddingConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertCount(1, $violations);
        self::assertSame('6.3.4', $violations[0]->clause);
        self::assertStringContainsString('Helvetica', $violations[0]->message);
    }

    public function testNoFontsPasses(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new FontEmbeddingConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1b));
    }
}
