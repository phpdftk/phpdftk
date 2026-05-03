<?php declare(strict_types=1);

namespace Phpdftk\Xmp\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\Xmp\XmpPacket;
use Phpdftk\Xmp\XmpWriter;
use Phpdftk\Xmp\XmpReader;

class XmpTest extends TestCase
{
    public function testXmpPacketCreate(): void
    {
        $packet = XmpPacket::create();
        $this->assertSame([], $packet->all());
    }

    public function testXmpPacketSetAndGet(): void
    {
        $packet = XmpPacket::create()
            ->set('dc:title', 'My Document')
            ->set('xmp:CreateDate', '2024-01-01');
        $this->assertSame('My Document', $packet->get('dc:title'));
        $this->assertSame('2024-01-01', $packet->get('xmp:CreateDate'));
    }

    public function testXmpPacketImmutable(): void
    {
        $original = XmpPacket::create();
        $modified = $original->set('dc:title', 'Test');
        $this->assertNull($original->get('dc:title'));
        $this->assertSame('Test', $modified->get('dc:title'));
    }

    public function testXmpPacketHas(): void
    {
        $packet = XmpPacket::create()->set('dc:title', 'Test');
        $this->assertTrue($packet->has('dc:title'));
        $this->assertFalse($packet->has('dc:author'));
    }

    public function testXmpPacketAll(): void
    {
        $packet = XmpPacket::create()
            ->set('dc:title', 'My PDF')
            ->set('dc:creator', 'John Doe')
            ->set('xmp:CreateDate', '2024-01-15');
        $all = $packet->all();
        $this->assertCount(3, $all);
        $this->assertSame('My PDF', $all['dc:title']);
        $this->assertSame('John Doe', $all['dc:creator']);
    }

    public function testXmpPacketGetNonExistent(): void
    {
        $packet = XmpPacket::create();
        $this->assertNull($packet->get('nonexistent:key'));
    }

    public function testXmpWriterProducesValidXml(): void
    {
        $packet = XmpPacket::create()
            ->set('dc:title', 'Test Document')
            ->set('xmp:CreateDate', '2024-01-01');
        $writer = new XmpWriter();
        $xml = $writer->serialize($packet);
        $this->assertStringContainsString('<?xpacket', $xml);
        $this->assertStringContainsString('x:xmpmeta', $xml);
        $this->assertStringContainsString('rdf:RDF', $xml);
        $this->assertStringContainsString('rdf:Description', $xml);
        $this->assertStringContainsString('<?xpacket end="w"?>', $xml);
    }

    public function testXmpWriterContainsProperties(): void
    {
        $packet = XmpPacket::create()
            ->set('dc:title', 'Hello World')
            ->set('xmp:CreateDate', '2024-06-15');
        $writer = new XmpWriter();
        $xml = $writer->serialize($packet);
        $this->assertStringContainsString('dc:title', $xml);
        $this->assertStringContainsString('Hello World', $xml);
        $this->assertStringContainsString('xmp:CreateDate', $xml);
        $this->assertStringContainsString('2024-06-15', $xml);
    }

    public function testXmpWriterNamespaceDeclarations(): void
    {
        $packet = XmpPacket::create()
            ->set('dc:title', 'Test')
            ->set('pdf:Keywords', 'pdf test');
        $writer = new XmpWriter();
        $xml = $writer->serialize($packet);
        $this->assertStringContainsString('xmlns:dc=', $xml);
        $this->assertStringContainsString('xmlns:pdf=', $xml);
    }

    public function testXmpWriterXmlIsWellFormed(): void
    {
        $packet = XmpPacket::create()
            ->set('dc:title', 'Test Document')
            ->set('dc:creator', 'Test Author')
            ->set('xmp:CreateDate', '2024-01-01T00:00:00Z');
        $writer = new XmpWriter();
        $xml = $writer->serialize($packet);

        // Strip xpacket processing instructions and validate as XML
        $cleanXml = preg_replace('/<\?xpacket[^?]*\?>/s', '', $xml);
        libxml_use_internal_errors(true);
        $result = simplexml_load_string(trim($cleanXml));
        $errors = libxml_get_errors();
        libxml_use_internal_errors(false);
        $this->assertNotFalse($result, 'XML should be well-formed. Errors: ' . json_encode($errors));
    }

    public function testXmpRoundTrip(): void
    {
        $originalPacket = XmpPacket::create()
            ->set('dc:title', 'Round Trip Test')
            ->set('dc:creator', 'Test Suite')
            ->set('xmp:CreateDate', '2024-03-15');

        $writer = new XmpWriter();
        $xml = $writer->serialize($originalPacket);

        $reader = new XmpReader();
        $parsedPacket = $reader->parse($xml);

        $this->assertSame('Round Trip Test', $parsedPacket->get('dc:title'));
        $this->assertSame('Test Suite', $parsedPacket->get('dc:creator'));
        $this->assertSame('2024-03-15', $parsedPacket->get('xmp:CreateDate'));
    }

    public function testXmpWriterEscapesSpecialCharacters(): void
    {
        $packet = XmpPacket::create()
            ->set('dc:title', 'Test <>&"\'');
        $writer = new XmpWriter();
        $xml = $writer->serialize($packet);
        // The special characters should be escaped in the XML
        $this->assertStringNotContainsString('<>&', $xml);
    }

    public function testXmpEmptyPacket(): void
    {
        $packet = XmpPacket::create();
        $writer = new XmpWriter();
        $xml = $writer->serialize($packet);
        $this->assertStringContainsString('<?xpacket', $xml);
        $this->assertStringContainsString('<?xpacket end="w"?>', $xml);
    }

    public function testXmpReaderEmptyXml(): void
    {
        $reader = new XmpReader();
        $packet = $reader->parse('');
        $this->assertSame([], $packet->all());
    }
}
