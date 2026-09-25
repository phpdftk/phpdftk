<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf;

use Phpdftk\Filesystem\ResourceLoader;
use Phpdftk\Html\Dom\Element;

/**
 * Resolves the markup an `<iframe>` / `<frame>` embeds — HTML §4.8.5,
 * "the iframe element".
 *
 * An iframe hosts a *child navigable*: a whole second document, laid out
 * in the iframe's content box. Everything downstream of that (parsing,
 * cascade, layout, paint) is the ordinary pipeline; the only thing that
 * is iframe-specific is deciding WHICH bytes to feed it, which is what
 * this class isolates so it can be reasoned about — and tested — on its
 * own.
 *
 * Resolution order, per spec:
 *
 *   1. `srcdoc` — its literal attribute value is the document, and it
 *      wins outright over `src` when both are present.
 *   2. `src`, for the schemes a static renderer can serve:
 *      `about:blank` (the empty document), `data:text/html,…`, and a
 *      same-origin path resolved through the renderer's sandbox.
 *
 * Anything else — `http(s)://`, `javascript:`, `blob:` — yields null and
 * the frame renders as an empty box. Network fetching is deliberately not
 * attempted here: a renderer that silently reached out to the network
 * while laying out a page would be an SSRF vector in exactly the shape
 * {@see \Phpdftk\Filesystem\LocalFilesystem::assertLocalPath()} exists to
 * prevent. `data:` payloads are decoded in memory and never touch the
 * filesystem layer.
 */
final readonly class FrameSource
{
    public function __construct(private ?ResourceLoader $loader = null) {}

    /**
     * The HTML markup `$frame` embeds, or null when the frame has no
     * renderable document (unsupported scheme, unreadable path, absent
     * `src`). An empty string is a meaningful result — `about:blank` and
     * `srcdoc=""` both embed a real, empty document.
     */
    public function markupFor(Element $frame): ?string
    {
        $tag = strtolower($frame->localName);
        if ($tag !== 'iframe' && $tag !== 'frame') {
            return null;
        }
        // `srcdoc` is an inline document, not a URL: it is used verbatim
        // and takes precedence over `src` (HTML §4.8.5). The attribute
        // being PRESENT is what counts — `srcdoc=""` embeds an empty
        // document rather than falling through to `src`.
        $srcdoc = $frame->getAttribute('srcdoc');
        if ($srcdoc !== null) {
            return $srcdoc;
        }
        $src = trim($frame->getAttribute('src') ?? '');
        if ($src === '') {
            return null;
        }
        if (strcasecmp($src, 'about:blank') === 0) {
            return '';
        }
        if (!str_starts_with(strtolower($src), 'data:')) {
            // A relative / root-relative path. Disk paths carry no
            // transport MIME, so the extension is the only signal for
            // whether the target is markup — and getting that wrong is
            // not harmless: `<iframe src="green.png">` handed a PNG's
            // bytes to the HTML parser renders a frame full of
            // `\x89PNG IHDR…IDAT` mojibake, which is a worse answer than
            // an empty frame. A non-markup resource is an *image / plugin
            // document*, which this does not model yet.
            $path = self::pathOf($src);
            if ($path === null || !self::hasMarkupExtension($path)) {
                return null;
            }
            // The loader confines the path to the configured sandbox and
            // refuses stream wrappers, so a frame cannot read outside the
            // document tree it came from.
            return $this->loader?->load($path);
        }
        // `data:text/html,…`. The MIME allowlist matters: a frame whose
        // `src` is `data:image/png;base64,…` embeds an image document we
        // cannot lay out as markup, and feeding its bytes to the HTML
        // parser would produce a page of mojibake rather than nothing.
        // A bare `data:,…` has no MIME at all and defaults to text/plain
        // per rfc2397, so it is not markup either.
        return $this->loader?->load($src, ['text/html']);
    }

    /**
     * The file-path part of `$src`, with query string and fragment
     * removed, or null when nothing is left.
     *
     * Both have to go before the path reaches the filesystem: there is no
     * server here to interpret `?v=2`, so `support/x.html?v=2` names the
     * file `support/x.html` and leaving the query attached simply fails
     * the read.
     */
    private static function pathOf(string $src): ?string
    {
        // `strcspn` rather than `strtok`, which SKIPS leading delimiters
        // and would turn the same-document reference `#frag` into the
        // path `frag`.
        $path = substr($src, 0, strcspn($src, '?#'));
        return $path === '' ? null : $path;
    }

    /**
     * Does `$path` name a markup resource?
     *
     * An extensionless path is accepted — a server path like
     * `/common/blank` names a document, not a file type we can rule out.
     */
    private static function hasMarkupExtension(string $path): bool
    {
        $dot = strrpos($path, '.');
        $slash = strrpos($path, '/');
        if ($dot === false || ($slash !== false && $dot < $slash)) {
            return true;
        }
        return in_array(
            strtolower(substr($path, $dot + 1)),
            ['html', 'htm', 'xhtml', 'xht'],
            true,
        );
    }
}
