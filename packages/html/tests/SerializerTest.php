<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\DocumentType;
use Phpdftk\Html\Dom\ShadowRootInit;
use Phpdftk\Html\Dom\ShadowRootMode;
use Phpdftk\Html\Serializer;
use PHPUnit\Framework\TestCase;

final class SerializerTest extends TestCase
{
    public function testSerializesSimpleElement(): void
    {
        $doc = new Document();
        $p = $doc->createElement('p');
        $p->appendChild($doc->createTextNode('Hello world'));
        $doc->appendChild($p);

        self::assertSame('<p>Hello world</p>', (new Serializer())->serialize($doc));
    }

    public function testEscapesTextContent(): void
    {
        $doc = new Document();
        $p = $doc->createElement('p');
        $p->appendChild($doc->createTextNode('<&>'));
        $doc->appendChild($p);

        self::assertSame('<p>&lt;&amp;&gt;</p>', (new Serializer())->serialize($doc));
    }

    public function testSerializesAttributes(): void
    {
        $doc = new Document();
        $a = $doc->createElement('a');
        $a->setAttribute('href', 'https://example.com/?x="quoted"&y=1');
        $a->setAttribute('class', 'btn primary');
        $a->appendChild($doc->createTextNode('link'));
        $doc->appendChild($a);

        self::assertSame(
            '<a href="https://example.com/?x=&quot;quoted&quot;&amp;y=1" class="btn primary">link</a>',
            (new Serializer())->serialize($doc),
        );
    }

    public function testEmitsVoidElementsWithoutClosing(): void
    {
        $doc = new Document();
        $img = $doc->createElement('img');
        $img->setAttribute('src', 'a.png');
        $br = $doc->createElement('br');
        $doc->appendChild($img);
        $doc->appendChild($br);

        self::assertSame('<img src="a.png"><br>', (new Serializer())->serialize($doc));
    }

    public function testRawTextElementsDoNotEscape(): void
    {
        $doc = new Document();
        $style = $doc->createElement('style');
        $style->appendChild($doc->createTextNode('a > b { color: red; }'));
        $doc->appendChild($style);

        self::assertSame('<style>a > b { color: red; }</style>', (new Serializer())->serialize($doc));
    }

    public function testEmitsDoctype(): void
    {
        $doc = new Document();
        $doc->appendChild(new DocumentType($doc, 'html'));
        $html = $doc->createElement('html');
        $doc->appendChild($html);

        self::assertSame('<!DOCTYPE html><html></html>', (new Serializer())->serialize($doc));
    }

    public function testEmitsComment(): void
    {
        $doc = new Document();
        $doc->appendChild($doc->createComment(' page header '));

        self::assertSame('<!-- page header -->', (new Serializer())->serialize($doc));
    }

    public function testSerialisableShadowRoot(): void
    {
        $doc = new Document();
        $host = $doc->createElement('div');
        $shadow = $host->attachShadow(ShadowRootMode::Open, new ShadowRootInit(serializable: true));
        $shadow->appendChild($doc->createTextNode('inside'));
        $doc->appendChild($host);

        self::assertSame(
            '<div><template shadowrootmode="open" shadowrootserializable="">inside</template></div>',
            (new Serializer())->serialize($doc),
        );
    }

    public function testNonSerializableShadowRootOmitted(): void
    {
        $doc = new Document();
        $host = $doc->createElement('div');
        $host->attachShadow(ShadowRootMode::Open); // serializable=false default
        $host->appendChild($doc->createTextNode('light'));
        $doc->appendChild($host);

        self::assertSame('<div>light</div>', (new Serializer())->serialize($doc));
    }
}
