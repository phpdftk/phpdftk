<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader;

use Phpdftk\ResourceLoader\Exception\SsrfBlockedException;

/**
 * Synchronous URL safety check. Rejects URLs that would otherwise
 * let a renderer reach the host's internal network: loopback,
 * RFC 1918 private ranges, link-local, CGNAT, multicast, reserved,
 * and any non-`http(s)` scheme.
 *
 * Hostnames are NOT DNS-resolved at validation time — this guard is
 * pure synchronous logic suitable for use anywhere a URL crosses a
 * trust boundary. The HTTP fetcher (Phase 4F.1) re-runs the IP
 * check after DNS resolution so DNS-rebinding attacks are caught
 * too.
 *
 * Override the defaults to allow specific ranges or hosts:
 *
 *   new SsrfGuard(
 *       allowedSchemes: ['http', 'https'],
 *       allowedHosts:   ['fonts.googleapis.com', 'fonts.gstatic.com'],
 *       allowLoopback:  false,
 *       allowPrivateIp: false,
 *   );
 */
final class SsrfGuard
{
    /**
     * @param list<string> $allowedSchemes URI schemes the guard
     *                                     permits. Default
     *                                     `['http', 'https']`.
     * @param list<string> $allowedHosts   Hostname allowlist. When
     *                                     non-empty, the URL's host
     *                                     must exactly match one of
     *                                     these entries. Empty (the
     *                                     default) means
     *                                     "allow any host that
     *                                     passes the IP / scheme
     *                                     checks".
     * @param bool $allowLoopback          Permit 127/8 + ::1.
     *                                     Useful for local-dev test
     *                                     servers.
     * @param bool $allowPrivateIp         Permit RFC 1918, link-
     *                                     local, CGNAT, etc.
     *                                     Required if you serve
     *                                     internal hosts that must
     *                                     resolve to private
     *                                     addresses.
     */
    public function __construct(
        private readonly array $allowedSchemes = ['http', 'https'],
        private readonly array $allowedHosts = [],
        private readonly bool $allowLoopback = false,
        private readonly bool $allowPrivateIp = false,
    ) {}

