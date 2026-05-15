<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Interactive;

use Phpdftk\Pdf\Core\Interactive\Form\FdfReader;
use Phpdftk\Pdf\Core\Interactive\Form\FdfWriter;
use Phpdftk\Pdf\Core\Interactive\Form\XfdfReader;
use Phpdftk\Pdf\Core\Interactive\Form\XfdfWriter;
use PHPUnit\Framework\TestCase;

class FdfXfdfTest extends TestCase
{
    // -----------------------------------------------------------------------
    // FDF
    // -----------------------------------------------------------------------

    public function testFdfWriterGeneratesValidFdf(): void
    {
        $fdf = FdfWriter::generate([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertStringStartsWith('%FDF-1.2', $fdf);
        $this->assertStringEndsWith('%%EOF', $fdf);
        $this->assertStringContainsString('/T (name)', $fdf);
        $this->assertStringContainsString('/V (John Doe)', $fdf);
        $this->assertStringContainsString('/T (email)', $fdf);
        $this->assertStringContainsString('/V (john@example.com)', $fdf);
    }

    public function testFdfWriterWithPdfPath(): void
    {
        $fdf = FdfWriter::generate(['field' => 'value'], '/path/to/form.pdf');

        $this->assertStringContainsString('/F (/path/to/form.pdf)', $fdf);
    }

    public function testFdfWriterEscapesSpecialCharacters(): void
    {
        $fdf = FdfWriter::generate(['field' => 'value with (parens) and \\backslash']);

        $this->assertStringContainsString('\\(parens\\)', $fdf);
        $this->assertStringContainsString('\\\\backslash', $fdf);
    }

    public function testFdfReaderParsesFields(): void
    {
        $fdf = FdfWriter::generate([
            'name' => 'Jane Smith',
            'age' => '30',
        ]);

        $fields = FdfReader::parse($fdf);

        $this->assertSame('Jane Smith', $fields['name']);
        $this->assertSame('30', $fields['age']);
    }

    public function testFdfRoundTrip(): void
    {
        $original = [
            'firstName' => 'Alice',
            'lastName' => 'Bob',
            'address' => '123 Main St',
        ];

        $fdf = FdfWriter::generate($original);
        $parsed = FdfReader::parse($fdf);

        $this->assertSame($original, $parsed);
    }

    public function testFdfRoundTripWithSpecialChars(): void
    {
        $original = [
            'notes' => 'Has (parens) and \\backslash',
        ];

        $fdf = FdfWriter::generate($original);
        $parsed = FdfReader::parse($fdf);

        $this->assertSame($original, $parsed);
    }

    public function testFdfReaderHandlesEmptyContent(): void
    {
        $fields = FdfReader::parse('');
        $this->assertSame([], $fields);
    }

    public function testFdfReaderParsesVThenT(): void
    {
        // FDF with /V before /T to exercise the second regex.
        $fdf = '%FDF-1.2' . "\n" . '1 0 obj << /V (Alice) /T (FullName) >> endobj';
        $fields = FdfReader::parse($fdf);
        $this->assertSame(['FullName' => 'Alice'], $fields);
    }

    public function testFdfReaderHandlesEscapedCharsAndBackslash(): void
    {
        // Field with escaped parens and backslash inside the value.
        $fdf = '<< /T (Path) /V (C:\\\\Users\\\\Bob \\(Admin\\)) >>';
        $fields = FdfReader::parse($fdf);
        $this->assertSame(['Path' => 'C:\\Users\\Bob (Admin)'], $fields);
    }

    public function testFdfReaderDoesNotMatchMalformedDictionary(): void
    {
        // Missing /V should produce no results
        $fdf = '<< /T (only-name) >>';
        $fields = FdfReader::parse($fdf);
        $this->assertSame([], $fields);
    }

    public function testFdfReaderTOverridesVOnDuplicateName(): void
    {
        // If the same field name appears via both regex passes, first match wins.
        $fdf = '<< /T (X) /V (first) >> << /V (second) /T (X) >>';
        $fields = FdfReader::parse($fdf);
        $this->assertSame(['X' => 'first'], $fields);
    }

    // -----------------------------------------------------------------------
    // XFDF
    // -----------------------------------------------------------------------

    public function testXfdfWriterGeneratesValidXml(): void
    {
        $xfdf = XfdfWriter::generate([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertStringStartsWith('<?xml version="1.0"', $xfdf);
        $this->assertStringContainsString('<xfdf', $xfdf);
        $this->assertStringContainsString('field name="name"', $xfdf);
        $this->assertStringContainsString('<value>John Doe</value>', $xfdf);
        $this->assertStringContainsString('field name="email"', $xfdf);

        // Should be valid XML
        $xml = simplexml_load_string($xfdf);
        $this->assertNotFalse($xml);
    }

    public function testXfdfWriterWithPdfPath(): void
    {
        $xfdf = XfdfWriter::generate(['field' => 'value'], '/path/to/form.pdf');

        $this->assertStringContainsString('href="/path/to/form.pdf"', $xfdf);
    }

    public function testXfdfWriterEscapesXmlEntities(): void
    {
        $xfdf = XfdfWriter::generate(['field' => 'value with <angle> & "quotes"']);

        // Should be valid XML despite special chars
        $xml = simplexml_load_string($xfdf);
        $this->assertNotFalse($xml);
    }

    public function testXfdfReaderParsesFields(): void
    {
        $xfdf = XfdfWriter::generate([
            'name' => 'Jane Smith',
            'age' => '30',
        ]);

        $fields = XfdfReader::parse($xfdf);

        $this->assertSame('Jane Smith', $fields['name']);
        $this->assertSame('30', $fields['age']);
    }

    public function testXfdfRoundTrip(): void
    {
        $original = [
            'firstName' => 'Alice',
            'lastName' => 'Bob',
            'address' => '123 Main St',
        ];

        $xfdf = XfdfWriter::generate($original);
        $parsed = XfdfReader::parse($xfdf);

        $this->assertSame($original, $parsed);
    }

    public function testXfdfRoundTripWithSpecialChars(): void
    {
        $original = [
            'notes' => 'Has <angle> & "quotes" plus \'apostrophes\'',
        ];

        $xfdf = XfdfWriter::generate($original);
        $parsed = XfdfReader::parse($xfdf);

        $this->assertSame($original, $parsed);
    }

    public function testXfdfReaderHandlesEmptyContent(): void
    {
        $fields = XfdfReader::parse('');
        $this->assertSame([], $fields);
    }

    public function testXfdfReaderHandlesNoNamespace(): void
    {
        $xfdf = '<xfdf><fields><field name="test"><value>hello</value></field></fields></xfdf>';

        $fields = XfdfReader::parse($xfdf);

        $this->assertSame('hello', $fields['test']);
    }
}
