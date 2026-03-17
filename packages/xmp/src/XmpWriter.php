<?php declare(strict_types=1);
namespace Phpdftk\Xmp;

final class XmpWriter {
    private const NAMESPACE_PREFIXES = [
        'dc'   => 'http://purl.org/dc/elements/1.1/',
        'xmp'  => 'http://ns.adobe.com/xap/1.0/',
        'pdf'  => 'http://ns.adobe.com/pdf/1.3/',
        'xmpMM' => 'http://ns.adobe.com/xap/1.0/mm/',
        'stEvt' => 'http://ns.adobe.com/xap/1.0/sType/ResourceEvent#',
    ];

    public function serialize(XmpPacket $packet): string {
        $properties = $packet->all();

        // Group properties by namespace prefix
        $grouped = [];
        $usedPrefixes = [];
        foreach ($properties as $key => $value) {
            if (str_contains($key, ':')) {
                [$prefix, $localName] = explode(':', $key, 2);
                $grouped[$prefix][$localName] = $value;
                $usedPrefixes[$prefix] = true;
            } else {
                $grouped['_unqualified'][$key] = $value;
            }
        }

        // Build namespace declarations for used prefixes
        $nsDecls = '';
        foreach (self::NAMESPACE_PREFIXES as $prefix => $uri) {
            if (isset($usedPrefixes[$prefix])) {
                $nsDecls .= "\n      xmlns:{$prefix}=\"" . htmlspecialchars($uri, ENT_XML1) . '"';
            }
        }

        // Build property elements
        $propsXml = '';
        foreach ($grouped as $prefix => $props) {
            if ($prefix === '_unqualified') continue;
            foreach ($props as $localName => $value) {
                $escaped = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $propsXml .= "      <{$prefix}:{$localName}>{$escaped}</{$prefix}:{$localName}>\n";
            }
        }
        // Unqualified properties
        if (isset($grouped['_unqualified'])) {
            foreach ($grouped['_unqualified'] as $key => $value) {
                $escaped = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $propsXml .= "      <{$key}>{$escaped}</{$key}>\n";
            }
        }

        $bom = "\xEF\xBB\xBF"; // UTF-8 BOM (the ﻿ character)

        return '<?xpacket begin="' . $bom . '" id="W5M0MpCehiHzreSzNTczkc9d"?>' . "\n"
            . '<x:xmpmeta xmlns:x="adobe:ns:meta/">' . "\n"
            . '  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">' . "\n"
            . '    <rdf:Description rdf:about=""' . $nsDecls . ">\n"
            . $propsXml
            . "    </rdf:Description>\n"
            . "  </rdf:RDF>\n"
            . "</x:xmpmeta>\n"
            . '<?xpacket end="w"?>';
    }
}
