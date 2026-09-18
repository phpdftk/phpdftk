<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Svg;

use Phpdftk\Css\Value\ListSeparator;
use Phpdftk\Css\Value\StringValue;
use Phpdftk\Css\Value\Value;
use Phpdftk\Css\Value\ValueList;
use Phpdftk\HtmlToPdf\Layout\FontResolver;
use Phpdftk\Pdf\Core\Font\RegisteredFont;
use Phpdftk\Pdf\Writer\Font as WriterFont;
use Phpdftk\SvgToPdf\Text\DocumentFont;
use Phpdftk\SvgToPdf\Text\DocumentFontProvider;

/**
 * Hands the HTML document's font machinery to SVG text painting.
 *
 * SVG 2 §11.5 defers font selection wholesale to CSS Fonts 4, so
 * `<svg><text>` in an HTML document selects from exactly the same face
 * set as the surrounding HTML — `@font-face` families included. Without
 * this bridge the SVG translator falls back to its standalone
 * standard-14 mapping and a `font-family: Ahem` run paints in
 * Helvetica while the neighbouring `<div>` paints in Ahem.
 *
 * Matching runs through the layout {@see FontResolver} so weight and
 * style selection follow the same CSS Fonts 4 §6 rules as HTML text.
 * The resolver's *no family matched* answer is preserved rather than
 * collapsed onto the document default font: returning null lets the
 * translator keep its standard-14 behaviour for family stacks the
 * document never registered, so wiring this bridge in changes only the
 * runs that were demonstrably rendering in the wrong typeface.
 */
final readonly class DocumentFontBridge implements DocumentFontProvider
{
    /**
     * @param array<string, RegisteredFont> $registeredFonts keyed by PostScript name
     */
    public function __construct(
        private FontResolver $resolver,
        private array $registeredFonts,
    ) {}

    public function resolveDocumentFont(array $families, ?string $weight, ?string $style): ?DocumentFont
    {
        if ($families === [] || $this->registeredFonts === []) {
            return null;
        }
        $match = $this->resolver->resolveMatch(
            self::familyValue($families),
            self::weightToNumber($weight),
            self::normaliseStyle($style),
        );
        if ($match === null) {
            return null;
        }
        $registered = $this->registeredFonts[$match->face->data->postScriptName] ?? null;
        if ($registered === null) {
            return null;
        }
        return new DocumentFont(
            $registered,
            $registered instanceof WriterFont ? $registered->getUnicodeToGidMap() : [],
        );
    }

    /**
     * Rebuild the SVG side's plain family list into the comma-separated
     * `font-family` {@see Value} the layout resolver consumes.
     *
     * @param list<string> $families
     */
    private static function familyValue(array $families): Value
    {
        return new ValueList(
            array_map(static fn(string $name): Value => new StringValue($name), $families),
            ListSeparator::Comma,
        );
    }

    /**
     * CSS Fonts 4 §2.3 — map the raw `font-weight` string onto the
     * numeric axis the resolver matches against. `bolder` / `lighter`
     * are relative to the parent's computed weight, which the SVG
     * accessors don't expose; they collapse onto the values they take
     * against the initial `normal` parent.
     */
    private static function weightToNumber(?string $weight): int
    {
        if ($weight === null) {
            return 400;
        }
        $value = strtolower(trim($weight));
        if (is_numeric($value)) {
            return (int) max(1.0, min(1000.0, (float) $value));
        }
        return match ($value) {
            'bold', 'bolder' => 700,
            'lighter' => 100,
            default => 400,
        };
    }

    private static function normaliseStyle(?string $style): string
    {
        $value = strtolower(trim($style ?? ''));
        return match ($value) {
            'italic', 'oblique' => $value,
            default => 'normal',
        };
    }
}
