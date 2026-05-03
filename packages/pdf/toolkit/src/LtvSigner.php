<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit;

use Phpdftk\Pdf\Core\Document\DssBuilder;
use Phpdftk\Pdf\Core\File\IncrementalWriter;
use Phpdftk\Pdf\Core\Interactive\Signature\CertificateUtils;
use Phpdftk\Pdf\Core\Interactive\Signature\CrlClient;
use Phpdftk\Pdf\Core\Interactive\Signature\OcspClient;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Reader\PdfReader;

/**
 * Add LTV (Long-Term Validation) data to signed PDFs — PAdES B-LT profile.
 *
 * Extracts certificates from existing signatures, fetches OCSP responses
 * and CRLs, and embeds them in a DSS (Document Security Store) via an
 * incremental update that preserves the original signatures.
 *
 * Supports both online fetching (via OcspClient/CrlClient) and offline
 * mode (pre-loaded OCSP/CRL data for testing).
 *
 * Usage:
 *   LtvSigner::openString($signedPdfBytes)
 *       ->setOcspClient(new OcspClient())
 *       ->setCrlClient(new CrlClient())
 *       ->save('ltv-enabled.pdf');
 *
 *   // Offline / testing:
 *   LtvSigner::openString($signedPdfBytes)
 *       ->addOcspResponse($derOcspBytes)
 *       ->addCertificate($derCaCert)
 *       ->save('ltv-enabled.pdf');
 *
 * @api
 */
final class LtvSigner
{
    private string $originalBytes;

    private ?OcspClient $ocspClient = null;
    private ?CrlClient $crlClient = null;

    /** @var list<string> Pre-loaded DER OCSP responses */
    private array $preloadedOcsps = [];

    /** @var list<string> Pre-loaded DER CRLs */
    private array $preloadedCrls = [];

    /** @var list<string> Extra DER certificates to include */
    private array $extraCerts = [];

    /** @var list<string> Target specific signature field names (empty = all) */
    private array $targetSignatures = [];

    /** @var list<string> */
    private array $lastVersionWarnings = [];

    /** @var list<string> Non-fatal warnings (OCSP/CRL fetch failures) */
    private array $warnings = [];

    private function __construct(
        private readonly PdfReader $reader,
        string $originalBytes,
    ) {
        $this->originalBytes = $originalBytes;
    }

