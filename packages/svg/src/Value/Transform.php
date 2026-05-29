<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value;

use Phpdftk\Svg\Value\Transform\Matrix;
use Phpdftk\Svg\Value\Transform\Rotate;
use Phpdftk\Svg\Value\Transform\Scale;
use Phpdftk\Svg\Value\Transform\SkewX;
use Phpdftk\Svg\Value\Transform\SkewY;
use Phpdftk\Svg\Value\Transform\Translate;

/**
 * The parsed value of an SVG 2 `transform` attribute (SVG 2 §8.4) — an ordered
 * list of `TransformFunction` operations. Compose them via `toMatrix()` to get
 * the flat 3×2 affine matrix the painter feeds to the PDF `cm` operator.
 *
 * Composition is left-to-right per SVG 2: a list `[A, B, C]` produces matrix
 * `A · B · C`, so `C` is applied to a point first, then `B`, then `A`. From
 * the coordinate-system's perspective, the system is transformed in
 * left-to-right order.
 */
final class Transform
{
    /** @param list<TransformFunction> $functions */
    public function __construct(public readonly array $functions) {}

    /**
     * Parse an SVG 2 transform-attribute string. Throws on malformed input —
     * callers (typically `Element::transform()`) that want SVG's
     * "invalid → ignored" semantics should catch and substitute null.
     *
     * @throws \InvalidArgumentException when the grammar isn't satisfied
     */
    public static function parse(string $raw): self
    {
        $functions = [];
        $offset = 0;
        $len = strlen($raw);
        while ($offset < $len) {
            while ($offset < $len && (ctype_space($raw[$offset]) || $raw[$offset] === ',')) {
                $offset++;
            }
            if ($offset >= $len) {
                break;
            }
            if (preg_match('/\G([a-zA-Z]+)\s*\(/', $raw, $m, 0, $offset) !== 1) {
                throw new \InvalidArgumentException(
                    sprintf('Malformed transform at offset %d: expected function name.', $offset),
                );
            }
            $name = $m[1];
            $offset += strlen($m[0]);
            $close = strpos($raw, ')', $offset);
            if ($close === false) {
                throw new \InvalidArgumentException(
                    sprintf('Malformed transform: unterminated `%s(`.', $name),
                );
            }
            $args = self::parseNumberList(substr($raw, $offset, $close - $offset));
            $offset = $close + 1;
            $functions[] = self::makeFunction($name, $args);
        }
        return new self($functions);
    }

    /**
     * Compose the function list and return the resulting affine matrix.
     * Identity for an empty list.
     *
     * @return array{float, float, float, float, float, float}
     */
    public function toMatrix(): array
    {
        $m = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        foreach ($this->functions as $fn) {
            $m = self::multiply($m, $fn->toMatrix());
        }
        return $m;
    }

    /**
     * @param list<float> $args
     */
    private static function makeFunction(string $name, array $args): TransformFunction
    {
        return match ($name) {
            'matrix' => count($args) === 6
                ? new Matrix($args[0], $args[1], $args[2], $args[3], $args[4], $args[5])
                : throw new \InvalidArgumentException(
                    sprintf('matrix() requires 6 arguments, got %d', count($args)),
                ),
            'translate' => match (count($args)) {
                1 => new Translate($args[0]),
                2 => new Translate($args[0], $args[1]),
                default => throw new \InvalidArgumentException(
                    sprintf('translate() requires 1 or 2 arguments, got %d', count($args)),
                ),
            },
            'scale' => match (count($args)) {
                1 => new Scale($args[0]),
                2 => new Scale($args[0], $args[1]),
                default => throw new \InvalidArgumentException(
                    sprintf('scale() requires 1 or 2 arguments, got %d', count($args)),
                ),
            },
            'rotate' => match (count($args)) {
                1 => new Rotate($args[0]),
                3 => new Rotate($args[0], $args[1], $args[2]),
                default => throw new \InvalidArgumentException(
                    sprintf('rotate() requires 1 or 3 arguments, got %d', count($args)),
                ),
            },
            'skewX' => count($args) === 1
                ? new SkewX($args[0])
                : throw new \InvalidArgumentException(
                    sprintf('skewX() requires 1 argument, got %d', count($args)),
                ),
            'skewY' => count($args) === 1
                ? new SkewY($args[0])
                : throw new \InvalidArgumentException(
                    sprintf('skewY() requires 1 argument, got %d', count($args)),
                ),
            default => throw new \InvalidArgumentException(
                sprintf('Unknown transform function `%s`.', $name),
            ),
        };
    }

    /**
     * @return list<float>
     */
    private static function parseNumberList(string $raw): array
    {
        if (preg_match_all('/[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $raw, $m) === false) {
            return [];
        }
        return array_values(array_map(static fn(string $v): float => (float) $v, $m[0]));
    }

    /**
     * Multiply two 3×2 affine matrices in SVG `[a,b,c,d,e,f]` order.
     *
     * @param array{float, float, float, float, float, float} $m1
     * @param array{float, float, float, float, float, float} $m2
     * @return array{float, float, float, float, float, float}
     */
    private static function multiply(array $m1, array $m2): array
    {
        [$a1, $b1, $c1, $d1, $e1, $f1] = $m1;
        [$a2, $b2, $c2, $d2, $e2, $f2] = $m2;
        return [
            $a1 * $a2 + $c1 * $b2,
            $b1 * $a2 + $d1 * $b2,
            $a1 * $c2 + $c1 * $d2,
            $b1 * $c2 + $d1 * $d2,
            $a1 * $e2 + $c1 * $f2 + $e1,
            $b1 * $e2 + $d1 * $f2 + $f1,
        ];
    }
}
