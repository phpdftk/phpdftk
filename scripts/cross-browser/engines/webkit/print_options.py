"""
Print contract for the WebKit daemon. Pinned to match the chromium
and firefox daemons' settings so the oracle's per-engine PDFs are
apples-to-apples — same paper size (US Letter), same margins (zero),
same viewport, same scale.

GTK's print stack uses inches (with explicit Gtk.Unit.INCH) for
page setup, and Cairo points (1/72 inch) for output. We feed in
inches and let GTK translate.

Reference: docs/plans/cross-browser-container-sweep.md § Phase P2
"""

# Letter, in inches. 8.5 × 11. Matches the chromium daemon's
# PDF_OPTIONS.format = 'Letter' and the firefox daemon's
# PRINT_OPTIONS.page = { width: 21.59, height: 27.94 } (cm).
PAPER_WIDTH_INCHES = 8.5
PAPER_HEIGHT_INCHES = 11.0

# Zero margins — strips browser-default headers/footers.
MARGIN_TOP_INCHES = 0.0
MARGIN_RIGHT_INCHES = 0.0
MARGIN_BOTTOM_INCHES = 0.0
MARGIN_LEFT_INCHES = 0.0

# Viewport for the rendering page before print is invoked.
# 816 = 8.5 in × 96 CSS px/in — matches our @media print cascade input.
VIEWPORT_WIDTH = 816
VIEWPORT_HEIGHT = 1056

# GTK print-settings keys. Constants are also available as
# Gtk.PRINT_SETTINGS_OUTPUT_URI etc. but the string form is more
# robust across GTK versions.
PRINT_SETTING_OUTPUT_URI = "output-uri"
PRINT_SETTING_OUTPUT_FILE_FORMAT = "output-file-format"
PRINT_SETTING_OUTPUT_FORMAT_VALUE_PDF = "pdf"
PRINT_SETTING_PRINT_BACKGROUND = "print-background"

# Window size (pixels) we resize the WebView to before triggering
# print, so the cascade picks up the right viewport for media
# queries that key off width. Matches Chromium's
# `BrowserContext.viewport` and Firefox's WebDriver page-size hint.
WINDOW_WIDTH = VIEWPORT_WIDTH
WINDOW_HEIGHT = VIEWPORT_HEIGHT
