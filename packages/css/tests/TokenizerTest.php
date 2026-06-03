<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Token\AtKeywordToken;
use Phpdftk\Css\Token\CdcToken;
use Phpdftk\Css\Token\CdoToken;
use Phpdftk\Css\Token\ColonToken;
use Phpdftk\Css\Token\CommaToken;
use Phpdftk\Css\Token\DelimToken;
use Phpdftk\Css\Token\DimensionToken;
use Phpdftk\Css\Token\EofToken;
use Phpdftk\Css\Token\FunctionToken;
use Phpdftk\Css\Token\HashToken;
use Phpdftk\Css\Token\HashTokenType;
use Phpdftk\Css\Token\IdentToken;
use Phpdftk\Css\Token\LeftBraceToken;
use Phpdftk\Css\Token\LeftParenToken;
use Phpdftk\Css\Token\NumberToken;
use Phpdftk\Css\Token\NumberTokenType;
use Phpdftk\Css\Token\PercentageToken;
use Phpdftk\Css\Token\RightBraceToken;
use Phpdftk\Css\Token\RightParenToken;
use Phpdftk\Css\Token\SemicolonToken;
use Phpdftk\Css\Token\StringToken;
use Phpdftk\Css\Token\Token;
use Phpdftk\Css\Token\UrlToken;
use Phpdftk\Css\Token\WhitespaceToken;
use Phpdftk\Css\Tokenizer;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    /** @return list<Token> */
    private function tokenize(string $input): array
    {
        return (new Tokenizer($input))->tokenize();
    }

    /**
     * Filter out whitespace tokens for easier "structural" comparisons.
     *
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private function withoutWhitespace(array $tokens): array
    {
        return array_values(array_filter($tokens, static fn(Token $t): bool => !$t instanceof WhitespaceToken));
    }

    public function testEmptyInputEmitsEof(): void
    {
        $tokens = $this->tokenize('');
        self::assertCount(1, $tokens);
        self::assertInstanceOf(EofToken::class, $tokens[0]);
    }

    public function testIdent(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('foo'));
        self::assertCount(2, $tokens);
        self::assertInstanceOf(IdentToken::class, $tokens[0]);
        self::assertSame('foo', $tokens[0]->value);
    }

    public function testIdentWithDash(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('-webkit-foo'));
        self::assertInstanceOf(IdentToken::class, $tokens[0]);
        self::assertSame('-webkit-foo', $tokens[0]->value);
    }

    public function testFunctionToken(): void
    {
        // Per CSS Syntax 3, `1+2` tokenizes as two NumberTokens (1 and +2)
        // — calc's grammar resolves the operator at the value-parser level.
        // To get a real DelimToken('+'), put whitespace around it.
        $tokens = $this->withoutWhitespace($this->tokenize('calc(1 + 2)'));
        self::assertInstanceOf(FunctionToken::class, $tokens[0]);
        self::assertSame('calc', $tokens[0]->name);
        self::assertInstanceOf(NumberToken::class, $tokens[1]);
        self::assertSame(1.0, $tokens[1]->value);
        self::assertInstanceOf(DelimToken::class, $tokens[2]);
        self::assertSame('+', $tokens[2]->value);
        self::assertInstanceOf(NumberToken::class, $tokens[3]);
        self::assertSame(2.0, $tokens[3]->value);
        self::assertInstanceOf(RightParenToken::class, $tokens[4]);
    }

    public function testSignedNumberWithoutWhitespace(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('1+2'));
        self::assertInstanceOf(NumberToken::class, $tokens[0]);
        self::assertSame(1.0, $tokens[0]->value);
        self::assertInstanceOf(NumberToken::class, $tokens[1]);
        self::assertSame(2.0, $tokens[1]->value);
    }

    public function testAtKeyword(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('@media'));
        self::assertInstanceOf(AtKeywordToken::class, $tokens[0]);
        self::assertSame('media', $tokens[0]->value);
    }

    public function testHashId(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('#main'));
        self::assertInstanceOf(HashToken::class, $tokens[0]);
        self::assertSame('main', $tokens[0]->value);
        self::assertSame(HashTokenType::Id, $tokens[0]->type);
    }

    public function testHashUnrestricted(): void
    {
        // #123 — doesn't start with ident-start code point so it's unrestricted.
        $tokens = $this->withoutWhitespace($this->tokenize('#123'));
        self::assertInstanceOf(HashToken::class, $tokens[0]);
        self::assertSame('123', $tokens[0]->value);
        self::assertSame(HashTokenType::Unrestricted, $tokens[0]->type);
    }

    public function testNumberInteger(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('42'));
        self::assertInstanceOf(NumberToken::class, $tokens[0]);
        self::assertSame(42.0, $tokens[0]->value);
        self::assertSame(NumberTokenType::Integer, $tokens[0]->type);
    }

    public function testNumberFloat(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('3.14'));
        self::assertInstanceOf(NumberToken::class, $tokens[0]);
        self::assertSame(3.14, $tokens[0]->value);
        self::assertSame(NumberTokenType::Number, $tokens[0]->type);
    }

    public function testNumberWithExponent(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('1.5e2'));
        self::assertInstanceOf(NumberToken::class, $tokens[0]);
        self::assertSame(150.0, $tokens[0]->value);
        self::assertSame(NumberTokenType::Number, $tokens[0]->type);
    }

    public function testNegativeNumber(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('-12.5'));
        self::assertInstanceOf(NumberToken::class, $tokens[0]);
        self::assertSame(-12.5, $tokens[0]->value);
    }

    public function testDimensionPixels(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('16px'));
        self::assertInstanceOf(DimensionToken::class, $tokens[0]);
        self::assertSame(16.0, $tokens[0]->value);
        self::assertSame('px', $tokens[0]->unit);
    }

    public function testDimensionEm(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('1.5em'));
        self::assertInstanceOf(DimensionToken::class, $tokens[0]);
        self::assertSame(1.5, $tokens[0]->value);
        self::assertSame('em', $tokens[0]->unit);
    }

    public function testPercentage(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('50%'));
        self::assertInstanceOf(PercentageToken::class, $tokens[0]);
        self::assertSame(50.0, $tokens[0]->value);
    }

    public function testStringDoubleQuoted(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('"hello world"'));
        self::assertInstanceOf(StringToken::class, $tokens[0]);
        self::assertSame('hello world', $tokens[0]->value);
    }

    public function testStringSingleQuoted(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize("'hello'"));
        self::assertInstanceOf(StringToken::class, $tokens[0]);
        self::assertSame('hello', $tokens[0]->value);
    }

    public function testUrlUnquoted(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('url(image.png)'));
        self::assertInstanceOf(UrlToken::class, $tokens[0]);
        self::assertSame('image.png', $tokens[0]->value);
    }

    public function testUrlQuotedBecomesFunctionToken(): void
    {
        // Quoted url() is FunctionToken('url') + StringToken + RightParen.
        $tokens = $this->withoutWhitespace($this->tokenize('url("image.png")'));
        self::assertInstanceOf(FunctionToken::class, $tokens[0]);
        self::assertSame('url', $tokens[0]->name);
        self::assertInstanceOf(StringToken::class, $tokens[1]);
        self::assertSame('image.png', $tokens[1]->value);
        self::assertInstanceOf(RightParenToken::class, $tokens[2]);
    }

    public function testCommentsAreStripped(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('a /* comment */ b'));
        self::assertCount(3, $tokens); // a + b + EOF
        self::assertInstanceOf(IdentToken::class, $tokens[0]);
        self::assertSame('a', $tokens[0]->value);
        self::assertInstanceOf(IdentToken::class, $tokens[1]);
        self::assertSame('b', $tokens[1]->value);
    }

    public function testCdoCdc(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('<!-- foo -->'));
        self::assertInstanceOf(CdoToken::class, $tokens[0]);
        self::assertInstanceOf(IdentToken::class, $tokens[1]);
        self::assertSame('foo', $tokens[1]->value);
        self::assertInstanceOf(CdcToken::class, $tokens[2]);
    }

    public function testXhtmlCdataSectionDelimitersAreSilentlyConsumed(): void
    {
        // XHTML stylesheets wrap their contents in `<![CDATA[ ... ]]>`.
        // Browsers strip the delimiters when parsing the CSS; we do too,
        // so the inner declarations tokenise as if the brackets weren't
        // there. Anything else would drop the whole stylesheet.
        $tokens = $this->withoutWhitespace($this->tokenize('<![CDATA[ foo ]]>'));
        self::assertInstanceOf(IdentToken::class, $tokens[0]);
        self::assertSame('foo', $tokens[0]->value);
        self::assertInstanceOf(EofToken::class, $tokens[1]);
    }

    public function testPunctuators(): void
    {
        $tokens = $this->tokenize('{};,():');
        $types = array_map(static fn($t) => $t::class, $tokens);
        self::assertSame([
            LeftBraceToken::class,
            RightBraceToken::class,
            SemicolonToken::class,
            CommaToken::class,
            LeftParenToken::class,
            RightParenToken::class,
            ColonToken::class,
            EofToken::class,
        ], $types);
    }

    public function testSimpleRule(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize('p { color: red; }'));
        $types = array_map(static fn($t) => $t::class, $tokens);
        self::assertSame([
            IdentToken::class,        // p
            LeftBraceToken::class,    // {
            IdentToken::class,        // color
            ColonToken::class,        // :
            IdentToken::class,        // red
            SemicolonToken::class,    // ;
            RightBraceToken::class,   // }
            EofToken::class,
        ], $types);
    }

    public function testEscapesInIdentifier(): void
    {
        // \41 (followed by space) is a hex escape for 'A'.
        $tokens = $this->withoutWhitespace($this->tokenize('\\41 BC'));
        self::assertInstanceOf(IdentToken::class, $tokens[0]);
        self::assertSame('ABC', $tokens[0]->value);
    }

    public function testHexEscapeForUnicodeChar(): void
    {
        // \2603 is the snowman.
        $tokens = $this->withoutWhitespace($this->tokenize('\\2603 '));
        self::assertInstanceOf(IdentToken::class, $tokens[0]);
        self::assertSame("\u{2603}", $tokens[0]->value);
    }

    public function testNullBecomesReplacementCharacter(): void
    {
        $tokens = $this->withoutWhitespace($this->tokenize("foo\0bar"));
        self::assertInstanceOf(IdentToken::class, $tokens[0]);
        self::assertSame("foo\u{FFFD}bar", $tokens[0]->value);
    }

    public function testCrLfNormalised(): void
    {
        $tokens = $this->tokenize("a\r\nb");
        // \r\n should normalise to \n, so we get ident "a", whitespace, ident "b", EOF.
        self::assertInstanceOf(IdentToken::class, $tokens[0]);
        self::assertInstanceOf(WhitespaceToken::class, $tokens[1]);
        self::assertInstanceOf(IdentToken::class, $tokens[2]);
    }

    public function testNextTokenStreaming(): void
    {
        $t = new Tokenizer('foo bar');
        $tokens = [];
        while (($tok = $t->nextToken()) !== null) {
            $tokens[] = $tok;
        }
        // foo, ws, bar, EOF.
        self::assertCount(4, $tokens);
        self::assertInstanceOf(IdentToken::class, $tokens[0]);
        self::assertInstanceOf(EofToken::class, $tokens[3]);
    }
}
