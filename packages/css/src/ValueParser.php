<?php

declare(strict_types=1);

namespace Phpdftk\Css;

use Phpdftk\Css\Token\CommaToken;
use Phpdftk\Css\Token\DelimToken;
use Phpdftk\Css\Token\DimensionToken;
use Phpdftk\Css\Token\EofToken;
use Phpdftk\Css\Token\FunctionToken;
use Phpdftk\Css\Token\HashToken;
use Phpdftk\Css\Token\IdentToken;
use Phpdftk\Css\Token\LeftBraceToken;
use Phpdftk\Css\Token\LeftBracketToken;
use Phpdftk\Css\Token\LeftParenToken;
use Phpdftk\Css\Token\NumberToken;
use Phpdftk\Css\Token\PercentageToken;
use Phpdftk\Css\Token\RightBraceToken;
use Phpdftk\Css\Token\RightBracketToken;
use Phpdftk\Css\Token\RightParenToken;
use Phpdftk\Css\Token\StringToken;
use Phpdftk\Css\Token\Token;
use Phpdftk\Css\Token\UrlToken;
use Phpdftk\Css\Token\WhitespaceToken;
use Phpdftk\Css\Value\Angle;
use Phpdftk\Css\Value\AngleUnit;
use Phpdftk\Css\Value\Calc;
use Phpdftk\Css\Value\CalcBinary;
use Phpdftk\Css\Value\CalcExpression;
use Phpdftk\Css\Value\CalcFunc;
use Phpdftk\Css\Value\CalcFunction;
use Phpdftk\Css\Value\CalcLeaf;
use Phpdftk\Css\Value\CalcOp;
use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\ColorSpace;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\CustomProperty;
use Phpdftk\Css\Value\Gradient;
use Phpdftk\Css\Value\GradientShape;
use Phpdftk\Css\Value\GradientStop;
use Phpdftk\Css\Value\Integer;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use Phpdftk\Css\Value\LinearGradient;
use Phpdftk\Css\Value\ListSeparator;
use Phpdftk\Css\Value\MatrixTransform;
use Phpdftk\Css\Value\NamedColors;
use Phpdftk\Css\Value\Number;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\RadialGradient;
use Phpdftk\Css\Value\RotateTransform;
use Phpdftk\Css\Value\ScaleTransform;
use Phpdftk\Css\Value\SkewTransform;
use Phpdftk\Css\Value\StringValue;
use Phpdftk\Css\Value\Transform;
use Phpdftk\Css\Value\TransformFunction;
use Phpdftk\Css\Value\TranslateTransform;
use Phpdftk\Css\Value\Url;
use Phpdftk\Css\Value\Value;
use Phpdftk\Css\Value\ValueList;

/**
 * Parses a CSS token stream into typed Value instances per CSS Values 4.
 *
 * Phase 1A.2 covers the common path: keywords, numbers, integers,
 * percentages, lengths (all units), colors (hex / named / rgb / rgba /
 * hsl / hsla / transparent), strings, urls, single-argument function
 * fallback. Calc, gradients, transforms, and color() with explicit space
 * are deferred — they get dedicated parsers in 1A.2-bis.
 */
final class ValueParser
{
    /**
     * Parse the entire token stream as a single value. Whitespace-separated
     * tokens become a `ValueList(Space)`; comma-separated become
     * `ValueList(Comma)`. A lone value is returned bare.
     */
    public function parseFromString(string $css): Value
    {
        $tokens = (new Tokenizer($css))->tokenize();
        return $this->parse($tokens);
    }

