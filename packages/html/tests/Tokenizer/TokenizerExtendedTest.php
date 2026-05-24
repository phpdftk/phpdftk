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
use Phpdftk\Html\Tokenizer\TokenizerState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1B.2-bis coverage: script-data escape states, DOCTYPE PUBLIC/SYSTEM
 * identifiers, CDATA sections, comment nested-comment recovery.
 */
final class TokenizerExtendedTest extends TestCase
{
    // ============================================================
    // DOCTYPE PUBLIC / SYSTEM identifiers
    // ============================================================

    public function testDoctypePublicIdentifier(): void
    {
        $tokens = $this->tokenize('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN">');
        self::assertInstanceOf(DoctypeToken::class, $tokens[0]);
        self::assertSame('html', $tokens[0]->name);
        self::assertSame('-//W3C//DTD HTML 4.01//EN', $tokens[0]->publicId);
        self::assertNull($tokens[0]->systemId);
        self::assertFalse($tokens[0]->forceQuirks);
    }

    public function testDoctypePublicAndSystemIdentifiers(): void
    {
        $tokens = $this->tokenize(
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
        );
        self::assertInstanceOf(DoctypeToken::class, $tokens[0]);
        self::assertSame('html', $tokens[0]->name);
        self::assertSame('-//W3C//DTD XHTML 1.0 Transitional//EN', $tokens[0]->publicId);
        self::assertSame('http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd', $tokens[0]->systemId);
    }

    public function testDoctypeSystemIdentifierOnly(): void
    {
        $tokens = $this->tokenize('<!DOCTYPE html SYSTEM "about:legacy-compat">');
        self::assertInstanceOf(DoctypeToken::class, $tokens[0]);
        self::assertSame('about:legacy-compat', $tokens[0]->systemId);
        self::assertNull($tokens[0]->publicId);
    }

    public function testDoctypeSingleQuotedIdentifier(): void
    {
        $tokens = $this->tokenize("<!DOCTYPE html SYSTEM 'about:legacy-compat'>");
        self::assertInstanceOf(DoctypeToken::class, $tokens[0]);
        self::assertSame('about:legacy-compat', $tokens[0]->systemId);
    }

    public function testDoctypePublicKeywordIsCaseInsensitive(): void
    {
        $tokens = $this->tokenize('<!doctype html public "x">');
        self::assertInstanceOf(DoctypeToken::class, $tokens[0]);
        self::assertSame('x', $tokens[0]->publicId);
    }

    public function testDoctypeMissingPublicIdentifierForcesQuirks(): void
    {
        $t = new Tokenizer('<!DOCTYPE html PUBLIC>');
        $tokens = $t->tokenize();
        self::assertInstanceOf(DoctypeToken::class, $tokens[0]);
        self::assertTrue($tokens[0]->forceQuirks);
        $codes = array_map(static fn($e) => $e->code, $t->errors());
        self::assertContains(ParseErrorCode::MissingDoctypePublicIdentifier, $codes);
    }

    public function testDoctypeAbruptPublicIdentifierForcesQuirks(): void
    {
        $t = new Tokenizer('<!DOCTYPE html PUBLIC "abrupt>');
        $tokens = $t->tokenize();
        self::assertInstanceOf(DoctypeToken::class, $tokens[0]);
        self::assertTrue($tokens[0]->forceQuirks);
        self::assertSame('abrupt', $tokens[0]->publicId);
        $codes = array_map(static fn($e) => $e->code, $t->errors());
        self::assertContains(ParseErrorCode::AbruptDoctypePublicIdentifier, $codes);
    }

    public function testDoctypeWithoutPublicSystemFallsToBogus(): void
    {
        $t = new Tokenizer('<!DOCTYPE html garbage>');
        $tokens = $t->tokenize();
        self::assertInstanceOf(DoctypeToken::class, $tokens[0]);
        self::assertTrue($tokens[0]->forceQuirks);
        $codes = array_map(static fn($e) => $e->code, $t->errors());
        self::assertContains(ParseErrorCode::InvalidCharacterSequenceAfterDoctypeName, $codes);
    }

    // ============================================================
    // Script data states
    // ============================================================

    public function testScriptDataEmitsContentAndAppropriateEndTag(): void
    {
        $t = new Tokenizer('var x = 1;</script>');
        $t->state = TokenizerState::ScriptData;
        $t->lastStartTagName = 'script';
        $tokens = $t->tokenize();

        $endTag = self::find($tokens, EndTagToken::class);
        self::assertNotNull($endTag);
        self::assertSame('script', $endTag->tagName);

        $chars = self::collectText($tokens);
        self::assertSame('var x = 1;', $chars);
    }

    public function testScriptDataIgnoresInappropriateEndTag(): void
    {
        // </s> is not an "appropriate end tag" because the last start was
        // (simulated to be) <script>; the </s> should be emitted as text.
        $t = new Tokenizer('foo</s>bar</script>');
        $t->state = TokenizerState::ScriptData;
        $t->lastStartTagName = 'script';
        $tokens = $t->tokenize();

        $endTag = self::find($tokens, EndTagToken::class);
        self::assertNotNull($endTag);
        self::assertSame('script', $endTag->tagName);

        $chars = self::collectText($tokens);
        self::assertSame('foo</s>bar', $chars);
    }

