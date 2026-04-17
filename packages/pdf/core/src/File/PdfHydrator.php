<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\File;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\Serializable;

/**
 * Hydrates raw `PdfDictionary` objects (from the reader) into typed
 * `PdfObject` subclasses (used by the writer).
 *
 * The hydrator uses a `/Type` → class registry and maps PDF dictionary
 * keys to PHP property names using a key→property map built from each
 * class's known field mappings.
 *
 * Values are assigned as-is — `PdfReference` values stay as references
 * (lazy resolution), and inline dicts/arrays are preserved. Deep
 * hydration (recursively hydrating nested objects) is opt-in via
 * the resolver.
 */
final class PdfHydrator
{
    /**
     * Registry mapping PDF /Type values to PHP class names.
     *
     * @var array<string, class-string<PdfObject>>
     */
    private static array $typeMap = [];

    /**
     * Cache of key→property maps per class.
     *
     * @var array<class-string, array<string, string>>
     */
    private static array $keyMapCache = [];

    /**
     * Register a PdfObject subclass for a given /Type value.
     *
     * @param class-string<PdfObject> $className
     */
    public static function registerType(string $pdfType, string $className): void
    {
        self::$typeMap[$pdfType] = $className;
    }

    /**
     * Hydrate a raw PdfDictionary into a typed PdfObject.
     *
     * If the dictionary has a /Type entry that maps to a registered class,
     * that class is instantiated and its properties populated. Otherwise,
     * the raw dictionary is returned unchanged.
     *
     * @param int $objectNumber Object number to assign to the hydrated object
     * @param int $generationNumber Generation number
     */
    public static function hydrate(
        PdfDictionary $dict,
        int $objectNumber = 0,
        int $generationNumber = 0,
    ): PdfObject|PdfDictionary {
        $type = $dict->get('Type');
        $typeName = $type instanceof PdfName ? $type->value : null;

        if ($typeName === null || !isset(self::$typeMap[$typeName])) {
            return $dict;
        }

        $className = self::$typeMap[$typeName];
        $object = new $className();

        if ($object instanceof PdfObject) {
            $object->objectNumber = $objectNumber;
            $object->generationNumber = $generationNumber;
        }

        $keyMap = self::getKeyMap($className);

        foreach ($dict->entries as $key => $value) {
            if ($key === 'Type') {
                continue;
            }

            $propertyName = $keyMap[$key] ?? null;
            if ($propertyName !== null && property_exists($object, $propertyName)) {
                try {
                    $object->$propertyName = self::coerce($value, $object, $propertyName);
                } catch (\TypeError) {
                    // Value type incompatible with property — skip
                    // (e.g., PdfDictionary for a typed Resources property)
                }
            }
        }

        return $object;
    }

    /**
     * Coerce a raw PDF value to match the declared PHP property type.
     *
     * Handles common mismatches like PdfNumber → int/float,
     * PdfArray → array, PdfReference in single-value array slots, etc.
     */
    private static function coerce(mixed $value, object $object, string $propertyName): mixed
    {
        $ref = new \ReflectionProperty($object, $propertyName);
        $type = $ref->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        // PdfNumber → int
        if ($typeName === 'int' && $value instanceof PdfNumber) {
            return (int) $value->toPdf();
        }

        // PdfNumber → float
        if ($typeName === 'float' && $value instanceof PdfNumber) {
            return (float) $value->toPdf();
        }

        // PdfBoolean → bool
        if ($typeName === 'bool' && $value instanceof PdfBoolean) {
            return $value->value;
        }

        // PdfArray → array (extract items)
        if ($typeName === 'array' && $value instanceof PdfArray) {
            return $value->items;
        }

        // Single PdfReference assigned to an array property (e.g., /Contents with 1 ref)
        if ($typeName === 'array' && $value instanceof PdfReference) {
            return [$value];
        }

        return $value;
    }

    /**
     * Build or retrieve the PDF key → PHP property map for a class.
     *
     * Uses reflection to discover all public properties, then maps
     * each property name to its corresponding PDF key using the
     * standard naming convention: lcfirst(PdfKey) = phpProperty.
     *
     * Special cases (where the convention doesn't apply) are handled
     * by explicit overrides.
     *
     * @param class-string $className
     * @return array<string, string> PDF key → property name
     */
    private static function getKeyMap(string $className): array
    {
        if (isset(self::$keyMapCache[$className])) {
            return self::$keyMapCache[$className];
        }

        $map = [];
        $ref = new \ReflectionClass($className);

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $propName = $prop->getName();

            // Skip PdfObject base class properties
            if ($propName === 'objectNumber' || $propName === 'generationNumber') {
                continue;
            }
            // Skip PdfStream properties
            if ($propName === 'dictionary' || $propName === 'data') {
                continue;
            }

            // Convert camelCase property to PDF key (ucfirst)
            $pdfKey = ucfirst($propName);

            $map[$pdfKey] = $propName;
        }

