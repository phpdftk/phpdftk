<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `env(<env-name> [<integer>]*, <fallback>?)` per CSS Environment
 * Variables 1 §3. Resolves to a UA-provided named environment
 * variable; commonly `safe-area-inset-*`, `titlebar-area-*`,
 * `viewport-segment-*`.
 *
 * Optional positional indices follow the name for indexed env vars
 * (e.g. `env(viewport-segment-width 0 1)`).
 *
 * For a static print render most env() values aren't defined and
 * the fallback (if any) wins. The cascade preserves the parsed
 * declaration so the renderer can register additional env values
 * via a future hook.
 *
 *   padding-top: env(safe-area-inset-top);
 *   padding-top: env(safe-area-inset-top, 12px);
 *   width:       env(viewport-segment-width 0 1, 100%);
 */
final readonly class EnvFunction extends Value
{
    /**
     * @param list<int> $indices
     */
    public function __construct(
        public string $name,
        public array $indices = [],
        public ?Value $fallback = null,
    ) {}

    public function toCss(): string
    {
        $parts = [$this->name];
        foreach ($this->indices as $i) {
            $parts[] = (string) $i;
        }
        $head = 'env(' . implode(' ', $parts);
        return $this->fallback !== null
            ? $head . ', ' . $this->fallback->toCss() . ')'
            : $head . ')';
    }
}
