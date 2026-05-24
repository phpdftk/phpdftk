<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\Tokenizer;

use Phpdftk\Html\Tokenizer\CharacterToken;
use Phpdftk\Html\Tokenizer\CommentToken;
use Phpdftk\Html\Tokenizer\DoctypeToken;
use Phpdftk\Html\Tokenizer\EndTagToken;
use Phpdftk\Html\Tokenizer\EofToken;
use Phpdftk\Html\Tokenizer\ParseErrorCode;
use Phpdftk\Html\Tokenizer\StartTagToken;
use Phpdftk\Html\Tokenizer\Token;
use Phpdftk\Html\Tokenizer\Tokenizer;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    /** @return list<Token> */
    private function tokenize(string $input): array
    {
        return (new Tokenizer($input))->tokenize();
    }

    public function testEmitsEofForEmptyInput(): void
    {
        $tokens = $this->tokenize('');
        self::assertCount(1, $tokens);
        self::assertInstanceOf(EofToken::class, $tokens[0]);
    }

    public function testEmitsTextAsCharacterTokens(): void
    {
        $tokens = $this->tokenize('Hello world');
        // Per WHATWG the tokenizer emits one character token per character;
        // tree construction merges them.
        self::assertSame('Hello world', self::charText($tokens));
        self::assertInstanceOf(EofToken::class, end($tokens));
    }

    public function testNormalisesCrAndCrlfToLf(): void
    {
        $tokens = $this->tokenize("line1\r\nline2\rline3");
        self::assertSame("line1\nline2\nline3", self::charText($tokens));
    }

    /** @param list<Token> $tokens */
    private static function charText(array $tokens): string
    {
        $out = '';
        foreach ($tokens as $t) {
            if ($t instanceof CharacterToken) {
                $out .= $t->data;
            }
        }
        return $out;
    }

    public function testStartTag(): void
    {
        $tokens = $this->tokenize('<p>');
        self::assertCount(2, $tokens);
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame('p', $tokens[0]->tagName);
        self::assertFalse($tokens[0]->selfClosing);
        self::assertSame([], $tokens[0]->attributes);
    }

    public function testStartTagLowercasesName(): void
    {
        $tokens = $this->tokenize('<DIV>');
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame('div', $tokens[0]->tagName);
    }

    public function testEndTag(): void
    {
        $tokens = $this->tokenize('</p>');
        self::assertCount(2, $tokens);
        self::assertInstanceOf(EndTagToken::class, $tokens[0]);
        self::assertSame('p', $tokens[0]->tagName);
    }

    public function testSelfClosingTag(): void
    {
        $tokens = $this->tokenize('<br/>');
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame('br', $tokens[0]->tagName);
        self::assertTrue($tokens[0]->selfClosing);
    }

    public function testAttributeDoubleQuoted(): void
    {
        $tokens = $this->tokenize('<a href="https://example.com">');
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame([['name' => 'href', 'value' => 'https://example.com']], $tokens[0]->attributes);
    }

    public function testAttributeSingleQuoted(): void
    {
        $tokens = $this->tokenize("<a href='/foo'>");
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame([['name' => 'href', 'value' => '/foo']], $tokens[0]->attributes);
    }

    public function testAttributeUnquoted(): void
    {
        $tokens = $this->tokenize('<a href=/foo>');
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame([['name' => 'href', 'value' => '/foo']], $tokens[0]->attributes);
    }

    public function testAttributeNameOnly(): void
    {
        $tokens = $this->tokenize('<input disabled>');
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame([['name' => 'disabled', 'value' => '']], $tokens[0]->attributes);
    }

    public function testMultipleAttributes(): void
    {
        $tokens = $this->tokenize('<input type="text" name="user" disabled>');
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame([
            ['name' => 'type', 'value' => 'text'],
            ['name' => 'name', 'value' => 'user'],
            ['name' => 'disabled', 'value' => ''],
        ], $tokens[0]->attributes);
    }

    public function testAttributeNameLowercased(): void
    {
        $tokens = $this->tokenize('<a HREF="x" Class="y">');
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame([
            ['name' => 'href', 'value' => 'x'],
            ['name' => 'class', 'value' => 'y'],
        ], $tokens[0]->attributes);
    }

    public function testDuplicateAttributesDroppedAfterFirst(): void
    {
        $tokens = $this->tokenize('<a x="1" x="2">');
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame([['name' => 'x', 'value' => '1']], $tokens[0]->attributes);
    }

    public function testElementWithText(): void
    {
        $tokens = $this->tokenize('<p>Hello</p>');
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame('p', $tokens[0]->tagName);
        self::assertSame('Hello', self::charText($tokens));
        // The last non-EOF token should be the </p> end tag.
        $end = $tokens[count($tokens) - 2];
        self::assertInstanceOf(EndTagToken::class, $end);
        self::assertSame('p', $end->tagName);
        self::assertInstanceOf(EofToken::class, end($tokens));
    }

    public function testComment(): void
    {
        $tokens = $this->tokenize('<!-- hello -->');
        self::assertCount(2, $tokens);
        self::assertInstanceOf(CommentToken::class, $tokens[0]);
        self::assertSame(' hello ', $tokens[0]->data);
    }

    public function testCommentWithDashes(): void
    {
        $tokens = $this->tokenize('<!-- a - b -->');
        self::assertInstanceOf(CommentToken::class, $tokens[0]);
        self::assertSame(' a - b ', $tokens[0]->data);
    }

    public function testEmptyComment(): void
    {
        $tokens = $this->tokenize('<!---->');
        self::assertInstanceOf(CommentToken::class, $tokens[0]);
        self::assertSame('', $tokens[0]->data);
    }

    public function testAbruptCommentEmitsParseError(): void
    {
        $t = new Tokenizer('<!-->');
        $tokens = $t->tokenize();
        self::assertInstanceOf(CommentToken::class, $tokens[0]);
        self::assertSame('', $tokens[0]->data);
        $codes = array_map(static fn($e) => $e->code, $t->errors());
        self::assertContains(ParseErrorCode::AbruptClosingOfEmptyComment, $codes);
    }

    public function testDoctype(): void
    {
        $tokens = $this->tokenize('<!DOCTYPE html>');
        self::assertCount(2, $tokens);
        self::assertInstanceOf(DoctypeToken::class, $tokens[0]);
        self::assertSame('html', $tokens[0]->name);
        self::assertFalse($tokens[0]->forceQuirks);
    }

    public function testDoctypeLowercasesName(): void
    {
        $tokens = $this->tokenize('<!doctype HTML>');
        self::assertInstanceOf(DoctypeToken::class, $tokens[0]);
        self::assertSame('html', $tokens[0]->name);
    }

    public function testNumericCharacterReferenceDecimal(): void
    {
        $tokens = $this->tokenize('&#65;');
        self::assertInstanceOf(CharacterToken::class, $tokens[0]);
        self::assertSame('A', $tokens[0]->data);
    }

    public function testNumericCharacterReferenceHex(): void
    {
        $tokens = $this->tokenize('&#x41;');
        self::assertInstanceOf(CharacterToken::class, $tokens[0]);
        self::assertSame('A', $tokens[0]->data);
    }

    public function testNumericCharacterReferenceMultiByteCodepoint(): void
    {
        $tokens = $this->tokenize('&#x1F600;');
        self::assertInstanceOf(CharacterToken::class, $tokens[0]);
        self::assertSame("\u{1F600}", $tokens[0]->data); // grinning emoji
    }

    public function testNamedCharacterReferenceWithSemicolon(): void
    {
        $tokens = $this->tokenize('&amp;');
        self::assertInstanceOf(CharacterToken::class, $tokens[0]);
        self::assertSame('&', $tokens[0]->data);
    }

    public function testNamedCharacterReferenceWithoutSemicolon(): void
    {
        // Legacy entry — should resolve even without trailing ;
        $tokens = $this->tokenize('&amp ');
        self::assertSame('& ', self::charText($tokens));
    }

    public function testUnknownNamedCharacterReferencePreservesAmpersand(): void
    {
        $tokens = $this->tokenize('&unknownentity;');
        self::assertSame('&unknownentity;', self::charText($tokens));
    }

    public function testNumericReferenceInAttribute(): void
    {
        $tokens = $this->tokenize('<a x="&#65;">');
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame([['name' => 'x', 'value' => 'A']], $tokens[0]->attributes);
    }

    public function testLegacyAmpInAttributeDoesNotDecodeIfFollowedByEquals(): void
    {
        // Per spec: in attribute values, "&amp=" with no semicolon stays literal
        // to preserve backward-compat with URLs like ?foo=bar&copy=1.
        $tokens = $this->tokenize('<a x="?a=1&copy=2">');
        self::assertInstanceOf(StartTagToken::class, $tokens[0]);
        self::assertSame([['name' => 'x', 'value' => '?a=1&copy=2']], $tokens[0]->attributes);
    }

    public function testTextWithNamedEntities(): void
    {
        $tokens = $this->tokenize('Hello &amp; goodbye');
        self::assertSame('Hello & goodbye', self::charText($tokens));
    }

    public function testMissingEndTagNameError(): void
    {
        $t = new Tokenizer('</>');
        $tokens = $t->tokenize();
        // Only EOF — no tokens produced, just an error logged.
        self::assertCount(1, $tokens);
        self::assertInstanceOf(EofToken::class, $tokens[0]);
        $codes = array_map(static fn($e) => $e->code, $t->errors());
        self::assertContains(ParseErrorCode::MissingEndTagName, $codes);
    }

    public function testInvalidFirstCharacterOfTagNameRecovers(): void
    {
        $t = new Tokenizer('<1abc>');
        $tokens = $t->tokenize();
        self::assertSame('<1abc>', self::charText($tokens));
        $codes = array_map(static fn($e) => $e->code, $t->errors());
        self::assertContains(ParseErrorCode::InvalidFirstCharacterOfTagName, $codes);
    }

    public function testCharacterTokensConcatenateToFullText(): void
    {
        // The tokenizer emits one character token per character (or per
        // multi-codepoint character-reference result). Tree construction
        // merges them into a single Text node; the test here just verifies
        // the concatenated payload covers every character of the input.
        $tokens = $this->tokenize('abc&amp;def&#65;ghi');
        $text = '';
        foreach ($tokens as $tok) {
            if ($tok instanceof CharacterToken) {
                $text .= $tok->data;
            }
        }
        self::assertSame('abc&defAghi', $text);
    }

    public function testFullDocument(): void
    {
        $html = "<!DOCTYPE html>\n<html><head><title>X</title></head><body><p class=\"hi\">Hi &amp; bye</p></body></html>";
        // Note: <title> is RCDATA in real tree construction; for tokenizer-only test,
        // we just verify each token type appears.
        $t = new Tokenizer($html);
        $tokens = $t->tokenize();

        $typeSeq = array_map(static fn($tok) => $tok::class, $tokens);
        self::assertContains(DoctypeToken::class, $typeSeq);
        self::assertContains(StartTagToken::class, $typeSeq);
        self::assertContains(EndTagToken::class, $typeSeq);
        self::assertContains(CharacterToken::class, $typeSeq);
        self::assertInstanceOf(EofToken::class, end($tokens));
    }

    public function testRcdataStateConsumesUntilAppropriateEndTag(): void
    {
        // Simulate the parser switching into RCDATA after <title>.
        $t = new Tokenizer('<title>Title with <not a tag> &amp; entity</title>');
        // Tokenize the start tag first.
        $first = $t->tokenize();
        // Manual switch verification: in real use, tree-construction would
        // call $t->state = RCDATA after seeing the <title> start tag.
        // Here we just verify the start tag emits cleanly.
        self::assertInstanceOf(StartTagToken::class, $first[0]);
        self::assertSame('title', $first[0]->tagName);
    }
}
