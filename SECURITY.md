# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in phpdftk, please report it responsibly.

**Do not open a public issue.** Instead, use [GitHub Security Advisories](https://github.com/phpdftk/phpdftk/security/advisories/new) to report the vulnerability privately.

We will acknowledge receipt within 48 hours and aim to release a fix within 7 days for critical issues.

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |

## Hardening: Local-file access

All file I/O in phpdftk routes through the [`phpdftk/filesystem`](packages/filesystem) package (`Phpdftk\Filesystem\LocalFilesystem`). This wrapper:

- **Rejects stream-wrapper paths.** Paths matching `<scheme>://` (`php://`, `http://`, `https://`, `data://`, `phar://`, `compress.zlib://`, etc.) throw `InvalidArgumentException` from `assertLocalPath()`. This blocks SSRF and arbitrary-stream reads through user-supplied filenames in `PdfReader::fromFile()`, `Pdf::save()`, `PdfMerger::addFile()`, font/image loaders, and all other path-accepting APIs.
- **Normalises failures.** `fopen` and `file_get_contents` errors become labelled `RuntimeException`s.
- **Centralises mkdir policy** for `writeFile(..., createDirectories: true)`.

If you find a way to bypass this guard or an internal call site that skips it, please report it via the GitHub Security Advisory link above.
