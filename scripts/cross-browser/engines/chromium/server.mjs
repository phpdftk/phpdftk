#!/usr/bin/env node

/**
 * Persistent Chromium render daemon.
 *
 * Replaces the per-fixture `node render.mjs chromium <file>` fork from
 * scripts/cross-browser/render.mjs. The browser launches once at
 * startup and stays up for the daemon's lifetime; each request gets a
 * fresh BrowserContext from a bounded pool, renders the fixture via
 * Playwright's `page.pdf()`, and releases the context back. A
 * disconnect on the browser process triggers a supervisor-driven
 * relaunch; in-flight requests receive 502 and the orchestrator can
 * retry.
 *
 * Protocol — defined in docs/plans/cross-browser-container-sweep.md
 * § "Daemon protocol":
 *
 *   GET  /status   → 200 { engine, version, ready, pool }
 *   POST /render   → 200 { pdf_bytes_base64, ms, from_cache }
 *                  | 304 { from_cache: true }  (when X-Cache-Probe: 1)
 *                  | 4xx { error } on bad input
 *                  | 408 { error } on timeout
 *                  | 502 { error } on browser-crash window
 *   POST /flush    → 204 (drain pool, restart browser; for cache-gen bumps)
 *
 * Configuration via env:
 *
 *   PORT              — HTTP port (default 9101)
 *   POOL_SIZE         — page-pool size (default min(cores−2, 16))
 *   WPT_ROOT          — fixture sandbox root (default /wpt)
 *   CACHE_DIR         — shared PDF cache (default /var/cache/browser)
 *   RENDER_TIMEOUT_MS — per-render budget (default 60000)
 *
 * Fixture sandboxing: every `fixture` path in a /render body must
 * start with WPT_ROOT and resolve to an existing file. Anything else
 * is rejected with 400 — the daemon never reads outside the mount.
 */

import { chromium } from 'playwright';
import { createServer } from 'node:http';
import { availableParallelism } from 'node:os';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import process from 'node:process';
import { PDF_OPTIONS, VIEWPORT } from './print-options.mjs';
import {
    cachedPathFor,
    createSemaphore,
    validateFixture,
    writeCacheAtomically,
} from './lib.mjs';

const ENGINE = 'chromium';
const PORT = Number(process.env.PORT ?? 9101);
const POOL_SIZE = Number(
    process.env.POOL_SIZE ?? Math.min(Math.max(availableParallelism() - 2, 1), 16),
);
const WPT_ROOT = resolve(process.env.WPT_ROOT ?? '/wpt');
const CACHE_DIR = resolve(process.env.CACHE_DIR ?? '/var/cache/browser');
const RENDER_TIMEOUT_MS = Number(process.env.RENDER_TIMEOUT_MS ?? 60000);

const browserState = {
    browser: null,
    version: null,
    launching: null,
    generation: 0,
    inFlight: 0,
};

const semaphore = createSemaphore(POOL_SIZE);

/**
 * Lazily bring up the Chromium process. Concurrent callers during
 * launch await the same in-flight promise. After a crash, the
 * generation counter ticks so any in-flight render that survives the
 * disconnect knows to fail rather than reuse a stale browser handle.
 */
async function getBrowser() {
    if (browserState.browser && browserState.browser.isConnected()) {
        return browserState.browser;
    }
    if (browserState.launching) {
        return browserState.launching;
    }
    browserState.launching = (async () => {
        const b = await chromium.launch();
        browserState.version = b.version();
        b.on('disconnected', () => {
            // Mark the slot empty; the next request triggers a relaunch.
            // In-flight renders fail their per-context awaits and return
            // 502 to the orchestrator.
            if (browserState.browser === b) {
                browserState.browser = null;
                browserState.generation++;
            }
        });
        browserState.browser = b;
        return b;
    })();
    try {
        return await browserState.launching;
    } finally {
        browserState.launching = null;
    }
}

async function renderOne(fixturePath, options) {
    const { viewport = VIEWPORT, timeoutMs = RENDER_TIMEOUT_MS } = options;
    const browser = await getBrowser();
    const launchGen = browserState.generation;
    const context = await browser.newContext({ viewport });
    try {
        const page = await context.newPage();
        await page.emulateMedia({ media: 'print' });
        await page.goto(pathToFileURL(fixturePath).href, {
            waitUntil: 'load',
            timeout: timeoutMs,
        });
        const pdf = await page.pdf(PDF_OPTIONS);
        if (browserState.generation !== launchGen) {
            // Browser was recycled while we were inside this render; the
            // bytes might be from a half-dead context. Bail.
            throw new Error('browser recycled during render');
        }
        return pdf;
    } finally {
        await context.close().catch(() => {});
    }
}

