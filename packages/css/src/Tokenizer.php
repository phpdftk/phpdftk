<?php

declare(strict_types=1);

namespace Phpdftk\Css;

use Phpdftk\Css\Token\AtKeywordToken;
use Phpdftk\Css\Token\BadStringToken;
use Phpdftk\Css\Token\BadUrlToken;
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
use Phpdftk\Css\Token\LeftBracketToken;
use Phpdftk\Css\Token\LeftParenToken;
use Phpdftk\Css\Token\NumberToken;
use Phpdftk\Css\Token\NumberTokenType;
use Phpdftk\Css\Token\PercentageToken;
use Phpdftk\Css\Token\RightBraceToken;
use Phpdftk\Css\Token\RightBracketToken;
use Phpdftk\Css\Token\RightParenToken;
use Phpdftk\Css\Token\SemicolonToken;
use Phpdftk\Css\Token\StringToken;
use Phpdftk\Css\Token\Token;
use Phpdftk\Css\Token\UrlToken;
use Phpdftk\Css\Token\WhitespaceToken;

/**
 * CSS Syntax Module 3 tokenizer (§4). Walks the preprocessed input
 * character-by-character, dispatching by the next character into one of the
 * "consume X" sub-procedures.
 *
 * Preprocessing per §3.3 is applied at construction: CR / CRLF / FF → LF;
 * NULL → U+FFFD (handled inline during consume). Comments are stripped
 * (not emitted as tokens).
 *
 * Both `tokenize()` (returns full array) and `nextToken()` (streaming) are
 * provided, mirroring the html tokenizer's contract so downstream parsers
 * can drive either way.
 */
final class Tokenizer
{
    /** @var list<string> input as an array of UTF-8 single-codepoint strings */
    private array $chars;
    private int $length;
    private int $pos = 0;
    /** @var list<Token> */
    private array $emitted = [];
    private int $emittedCursor = 0;
    private bool $done = false;

    public function __construct(string $input)
    {
        $normalised = $this->preprocess($input);
        $this->chars = $normalised === '' ? [] : (mb_str_split($normalised, 1, 'UTF-8') ?: []);
        $this->length = count($this->chars);
    }

    /** @return list<Token> */
    public function tokenize(): array
    {
        while (!$this->done) {
            $this->step();
        }
        return $this->emitted;
    }

    public function nextToken(): ?Token
    {
        while ($this->emittedCursor >= count($this->emitted) && !$this->done) {
            $this->step();
        }
        if ($this->emittedCursor < count($this->emitted)) {
            return $this->emitted[$this->emittedCursor++];
        }
        return null;
    }

