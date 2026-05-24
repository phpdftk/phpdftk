<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

/**
 * Per-call style overrides for {@see Pdf::addCallout()} /
 * {@see Writer\Page::drawCallout()}.
 *
 * All colour fields are nullable: null means "use the
 * {@see CalloutType}'s built-in default". The two scalar fields
 * (padding, barWidth) always have a value — there's no per-type
 * default for those.
 */
final class CalloutStyle
{
    /**
     * @param float                              $padding       Internal padding around the body text.
     * @param float                              $barWidth      Left-edge bar thickness.
     * @param array{float,float,float}|null      $barColor      Override the type's default bar colour.
     * @param array{float,float,float}|null      $bgColor       Override the type's default background tint. `null` keeps the default; pass `[]`-style explicit-white to disable.
     * @param array{float,float,float}|null      $textColor     Body text colour; null = theme default.
     * @param bool                               $showLabel     Whether to render the title row (e.g. "Note", "Warning").
     * @param string|null                        $labelOverride Title text override; null = use {@see CalloutType::defaultLabel()}.
     */
    public function __construct(
        public readonly float $padding = 8.0,
        public readonly float $barWidth = 4.0,
        public readonly ?array $barColor = null,
        public readonly ?array $bgColor = null,
        public readonly ?array $textColor = null,
        public readonly bool $showLabel = true,
        public readonly ?string $labelOverride = null,
    ) {}

    /**
     * @return array{float,float,float}
     */
    public function resolveBarColor(CalloutType $type): array
    {
        return $this->barColor ?? $type->defaultBarColor();
    }

    /**
     * @return array{float,float,float}
     */
    public function resolveBgColor(CalloutType $type): array
    {
        return $this->bgColor ?? $type->defaultBgColor();
    }

    public function resolveLabel(CalloutType $type): string
    {
        return $this->labelOverride ?? $type->defaultLabel();
    }
}
