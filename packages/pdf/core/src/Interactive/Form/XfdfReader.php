<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Form;

/**
 * XFDF (XML Forms Data Format) reader — ISO 32000-2 §12.7.8.
 *
 * Parses a standalone .xfdf XML file and extracts field name/value pairs.
 */
final class XfdfReader
{
    /**
     * Parse XFDF content and return a field name → value map.
     *
     * @return array<string, string>
     */
    public static function parse(string $xfdfContent): array
    {
        $fields = [];

        $xml = @simplexml_load_string($xfdfContent);
        if ($xml === false) {
            return $fields;
        }

        // Register the XFDF namespace
        $xml->registerXPathNamespace('x', 'http://ns.adobe.com/xfdf/');

        // Try with namespace
        $fieldNodes = $xml->xpath('//x:field');
        if ($fieldNodes === false || $fieldNodes === []) {
            // Try without namespace (some XFDF files omit it)
            $fieldNodes = $xml->xpath('//field');
        }

        if ($fieldNodes !== false) {
            foreach ($fieldNodes as $field) {
                $name = (string) $field['name'];
                $value = (string) ($field->value ?? '');
                if ($name !== '') {
                    $fields[$name] = $value;
                }
            }
        }

        return $fields;
    }
}
