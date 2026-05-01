<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Constraint;

use ApprLabs\Pdf\Conformance\Constraint\FilterConstraint;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfStream;
use PHPUnit\Framework\TestCase;

class FilterConstraintTest extends TestCase
{
    public function testNoFiltersPasses(): void
    {
        $inspector = new MockDocumentInspector();
        $constraint = new FilterConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1b));
    }

    public function testFlateDecodeAllowed(): void
    {
        $stream = new PdfStream(new PdfDictionary(), 'data');
        $stream->objectNumber = 1;
        $stream->generationNumber = 0;
        $stream->dictionary->set('Filter', new PdfName('FlateDecode'));

        $inspector = new MockDocumentInspector(registeredObjects: [$stream]);
        $constraint = new FilterConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A1b));
    }

    public function testLzwDecodeFailsForA1(): void
    {
        $stream = new PdfStream(new PdfDictionary(), 'data');
        $stream->objectNumber = 1;
        $stream->generationNumber = 0;
        $stream->dictionary->set('Filter', new PdfName('LZWDecode'));

        $inspector = new MockDocumentInspector(registeredObjects: [$stream]);
        $constraint = new FilterConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertCount(1, $violations);
        self::assertSame('6.1.10', $violations[0]->clause);
    }

    public function testLzwDecodeAllowedForA2(): void
    {
        $stream = new PdfStream(new PdfDictionary(), 'data');
        $stream->objectNumber = 1;
        $stream->generationNumber = 0;
        $stream->dictionary->set('Filter', new PdfName('LZWDecode'));

        $inspector = new MockDocumentInspector(registeredObjects: [$stream]);
        $constraint = new FilterConstraint();

        self::assertEmpty($constraint->check($inspector, PdfAProfile::A2b));
    }
}
