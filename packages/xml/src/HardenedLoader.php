<?php

declare(strict_types=1);

namespace Phpdftk\Xml;

use Phpdftk\Xml\Exception\InvalidXmlException;

/**
 * Single source of truth for safe libxml parsing.
 *
 * Every typed-tree parser in the codebase ({@see \Phpdftk\Svg\Parser},
 * {@see \Phpdftk\Mathml\Parser}, …) routes through this class so the
 * security posture cannot drift between consumers. A CVE that affects
 * libxml is one update here, applied everywhere.
 *
 * Security posture (kept intentionally narrow):
 *
 *  - **External entities NOT substituted.** `LIBXML_NOENT` is the
 *    libxml flag that asks for entity substitution; we deliberately
 *    omit it. A document with
 *    `<!ENTITY x SYSTEM "file:///etc/passwd">` has the entity
 *    reference preserved verbatim in the resulting DOM — it never
 *    resolves, so the file's contents never enter the tree.
 *  - **No network fetches.** `LIBXML_NONET` forbids libxml from
 *    fetching any URL for DTD lookups, entity resolution, etc.
 *  - **XInclude rejected.** This class never calls
 *    `DOMDocument::xinclude()`. A document containing `<xi:include/>`
 *    keeps the include element in the tree as a literal foreign
 *    element; the consumer's typed walker treats it as unknown.
 *  - **Errors silent at libxml.** `LIBXML_NOERROR | LIBXML_NOWARNING`
 *    suppresses libxml's default `trigger_error()` output. We check
 *    the return value ourselves and surface the first error as the
 *    {@see InvalidXmlException} message.
 */
final class HardenedLoader
{
    /**
     * Parse `$xml` into a `\DOMDocument` with the security flags
     * described in this class's docblock. Throws
     * {@see InvalidXmlException} on empty input, parse failure, or
     * any libxml error; the message includes the first reported
     * libxml diagnostic when available.
     */
    public function load(string $xml): \DOMDocument
    {
        if (trim($xml) === '') {
            throw new InvalidXmlException('Cannot parse an empty XML document.');
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = true;
            $dom->resolveExternals = false;
            $dom->substituteEntities = false;
            $loaded = $dom->loadXML(
                $xml,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
            if (!$loaded) {
                $errors = libxml_get_errors();
                $first = $errors[0] ?? null;
                throw new InvalidXmlException(
                    $first === null
                        ? 'Failed to parse XML.'
                        : sprintf('Failed to parse XML: %s', trim($first->message)),
                );
            }
            return $dom;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }
    }
}
