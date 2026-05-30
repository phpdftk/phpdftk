<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader;

use Phpdftk\ResourceLoader\Exception\FetchFailedException;
use Phpdftk\ResourceLoader\Transport\CurlTransport;
use Phpdftk\ResourceLoader\Transport\RawResponse;
use Phpdftk\ResourceLoader\Transport\TransportInterface;

/**
 * Orchestrates one resource fetch end-to-end:
 *
 *   1. Pre-flight SSRF check on the requested URL
 *   2. Hand off to the {@see TransportInterface} for the actual
 *      send
 *   3. If the response is a 3xx redirect, re-check SSRF against the
 *      Location header, strip Authorization if the host changes
 *      (RFC 9110 §15.4), and loop
 *   4. After {@see FetchOptions::$maxRedirects} hops, fail
 *   5. On a 2xx final response, sniff the body's MIME type and
 *      return the assembled {@see FetchResult}
 *
 * The orchestration is independent of the actual transport so the
 * orchestration logic can be unit-tested against an in-test fake
 * transport. The production fetcher defaults to {@see CurlTransport}.
 */
final class HttpFetcher
{
    public function __construct(
        private readonly SsrfGuard $ssrfGuard = new SsrfGuard(),
        private readonly MimeSniffer $mimeSniffer = new MimeSniffer(),
        private readonly TransportInterface $transport = new CurlTransport(),
    ) {}

    public function fetch(string $originalUrl, FetchOptions $options): FetchResult
    {
        $currentUrl = $originalUrl;
        $previousHost = self::hostOf($currentUrl);
        $headers = self::initialHeaders($options);
        $hops = 0;

        while (true) {
            $this->ssrfGuard->assertSafe($currentUrl);

            $response = $this->transport->send(
                $currentUrl,
                $headers,
                $options->timeoutSeconds,
                $options->maxContentLengthBytes,
            );

            if (!self::isRedirect($response->statusCode)) {
                return $this->finaliseResponse($response, $originalUrl, $currentUrl, $options);
            }

            $hops++;
            if ($hops > $options->maxRedirects) {
                throw new FetchFailedException(sprintf(
                    'Exceeded redirect limit of %d while fetching %s',
                    $options->maxRedirects,
                    $originalUrl,
                ));
            }

            $location = $response->headers['location'] ?? null;
            if ($location === null || $location === '') {
                throw new FetchFailedException(sprintf(
                    'Redirect response %d from %s has no Location header',
                    $response->statusCode,
                    $currentUrl,
                ));
            }

            $nextUrl = self::resolveUrl($currentUrl, $location);
            $nextHost = self::hostOf($nextUrl);
            if ($nextHost !== $previousHost) {
                // Cross-host redirect — strip Authorization per
                // RFC 9110 §15.4 so we don't leak credentials to a
                // host the caller didn't intend.
                unset($headers['Authorization']);
            }
            $currentUrl = $nextUrl;
            $previousHost = $nextHost;
        }
    }

    private function finaliseResponse(
        RawResponse $response,
        string $originalUrl,
        string $finalUrl,
        FetchOptions $options,
    ): FetchResult {
        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw new FetchFailedException(sprintf(
                'HTTP %d from %s',
                $response->statusCode,
                $finalUrl,
            ));
        }

        // Defence in depth — transport already enforces the cap but
        // we re-check here so a malicious transport can't subvert
        // it.
        if (strlen($response->body) > $options->maxContentLengthBytes) {
            throw new FetchFailedException(sprintf(
                'Response body exceeds the %d-byte limit: %s',
                $options->maxContentLengthBytes,
                $finalUrl,
            ));
        }

        // Sniff the body for MIME detection. Server Content-Type is
        // a hint at best.
        $mimeType = $this->mimeSniffer->sniff($response->body);

        return new FetchResult(
            bytes: $response->body,
            mimeType: $mimeType,
            originalUrl: $originalUrl,
            finalUrl: $finalUrl,
            cacheHit: false,
            statusCode: $response->statusCode,
        );
    }

    public function ssrfGuard(): SsrfGuard
    {
        return $this->ssrfGuard;
    }

    public function mimeSniffer(): MimeSniffer
    {
        return $this->mimeSniffer;
    }

    public function transport(): TransportInterface
    {
        return $this->transport;
    }

    private static function isRedirect(int $status): bool
    {
        return in_array($status, [301, 302, 303, 307, 308], true);
    }

    private static function hostOf(string $url): string
    {
        $parsed = parse_url($url);
        if (!is_array($parsed) || !isset($parsed['host']) || !is_string($parsed['host'])) {
            return '';
        }
        return strtolower($parsed['host']);
    }

    /**
     * @return array<string, string>
     */
    private static function initialHeaders(FetchOptions $options): array
    {
        $headers = $options->headers;
        $headers['User-Agent'] = $options->userAgent;
        // Accept anything — the MIME sniffer figures out what we
        // actually got. Caller can override by passing
        // `Accept: image/*` etc in `$options->headers`.
        if (!isset($headers['Accept'])) {
            $headers['Accept'] = '*/*';
        }
        return $headers;
    }

    /**
     * Resolve a (possibly relative) Location URL against the
     * current URL. Doesn't handle every RFC 3986 §5 edge case (a
     * full reference resolver lives in 4F.1.x); covers absolute
     * URLs, root-relative paths, and relative paths.
     */
    private static function resolveUrl(string $base, string $reference): string
    {
        if (str_contains($reference, '://')) {
            return $reference;
        }
        $parsed = parse_url($base);
        if (!is_array($parsed) || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return $reference;
        }
        $origin = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $origin .= ':' . $parsed['port'];
        }
        if (str_starts_with($reference, '//')) {
            return $parsed['scheme'] . ':' . $reference;
        }
        if (str_starts_with($reference, '/')) {
            return $origin . $reference;
        }
        // Relative path — strip the last segment of the base path
        // and join.
        $basePath = $parsed['path'] ?? '/';
        $lastSlash = strrpos($basePath, '/');
        $basePathDir = $lastSlash === false ? '/' : substr($basePath, 0, $lastSlash + 1);
        return $origin . $basePathDir . $reference;
    }
}