    private function step(): void
    {
        // Comments are stripped here so the dispatch below doesn't see them.
        $this->consumeComments();
        $c = $this->peek(0);
        if ($c === null) {
            $this->emit(new EofToken());
            $this->done = true;
            return;
        }
        if (self::isWhitespace($c)) {
            while (($next = $this->peek(0)) !== null && self::isWhitespace($next)) {
                $this->advance();
            }
            $this->emit(new WhitespaceToken());
            return;
        }
        if ($c === '"' || $c === "'") {
            $this->advance();
            $this->emit($this->consumeString($c));
            return;
        }
        if ($c === '#') {
            // Hash if followed by ident-code-point or escape; else Delim('#').
            $next = $this->peek(1);
            if ($next !== null && (self::isIdentCodePoint($next) || $this->isValidEscape(1))) {
                $this->advance(); // consume '#'
                $type = $this->wouldStartIdentSequence(0) ? HashTokenType::Id : HashTokenType::Unrestricted;
                $name = $this->consumeIdentSequence();
                $this->emit(new HashToken($name, $type));
                return;
            }
            $this->advance();
            $this->emit(new DelimToken('#'));
            return;
        }
        if ($c === '(') {
            $this->advance();
            $this->emit(new LeftParenToken());
            return;
        }
        if ($c === ')') {
            $this->advance();
            $this->emit(new RightParenToken());
            return;
        }
        if ($c === '+' || $c === '.') {
            if ($this->wouldStartNumber(0)) {
                $this->emit($this->consumeNumericToken());
                return;
            }
            $this->advance();
            $this->emit(new DelimToken($c));
            return;
        }
        if ($c === ',') {
            $this->advance();
            $this->emit(new CommaToken());
            return;
        }
        if ($c === '-') {
            if ($this->wouldStartNumber(0)) {
                $this->emit($this->consumeNumericToken());
                return;
            }
            if ($this->peek(1) === '-' && $this->peek(2) === '>') {
                $this->advance();
                $this->advance();
                $this->advance();
                $this->emit(new CdcToken());
                return;
            }
            if ($this->wouldStartIdentSequence(0)) {
                $this->emit($this->consumeIdentLikeToken());
                return;
            }
            $this->advance();
            $this->emit(new DelimToken('-'));
            return;
        }
        if ($c === ':') {
            $this->advance();
            $this->emit(new ColonToken());
            return;
        }
        if ($c === ';') {
            $this->advance();
            $this->emit(new SemicolonToken());
            return;
        }
        if ($c === '<') {
            if ($this->peek(1) === '!' && $this->peek(2) === '-' && $this->peek(3) === '-') {
                $this->advance();
                $this->advance();
                $this->advance();
                $this->advance();
                $this->emit(new CdoToken());
                return;
            }
            $this->advance();
            $this->emit(new DelimToken('<'));
            return;
        }
        if ($c === '@') {
            if ($this->wouldStartIdentSequence(1)) {
                $this->advance(); // consume '@'
                $name = $this->consumeIdentSequence();
                $this->emit(new AtKeywordToken($name));
                return;
            }
            $this->advance();
            $this->emit(new DelimToken('@'));
            return;
        }
        if ($c === '[') {
            $this->advance();
            $this->emit(new LeftBracketToken());
            return;
        }
        if ($c === ']') {
            $this->advance();
            $this->emit(new RightBracketToken());
            return;
        }
        if ($c === '\\') {
            if ($this->isValidEscape(0)) {
                $this->emit($this->consumeIdentLikeToken());
                return;
            }
            $this->advance();
            $this->emit(new DelimToken('\\'));
            return;
        }
        if ($c === '{') {
            $this->advance();
            $this->emit(new LeftBraceToken());
            return;
        }
        if ($c === '}') {
            $this->advance();
            $this->emit(new RightBraceToken());
            return;
        }
        if (self::isDigit($c)) {
            $this->emit($this->consumeNumericToken());
            return;
        }
        if (self::isIdentStartCodePoint($c)) {
            $this->emit($this->consumeIdentLikeToken());
            return;
        }
        $this->advance();
        $this->emit(new DelimToken($c));
    }

    // ============================================================
    // Sub-procedures
    // ============================================================

    private function consumeComments(): void
    {
        while ($this->peek(0) === '/' && $this->peek(1) === '*') {
            $this->advance();
            $this->advance();
            while (true) {
                $c = $this->peek(0);
                if ($c === null) {
                    return; // EOF inside comment is a parse error per spec; we just stop.
                }
                if ($c === '*' && $this->peek(1) === '/') {
                    $this->advance();
                    $this->advance();
                    break;
                }
                $this->advance();
            }
        }
    }

    private function consumeString(string $terminator): Token
    {
        $buf = '';
        while (true) {
            $c = $this->peek(0);
            if ($c === null) {
                return new StringToken($buf); // EOF — parse error per spec; return what we have.
            }
            if ($c === $terminator) {
                $this->advance();
                return new StringToken($buf);
            }
            if ($c === "\n") {
                return new BadStringToken();
            }
            if ($c === '\\') {
                if ($this->peek(1) === null) {
                    $this->advance();
                    continue;
                }
                if ($this->peek(1) === "\n") {
                    $this->advance();
                    $this->advance();
                    continue;
                }
                $buf .= $this->consumeEscape();
                continue;
            }
            $buf .= $c;
            $this->advance();
        }
    }

    private function consumeIdentLikeToken(): Token
    {
        $name = $this->consumeIdentSequence();
        if (strcasecmp($name, 'url') === 0 && $this->peek(0) === '(') {
            $this->advance(); // consume '('
            // Skip leading whitespace.
            while (($next = $this->peek(0)) !== null && self::isWhitespace($next)) {
                $this->advance();
            }
            // If quote follows, it's a function-call url() with a string arg.
            $n = $this->peek(0);
            if ($n === '"' || $n === "'") {
                return new FunctionToken($name);
            }
            return $this->consumeUrlToken();
        }
        if ($this->peek(0) === '(') {
            $this->advance();
            return new FunctionToken($name);
        }
        return new IdentToken($name);
    }

