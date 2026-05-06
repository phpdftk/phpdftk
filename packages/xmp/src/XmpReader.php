<?php

declare(strict_types=1);

namespace Phpdftk\Xmp;

/**
 * Parse XMP metadata packets from XML into {@see XmpPacket}.
 *
 * Strips `<?xpacket?>` processing instructions and extracts
 * namespaced properties from the RDF structure. Used by the reader
 * and conformance checker to inspect PDF/A identification metadata.
 */
final class XmpReader
{
    public function parse(string $xml): XmpPacket
    {
        // Strip xpacket processing instructions if present
        $xmlContent = preg_replace('/<\?xpacket[^?]*\?>/s', '', $xml);
        $xmlContent = trim($xmlContent);

        if (empty($xmlContent)) {
            return XmpPacket::create();
        }

        libxml_use_internal_errors(true);
        $sxe = simplexml_load_string($xmlContent);
        libxml_use_internal_errors(false);

        if ($sxe === false) {
            return XmpPacket::create();
        }

        $packet = XmpPacket::create();

        $rdfNs = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

        // Navigate: x:xmpmeta → rdf:RDF → rdf:Description
        // The root is x:xmpmeta, its child (in rdf ns) is RDF
        $xRdfChildren = $sxe->children($rdfNs);
        if (!isset($xRdfChildren->RDF)) {
            return $packet;
        }

        $rdfRDF = $xRdfChildren->RDF;
        $descriptions = $rdfRDF->children($rdfNs);

        foreach ($descriptions as $descName => $desc) {
            if ($descName !== 'Description') {
                continue;
            }

            // Get all namespaces defined at this level and below
            $namespaces = $desc->getNamespaces(true);

            foreach ($namespaces as $prefix => $uri) {
                if ($prefix === '' || $prefix === 'rdf' || $prefix === 'x') {
                    continue;
                }

                // Check child elements in this namespace
                foreach ($desc->children($uri) as $localName => $child) {
                    $key = $prefix . ':' . $localName;
                    $packet = $packet->set($key, (string) $child);
                }

                // Check attributes in this namespace
                $attrs = $desc->attributes($uri);
                if ($attrs !== null) {
                    foreach ($attrs as $attrName => $attrValue) {
                        $key = $prefix . ':' . $attrName;
                        $packet = $packet->set($key, (string) $attrValue);
                    }
                }
            }
        }

        return $packet;
    }
}
