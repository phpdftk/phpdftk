<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\TrimBoxConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfXProfile;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\Document\Page;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use PHPUnit\Framework\TestCase;

class TrimBoxConstraintTest extends TestCase
{
    private function makeRect(): PdfArray
    {
        return new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]);
    }

    public function testPageWithTrimBoxPasses(): void
    {
        $page = new Page();
        $page->objectNumber = 1;
        $page->generationNumber = 0;
        $page->trimBox = $this->makeRect();

        $inspector = new PageMockInspector([$page]);
        $constraint = new TrimBoxConstraint();

        self::assertEmpty($constraint->check($inspector, PdfXProfile::X1a2003));
    }

    public function testPageWithArtBoxPasses(): void
    {
        $page = new Page();
        $page->objectNumber = 1;
        $page->generationNumber = 0;
        $page->artBox = $this->makeRect();

        $inspector = new PageMockInspector([$page]);
        $constraint = new TrimBoxConstraint();

        self::assertEmpty($constraint->check($inspector, PdfXProfile::X4));
    }

    public function testPageWithoutTrimBoxOrArtBoxFails(): void
    {
        $page = new Page();
        $page->objectNumber = 1;
        $page->generationNumber = 0;

        $inspector = new PageMockInspector([$page]);
        $constraint = new TrimBoxConstraint();

        $violations = $constraint->check($inspector, PdfXProfile::X1a2003);
        self::assertCount(1, $violations);
        self::assertSame('6.2', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
    }

    public function testMultiplePagesReportsEach(): void
    {
        $p1 = new Page();
        $p1->objectNumber = 1;
        $p1->generationNumber = 0;
        $p1->trimBox = $this->makeRect(); // OK

        $p2 = new Page();
        $p2->objectNumber = 2;
        $p2->generationNumber = 0;
        // No TrimBox — fails

        $inspector = new PageMockInspector([$p1, $p2]);
        $constraint = new TrimBoxConstraint();

        $violations = $constraint->check($inspector, PdfXProfile::X4);
        self::assertCount(1, $violations);
        self::assertStringContainsString('Page 1', $violations[0]->message);
    }

    public function testNoPagesPassesVacuously(): void
    {
        $inspector = new PageMockInspector([]);
        $constraint = new TrimBoxConstraint();

        self::assertEmpty($constraint->check($inspector, PdfXProfile::X1a2003));
    }
}