    public static function open(string $path, string $password = ''): self
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException("Cannot read file: $path");
        }
        return new self(PdfReader::fromString($bytes, $password), $bytes);
    }

    public static function openString(string $pdfBytes, string $password = ''): self
    {
        return new self(PdfReader::fromString($pdfBytes, $password), $pdfBytes);
    }

    // -----------------------------------------------------------------------
    // Configuration (fluent)
    // -----------------------------------------------------------------------

    /**
     * Set the OCSP client for online revocation checking.
     */
    public function setOcspClient(OcspClient $client): self
    {
        $this->ocspClient = $client;
        return $this;
    }

    /**
     * Set the CRL client for online revocation checking.
     */
    public function setCrlClient(CrlClient $client): self
    {
        $this->crlClient = $client;
        return $this;
    }

    /**
     * Pre-load a DER-encoded OCSP response (for offline/testing use).
     */
    public function addOcspResponse(string $derOcsp): self
    {
        $this->preloadedOcsps[] = $derOcsp;
        return $this;
    }

    /**
     * Pre-load a DER-encoded CRL (for offline/testing use).
     */
    public function addCrl(string $derCrl): self
    {
        $this->preloadedCrls[] = $derCrl;
        return $this;
    }

    /**
     * Add an extra DER-encoded certificate to include in the DSS.
     */
    public function addCertificate(string $derCert): self
    {
        $this->extraCerts[] = $derCert;
        return $this;
    }

    /**
     * Target a specific signature field by name. Can be called multiple times.
     * If never called, all signatures are processed.
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function forSignature(string $fieldName): self
    {
        $this->targetSignatures[] = $fieldName;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Output
    // -----------------------------------------------------------------------

    public function save(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $this->toBytes());
    }

    public function toBytes(): string
    {
        $this->warnings = [];

        // Discover signatures in the PDF
        $signatures = $this->discoverSignatures();

        if (empty($signatures)) {
            return $this->originalBytes;
        }

        // Filter by target if specified
        if (!empty($this->targetSignatures)) {
            $filtered = [];
            foreach ($this->targetSignatures as $name) {
                if (!isset($signatures[$name])) {
                    throw new \InvalidArgumentException("Signature field not found: $name");
                }
                $filtered[$name] = $signatures[$name];
            }
            $signatures = $filtered;
        }

        $writer = IncrementalWriter::fromReader($this->reader, $this->originalBytes);
        $builder = new DssBuilder($writer);

        // Add extra certificates
        foreach ($this->extraCerts as $cert) {
            $builder->addCertificate($cert);
        }

        // Add pre-loaded OCSP responses to DSS global pool
        $preloadedOcspRefs = [];
        foreach ($this->preloadedOcsps as $ocsp) {
            $preloadedOcspRefs[] = $builder->addOcspResponse($ocsp);
        }

        // Add pre-loaded CRLs to DSS global pool
        $preloadedCrlRefs = [];
        foreach ($this->preloadedCrls as $crl) {
            $preloadedCrlRefs[] = $builder->addCrl($crl);
        }

        // Process each signature
        foreach ($signatures as $name => $sigData) {
            $this->processSignature($name, $sigData, $builder, $preloadedOcspRefs, $preloadedCrlRefs);
        }

        // Build DSS and register with writer
        $dss = $builder->build();
        $dssRef = $writer->addNewObject($dss);

        // Update Catalog to include /DSS
        $this->updateCatalog($writer, $dssRef);

        $result = $writer->generate();
        $this->lastVersionWarnings = $writer->getVersionWarnings();
        return $result;
    }

    // -----------------------------------------------------------------------
    // Escape hatches
    // -----------------------------------------------------------------------

    /** @return list<string> */
    public function getVersionWarnings(): array
    {
        return $this->lastVersionWarnings;
    }

    /**
     * Get non-fatal warnings from OCSP/CRL fetching.
     *
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getReader(): PdfReader
    {
        return $this->reader;
    }

    public function getPageCount(): int
    {
        return $this->reader->getPageCount();
    }

    // -----------------------------------------------------------------------
    // Internal: signature discovery
    // -----------------------------------------------------------------------

    /**
     * Discover all signature fields in the PDF.
     *
     * @return array<string, array{contentsRaw: string}>
     *         Field name => signature data
     */
    private function discoverSignatures(): array
    {
        $trailer = $this->reader->getTrailer();
        $rootRef = $trailer->get('Root');
        if (!$rootRef instanceof PdfReference) {
            return [];
        }

        $catalog = $this->reader->resolveReference($rootRef);
        if (!$catalog instanceof PdfDictionary) {
            return [];
        }

        $acroFormVal = $catalog->get('AcroForm');
        $acroForm = $this->resolve($acroFormVal);
        if (!$acroForm instanceof PdfDictionary) {
            return [];
        }

        $fieldsVal = $acroForm->get('Fields');
        $fieldsArray = $this->resolve($fieldsVal);
        if (!$fieldsArray instanceof PdfArray) {
            return [];
        }

        $signatures = [];
        foreach ($fieldsArray->items as $fieldRef) {
            $this->walkFieldForSignatures($fieldRef, '', $signatures);
        }

        return $signatures;
    }

    /**
     * Recursively walk fields looking for signature fields with values.
     *
     * @param array<string, array{contentsHex: string, contentsRaw: string}> $signatures
     */
    private function walkFieldForSignatures(
        mixed $fieldRefOrDict,
        string $parentName,
        array &$signatures,
    ): void {
        $fieldDict = $this->resolve($fieldRefOrDict);
        if (!$fieldDict instanceof PdfDictionary) {
            return;
        }

        // Build fully-qualified name
        $partialName = '';
        $tVal = $fieldDict->get('T');
        if ($tVal instanceof PdfString) {
            $partialName = $tVal->value;
        }

        $fullName = $parentName !== '' && $partialName !== ''
            ? $parentName . '.' . $partialName
            : ($partialName !== '' ? $partialName : $parentName);

        // Check if this is a signature field with a value
        $ft = $fieldDict->get('FT');
        if ($ft instanceof PdfName && $ft->value === 'Sig') {
            $vRef = $fieldDict->get('V');
            $sigValue = $this->resolve($vRef);
            if ($sigValue instanceof PdfDictionary) {
                $contents = $sigValue->get('Contents');
                if ($contents instanceof PdfString && $contents->value !== '') {
                    // PdfString with hex=true: value is already raw binary bytes
                    // PdfString with hex=false: value is the literal string
                    $signatures[$fullName] = [
                        'contentsRaw' => $contents->value,
                    ];
                }
            }
        }

        // Recurse into /Kids
        $kidsVal = $fieldDict->get('Kids');
        $kids = $this->resolve($kidsVal);
        if ($kids instanceof PdfArray) {
            foreach ($kids->items as $kidRef) {
                $this->walkFieldForSignatures($kidRef, $fullName, $signatures);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Internal: signature processing
    // -----------------------------------------------------------------------

    /**
     * Process a single signature: extract certs, fetch revocation data, add to DSS.
     *
     * @param array{contentsRaw: string} $sigData
     * @param list<PdfReference> $preloadedOcspRefs
     * @param list<PdfReference> $preloadedCrlRefs
     */
    private function processSignature(
        string $name,
        array $sigData,
        DssBuilder $builder,
        array $preloadedOcspRefs,
        array $preloadedCrlRefs,
    ): void {
        $rawDer = $sigData['contentsRaw'];

        // Strip trailing zero padding from the signature placeholder
        $rawDer = rtrim($rawDer, "\x00");

        if ($rawDer === '') {
            $this->warnings[] = "Signature '$name': empty /Contents value, skipping";
            return;
        }

        // Extract certificates from PKCS#7
        $certsDer = [];
        try {
            $certsDer = CertificateUtils::extractCertsFromPkcs7Der($rawDer);
        } catch (\RuntimeException $e) {
            $this->warnings[] = "Signature '$name': failed to extract certificates: {$e->getMessage()}";
            return;
        }

        // Order as chain (leaf → root)
        $certsDer = CertificateUtils::buildChain($certsDer);

        // Add all certificates to DSS
        $certRefs = [];
        foreach ($certsDer as $certDer) {
            $certRefs[] = $builder->addCertificate($certDer);
        }

        // Fetch OCSP responses for each cert (except root/self-signed)
        $ocspRefs = $preloadedOcspRefs;
        for ($i = 0; $i < count($certsDer) - 1; $i++) {
            $cert = $certsDer[$i];
            $issuer = $certsDer[$i + 1] ?? $certsDer[$i];

            if ($this->ocspClient !== null) {
                try {
                    $ocspDer = $this->ocspClient->getOcspResponse($cert, $issuer);
                    $ocspRefs[] = $builder->addOcspResponse($ocspDer);
                } catch (\RuntimeException $e) {
                    $this->warnings[] = "Signature '$name': OCSP fetch failed for cert $i: {$e->getMessage()}";
                }
            }
        }

        // Fetch CRLs for each cert (except root)
        $crlRefs = $preloadedCrlRefs;
        for ($i = 0; $i < count($certsDer) - 1; $i++) {
            $cert = $certsDer[$i];

            if ($this->crlClient !== null) {
                try {
                    $crlDer = $this->crlClient->getCrl($cert);
                    $crlRefs[] = $builder->addCrl($crlDer);
                } catch (\RuntimeException $e) {
                    $this->warnings[] = "Signature '$name': CRL fetch failed for cert $i: {$e->getMessage()}";
                }
            }
        }

        // Add VRI entry
        $vriKey = DssBuilder::computeVriKey($rawDer);
        $builder->addVriEntry($vriKey, $certRefs, $ocspRefs, $crlRefs);
    }

    // -----------------------------------------------------------------------
    // Internal: catalog update
    // -----------------------------------------------------------------------

    /**
     * Update the Catalog to include the /DSS reference via incremental update.
     */
    private function updateCatalog(IncrementalWriter $writer, PdfReference $dssRef): void
    {
        $trailer = $this->reader->getTrailer();
        $rootRef = $trailer->get('Root');
        if (!$rootRef instanceof PdfReference) {
            throw new \RuntimeException('Cannot find /Root reference in trailer');
        }

        $catalogDict = $this->reader->resolveReference($rootRef);
        if (!$catalogDict instanceof PdfDictionary) {
            throw new \RuntimeException('Cannot resolve Catalog dictionary');
        }

        // Clone the catalog dictionary and add /DSS
        $modifiedDict = new PdfDictionary($catalogDict->entries);
        $modifiedDict->set('DSS', $dssRef);

        // If version needs bumping to 2.0 for DSS, set /Version
        if ($writer->wasVersionBumped()) {
            $modifiedDict->set('Version', new PdfName($writer->getPdfVersion()->value));
        }

        $wrapper = new class ($modifiedDict) extends PdfObject {
            public function __construct(private readonly PdfDictionary $dict) {}
            public function toPdf(): string { return $this->dict->toPdf(); }
        };
        $wrapper->objectNumber = $rootRef->objectNumber;
        $wrapper->generationNumber = 0;

        $writer->addModifiedObject($wrapper);
    }

    /**
     * Resolve a value that might be a PdfReference.
     */
    private function resolve(mixed $val): mixed
    {
        if ($val instanceof PdfReference) {
            return $this->reader->resolveReference($val);
        }
        return $val;
    }
}
