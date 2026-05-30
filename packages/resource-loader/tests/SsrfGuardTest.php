<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Tests;

use Phpdftk\ResourceLoader\Exception\SsrfBlockedException;
use Phpdftk\ResourceLoader\SsrfGuard;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4F scaffold — the SSRF guard is the one piece of working
 * logic in the scaffold (URL validation is pure synchronous), so
 * we test it thoroughly. The guard must reject every URL category
 * documented as out-of-policy in the README before the actual HTTP
 * fetcher lands in 4F.1.
 */
final class SsrfGuardTest extends TestCase
{
    private SsrfGuard $defaultGuard;

    protected function setUp(): void
    {
        $this->defaultGuard = new SsrfGuard();
    }

    // -----------------------------------------------------------------------
    // Scheme allowlist
    // -----------------------------------------------------------------------

    public function testHttpsIsAllowedByDefault(): void
    {
        // Doesn't throw → passes. Note: real fetcher will fail
        // later when 4F.1 hasn't shipped, but the guard accepts the
        // URL.
        $this->defaultGuard->assertSafe('https://example.com/asset.png');
        $this->expectNotToPerformAssertions();
    }

    public function testHttpIsAllowedByDefault(): void
    {
        $this->defaultGuard->assertSafe('http://example.com/asset.png');
        $this->expectNotToPerformAssertions();
    }

