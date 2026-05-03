<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\TaggedStructureConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\MarkInfo;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class TaggedStructureConstraintTest extends TestCase
{
    public function testSkippedForLevelB(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new TaggedStructureConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1b));
        self::assertEmpty($constraint->check($inspector, PdfAProfile::A2b));
        self::assertEmpty($constraint->check($inspector, PdfAProfile::A3b));
    }

    public function testMissingMarkInfoFails(): void
    {
        $catalog = new Catalog();
        $inspector = new MockDocumentInspector(catalog: $catalog);
        $constraint = new TaggedStructureConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1a);
        self::assertNotEmpty($violations);

        $clauses = array_map(fn($v) => $v->clause, $violations);
        self::assertContains('6.8.1', $clauses); // MarkInfo
        self::assertContains('6.8.2', $clauses); // StructTreeRoot
        self::assertContains('6.8.4', $clauses); // Lang
    }

    public function testMarkInfoNotMarkedFails(): void
    {
        $catalog = new Catalog();
        $markInfo = new MarkInfo();
        $markInfo->marked = false;
        $catalog->markInfo = $markInfo;
        $catalog->structTreeRoot = new PdfReference(5);
        $catalog->lang = new PdfString('en');

        $inspector = new MockDocumentInspector(catalog: $catalog);
        $constraint = new TaggedStructureConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A2a);
        self::assertCount(1, $violations);
        self::assertSame('6.8.1', $violations[0]->clause);
    }

    public function testFullyTaggedPasses(): void
    {
        $catalog = new Catalog();
        $markInfo = new MarkInfo();
        $markInfo->marked = true;
        $catalog->markInfo = $markInfo;
        $catalog->structTreeRoot = new PdfReference(5);
        $catalog->lang = new PdfString('en-US');

        $inspector = new MockDocumentInspector(catalog: $catalog);
        $constraint = new TaggedStructureConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1a));
        self::assertEmpty($constraint->check($inspector, PdfAProfile::A2a));
        self::assertEmpty($constraint->check($inspector, PdfAProfile::A3a));
    }
}
