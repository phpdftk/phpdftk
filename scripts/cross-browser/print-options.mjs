/**
 * Canonical print-options contract for the cross-browser oracle.
 *
 * Both `render.mjs` (browser engines via Playwright) AND
 * `Phpdftk\HtmlToPdf\Renderer` (our PHP engine) must agree on every
 * setting here. Drift = test failures that aren't bugs. If you
 * change something here, mirror the change in
 * `packages/wpt-harness/src/BrowserOracle.php` and verify the
 * resulting PDFs are still the same geometry.
 *
 * Reference: docs/plans/cross-browser-oracle.md § "Print-options
 * contract"
 */

/** Letter, in PDF points (1 in = 72 pt). Matches our default MediaBox. */
export const PAPER = {
    format: 'Letter',
    width: '8.5in',
    height: '11in',
};

/** Margin zero on all sides; strips browser-default headers / footers. */
export const MARGIN = {
    top: '0',
    right: '0',
    bottom: '0',
    left: '0',
};

/**
 * Page-PDF options. Pass as a single object to `page.pdf()`.
 */
export const PDF_OPTIONS = Object.freeze({
    format: PAPER.format,
    margin: MARGIN,
    printBackground: true,
    // Honour `@page { size: ... }` declarations in fixtures so paged-media
    // tests behave the same way they do in our engine's page-size resolver.
    preferCSSPageSize: true,
    scale: 1.0,
    displayHeaderFooter: false,
});

/** Viewport for the rendering page before pdf() is invoked. */
export const VIEWPORT = Object.freeze({
    width: 816,   // 8.5 in × 96 CSS px/in — matches our `@media print` cascade input
    height: 1056,
});
