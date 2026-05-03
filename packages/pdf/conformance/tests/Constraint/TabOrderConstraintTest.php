<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\TabOrderConstraint;
use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\PdfUaProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class TabOrderConstraintTest extends TestCase
{
    public function testPageWithoutAnnotationsPasses(): void
    {
        $page = new Page();
        $page->objectNumber = 1;
        $page->generationNumber = 0;
        // No annotations, no /Tabs needed

        $inspector = new PageMockInspector([$page]);
        $constraint = new TabOrderConstraint();

        self::assertEmpty($constraint->check($inspector, PdfUaProfile::UA1));
    }

    public function testPageWithAnnotationsButNoTabsFails(): void
    {
        $page = new Page();
        $page->objectNumber = 1;
        $page->generationNumber = 0;
        $page->annots = [new PdfReference(5)]; // has annotations
        // No /Tabs set

        $inspector = new PageMockInspector([$page]);
        $constraint = new TabOrderConstraint();

        $violations = $constraint->check($inspector, PdfUaProfile::UA1);
        self::assertCount(1, $violations);
        self::assertSame('7.5', $violations[0]->clause);
        self::assertSame(ViolationSeverity::Error, $violations[0]->severity);
    }

    public function testPageWithAnnotationsAndTabsSPasses(): void
    {
        $page = new Page();
        $page->objectNumber = 1;
        $page->generationNumber = 0;
        $page->annots = [new PdfReference(5)];
        $page->tabs = new PdfName('S');

        $inspector = new PageMockInspector([$page]);
        $constraint = new TabOrderConstraint();

        self::assertEmpty($constraint->check($inspector, PdfUaProfile::UA1));
    }

    public function testPageWithAnnotationsAndWrongTabsValueFails(): void
    {
        $page = new Page();
        $page->objectNumber = 1;
        $page->generationNumber = 0;
        $page->annots = [new PdfReference(5)];
        $page->tabs = new PdfName('R'); // Row order, not structure

        $inspector = new PageMockInspector([$page]);
        $constraint = new TabOrderConstraint();

        $violations = $constraint->check($inspector, PdfUaProfile::UA1);
        self::assertCount(1, $violations);
    }
}

/**
 * A mock inspector that yields specific pages.
 */
class PageMockInspector implements DocumentInspector
{
    /** @param list<Page> $pages */
    public function __construct(private array $pages) {}

    public function getCatalog(): Catalog { return new Catalog(); }
    public function getInfo(): ?Info { return null; }
    public function getPages(): iterable { return $this->pages; }
    public function getFonts(): iterable { return []; }
    public function hasEncryption(): bool { return false; }
    public function hasXmpMetadata(): bool { return false; }
    public function getXmpBytes(): ?string { return null; }
    public function hasOutputIntents(): bool { return false; }
    public function hasOutputIntentWithIccProfile(): bool { return false; }
    public function hasTransparency(): bool { return false; }
    public function hasJavaScript(): bool { return false; }
    public function hasEmbeddedFiles(): bool { return false; }
    public function getRegisteredObjects(): iterable { return []; }
    public function hasThreeDAnnotations(): bool { return false; }
    public function getThreeDStreams(): iterable { return []; }
    public function hasRasterOnlyContent(): bool { return true; }
    public function getImageXObjects(): iterable { return []; }
    public function hasInteractiveForms(): bool { return false; }
    public function hasMultimediaContent(): bool { return false; }
    public function getReferenceXObjects(): iterable { return []; }
}
