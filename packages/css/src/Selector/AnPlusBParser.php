<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

use Phpdftk\Css\Token\DelimToken;
use Phpdftk\Css\Token\DimensionToken;
use Phpdftk\Css\Token\IdentToken;
use Phpdftk\Css\Token\NumberToken;
use Phpdftk\Css\Token\NumberTokenType;
use Phpdftk\Css\Token\Token;
use Phpdftk\Css\Token\WhitespaceToken;

/**
 * Parser for the An+B microsyntax per CSS Syntax 3 §6 / Selectors 4 §11.1.
 * Handles: `2n+1`, `2n - 1`, `even`, `odd`, `+5`, `-n+3`, `n`, `0n+0`.
 *
 * Also supports the `:nth-child(... of S)` extension, returning the optional
 * inner SelectorList.
 */
final class AnPlusBParser
{
    /**
     * @param list<Token> $tokens
     * @return array{AnPlusB, ?SelectorList}
     */
    public static function parseWithOf(array $tokens): array
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            throw new SelectorSyntaxException('Empty An+B expression');
        }
        // Look for ` of ` separator at the top level.
        $ofIndex = self::findOfKeyword($tokens);
        $anbTokens = $ofIndex === null ? $tokens : array_slice($tokens, 0, $ofIndex);
        $ofList = null;
        if ($ofIndex !== null) {
            $ofTokens = array_slice($tokens, $ofIndex + 1);
            $ofList = SelectorParser::parseTokens($ofTokens);
        }
        return [self::parse($anbTokens), $ofList];
    }

    /** @param list<Token> $tokens */
    public static function parse(array $tokens): AnPlusB
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            throw new SelectorSyntaxException('Empty An+B expression');
        }
        // Keyword shortcuts.
        if (count($tokens) === 1 && $tokens[0] instanceof IdentToken) {
            $low = strtolower($tokens[0]->value);
            if ($low === 'even') {
                return AnPlusB::even();
            }
            if ($low === 'odd') {
                return AnPlusB::odd();
            }
            // `n` alone — a=1, b=0.
            if ($low === 'n') {
                return new AnPlusB(1, 0);
            }
            if ($low === '-n') {
                return new AnPlusB(-1, 0);
            }
        }

        // Pure integer: b only.
        if (count($tokens) === 1 && $tokens[0] instanceof NumberToken
            && $tokens[0]->type === NumberTokenType::Integer
        ) {
            return new AnPlusB(0, (int) $tokens[0]->value);
        }

        // <n-dimension> with optional sign and following integer.
        // e.g. "2n", "2n+1", "2n -1", "-n+3"
        $a = 0;
        $b = 0;
        $i = 0;
        $count = count($tokens);

        $tok = $tokens[$i];
        if ($tok instanceof DimensionToken && self::isNDimensionUnit($tok->unit)
            && $tok->type === NumberTokenType::Integer
        ) {
            // 2n, -2n, 0n
            $a = self::dimensionACoefficient($tok);
            $i++;
        } elseif ($tok instanceof IdentToken && self::isNLikeIdent($tok->value)) {
            $value = strtolower($tok->value);
            $a = ($value === 'n' || $value === 'n-') ? 1 : -1;
            // ident might encode a trailing -<digits>, e.g. "n-3".
            if (preg_match('/^-?n-(\d+)$/i', $tok->value, $m)) {
                return new AnPlusB($a, -(int) $m[1]);
            }
            $i++;
        } elseif ($tok instanceof DelimToken && in_array($tok->value, ['+', '-'], true)
            && ($next = $tokens[$i + 1] ?? null) instanceof IdentToken
            && self::isNLikeIdent($next->value)
        ) {
            $sign = $tok->value === '-' ? -1 : 1;
            $a = $sign;
            // ident might be "n-3".
            if (preg_match('/^n-(\d+)$/i', $next->value, $m)) {
                return new AnPlusB($a, -(int) $m[1]);
            }
            $i += 2;
        } else {
            throw new SelectorSyntaxException('Invalid An+B expression');
        }

        // Optional `+ b` or `- b`.
        $i = self::skipWs($tokens, $i);
        if ($i >= $count) {
            return new AnPlusB($a, 0);
        }
        $signTok = $tokens[$i] ?? null;
        $sign = 0;
        if ($signTok instanceof DelimToken && $signTok->value === '+') {
            $sign = 1;
            $i++;
        } elseif ($signTok instanceof DelimToken && $signTok->value === '-') {
            $sign = -1;
            $i++;
        } else {
            // Maybe the integer carries a sign already (e.g. "2n -1").
            if ($signTok instanceof NumberToken && $signTok->type === NumberTokenType::Integer) {
                return new AnPlusB($a, (int) $signTok->value);
            }
            throw new SelectorSyntaxException('Expected + or - in An+B');
        }
        $i = self::skipWs($tokens, $i);
        $numTok = $tokens[$i] ?? null;
        if (!($numTok instanceof NumberToken) || $numTok->type !== NumberTokenType::Integer) {
            throw new SelectorSyntaxException('Expected integer in An+B');
        }
        $b = $sign * (int) abs($numTok->value);
        return new AnPlusB($a, $b);
    }

    private static function dimensionACoefficient(DimensionToken $t): int
    {
        // "n" or "n-" unit (the dash variant is consumed when followed by a number).
        return (int) $t->value;
    }

    private static function isNDimensionUnit(string $unit): bool
    {
        $low = strtolower($unit);
        return $low === 'n' || $low === 'n-';
    }

    private static function isNLikeIdent(string $ident): bool
    {
        return preg_match('/^-?n(-\d+)?$/i', $ident) === 1;
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private static function trimWhitespace(array $tokens): array
    {
        while ($tokens !== [] && $tokens[0] instanceof WhitespaceToken) {
            array_shift($tokens);
        }
        while ($tokens !== [] && end($tokens) instanceof WhitespaceToken) {
            array_pop($tokens);
        }
        return array_values($tokens);
    }

    /** @param list<Token> $tokens */
    private static function skipWs(array $tokens, int $i): int
    {
        while (isset($tokens[$i]) && $tokens[$i] instanceof WhitespaceToken) {
            $i++;
        }
        return $i;
    }

    /** @param list<Token> $tokens */
    private static function findOfKeyword(array $tokens): ?int
    {
        foreach ($tokens as $i => $t) {
            if ($t instanceof IdentToken && strtolower($t->value) === 'of') {
                return $i;
            }
        }
        return null;
    }
}
