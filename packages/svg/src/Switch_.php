<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §5.7 — `<switch>` evaluates each direct child element's
 * conditional processing attributes (`requiredFeatures`,
 * `requiredExtensions`, `systemLanguage`) and renders only the
 * first child for which every test evaluates true. Children
 * without any test attribute always pass.
 *
 * For the server-side print medium:
 *
 *   - `requiredFeatures` (deprecated, SVG 1.1 holdover) — every
 *     listed feature URI evaluates true so the test never fails.
 *   - `requiredExtensions` — unknown URIs fail; common UA
 *     extensions are rejected since print can't observe them.
 *   - `systemLanguage` — matches the document's `xml:lang` /
 *     `lang` attribute using BCP 47 case-insensitive prefix
 *     comparison.
 *
 * The selection is computed eagerly at paint time from the
 * Translator; this class is purely a typed container so the
 * dispatch can pattern-match on it.
 */
final class Switch_ extends Element
{
    public function __construct()
    {
        parent::__construct('switch');
    }
}
