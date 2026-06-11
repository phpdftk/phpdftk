#!/usr/bin/env node

/**
 * DOM settler for WPT `class="reftest-wait"` fixtures.
 *
 * Loads a test fixture in headless Chromium, waits for any
 * `reftest-wait` JS to finish (the WPT convention: the test removes
 * the `reftest-wait` class on `<html>` to signal "ready"), and dumps
 * the post-JS HTML to stdout. The wpt-harness feeds that settled
 * HTML to our PHP renderer instead of the original source so the
 * static renderer sees the same DOM state a browser would screenshot.
 *
 * ## CSS animations + transitions are paused at t=0
 *
 * Our PHP renderer is a static-document renderer — there is no
 * notion of time. To keep the settled DOM aligned with what our
 * renderer would do anyway, we inject a stylesheet that zeroes
 * `animation-duration` / `animation-delay` / `transition-duration`
 * / `transition-delay` on every element BEFORE the page loads.
 * This forces:
 *
 *   - CSS animations to evaluate at their `0%` keyframe (initial
 *     value), never reaching mid-animation or end states.
 *   - CSS transitions to apply target values instantly, so the
 *     starting style is never captured mid-tween.
 *
 * Result: when the settled DOM is serialized, computed styles
 * reflect the initial / target state (matching `t=0` semantics in
 * our renderer), not whatever animation frame the browser happened
 * to be on when `reftest-wait` was removed.
 *
 * Tests that rely on observing an *animated* end-state (rare in WPT
 * reftests; most use animation for visibility only and assert on
 * the post-animation static layout) will not match what the
 * unmodified browser would render. That's the trade-off this script
 * documents and locks in.
 *
 * ## Usage
 *
 *   node settle-dom.mjs <test-file-path> [--output=path] [--timeout=ms]
 *                                       [--corpus-root=path]
 *
 *   test-file-path     — absolute path to an HTML / XHTML fixture.
 *   --output=path      — write the settled HTML here. When omitted,
 *                        stdout receives the HTML.
 *   --timeout=ms       — how long to wait for `reftest-wait` removal
 *                        before giving up and dumping whatever state
 *                        the page has reached. Default: 5000.
 *   --corpus-root=path — root directory for `/`-prefixed URLs (the
 *                        WPT convention: `/fonts/x.woff` means
 *                        "the corpus root's /fonts/x.woff"). When
 *                        set, the script registers a request router
 *                        so the test's @font-face and helper-script
 *                        URLs resolve against this directory.
 *
 * Exit codes:
 *   0  — settled HTML produced
 *   1  — bad argv
 *   2  — chromium launch failed
 *   3  — page navigation / settling failed
 */

import { chromium } from 'playwright';
import { readFileSync, statSync, writeFileSync } from 'node:fs';
import { join, normalize } from 'node:path';
import { pathToFileURL } from 'node:url';
import process from 'node:process';

const DEFAULT_TIMEOUT_MS = 5000;

const MIME_BY_EXT = {
    '.html': 'text/html; charset=utf-8',
    '.htm': 'text/html; charset=utf-8',
    '.xhtml': 'application/xhtml+xml; charset=utf-8',
    '.xht': 'application/xhtml+xml; charset=utf-8',
    '.svg': 'image/svg+xml; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.mjs': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
    '.ttf': 'font/ttf',
    '.otf': 'font/otf',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.gif': 'image/gif',
    '.webp': 'image/webp',
};

function contentTypeFor(path) {
    const idx = path.lastIndexOf('.');
    if (idx < 0) {
        return 'application/octet-stream';
    }
    const ext = path.slice(idx).toLowerCase();
    return MIME_BY_EXT[ext] ?? 'application/octet-stream';
}

function parseArgs(argv) {
    if (argv.length < 3) {
        return null;
    }
    const file = argv[2];
    let output = null;
    let timeout = DEFAULT_TIMEOUT_MS;
    let corpusRoot = null;
    for (let i = 3; i < argv.length; i++) {
        const arg = argv[i];
        if (arg.startsWith('--output=')) {
            output = arg.slice('--output='.length);
        } else if (arg.startsWith('--timeout=')) {
            const value = parseInt(arg.slice('--timeout='.length), 10);
            if (Number.isFinite(value) && value > 0) {
                timeout = value;
            }
        } else if (arg.startsWith('--corpus-root=')) {
            corpusRoot = arg.slice('--corpus-root='.length);
        }
    }
    return { file, output, timeout, corpusRoot };
}

