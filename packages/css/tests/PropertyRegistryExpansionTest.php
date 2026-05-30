<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Cascade\PropertyRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Confirms the recently-expanded CSS property surface lands as
 * registered properties so author declarations cascade instead of
 * being silently dropped:
 *
 *   CSS Text 4         text-wrap, text-wrap-style, hyphens,
 *                      hyphenate-character, hyphenate-limit-chars,
 *                      text-spacing-trim, text-spacing,
 *                      text-autospace
 *   CSS Text Decor 4   text-emphasis, text-emphasis-color,
 *                      text-emphasis-position, text-emphasis-style,
 *                      text-decoration-skip-ink
 *   CSS Inline 3       text-box-trim, text-box-edge, initial-letter
 *   CSS Sizing 4       contain-intrinsic-* family
 *   CSS Anchor Pos 1   anchor-name, anchor-scope, position-anchor,
 *                      position-area, inset-area
 */
final class PropertyRegistryExpansionTest extends TestCase
{
    private PropertyRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = PropertyRegistry::default();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function newlyRegisteredProperties(): iterable
    {
        // CSS Text 4 §6 (text-wrap) + §7 (hyphenation) + §10 (text-spacing)
        yield 'text-wrap' => ['text-wrap'];
        yield 'text-wrap-style' => ['text-wrap-style'];
        yield 'hyphens' => ['hyphens'];
        yield 'hyphenate-character' => ['hyphenate-character'];
        yield 'hyphenate-limit-chars' => ['hyphenate-limit-chars'];
        yield 'text-spacing-trim' => ['text-spacing-trim'];
        yield 'text-spacing' => ['text-spacing'];
        yield 'text-autospace' => ['text-autospace'];

        // CSS Text Decoration 4
        yield 'text-emphasis' => ['text-emphasis'];
        yield 'text-emphasis-color' => ['text-emphasis-color'];
        yield 'text-emphasis-position' => ['text-emphasis-position'];
        yield 'text-emphasis-style' => ['text-emphasis-style'];
        yield 'text-decoration-skip-ink' => ['text-decoration-skip-ink'];

        // CSS Inline 3 §6
        yield 'text-box-trim' => ['text-box-trim'];
        yield 'text-box-edge' => ['text-box-edge'];
        yield 'initial-letter' => ['initial-letter'];

        // CSS Sizing 4 §6
        yield 'contain-intrinsic-size' => ['contain-intrinsic-size'];
        yield 'contain-intrinsic-width' => ['contain-intrinsic-width'];
        yield 'contain-intrinsic-height' => ['contain-intrinsic-height'];
        yield 'contain-intrinsic-block-size' => ['contain-intrinsic-block-size'];
        yield 'contain-intrinsic-inline-size' => ['contain-intrinsic-inline-size'];

        // CSS Anchor Positioning 1
        yield 'anchor-name' => ['anchor-name'];
        yield 'anchor-scope' => ['anchor-scope'];
        yield 'position-anchor' => ['position-anchor'];
        yield 'position-area' => ['position-area'];
        yield 'inset-area' => ['inset-area'];

        // CSS Cascade 5 + Lists 3 + UI 4 + Values 5
        yield 'all' => ['all'];
        yield 'marker-side' => ['marker-side'];
        yield 'counter-set' => ['counter-set'];
        yield 'appearance' => ['appearance'];
        yield 'field-sizing' => ['field-sizing'];
        yield 'interpolate-size' => ['interpolate-size'];

        // CSS Box Alignment 3
        yield 'grid-gap' => ['grid-gap'];
        yield 'grid-row-gap' => ['grid-row-gap'];
        yield 'grid-column-gap' => ['grid-column-gap'];
        yield 'place-content' => ['place-content'];
        yield 'place-items' => ['place-items'];
        yield 'place-self' => ['place-self'];

        // CSS Containment 3 §4
        yield 'container' => ['container'];
        yield 'container-name' => ['container-name'];
        yield 'container-type' => ['container-type'];

        // CSS Logical Properties 1 §4 — sizing
        yield 'block-size' => ['block-size'];
        yield 'inline-size' => ['inline-size'];
        yield 'min-block-size' => ['min-block-size'];
        yield 'min-inline-size' => ['min-inline-size'];
        yield 'max-block-size' => ['max-block-size'];
        yield 'max-inline-size' => ['max-inline-size'];

        // CSS Logical Properties 1 §5 — margin / padding
        foreach ([
            'margin-block', 'margin-block-start', 'margin-block-end',
            'margin-inline', 'margin-inline-start', 'margin-inline-end',
            'padding-block', 'padding-block-start', 'padding-block-end',
            'padding-inline', 'padding-inline-start', 'padding-inline-end',
        ] as $name) {
            yield $name => [$name];
        }

        // CSS Logical Properties 1 §6 — inset
        foreach ([
            'inset', 'inset-block', 'inset-block-start', 'inset-block-end',
            'inset-inline', 'inset-inline-start', 'inset-inline-end',
        ] as $name) {
            yield $name => [$name];
        }

        // CSS Logical Properties 1 §7 — borders + corner radii
        foreach ([
            'border-block', 'border-inline',
            'border-block-color', 'border-inline-color',
            'border-block-style', 'border-inline-style',
            'border-block-width', 'border-inline-width',
            'border-block-start-color', 'border-block-end-color',
            'border-inline-start-color', 'border-inline-end-color',
            'border-block-start-style', 'border-block-end-style',
            'border-inline-start-style', 'border-inline-end-style',
            'border-block-start-width', 'border-block-end-width',
            'border-inline-start-width', 'border-inline-end-width',
            'border-start-start-radius', 'border-start-end-radius',
            'border-end-start-radius', 'border-end-end-radius',
        ] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('newlyRegisteredProperties')]
    public function testPropertyIsRegistered(string $property): void
    {
        self::assertTrue(
            $this->registry->has($property),
            sprintf('expected "%s" to be registered so author CSS cascades', $property),
        );
        self::assertNotNull(
            $this->registry->get($property),
            sprintf('expected registry->get("%s") to return a definition', $property),
        );
    }

    #[DataProvider('inheritingProperties')]
    public function testTextAndDecorationPropertiesInherit(string $property): void
    {
        $def = $this->registry->get($property);
        self::assertNotNull($def);
        self::assertTrue(
            $def->inherits,
            sprintf('expected "%s" to inherit per spec', $property),
        );
    }

    /**
     * Subset that MUST inherit per the relevant spec.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function inheritingProperties(): iterable
    {
        // CSS Text 4 §6 — text-wrap is inherited per CSSWG spec.
        yield 'text-wrap' => ['text-wrap'];
        yield 'text-wrap-style' => ['text-wrap-style'];
        yield 'hyphens' => ['hyphens'];
        yield 'hyphenate-character' => ['hyphenate-character'];
        // Text Decoration 4
        yield 'text-emphasis' => ['text-emphasis'];
        yield 'text-emphasis-color' => ['text-emphasis-color'];
        yield 'text-emphasis-position' => ['text-emphasis-position'];
        yield 'text-emphasis-style' => ['text-emphasis-style'];
        yield 'text-decoration-skip-ink' => ['text-decoration-skip-ink'];
        // Inline 3
        yield 'text-box-trim' => ['text-box-trim'];
    }

    public function testContainIntrinsicPropertiesDoNotInherit(): void
    {
        // CSS Sizing 4 — contain-intrinsic-* are NOT inherited.
        foreach ([
            'contain-intrinsic-size',
            'contain-intrinsic-width',
            'contain-intrinsic-height',
            'contain-intrinsic-block-size',
            'contain-intrinsic-inline-size',
        ] as $property) {
            $def = $this->registry->get($property);
            self::assertNotNull($def);
            self::assertFalse(
                $def->inherits,
                sprintf('expected "%s" NOT to inherit', $property),
            );
        }
    }

    public function testAnchorPositioningPropertiesDoNotInherit(): void
    {
        foreach (['anchor-name', 'anchor-scope', 'position-anchor', 'position-area'] as $property) {
            $def = $this->registry->get($property);
            self::assertNotNull($def);
            self::assertFalse(
                $def->inherits,
                sprintf('expected "%s" NOT to inherit', $property),
            );
        }
    }
}
