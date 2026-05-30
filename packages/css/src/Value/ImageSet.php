<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `image-set(<image> [<resolution>]? [type(<mime>)]? [, ...])`
 * per CSS Images 4 §6. Lets authors offer multiple resolutions
 * (1x, 2x, ...) or formats (PNG, AVIF, WebP, ...) of the same
 * image; the renderer selects whichever entry best matches the
 * output target.
 *
 * Parser-level storage only. The selection algorithm runs once
 * the resource loader knows the target DPR and the format
 * negotiation policy.
 */
final readonly class ImageSet extends Value
{
    /**
     * @param list<ImageSetOption> $options
     */
    public function __construct(public array $options) {}

    public function toCss(): string
    {
        return 'image-set(' . implode(', ', array_map(
            static fn(ImageSetOption $o): string => $o->toCss(),
            $this->options,
        )) . ')';
    }
}