    /**
     * Validate a URL. Throws {@see SsrfBlockedException} with a
     * message documenting the exact policy violation if the URL is
     * unsafe.
     */
    public function assertSafe(string $url): void
    {
        $parsed = parse_url($url);
        if ($parsed === false || $parsed === null) {
            throw new SsrfBlockedException(sprintf('URL is malformed: %s', $url));
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if ($scheme === '') {
            throw new SsrfBlockedException(sprintf('URL is missing a scheme: %s', $url));
        }
        if (!in_array($scheme, $this->allowedSchemes, true)) {
            throw new SsrfBlockedException(sprintf(
                'Scheme "%s" is not in the allowlist [%s]: %s',
                $scheme,
                implode(', ', $this->allowedSchemes),
                $url,
            ));
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            throw new SsrfBlockedException(sprintf('URL is missing a host: %s', $url));
        }

        // Strip the brackets PHP leaves around IPv6 hosts so the
        // filter_var IP check sees the bare address.
        $bareHost = $host;
        if (str_starts_with($bareHost, '[') && str_ends_with($bareHost, ']')) {
            $bareHost = substr($bareHost, 1, -1);
        }

        if ($this->allowedHosts !== [] && !in_array($host, $this->allowedHosts, true) && !in_array($bareHost, $this->allowedHosts, true)) {
            throw new SsrfBlockedException(sprintf(
                'Host "%s" is not in the allowlist [%s]: %s',
                $host,
                implode(', ', $this->allowedHosts),
                $url,
            ));
        }

        // If the host is an IP literal, run it through filter_var's
        // private + reserved range checks. filter_var's
        // FILTER_FLAG_NO_RES_RANGE covers 0/8, 127/8, 169.254/16,
        // 224/4 (multicast), 240/4 (reserved), ::, ::1, fe80::/10,
        // and IPv4-mapped IPv6 in those ranges. FILTER_FLAG_NO_PRIV_RANGE
        // covers 10/8, 172.16/12, 192.168/16, and fc00::/7.
        if (filter_var($bareHost, FILTER_VALIDATE_IP) !== false) {
            $flags = 0;
            if (!$this->allowPrivateIp) {
                $flags |= FILTER_FLAG_NO_PRIV_RANGE;
            }
            if (!$this->allowLoopback && !$this->allowPrivateIp) {
                $flags |= FILTER_FLAG_NO_RES_RANGE;
            }
            if ($flags !== 0 && filter_var($bareHost, FILTER_VALIDATE_IP, $flags) === false) {
                throw new SsrfBlockedException(sprintf(
                    'Host "%s" is a loopback / private / reserved IP: %s',
                    $bareHost,
                    $url,
                ));
            }
            // Reject CGNAT 100.64.0.0/10 — not covered by filter_var
            // flags but RFC 6598 makes it carrier-grade NAT space
            // that shouldn't be a public destination.
            if (!$this->allowPrivateIp && self::isCgnat($bareHost)) {
                throw new SsrfBlockedException(sprintf(
                    'Host "%s" is in the CGNAT range 100.64.0.0/10: %s',
                    $bareHost,
                    $url,
                ));
            }
            // Reject IPv4 multicast 224.0.0.0/4 — filter_var's
            // NO_RES_RANGE doesn't include it; we don't want a
            // renderer joining a multicast group.
            if (!$this->allowPrivateIp && self::isMulticastV4($bareHost)) {
                throw new SsrfBlockedException(sprintf(
                    'Host "%s" is in the IPv4 multicast range 224.0.0.0/4: %s',
                    $bareHost,
                    $url,
                ));
            }
            // Reject IPv6 multicast ff00::/8 — same reason.
            if (!$this->allowPrivateIp && self::isMulticastV6($bareHost)) {
                throw new SsrfBlockedException(sprintf(
                    'Host "%s" is in the IPv6 multicast range ff00::/8: %s',
                    $bareHost,
                    $url,
                ));
            }
        }
    }

    /**
     * @return list<string>
     */
    public function allowedSchemes(): array
    {
        return $this->allowedSchemes;
    }

    /**
     * @return list<string>
     */
    public function allowedHosts(): array
    {
        return $this->allowedHosts;
    }

    public function allowsLoopback(): bool
    {
        return $this->allowLoopback;
    }

    public function allowsPrivateIp(): bool
    {
        return $this->allowPrivateIp;
    }

    /**
     * Check whether a dotted-quad IPv4 address falls in the RFC 6598
     * CGNAT range `100.64.0.0/10` (`100.64.0.0` through
     * `100.127.255.255`). filter_var's NO_PRIV_RANGE doesn't catch
     * this, so we handle it explicitly.
     */
    private static function isCgnat(string $ip): bool
    {
        $parts = explode('.', $ip);
        if (count($parts) !== 4) {
            return false;
        }
        if ((int) $parts[0] !== 100) {
            return false;
        }
        $second = (int) $parts[1];
        return $second >= 64 && $second <= 127;
    }

    /**
     * Check whether a dotted-quad IPv4 address falls in the
     * multicast range `224.0.0.0/4` (RFC 5771).
     */
    private static function isMulticastV4(string $ip): bool
    {
        $parts = explode('.', $ip);
        if (count($parts) !== 4) {
            return false;
        }
        $first = (int) $parts[0];
        return $first >= 224 && $first <= 239;
    }

    /**
     * Check whether an IPv6 address falls in the multicast range
     * `ff00::/8` (RFC 4291).
     */
    private static function isMulticastV6(string $ip): bool
    {
        // Only meaningful for IPv6 — reject non-v6 silently.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }
        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return false;
        }
        return ord($packed[0]) === 0xff;
    }
}
