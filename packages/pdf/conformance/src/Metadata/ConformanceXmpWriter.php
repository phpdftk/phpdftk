<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Metadata;

use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;

/**
 * Builds XMP metadata with the conformance identification schema.
 *
 * PDF/A requires specific RDF types (rdf:Alt for dc:title, rdf:Seq
 * for dc:creator) that the generic XmpWriter does not produce. This
 * class builds the XMP packet directly, following the pattern used
 * in the existing PdfAConformanceTest.
 */
final class ConformanceXmpWriter
{
    /**
     * Build a complete XMP packet with conformance identification.
     *
     * @param ConformanceProfile $profile  The target conformance profile
     * @param string             $title    Document title (for dc:title)
     * @param string             $creator  Document creator/author (for dc:creator)
     * @param string             $producer PDF producer string (for pdf:Producer)
     */
    public function buildXmp(
        ConformanceProfile $profile,
        string $title = '',
        string $creator = '',
        string $producer = '',
    ): string {
        $bom = "\xEF\xBB\xBF";
        $titleEsc = htmlspecialchars($title, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $creatorEsc = htmlspecialchars($creator, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $producerEsc = htmlspecialchars($producer, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        $nsUri = htmlspecialchars($profile->getXmpNamespace(), ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $prefix = $profile->getXmpPrefix();

        // Build identification properties
        $identLines = '';
        foreach ($profile->getXmpProperties() as $localName => $value) {
            $valueEsc = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $identLines .= "              <{$prefix}:{$localName}>{$valueEsc}</{$prefix}:{$localName}>\n";
        }

        return <<<XML
        <?xpacket begin="{$bom}" id="W5M0MpCehiHzreSzNTczkc9d"?>
        <x:xmpmeta xmlns:x="adobe:ns:meta/">
          <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
            <rdf:Description rdf:about=""
              xmlns:dc="http://purl.org/dc/elements/1.1/"
              xmlns:pdf="http://ns.adobe.com/pdf/1.3/"
              xmlns:{$prefix}="{$nsUri}">
              <dc:title>
                <rdf:Alt>
                  <rdf:li xml:lang="x-default">{$titleEsc}</rdf:li>
                </rdf:Alt>
              </dc:title>
              <dc:creator>
                <rdf:Seq>
                  <rdf:li>{$creatorEsc}</rdf:li>
                </rdf:Seq>
              </dc:creator>
              <pdf:Producer>{$producerEsc}</pdf:Producer>
        {$identLines}            </rdf:Description>
          </rdf:RDF>
        </x:xmpmeta>
        <?xpacket end="w"?>
        XML;
    }
}