    private function consumeUrlToken(): Token
    {
        $buf = '';
        while (true) {
            $c = $this->peek(0);
            if ($c === null) {
                return new UrlToken($buf);
            }
            if ($c === ')') {
                $this->advance();
                return new UrlToken($buf);
            }
            if (self::isWhitespace($c)) {
                while (($next = $this->peek(0)) !== null && self::isWhitespace($next)) {
                    $this->advance();
                }
                if ($this->peek(0) === ')') {
                    $this->advance();
                    return new UrlToken($buf);
                }
                if ($this->peek(0) === null) {
                    return new UrlToken($buf);
                }
                $this->consumeRemnantsOfBadUrl();
                return new BadUrlToken();
            }
            if ($c === '"' || $c === "'" || $c === '(' || self::isNonPrintable($c)) {
                $this->consumeRemnantsOfBadUrl();
                return new BadUrlToken();
            }
            if ($c === '\\') {
                if ($this->isValidEscape(0)) {
                    $buf .= $this->consumeEscape();
                    continue;
                }
                $this->consumeRemnantsOfBadUrl();
                return new BadUrlToken();
            }
            $buf .= $c;
            $this->advance();
        }
    }

    private function consumeRemnantsOfBadUrl(): void
    {
        while (true) {
            $c = $this->peek(0);
            if ($c === null || $c === ')') {
                if ($c === ')') {
                    $this->advance();
                }
                return;
            }
            if ($c === '\\' && $this->isValidEscape(0)) {
                $this->consumeEscape();
                continue;
            }
            $this->advance();
        }
    }

    private function consumeIdentSequence(): string
    {
        $out = '';
        while (true) {
            $c = $this->peek(0);
            if ($c === null) {
                return $out;
            }
            if (self::isIdentCodePoint($c)) {
                $out .= $c;
                $this->advance();
                continue;
            }
            if ($this->isValidEscape(0)) {
                $out .= $this->consumeEscape();
                continue;
            }
            return $out;
        }
    }

    private function consumeNumericToken(): Token
    {
        [$value, $type] = $this->consumeNumber();
        if ($this->wouldStartIdentSequence(0)) {
            $unit = $this->consumeIdentSequence();
            return new DimensionToken($value, $unit, $type);
        }
        if ($this->peek(0) === '%') {
            $this->advance();
            return new PercentageToken($value);
        }
        return new NumberToken($value, $type);
    }

    /** @return array{0: float, 1: NumberTokenType} */
    private function consumeNumber(): array
    {
        $type = NumberTokenType::Integer;
        $buf = '';
        $c = $this->peek(0);
        if ($c === '+' || $c === '-') {
            $buf .= $c;
            $this->advance();
        }
        while (self::isDigit($this->peek(0) ?? '')) {
            $buf .= $this->peek(0);
            $this->advance();
        }
        if ($this->peek(0) === '.' && self::isDigit($this->peek(1) ?? '')) {
            $buf .= $this->peek(0) . $this->peek(1);
            $this->advance();
            $this->advance();
            $type = NumberTokenType::Number;
            while (self::isDigit($this->peek(0) ?? '')) {
                $buf .= $this->peek(0);
                $this->advance();
            }
        }
        $c = $this->peek(0);
        $next = $this->peek(1);
        $next2 = $this->peek(2);
        if (($c === 'e' || $c === 'E')
            && (self::isDigit($next ?? '')
                || (($next === '+' || $next === '-') && self::isDigit($next2 ?? '')))
        ) {
            $buf .= $this->peek(0);
            $this->advance();
            if ($this->peek(0) === '+' || $this->peek(0) === '-') {
                $buf .= $this->peek(0);
                $this->advance();
            }
            $type = NumberTokenType::Number;
            while (self::isDigit($this->peek(0) ?? '')) {
                $buf .= $this->peek(0);
                $this->advance();
            }
        }
        return [(float) $buf, $type];
    }

