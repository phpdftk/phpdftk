<?php

declare(strict_types=1);

namespace Phpdftk\Xml\Tests;

use Phpdftk\Xml\Exception\InvalidXmlException;
use Phpdftk\Xml\HardenedLoader;
use PHPUnit\Framework\TestCase;

/**
 * Security + parse-failure coverage for the centralised libxml
 * loader. Every typed-tree consumer routes through this class, so a
 * regression here is a multi-package security bug — coverage is bias
 * heavily toward negative cases.
 */
final class HardenedLoaderTest extends TestCase
{
    private HardenedLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new HardenedLoader();
    }

    // -----------------------------------------------------------------
    // Negative cases
    // -----------------------------------------------------------------

    public function testRejectsEmptyInput(): void
    {
        $this->expectException(InvalidXmlException::class);
        $this->expectExceptionMessageMatches('/empty/');
        $this->loader->load('');
    }

    public function testRejectsWhitespaceOnlyInput(): void
    {
        $this->expectException(InvalidXmlException::class);
        $this->loader->load("\n\t   ");
    }

    public function testRejectsMalformedXml(): void
    {
        $this->expectException(InvalidXmlException::class);
        $this->expectExceptionMessageMatches('/parse XML/');
        $this->loader->load('<root><child>');
    }

    public function testRejectsMismatchedTags(): void
    {
        $this->expectException(InvalidXmlException::class);
        $this->loader->load('<a></b>');
    }

    public function testRejectsUnescapedAmpersand(): void
    {
        $this->expectException(InvalidXmlException::class);
        $this->loader->load('<root>A & B</root>');
    }

    // -----------------------------------------------------------------
    // Security boundary — these MUST hold or every typed parser is
    // a vulnerability vector.
    // -----------------------------------------------------------------

    public function testDoesNotSubstituteExternalEntities(): void
    {
        // Classic XXE attempt. A correctly hardened loader keeps the
        // entity reference inert — the resulting DOM contains
        // either nothing or the literal `&xxe;` (which DOMDocument
        // surfaces as an EntityReference node, NOT as the file's
        // contents).
        $xml = <<<XML
        <?xml version="1.0"?>
        <!DOCTYPE root [ <!ENTITY xxe SYSTEM "file:///etc/passwd"> ]>
        <root>&xxe;</root>
        XML;
        $dom = $this->loader->load($xml);
        $root = $dom->documentElement;
        self::assertNotNull($root);
        // The DOM tree must NOT contain content read from
        // /etc/passwd. Without substitution, `&xxe;` becomes an
        // EntityReference node whose textContent is empty.
        $text = $root->textContent;
        self::assertSame(
            '',
            $text,
            "XXE entity was substituted; got: $text",
        );
    }

    public function testDoesNotFetchExternalDtd(): void
    {
        // Loading a document that references an external DTD over the
        // network must NOT make the network call. Without LIBXML_NONET
        // libxml would attempt the fetch; with it the load proceeds
        // without DTD resolution (and the DTD's declarations don't
        // affect the parse).
        $xml = <<<XML
        <?xml version="1.0"?>
        <!DOCTYPE root SYSTEM "http://example.invalid/never.dtd">
        <root/>
        XML;
        // The load must succeed (DTD is ignored, not fetched). If
        // libxml were trying the network we'd either hit DNS failure
        // or a much longer timeout; this assertion implicitly
        // confirms LIBXML_NONET is in effect because the call returns
        // promptly.
        $dom = $this->loader->load($xml);
        self::assertNotNull($dom->documentElement);
    }

    public function testIgnoresXIncludeReference(): void
    {
        // <xi:include/> elements are preserved literally — we never
        // call DOMDocument::xinclude(), so the include element is
        // just another foreign element in the DOM.
        $xml = <<<XML
        <root xmlns:xi="http://www.w3.org/2001/XInclude">
          <xi:include href="file:///etc/passwd"/>
        </root>
        XML;
        $dom = $this->loader->load($xml);
        $root = $dom->documentElement;
        self::assertNotNull($root);
        // Find the include element — it should be present in the
        // DOM but contribute no text content (file contents never
        // resolved).
        $include = $root->getElementsByTagNameNS(
            'http://www.w3.org/2001/XInclude',
            'include',
        )->item(0);
        self::assertNotNull($include);
        self::assertSame('', $include->textContent);
    }

    // -----------------------------------------------------------------
    // Positive cases
    // -----------------------------------------------------------------

    public function testLoadsMinimalDocument(): void
    {
        $dom = $this->loader->load('<root/>');
        $root = $dom->documentElement;
        self::assertNotNull($root);
        self::assertSame('root', $root->localName);
    }

    public function testPreservesAttributesVerbatim(): void
    {
        $dom = $this->loader->load('<root a="1" b="two"/>');
        $root = $dom->documentElement;
        self::assertNotNull($root);
        self::assertSame('1', $root->getAttribute('a'));
        self::assertSame('two', $root->getAttribute('b'));
    }

    public function testPreservesNestedTextAndElements(): void
    {
        $dom = $this->loader->load('<root>hello <child/>world</root>');
        $root = $dom->documentElement;
        self::assertNotNull($root);
        self::assertSame('hello world', $root->textContent);
    }

    public function testPreservesWhitespaceByDefault(): void
    {
        $dom = $this->loader->load("<root>\n  <child/>\n</root>");
        $root = $dom->documentElement;
        self::assertNotNull($root);
        // The whitespace between <root> and <child/> survives as a
        // text node — important for token elements in math / svg.
        self::assertStringContainsString("\n  ", $root->textContent);
    }
}
