<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Constraint\MetadataConstraint;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use PHPUnit\Framework\TestCase;

class MetadataConstraintTest extends TestCase
{
    public function testNoXmpFails(): void
    {
        $inspector = new MockDocumentInspector(hasXmpMetadata: false);
        $constraint = new MetadataConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertCount(1, $violations);
        self::assertSame('6.7.2', $violations[0]->clause);
    }

    public function testXmpWithoutIdentificationFails(): void
    {
        $xmp = '<x:xmpmeta><rdf:RDF><rdf:Description/></rdf:RDF></x:xmpmeta>';
        $inspector = new MockDocumentInspector(hasXmpMetadata: true, xmpBytes: $xmp);
        $constraint = new MetadataConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        // Should fail because pdfaid:part and pdfaid:conformance are missing
        self::assertNotEmpty($violations);
        $clauses = array_map(fn($v) => $v->clause, $violations);
        self::assertContains('6.7.11', $clauses);
    }

    public function testValidXmpPasses(): void
    {
        $xmp = <<<XML
        <x:xmpmeta xmlns:x="adobe:ns:meta/">
          <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
            <rdf:Description xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/">
              <pdfaid:part>1</pdfaid:part>
              <pdfaid:conformance>B</pdfaid:conformance>
            </rdf:Description>
          </rdf:RDF>
        </x:xmpmeta>
        XML;

        $inspector = new MockDocumentInspector(hasXmpMetadata: true, xmpBytes: $xmp);
        $constraint = new MetadataConstraint();

        $violations = $constraint->check($inspector, PdfAProfile::A1b);
        self::assertEmpty($violations);
    }
}
