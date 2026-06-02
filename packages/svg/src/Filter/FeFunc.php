<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

use Phpdftk\Svg\Element;

/**
 * SVG 2 Filter Effects §15.14 — common base for `feFuncR`,
 * `feFuncG`, `feFuncB`, `feFuncA`. Each defines a transfer
 * function for one colour channel.
 *
 * `type` chooses the function shape:
 *
 *   identity (default) — `out = in`
 *   table              — piecewise-linear lookup table
 *   discrete           — step function
 *   linear             — `out = slope·in + intercept`
 *   gamma              — `out = amplitude · in^exponent + offset`
 */
abstract class FeFunc extends Element
{
    public function type(): string
    {
        $v = strtolower($this->getAttribute('type') ?? 'identity');
        return match ($v) {
            'identity', 'table', 'discrete', 'linear', 'gamma' => $v,
            default => 'identity',
        };
    }

    /**
     * @return list<float>
     */
    public function tableValues(): array
    {
        $raw = trim($this->getAttribute('tableValues') ?? '');
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            $out[] = (float) $p;
        }
        return $out;
    }

    public function slope(): float
    {
        return (float) ($this->getAttribute('slope') ?? 1);
    }

    public function intercept(): float
    {
        return (float) ($this->getAttribute('intercept') ?? 0);
    }

    public function amplitude(): float
    {
        return (float) ($this->getAttribute('amplitude') ?? 1);
    }

    public function exponent(): float
    {
        return (float) ($this->getAttribute('exponent') ?? 1);
    }

    public function offset(): float
    {
        return (float) ($this->getAttribute('offset') ?? 0);
    }
}