    /** @param list<Token> $tokens */
    public function parse(array $tokens): Value
    {
        $tokens = self::trimWhitespace($tokens);

        // Split on top-level commas first.
        $commaGroups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($commaGroups) > 1) {
            $values = array_map(fn($g): Value => $this->parseSlashList($g), $commaGroups);
            return new ValueList($values, ListSeparator::Comma);
        }
        return $this->parseSlashList($tokens);
    }

    /**
     * Per CSS Values 4, `/` is a top-level separator inside a comma group
     * for several shorthands (`font: 16px/1.5 sans-serif`, `border-radius:
     * 4px / 8px`, `background: <bg-color> / <bg-size>` etc.). Splits the
     * group on top-level `/` delims, then recurses into the space-list
     * parser for each slash segment.
     *
     * @param list<Token> $tokens
     */
    private function parseSlashList(array $tokens): Value
    {
        $slashGroups = self::splitTopLevelDelim($tokens, '/');
        if (count($slashGroups) > 1) {
            $values = array_map(fn($g): Value => $this->parseSpaceList($g), $slashGroups);
            return new ValueList($values, ListSeparator::Slash);
        }
        return $this->parseSpaceList($tokens);
    }

    /** @param list<Token> $tokens */
    private function parseSpaceList(array $tokens): Value
    {
        $tokens = self::trimWhitespace($tokens);
        $parts = self::splitOnWhitespace($tokens);
        if (count($parts) === 1) {
            return $this->parseSingle($parts[0]);
        }
        $values = array_map(fn($p): Value => $this->parseSingle($p), $parts);
        return new ValueList($values, ListSeparator::Space);
    }

    /** @param list<Token> $tokens A single value without separators. */
    private function parseSingle(array $tokens): Value
    {
        if ($tokens === []) {
            return new Keyword(''); // shouldn't happen in well-formed input
        }
        $head = $tokens[0];
        if ($head instanceof IdentToken) {
            $name = strtolower($head->value);
            if ($name === 'transparent') {
                return new Color(0, 0, 0, 0);
            }
            $named = NamedColors::lookup($name);
            if ($named !== null) {
                return $named;
            }
            return new Keyword($name);
        }
        if ($head instanceof HashToken) {
            $color = $this->parseHexColor($head->value);
            if ($color !== null) {
                return $color;
            }
            return new Keyword('#' . $head->value);
        }
        if ($head instanceof NumberToken) {
            return $head->type->name === 'Integer'
                ? new Integer((int) $head->value)
                : new Number($head->value);
        }
        if ($head instanceof PercentageToken) {
            return new Percentage($head->value);
        }
        if ($head instanceof DimensionToken) {
            $unit = strtolower($head->unit);
            $lengthUnit = LengthUnit::tryFrom($unit);
            if ($lengthUnit !== null) {
                return new Length($head->value, $lengthUnit);
            }
            $angleUnit = AngleUnit::tryFrom($unit);
            if ($angleUnit !== null) {
                return new Angle($head->value, $angleUnit);
            }
            // Unknown unit — fall back to a CssFunction representation so the
            // value survives round-trip without being silently dropped.
            return new CssFunction($head->unit, [new Number($head->value)]);
        }
        if ($head instanceof StringToken) {
            return new StringValue($head->value);
        }
        if ($head instanceof UrlToken) {
            return new Url($head->value);
        }
        if ($head instanceof FunctionToken) {
            return $this->parseFunction($head->name, array_slice($tokens, 1));
        }
        // Fallback — keep the raw delim as a one-char keyword.
        if ($head instanceof DelimToken) {
            return new Keyword($head->value);
        }
        return new Keyword('');
    }

    /**
     * @param list<Token> $tokens The function body, excluding the
     *        FunctionToken header and the closing RightParenToken.
     */
    private function parseFunction(string $name, array $tokens): Value
    {
        $name = strtolower($name);
        // Drop the trailing ) if present.
        if ($tokens !== [] && end($tokens) instanceof RightParenToken) {
            array_pop($tokens);
        }
        if ($name === 'url') {
            // url("...") form.
            foreach ($tokens as $tok) {
                if ($tok instanceof StringToken) {
                    return new Url($tok->value);
                }
            }
            return new Url('');
        }
        if ($name === 'rgb' || $name === 'rgba') {
            return $this->parseRgbFunction($tokens) ?? new CssFunction($name, $this->parseArgs($tokens));
        }
        if ($name === 'hsl' || $name === 'hsla') {
            return $this->parseHslFunction($tokens) ?? new CssFunction($name, $this->parseArgs($tokens));
        }
        if ($name === 'var') {
            return $this->parseVarFunction($tokens) ?? new CssFunction($name, $this->parseArgs($tokens));
        }
        if ($name === 'color') {
            return $this->parseColorFunction($tokens) ?? new CssFunction($name, $this->parseArgs($tokens));
        }
        if (CalcFunction::tryFrom($name) !== null) {
            $calc = $this->parseCalcFunction(CalcFunction::from($name), $tokens);
            if ($calc !== null) {
                return $calc;
            }
        }
        if ($name === 'linear-gradient' || $name === 'repeating-linear-gradient') {
            $g = $this->parseLinearGradient($tokens, $name === 'repeating-linear-gradient');
            if ($g !== null) {
                return $g;
            }
        }
        if ($name === 'radial-gradient' || $name === 'repeating-radial-gradient') {
            $g = $this->parseRadialGradient($tokens, $name === 'repeating-radial-gradient');
            if ($g !== null) {
                return $g;
            }
        }
        // Generic fallback: each comma-separated group becomes one argument.
        return new CssFunction($name, $this->parseArgs($tokens));
    }

    /**
     * Parse a sequence of transform functions into a single Transform value.
     * Call from the cascade when the property being computed is `transform`.
     */
    public function parseTransform(string $css): Value
    {
        $value = $this->parseFromString($css);
        $items = $value instanceof ValueList ? $value->values : [$value];
        $fns = [];
        foreach ($items as $v) {
            $fn = $this->valueToTransformFunction($v);
            if ($fn === null) {
                // Not a recognised transform function — abort and return the raw value.
                return $value;
            }
            $fns[] = $fn;
        }
        return new Transform($fns);
    }

    private function valueToTransformFunction(Value $value): ?TransformFunction
    {
        if (!$value instanceof CssFunction) {
            return null;
        }
        $name = strtolower($value->name);
        $args = $value->arguments;
        return match ($name) {
            'translate' => $this->buildTranslate($args, false),
            'translatex' => count($args) === 1 ? new TranslateTransform($this->toLengthOrPct($args[0]), new Length(0, LengthUnit::Px)) : null,
            'translatey' => count($args) === 1 ? new TranslateTransform(new Length(0, LengthUnit::Px), $this->toLengthOrPct($args[0])) : null,
            'translatez' => count($args) === 1 && $args[0] instanceof Length ? new TranslateTransform(new Length(0, LengthUnit::Px), new Length(0, LengthUnit::Px), $args[0]) : null,
            'translate3d' => $this->buildTranslate($args, true),
            'rotate' => count($args) === 1 ? new RotateTransform($this->toAngleDeg($args[0])) : null,
            'rotatex' => count($args) === 1 ? new RotateTransform($this->toAngleDeg($args[0]), 1.0, 0.0, 0.0) : null,
            'rotatey' => count($args) === 1 ? new RotateTransform($this->toAngleDeg($args[0]), 0.0, 1.0, 0.0) : null,
            'rotatez' => count($args) === 1 ? new RotateTransform($this->toAngleDeg($args[0]), 0.0, 0.0, 1.0) : null,
            'rotate3d' => $this->buildRotate3d($args),
            'scale' => $this->buildScale($args),
            'scalex' => count($args) === 1 ? new ScaleTransform($this->toFloat($args[0]), 1.0) : null,
            'scaley' => count($args) === 1 ? new ScaleTransform(1.0, $this->toFloat($args[0])) : null,
            'scalez' => count($args) === 1 ? new ScaleTransform(1.0, 1.0, $this->toFloat($args[0])) : null,
            'scale3d' => count($args) === 3 ? new ScaleTransform($this->toFloat($args[0]), $this->toFloat($args[1]), $this->toFloat($args[2])) : null,
            'skew' => $this->buildSkew($args),
            'skewx' => count($args) === 1 ? new SkewTransform($this->toAngleDeg($args[0])) : null,
            'skewy' => count($args) === 1 ? new SkewTransform(0.0, $this->toAngleDeg($args[0])) : null,
            'matrix' => count($args) === 6 ? new MatrixTransform(
                $this->toFloat($args[0]),
                $this->toFloat($args[1]),
                $this->toFloat($args[2]),
                $this->toFloat($args[3]),
                $this->toFloat($args[4]),
                $this->toFloat($args[5]),
            ) : null,
            default => null,
        };
    }

    /** @param list<Value> $args */
    private function buildTranslate(array $args, bool $is3d): ?TransformFunction
    {
        if ($is3d) {
            if (count($args) !== 3) {
                return null;
            }
            $z = $args[2] instanceof Length ? $args[2] : null;
            if ($z === null) {
                return null;
            }
            return new TranslateTransform($this->toLengthOrPct($args[0]), $this->toLengthOrPct($args[1]), $z);
        }
        if (count($args) === 1) {
            return new TranslateTransform($this->toLengthOrPct($args[0]), new Length(0, LengthUnit::Px));
        }
        if (count($args) === 2) {
            return new TranslateTransform($this->toLengthOrPct($args[0]), $this->toLengthOrPct($args[1]));
        }
        return null;
    }

    /** @param list<Value> $args */
    private function buildRotate3d(array $args): ?RotateTransform
    {
        if (count($args) !== 4) {
            return null;
        }
        return new RotateTransform(
            $this->toAngleDeg($args[3]),
            $this->toFloat($args[0]),
            $this->toFloat($args[1]),
            $this->toFloat($args[2]),
        );
    }

    /** @param list<Value> $args */
    private function buildScale(array $args): ?ScaleTransform
    {
        if (count($args) === 1) {
            $sx = $this->toFloat($args[0]);
            return new ScaleTransform($sx, $sx);
        }
        if (count($args) === 2) {
            return new ScaleTransform($this->toFloat($args[0]), $this->toFloat($args[1]));
        }
        return null;
    }

    /** @param list<Value> $args */
    private function buildSkew(array $args): ?SkewTransform
    {
        if (count($args) === 1) {
            return new SkewTransform($this->toAngleDeg($args[0]));
        }
        if (count($args) === 2) {
            return new SkewTransform($this->toAngleDeg($args[0]), $this->toAngleDeg($args[1]));
        }
        return null;
    }

    private function toLengthOrPct(Value $v): Length|Percentage
    {
        if ($v instanceof Length) {
            return $v;
        }
        if ($v instanceof Percentage) {
            return $v;
        }
        if ($v instanceof Number || $v instanceof Integer) {
            // Per spec: in some contexts unitless numbers are treated as pixels.
            return new Length((float) ($v instanceof Number ? $v->value : $v->value), LengthUnit::Px);
        }
        return new Length(0, LengthUnit::Px);
    }

    private function toAngleDeg(Value $v): float
    {
        if ($v instanceof Angle) {
            return $v->toDegrees();
        }
        if ($v instanceof Number || $v instanceof Integer) {
            // Unitless 0 is the only valid angle without a unit per spec;
            // other unitless values are technically a parse error, but we
            // accept them by treating as degrees.
            return (float) ($v instanceof Number ? $v->value : $v->value);
        }
        return 0.0;
    }

    private function toFloat(Value $v): float
    {
        if ($v instanceof Number || $v instanceof Integer) {
            return (float) ($v instanceof Number ? $v->value : $v->value);
        }
        return 0.0;
    }

    /** @param list<Token> $tokens
     *  @return list<Value>
     */
    private function parseArgs(array $tokens): array
    {
        $groups = self::splitTopLevel($tokens, CommaToken::class);
        return array_map(fn($g): Value => $this->parseSpaceList($g), $groups);
    }

    /** @param list<Token> $tokens */
    private function parseRgbFunction(array $tokens): ?Color
    {
        // Accept both legacy comma form `rgb(r, g, b[, a])` and modern
        // space form `rgb(r g b [/ a])`. Components are number 0-255 or
        // percentage 0-100; alpha is number 0-1 or percentage.
        $tokens = self::trimWhitespace($tokens);
        $commaGroups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($commaGroups) >= 3) {
            $groups = $commaGroups;
        } else {
            // Space-separated; the slash separates alpha.
            $groups = self::splitRgbSpaceForm($tokens);
        }
        $count = count($groups);
        if ($count < 3 || $count > 4) {
            return null;
        }
        $rgb = [];
        for ($i = 0; $i < 3; $i++) {
            $v = $this->extractRgbComponent($groups[$i]);
            if ($v === null) {
                return null;
            }
            $rgb[] = $v;
        }
        $a = 1.0;
        if ($count === 4) {
            $alphaTok = $this->extractAlphaComponent($groups[3]);
            if ($alphaTok === null) {
                return null;
            }
            $a = $alphaTok;
        }
        return new Color($rgb[0], $rgb[1], $rgb[2], $a);
    }

    /** @param list<Token> $group */
    private function extractRgbComponent(array $group): ?float
    {
        $group = self::trimWhitespace($group);
        if (count($group) !== 1) {
            return null;
        }
        $t = $group[0];
        if ($t instanceof NumberToken) {
            return max(0.0, min(1.0, $t->value / 255.0));
        }
        if ($t instanceof PercentageToken) {
            return max(0.0, min(1.0, $t->value / 100.0));
        }
        return null;
    }

    /** @param list<Token> $group */
    private function extractAlphaComponent(array $group): ?float
    {
        $group = self::trimWhitespace($group);
        if (count($group) !== 1) {
            return null;
        }
        $t = $group[0];
        if ($t instanceof NumberToken) {
            return max(0.0, min(1.0, $t->value));
        }
        if ($t instanceof PercentageToken) {
            return max(0.0, min(1.0, $t->value / 100.0));
        }
        return null;
    }

    /**
     * Split tokens by top-level whitespace, with `/` recognised as the
     * alpha separator that yields a 4th group.
     *
     * @param list<Token> $tokens
     * @return list<list<Token>>
     */
    private static function splitRgbSpaceForm(array $tokens): array
    {
        $groups = [];
        $current = [];
        foreach ($tokens as $t) {
            if ($t instanceof WhitespaceToken) {
                if ($current !== []) {
                    $groups[] = $current;
                    $current = [];
                }
                continue;
            }
            if ($t instanceof DelimToken && $t->value === '/') {
                if ($current !== []) {
                    $groups[] = $current;
                    $current = [];
                }
                continue;
            }
            $current[] = $t;
        }
        if ($current !== []) {
            $groups[] = $current;
        }
        return $groups;
    }

    /** @param list<Token> $tokens */
    private function parseHslFunction(array $tokens): ?Color
    {
        $tokens = self::trimWhitespace($tokens);
        $commaGroups = self::splitTopLevel($tokens, CommaToken::class);
        $groups = count($commaGroups) >= 3 ? $commaGroups : self::splitRgbSpaceForm($tokens);
        $count = count($groups);
        if ($count < 3 || $count > 4) {
            return null;
        }
        $h = $this->extractHueComponent($groups[0]);
        $s = $this->extractPercentageComponent($groups[1]);
        $l = $this->extractPercentageComponent($groups[2]);
        if ($h === null || $s === null || $l === null) {
            return null;
        }
        $a = $count === 4 ? $this->extractAlphaComponent($groups[3]) : 1.0;
        if ($a === null) {
            return null;
        }
        [$r, $g, $b] = self::hslToRgb($h, $s, $l);
        return new Color($r, $g, $b, $a);
    }

    /** @param list<Token> $group */
    private function extractHueComponent(array $group): ?float
    {
        $group = self::trimWhitespace($group);
        if (count($group) !== 1) {
            return null;
        }
        $t = $group[0];
        if ($t instanceof NumberToken) {
            return fmod(fmod($t->value, 360) + 360, 360) / 360.0; // normalise to [0, 1)
        }
        if ($t instanceof DimensionToken) {
            $degrees = match (strtolower($t->unit)) {
                'deg' => $t->value,
                'rad' => $t->value * 180 / M_PI,
                'grad' => $t->value * 0.9,
                'turn' => $t->value * 360,
                default => null,
            };
            if ($degrees === null) {
                return null;
            }
            return fmod(fmod($degrees, 360) + 360, 360) / 360.0;
        }
        return null;
    }

    /** @param list<Token> $group */
    private function extractPercentageComponent(array $group): ?float
    {
        $group = self::trimWhitespace($group);
        if (count($group) !== 1) {
            return null;
        }
        $t = $group[0];
        if ($t instanceof PercentageToken) {
            return max(0.0, min(1.0, $t->value / 100.0));
        }
        return null;
    }

    /**
     * Convert HSL (each component in [0, 1]) to sRGB (each component in [0, 1])
     * per CSS Color 4 algorithm.
     *
     * @return array{0:float,1:float,2:float}
     */
    private static function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s === 0.0) {
            return [$l, $l, $l];
        }
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $hueToRgb = static function (float $p, float $q, float $t): float {
            if ($t < 0) {
                $t += 1;
            }
            if ($t > 1) {
                $t -= 1;
            }
            if ($t < 1 / 6) {
                return $p + ($q - $p) * 6 * $t;
            }
            if ($t < 1 / 2) {
                return $q;
            }
            if ($t < 2 / 3) {
                return $p + ($q - $p) * (2 / 3 - $t) * 6;
            }
            return $p;
        };
        return [
            $hueToRgb($p, $q, $h + 1 / 3),
            $hueToRgb($p, $q, $h),
            $hueToRgb($p, $q, $h - 1 / 3),
        ];
    }

    private function parseHexColor(string $hex): ?Color
    {
        $hex = strtolower($hex);
        // Accept 3, 4, 6, 8 hex digits.
        if (!preg_match('/^[0-9a-f]+$/', $hex)) {
            return null;
        }
        $len = strlen($hex);
        if ($len === 3) {
            $r = hexdec($hex[0] . $hex[0]);
            $g = hexdec($hex[1] . $hex[1]);
            $b = hexdec($hex[2] . $hex[2]);
            return new Color($r / 255.0, $g / 255.0, $b / 255.0);
        }
        if ($len === 4) {
            $r = hexdec($hex[0] . $hex[0]);
            $g = hexdec($hex[1] . $hex[1]);
            $b = hexdec($hex[2] . $hex[2]);
            $a = hexdec($hex[3] . $hex[3]);
            return new Color($r / 255.0, $g / 255.0, $b / 255.0, $a / 255.0);
        }
        if ($len === 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            return new Color($r / 255.0, $g / 255.0, $b / 255.0);
        }
        if ($len === 8) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $a = hexdec(substr($hex, 6, 2));
            return new Color($r / 255.0, $g / 255.0, $b / 255.0, $a / 255.0);
        }
        return null;
    }

    // ============================================================
    // var(--name, fallback)
    // ============================================================
    /** @param list<Token> $tokens */
    private function parseVarFunction(array $tokens): ?CustomProperty
    {
        $tokens = self::trimWhitespace($tokens);
        $groups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($groups) < 1 || count($groups) > 2) {
            return null;
        }
        $nameTokens = self::trimWhitespace($groups[0]);
        if (count($nameTokens) !== 1 || !($nameTokens[0] instanceof IdentToken)) {
            return null;
        }
        $name = $nameTokens[0]->value;
        if (!str_starts_with($name, '--')) {
            return null;
        }
        $fallback = null;
        if (count($groups) === 2) {
            $fallback = $this->parseSpaceList($groups[1]);
        }
        return new CustomProperty($name, $fallback);
    }

    // ============================================================
    // color(<space> r g b [/ a])
    // ============================================================
    /** @param list<Token> $tokens */
    private function parseColorFunction(array $tokens): ?Color
    {
        $tokens = self::trimWhitespace($tokens);
        $groups = self::splitRgbSpaceForm($tokens);
        // Expect: [space] [r] [g] [b] (4 groups), with optional [a] (5 groups).
        if (count($groups) < 4 || count($groups) > 5) {
            return null;
        }
        $spaceTokens = self::trimWhitespace($groups[0]);
        if (count($spaceTokens) !== 1 || !($spaceTokens[0] instanceof IdentToken)) {
            return null;
        }
        $space = match (strtolower($spaceTokens[0]->value)) {
            'srgb' => ColorSpace::sRGB,
            'display-p3' => ColorSpace::DisplayP3,
            'a98-rgb' => ColorSpace::A98RGB,
            'prophoto-rgb' => ColorSpace::ProPhotoRGB,
            'rec2020' => ColorSpace::Rec2020,
            default => null,
        };
        if ($space === null) {
            return null;
        }
        $components = [];
        for ($i = 1; $i <= 3; $i++) {
            $c = $this->extractColorComponent($groups[$i]);
            if ($c === null) {
                return null;
            }
            $components[] = $c;
        }
        $a = 1.0;
        if (count($groups) === 5) {
            $aValue = $this->extractAlphaComponent($groups[4]);
            if ($aValue === null) {
                return null;
            }
            $a = $aValue;
        }
        return new Color($components[0], $components[1], $components[2], $a, $space);
    }

    /** @param list<Token> $group */
    private function extractColorComponent(array $group): ?float
    {
        $group = self::trimWhitespace($group);
        if (count($group) !== 1) {
            return null;
        }
        $t = $group[0];
        if ($t instanceof NumberToken) {
            // color() takes 0-1 numbers for sRGB-ish spaces.
            return max(0.0, min(1.0, $t->value));
        }
        if ($t instanceof PercentageToken) {
            return max(0.0, min(1.0, $t->value / 100.0));
        }
        return null;
    }

    // ============================================================
    // calc() / min() / max() / clamp() / sin() / cos() / ...
    // ============================================================
    /** @param list<Token> $tokens */
    private function parseCalcFunction(CalcFunction $func, array $tokens): ?Calc
    {
        $tokens = self::trimWhitespace($tokens);
        if ($func === CalcFunction::Calc) {
            $expr = $this->parseCalcSum($tokens);
            if ($expr === null) {
                return null;
            }
            return new Calc($expr);
        }
        // Multi-argument math functions: comma-separated arguments.
        $groups = self::splitTopLevel($tokens, CommaToken::class);
        $args = [];
        foreach ($groups as $group) {
            $expr = $this->parseCalcSum(self::trimWhitespace($group));
            if ($expr === null) {
                return null;
            }
            $args[] = $expr;
        }
        return new Calc(new CalcFunc($func, $args));
    }

    /**
     * Parse a calc-sum expression: product (('+' | '-') product)*. Per CSS
     * Values 4 the +/- operators MUST have whitespace on both sides; the
     * tokenizer guarantees this by greedily consuming `+2`/`-2` as signed
     * numbers when there's no whitespace.
     *
     * @param list<Token> $tokens
     */
    private function parseCalcSum(array $tokens): ?CalcExpression
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        // Split on top-level +/- delim tokens.
        $parts = []; // alternating: [expr, op, expr, op, ...]
        $current = [];
        $depth = 0;
        foreach ($tokens as $t) {
            if ($depth === 0 && $t instanceof DelimToken && ($t->value === '+' || $t->value === '-')) {
                $parts[] = self::trimWhitespace($current);
                $parts[] = $t->value;
                $current = [];
                continue;
            }
            $cls = $t::class;
            if (str_ends_with($cls, '\\LeftParenToken') || $t instanceof FunctionToken) {
                $depth++;
            } elseif (str_ends_with($cls, '\\RightParenToken')) {
                if ($depth > 0) {
                    $depth--;
                }
            }
            $current[] = $t;
        }
        $parts[] = self::trimWhitespace($current);

        $left = $this->parseCalcProduct($parts[0]);
        if ($left === null) {
            return null;
        }
        for ($i = 1; $i < count($parts); $i += 2) {
            $op = $parts[$i] === '+' ? CalcOp::Add : CalcOp::Sub;
            $right = $this->parseCalcProduct($parts[$i + 1] ?? []);
            if ($right === null) {
                return null;
            }
            $left = new CalcBinary($left, $op, $right);
        }
        return $left;
    }

    /** @param list<Token> $tokens */
    private function parseCalcProduct(array $tokens): ?CalcExpression
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        $parts = [];
        $current = [];
        $depth = 0;
        foreach ($tokens as $t) {
            if ($depth === 0 && $t instanceof DelimToken && ($t->value === '*' || $t->value === '/')) {
                $parts[] = self::trimWhitespace($current);
                $parts[] = $t->value;
                $current = [];
                continue;
            }
            $cls = $t::class;
            if (str_ends_with($cls, '\\LeftParenToken') || $t instanceof FunctionToken) {
                $depth++;
            } elseif (str_ends_with($cls, '\\RightParenToken')) {
                if ($depth > 0) {
                    $depth--;
                }
            }
            $current[] = $t;
        }
        $parts[] = self::trimWhitespace($current);

        $left = $this->parseCalcValue($parts[0]);
        if ($left === null) {
            return null;
        }
        for ($i = 1; $i < count($parts); $i += 2) {
            $op = $parts[$i] === '*' ? CalcOp::Mul : CalcOp::Div;
            $right = $this->parseCalcValue($parts[$i + 1] ?? []);
            if ($right === null) {
                return null;
            }
            $left = new CalcBinary($left, $op, $right);
        }
        return $left;
    }

    /** @param list<Token> $tokens */
    private function parseCalcValue(array $tokens): ?CalcExpression
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        // Parenthesised expression — strip a balanced outer pair.
        $head = $tokens[0];
        $last = $tokens[count($tokens) - 1];
        if ($this->isMatchingParenWrap($tokens)) {
            return $this->parseCalcSum(array_slice($tokens, 1, count($tokens) - 2));
        }
        // Inline math function — e.g. nested calc, min, max.
        if ($head instanceof FunctionToken && $last instanceof RightParenToken && count($tokens) >= 2) {
            $inner = array_slice($tokens, 1, count($tokens) - 2);
            $name = strtolower($head->name);
            $func = CalcFunction::tryFrom($name);
            if ($func === CalcFunction::Calc) {
                return $this->parseCalcSum($inner);
            }
            if ($func !== null) {
                $groups = self::splitTopLevel($inner, CommaToken::class);
                $args = [];
                foreach ($groups as $g) {
                    $expr = $this->parseCalcSum(self::trimWhitespace($g));
                    if ($expr === null) {
                        return null;
                    }
                    $args[] = $expr;
                }
                return new CalcFunc($func, $args);
            }
            return null;
        }
        // Single primitive value.
        if (count($tokens) === 1) {
            $value = $this->parseSingle($tokens);
            if ($value instanceof Number || $value instanceof Integer
                || $value instanceof Length || $value instanceof Percentage
                || $value instanceof Angle
            ) {
                return new CalcLeaf($value);
            }
        }
        return null;
    }

    /** @param list<Token> $tokens */
    private function isMatchingParenWrap(array $tokens): bool
    {
        if (count($tokens) < 2) {
            return false;
        }
        $head = $tokens[0];
        $last = $tokens[count($tokens) - 1];
        if (!str_ends_with($head::class, '\\LeftParenToken')) {
            return false;
        }
        if (!str_ends_with($last::class, '\\RightParenToken')) {
            return false;
        }
        // Walk to verify the leading paren matches the trailing one (not
        // a separate nested pair).
        $depth = 0;
        $matched = -1;
        foreach ($tokens as $i => $t) {
            if (str_ends_with($t::class, '\\LeftParenToken') || $t instanceof FunctionToken) {
                $depth++;
            } elseif (str_ends_with($t::class, '\\RightParenToken')) {
                $depth--;
                if ($depth === 0) {
                    $matched = $i;
                    break;
                }
            }
        }
        return $matched === count($tokens) - 1;
    }

    // ============================================================
    // linear-gradient / radial-gradient
    // ============================================================
    /** @param list<Token> $tokens */
    private function parseLinearGradient(array $tokens, bool $repeating): ?LinearGradient
    {
        $tokens = self::trimWhitespace($tokens);
        $groups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($groups) < 2) {
            return null;
        }
        // First group may be the angle/side spec, or directly a colour stop.
        $first = self::trimWhitespace($groups[0]);
        $angleDeg = 180.0; // default: top → bottom
        $stopGroups = $groups;
        $maybeAngle = $this->parseLinearAngleHeader($first);
        if ($maybeAngle !== null) {
            $angleDeg = $maybeAngle;
            $stopGroups = array_slice($groups, 1);
        }
        $stops = [];
        foreach ($stopGroups as $g) {
            $stop = $this->parseGradientStop($g);
            if ($stop === null) {
                return null;
            }
            $stops[] = $stop;
        }
        if (count($stops) < 2) {
            return null;
        }
        return new LinearGradient($angleDeg, $stops, $repeating);
    }

    /**
     * Parse `<angle>` or `to <side-or-corner>` into degrees, or null if the
     * first comma-group is actually a colour stop.
     *
     * @param list<Token> $tokens
     */
    private function parseLinearAngleHeader(array $tokens): ?float
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        $head = $tokens[0];
        // Direct angle: e.g. `45deg`, `0.25turn`.
        if ($head instanceof DimensionToken) {
            $unit = AngleUnit::tryFrom(strtolower($head->unit));
            if ($unit !== null) {
                return $unit->toDegrees($head->value);
            }
        }
        // `to <side> [<side>]`.
        if ($head instanceof IdentToken && strtolower($head->value) === 'to') {
            $sides = [];
            for ($i = 1; $i < count($tokens); $i++) {
                if ($tokens[$i] instanceof WhitespaceToken) {
                    continue;
                }
                if ($tokens[$i] instanceof IdentToken) {
                    $sides[] = strtolower($tokens[$i]->value);
                }
            }
            return self::sidesToAngle($sides);
        }
        return null;
    }

    /** @param list<string> $sides */
    private static function sidesToAngle(array $sides): float
    {
        // Per spec table.
        sort($sides);
        $key = implode(' ', $sides);
        return match ($key) {
            'top' => 0.0,
            'right top', 'top right' => 45.0,
            'right' => 90.0,
            'bottom right', 'right bottom' => 135.0,
            'bottom' => 180.0,
            'bottom left', 'left bottom' => 225.0,
            'left' => 270.0,
            'left top', 'top left' => 315.0,
            default => 180.0,
        };
    }

    /** @param list<Token> $tokens */
    private function parseGradientStop(array $tokens): ?GradientStop
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        // Split by whitespace: <color> [<position>]
        $parts = self::splitOnWhitespace($tokens);
        if (count($parts) === 0) {
            return null;
        }
        $colorValue = $this->parseSingle($parts[0]);
        if (!$colorValue instanceof Color) {
            return null;
        }
        $position = null;
        if (count($parts) >= 2) {
            $posVal = $this->parseSingle($parts[1]);
            if ($posVal instanceof Length || $posVal instanceof Percentage) {
                $position = $posVal;
            }
        }
        return new GradientStop($colorValue, $position);
    }

    /** @param list<Token> $tokens */
    private function parseRadialGradient(array $tokens, bool $repeating): ?RadialGradient
    {
        $tokens = self::trimWhitespace($tokens);
        $groups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($groups) < 2) {
            return null;
        }
        // First group MAY be shape/size [at position]; otherwise it's a stop.
        $first = self::trimWhitespace($groups[0]);
        $shape = GradientShape::Ellipse;
        $sizeX = null;
        $sizeY = null;
        $centerX = null;
        $centerY = null;
        $stopGroups = $groups;
        if ($this->isRadialHeader($first)) {
            [$shape, $sizeX, $sizeY, $centerX, $centerY] = $this->parseRadialHeader($first);
            $stopGroups = array_slice($groups, 1);
        }
        $stops = [];
        foreach ($stopGroups as $g) {
            $stop = $this->parseGradientStop($g);
            if ($stop === null) {
                return null;
            }
            $stops[] = $stop;
        }
        if (count($stops) < 2) {
            return null;
        }
        return new RadialGradient($shape, $sizeX, $sizeY, $centerX, $centerY, $stops, $repeating);
    }

    /** @param list<Token> $tokens */
    private function isRadialHeader(array $tokens): bool
    {
        foreach ($tokens as $t) {
            if ($t instanceof IdentToken
                && in_array(strtolower($t->value), ['circle', 'ellipse', 'at', 'closest-side', 'closest-corner', 'farthest-side', 'farthest-corner'], true)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<Token> $tokens
     * @return array{0: GradientShape, 1: ?Length, 2: ?Length, 3: ?Length, 4: ?Length}
     */
    private function parseRadialHeader(array $tokens): array
    {
        $shape = GradientShape::Ellipse;
        $sizeX = null;
        $sizeY = null;
        $centerX = null;
        $centerY = null;
        $atIdx = null;
        foreach ($tokens as $i => $t) {
            if ($t instanceof IdentToken && strtolower($t->value) === 'at') {
                $atIdx = $i;
                break;
            }
        }
        $headerPart = $atIdx !== null ? array_slice($tokens, 0, $atIdx) : $tokens;
        foreach (self::splitOnWhitespace($headerPart) as $piece) {
            $piece = self::trimWhitespace($piece);
            if ($piece === []) {
                continue;
            }
            $head = $piece[0];
            if ($head instanceof IdentToken) {
                $name = strtolower($head->value);
                if ($name === 'circle') {
                    $shape = GradientShape::Circle;
                } elseif ($name === 'ellipse') {
                    $shape = GradientShape::Ellipse;
                }
                // closest-side etc. are sizing keywords; ignored for now.
            } elseif ($head instanceof DimensionToken) {
                $val = $this->parseSingle($piece);
                if ($val instanceof Length) {
                    if ($sizeX === null) {
                        $sizeX = $val;
                    } else {
                        $sizeY = $val;
                    }
                }
            }
        }
        if ($atIdx !== null) {
            $positionPart = array_slice($tokens, $atIdx + 1);
            $positions = [];
            foreach (self::splitOnWhitespace($positionPart) as $piece) {
                $piece = self::trimWhitespace($piece);
                if ($piece === []) {
                    continue;
                }
                $val = $this->parseSingle($piece);
                if ($val instanceof Length) {
                    $positions[] = $val;
                }
            }
            $centerX = $positions[0] ?? null;
            $centerY = $positions[1] ?? null;
        }
        return [$shape, $sizeX, $sizeY, $centerX, $centerY];
    }

    // ============================================================
    // Token-stream utilities
    // ============================================================

    /** @param list<Token> $tokens
     *  @return list<Token>
     */
    private static function trimWhitespace(array $tokens): array
    {
        $start = 0;
        $end = count($tokens) - 1;
        while ($start <= $end && ($tokens[$start] instanceof WhitespaceToken || $tokens[$start] instanceof EofToken)) {
            $start++;
        }
        while ($end >= $start && ($tokens[$end] instanceof WhitespaceToken || $tokens[$end] instanceof EofToken)) {
            $end--;
        }
        return array_slice($tokens, $start, $end - $start + 1);
    }

    /**
     * Split a flat token list at top-level instances of $separator (i.e.
     * outside any nested parens / brackets).
     *
     * @param list<Token> $tokens
     * @param class-string<Token> $separator
     * @return list<list<Token>>
     */
    private static function splitTopLevel(array $tokens, string $separator): array
    {
        $groups = [];
        $current = [];
        $depth = 0;
        foreach ($tokens as $t) {
            $cls = $t::class;
            if ($depth === 0 && $cls === $separator) {
                $groups[] = $current;
                $current = [];
                continue;
            }
            if (str_ends_with($cls, '\\LeftParenToken')
                || str_ends_with($cls, '\\LeftBracketToken')
                || str_ends_with($cls, '\\LeftBraceToken')
                || $t instanceof FunctionToken
            ) {
                $depth++;
            } elseif (str_ends_with($cls, '\\RightParenToken')
                || str_ends_with($cls, '\\RightBracketToken')
                || str_ends_with($cls, '\\RightBraceToken')
            ) {
                if ($depth > 0) {
                    $depth--;
                }
            }
            $current[] = $t;
        }
        $groups[] = $current;
        return $groups;
    }

    /**
     * Like {@see splitTopLevel} but splits on a specific `DelimToken` value
     * (typically `/` for the slash-shorthand pattern).
     *
     * @param list<Token> $tokens
     * @return list<list<Token>>
     */
    private static function splitTopLevelDelim(array $tokens, string $delim): array
    {
        $groups = [];
        $current = [];
        $depth = 0;
        foreach ($tokens as $t) {
            if ($depth === 0 && $t instanceof DelimToken && $t->value === $delim) {
                $groups[] = $current;
                $current = [];
                continue;
            }
            if ($t instanceof LeftParenToken
                || $t instanceof LeftBracketToken
                || $t instanceof LeftBraceToken
                || $t instanceof FunctionToken
            ) {
                $depth++;
            } elseif ($t instanceof RightParenToken
                || $t instanceof RightBracketToken
                || $t instanceof RightBraceToken
            ) {
                if ($depth > 0) {
                    $depth--;
                }
            }
            $current[] = $t;
        }
        $groups[] = $current;
        return $groups;
    }

    /** @param list<Token> $tokens
     *  @return list<list<Token>>
     */
    private static function splitOnWhitespace(array $tokens): array
    {
        $groups = [];
        $current = [];
        $depth = 0;
        foreach ($tokens as $t) {
            if ($depth === 0 && $t instanceof WhitespaceToken) {
                if ($current !== []) {
                    $groups[] = $current;
                    $current = [];
                }
                continue;
            }
            $cls = $t::class;
            if (str_ends_with($cls, '\\LeftParenToken')
                || str_ends_with($cls, '\\LeftBracketToken')
                || str_ends_with($cls, '\\LeftBraceToken')
                || $t instanceof FunctionToken
            ) {
                $depth++;
            } elseif (str_ends_with($cls, '\\RightParenToken')
                || str_ends_with($cls, '\\RightBracketToken')
                || str_ends_with($cls, '\\RightBraceToken')
            ) {
                if ($depth > 0) {
                    $depth--;
                }
            }
            $current[] = $t;
        }
        if ($current !== []) {
            $groups[] = $current;
        }
        return $groups;
    }
}
