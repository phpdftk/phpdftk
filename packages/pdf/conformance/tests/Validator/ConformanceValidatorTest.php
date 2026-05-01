<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Validator;

use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Conformance\Tests\Constraint\MockDocumentInspector;
use ApprLabs\Pdf\Conformance\Validator\ConformanceValidator;
use PHPUnit\Framework\TestCase;

class ConformanceValidatorTest extends TestCase
{
    /**
     * Negative: encrypted document with no metadata and no OutputIntent
     * should fail multiple constraints at once.
     */
    public function testMultipleViolationsDetected(): void
    {
        $inspector = new MockDocumentInspector(
            hasEncryption: true,
            hasXmpMetadata: false,
            hasOutputIntents: false,
        );

        $validator = new ConformanceValidator();
        $result = $validator->validate($inspector, PdfAProfile::A1b);

        self::assertFalse($result->isCompliant);

        $clauses = array_map(fn($v) => $v->clause, $result->getErrors());
        self::assertContains('6.6', $clauses);   // encryption
        self::assertContains('6.7.2', $clauses); // metadata
        self::assertContains('6.2.2', $clauses); // output intent
    }

    /**
     * A fully compliant mock should pass.
     */
    public function testCompliantDocumentPasses(): void
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

        $inspector = new MockDocumentInspector(
            hasEncryption: false,
            hasXmpMetadata: true,
            xmpBytes: $xmp,
            hasOutputIntents: true,
            hasOutputIntentWithIccProfile: true,
        );

        $validator = new ConformanceValidator();
        $result = $validator->validate($inspector, PdfAProfile::A1b);

        // No errors — isCompliant should be true
        self::assertTrue($result->isCompliant);
    }

    /**
     * validateAll() returns results for each profile.
     */
    public function testValidateAllReturnsMultipleResults(): void
    {
        $inspector = new MockDocumentInspector();

        $validator = new ConformanceValidator();
        $results = $validator->validateAll($inspector, [PdfAProfile::A1b, PdfAProfile::A2b]);

        self::assertCount(2, $results);
        self::assertSame('1b', $results[0]->profile->getLevel());
        self::assertSame('2b', $results[1]->profile->getLevel());
    }

    /**
     * Negative: A-1a with no tagged structure should have tagging violations
     * in addition to other violations.
     */
    public function testA1aViolatesTaggedStructure(): void
    {
        $xmp = <<<XML
        <x:xmpmeta xmlns:x="adobe:ns:meta/">
          <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
            <rdf:Description xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/">
              <pdfaid:part>1</pdfaid:part>
              <pdfaid:conformance>A</pdfaid:conformance>
            </rdf:Description>
          </rdf:RDF>
        </x:xmpmeta>
        XML;

        $inspector = new MockDocumentInspector(
            hasXmpMetadata: true,
            xmpBytes: $xmp,
            hasOutputIntents: true,
        );

        $validator = new ConformanceValidator();
        $result = $validator->validate($inspector, PdfAProfile::A1a);

        self::assertFalse($result->isCompliant);

        $clauses = array_map(fn($v) => $v->clause, $result->getErrors());
        self::assertContains('6.8.1', $clauses); // MarkInfo
        self::assertContains('6.8.2', $clauses); // StructTreeRoot
        self::assertContains('6.8.4', $clauses); // Lang
    }
}
