<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Signature;

/**
 * CRL (Certificate Revocation List) fetcher.
 *
 * Extracts CRL Distribution Point URLs from a certificate's CDP
 * extension and fetches the CRL via HTTP GET. Returns raw DER-encoded
 * CRL bytes suitable for embedding in a {@see \ApprLabs\Pdf\Core\Document\DSS}.
 */
final class CrlClient
{
    private int $timeout;

    /**
     * @param int $timeout HTTP request timeout in seconds
     */
    public function __construct(int $timeout = 30)
    {
        $this->timeout = $timeout;
    }

    /**
     * Fetch the CRL for a certificate from its CRL Distribution Points.
     *
     * Tries each CDP URL in order until one succeeds.
     *
     * @param string $derCert DER-encoded certificate
     * @return string Raw DER-encoded CRL
     * @throws \RuntimeException if no CDP is present or all URLs fail
     */
    public function getCrl(string $derCert): string
    {
        $urls = CertificateUtils::getCrlDistributionPointUrls($derCert);
        if (empty($urls)) {
            throw new \RuntimeException(
                'Certificate does not contain CRL Distribution Point URLs (no CDP extension)'
            );
        }

        $lastError = '';
        foreach ($urls as $url) {
            try {
                return $this->fetchCrl($url);
            } catch (\RuntimeException $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new \RuntimeException("Failed to fetch CRL from any distribution point: $lastError");
    }

    /**
     * Fetch a CRL from the given URL via HTTP GET.
     *
     * Automatically detects PEM vs DER format and converts PEM to DER.
     *
     * @param string $url HTTP/HTTPS URL to the CRL
     * @return string Raw DER-encoded CRL
     * @throws \RuntimeException on network error or invalid response
     */
    public function fetchCrl(string $url): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL for CRL request');
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("CRL fetch failed: $error");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("CRL server returned HTTP $httpCode");
        }

        $data = (string) $response;
        if ($data === '') {
            throw new \RuntimeException('CRL response is empty');
        }

        // Auto-detect PEM format and convert to DER
        if (str_contains($data, '-----BEGIN X509 CRL-----')) {
            $pem = preg_replace('/-----[A-Z0-9 ]+-----/', '', $data) ?? '';
            $pem = preg_replace('/\s+/', '', $pem) ?? '';
            $der = base64_decode($pem, true);
            if ($der === false || $der === '') {
                throw new \RuntimeException('Failed to decode PEM CRL to DER');
            }
            return $der;
        }

        return $data;
    }
}
