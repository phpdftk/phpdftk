/**
 * Mirrors scripts/cross-browser/print-options.mjs. Daemon containers
 * are self-contained images that don't share files with the host repo,
 * so we vendor the canonical settings here. Keep both files in sync —
 * drift between them means drift between the one-shot render.mjs path
 * and the daemon path, which would show up as oracle disagreement
 * unrelated to the renderer under test.
 */

export const PAPER = Object.freeze({
    format: 'Letter',
    width: '8.5in',
    height: '11in',
});

export const MARGIN = Object.freeze({
    top: '0',
    right: '0',
    bottom: '0',
    left: '0',
});

export const PDF_OPTIONS = Object.freeze({
    format: PAPER.format,
    margin: MARGIN,
    printBackground: true,
    preferCSSPageSize: true,
    scale: 1.0,
    displayHeaderFooter: false,
});

export const VIEWPORT = Object.freeze({
    width: 816,
    height: 1056,
});
