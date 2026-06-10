<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * One corner kern table from a {@see MathKernRecord}.
 *
 * Per OpenType MATH spec, a kern table is a piecewise function of
 * Y position: `n` correction heights (breakpoints) define `n + 1`
 * height ranges, each carrying a kern value.
 *
 *   - For Y < correctionHeights[0]:                kern = kernValues[0]
 *   - For correctionHeights[i-1] <= Y < heights[i]: kern = kernValues[i]
 *   - For Y >= correctionHeights[n-1]:             kern = kernValues[n]
 *
 * The painter walks correction heights and picks the kern value
 * matching the sub/super Y attachment height.
 */
final readonly class MathKern
{
    /**
     * @param list<int> $correctionHeights FUnit Y breakpoints,
     *        strictly increasing per spec.
     * @param list<int> $kernValues FUnit horizontal kerning at each
     *        range. Always length `correctionHeights + 1`.
     */
    public function __construct(
        public array $correctionHeights,
        public array $kernValues,
    ) {}

    /**
     * Look up the kern value for a given Y offset above baseline.
     */
    public function valueAt(int $heightFunits): int
    {
        $i = 0;
        $count = count($this->correctionHeights);
        while ($i < $count && $heightFunits >= $this->correctionHeights[$i]) {
            $i++;
        }
        return $this->kernValues[$i] ?? 0;
    }
}