        // Apply known special-case overrides where the naming convention
        // doesn't match (PDF key differs from ucfirst(property))
        $overrides = self::getOverrides($className);
        foreach ($overrides as $pdfKey => $propName) {
            $map[$pdfKey] = $propName;
        }

        self::$keyMapCache[$className] = $map;
        return $map;
    }

    /**
     * Return special-case PDF key → property overrides for a class.
     *
     * @return array<string, string>
     */
    private static function getOverrides(string $className): array
    {
        // Class-specific overrides where PDF key ≠ ucfirst(propertyName)
        $shortName = (new \ReflectionClass($className))->getShortName();

        return match ($shortName) {
            'Page' => [
                'Trans' => 'transition',
                'AA' => 'aa',
                'PZ' => 'pz',
                'B' => 'b',
                'AF' => 'af',
                'ID' => 'id',
                'VP' => 'vp',
                'DPart' => 'dPart',
            ],
            'Catalog' => [
                'AA' => 'aa',
                'URI' => 'uri',
                'AF' => 'af',
                'DSS' => 'dss',
                'DPartRoot' => 'dPartRoot',
            ],
            'PageTree' => [
                'Kids' => 'kids',
            ],
            'Info' => [
                'ModDate' => 'modDate',
            ],
            'FontDescriptor' => [
                'ItalicAngle' => 'italicAngle',
                'StemV' => 'stemV',
                'StemH' => 'stemH',
                'CapHeight' => 'capHeight',
                'XHeight' => 'xHeight',
                'AvgWidth' => 'avgWidth',
                'MaxWidth' => 'maxWidth',
                'MissingWidth' => 'missingWidth',
                'FontFile' => 'fontFile',
                'FontFile2' => 'fontFile2',
                'FontFile3' => 'fontFile3',
                'FontBBox' => 'fontBBox',
                'FontName' => 'fontName',
                'FontFamily' => 'fontFamily',
                'FontStretch' => 'fontStretch',
                'FontWeight' => 'fontWeight',
            ],
            'OutlineItem' => [
                'A' => 'a',
                'C' => 'c',
                'F' => 'f',
                'SE' => 'se',
            ],
            'OutputIntent' => [
                'S' => 's',
            ],
            'StructTreeRoot' => [
                'K' => 'k',
            ],
            'EncryptDictionary' => [
                'CF' => 'cf',
                'O' => 'o',
                'U' => 'u',
                'OE' => 'oe',
                'UE' => 'ue',
                'P' => 'p',
                'R' => 'r',
                'V' => 'v',
            ],
            default => [],
        };
    }

    /**
     * Auto-discover and register all PdfObject subclasses that define
     * a PDF_TYPE constant. Call once at bootstrap.
     */
    public static function registerDefaults(): void
    {
        if (!empty(self::$typeMap)) {
            return;
        }

        // Core document types
        $classes = [
            \ApprLabs\Pdf\Core\Document\Catalog::class,
            \ApprLabs\Pdf\Core\Document\Page::class,
            \ApprLabs\Pdf\Core\Document\PageTree::class,
            \ApprLabs\Pdf\Core\Document\Outline::class,
            \ApprLabs\Pdf\Core\Document\OutlineItem::class,
            \ApprLabs\Pdf\Core\Document\PageLabel::class,
            \ApprLabs\Pdf\Core\Document\OutputIntent::class,
            \ApprLabs\Pdf\Core\Document\StructTreeRoot::class,
            \ApprLabs\Pdf\Core\Document\StructElem::class,
            \ApprLabs\Pdf\Core\Document\OCG::class,
            \ApprLabs\Pdf\Core\Document\OCMD::class,
            \ApprLabs\Pdf\Core\Document\Collection::class,
            \ApprLabs\Pdf\Core\Document\Thread::class,
            \ApprLabs\Pdf\Core\Document\Bead::class,
            \ApprLabs\Pdf\Core\Document\ObjectRef::class,
            \ApprLabs\Pdf\Core\Document\DPartRoot::class,
            \ApprLabs\Pdf\Core\Document\DPart::class,
            \ApprLabs\Pdf\Core\Document\RequirementHandler::class,
            // Font types
            \ApprLabs\Pdf\Core\Font\FontDescriptor::class,
            // FileSpec
            \ApprLabs\Pdf\Core\FileSpec\FileSpec::class,
            // Security
            \ApprLabs\Pdf\Core\Security\EncryptDictionary::class,
        ];

        foreach ($classes as $class) {
            if (defined("$class::PDF_TYPE")) {
                self::registerType($class::PDF_TYPE, $class);
            }
        }
    }
}
