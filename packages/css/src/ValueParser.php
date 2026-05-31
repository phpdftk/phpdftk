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
use Phpdftk\Css\Value\AnchorFunction;
use Phpdftk\Css\Value\AnchorSizeFunction;
use Phpdftk\Css\Value\AttrFunction;
use Phpdftk\Css\Value\BasicShape;
use Phpdftk\Css\Value\CircleShape;
use Phpdftk\Css\Value\EllipseShape;
use Phpdftk\Css\Value\EnvFunction;
use Phpdftk\Css\Value\InsetShape;
use Phpdftk\Css\Value\PathShape;
use Phpdftk\Css\Value\PolygonShape;
use Phpdftk\Css\Value\RectShape;
use Phpdftk\Css\Value\XywhShape;
use Phpdftk\Css\Value\Filter;
use Phpdftk\Css\Value\FilterFunction;
use Phpdftk\Css\Value\FilterKind;
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
use Phpdftk\Css\Value\ColorMix;
use Phpdftk\Css\Value\CubicBezier;
use Phpdftk\Css\Value\StepsEasing;
use Phpdftk\Css\Value\StepsJumpTerm;
use Phpdftk\Css\Value\HueInterpolation;
use Phpdftk\Css\Value\ColorSpace;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\CustomProperty;
use Phpdftk\Css\Value\Gradient;
use Phpdftk\Css\Value\ConicGradient;
use Phpdftk\Css\Value\CrossFade;
use Phpdftk\Css\Value\CrossFadeOption;
use Phpdftk\Css\Value\GradientShape;
use Phpdftk\Css\Value\GradientStop;
use Phpdftk\Css\Value\ImageSet;
use Phpdftk\Css\Value\ImageSetOption;
use Phpdftk\Css\Value\Integer;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use Phpdftk\Css\Value\LightDark;
use Phpdftk\Css\Value\LinearEasing;
use Phpdftk\Css\Value\LinearEasingStop;
use Phpdftk\Css\Value\LinearGradient;
use Phpdftk\Css\Value\ListSeparator;
use Phpdftk\Css\Value\MatrixTransform;
use Phpdftk\Css\Value\NamedColors;
use Phpdftk\Css\Value\Number;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\RadialGradient;
use Phpdftk\Css\Value\RelativeColor;
use Phpdftk\Css\Value\RotateTransform;
use Phpdftk\Css\Value\ScaleTransform;
use Phpdftk\Css\Value\SkewTransform;
use Phpdftk\Css\Value\StringValue;
use Phpdftk\Css\Value\TargetFunction;
use Phpdftk\Css\Value\TargetFunctionKind;
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
        // CSS Color 5 §4 — relative color syntax must run BEFORE
        // the regular rgb / hsl / lab / etc. function parsers so
        // they don't trip on the leading `from` keyword.
        if (in_array($name, ['rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'color'], true)) {
            $relative = $this->parseRelativeColorIfPresent($name, $tokens);
            if ($relative !== null) {
                return $relative;
            }
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
        if ($name === 'lab' || $name === 'lch' || $name === 'oklab' || $name === 'oklch') {
            return $this->parseLabFunction($name, $tokens) ?? new CssFunction($name, $this->parseArgs($tokens));
        }
        if ($name === 'color-mix') {
            return $this->parseColorMixFunction($tokens) ?? new CssFunction($name, $this->parseArgs($tokens));
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
        if ($name === 'conic-gradient' || $name === 'repeating-conic-gradient') {
            $g = $this->parseConicGradient($tokens, $name === 'repeating-conic-gradient');
            if ($g !== null) {
                return $g;
            }
        }
        if ($name === 'image-set' || $name === '-webkit-image-set') {
            $set = $this->parseImageSet($tokens);
            if ($set !== null) {
                return $set;
            }
        }
        if ($name === 'anchor') {
            $a = $this->parseAnchorFunction($tokens);
            if ($a !== null) {
                return $a;
            }
        }
        if ($name === 'anchor-size') {
            $a = $this->parseAnchorSizeFunction($tokens);
            if ($a !== null) {
                return $a;
            }
        }
        if ($name === 'attr') {
            $a = $this->parseAttrFunction($tokens);
            if ($a !== null) {
                return $a;
            }
        }
        if ($name === 'env') {
            $e = $this->parseEnvFunction($tokens);
            if ($e !== null) {
                return $e;
            }
        }
        if ($name === 'target-counter' || $name === 'target-counters' || $name === 'target-text') {
            $t = $this->parseTargetFunction($name, $tokens);
            if ($t !== null) {
                return $t;
            }
        }
        if ($name === 'light-dark') {
            $ld = $this->parseLightDark($tokens);
            if ($ld !== null) {
                return $ld;
            }
        }
        if ($name === 'cross-fade') {
            $cf = $this->parseCrossFade($tokens);
            if ($cf !== null) {
                return $cf;
            }
        }
        if ($name === 'linear') {
            $le = $this->parseLinearEasingFunction($tokens);
            if ($le !== null) {
                return $le;
            }
        }
        if ($name === 'cubic-bezier') {
            $cb = $this->parseCubicBezierFunction($tokens);
            if ($cb !== null) {
                return $cb;
            }
        }
        if ($name === 'steps') {
            $st = $this->parseStepsFunction($tokens);
            if ($st !== null) {
                return $st;
            }
        }
        if (in_array($name, ['circle', 'ellipse', 'inset', 'polygon', 'rect', 'xywh', 'path'], true)) {
            $shape = $this->parseBasicShape($name, $tokens);
            if ($shape !== null) {
                return $shape;
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
        return $this->postProcessTransform($this->parseFromString($css));
    }

    /**
     * Convert an already-parsed generic value (`CssFunction` or
     * `ValueList` of `CssFunction`s) into a typed `Transform` value.
     * Falls back to the original value when a function isn't a
     * recognised transform — preserves `none` and any future
     * additions that aren't yet handled.
     */
    public function postProcessTransform(Value $value): Value
    {
        if ($value instanceof Transform) {
            return $value;
        }
        $items = $value instanceof ValueList ? $value->values : [$value];
        $fns = [];
        foreach ($items as $v) {
            $fn = $this->valueToTransformFunction($v);
            if ($fn === null) {
                return $value;
            }
            $fns[] = $fn;
        }
        return new Transform($fns);
    }

    /**
     * Parse + type a `filter:` declaration. Convenience entry
     * point for tests + the cascade post-processor.
     */
    public function parseFilter(string $css): Value
    {
        return $this->postProcessFilter($this->parseFromString($css));
    }

    /**
     * Convert a generic parsed value (CssFunction, ValueList of
     * CssFunctions, Url) into a typed `Filter` so the painter can
     * dispatch by FilterKind without re-inspecting strings. The
     * keyword `none` (initial value) and any unrecognised
     * function fall through unchanged.
     */
    public function postProcessFilter(Value $value): Value
    {
        if ($value instanceof Filter) {
            return $value;
        }
        if ($value instanceof Keyword && strtolower($value->name) === 'none') {
            return $value;
        }
        $items = $value instanceof ValueList ? $value->values : [$value];
        $fns = [];
        foreach ($items as $v) {
            $fn = $this->valueToFilterFunction($v);
            if ($fn === null) {
                return $value;
            }
            $fns[] = $fn;
        }
        if ($fns === []) {
            return $value;
        }
        return new Filter($fns);
    }

    private function valueToFilterFunction(Value $value): ?FilterFunction
    {
        // url() references for SVG filter chains are valid filter
        // values too.
        if ($value instanceof Url) {
            return new FilterFunction(FilterKind::Url, [$value]);
        }
        if (!($value instanceof CssFunction)) {
            return null;
        }
        $kind = FilterKind::tryFrom(strtolower($value->name));
        if ($kind === null) {
            return null;
        }
        return new FilterFunction($kind, $value->arguments);
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
            // CSS Transforms 2 §6.6 — `matrix3d(a1..a16)` in column-
            // major order. Print is 2D, so we extract the affine
            // (a, b, c, d, e, f) entries by projecting the 4×4 onto
            // the X / Y plane: the 2D matrix `matrix(m11, m12, m21,
            // m22, m41, m42)` corresponds to input indices 0, 1, 4,
            // 5, 12, 13. The 3D rotation / perspective components
            // collapse out, which is the same flattening other 3D
            // transforms get.
            'matrix3d' => count($args) === 16 ? new MatrixTransform(
                $this->toFloat($args[0]),
                $this->toFloat($args[1]),
                $this->toFloat($args[4]),
                $this->toFloat($args[5]),
                $this->toFloat($args[12]),
                $this->toFloat($args[13]),
            ) : null,
            // `perspective(<length>)` accepts the syntax but the
            // print medium has no depth, so the function flattens
            // to identity. Authors typically combine `perspective`
            // with `rotateX` / `rotateY`; in print only the
            // rotation portion contributes visually (via the
            // cos-flatten in the painter).
            'perspective' => new MatrixTransform(1.0, 0.0, 0.0, 1.0, 0.0, 0.0),
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
            'srgb-linear' => ColorSpace::sRGBLinear,
            'display-p3' => ColorSpace::DisplayP3,
            'a98-rgb' => ColorSpace::A98RGB,
            'prophoto-rgb' => ColorSpace::ProPhotoRGB,
            'rec2020' => ColorSpace::Rec2020,
            'xyz', 'xyz-d65' => ColorSpace::XYZD65,
            'xyz-d50' => ColorSpace::XYZD50,
            default => null,
        };
        if ($space === null) {
            return null;
        }
        $isXyzSpace = $space === ColorSpace::XYZD50 || $space === ColorSpace::XYZD65;
        $components = [];
        for ($i = 1; $i <= 3; $i++) {
            $c = $this->extractColorComponent($groups[$i], $isXyzSpace);
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

    /**
     * Extract a `color()` component. For sRGB-ish spaces this is
     * 0-1; CSS Color 4 §6 also allows out-of-gamut values, so we
     * preserve numeric inputs as-is rather than clamping. For XYZ
     * spaces, percentages map to the sRGB white-point's Y = 100%
     * (which is XYZ 1) so the divide-by-100 is still right.
     *
     * `none` ident resolves to 0 — a Missing-typed component lands
     * once the 4E color engine ships.
     *
     * @param list<Token> $group
     */
    private function extractColorComponent(array $group, bool $isXyzSpace = false): ?float
    {
        unset($isXyzSpace);
        $group = self::trimWhitespace($group);
        if (count($group) !== 1) {
            return null;
        }
        $t = $group[0];
        if ($t instanceof IdentToken && strtolower($t->value) === 'none') {
            return 0.0;
        }
        if ($t instanceof NumberToken) {
            // CSS Color 4 §6 allows out-of-gamut values; preserve
            // the raw number and let the gamut-mapping algorithm
            // (4E) decide what to do with it.
            return (float) $t->value;
        }
        if ($t instanceof PercentageToken) {
            return (float) $t->value / 100.0;
        }
        return null;
    }

    // ============================================================
    // lab() / lch() / oklab() / oklch()  — CSS Color 4 §10
    // ============================================================
    /**
     * Parse a `lab()`, `lch()`, `oklab()`, or `oklch()` functional
     * notation. CSS Color 4 §10 syntax:
     *
     *   lab(L a b [/ alpha])
     *   lch(L C H [/ alpha])
     *   oklab(L a b [/ alpha])
     *   oklch(L C H [/ alpha])
     *
     * Component value ranges per CSS Color 4:
     *
     *   lab    L: 0-100 (or 0-100%); a, b: ±125 (±100% = ±125)
     *   lch    L: 0-100 (or 0-100%); C: 0-150 (0-100% = 0-150); H: angle
     *   oklab  L: 0-1   (or 0-100%); a, b: ±0.4 (±100% = ±0.4)
     *   oklch  L: 0-1   (or 0-100%); C: 0-0.4 (0-100% = 0-0.4); H: angle
     *
     * Resolved values are stored in the Color's r/g/b slots without
     * normalisation — the color space tag identifies which axes
     * they represent. Downstream consumers (the 4E color engine)
     * do the gamut-conversion math.
     *
     * @param list<Token> $tokens
     */
    private function parseLabFunction(string $name, array $tokens): ?Color
    {
        $tokens = self::trimWhitespace($tokens);
        $groups = self::splitRgbSpaceForm($tokens);
        if (count($groups) < 3 || count($groups) > 4) {
            return null;
        }

        $isLightnessPct100 = ($name === 'lab' || $name === 'lch');
        $L = $this->extractLabLightness($groups[0], $isLightnessPct100);
        if ($L === null) {
            return null;
        }

        $space = match ($name) {
            'lab' => ColorSpace::Lab,
            'lch' => ColorSpace::Lch,
            'oklab' => ColorSpace::OKLab,
            'oklch' => ColorSpace::OKLCH,
            default => null,
        };
        if ($space === null) {
            return null;
        }

        $isPolar = ($name === 'lch' || $name === 'oklch');
        if ($isPolar) {
            // C (chroma) — 0-150 for lch, 0-0.4 for oklch.
            $chromaMax = $name === 'lch' ? 150.0 : 0.4;
            $c = $this->extractChromaComponent($groups[1], $chromaMax);
            if ($c === null) {
                return null;
            }
            $h = $this->extractLabHueComponent($groups[2]);
            if ($h === null) {
                return null;
            }
            $a = 1.0;
            if (count($groups) === 4) {
                $aValue = $this->extractAlphaComponent($groups[3]);
                if ($aValue === null) {
                    return null;
                }
                $a = $aValue;
            }
            return new Color($L, $c, $h, $a, $space);
        }

        // lab / oklab — a and b in Cartesian coordinates.
        $axisMax = $name === 'lab' ? 125.0 : 0.4;
        $aAxis = $this->extractLabAxisComponent($groups[1], $axisMax);
        $bAxis = $this->extractLabAxisComponent($groups[2], $axisMax);
        if ($aAxis === null || $bAxis === null) {
            return null;
        }
        $alpha = 1.0;
        if (count($groups) === 4) {
            $aValue = $this->extractAlphaComponent($groups[3]);
            if ($aValue === null) {
                return null;
            }
            $alpha = $aValue;
        }
        return new Color($L, $aAxis, $bAxis, $alpha, $space);
    }

    /**
     * Extract a `lab()` / `lch()` / `oklab()` / `oklch()` lightness.
     * `$pctTo100` selects the percentage mapping:
     *   true  → 0-100% maps to 0-100 (lab / lch)
     *   false → 0-100% maps to 0-1   (oklab / oklch)
     *
     * `none` is parsed as a missing component per CSS Color 4 §10.5;
     * we resolve it to 0 for now (a Missing-typed component lands
     * once the 4E color engine ships).
     *
     * @param list<Token> $group
     */
    private function extractLabLightness(array $group, bool $pctTo100): ?float
    {
        $group = self::trimWhitespace($group);
        if (count($group) !== 1) {
            return null;
        }
        $t = $group[0];
        if ($t instanceof IdentToken && strtolower($t->value) === 'none') {
            return 0.0;
        }
        if ($t instanceof NumberToken) {
            return (float) $t->value;
        }
        if ($t instanceof PercentageToken) {
            return $pctTo100 ? (float) $t->value : ((float) $t->value / 100.0);
        }
        return null;
    }

    /**
     * Extract a `lab()` / `oklab()` axis component (a or b).
     * Percentages map ±100% → ±$axisMax. Numbers pass through.
     * `none` resolves to 0.
     *
     * @param list<Token> $group
     */
    private function extractLabAxisComponent(array $group, float $axisMax): ?float
    {
        $group = self::trimWhitespace($group);
        if (count($group) !== 1) {
            return null;
        }
        $t = $group[0];
        if ($t instanceof IdentToken && strtolower($t->value) === 'none') {
            return 0.0;
        }
        if ($t instanceof NumberToken) {
            return (float) $t->value;
        }
        if ($t instanceof PercentageToken) {
            return ((float) $t->value / 100.0) * $axisMax;
        }
        return null;
    }

    /**
     * Extract an `lch()` / `oklch()` chroma component.
     * Percentages map 0-100% → 0-$chromaMax. Numbers pass through.
     * `none` resolves to 0.
     *
     * @param list<Token> $group
     */
    private function extractChromaComponent(array $group, float $chromaMax): ?float
    {
        $group = self::trimWhitespace($group);
        if (count($group) !== 1) {
            return null;
        }
        $t = $group[0];
        if ($t instanceof IdentToken && strtolower($t->value) === 'none') {
            return 0.0;
        }
        if ($t instanceof NumberToken) {
            return max(0.0, (float) $t->value);
        }
        if ($t instanceof PercentageToken) {
            return max(0.0, ((float) $t->value / 100.0) * $chromaMax);
        }
        return null;
    }

    /**
     * Extract an `lch()` / `oklch()` hue component in degrees
     * (CSS Color 4 stores hues as degrees, not the 0-1 normalised
     * form HSL uses). Accepts number (degrees implied), `deg`,
     * `rad`, `grad`, `turn` dimension units, or `none`.
     *
     * @param list<Token> $group
     */
    private function extractLabHueComponent(array $group): ?float
    {
        $group = self::trimWhitespace($group);
        if (count($group) !== 1) {
            return null;
        }
        $t = $group[0];
        if ($t instanceof IdentToken && strtolower($t->value) === 'none') {
            return 0.0;
        }
        if ($t instanceof NumberToken) {
            return self::normaliseHueDegrees((float) $t->value);
        }
        if ($t instanceof DimensionToken) {
            $degrees = match (strtolower($t->unit)) {
                'deg' => (float) $t->value,
                'rad' => (float) $t->value * 180 / M_PI,
                'grad' => (float) $t->value * 0.9,
                'turn' => (float) $t->value * 360,
                default => null,
            };
            if ($degrees === null) {
                return null;
            }
            return self::normaliseHueDegrees($degrees);
        }
        return null;
    }

    /**
     * Wrap a hue angle into the canonical [0, 360) range.
     */
    private static function normaliseHueDegrees(float $degrees): float
    {
        return fmod(fmod($degrees, 360.0) + 360.0, 360.0);
    }

    // ============================================================
    // relative color syntax — CSS Color 5 §4
    // ============================================================
    /**
     * Detect a `<colorFn>(from <color> ...)` prefix and parse the
     * relative-color form. Returns null when the function doesn't
     * start with `from`, so the caller falls back to its non-
     * relative parser.
     *
     * @param list<Token> $tokens
     */
    private function parseRelativeColorIfPresent(string $name, array $tokens): ?RelativeColor
    {
        $trimmed = self::trimWhitespace($tokens);
        if ($trimmed === []) {
            return null;
        }
        $first = $trimmed[0];
        if (!($first instanceof IdentToken) || strtolower($first->value) !== 'from') {
            return null;
        }
        return $this->parseRelativeColorBody($name, array_slice($trimmed, 1));
    }

    /**
     * Parse the body of `<colorFn>(from <source> <c1> <c2> <c3>
     * [/ alpha])` (or the `color(from <source> <space> <c1> <c2>
     * <c3> [/ alpha])` form for `color()`).
     *
     * @param list<Token> $tokensAfterFrom
     */
    private function parseRelativeColorBody(string $name, array $tokensAfterFrom): ?RelativeColor
    {
        $tokensAfterFrom = self::trimWhitespace($tokensAfterFrom);

        // The source color is the next "value" — could be a hex
        // (HashToken), an ident (named color), or a function call.
        // Use collectFunctionLikeRun for function-call runs; for
        // hash / ident, just one token.
        if ($tokensAfterFrom === []) {
            return null;
        }
        $sourceConsumed = self::collectColorRun($tokensAfterFrom, 0);
        if ($sourceConsumed === 0) {
            return null;
        }
        $sourceCss = self::serializeTokens(array_slice($tokensAfterFrom, 0, $sourceConsumed));
        $source = $this->parseFromString($sourceCss);
        if (!($source instanceof Color)) {
            return null;
        }

        $rest = self::trimWhitespace(array_slice($tokensAfterFrom, $sourceConsumed));

        // For `color()`, an additional <space> ident follows.
        $space = null;
        if ($name === 'color') {
            if ($rest === [] || !($rest[0] instanceof IdentToken)) {
                return null;
            }
            $space = match (strtolower($rest[0]->value)) {
                'srgb' => ColorSpace::sRGB,
                'srgb-linear' => ColorSpace::sRGBLinear,
                'display-p3' => ColorSpace::DisplayP3,
                'a98-rgb' => ColorSpace::A98RGB,
                'prophoto-rgb' => ColorSpace::ProPhotoRGB,
                'rec2020' => ColorSpace::Rec2020,
                'xyz', 'xyz-d65' => ColorSpace::XYZD65,
                'xyz-d50' => ColorSpace::XYZD50,
                default => null,
            };
            if ($space === null) {
                return null;
            }
            $rest = self::trimWhitespace(array_slice($rest, 1));
        } else {
            $space = match ($name) {
                'rgb', 'rgba' => ColorSpace::sRGB,
                'hsl', 'hsla' => ColorSpace::sRGB,    // polar in CSS; stored as sRGB
                'hwb' => ColorSpace::sRGB,
                'lab' => ColorSpace::Lab,
                'lch' => ColorSpace::Lch,
                'oklab' => ColorSpace::OKLab,
                'oklch' => ColorSpace::OKLCH,
                default => null,
            };
            if ($space === null) {
                return null;
            }
        }

        // Split the remainder on `/` (alpha separator) and then
        // by whitespace into three component groups. Use the
        // paren-aware splitter so `calc(r + 10)` stays one group
        // despite the inner whitespace.
        $slashGroups = self::splitTopLevelDelim($rest, '/');
        $componentTokens = self::trimWhitespace($slashGroups[0]);
        $componentGroups = self::splitParenAwareSpaceForm($componentTokens);
        if (count($componentGroups) !== 3) {
            return null;
        }

        $c1 = $this->parseRelativeComponent($componentGroups[0]);
        $c2 = $this->parseRelativeComponent($componentGroups[1]);
        $c3 = $this->parseRelativeComponent($componentGroups[2]);
        if ($c1 === null || $c2 === null || $c3 === null) {
            return null;
        }

        $alpha = new Number(1.0);
        if (count($slashGroups) === 2) {
            $alphaTokens = self::trimWhitespace($slashGroups[1]);
            $parsedAlpha = $this->parseRelativeComponent($alphaTokens);
            if ($parsedAlpha === null) {
                return null;
            }
            $alpha = $parsedAlpha;
        }

        return new RelativeColor($space, $source, $c1, $c2, $c3, $alpha);
    }

    /**
     * Like {@see splitRgbSpaceForm} but skips whitespace / `/`
     * delimiters inside paren-balanced runs (function calls etc).
     * Lets `rgb(from red calc(r + 10) g b)` split into 3 groups
     * even though `calc(r + 10)` carries inner whitespace.
     *
     * @param list<Token> $tokens
     * @return list<list<Token>>
     */
    private static function splitParenAwareSpaceForm(array $tokens): array
    {
        $groups = [];
        $current = [];
        $depth = 0;
        foreach ($tokens as $t) {
            if ($t instanceof LeftParenToken || $t instanceof FunctionToken) {
                $depth++;
                $current[] = $t;
                continue;
            }
            if ($t instanceof RightParenToken) {
                $depth--;
                $current[] = $t;
                continue;
            }
            if ($depth === 0 && $t instanceof WhitespaceToken) {
                if ($current !== []) {
                    $groups[] = $current;
                    $current = [];
                }
                continue;
            }
            if ($depth === 0 && $t instanceof DelimToken && $t->value === '/') {
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

    /**
     * Collect tokens for a single color value at the start of the
     * sequence: a hash, a function call (possibly nested), or a
     * single ident (named color). Whitespace consumed too. Returns
     * the count of tokens consumed (0 on no progress).
     *
     * @param list<Token> $tokens
     */
    private static function collectColorRun(array $tokens, int $start): int
    {
        $head = $tokens[$start] ?? null;
        if ($head === null) {
            return 0;
        }
        if ($head instanceof FunctionToken) {
            return self::collectFunctionLikeRun($tokens, $start);
        }
        if ($head instanceof HashToken || $head instanceof IdentToken) {
            return 1;
        }
        return 0;
    }

    /**
     * Parse a single relative-color component expression. The
     * input may be an ident (e.g. `r`, `none`, `alpha`), a
     * number, a percentage, a dimension, or a calc()-like
     * function — any Value the cascade can represent. Returns
     * null when the token group is empty or doesn't parse.
     *
     * @param list<Token> $tokens
     */
    private function parseRelativeComponent(array $tokens): ?Value
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        $css = self::serializeTokens($tokens);
        if ($css === '') {
            return null;
        }
        return $this->parseFromString($css);
    }

    // ============================================================
    // color-mix() — CSS Color 5 §3
    // ============================================================
    /**
     * Parse `color-mix(in <space> [<hue-interpolation> hue], <color>
     * [<percentage>], <color> [<percentage>])` per CSS Color 5 §3.
     *
     * Percentage normalisation per §3.1:
     *
     *   - Both omitted          → 50% / 50%
     *   - One given (p)         → other = 100% − p
     *   - Both given, sum > 0%  → multiply each by 100/(p1+p2) and
     *                             remember the original sum as
     *                             `alphaMultiplier` so the resulting
     *                             color's alpha can be scaled.
     *   - Both 0%               → invalid; return null.
     *
     * Per-space hue interpolation method is captured for polar
     * spaces (HSL, HWB, LCH, OKLCH) and ignored for the rest.
     *
     * @param list<Token> $tokens
     */
    private function parseColorMixFunction(array $tokens): ?ColorMix
    {
        $tokens = self::trimWhitespace($tokens);
        $groups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($groups) !== 3) {
            return null;
        }

        // Group 0: "in <space> [<hue-interp> hue]"
        $methodSpec = $this->parseColorMixMethod(self::trimWhitespace($groups[0]));
        if ($methodSpec === null) {
            return null;
        }
        [$space, $hueInterpolation] = $methodSpec;

        // Groups 1 + 2: each is `<color> [<percentage>]`.
        $left = $this->parseColorMixColorAndPercent(self::trimWhitespace($groups[1]));
        $right = $this->parseColorMixColorAndPercent(self::trimWhitespace($groups[2]));
        if ($left === null || $right === null) {
            return null;
        }
        [$color1, $p1] = $left;
        [$color2, $p2] = $right;

        // Normalise percentages.
        if ($p1 === null && $p2 === null) {
            $p1 = 50.0;
            $p2 = 50.0;
            $alphaMultiplier = 1.0;
        } elseif ($p1 === null) {
            assert($p2 !== null);
            $p1 = 100.0 - $p2;
            $alphaMultiplier = 1.0;
        } elseif ($p2 === null) {
            $p2 = 100.0 - $p1;
            $alphaMultiplier = 1.0;
        } else {
            $sum = $p1 + $p2;
            if ($sum <= 0.0) {
                return null;
            }
            // Per §3.1, when both percentages are present and sum
            // ≠ 100, we scale them and remember `(p1+p2)/100` as
            // the alpha multiplier so the result's alpha can be
            // multiplied by it.
            $alphaMultiplier = min(1.0, $sum / 100.0);
            $p1 = ($p1 / $sum) * 100.0;
            $p2 = ($p2 / $sum) * 100.0;
        }

        return new ColorMix(
            space: $space,
            color1: $color1,
            percentage1: $p1,
            color2: $color2,
            percentage2: $p2,
            alphaMultiplier: $alphaMultiplier,
            hueInterpolation: $hueInterpolation,
        );
    }

    /**
     * Parse the `in <space> [<hue-interp> hue]` head of a
     * color-mix() function. Returns `[ColorSpace, ?HueInterpolation]`
     * or null if malformed.
     *
     * @param list<Token> $tokens
     * @return array{0: ColorSpace, 1: ?HueInterpolation}|null
     */
    private function parseColorMixMethod(array $tokens): ?array
    {
        if ($tokens === []) {
            return null;
        }
        $first = array_shift($tokens);
        if (!($first instanceof IdentToken) || strtolower($first->value) !== 'in') {
            return null;
        }
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        $spaceTok = array_shift($tokens);
        if (!($spaceTok instanceof IdentToken)) {
            return null;
        }
        $space = match (strtolower($spaceTok->value)) {
            'srgb' => ColorSpace::sRGB,
            'srgb-linear' => ColorSpace::sRGBLinear,
            'display-p3' => ColorSpace::DisplayP3,
            'a98-rgb' => ColorSpace::A98RGB,
            'prophoto-rgb' => ColorSpace::ProPhotoRGB,
            'rec2020' => ColorSpace::Rec2020,
            'lab' => ColorSpace::Lab,
            'lch' => ColorSpace::Lch,
            'oklab' => ColorSpace::OKLab,
            'oklch' => ColorSpace::OKLCH,
            'xyz', 'xyz-d65' => ColorSpace::XYZD65,
            'xyz-d50' => ColorSpace::XYZD50,
            // HSL and HWB are polar spaces for mixing only —
            // they're handled by the engine via a sRGB round-trip;
            // we accept them at parse time but tag as sRGB so the
            // engine can lift back into the polar space for hue
            // interpolation.
            'hsl', 'hwb' => ColorSpace::sRGB,
            default => null,
        };
        if ($space === null) {
            return null;
        }

        // Optional `<hue-interp> hue` clause — only meaningful for
        // polar spaces.
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return [$space, null];
        }
        $hueInterp = array_shift($tokens);
        if (!($hueInterp instanceof IdentToken)) {
            return null;
        }
        $method = match (strtolower($hueInterp->value)) {
            'shorter' => HueInterpolation::Shorter,
            'longer' => HueInterpolation::Longer,
            'increasing' => HueInterpolation::Increasing,
            'decreasing' => HueInterpolation::Decreasing,
            default => null,
        };
        if ($method === null) {
            return null;
        }
        $tokens = self::trimWhitespace($tokens);
        // Expect the literal `hue` keyword.
        if (count($tokens) !== 1 || !($tokens[0] instanceof IdentToken) || strtolower($tokens[0]->value) !== 'hue') {
            return null;
        }
        return [$space, $method];
    }

    /**
     * Parse `<color> [<percentage>]` — the percentage may come
     * before or after the color per CSS Color 5 §3.1.
     *
     * @param list<Token> $tokens
     * @return array{0: Color, 1: ?float}|null
     */
    private function parseColorMixColorAndPercent(array $tokens): ?array
    {
        // Find a percentage token at start or end.
        $percent = null;
        if ($tokens !== []) {
            $last = $tokens[count($tokens) - 1];
            if ($last instanceof PercentageToken) {
                $percent = (float) $last->value;
                array_pop($tokens);
            }
        }
        $tokens = self::trimWhitespace($tokens);
        if ($percent === null && $tokens !== [] && $tokens[0] instanceof PercentageToken) {
            $percent = (float) $tokens[0]->value;
            array_shift($tokens);
            $tokens = self::trimWhitespace($tokens);
        }
        $tokens = self::trimWhitespace($tokens);

        // Whatever's left should be a parseable color.
        $color = $this->parseColorFromTokens($tokens);
        if ($color === null) {
            return null;
        }
        return [$color, $percent];
    }

    /**
     * Parse a token sequence into a Color, dispatching to the
     * function / hash / named-color parsers as appropriate. Returns
     * null if the tokens don't resolve to a color.
     *
     * @param list<Token> $tokens
     */
    private function parseColorFromTokens(array $tokens): ?Color
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        // Re-serialise the inner tokens by stripping outer parens
        // and re-parsing through the public entry point so we get
        // hash colors, named colors, and any function-color through
        // one path.
        $css = self::serializeTokens($tokens);
        $value = $this->parseFromString($css);
        return $value instanceof Color ? $value : null;
    }

    /**
     * Roughly reconstruct CSS text from a token list. Adequate for
     * round-tripping color tokens through parseFromString. The CSS
     * Syntax 3 serialisation algorithm has edge cases this doesn't
     * cover (escape sequences, etc.) but for the well-formed color
     * tokens color-mix() argues over, it's enough.
     *
     * @param list<Token> $tokens
     */
    private static function serializeTokens(array $tokens): string
    {
        $out = '';
        foreach ($tokens as $t) {
            $out .= match (true) {
                $t instanceof IdentToken => $t->value,
                $t instanceof NumberToken => (string) $t->value,
                $t instanceof PercentageToken => $t->value . '%',
                $t instanceof DimensionToken => $t->value . $t->unit,
                $t instanceof HashToken => '#' . $t->value,
                $t instanceof StringToken => '"' . str_replace('"', '\\"', $t->value) . '"',
                $t instanceof FunctionToken => $t->name . '(',
                $t instanceof UrlToken => 'url(' . $t->value . ')',
                $t instanceof LeftParenToken => '(',
                $t instanceof RightParenToken => ')',
                $t instanceof CommaToken => ',',
                $t instanceof WhitespaceToken => ' ',
                $t instanceof DelimToken => $t->value,
                default => '',
            };
        }
        return $out;
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

    // ============================================================
    // conic-gradient — CSS Backgrounds 4 / Images 4 §3.5
    // ============================================================
    /**
     * Parse `conic-gradient([from <angle>]? [at <position>]?,
     * <color-stop-list>)`.
     *
     *   conic-gradient(red, blue)
     *   conic-gradient(from 90deg, red, blue)
     *   conic-gradient(at 25% 75%, red, blue)
     *   conic-gradient(from 0deg at center, red, blue, red)
     *   conic-gradient(red 0deg, yellow 90deg, blue 180deg)
     *
     * Stops can use angular positions (degrees) per CSS Images 4
     * §3.5, but `parseGradientStop` only handles length /
     * percentage positions for now — angle-positioned stops fall
     * through to "stop position null" and the engine interpolates
     * uniformly. Full angular stop parsing lands once the
     * gradient painter ships conic rendering.
     *
     * @param list<Token> $tokens
     */
    private function parseConicGradient(array $tokens, bool $repeating): ?ConicGradient
    {
        $tokens = self::trimWhitespace($tokens);
        $groups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($groups) < 2) {
            return null;
        }
        $first = self::trimWhitespace($groups[0]);
        $fromAngle = 0.0;
        $centerX = null;
        $centerY = null;
        $stopGroups = $groups;
        if ($this->isConicHeader($first)) {
            $header = $this->parseConicHeader($first);
            if ($header === null) {
                return null;
            }
            [$fromAngle, $centerX, $centerY] = $header;
            $stopGroups = array_slice($groups, 1);
        }
        if (count($stopGroups) < 2) {
            return null;
        }
        $stops = [];
        foreach ($stopGroups as $g) {
            $stop = $this->parseGradientStop($g);
            if ($stop === null) {
                return null;
            }
            $stops[] = $stop;
        }
        return new ConicGradient(
            fromAngleDeg: $fromAngle,
            centerX: $centerX,
            centerY: $centerY,
            stops: $stops,
            repeating: $repeating,
        );
    }

    /** @param list<Token> $tokens */
    private function isConicHeader(array $tokens): bool
    {
        foreach ($tokens as $t) {
            if ($t instanceof IdentToken
                && in_array(strtolower($t->value), ['from', 'at'], true)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse the optional `from <angle> [at <position>]` /
     * `at <position>` header of a conic-gradient.
     *
     * @param list<Token> $tokens
     * @return array{0: float, 1: ?float, 2: ?float}|null
     */
    private function parseConicHeader(array $tokens): ?array
    {
        $fromAngle = 0.0;
        $centerX = null;
        $centerY = null;

        // Walk the tokens looking for `from <angle>` followed by
        // optional `at <position-x> <position-y>`. Both clauses
        // optional but at least one of them must be present (the
        // caller's `isConicHeader` already verified that).
        $i = 0;
        $count = count($tokens);
        while ($i < $count) {
            $t = $tokens[$i];
            if ($t instanceof WhitespaceToken) {
                $i++;
                continue;
            }
            if ($t instanceof IdentToken && strtolower($t->value) === 'from') {
                $i++;
                // Skip whitespace.
                while ($i < $count && $tokens[$i] instanceof WhitespaceToken) {
                    $i++;
                }
                if ($i >= $count) {
                    return null;
                }
                $angleTok = $tokens[$i];
                if (!($angleTok instanceof DimensionToken) && !($angleTok instanceof NumberToken)) {
                    return null;
                }
                if ($angleTok instanceof DimensionToken) {
                    $unit = AngleUnit::tryFrom(strtolower($angleTok->unit));
                    if ($unit === null) {
                        return null;
                    }
                    $fromAngle = $unit->toDegrees((float) $angleTok->value);
                } else {
                    $fromAngle = (float) $angleTok->value;
                }
                $fromAngle = fmod(fmod($fromAngle, 360.0) + 360.0, 360.0);
                $i++;
                continue;
            }
            if ($t instanceof IdentToken && strtolower($t->value) === 'at') {
                $i++;
                // Collect the next two position values.
                $positions = [];
                while ($i < $count && count($positions) < 2) {
                    $p = $tokens[$i];
                    if ($p instanceof WhitespaceToken) {
                        $i++;
                        continue;
                    }
                    if ($p instanceof PercentageToken) {
                        $positions[] = (float) $p->value / 100.0;
                        $i++;
                        continue;
                    }
                    if ($p instanceof IdentToken) {
                        $name = strtolower($p->value);
                        $val = match ($name) {
                            'left', 'top' => 0.0,
                            'center', 'centre' => 0.5,
                            'right', 'bottom' => 1.0,
                            default => null,
                        };
                        if ($val === null) {
                            return null;
                        }
                        $positions[] = $val;
                        $i++;
                        continue;
                    }
                    return null;
                }
                if ($positions === []) {
                    return null;
                }
                $centerX = $positions[0];
                $centerY = $positions[1] ?? $positions[0];
                continue;
            }
            // Unknown header token.
            return null;
        }
        return [$fromAngle, $centerX, $centerY];
    }

    // ============================================================
    // anchor() / anchor-size() — CSS Anchor Positioning 1 §6, §7
    // ============================================================
    /** @param list<Token> $tokens */
    private function parseAnchorFunction(array $tokens): ?AnchorFunction
    {
        return $this->parseAnchorOrSize(
            $tokens,
            ['top', 'bottom', 'left', 'right', 'start', 'end',
                'self-start', 'self-end', 'center', 'inside', 'outside'],
            isSize: false,
        );
    }

    /** @param list<Token> $tokens */
    private function parseAnchorSizeFunction(array $tokens): ?AnchorSizeFunction
    {
        return $this->parseAnchorOrSize(
            $tokens,
            ['width', 'height', 'block', 'inline', 'self-block', 'self-inline'],
            isSize: true,
        );
    }

    /**
     * Shared parser for `anchor()` / `anchor-size()`. The two
     * differ only in the keyword vocabulary their middle slot
     * accepts.
     *
     * @param list<Token> $tokens
     * @param list<string> $validSideKeywords
     * @return AnchorFunction|AnchorSizeFunction|null
     */
    private function parseAnchorOrSize(array $tokens, array $validSideKeywords, bool $isSize): AnchorFunction|AnchorSizeFunction|null
    {
        $tokens = self::trimWhitespace($tokens);
        // Split on top-level comma — yields [main, fallback?].
        $commaGroups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($commaGroups) < 1 || count($commaGroups) > 2) {
            return null;
        }
        $mainGroup = self::trimWhitespace($commaGroups[0]);
        if ($mainGroup === []) {
            return null;
        }

        $anchorName = null;
        $first = $mainGroup[0];
        // `--my-anchor` starts with `--`; the tokenizer emits this
        // as an IdentToken whose value starts with `--`.
        if ($first instanceof IdentToken && str_starts_with($first->value, '--')) {
            $anchorName = $first->value;
            $mainGroup = self::trimWhitespace(array_slice($mainGroup, 1));
            if ($mainGroup === []) {
                return null;
            }
        }

        // The middle slot — keyword from the per-function list,
        // or a percentage (anchor() only).
        if (count($mainGroup) !== 1) {
            return null;
        }
        $sideTok = $mainGroup[0];
        $side = null;
        if ($sideTok instanceof IdentToken
            && in_array(strtolower($sideTok->value), $validSideKeywords, true)
        ) {
            $side = new Keyword(strtolower($sideTok->value));
        } elseif (!$isSize && $sideTok instanceof PercentageToken) {
            $side = new Percentage((float) $sideTok->value);
        }
        if ($side === null) {
            return null;
        }

        // Optional fallback expression.
        $fallback = null;
        if (count($commaGroups) === 2) {
            $fallbackCss = self::serializeTokens(self::trimWhitespace($commaGroups[1]));
            $fallback = $this->parseFromString($fallbackCss);
        }

        return $isSize
            ? new AnchorSizeFunction($anchorName, $side, $fallback)
            : new AnchorFunction($anchorName, $side, $fallback);
    }

    // ============================================================
    // basic-shape — CSS Shapes 1 §3
    // ============================================================
    /**
     * Dispatch for the four most commonly-used basic shapes.
     * `path()`, `rect()`, `xywh()` (CSS Shapes 2) still fall
     * through to generic CssFunction.
     *
     * @param list<Token> $tokens
     */
    private function parseBasicShape(string $name, array $tokens): ?BasicShape
    {
        return match ($name) {
            'circle' => $this->parseCircleShape($tokens),
            'ellipse' => $this->parseEllipseShape($tokens),
            'inset' => $this->parseInsetShape($tokens),
            'polygon' => $this->parsePolygonShape($tokens),
            'rect' => $this->parseRectShape($tokens),
            'xywh' => $this->parseXywhShape($tokens),
            'path' => $this->parsePathShape($tokens),
            default => null,
        };
    }

    /** @param list<Token> $tokens */
    private function parseRectShape(array $tokens): ?RectShape
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        [$edgeTokens, $roundTokens] = self::splitOnRoundKeyword($tokens);
        $edgeGroups = self::splitParenAwareSpaceForm(self::trimWhitespace($edgeTokens));
        if (count($edgeGroups) !== 4) {
            return null;
        }
        $edges = [];
        foreach ($edgeGroups as $group) {
            $value = $this->parseFromString(self::serializeTokens(self::trimWhitespace($group)));
            if (!self::isShapePositionValue($value)
                && !($value instanceof Keyword && strtolower($value->name) === 'auto')
            ) {
                return null;
            }
            $edges[] = $value;
        }
        $radius = null;
        if ($roundTokens !== []) {
            $roundGroups = self::splitParenAwareSpaceForm(self::trimWhitespace($roundTokens));
            $radius = [];
            foreach ($roundGroups as $group) {
                $radius[] = $this->parseFromString(self::serializeTokens(self::trimWhitespace($group)));
            }
            if ($radius === []) {
                $radius = null;
            }
        }
        return new RectShape($edges, $radius);
    }

    /** @param list<Token> $tokens */
    private function parseXywhShape(array $tokens): ?XywhShape
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        [$mainTokens, $roundTokens] = self::splitOnRoundKeyword($tokens);
        $groups = self::splitParenAwareSpaceForm(self::trimWhitespace($mainTokens));
        if (count($groups) !== 4) {
            return null;
        }
        $values = [];
        foreach ($groups as $group) {
            $value = $this->parseFromString(self::serializeTokens(self::trimWhitespace($group)));
            if (!self::isShapePositionValue($value)) {
                return null;
            }
            $values[] = $value;
        }
        $radius = null;
        if ($roundTokens !== []) {
            $roundGroups = self::splitParenAwareSpaceForm(self::trimWhitespace($roundTokens));
            $radius = [];
            foreach ($roundGroups as $group) {
                $radius[] = $this->parseFromString(self::serializeTokens(self::trimWhitespace($group)));
            }
            if ($radius === []) {
                $radius = null;
            }
        }
        return new XywhShape($values[0], $values[1], $values[2], $values[3], $radius);
    }

    /** @param list<Token> $tokens */
    private function parsePathShape(array $tokens): ?PathShape
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        $commaGroups = self::splitTopLevel($tokens, CommaToken::class);
        $fillRule = 'nonzero';
        // Optional leading fill-rule ident.
        if (count($commaGroups) === 2) {
            $first = self::trimWhitespace($commaGroups[0]);
            if (count($first) !== 1 || !($first[0] instanceof IdentToken)) {
                return null;
            }
            $rule = strtolower($first[0]->value);
            if ($rule !== 'nonzero' && $rule !== 'evenodd') {
                return null;
            }
            $fillRule = $rule;
            $pathGroup = $commaGroups[1];
        } else {
            $pathGroup = $commaGroups[0];
        }
        // Path data string.
        $pathTokens = self::trimWhitespace($pathGroup);
        if (count($pathTokens) !== 1 || !($pathTokens[0] instanceof StringToken)) {
            return null;
        }
        return new PathShape($fillRule, $pathTokens[0]->value);
    }

    /** @param list<Token> $tokens */
    private function parseCircleShape(array $tokens): ?CircleShape
    {
        $tokens = self::trimWhitespace($tokens);
        // Possible forms:
        //   circle()
        //   circle(<radius>)
        //   circle(at <position>)
        //   circle(<radius> at <position>)
        if ($tokens === []) {
            return new CircleShape();
        }
        // Look for the `at` keyword to split radius from position.
        [$radiusTokens, $positionTokens] = self::splitOnAtKeyword($tokens);
        $radius = $radiusTokens === [] ? null : $this->parseShapeRadius($radiusTokens);
        if ($radiusTokens !== [] && $radius === null) {
            return null;
        }
        [$cx, $cy] = $this->parsePositionXY($positionTokens);
        return new CircleShape($radius, $cx, $cy);
    }

    /** @param list<Token> $tokens */
    private function parseEllipseShape(array $tokens): ?EllipseShape
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return new EllipseShape();
        }
        [$radiusTokens, $positionTokens] = self::splitOnAtKeyword($tokens);
        $rx = null;
        $ry = null;
        if ($radiusTokens !== []) {
            // Expect two space-separated radius tokens.
            $rGroups = self::splitParenAwareSpaceForm($radiusTokens);
            if (count($rGroups) !== 2) {
                return null;
            }
            $rx = $this->parseShapeRadius($rGroups[0]);
            $ry = $this->parseShapeRadius($rGroups[1]);
            if ($rx === null || $ry === null) {
                return null;
            }
        }
        [$cx, $cy] = $this->parsePositionXY($positionTokens);
        return new EllipseShape($rx, $ry, $cx, $cy);
    }

    /** @param list<Token> $tokens */
    private function parseInsetShape(array $tokens): ?InsetShape
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        // Split on `round` keyword if present.
        [$insetTokens, $roundTokens] = self::splitOnRoundKeyword($tokens);
        $insetGroups = self::splitParenAwareSpaceForm(self::trimWhitespace($insetTokens));
        if (count($insetGroups) < 1 || count($insetGroups) > 4) {
            return null;
        }
        $insets = [];
        foreach ($insetGroups as $group) {
            $value = $this->parseFromString(self::serializeTokens(self::trimWhitespace($group)));
            if (!self::isShapePositionValue($value)) {
                return null;
            }
            $insets[] = $value;
        }
        $radius = null;
        if ($roundTokens !== []) {
            $roundGroups = self::splitParenAwareSpaceForm(self::trimWhitespace($roundTokens));
            $radius = [];
            foreach ($roundGroups as $group) {
                $radius[] = $this->parseFromString(self::serializeTokens(self::trimWhitespace($group)));
            }
            if ($radius === []) {
                $radius = null;
            }
        }
        return new InsetShape($insets, $radius);
    }

    /** @param list<Token> $tokens */
    private function parsePolygonShape(array $tokens): ?PolygonShape
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        $commaGroups = self::splitTopLevel($tokens, CommaToken::class);
        if ($commaGroups === []) {
            return null;
        }
        $fillRule = 'nonzero';
        // Detect leading fill-rule ident.
        $firstGroup = self::trimWhitespace($commaGroups[0]);
        if ($firstGroup !== [] && $firstGroup[0] instanceof IdentToken) {
            $rule = strtolower($firstGroup[0]->value);
            if ($rule === 'nonzero' || $rule === 'evenodd') {
                $fillRule = $rule;
                // Remove the fill-rule ident from the first group.
                $remainder = self::trimWhitespace(array_slice($firstGroup, 1));
                if ($remainder !== []) {
                    $commaGroups[0] = $remainder;
                } else {
                    array_shift($commaGroups);
                }
            }
        }
        $vertices = [];
        foreach ($commaGroups as $group) {
            $group = self::trimWhitespace($group);
            $parts = self::splitParenAwareSpaceForm($group);
            if (count($parts) !== 2) {
                return null;
            }
            $x = $this->parseFromString(self::serializeTokens(self::trimWhitespace($parts[0])));
            $y = $this->parseFromString(self::serializeTokens(self::trimWhitespace($parts[1])));
            // Polygon vertices accept length / percentage / calc / number
            // (bare `0` parses as Integer; treat zeros as 0px).
            if (!self::isShapePositionValue($x) || !self::isShapePositionValue($y)) {
                return null;
            }
            $vertices[] = [$x, $y];
        }
        if ($vertices === []) {
            return null;
        }
        return new PolygonShape($fillRule, $vertices);
    }

    /**
     * Accept the set of value types that may appear in a basic-
     * shape vertex / inset slot: Length, Percentage, Calc, plus
     * the literal `0` zero shorthand (Integer / Number).
     */
    private static function isShapePositionValue(?Value $value): bool
    {
        return $value instanceof Length
            || $value instanceof Percentage
            || $value instanceof Calc
            || $value instanceof Integer
            || $value instanceof Number;
    }

    /**
     * Parse a `<shape-radius>` — Length / Percentage / Keyword
     * (`closest-side` or `farthest-side`).
     *
     * @param list<Token> $tokens
     */
    private function parseShapeRadius(array $tokens): ?Value
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        if (count($tokens) === 1 && $tokens[0] instanceof IdentToken) {
            $kw = strtolower($tokens[0]->value);
            if ($kw === 'closest-side' || $kw === 'farthest-side') {
                return new Keyword($kw);
            }
        }
        $value = $this->parseFromString(self::serializeTokens($tokens));
        if ($value instanceof Length || $value instanceof Percentage || $value instanceof Calc) {
            return $value;
        }
        return null;
    }

    /**
     * Parse a `<position>` token sequence into `[centerX, centerY]`
     * Value pairs. Accepts:
     *   - empty list → [null, null]
     *   - single keyword → applies to both axes via shape semantics
     *   - two values (keywords / percentages / lengths) → x then y
     *
     * @param list<Token> $tokens
     * @return array{?Value, ?Value}
     */
    private function parsePositionXY(array $tokens): array
    {
        if ($tokens === []) {
            return [null, null];
        }
        $groups = self::splitParenAwareSpaceForm(self::trimWhitespace($tokens));
        $values = [];
        foreach ($groups as $group) {
            $group = self::trimWhitespace($group);
            if ($group === []) {
                continue;
            }
            // Position keywords (left / center / right / top / bottom).
            if (count($group) === 1 && $group[0] instanceof IdentToken) {
                $kw = strtolower($group[0]->value);
                if (in_array($kw, ['left', 'center', 'right', 'top', 'bottom'], true)) {
                    $values[] = new Keyword($kw);
                    continue;
                }
            }
            $value = $this->parseFromString(self::serializeTokens($group));
            $values[] = $value;
        }
        if (count($values) === 1) {
            return [$values[0], $values[0]];
        }
        if (count($values) === 2) {
            return [$values[0], $values[1]];
        }
        return [null, null];
    }

    /**
     * Split a token sequence at the first top-level `at` ident.
     *
     * @param list<Token> $tokens
     * @return array{list<Token>, list<Token>}
     */
    private static function splitOnAtKeyword(array $tokens): array
    {
        $depth = 0;
        foreach ($tokens as $i => $t) {
            if ($t instanceof LeftParenToken || $t instanceof FunctionToken) {
                $depth++;
                continue;
            }
            if ($t instanceof RightParenToken) {
                $depth--;
                continue;
            }
            if ($depth === 0 && $t instanceof IdentToken && strtolower($t->value) === 'at') {
                return [
                    self::trimWhitespace(array_slice($tokens, 0, $i)),
                    self::trimWhitespace(array_slice($tokens, $i + 1)),
                ];
            }
        }
        return [self::trimWhitespace($tokens), []];
    }

    /**
     * Split a token sequence at the first top-level `round` ident
     * (CSS Shapes 1 `inset()` round-corner separator).
     *
     * @param list<Token> $tokens
     * @return array{list<Token>, list<Token>}
     */
    private static function splitOnRoundKeyword(array $tokens): array
    {
        $depth = 0;
        foreach ($tokens as $i => $t) {
            if ($t instanceof LeftParenToken || $t instanceof FunctionToken) {
                $depth++;
                continue;
            }
            if ($t instanceof RightParenToken) {
                $depth--;
                continue;
            }
            if ($depth === 0 && $t instanceof IdentToken && strtolower($t->value) === 'round') {
                return [
                    self::trimWhitespace(array_slice($tokens, 0, $i)),
                    self::trimWhitespace(array_slice($tokens, $i + 1)),
                ];
            }
        }
        return [self::trimWhitespace($tokens), []];
    }

    // ============================================================
    // cubic-bezier() + steps() — CSS Easing 1 §3.4 / §3.5
    // ============================================================
    /** @param list<Token> $tokens */
    private function parseCubicBezierFunction(array $tokens): ?CubicBezier
    {
        $tokens = self::trimWhitespace($tokens);
        $commaGroups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($commaGroups) !== 4) {
            return null;
        }
        $values = [];
        foreach ($commaGroups as $group) {
            $g = self::trimWhitespace($group);
            if (count($g) !== 1 || !($g[0] instanceof NumberToken)) {
                return null;
            }
            $values[] = (float) $g[0]->value;
        }
        // CSS Easing 1 §3.4 — x coordinates must be in [0, 1].
        if ($values[0] < 0.0 || $values[0] > 1.0 || $values[2] < 0.0 || $values[2] > 1.0) {
            return null;
        }
        return new CubicBezier($values[0], $values[1], $values[2], $values[3]);
    }

    /** @param list<Token> $tokens */
    private function parseStepsFunction(array $tokens): ?StepsEasing
    {
        $tokens = self::trimWhitespace($tokens);
        $commaGroups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($commaGroups) < 1 || count($commaGroups) > 2) {
            return null;
        }
        // Count.
        $countTokens = self::trimWhitespace($commaGroups[0]);
        if (count($countTokens) !== 1 || !($countTokens[0] instanceof NumberToken)) {
            return null;
        }
        $countValue = $countTokens[0]->value;
        if ($countValue !== floor($countValue) || $countValue < 1) {
            return null;
        }
        $count = (int) $countValue;
        // Optional jump term.
        $jump = StepsJumpTerm::End;
        if (count($commaGroups) === 2) {
            $jumpTokens = self::trimWhitespace($commaGroups[1]);
            if (count($jumpTokens) !== 1 || !($jumpTokens[0] instanceof IdentToken)) {
                return null;
            }
            $parsed = StepsJumpTerm::tryFrom(strtolower($jumpTokens[0]->value));
            if ($parsed === null) {
                return null;
            }
            $jump = $parsed;
        }
        return new StepsEasing($count, $jump);
    }

    // ============================================================
    // linear() — CSS Easing 2 §3.1
    // ============================================================
    /**
     * Parse `linear(<linear-stop-list>)` where each stop is
     * `<number> [<percentage>]?` (CSS Easing 2 §3.1) or the range
     * form `<number> <percentage> <percentage>` (§3.2). Returns
     * null when the stop list is empty or malformed.
     *
     * @param list<Token> $tokens
     */
    private function parseLinearEasingFunction(array $tokens): ?LinearEasing
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        $groups = self::splitTopLevel($tokens, CommaToken::class);
        $stops = [];
        foreach ($groups as $group) {
            $group = self::trimWhitespace($group);
            $parts = self::splitParenAwareSpaceForm($group);
            if (count($parts) === 0 || count($parts) > 3) {
                return null;
            }
            // First part is the output number.
            $outputTokens = self::trimWhitespace($parts[0]);
            if (count($outputTokens) !== 1 || !($outputTokens[0] instanceof NumberToken)) {
                return null;
            }
            $output = (float) $outputTokens[0]->value;

            // Range form: 3 parts (output, percentageFrom, percentageTo)
            // emits two stops sharing the output.
            if (count($parts) === 3) {
                $fromPct = self::extractSinglePercentage(self::trimWhitespace($parts[1]));
                $toPct = self::extractSinglePercentage(self::trimWhitespace($parts[2]));
                if ($fromPct === null || $toPct === null) {
                    return null;
                }
                $stops[] = new LinearEasingStop($output, $fromPct);
                $stops[] = new LinearEasingStop($output, $toPct);
                continue;
            }

            // Regular form: 1 or 2 parts (output, optional percentage).
            $inputPct = null;
            if (count($parts) === 2) {
                $pct = self::extractSinglePercentage(self::trimWhitespace($parts[1]));
                if ($pct === null) {
                    return null;
                }
                $inputPct = $pct;
            }
            $stops[] = new LinearEasingStop($output, $inputPct);
        }
        if ($stops === []) {
            return null;
        }
        return new LinearEasing($stops);
    }

    /**
     * Extract a single percentage value from a token sequence
     * already trimmed of surrounding whitespace.
     *
     * @param list<Token> $tokens
     */
    private static function extractSinglePercentage(array $tokens): ?float
    {
        if (count($tokens) !== 1) {
            return null;
        }
        $t = $tokens[0];
        if (!($t instanceof PercentageToken)) {
            return null;
        }
        return (float) $t->value;
    }

    // ============================================================
    // attr() — CSS Values 5 §11
    // ============================================================
    /** @param list<Token> $tokens */
    private function parseAttrFunction(array $tokens): ?AttrFunction
    {
        $tokens = self::trimWhitespace($tokens);
        $commaGroups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($commaGroups) < 1 || count($commaGroups) > 2) {
            return null;
        }
        $head = self::trimWhitespace($commaGroups[0]);
        if ($head === []) {
            return null;
        }
        if (!($head[0] instanceof IdentToken)) {
            return null;
        }
        $attributeName = $head[0]->value;
        $typeOrUnit = null;
        if (count($head) >= 2) {
            $rest = self::trimWhitespace(array_slice($head, 1));
            if ($rest !== []) {
                // Type/unit hint — accept any single ident or
                // dimension unit; just serialise verbatim so the
                // cascade preserves the author's intent.
                $typeOrUnit = self::serializeTokens($rest);
            }
        }
        $fallback = null;
        if (count($commaGroups) === 2) {
            $fbCss = self::serializeTokens(self::trimWhitespace($commaGroups[1]));
            $fallback = $this->parseFromString($fbCss);
        }
        return new AttrFunction($attributeName, $typeOrUnit, $fallback);
    }

    // ============================================================
    // env() — CSS Environment Variables 1 §3
    // ============================================================
    /** @param list<Token> $tokens */
    private function parseEnvFunction(array $tokens): ?EnvFunction
    {
        $tokens = self::trimWhitespace($tokens);
        $commaGroups = self::splitTopLevel($tokens, CommaToken::class);
        if (count($commaGroups) < 1 || count($commaGroups) > 2) {
            return null;
        }
        $head = self::trimWhitespace($commaGroups[0]);
        if ($head === []) {
            return null;
        }
        if (!($head[0] instanceof IdentToken)) {
            return null;
        }
        $envName = $head[0]->value;
        $indices = [];
        $rest = array_slice($head, 1);
        foreach ($rest as $t) {
            if ($t instanceof WhitespaceToken) {
                continue;
            }
            if (!($t instanceof NumberToken)) {
                return null;
            }
            // Indices must be integers per CSS Env Vars 1 §3.
            if ($t->value !== floor($t->value)) {
                return null;
            }
            $indices[] = (int) $t->value;
        }
        $fallback = null;
        if (count($commaGroups) === 2) {
            $fbCss = self::serializeTokens(self::trimWhitespace($commaGroups[1]));
            $fallback = $this->parseFromString($fbCss);
        }
        return new EnvFunction($envName, $indices, $fallback);
    }

    // ============================================================
    // target-counter / target-counters / target-text — GCPM 3 §3
    // ============================================================
    /**
     * Parse cross-reference functions used in paged-media TOCs:
     *
     *   target-counter(<url-or-attr>, <counter>, <style>?)
     *   target-counters(<url-or-attr>, <counter>, <string>, <style>?)
     *   target-text(<url-or-attr>, [content | before | after | first-letter]?)
     *
     * Returns null on malformed input (caller falls back to the
     * generic function-token preservation path).
     *
     * @param list<Token> $tokens
     */
    private function parseTargetFunction(string $name, array $tokens): ?TargetFunction
    {
        $kind = TargetFunctionKind::tryFrom(strtolower($name));
        if ($kind === null) {
            return null;
        }
        $groups = self::splitTopLevel(self::trimWhitespace($tokens), CommaToken::class);
        if ($groups === []) {
            return null;
        }
        $target = $this->parseFromString(self::serializeTokens(self::trimWhitespace($groups[0])));
        return match ($kind) {
            TargetFunctionKind::Counter   => $this->buildTargetCounter($kind, $target, $groups),
            TargetFunctionKind::Counters  => $this->buildTargetCounters($kind, $target, $groups),
            TargetFunctionKind::Text      => $this->buildTargetText($kind, $target, $groups),
        };
    }

    /**
     * @param list<list<Token>> $groups
     */
    private function buildTargetCounter(TargetFunctionKind $kind, Value $target, array $groups): ?TargetFunction
    {
        if (count($groups) < 2 || count($groups) > 3) {
            return null;
        }
        $name = $this->parseCounterIdent($groups[1]);
        if ($name === null) {
            return null;
        }
        $style = null;
        if (count($groups) === 3) {
            $style = $this->parseCounterIdent($groups[2]);
            if ($style === null) {
                return null;
            }
        }
        return new TargetFunction($kind, $target, $name, null, $style);
    }

    /**
     * @param list<list<Token>> $groups
     */
    private function buildTargetCounters(TargetFunctionKind $kind, Value $target, array $groups): ?TargetFunction
    {
        if (count($groups) < 3 || count($groups) > 4) {
            return null;
        }
        $name = $this->parseCounterIdent($groups[1]);
        if ($name === null) {
            return null;
        }
        $sepTokens = self::trimWhitespace($groups[2]);
        if (count($sepTokens) !== 1 || !($sepTokens[0] instanceof StringToken)) {
            return null;
        }
        $separator = new StringValue($sepTokens[0]->value);
        $style = null;
        if (count($groups) === 4) {
            $style = $this->parseCounterIdent($groups[3]);
            if ($style === null) {
                return null;
            }
        }
        return new TargetFunction($kind, $target, $name, $separator, $style);
    }

    /**
     * @param list<list<Token>> $groups
     */
    private function buildTargetText(TargetFunctionKind $kind, Value $target, array $groups): ?TargetFunction
    {
        if (count($groups) < 1 || count($groups) > 2) {
            return null;
        }
        $source = null;
        if (count($groups) === 2) {
            $srcTokens = self::trimWhitespace($groups[1]);
            if (count($srcTokens) !== 1 || !($srcTokens[0] instanceof IdentToken)) {
                return null;
            }
            $kw = strtolower($srcTokens[0]->value);
            if (!in_array($kw, ['content', 'before', 'after', 'first-letter'], true)) {
                return null;
            }
            $source = new Keyword($kw);
        }
        return new TargetFunction($kind, $target, null, $source, null);
    }

    /**
     * @param list<Token> $group
     */
    private function parseCounterIdent(array $group): ?Keyword
    {
        $trim = self::trimWhitespace($group);
        if (count($trim) !== 1 || !($trim[0] instanceof IdentToken)) {
            return null;
        }
        return new Keyword($trim[0]->value);
    }

    // ============================================================
    // cross-fade — CSS Images 4 §4
    // ============================================================
    /**
     * Parse `cross-fade(<entry> [, <entry>]*)` where each entry
     * is `[<percentage>]? <image>`. Percentages are 0..100; the
     * unlabeled entries share whatever weight remains after the
     * labeled entries.
     *
     * @param list<Token> $tokens
     */
    private function parseCrossFade(array $tokens): ?CrossFade
    {
        $groups = self::splitTopLevel(self::trimWhitespace($tokens), CommaToken::class);
        if ($groups === []) {
            return null;
        }
        $options = [];
        foreach ($groups as $group) {
            $opt = $this->parseCrossFadeEntry(self::trimWhitespace($group));
            if ($opt === null) {
                return null;
            }
            $options[] = $opt;
        }
        if (count($options) < 1) {
            return null;
        }
        return new CrossFade($options);
    }

    /**
     * @param list<Token> $tokens
     */
    private function parseCrossFadeEntry(array $tokens): ?CrossFadeOption
    {
        if ($tokens === []) {
            return null;
        }
        $percent = null;
        $i = 0;
        // Optional leading percentage.
        if ($tokens[0] instanceof PercentageToken) {
            $percent = (float) $tokens[0]->value;
            if ($percent < 0.0 || $percent > 100.0) {
                return null;
            }
            $i = 1;
            // Eat trailing whitespace before the image.
            while ($i < count($tokens) && $tokens[$i] instanceof WhitespaceToken) {
                $i++;
            }
        }
        $rest = array_slice($tokens, $i);
        if ($rest === []) {
            return null;
        }
        $image = $this->parseFromString(self::serializeTokens($rest));
        return new CrossFadeOption($image, $percent);
    }

    // ============================================================
    // light-dark — CSS Color 5 §5
    // ============================================================
    /**
     * Parse `light-dark(<color>, <color>)`. The renderer selects
     * the active branch at paint time based on the resolved
     * `color-scheme` for the document / element.
     *
     * @param list<Token> $tokens
     */
    private function parseLightDark(array $tokens): ?LightDark
    {
        $groups = self::splitTopLevel(self::trimWhitespace($tokens), CommaToken::class);
        if (count($groups) !== 2) {
            return null;
        }
        $light = $this->parseFromString(self::serializeTokens(self::trimWhitespace($groups[0])));
        $dark = $this->parseFromString(self::serializeTokens(self::trimWhitespace($groups[1])));
        return new LightDark($light, $dark);
    }

    // ============================================================
    // image-set — CSS Images 4 §6
    // ============================================================
    /**
     * Parse `image-set(<option> [, <option>]*)` where each option
     * is `<image> [<resolution>]? [type(<mime>)]?`. Resolutions
     * accept the `x` / `dppx` / `dpcm` / `dpi` units; type() takes
     * a string-literal MIME.
     *
     * @param list<Token> $tokens
     */
    private function parseImageSet(array $tokens): ?ImageSet
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        $groups = self::splitTopLevel($tokens, CommaToken::class);
        $options = [];
        foreach ($groups as $group) {
            $opt = $this->parseImageSetOption(self::trimWhitespace($group));
            if ($opt === null) {
                return null;
            }
            $options[] = $opt;
        }
        return new ImageSet($options);
    }

    /**
     * @param list<Token> $tokens
     */
    private function parseImageSetOption(array $tokens): ?ImageSetOption
    {
        if ($tokens === []) {
            return null;
        }
        // First non-WS token may be either a URL function, a
        // string literal, or a nested image-* function (gradient
        // etc). For simplicity, treat the head as the "image"
        // until we hit a resolution token or a `type(` function.
        $image = null;
        $resolution = null;
        $mime = null;

        $i = 0;
        $count = count($tokens);
        while ($i < $count) {
            $t = $tokens[$i];
            if ($t instanceof WhitespaceToken) {
                $i++;
                continue;
            }
            if ($t instanceof DimensionToken && self::isResolutionUnit($t->unit)) {
                $dppx = self::resolutionToDppx((float) $t->value, $t->unit);
                if ($dppx === null) {
                    return null;
                }
                $resolution = $dppx;
                $i++;
                continue;
            }
            if ($t instanceof NumberToken && $image !== null) {
                // Bare numbers are NOT valid resolution per spec;
                // fail to parse.
                return null;
            }
            if ($t instanceof FunctionToken && strtolower($t->name) === 'type') {
                // Collect the matching close paren.
                $depth = 1;
                $argStart = $i + 1;
                $j = $argStart;
                while ($j < $count) {
                    if ($tokens[$j] instanceof LeftParenToken || $tokens[$j] instanceof FunctionToken) {
                        $depth++;
                    } elseif ($tokens[$j] instanceof RightParenToken) {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    }
                    $j++;
                }
                $inner = self::trimWhitespace(array_slice($tokens, $argStart, $j - $argStart));
                if (count($inner) !== 1 || !($inner[0] instanceof StringToken)) {
                    return null;
                }
                $mime = $inner[0]->value;
                $i = $j + 1;
                continue;
            }
            if ($image === null) {
                // Fast paths for the common cases — saves a
                // re-tokenise + re-parse round trip:
                //   - UrlToken    → Url value directly
                //   - StringToken → StringValue directly
                if ($t instanceof UrlToken) {
                    $image = new Url($t->value);
                    $i++;
                    continue;
                }
                if ($t instanceof StringToken) {
                    $image = new \Phpdftk\Css\Value\StringValue($t->value);
                    $i++;
                    continue;
                }
                // Fallback: treat the token (and any function /
                // paren-balanced run starting here) as the image
                // and let the dispatch parse it. Used for nested
                // image-set / linear-gradient / etc.
                $consumed = self::collectFunctionLikeRun($tokens, $i);
                $imageTokens = array_slice($tokens, $i, $consumed);
                $css = self::serializeTokens($imageTokens);
                $image = $this->parseFromString($css);
                $i += $consumed;
                continue;
            }
            return null;
        }
        if ($image === null) {
            return null;
        }
        return new ImageSetOption($image, $resolution, $mime);
    }

    /**
     * Collect a function call (and any nested function calls in
     * its arguments) starting at $start, OR a single non-function
     * token. Returns the count of tokens consumed.
     *
     * @param list<Token> $tokens
     */
    private static function collectFunctionLikeRun(array $tokens, int $start): int
    {
        $head = $tokens[$start] ?? null;
        if (!($head instanceof FunctionToken)) {
            return 1;
        }
        $depth = 1;
        $i = $start + 1;
        $count = count($tokens);
        while ($i < $count && $depth > 0) {
            if ($tokens[$i] instanceof LeftParenToken || $tokens[$i] instanceof FunctionToken) {
                $depth++;
            } elseif ($tokens[$i] instanceof RightParenToken) {
                $depth--;
            }
            $i++;
        }
        return $i - $start;
    }

    private static function isResolutionUnit(string $unit): bool
    {
        return in_array(strtolower($unit), ['x', 'dppx', 'dpcm', 'dpi'], true);
    }

    private static function resolutionToDppx(float $value, string $unit): ?float
    {
        return match (strtolower($unit)) {
            'x', 'dppx' => $value,
            'dpi' => $value / 96.0,                        // 96 dpi = 1 dppx
            'dpcm' => $value * 2.54 / 96.0,                // 1 dpcm = 2.54 dpi → /96
            default => null,
        };
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