function die(code, msg) {
    process.stderr.write(`settle-dom.mjs: ${msg}\n`);
    process.exit(code);
}

const args = parseArgs(process.argv);
if (args === null) {
    die(1, 'usage: settle-dom.mjs <file> [--output=path] [--timeout=ms]');
}

let browser;
try {
    browser = await chromium.launch();
} catch (err) {
    die(2, `chromium launch failed: ${err.message}`);
}

try {
    const context = await browser.newContext({
        // Match the screen-media print viewport used by render.mjs so
        // layout sees the same widow size browsers screenshot at.
        viewport: { width: 800, height: 600 },
    });
    const page = await context.newPage();

    // Resolve `/`-prefixed URLs against the corpus root when one was
    // configured. Without this, requests for `/fonts/x.woff` would
    // hit the OS root (which doesn't have them) and the test's JS
    // would wait forever on `loadAllFonts()`. Same convention the
    // PHP ResourceLoader applies in #105.
    if (args.corpusRoot !== null) {
        const corpusRoot = normalize(args.corpusRoot);
        await page.route('file://**', async (route, request) => {
            const url = new URL(request.url());
            // Detect `/`-prefixed URLs that the browser turned into
            // `file:///abs/path/...`. The path-segment after `file://`
            // is what we want to remap.
            const path = url.pathname;
            // Only rewrite when the path doesn't already exist on
            // disk AND a corpus-relative form would. Avoids breaking
            // legitimate absolute paths.
            let resolved = null;
            try {
                statSync(path);
            } catch {
                const candidate = join(corpusRoot, path);
                try {
                    statSync(candidate);
                    resolved = candidate;
                } catch {
                    // Neither path exists - let the browser fail the
                    // request naturally.
                }
            }
            if (resolved !== null) {
                const body = readFileSync(resolved);
                await route.fulfill({
                    status: 200,
                    body,
                    contentType: contentTypeFor(resolved),
                });
                return;
            }
            await route.continue();
        });
    }

    // Disable CSS animations + transitions at t=0 by injecting the
    // override stylesheet BEFORE any page script runs. This keeps the
    // settled DOM aligned with the static-renderer's "no time"
    // semantics - see the header doc for the trade-off.
    await page.addInitScript(() => {
        const style = document.createElement('style');
        style.setAttribute('data-source', 'phpdftk-settle-dom');
        style.textContent = `
            *, *::before, *::after {
                animation-duration: 0s !important;
                animation-delay: 0s !important;
                transition-duration: 0s !important;
                transition-delay: 0s !important;
            }
        `;
        // Wait for <head> to exist before inserting. The init
        // script runs before document construction in some cases;
        // observe and inject on the first available parent.
        const inject = () => {
            const target = document.head ?? document.documentElement;
            if (target) {
                target.appendChild(style);
                return true;
            }
            return false;
        };
        if (!inject()) {
            new MutationObserver((_, obs) => {
                if (inject()) {
                    obs.disconnect();
                }
            }).observe(document, { childList: true, subtree: true });
        }
    });

    try {
        await page.goto(pathToFileURL(args.file).href, { waitUntil: 'load' });
    } catch (err) {
        die(3, `navigation failed: ${err.message}`);
    }

    // Wait for the WPT-standard `class="reftest-wait"` signal to
    // clear. The class is added by the test author to indicate "JS
    // hasn't finished setup; don't screenshot yet"; it's removed
    // (typically by /common/reftest-wait.js's takeScreenshot()) once
    // the test's setup work is done. We treat the removal as our
    // "DOM is settled" cue.
    //
    // Tests that never set the class (most reftests) get past the
    // initial check immediately and skip the wait entirely. Tests
    // that hang and never clear the class hit the timeout - we
    // still dump whatever state the page reached, which matches what
    // a browser would screenshot if its own timeout fires.
    try {
        await page.waitForFunction(
            () => !document.documentElement.classList.contains('reftest-wait'),
            null,
            { timeout: args.timeout },
        );
    } catch {
        // Timeout - dump the current state anyway. Don't die here;
        // a fixture that hangs is still informative to compare.
    }

    const settledHtml = await page.content();
    if (args.output !== null) {
        writeFileSync(args.output, settledHtml, 'utf8');
    } else {
        process.stdout.write(settledHtml);
    }
} finally {
    await browser.close().catch(() => undefined);
}