    public function testScriptDataEscapedComment(): void
    {
        $t = new Tokenizer('<!-- comment-like --></script>');
        $t->state = TokenizerState::ScriptData;
        $t->lastStartTagName = 'script';
        $tokens = $t->tokenize();

        $endTag = self::find($tokens, EndTagToken::class);
        self::assertNotNull($endTag);
        self::assertSame('script', $endTag->tagName);
        self::assertSame('<!-- comment-like -->', self::collectText($tokens));
    }

    public function testScriptDataDoubleEscapeWithNestedScript(): void
    {
        // Tricky case: HTML-comment-style escape with nested <script> ... </script>
        $input = '<!--<script>var x;</script>--></script>';
        $t = new Tokenizer($input);
        $t->state = TokenizerState::ScriptData;
        $t->lastStartTagName = 'script';
        $tokens = $t->tokenize();

        // The outer </script> should be the appropriate end tag.
        $endTags = array_filter($tokens, static fn($tok) => $tok instanceof EndTagToken);
        $endTags = array_values($endTags);
        self::assertNotEmpty($endTags);
        self::assertSame('script', end($endTags)->tagName);
    }

    // ============================================================
    // CDATA sections
    // ============================================================

    public function testCdataSectionInForeignContent(): void
    {
        $t = new Tokenizer('<![CDATA[Raw <text> & data]]>');
        $t->inForeignContent = true;
        $tokens = $t->tokenize();

        $text = self::collectText($tokens);
        self::assertSame('Raw <text> & data', $text);
    }

    public function testCdataInHtmlContentBecomesBogusCommentWithError(): void
    {
        $t = new Tokenizer('<![CDATA[ignored]]>');
        $t->inForeignContent = false;
        $tokens = $t->tokenize();

        self::assertInstanceOf(CommentToken::class, $tokens[0]);
        self::assertStringContainsString('[CDATA[', $tokens[0]->data);

        $codes = array_map(static fn($e) => $e->code, $t->errors());
        self::assertContains(ParseErrorCode::CdataInHtmlContent, $codes);
    }

    public function testCdataSectionWithDoubleBracket(): void
    {
        // "]]" inside a CDATA section that is NOT followed by ">" should be
        // emitted verbatim rather than terminating.
        $t = new Tokenizer('<![CDATA[a]]b]]>');
        $t->inForeignContent = true;
        $tokens = $t->tokenize();

        self::assertSame('a]]b', self::collectText($tokens));
    }

    // ============================================================
    // Comment nested-comment recovery
    // ============================================================

    public function testNestedCommentEmitsParseError(): void
    {
        $t = new Tokenizer('<!-- outer <!-- inner -->');
        $tokens = $t->tokenize();
        self::assertInstanceOf(CommentToken::class, $tokens[0]);
        $codes = array_map(static fn($e) => $e->code, $t->errors());
        self::assertContains(ParseErrorCode::NestedComment, $codes);
    }

    // ============================================================
    // Named character reference table coverage (hand-curated subset)
    // ============================================================

    #[DataProvider('provideNamedEntityResolutions')]
    public function testNamedEntityResolution(string $input, string $expected): void
    {
        $tokens = $this->tokenize($input);
        self::assertInstanceOf(CharacterToken::class, $tokens[0]);
        self::assertSame($expected, $tokens[0]->data);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideNamedEntityResolutions(): iterable
    {
        // Legacy
        yield 'amp;' => ['&amp;', '&'];
        yield 'copy;' => ['&copy;', "\u{00A9}"];
        // Typography
        yield 'mdash;' => ['&mdash;', "\u{2014}"];
        yield 'hellip;' => ['&hellip;', "\u{2026}"];
        // Currency
        yield 'euro;' => ['&euro;', "\u{20AC}"];
        // Greek
        yield 'alpha;' => ['&alpha;', "\u{03B1}"];
        yield 'Pi;' => ['&Pi;', "\u{03A0}"];
        // Math
        yield 'le;' => ['&le;', "\u{2264}"];
        yield 'forall;' => ['&forall;', "\u{2200}"];
        // Arrows
        yield 'rArr;' => ['&rArr;', "\u{21D2}"];
        // Accented
        yield 'auml;' => ['&auml;', "\u{00E4}"];
        // Symbols
        yield 'hearts;' => ['&hearts;', "\u{2665}"];
    }

    // ============================================================
    // Helpers
    // ============================================================

    /** @return list<Token> */
    private function tokenize(string $input): array
    {
        return (new Tokenizer($input))->tokenize();
    }

    /**
     * @template T of Token
     * @param  list<Token> $tokens
     * @param  class-string<T> $class
     * @return T|null
     */
    private static function find(array $tokens, string $class): ?Token
    {
        foreach ($tokens as $t) {
            if ($t instanceof $class) {
                return $t;
            }
        }
        return null;
    }

    /** @param list<Token> $tokens */
    private static function collectText(array $tokens): string
    {
        $out = '';
        foreach ($tokens as $t) {
            if ($t instanceof CharacterToken) {
                $out .= $t->data;
            }
        }
        return $out;
    }
}
