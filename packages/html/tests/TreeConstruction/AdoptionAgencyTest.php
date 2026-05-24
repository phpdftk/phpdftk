<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\TreeConstruction;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Parser;
use Phpdftk\Html\Serializer;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the adoption agency algorithm (WHATWG §13.2.6.4.7) — the
 * famous "Algorithm A" that recovers from misnested formatting elements.
 *
 * Tests focus on observable DOM-shape outcomes rather than exact spec-text
 * equivalence: in particular, that the formatting wrapper survives across
 * the (incorrectly placed) block boundary, and that no infinite loop or
 * spurious dropped content occurs.
 */
final class AdoptionAgencyTest extends TestCase
{
    private function parse(string $html): Document
    {
        return (new Parser())->parseDocument($html);
    }

    private function bodyHtml(Document $doc): string
    {
        return (new Serializer())->serialize($doc->body ?? $doc);
    }

    public function testProperlyNestedFormattingNoOp(): void
    {
        // Correctly nested input should round-trip without disturbance.
        $doc = $this->parse('<!DOCTYPE html><body><b><i>hello</i></b>');
        $b = $doc->getElementsByTagName('b')[0] ?? null;
        self::assertNotNull($b);
        $i = $b->getElementsByTagName('i')[0] ?? null;
        self::assertNotNull($i);
        self::assertSame('hello', $i->textContent());
    }

    public function testMisnestedBoldItalic(): void
    {
        // <b>1<i>2</b>3</i> — the </b> closes <b> before </i>. AAA should
        // clone <i> so the "3" remains inside an <i> wrapper.
        $doc = $this->parse('<!DOCTYPE html><body><b>1<i>2</b>3</i>');
        $body = $doc->body;
        self::assertNotNull($body);
        self::assertStringContainsString('1', $body->textContent());
        self::assertStringContainsString('2', $body->textContent());
        self::assertStringContainsString('3', $body->textContent());

        // Both <b> and <i> should still exist; "2" inside both, "3" inside <i>.
        $is = $body->getElementsByTagName('i');
        self::assertNotEmpty($is, '<i> should still be present after AAA');

        // The "3" should be inside an <i> element somewhere.
        $foundThreeInI = false;
        foreach ($is as $iEl) {
            if (str_contains($iEl->textContent(), '3')) {
                $foundThreeInI = true;
            }
        }
        self::assertTrue($foundThreeInI, 'Trailing "3" should be wrapped in <i>');
    }

    public function testFormattingClosesBeforeBlock(): void
    {
        // <b><p>x</b></p> — close <b> while <p> is still open. AAA should
        // not lose any content.
        $doc = $this->parse('<!DOCTYPE html><body><b><p>x</b></p>');
        $body = $doc->body;
        self::assertNotNull($body);
        self::assertStringContainsString('x', $body->textContent());

        // The <p> should still be present after AAA.
        $ps = $body->getElementsByTagName('p');
        self::assertNotEmpty($ps);
    }

    public function testRepeatedAnchorRunsAaaOnSecond(): void
    {
        // <a>first<a>second — the second <a> triggers AAA against the first,
        // closing it before the second opens.
        $doc = $this->parse('<!DOCTYPE html><body><a>first<a>second');
        $body = $doc->body;
        self::assertNotNull($body);

        // Should have two <a> elements; the first should NOT contain the second.
        $anchors = $body->getElementsByTagName('a');
        self::assertCount(2, $anchors);
        foreach ($anchors as $a) {
            $nested = $a->getElementsByTagName('a');
            self::assertCount(0, $nested, 'Anchors must not be nested after AAA');
        }
        self::assertStringContainsString('first', $anchors[0]->textContent());
        self::assertStringContainsString('second', $anchors[1]->textContent());
    }

    public function testMultipleFormattingLevelsClosedOutOfOrder(): void
    {
        // <b><i><u>x</b></u></i> — three levels closed in wrong order.
        $doc = $this->parse('<!DOCTYPE html><body><b><i><u>x</b></u></i>');
        $body = $doc->body;
        self::assertNotNull($body);

        // All four elements should appear in the resulting DOM.
        self::assertNotEmpty($body->getElementsByTagName('b'));
        self::assertNotEmpty($body->getElementsByTagName('i'));
        self::assertNotEmpty($body->getElementsByTagName('u'));
        self::assertStringContainsString('x', $body->textContent());
    }

    public function testAaaDoesNotInfiniteLoopOnPathologicalInput(): void
    {
        // Specifically crafted to make a naive AAA spin: many alternating
        // formatting elements.
        $input = '<!DOCTYPE html><body>'
            . str_repeat('<b><i>', 10)
            . 'inside'
            . str_repeat('</i></b>', 10);
        $start = microtime(true);
        $doc = $this->parse($input);
        $elapsed = microtime(true) - $start;
        self::assertLessThan(1.0, $elapsed, 'AAA should complete promptly on pathological input');
        self::assertStringContainsString('inside', $doc->body->textContent());
    }

    public function testAaaOnFontElement(): void
    {
        // <font> is in the formatting-element list; AAA should handle it too.
        $doc = $this->parse('<!DOCTYPE html><body><font color="red">red <b>and bold</font></b>');
        $body = $doc->body;
        self::assertNotNull($body);
        $font = $body->getElementsByTagName('font')[0] ?? null;
        self::assertNotNull($font);
        self::assertSame('red', $font->getAttribute('color'));
        self::assertStringContainsString('red', $font->textContent());
    }

    public function testEndTagWithNoMatchingFormattingFallsBackGracefully(): void
    {
        // </b> with no <b> open — AAA falls through to "any other end tag" path.
        $doc = $this->parse('<!DOCTYPE html><body>hello</b>world');
        $body = $doc->body;
        self::assertNotNull($body);
        // The stray </b> should be a parse error but content survives.
        self::assertStringContainsString('hello', $body->textContent());
        self::assertStringContainsString('world', $body->textContent());
    }

    public function testFormattingPersistsAcrossParagraphBoundary(): void
    {
        // <b>1<p>2</p>3</b> — <p> closes inside <b>; AAA + reconstruction
        // should ensure "3" remains wrapped in <b>.
        $doc = $this->parse('<!DOCTYPE html><body><b>1<p>2</p>3</b>');
        $body = $doc->body;
        self::assertNotNull($body);
        self::assertStringContainsString('1', $body->textContent());
        self::assertStringContainsString('2', $body->textContent());
        self::assertStringContainsString('3', $body->textContent());

        // There should be a <b> wrapping "3" after the <p>.
        $bs = $body->getElementsByTagName('b');
        $foundThreeInB = false;
        foreach ($bs as $b) {
            if (str_contains($b->textContent(), '3')) {
                $foundThreeInB = true;
                break;
            }
        }
        self::assertTrue($foundThreeInB, '"3" should remain inside a <b> after the <p> closes');
    }
}