    private function consumeEscape(): string
    {
        // Caller has positioned us on '\\'; advance past it.
        $this->advance();
        $c = $this->peek(0);
        if ($c === null) {
            return "\u{FFFD}";
        }
        if (self::isHexDigit($c)) {
            $hex = '';
            for ($i = 0; $i < 6; $i++) {
                $n = $this->peek(0);
                if ($n === null || !self::isHexDigit($n)) {
                    break;
                }
                $hex .= $n;
                $this->advance();
            }
            $next = $this->peek(0);
            if ($next !== null && self::isWhitespace($next)) {
                $this->advance();
            }
            $cp = (int) hexdec($hex);
            if ($cp === 0 || $cp > 0x10FFFF || ($cp >= 0xD800 && $cp <= 0xDFFF)) {
                return "\u{FFFD}";
            }
            return mb_chr($cp, 'UTF-8') ?: "\u{FFFD}";
        }
        $this->advance();
        return $c;
    }

    // ============================================================
    // Lookahead helpers (CSS Syntax 3 §4.3.8 / §4.3.9)
    // ============================================================

    private function isValidEscape(int $offset): bool
    {
        if ($this->peek($offset) !== '\\') {
            return false;
        }
        return $this->peek($offset + 1) !== "\n";
    }

    private function wouldStartIdentSequence(int $offset): bool
    {
        $c1 = $this->peek($offset);
        if ($c1 === '-') {
            $c2 = $this->peek($offset + 1);
            if ($c2 !== null && (self::isIdentStartCodePoint($c2) || $c2 === '-')) {
                return true;
            }
            return $this->isValidEscape($offset + 1);
        }
        if ($c1 !== null && self::isIdentStartCodePoint($c1)) {
            return true;
        }
        if ($c1 === '\\') {
            return $this->isValidEscape($offset);
        }
        return false;
    }

    private function wouldStartNumber(int $offset): bool
    {
        $c1 = $this->peek($offset);
        if ($c1 === '+' || $c1 === '-') {
            $c2 = $this->peek($offset + 1);
            if (self::isDigit($c2 ?? '')) {
                return true;
            }
            if ($c2 === '.') {
                $c3 = $this->peek($offset + 2);
                return self::isDigit($c3 ?? '');
            }
            return false;
        }
        if ($c1 === '.') {
            return self::isDigit($this->peek($offset + 1) ?? '');
        }
        return $c1 !== null && self::isDigit($c1);
    }

    // ============================================================
    // I/O helpers
    // ============================================================

    private function preprocess(string $input): string
    {
        $input = str_replace(["\r\n", "\r", "\f"], "\n", $input);
        return str_replace("\0", "\u{FFFD}", $input);
    }

    private function peek(int $offset): ?string
    {
        $i = $this->pos + $offset;
        return $i < $this->length ? $this->chars[$i] : null;
    }

    private function advance(): void
    {
        $this->pos++;
    }

    private function emit(Token $t): void
    {
        $this->emitted[] = $t;
    }

    // ============================================================
    // Character classification
    // ============================================================

    private static function isWhitespace(string $c): bool
    {
        return $c === ' ' || $c === "\t" || $c === "\n";
    }

    private static function isDigit(string $c): bool
    {
        return $c >= '0' && $c <= '9';
    }

    private static function isHexDigit(string $c): bool
    {
        return self::isDigit($c) || ($c >= 'A' && $c <= 'F') || ($c >= 'a' && $c <= 'f');
    }

    private static function isLetter(string $c): bool
    {
        return ($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z');
    }

    private static function isIdentStartCodePoint(string $c): bool
    {
        if ($c === '') {
            return false;
        }
        if (self::isLetter($c) || $c === '_') {
            return true;
        }
        return mb_ord($c, 'UTF-8') >= 0x80;
    }

    private static function isIdentCodePoint(string $c): bool
    {
        return self::isIdentStartCodePoint($c) || self::isDigit($c) || $c === '-';
    }

    private static function isNonPrintable(string $c): bool
    {
        $cp = mb_ord($c, 'UTF-8');
        if ($cp === false) {
            return false;
        }
        return ($cp >= 0x00 && $cp <= 0x08)
            || $cp === 0x0B
            || ($cp >= 0x0E && $cp <= 0x1F)
            || $cp === 0x7F;
    }
}