async function handleRender(req, res) {
    let body;
    try {
        body = await readJson(req);
    } catch (err) {
        return sendJson(res, 400, { error: `bad JSON body: ${err.message}` });
    }
    let fixture;
    try {
        fixture = validateFixture(body.fixture, WPT_ROOT);
    } catch (err) {
        return sendJson(res, err.status ?? 400, { error: err.message });
    }
    const cachePath = cachedPathFor(ENGINE, body.cache_key, CACHE_DIR);
    if (cachePath && existsSync(cachePath)) {
        if (req.headers['x-cache-probe'] === '1') {
            return sendJson(res, 304, { from_cache: true });
        }
        const bytes = readFileSync(cachePath);
        return sendJson(res, 200, {
            pdf_bytes_base64: bytes.toString('base64'),
            ms: 0,
            from_cache: true,
        });
    }
    if (req.headers['x-cache-probe'] === '1') {
        return sendJson(res, 404, { from_cache: false });
    }
    await semaphore.acquire();
    browserState.inFlight++;
    const tStart = Date.now();
    try {
        const pdf = await Promise.race([
            renderOne(fixture, {
                viewport: body.viewport,
                timeoutMs: body.timeout_ms,
            }),
            new Promise((_, reject) => setTimeout(
                () => reject(Object.assign(new Error('render timed out'), { status: 408 })),
                body.timeout_ms ?? RENDER_TIMEOUT_MS,
            )),
        ]);
        if (!pdf || pdf.length === 0) {
            return sendJson(res, 502, { error: 'empty PDF from chromium' });
        }
        if (cachePath) {
            try {
                writeCacheAtomically(cachePath, pdf);
            } catch (err) {
                // Cache write failures aren't fatal — the orchestrator
                // still gets the bytes, just no warm-cache benefit.
                process.stderr.write(`cache write failed: ${err.message}\n`);
            }
        }
        return sendJson(res, 200, {
            pdf_bytes_base64: Buffer.from(pdf).toString('base64'),
            ms: Date.now() - tStart,
            from_cache: false,
        });
    } catch (err) {
        const status = err.status ?? (err.message?.includes('recycled') ? 502 : 500);
        return sendJson(res, status, { error: err.message });
    } finally {
        browserState.inFlight--;
        semaphore.release();
    }
}

async function handleStatus(_req, res) {
    const browser = browserState.browser;
    const ready = browser !== null && browser.isConnected();
    return sendJson(res, 200, {
        engine: ENGINE,
        version: browserState.version,
        ready,
        pool: {
            size: semaphore.capacity,
            in_flight: browserState.inFlight,
            queued: semaphore.queued,
        },
    });
}

async function handleFlush(_req, res) {
    const b = browserState.browser;
    browserState.browser = null;
    browserState.generation++;
    if (b) {
        await b.close().catch(() => {});
    }
    res.statusCode = 204;
    res.end();
}

function readJson(req) {
    return new Promise((resolve, reject) => {
        let data = '';
        req.on('data', (chunk) => { data += chunk; });
        req.on('end', () => {
            if (data === '') {
                resolve({});
                return;
            }
            try {
                resolve(JSON.parse(data));
            } catch (err) {
                reject(err);
            }
        });
        req.on('error', reject);
    });
}

function sendJson(res, status, body) {
    res.statusCode = status;
    res.setHeader('content-type', 'application/json');
    res.end(JSON.stringify(body));
}

const server = createServer(async (req, res) => {
    try {
        if (req.method === 'GET' && req.url === '/status') {
            return await handleStatus(req, res);
        }
        if (req.method === 'POST' && req.url === '/render') {
            return await handleRender(req, res);
        }
        if (req.method === 'POST' && req.url === '/flush') {
            return await handleFlush(req, res);
        }
        return sendJson(res, 404, { error: 'unknown route' });
    } catch (err) {
        return sendJson(res, 500, { error: err.message ?? String(err) });
    }
});

server.listen(PORT, '0.0.0.0', () => {
    process.stderr.write(
        `chromium daemon listening on :${PORT} (pool=${POOL_SIZE}, wpt=${WPT_ROOT}, cache=${CACHE_DIR})\n`,
    );
    // Eagerly launch Chromium so /status reports `ready: true` to the
    // PHP-side availability probe before the first /render. The launch
    // takes ~300ms on a warm cache; we pay it once at startup instead
    // of stalling the first request. Failures are logged but
    // non-fatal — /render retries lazy-launch and surfaces the error
    // to the orchestrator if it still fails there.
    getBrowser().catch((err) => {
        process.stderr.write(`eager browser launch failed: ${err.message}\n`);
    });
});

// Graceful shutdown: SIGTERM from docker stop drains in-flight before
// killing the browser. SIGINT from a dev Ctrl-C does the same.
for (const sig of ['SIGTERM', 'SIGINT']) {
    process.on(sig, async () => {
        server.close();
        if (browserState.browser) {
            await browserState.browser.close().catch(() => {});
        }
        process.exit(0);
    });
}
