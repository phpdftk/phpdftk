<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\EmbeddedFileConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use PHPUnit\Framework\TestCase;

class EmbeddedFileConstraintTest extends TestCase
{
    public function testNoEmbeddedFilesPasses(): void
    {
        $inspector = new MockDocumentInspector(hasEmbeddedFiles: false);
        $constraint = new EmbeddedFileConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1b));
    }

    public function testEmbeddedFilesFailForA1(): void
    {
        $inspector = new MockDocumentInspector(hasEmbeddedFiles: true);
        $constraint = new EmbeddedFileConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertCount(1, $violations);
        self::assertSame('6.9', $violations[0]->clause);
    }

    public function testEmbeddedFilesFailForA2(): void
    {
        $inspector = new MockDocumentInspector(hasEmbeddedFiles: true);
        $constraint = new EmbeddedFileConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A2b);
        self::assertCount(1, $violations);
        self::assertSame('6.10', $violations[0]->clause);
    }

    public function testEmbeddedFilesAllowedForA3(): void
    {
        $inspector = new MockDocumentInspector(hasEmbeddedFiles: true);
        $constraint = new EmbeddedFileConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A3b));
    }

    public function testEmbeddedFilesAllowedForA4(): void
    {
        $inspector = new MockDocumentInspector(hasEmbeddedFiles: true);
        $constraint = new EmbeddedFileConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A4));
    }
}
