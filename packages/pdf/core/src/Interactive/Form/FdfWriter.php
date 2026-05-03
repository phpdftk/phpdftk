<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Form;

/**
 * FDF (Forms Data Format) file writer — ISO 32000-2 §12.7.8.
 *
 * Generates a standalone .fdf file containing field name/value pairs
 * that can be used to fill a PDF form.
 */
final class FdfWriter
{
    /**
     * Generate FDF file content from a field name → value map.
     *
     * @param array<string, string> $fields Field name => value
     * @param string|null $pdfPath Optional /F entry pointing to the PDF file
     */
    public static function generate(array $fields, ?string $pdfPath = null): string
    {
        $chunks = [];
        $chunks[] = "%FDF-1.2\n";

        // Build the /Fields array
        $fieldsArray = '';
        foreach ($fields as $name => $value) {
            $fieldsArray .= '<< /T ' . self::escapeString($name)
                . ' /V ' . self::escapeString($value) . " >>\n";
        }

        // Build /FDF dictionary
        $fdfDict = "<< /Fields [\n" . $fieldsArray . "]\n";
        if ($pdfPath !== null) {
            $fdfDict .= '/F ' . self::escapeString($pdfPath) . "\n";
        }
        $fdfDict .= ">>";

        // Catalog
        $chunks[] = "1 0 obj\n<< /FDF " . $fdfDict . " >>\nendobj\n";

        // Trailer
        $chunks[] = "trailer\n<< /Root 1 0 R >>\n";
        $chunks[] = '%%EOF';

        return implode('', $chunks);
    }

    private static function escapeString(string $value): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
        return '(' . $escaped . ')';
    }
}
