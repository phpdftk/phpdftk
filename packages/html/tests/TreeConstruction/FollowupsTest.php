<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\TreeConstruction;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\DocumentFragment;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Dom\HTMLTemplateElement;
use Phpdftk\Html\Parser;
use Phpdftk\Html\ParserOptions;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the final 1B.3 follow-ups:
 *  - Noah's Ark dedup in ActiveFormattingElements
 *  - InHeadNoscript (only when scripting is enabled)
 *  - InFrameset / AfterFrameset / AfterAfterFrameset
 *  - Fragment parsing (context-aware initial state)
 */
final class FollowupsTest extends TestCase
{
    private function parse(string $html): Document
    {
        return (new Parser())->parseDocument($html);
    }

    // ============================================================
    // Noah's Ark
    // ============================================================

    public function testNoahsArkLimitsIdenticalFormattingElements(): void
    {
        // Four identical <b> tags should not all live in the AFE list;
        // earliest match gets dropped when the 4th is pushed.
        $html = '<!DOCTYPE html><body><b>1</b><b>2</b><b>3</b><b>4</b>';
        $start = microtime(true);
        $doc = $this->parse($html);
        $elapsed = microtime(true) - $start;
        self::assertLessThan(1.0, $elapsed);

        // All content survives.
        $body = $doc->body;
        self::assertNotNull($body);
        self::assertStringContainsString('1', $body->textContent());
        self::assertStringContainsString('4', $body->textContent());
    }

    public function testNoahsArkAttributesDistinguish(): void
    {
        // Different attributes mean they DON'T match — all entries survive.
        $html = '<!DOCTYPE html><body><b id="a">a</b><b id="b">b</b><b id="c">c</b><b id="d">d</b>';
        $doc = $this->parse($html);
        $bs = $doc->getElementsByTagName('b');
        self::assertCount(4, $bs);
    }

    // ============================================================
    // InHeadNoscript (scripting enabled)
    // ============================================================

    public function testNoscriptWithScriptingEnabledIsRawText(): void
    {
        $parser = new Parser(new ParserOptions(scriptingEnabled: true));
        $doc = $parser->parseDocument('<!DOCTYPE html><head><noscript><meta charset="utf-8"></noscript></head>');
        $head = $doc->head;
        self::assertNotNull($head);
        $noscript = $head->getElementsByTagName('noscript')[0] ?? null;
        self::assertNotNull($noscript);
        // With scripting=true, the <meta> inside <noscript> is text, not a child element.
        self::assertCount(0, $noscript->getElementsByTagName('meta'));
        self::assertStringContainsString('meta charset', $noscript->textContent());
    }

    public function testNoscriptWithScriptingDisabledIsParsedNormally(): void
    {
        // Default: scripting=false. <noscript> content is parsed as flow.
        $doc = $this->parse('<!DOCTYPE html><head><noscript><meta charset="utf-8"></noscript></head>');
        $head = $doc->head;
        self::assertNotNull($head);
        $noscript = $head->getElementsByTagName('noscript')[0] ?? null;
        self::assertNotNull($noscript);
        $meta = $noscript->getElementsByTagName('meta')[0] ?? null;
        self::assertNotNull($meta);
        self::assertSame('utf-8', $meta->getAttribute('charset'));
    }

    // ============================================================
    // Framesets
    // ============================================================

    public function testFramesetDocument(): void
    {
        $html = '<!DOCTYPE html><html><head></head><frameset cols="50%,50%"><frame src="a.html"><frame src="b.html"></frameset></html>';
        $doc = $this->parse($html);
        $frameset = $doc->getElementsByTagName('frameset')[0] ?? null;
        self::assertNotNull($frameset);
        self::assertSame('50%,50%', $frameset->getAttribute('cols'));
        $frames = $frameset->getElementsByTagName('frame');
        self::assertCount(2, $frames);
        self::assertSame('a.html', $frames[0]->getAttribute('src'));
        self::assertSame('b.html', $frames[1]->getAttribute('src'));
    }

    public function testNestedFramesets(): void
    {
        $html = '<!DOCTYPE html><html><frameset cols="100,*"><frame src="a.html"><frameset rows="50%,50%"><frame src="b.html"><frame src="c.html"></frameset></frameset></html>';
        $doc = $this->parse($html);
        $outer = $doc->getElementsByTagName('frameset')[0] ?? null;
        self::assertNotNull($outer);
        $inner = $outer->children()[1] ?? null;
        self::assertNotNull($inner);
        self::assertSame('frameset', $inner->localName);
        self::assertSame('50%,50%', $inner->getAttribute('rows'));
    }

