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
     * Registry mapping /Type+/Subtype to PHP class names for types
     * that share a /Type value (e.g., Annot, Font, XObject, Pattern).
     *
     * @var array<string, array<string, class-string<PdfObject>>>
     */
    private static array $subtypeMap = [];

    /**
     * Registry mapping action /S values to PHP class names.
     *
     * @var array<string, class-string<PdfObject>>
     */
    private static array $actionTypeMap = [];

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
     * Register a PdfObject subclass for a /Type + /Subtype combination.
     *
     * @param class-string<PdfObject> $className
     */
    public static function registerSubtype(string $pdfType, string $subtype, string $className): void
    {
        self::$subtypeMap[$pdfType][$subtype] = $className;
    }

    /**
     * Register a PdfObject subclass for an action /S value.
     *
     * @param class-string<PdfObject> $className
     */
    public static function registerActionType(string $actionType, string $className): void
    {
        self::$actionTypeMap[$actionType] = $className;
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

        if ($typeName === null) {
            // Check if this is an action dict (has /S but no /Type — common in real PDFs)
            $className = self::resolveActionClass($dict);
            if ($className === null) {
                return $dict;
            }
        } else {
            // Resolve the target class — check subtype map first for shared /Type values
            $className = self::resolveClass($typeName, $dict);
        }

        // For /Type /Action, also try action dispatch if subtype/type lookup failed
        if ($className === null && $typeName === 'Action') {
            $className = self::resolveActionClass($dict);
        }
        if ($className === null) {
            return $dict;
        }

        // Construct the object — some classes require constructor args from the dict
        $object = self::construct($className, $dict);
        if ($object === null) {
            return $dict;
        }

        $object->objectNumber = $objectNumber;
        $object->generationNumber = $generationNumber;

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
     * Resolve the target class for a given /Type, considering /Subtype.
     *
     * @return class-string<PdfObject>|null
     */
    private static function resolveClass(string $typeName, PdfDictionary $dict): ?string
    {
        // Check subtype map first for types with multiple implementations
        if (isset(self::$subtypeMap[$typeName])) {
            $subtype = $dict->get('Subtype');
            if ($subtype instanceof PdfName && isset(self::$subtypeMap[$typeName][$subtype->value])) {
                return self::$subtypeMap[$typeName][$subtype->value];
            }
            // Fall through to base type map if no subtype match
        }

        return self::$typeMap[$typeName] ?? null;
    }

    /**
     * Resolve an action class from the /S key in the dictionary.
     *
     * @return class-string<PdfObject>|null
     */
    private static function resolveActionClass(PdfDictionary $dict): ?string
    {
        $s = $dict->get('S');
        if ($s instanceof PdfName && isset(self::$actionTypeMap[$s->value])) {
            return self::$actionTypeMap[$s->value];
        }
        return null;
    }

    /**
     * Construct a PdfObject, extracting required constructor args from the dict.
     */
    private static function construct(string $className, PdfDictionary $dict): ?PdfObject
    {
        $ref = new \ReflectionClass($className);
        if ($ref->isAbstract()) {
            return null;
        }

        $constructor = $ref->getConstructor();
        if ($constructor === null) {
            return new $className();
        }

        // Check if all parameters have defaults
        $params = $constructor->getParameters();
        $allOptional = true;
        foreach ($params as $param) {
            if (!$param->isOptional()) {
                $allOptional = false;
                break;
            }
        }
        if ($allOptional) {
            return new $className();
        }

        // Try to extract required args from the dictionary
        $args = [];
        foreach ($params as $param) {
            if ($param->isOptional()) {
                break; // Once we hit optional params, stop — let defaults apply
            }
            $value = self::extractConstructorArg($param, $dict);
            if ($value === null) {
                return null; // Can't satisfy required arg — bail
            }
            $args[] = $value;
        }

        try {
            return new $className(...$args);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Try to extract a constructor argument value from the dictionary.
     */
    private static function extractConstructorArg(\ReflectionParameter $param, PdfDictionary $dict): mixed
    {
        $name = $param->getName();
        $type = $param->getType();
        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

        // Map common constructor param names to PDF keys
        $pdfKey = match ($name) {
            'title' => 'Title',
            'fontName' => 'FontName',
            'rect' => 'Rect',
            'subtype' => 'Subtype',
            'structureType' => 'S',
            'name' => 'Name',
            'externalName' => 'XN',
            'sampleRate' => 'R',
            'transformMethod' => 'TransformMethod',
            'outputConditionIdentifier' => 'OutputConditionIdentifier',
            'dPartRootNode' => 'DPartRootNode',
            'parent' => 'Parent',
            // Action constructor params
            'dest' => 'D',
            'uri' => 'URI',
            'js' => 'JS',
            'n' => 'N',
            'f' => 'F',
            't' => 'T',
            'hide' => 'H',
            'sound' => 'Sound',
            'ta' => 'TA',
            'state' => 'State',
            'trans' => 'Trans',
            'bBox' => 'BBox',
            'baseFontName' => 'BaseFont',
            default => ucfirst($name),
        };

        $value = $dict->get($pdfKey);
        if ($value === null) {
            return null;
        }

        // Coerce to expected type
        if ($typeName === 'string') {
            if ($value instanceof PdfName) {
                return $value->value;
            }
            if ($value instanceof \ApprLabs\Pdf\Core\PdfString) {
                return $value->value;
            }
            return (string) $value;
        }

        if ($typeName === 'int' && $value instanceof PdfNumber) {
            return (int) $value->toPdf();
        }

        if ($typeName === 'float' && $value instanceof PdfNumber) {
            return (float) $value->toPdf();
        }

        return $value;
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
            'StructElem' => [
                'S' => 's',
                'P' => 'p',
                'K' => 'k',
                'A' => 'a',
                'C' => 'c',
                'R' => 'r',
                'T' => 't',
                'E' => 'e',
                'ID' => 'id',
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
            'OCG' => [
                'Name' => 'name',
            ],
            'ExtGState' => [
                'LW' => 'lw',
                'LC' => 'lc',
                'LJ' => 'lj',
                'ML' => 'ml',
                'D' => 'd',
                'RI' => 'ri',
                'OP' => 'op',
                'op' => 'opLower',
                'OPM' => 'opm',
                'FL' => 'fl',
                'SM' => 'sm',
                'SA' => 'sa',
                'BM' => 'bm',
                'SMask' => 'sMask',
                'CA' => 'ca',
                'ca' => 'caLower',
                'AIS' => 'ais',
                'TK' => 'tk',
                'BG' => 'bg',
                'BG2' => 'bg2',
                'UCR' => 'ucr',
                'UCR2' => 'ucr2',
                'TR' => 'tr',
                'TR2' => 'tr2',
                'HT' => 'ht',
                'HTO' => 'hto',
            ],
            'SignatureValue', 'DocTimeStamp' => [
                'M' => 'm',
            ],
            'ThreeDBackground' => [
                'CS' => 'cs',
                'C' => 'c',
                'EA' => 'ea',
            ],
            'ThreeDCrossSection' => [
                'C' => 'c',
                'O' => 'o',
                'PC' => 'pc',
                'PO' => 'po',
                'IV' => 'iv',
                'IC' => 'ic',
                'ST' => 'st',
            ],
            'ThreeDView' => [
                'XN' => 'xn',
                'IN' => 'in',
                'MS' => 'ms',
                'C2W' => 'c2w',
                'CO' => 'co',
                'P' => 'p',
                'O' => 'o',
                'BG' => 'bg',
                'RM' => 'rm',
                'LS' => 'ls',
                'SA' => 'sa',
            ],
            'ThreeDStream' => [
                'VA' => 'va',
                'DV' => 'dv',
                'AN' => 'an',
            ],
            // Actions — map /S and single-letter PDF keys to property names
            'GoToAction' => ['D' => 'dest'],
            'GoToRAction' => ['D' => 'dest', 'F' => 'f'],
            'GoToEAction' => ['D' => 'd', 'F' => 'f', 'T' => 't'],
            'URIAction' => ['URI' => 'uri'],
            'JavaScriptAction' => ['JS' => 'js'],
            'NamedAction' => ['N' => 'n'],
            'HideAction' => ['T' => 't', 'H' => 'h'],
            'SoundAction' => ['Sound' => 'sound'],
            'SubmitFormAction' => ['F' => 'f'],
            'ImportDataAction' => ['F' => 'f'],
            'SetOCGStateAction' => ['State' => 'state'],
            'GoTo3DViewAction' => ['TA' => 'ta', 'V' => 'v'],
            'TransAction' => ['Trans' => 'trans'],
            'RenditionAction' => ['R' => 'r', 'AN' => 'an', 'OP' => 'op'],
            'MovieAction' => ['T' => 't'],
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

        // ---------------------------------------------------------------
        // Unique /Type classes (one class per /Type value)
        // ---------------------------------------------------------------
        $uniqueTypes = [
            // Document structure
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
            \ApprLabs\Pdf\Core\Document\CollectionSchema::class,
            \ApprLabs\Pdf\Core\Document\Thread::class,
            \ApprLabs\Pdf\Core\Document\Bead::class,
            \ApprLabs\Pdf\Core\Document\ObjectRef::class,
            \ApprLabs\Pdf\Core\Document\DPartRoot::class,
            \ApprLabs\Pdf\Core\Document\DPart::class,
            \ApprLabs\Pdf\Core\Document\Requirement::class,
            \ApprLabs\Pdf\Core\Document\RequirementHandler::class,
            \ApprLabs\Pdf\Core\Document\MetadataStream::class,
            \ApprLabs\Pdf\Core\Document\CrossReferenceStream::class,
            \ApprLabs\Pdf\Core\Document\ObjectStream::class,
            // Font
            \ApprLabs\Pdf\Core\Font\FontDescriptor::class,
            \ApprLabs\Pdf\Core\Font\Encoding::class,
            \ApprLabs\Pdf\Core\Font\CMapStream::class,
            // FileSpec
            \ApprLabs\Pdf\Core\FileSpec\FileSpec::class,
            \ApprLabs\Pdf\Core\FileSpec\EmbeddedFile::class,
            // Security
            \ApprLabs\Pdf\Core\Security\EncryptDictionary::class,
            // Graphics
            \ApprLabs\Pdf\Core\Graphics\ExtGState::class,
            // Interactive — forms
            \ApprLabs\Pdf\Core\Interactive\Form\SigFieldLock::class,
            \ApprLabs\Pdf\Core\Interactive\Form\SeedValueDictionary::class,
            // Interactive — signatures
            \ApprLabs\Pdf\Core\Interactive\Signature\SignatureValue::class,
            \ApprLabs\Pdf\Core\Interactive\Signature\DocTimeStamp::class,
            \ApprLabs\Pdf\Core\Interactive\Signature\SignatureReference::class,
            // Multimedia
            \ApprLabs\Pdf\Core\Multimedia\Sound::class,
            \ApprLabs\Pdf\Core\Multimedia\MediaPlayParams::class,
            \ApprLabs\Pdf\Core\Multimedia\MediaScreenParams::class,
            \ApprLabs\Pdf\Core\Multimedia\MediaCriteria::class,
            \ApprLabs\Pdf\Core\Multimedia\Navigator::class,
            // 3D
            \ApprLabs\Pdf\Core\ThreeD\ThreeDStream::class,
            \ApprLabs\Pdf\Core\ThreeD\ThreeDView::class,
            \ApprLabs\Pdf\Core\ThreeD\ThreeDBackground::class,
            \ApprLabs\Pdf\Core\ThreeD\ThreeDRenderMode::class,
            \ApprLabs\Pdf\Core\ThreeD\ThreeDLightingScheme::class,
            \ApprLabs\Pdf\Core\ThreeD\ThreeDCrossSection::class,
            \ApprLabs\Pdf\Core\ThreeD\ThreeDNode::class,
            \ApprLabs\Pdf\Core\ThreeD\ThreeDMeasure::class,
        ];

        foreach ($uniqueTypes as $class) {
            if (defined("$class::PDF_TYPE")) {
                self::registerType($class::PDF_TYPE, $class);
            }
        }

        // ---------------------------------------------------------------
        // Shared /Type classes — dispatched by /Subtype
        // ---------------------------------------------------------------

        // /Type /Annot — concrete annotation subtypes
        $annotationSubtypes = [
            'Text' => \ApprLabs\Pdf\Core\Annotation\TextAnnotation::class,
            'Link' => \ApprLabs\Pdf\Core\Annotation\LinkAnnotation::class,
            'FreeText' => \ApprLabs\Pdf\Core\Annotation\FreeTextAnnotation::class,
            'Highlight' => \ApprLabs\Pdf\Core\Annotation\HighlightAnnotation::class,
            'Underline' => \ApprLabs\Pdf\Core\Annotation\UnderlineAnnotation::class,
            'Squiggly' => \ApprLabs\Pdf\Core\Annotation\SquigglyAnnotation::class,
            'StrikeOut' => \ApprLabs\Pdf\Core\Annotation\StrikeOutAnnotation::class,
            'Stamp' => \ApprLabs\Pdf\Core\Annotation\StampAnnotation::class,
            'Ink' => \ApprLabs\Pdf\Core\Annotation\InkAnnotation::class,
            'Popup' => \ApprLabs\Pdf\Core\Annotation\PopupAnnotation::class,
            'Widget' => \ApprLabs\Pdf\Core\Annotation\WidgetAnnotation::class,
            'Line' => \ApprLabs\Pdf\Core\Annotation\LineAnnotation::class,
            'Square' => \ApprLabs\Pdf\Core\Annotation\SquareAnnotation::class,
            'Circle' => \ApprLabs\Pdf\Core\Annotation\CircleAnnotation::class,
            'Polygon' => \ApprLabs\Pdf\Core\Annotation\PolygonAnnotation::class,
            'PolyLine' => \ApprLabs\Pdf\Core\Annotation\PolyLineAnnotation::class,
            'Caret' => \ApprLabs\Pdf\Core\Annotation\CaretAnnotation::class,
            'FileAttachment' => \ApprLabs\Pdf\Core\Annotation\FileAttachmentAnnotation::class,
            'Sound' => \ApprLabs\Pdf\Core\Annotation\SoundAnnotation::class,
            'Movie' => \ApprLabs\Pdf\Core\Annotation\MovieAnnotation::class,
            'Screen' => \ApprLabs\Pdf\Core\Annotation\ScreenAnnotation::class,
            'PrinterMark' => \ApprLabs\Pdf\Core\Annotation\PrinterMarkAnnotation::class,
            'TrapNet' => \ApprLabs\Pdf\Core\Annotation\TrapNetAnnotation::class,
            'Watermark' => \ApprLabs\Pdf\Core\Annotation\WatermarkAnnotation::class,
            '3D' => \ApprLabs\Pdf\Core\Annotation\ThreeDAnnotation::class,
            'Redact' => \ApprLabs\Pdf\Core\Annotation\RedactAnnotation::class,
            'Projection' => \ApprLabs\Pdf\Core\Annotation\ProjectionAnnotation::class,
            'RichMedia' => \ApprLabs\Pdf\Core\Annotation\RichMediaAnnotation::class,
        ];
        foreach ($annotationSubtypes as $subtype => $class) {
            self::registerSubtype('Annot', $subtype, $class);
        }

        // /Type /Font — font subtypes
        self::registerSubtype('Font', 'Type1', \ApprLabs\Pdf\Core\Font\Type1Font::class);
        self::registerSubtype('Font', 'TrueType', \ApprLabs\Pdf\Core\Font\TrueTypeFont::class);
        self::registerSubtype('Font', 'Type0', \ApprLabs\Pdf\Core\Font\Type0Font::class);
        self::registerSubtype('Font', 'Type3', \ApprLabs\Pdf\Core\Font\Type3Font::class);
        self::registerSubtype('Font', 'MMType1', \ApprLabs\Pdf\Core\Font\MMType1Font::class);
        self::registerSubtype('Font', 'CIDFontType0', \ApprLabs\Pdf\Core\Font\CIDFontType0Font::class);
        self::registerSubtype('Font', 'CIDFontType2', \ApprLabs\Pdf\Core\Font\CIDFontType2Font::class);

        // /Type /XObject
        self::registerSubtype('XObject', 'Image', \ApprLabs\Pdf\Core\Graphics\XObject\ImageXObject::class);
        self::registerSubtype('XObject', 'Form', \ApprLabs\Pdf\Core\Graphics\XObject\FormXObject::class);
        self::registerSubtype('XObject', 'PS', \ApprLabs\Pdf\Core\Graphics\XObject\PostScriptXObject::class);

        // /Type /Rendition
        self::registerSubtype('Rendition', 'MR', \ApprLabs\Pdf\Core\Multimedia\MediaRendition::class);
        self::registerSubtype('Rendition', 'SR', \ApprLabs\Pdf\Core\Multimedia\SelectorRendition::class);

        // /Type /MediaClip
        self::registerSubtype('MediaClip', 'MCD', \ApprLabs\Pdf\Core\Multimedia\MediaClipData::class);
        self::registerSubtype('MediaClip', 'MCS', \ApprLabs\Pdf\Core\Multimedia\MediaClipSection::class);

        // ---------------------------------------------------------------
        // Actions — dispatched by /S (not /Type + /Subtype)
        // ---------------------------------------------------------------
        $actionTypes = [
            'GoTo'              => \ApprLabs\Pdf\Core\Action\GoToAction::class,
            'GoToR'             => \ApprLabs\Pdf\Core\Action\GoToRAction::class,
            'GoToE'             => \ApprLabs\Pdf\Core\Action\GoToEAction::class,
            'GoToDP'            => \ApprLabs\Pdf\Core\Action\GoToDPAction::class,
            'GoTo3DView'        => \ApprLabs\Pdf\Core\Action\GoTo3DViewAction::class,
            'Launch'            => \ApprLabs\Pdf\Core\Action\LaunchAction::class,
            'Thread'            => \ApprLabs\Pdf\Core\Action\ThreadAction::class,
            'URI'               => \ApprLabs\Pdf\Core\Action\URIAction::class,
            'Sound'             => \ApprLabs\Pdf\Core\Action\SoundAction::class,
            'Movie'             => \ApprLabs\Pdf\Core\Action\MovieAction::class,
            'Hide'              => \ApprLabs\Pdf\Core\Action\HideAction::class,
            'Named'             => \ApprLabs\Pdf\Core\Action\NamedAction::class,
            'SubmitForm'        => \ApprLabs\Pdf\Core\Action\SubmitFormAction::class,
            'ResetForm'         => \ApprLabs\Pdf\Core\Action\ResetFormAction::class,
            'ImportData'        => \ApprLabs\Pdf\Core\Action\ImportDataAction::class,
            'SetOCGState'       => \ApprLabs\Pdf\Core\Action\SetOCGStateAction::class,
            'Rendition'         => \ApprLabs\Pdf\Core\Action\RenditionAction::class,
            'Trans'             => \ApprLabs\Pdf\Core\Action\TransAction::class,
            'JavaScript'        => \ApprLabs\Pdf\Core\Action\JavaScriptAction::class,
            'RichMediaExecute'  => \ApprLabs\Pdf\Core\Action\RichMediaExecuteAction::class,
        ];
        foreach ($actionTypes as $sValue => $class) {
            self::registerActionType($sValue, $class);
        }

        // DSS (Document Security Store — PDF 2.0)
        self::registerType('DSS', \ApprLabs\Pdf\Core\Document\DSS::class);
    }
}
