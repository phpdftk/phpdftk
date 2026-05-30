# phpdftk/resource-loader

HTTP resource loader for the phpdftk HTML / CSS / SVG pipeline.

Resolves URL-form resources — `<img src="https://…">`, `<image href="https://…">`, `@font-face src: url(…)`, `@import url(…)`, `background-image: url(…)`, etc. — through one audited code path with:

- SSRF guard (rejects loopback, private, link-local, multicast, reserved IP ranges; configurable allowlist)
- Configurable timeout, max redirect chain length, max content-length cap
- Cache abstraction (in-memory, file-backed, or null) so the same URL fetched twice in one render hits the cache
- MIME sniffing on the response bytes (the `Content-Type` header is a hint, not authoritative — we sniff the bytes the same way browsers do for image / font / SVG inputs)
- Bounded scheme allowlist (default `http`, `https`; `data:` is handled by callers via `phpdftk/svg-to-pdf`'s data URI decoder so it doesn't go through this loader)

## Status

**Phase 4F scaffold.** The package shape lands here — public types for `FetchOptions`, `FetchResult`, and the `Cache` abstraction; a real `SsrfGuard` (URL validation is pure synchronous logic). The HTTP fetcher itself, the cache backends, and MIME sniffing land in `4F.1`–`4F.5`.

```
4F.1  HttpFetcher        curl-multi based fetcher with retry / redirect logic
4F.2  FileCache          on-disk cache with TTL + content-hash keys
4F.3  MimeSniffer        whatwg/mimesniff-compatible bytes → mime type
4F.4  Tests + fixtures   live HTTP integration via a local test server
4F.5  Wiring             svg-to-pdf <image> hrefs, html-to-pdf <img>,
                         css @font-face url() and @import url()
```

## Usage (target API)

```php
use Phpdftk\ResourceLoader\ResourceLoader;
use Phpdftk\ResourceLoader\FetchOptions;
use Phpdftk\ResourceLoader\SsrfGuard;
use Phpdftk\ResourceLoader\Cache\FileCache;

$loader = new ResourceLoader(
    cache: new FileCache(directory: '/var/cache/phpdftk/loader'),
    ssrfGuard: new SsrfGuard(),  // default-deny private / loopback
    defaultOptions: new FetchOptions(
        timeoutSeconds: 10,
        maxRedirects: 5,
        maxContentLengthBytes: 50_000_000,
    ),
);

$result = $loader->fetch('https://example.com/logo.png');
$bytes  = $result->bytes;
$mime   = $result->mimeType;       // sniffed, not just Content-Type
$final  = $result->finalUrl;       // after redirects
```

## SSRF policy

By default the loader rejects:

- Any scheme not in `{ http, https }`
- IPv4 / IPv6 literal hosts in private (RFC 1918, fc00::/7), loopback (127/8, ::1), link-local (169.254/16, fe80::/10), CGNAT (100.64/10), multicast (224/4, ff00::/8), or reserved (0/8, 240/4) ranges
- Empty hostnames

Hostnames are not DNS-resolved at validation time (the `SsrfGuard` is pure synchronous logic). DNS resolution + post-resolve IP re-checks land in `4F.1` so DNS-rebinding attacks are also caught.

Override per-fetcher:

```php
$ssrf = new SsrfGuard(
    allowedSchemes: ['http', 'https'],
    allowedHosts: ['fonts.googleapis.com', 'fonts.gstatic.com'],  // exact match
    allowLoopback: false,
    allowPrivateIp: false,
);
```

## Installation

```bash
composer require phpdftk/resource-loader
```

## License

MIT
