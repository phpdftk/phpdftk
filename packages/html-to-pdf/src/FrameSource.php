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
            // A relative / root-relative path. The loader confines it to
            // the configured sandbox and refuses stream wrappers, so a
            // frame cannot read outside the document tree it came from.
            return $this->loader?->load($src);
        }
        // `data:text/html,…`. The MIME allowlist matters: a frame whose
        // `src` is `data:image/png;base64,…` embeds an image document we
        // cannot lay out as markup, and feeding its bytes to the HTML
        // parser would produce a page of mojibake rather than nothing.
        // A bare `data:,…` has no MIME at all and defaults to text/plain
        // per rfc2397, so it is not markup either.
        return $this->loader?->load($src, ['text/html', 'application/xhtml+xml']);
    }
}
