<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.13 — `<feConvolveMatrix>`. Applies an
 * arbitrary 2D convolution kernel to the input. Edge detection,
 * sharpening, custom blurs, embossing, etc.
 *
 *   order:           kernel dimensions (one or two integers)
 *   kernelMatrix:    flat list of order×order values
 *   divisor:         post-convolution divisor (default = sum of
 *                    kernel values)
 *   bias:            additive bias (default 0)
 *   targetX/targetY: kernel anchor point (defaults to centre)
 *   edgeMode:        duplicate | wrap | none (default duplicate)
 *   preserveAlpha:   true | false (default false)
 */
final class FeConvolveMatrix extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feConvolveMatrix');
    }

    /**
     * @return array{0: int, 1: int}  [orderX, orderY]
     */
    public function order(): array
    {
        $raw = trim($this->getAttribute('order') ?? '');
        if ($raw === '') {
            return [3, 3];
        }
        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $ox = max(1, (int) ($parts[0] ?? 3));
        $oy = isset($parts[1]) ? max(1, (int) $parts[1]) : $ox;
        return [$ox, $oy];
    }

    /**
     * @return list<float>
     */
    public function kernelMatrix(): array
    {
        $raw = trim($this->getAttribute('kernelMatrix') ?? '');
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

    public function divisor(): ?float
    {
        $v = $this->getAttribute('divisor');
        return $v !== null ? (float) $v : null;
    }

    public function bias(): float
    {
        return (float) ($this->getAttribute('bias') ?? 0);
    }

    public function targetX(): ?int
    {
        $v = $this->getAttribute('targetX');
        return $v !== null ? (int) $v : null;
    }

    public function targetY(): ?int
    {
        $v = $this->getAttribute('targetY');
        return $v !== null ? (int) $v : null;
    }

    public function edgeMode(): string
    {
        $v = strtolower($this->getAttribute('edgeMode') ?? 'duplicate');
        return in_array($v, ['duplicate', 'wrap', 'none'], true) ? $v : 'duplicate';
    }

    public function preserveAlpha(): bool
    {
        return strtolower($this->getAttribute('preserveAlpha') ?? 'false') === 'true';
    }
}
