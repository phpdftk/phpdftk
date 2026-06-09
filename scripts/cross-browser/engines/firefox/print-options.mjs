/**
 * Print contract for the Firefox daemon. Pinned to match the
 * chromium daemon's PDF_OPTIONS so the oracle's per-engine PDFs are
 * apples-to-apples — same paper size, same margins, same viewport.
 *
 * WebDriver Print uses centimetres rather than points/inches:
 *   Letter = 8.5 in × 11 in = 21.59 cm × 27.94 cm.
 *
 * Reference: https://w3c.github.io/webdriver/#print
 */

export const VIEWPORT = Object.freeze({
    width: 816,   // 8.5 in × 96 CSS px/in
    height: 1056,
});

/** Body for WebDriver `POST /session/:id/print`. */
export const PRINT_OPTIONS = Object.freeze({
    page: { width: 21.59, height: 27.94 },
    margin: { top: 0, right: 0, bottom: 0, left: 0 },
    orientation: 'portrait',
    background: true,
    scale: 1.0,
    shrinkToFit: false,
});

/**
 * Firefox preferences applied to every render. Locks the profile down
 * so WPT fixtures that link to external schemes (mailto:, tel:, …) or
 * open popups don't escape the sandbox. Mirrors the macOS one-shot
 * path in scripts/cross-browser/render.mjs:renderFirefoxViaGeckodriver.
 */
export const FIREFOX_PREFS = Object.freeze({
    // Popups + beforeunload
    'dom.disable_open_during_load': true,
    'dom.popup_maximum': 0,
    'dom.disable_beforeunload': true,
    'browser.tabs.warnOnClose': false,
    'browser.tabs.warnOnCloseOtherTabs': false,
    'browser.sessionstore.resume_from_crash': false,
    // External protocol handlers — refuse every scheme + suppress the
    // launch-helper warning that would otherwise hit the OS.
    'network.protocol-handler.external-default': false,
    'network.protocol-handler.warn-external-default': false,
    'network.protocol-handler.expose-all': false,
    // Allow file:// fixtures to reference sibling files (images,
    // stylesheets, fonts) without same-origin rejections.
    'privacy.file_unique_origin': false,
    // Don't ask the OS about unknown content types.
    'browser.helperApps.deleteTempFileOnExit': true,
    'browser.download.useDownloadDir': true,
});
