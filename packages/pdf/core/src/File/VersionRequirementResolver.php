<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\File;

use ApprLabs\Pdf\Core\DeprecatedPdfFeature;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\PdfVersionAware;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Reads {@see RequiresPdfVersion} and {@see DeprecatedPdfFeature} attributes
 * from PDF object classes via reflection, with static per-class caching.
 */
final class VersionRequirementResolver
{
    /** @var array<class-string, PdfVersion|null> */
    private static array $classCache = [];

    /** @var array<class-string, array<string, PdfVersion>> */
    private static array $propertyCache = [];

    /** @var array<class-string, DeprecatedPdfFeature|null> */
    private static array $deprecationCache = [];

    /**
     * Get the class-level minimum PDF version requirement, if any.
     */
    public static function getClassRequirement(string|object $class): ?PdfVersion
    {
        $className = is_object($class) ? $class::class : $class;

        if (!array_key_exists($className, self::$classCache)) {
            $version = null;
            $ref = new \ReflectionClass($className);
            // Walk the class hierarchy to find inherited attributes
            while ($ref !== false) {
                $attrs = $ref->getAttributes(RequiresPdfVersion::class);
                if ($attrs !== []) {
                    $found = $attrs[0]->newInstance()->minimumVersion;
                    $version = $version !== null ? $version->max($found) : $found;
                }
                $ref = $ref->getParentClass();
            }
            self::$classCache[$className] = $version;
        }

        return self::$classCache[$className];
    }

    /**
     * Get the effective minimum version for an object instance, considering
     * both the class-level requirement and all non-null property-level
     * requirements. Returns PdfVersion::V1_0 if nothing is annotated.
     */
    public static function getEffectiveRequirement(object $object): PdfVersion
    {
        $version = self::getClassRequirement($object) ?? PdfVersion::V1_0;

        // Check PdfVersionAware interface for runtime version requirements
        if ($object instanceof PdfVersionAware) {
            $runtimeVersion = $object->getMinimumPdfVersion();
            if ($runtimeVersion !== null) {
                $version = $version->max($runtimeVersion);
            }
        }

        $className = $object::class;
        if (!isset(self::$propertyCache[$className])) {
            self::$propertyCache[$className] = [];
            $ref = new \ReflectionClass($className);
            foreach ($ref->getProperties() as $prop) {
                $attrs = $prop->getAttributes(RequiresPdfVersion::class);
                if ($attrs !== []) {
                    self::$propertyCache[$className][$prop->getName()] =
                        $attrs[0]->newInstance()->minimumVersion;
                }
            }
        }

        foreach (self::$propertyCache[$className] as $propName => $propVersion) {
            $value = $object->$propName ?? null;
            if ($value !== null) {
                $version = $version->max($propVersion);
            }
        }

        return $version;
    }

    /**
     * Check if a class is marked as deprecated in the PDF specification.
     */
    public static function getDeprecation(string|object $class): ?DeprecatedPdfFeature
    {
        $className = is_object($class) ? $class::class : $class;

        if (!array_key_exists($className, self::$deprecationCache)) {
            $found = null;
            $ref = new \ReflectionClass($className);
            while ($ref !== false) {
                $attrs = $ref->getAttributes(DeprecatedPdfFeature::class);
                if ($attrs !== []) {
                    $found = $attrs[0]->newInstance();
                    break;
                }
                $ref = $ref->getParentClass();
            }
            self::$deprecationCache[$className] = $found;
        }

        return self::$deprecationCache[$className];
    }

    /**
     * Clear all caches (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$classCache = [];
        self::$propertyCache = [];
        self::$deprecationCache = [];
    }
}
