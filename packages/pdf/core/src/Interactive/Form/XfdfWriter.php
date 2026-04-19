<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Form;

/**
 * XFDF (XML Forms Data Format) writer — ISO 32000-2 §12.7.8.
 *
 * Generates a standalone .xfdf XML file containing field name/value pairs.
 */
final class XfdfWriter
{
    /**
     * Generate XFDF content from a field name → value map.
     *
     * @param array<string, string> $fields Field name => value
     * @param string|null $pdfPath Optional href attribute pointing to the PDF
     */
    public static function generate(array $fields, ?string $pdfPath = null): string
    {
        $href = $pdfPath !== null ? ' href="' . htmlspecialchars($pdfPath, ENT_XML1) . '"' : '';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<xfdf xmlns="http://ns.adobe.com/xfdf/"' . $href . ">\n";
        $xml .= "  <fields>\n";

        foreach ($fields as $name => $value) {
            $xml .= '    <field name="' . htmlspecialchars($name, ENT_XML1) . "\">\n";
            $xml .= '      <value>' . htmlspecialchars($value, ENT_XML1) . "</value>\n";
            $xml .= "    </field>\n";
        }

        $xml .= "  </fields>\n";
        $xml .= "</xfdf>\n";

        return $xml;
    }
}
