<?php declare(strict_types=1);
namespace ApprLabs\Xmp;

final class XmpWriter {
    private const DEFAULT_NAMESPACES = [
        'dc'   => 'http://purl.org/dc/elements/1.1/',
        'xmp'  => 'http://ns.adobe.com/xap/1.0/',
        'pdf'  => 'http://ns.adobe.com/pdf/1.3/',
        'xmpMM' => 'http://ns.adobe.com/xap/1.0/mm/',
        'stEvt' => 'http://ns.adobe.com/xap/1.0/sType/ResourceEvent#',
    ];

    /** @var array<string, string> */
    private array $namespaces;

    /** @param array<string, string> $additionalNamespaces prefix => URI */
    public function __construct(array $additionalNamespaces = []) {
        $this->namespaces = array_merge(self::DEFAULT_NAMESPACES, $additionalNamespaces);
    }

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
        foreach ($this->namespaces as $prefix => $uri) {
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