    public function testFileSchemeIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->expectExceptionMessageMatches('/scheme "file"/i');
        $this->defaultGuard->assertSafe('file:///etc/passwd');
    }

    public function testFtpSchemeIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('ftp://example.com/file');
    }

    public function testGopherSchemeIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('gopher://example.com/x');
    }

    public function testJavascriptSchemeIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('javascript:alert(1)');
    }

    public function testDataSchemeIsRejected(): void
    {
        // `data:` URIs are handled inline by svg-to-pdf — they
        // don't route through this loader, so we reject them here
        // to make the policy explicit.
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('data:image/png;base64,iVBO');
    }

    public function testCustomSchemeAllowlistOverrides(): void
    {
        $guard = new SsrfGuard(allowedSchemes: ['http', 'https', 'gopher']);
        $guard->assertSafe('gopher://example.com/x');
        $this->expectNotToPerformAssertions();
    }

    // -----------------------------------------------------------------------
    // Loopback
    // -----------------------------------------------------------------------

    public function testIpv4LoopbackIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->expectExceptionMessageMatches('/loopback|reserved/i');
        $this->defaultGuard->assertSafe('http://127.0.0.1/x');
    }

    public function testIpv4LoopbackInOtherRangeIsRejected(): void
    {
        // 127.0.0.0/8 — not just 127.0.0.1
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://127.42.13.7/x');
    }

    public function testIpv6LoopbackIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://[::1]/x');
    }

    public function testLoopbackAllowedWhenFlagSet(): void
    {
        $guard = new SsrfGuard(allowLoopback: true);
        $guard->assertSafe('http://127.0.0.1/x');
        $guard->assertSafe('http://[::1]/x');
        $this->expectNotToPerformAssertions();
    }

    // -----------------------------------------------------------------------
    // RFC 1918 private ranges
    // -----------------------------------------------------------------------

    public function testRfc1918TenSlashEightIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://10.0.0.1/x');
    }

    public function testRfc1918OneSeventyTwoIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://172.16.5.1/x');
    }

    public function testRfc1918OneSeventyTwoBoundaryAllowed(): void
    {
        // 172.16.0.0/12 → 172.16.0.0 through 172.31.255.255. 172.32
        // is OUTSIDE the private range.
        $this->defaultGuard->assertSafe('http://172.32.0.1/x');
        $this->expectNotToPerformAssertions();
    }

    public function testRfc1918OneNinetyTwoIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://192.168.1.1/x');
    }

    public function testIpv6UniqueLocalIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://[fd00::1]/x');
    }

    public function testPrivateIpAllowedWhenFlagSet(): void
    {
        $guard = new SsrfGuard(allowPrivateIp: true);
        $guard->assertSafe('http://10.0.0.1/x');
        $guard->assertSafe('http://192.168.1.1/x');
        $guard->assertSafe('http://[fd00::1]/x');
        $this->expectNotToPerformAssertions();
    }

    // -----------------------------------------------------------------------
    // Link-local + multicast + reserved
    // -----------------------------------------------------------------------

    public function testIpv4LinkLocalIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://169.254.169.254/x');
    }

    public function testIpv6LinkLocalIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://[fe80::1]/x');
    }

    public function testIpv4MulticastIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://224.0.0.1/x');
    }

    public function testIpv4ZeroNetworkIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://0.0.0.0/x');
    }

    public function testIpv4ReservedTopRangeIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://240.0.0.1/x');
    }

    // -----------------------------------------------------------------------
    // CGNAT 100.64.0.0/10
    // -----------------------------------------------------------------------

    public function testCgnatIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->expectExceptionMessageMatches('/CGNAT|100\.64\.0\.0/i');
        $this->defaultGuard->assertSafe('http://100.64.5.1/x');
    }

    public function testCgnatBoundaryAllowed(): void
    {
        // 100.63.x.x is below the CGNAT range and not in any other
        // private range → allowed.
        $this->defaultGuard->assertSafe('http://100.63.255.255/x');
        // 100.128.x.x is above the CGNAT range → allowed.
        $this->defaultGuard->assertSafe('http://100.128.0.1/x');
        $this->expectNotToPerformAssertions();
    }

    public function testCgnatTopBoundaryRejected(): void
    {
        // 100.127.255.255 is the last CGNAT address.
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('http://100.127.255.255/x');
    }

    // -----------------------------------------------------------------------
    // Public IPs and hostnames
    // -----------------------------------------------------------------------

    public function testPublicIpv4Allowed(): void
    {
        $this->defaultGuard->assertSafe('https://8.8.8.8/x');
        $this->defaultGuard->assertSafe('https://1.1.1.1/x');
        $this->expectNotToPerformAssertions();
    }

    public function testPublicHostnameAllowed(): void
    {
        // Hostnames are not DNS-resolved at validation time, so
        // they pass the guard. The 4F.1 fetcher will re-check after
        // DNS resolution.
        $this->defaultGuard->assertSafe('https://example.com/x');
        $this->defaultGuard->assertSafe('https://fonts.googleapis.com/css2?family=Inter');
        $this->expectNotToPerformAssertions();
    }

    // -----------------------------------------------------------------------
    // Hostname allowlist
    // -----------------------------------------------------------------------

    public function testHostnameAllowlistRejectsUnlistedHosts(): void
    {
        $guard = new SsrfGuard(allowedHosts: ['fonts.googleapis.com']);
        $this->expectException(SsrfBlockedException::class);
        $this->expectExceptionMessageMatches('/not in the allowlist/');
        $guard->assertSafe('https://evil.example.com/x');
    }

    public function testHostnameAllowlistAcceptsListedHosts(): void
    {
        $guard = new SsrfGuard(allowedHosts: ['fonts.googleapis.com']);
        $guard->assertSafe('https://fonts.googleapis.com/css2?family=Inter');
        $this->expectNotToPerformAssertions();
    }

    public function testHostnameAllowlistIsExactMatchNotSubdomain(): void
    {
        // No subdomain magic — `fonts.googleapis.com` doesn't allow
        // `subdomain.fonts.googleapis.com`. Authors must be explicit.
        $guard = new SsrfGuard(allowedHosts: ['fonts.googleapis.com']);
        $this->expectException(SsrfBlockedException::class);
        $guard->assertSafe('https://subdomain.fonts.googleapis.com/x');
    }

    public function testHostnameAllowlistMatchesIpv6Bracketed(): void
    {
        // Allowlist entry without brackets should still match the
        // bracketed-in-URL form.
        $guard = new SsrfGuard(
            allowedHosts: ['2606:4700:4700::1111'],
            allowPrivateIp: true,
            allowLoopback: true,
        );
        $guard->assertSafe('https://[2606:4700:4700::1111]/x');
        $this->expectNotToPerformAssertions();
    }

    // -----------------------------------------------------------------------
    // Malformed URLs
    // -----------------------------------------------------------------------

    public function testMissingSchemeIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->expectExceptionMessageMatches('/scheme/i');
        $this->defaultGuard->assertSafe('//example.com/x');
    }

    public function testMissingHostIsRejected(): void
    {
        // Either "URL is missing a host" (parse_url returned an
        // array with no host) or "URL is malformed" (parse_url
        // returned false). Both are policy violations.
        $this->expectException(SsrfBlockedException::class);
        $this->expectExceptionMessageMatches('/host|malformed/i');
        $this->defaultGuard->assertSafe('http:///');
    }

    public function testBareGarbageIsRejected(): void
    {
        $this->expectException(SsrfBlockedException::class);
        $this->defaultGuard->assertSafe('not-a-url');
    }

    // -----------------------------------------------------------------------
    // Accessor surface
    // -----------------------------------------------------------------------

    public function testAccessorsReflectConstructorArgs(): void
    {
        $guard = new SsrfGuard(
            allowedSchemes: ['https'],
            allowedHosts: ['cdn.example.com'],
            allowLoopback: true,
            allowPrivateIp: false,
        );
        self::assertSame(['https'], $guard->allowedSchemes());
        self::assertSame(['cdn.example.com'], $guard->allowedHosts());
        self::assertTrue($guard->allowsLoopback());
        self::assertFalse($guard->allowsPrivateIp());
    }
}
