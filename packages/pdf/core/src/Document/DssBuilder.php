<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\File\IncrementalWriter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;

/**
 * Builder for the Document Security Store (DSS) — ISO 32000-2 §12.8.4.3.
 *
 * Registers certificate, OCSP response, and CRL streams as indirect objects
 * via an {@see IncrementalWriter}, builds VRI (Validation Related Information)
 * entries keyed by signature hash, and produces a populated {@see DSS}.
 *
 * Deduplicates identical data by SHA-256 hash.
 */
final class DssBuilder
{
    /** @var array<string, PdfReference> sha256(cert) => reference */
    private array $certRefs = [];

    /** @var array<string, PdfReference> sha256(ocsp) => reference */
    private array $ocspRefs = [];

    /** @var array<string, PdfReference> sha256(crl) => reference */
    private array $crlRefs = [];

    /** @var array<string, array{certs: list<PdfReference>, ocsps: list<PdfReference>, crls: list<PdfReference>}> */
    private array $vriEntries = [];

    public function __construct(
        private readonly IncrementalWriter $writer,
    ) {}

    /**
     * Add a DER-encoded certificate to the DSS.
     *
     * Creates a PdfStream indirect object and registers it with the writer.
     * Deduplicates: identical certificates (by SHA-256) return the same reference.
     */
    public function addCertificate(string $derCert): PdfReference
    {
        $hash = hash('sha256', $derCert);
        if (isset($this->certRefs[$hash])) {
            return $this->certRefs[$hash];
        }

        $stream = $this->createStream($derCert);
        $ref = $this->writer->addNewObject($stream);
        $this->certRefs[$hash] = $ref;
        return $ref;
    }

    /**
     * Add a DER-encoded OCSP response to the DSS.
     */
    public function addOcspResponse(string $derOcsp): PdfReference
    {
        $hash = hash('sha256', $derOcsp);
        if (isset($this->ocspRefs[$hash])) {
            return $this->ocspRefs[$hash];
        }

        $stream = $this->createStream($derOcsp);
        $ref = $this->writer->addNewObject($stream);
        $this->ocspRefs[$hash] = $ref;
        return $ref;
    }

    /**
     * Add a DER-encoded CRL to the DSS.
     */
    public function addCrl(string $derCrl): PdfReference
    {
        $hash = hash('sha256', $derCrl);
        if (isset($this->crlRefs[$hash])) {
            return $this->crlRefs[$hash];
        }

        $stream = $this->createStream($derCrl);
        $ref = $this->writer->addNewObject($stream);
        $this->crlRefs[$hash] = $ref;
        return $ref;
    }

    /**
     * Add a VRI (Validation Related Information) entry for a signature.
     *
     * @param string $sigContentsHash Uppercase hex SHA-256 of the raw signature
     *                                 bytes (hex-decoded /Contents value)
     * @param list<PdfReference> $certRefs References to certificate streams
     * @param list<PdfReference> $ocspRefs References to OCSP response streams
     * @param list<PdfReference> $crlRefs  References to CRL streams
     */
    public function addVriEntry(
        string $sigContentsHash,
        array $certRefs = [],
        array $ocspRefs = [],
        array $crlRefs = [],
    ): void {
        $this->vriEntries[$sigContentsHash] = [
            'certs' => $certRefs,
            'ocsps' => $ocspRefs,
            'crls' => $crlRefs,
        ];
    }

    /**
     * Build the populated DSS object and register it with the writer.
     */
    public function build(): DSS
    {
        $dss = new DSS();

        // Global certificate list
        $allCerts = array_values($this->certRefs);
        if (!empty($allCerts)) {
            $dss->certs = new PdfArray($allCerts);
        }

        // Global OCSP list
        $allOcsps = array_values($this->ocspRefs);
        if (!empty($allOcsps)) {
            $dss->ocsps = new PdfArray($allOcsps);
        }

        // Global CRL list
        $allCrls = array_values($this->crlRefs);
        if (!empty($allCrls)) {
            $dss->crls = new PdfArray($allCrls);
        }

        // VRI dictionary
        if (!empty($this->vriEntries)) {
            $vriDict = new PdfDictionary();
            foreach ($this->vriEntries as $hash => $entry) {
                $vriEntry = new PdfDictionary();
                if (!empty($entry['certs'])) {
                    $vriEntry->set('Cert', new PdfArray($entry['certs']));
                }
                if (!empty($entry['ocsps'])) {
                    $vriEntry->set('OCSP', new PdfArray($entry['ocsps']));
                }
                if (!empty($entry['crls'])) {
                    $vriEntry->set('CRL', new PdfArray($entry['crls']));
                }
                // /TU — time of validation (optional but recommended)
                $vriEntry->set('TU', new PdfString(gmdate('D:YmdHis\Z')));

                $vriDict->set($hash, $vriEntry);
            }
            $dss->vri = $vriDict;
        }

        return $dss;
    }

    /**
     * Compute the VRI dictionary key for a signature.
     *
     * @param string $rawSignatureBytes The raw DER bytes of the signature
     *                                   (hex-decoded /Contents value)
     * @return string Uppercase hex SHA-256 hash
     */
    public static function computeVriKey(string $rawSignatureBytes): string
    {
        return strtoupper(hash('sha256', $rawSignatureBytes));
    }

    /**
     * Create a PdfStream containing binary data.
     */
    private function createStream(string $data): PdfStream
    {
        return new class ($data) extends PdfStream {
            public function __construct(string $data)
            {
                parent::__construct(new PdfDictionary(), $data);
            }
        };
    }
}