    public function testNoframesInsideFrameset(): void
    {
        $html = '<!DOCTYPE html><html><frameset><frame src="x"></frameset><noframes>You need frames.</noframes></html>';
        $doc = $this->parse($html);
        $noframes = $doc->getElementsByTagName('noframes')[0] ?? null;
        self::assertNotNull($noframes);
        self::assertStringContainsString('You need frames', $noframes->textContent());
    }

    public function testFramesetDoesNotReplaceBodyWhenFramesetOkIsFalse(): void
    {
        // Any content prior makes framesetOk false. <frameset> then ignored.
        $html = '<!DOCTYPE html><body>some text<frameset></frameset></body>';
        $doc = $this->parse($html);
        // The body should still contain "some text" and NO frameset should
        // have replaced it.
        $body = $doc->body;
        self::assertNotNull($body);
        self::assertStringContainsString('some text', $body->textContent());
        self::assertCount(0, $doc->getElementsByTagName('frameset'));
    }

    // ============================================================
    // Fragment parsing
    // ============================================================

    public function testParseFragmentInBodyContext(): void
    {
        $doc = new Document();
        $bodyContext = $doc->createElement('body');

        $fragment = (new Parser())->parseFragment('<p>Hello</p><div>World</div>', $bodyContext);
        self::assertInstanceOf(DocumentFragment::class, $fragment);
        $children = $fragment->childNodes();
        self::assertCount(2, $children);
        self::assertSame('p', $children[0]->localName);
        self::assertSame('Hello', $children[0]->textContent());
        self::assertSame('div', $children[1]->localName);
        self::assertSame('World', $children[1]->textContent());
    }

    public function testParseFragmentInTitleContextIsRcdata(): void
    {
        // <title> uses RCDATA — angle brackets are character data.
        $doc = new Document();
        $titleContext = $doc->createElement('title');

        $fragment = (new Parser())->parseFragment('Hello <not a tag> &amp; world', $titleContext);
        self::assertSame('Hello <not a tag> & world', $fragment->textContent());
    }

    public function testParseFragmentInStyleContextIsRawtext(): void
    {
        $doc = new Document();
        $styleContext = $doc->createElement('style');

        $fragment = (new Parser())->parseFragment('a > b { color: red; }', $styleContext);
        self::assertSame('a > b { color: red; }', $fragment->textContent());
    }

    public function testParseFragmentInTableContext(): void
    {
        // Parsing inside <table> means <tr> opens directly (with implicit tbody).
        $doc = new Document();
        $tableContext = $doc->createElement('table');

        $fragment = (new Parser())->parseFragment('<tr><td>cell</td></tr>', $tableContext);
        // Walk into the fragment looking for tbody/tr/td.
        $found = false;
        foreach ($fragment->childNodes() as $kid) {
            if ($kid instanceof Element && $kid->localName === 'tbody') {
                $tr = $kid->children()[0] ?? null;
                if ($tr !== null && $tr->localName === 'tr') {
                    $td = $tr->children()[0] ?? null;
                    if ($td !== null && $td->localName === 'td' && $td->textContent() === 'cell') {
                        $found = true;
                    }
                }
            }
        }
        self::assertTrue($found, 'Fragment in table context should produce tbody>tr>td');
    }

    public function testParseFragmentInTrContext(): void
    {
        // Parsing inside <tr> — <td> opens directly.
        $doc = new Document();
        $trContext = $doc->createElement('tr');

        $fragment = (new Parser())->parseFragment('<td>a</td><td>b</td>', $trContext);
        $cells = [];
        foreach ($fragment->childNodes() as $kid) {
            if ($kid instanceof Element && $kid->localName === 'td') {
                $cells[] = $kid;
            }
        }
        self::assertCount(2, $cells);
        self::assertSame('a', $cells[0]->textContent());
        self::assertSame('b', $cells[1]->textContent());
    }

    public function testParseFragmentInTemplateContext(): void
    {
        $doc = new Document();
        $template = $doc->createElement('template');

        $fragment = (new Parser())->parseFragment('<p>x</p>', $template);
        self::assertInstanceOf(DocumentFragment::class, $fragment);
        $p = null;
        foreach ($fragment->childNodes() as $kid) {
            if ($kid instanceof Element && $kid->localName === 'p') {
                $p = $kid;
                break;
            }
        }
        self::assertNotNull($p);
        self::assertSame('x', $p->textContent());
    }

    public function testParseFragmentFindsAncestorForm(): void
    {
        $doc = new Document();
        $form = $doc->createElement('form');
        $div = $doc->createElement('div');
        $form->appendChild($div);

        // The fragment parser walks up from context looking for a form ancestor.
        // We just verify it doesn't crash when one is found.
        $fragment = (new Parser())->parseFragment('<input name="x">', $div);
        $input = null;
        foreach ($fragment->childNodes() as $kid) {
            if ($kid instanceof Element && $kid->localName === 'input') {
                $input = $kid;
                break;
            }
        }
        self::assertNotNull($input);
    }
}
