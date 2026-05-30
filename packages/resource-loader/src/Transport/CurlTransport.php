<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Transport;

use Phpdftk\ResourceLoader\Exception\FetchFailedException;

/**
 * curl-based HTTP transport. Single-shot per call — caller chains
 * redirects + handles cap enforcement.
 *
 * Each call constructs a fresh curl handle so requests don't share
 * state. cookies, sessions, keep-alive — none of these are wanted
 * for a one-shot resource fetcher.
 */
final class CurlTransport implements TransportInterface
{
    public function send(
        string $url,
        array $headers,
        int $timeoutSeconds,
        int $maxBodyBytes,
    ): RawResponse {
        if (!function_exists('curl_init')) {
            throw new FetchFailedException('ext-curl is not loaded; CurlTransport cannot run.');
        }

        $handle = curl_init();
        if ($handle === false) {
            throw new FetchFailedException('curl_init() returned false.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        // Response header collector. Curl calls this once per
        // header line including blank separators between
        // header blocks (which we ignore).
        $responseHeaders = [];
        $headerCallback = static function ($ch, string $line) use (&$responseHeaders): int {
            unset($ch);
            $colon = strpos($line, ':');
            if ($colon !== false) {
                $name = strtolower(trim(substr($line, 0, $colon)));
                $value = trim(substr($line, $colon + 1));
                if ($name !== '') {
                    $responseHeaders[$name] = $value;
                }
            }
            return strlen($line);
        };

        // Body collector with size cap. Returning less than the
        // received chunk size signals curl to abort.
        $bodyBuffer = '';
        $bodyOverflowed = false;
        $writeCallback = static function ($ch, string $chunk) use (&$bodyBuffer, &$bodyOverflowed, $maxBodyBytes): int {
            unset($ch);
            $chunkLen = strlen($chunk);
            $remaining = $maxBodyBytes - strlen($bodyBuffer);
            if ($remaining <= 0) {
                $bodyOverflowed = true;
                return 0;
            }
            if ($chunkLen > $remaining) {
                $bodyBuffer .= substr($chunk, 0, $remaining);
                $bodyOverflowed = true;
                return $remaining;
            }
            $bodyBuffer .= $chunk;
            return $chunkLen;
        };

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            // We do redirect handling ourselves so the fetcher can
            // re-check SSRF + strip Authorization across hops.
            CURLOPT_FOLLOWLOCATION => false,
            // Defensive defaults — no auto-decoding (we want raw
            // bytes for image sniffing), no compression unless the
            // server explicitly negotiates it via Accept-Encoding.
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_HEADERFUNCTION => $headerCallback,
            CURLOPT_WRITEFUNCTION => $writeCallback,
            CURLOPT_FAILONERROR => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);

        $success = curl_exec($handle);
        if ($success === false && !$bodyOverflowed) {
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            curl_close($handle);
            throw new FetchFailedException(sprintf(
                'HTTP fetch failed (curl errno %d): %s — %s',
                $errno,
                $error,
                $url,
            ));
        }
        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if (!is_int($statusCode) || $statusCode === 0) {
            throw new FetchFailedException(sprintf('HTTP fetch did not return a status code: %s', $url));
        }

        if ($bodyOverflowed && strlen($bodyBuffer) >= $maxBodyBytes) {
            throw new FetchFailedException(sprintf(
                'Response body exceeds the %d-byte limit: %s',
                $maxBodyBytes,
                $url,
            ));
        }

        return new RawResponse(
            statusCode: $statusCode,
            headers: $responseHeaders,
            body: $bodyBuffer,
            finalUrl: $url,
        );
    }
}
