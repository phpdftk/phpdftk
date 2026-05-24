<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

/**
 * Built-in callout types for {@see Pdf::addCallout()} / {@see Writer\Page::drawCallout()}.
 *
 * Each case carries default bar / background colours and a default
 * label. Override any of these via {@see CalloutStyle}.
 */
enum CalloutType: string
{
    case Note = 'Note';
    case Tip = 'Tip';
    case Warning = 'Warning';
    case Danger = 'Danger';

    /**
     * Default left-bar colour for this type, RGB 0-1.
     *
     * @return array{float,float,float}
     */
    public function defaultBarColor(): array
    {
        return match ($this) {
            self::Note    => [0.23, 0.51, 0.96], // blue-500
            self::Tip     => [0.13, 0.60, 0.35], // green-600
            self::Warning => [0.92, 0.61, 0.10], // amber-500
            self::Danger  => [0.86, 0.20, 0.20], // red-600
        };
    }

    /**
     * Default background tint, RGB 0-1.
     *
     * @return array{float,float,float}
     */
    public function defaultBgColor(): array
    {
        return match ($this) {
            self::Note    => [0.93, 0.95, 1.00], // blue-50
            self::Tip     => [0.93, 0.98, 0.94], // green-50
            self::Warning => [1.00, 0.97, 0.92], // amber-50
            self::Danger  => [1.00, 0.93, 0.93], // red-50
        };
    }

    public function defaultLabel(): string
    {
        return $this->value;
    }
}
