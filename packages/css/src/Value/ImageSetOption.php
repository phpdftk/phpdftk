<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * One `<image> [<resolution>]? [type(<mime>)]?` entry inside an
 * `image-set()`.
 *
 *   - `image` is either a {@see Url} or a {@see StringValue}
 *     (the parser accepts both `url(foo.png)` and `"foo.png"`).
 *   - `resolutionDppx` carries the entry's resolution in dppx
 *     (dots-per-pixel) — `1x` = 1.0, `2x` = 2.0, `192dpi` = 2.0,
 *     etc. Null when the author omitted a resolution.
 *   - `mimeType` is the entry's `type(<string>)` MIME hint
 *     (e.g. `image/svg+xml`). Null when omitted.
 */
final readonly class ImageSetOption
{
    public function __construct(
        public Value $image,
        public ?float $resolutionDppx = null,
        public ?string $mimeType = null,
    ) {}

    public function toCss(): string
    {
        $parts = [$this->image->toCss()];
        if ($this->resolutionDppx !== null) {
            $parts[] = self::formatResolution($this->resolutionDppx);
        }
        if ($this->mimeType !== null) {
            $parts[] = 'type("' . $this->mimeType . '")';
        }
        return implode(' ', $parts);
    }

    private static function formatResolution(float $dppx): string
    {
        return fmod($dppx, 1.0) === 0.0
            ? ((int) $dppx) . 'x'
            : $dppx . 'x';
    }
}
