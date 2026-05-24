<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

/**
 * Styling for {@see ListBlock} rendering.
 *
 * Defaults produce a familiar bullet style: solid round bullet `•`,
 * 18pt indent, 2pt vertical gap between items. The bullet alternates by
 * depth to mimic standard outline styles: `•` → `◦` → `▪` → cycle.
 *
 * For numbered lists, `$numberSuffix` is appended to the index
 * (`1.`, `2.`, `3.` by default).
 */
final class ListStyle
{
    /**
     * @param list<string> $bulletGlyphs One bullet per nesting level; cycles after the last entry.
     */
    public function __construct(
        public readonly float $indent = 18.0,
        public readonly float $itemSpacing = 3.0,
        public readonly array $bulletGlyphs = ['•', '◦', '▪'],
        public readonly string $numberSuffix = '.',
    ) {}

    public function bulletAt(int $depth): string
    {
        $count = count($this->bulletGlyphs);
        return $count === 0 ? '•' : $this->bulletGlyphs[$depth % $count];
    }
}
