#!/usr/bin/env node

/**
 * Persistent Firefox render daemon — drives geckodriver's WebDriver
 * Print endpoint instead of forking `node render.mjs firefox` per
 * fixture. One geckodriver, one Firefox session, a window-handle
 * free-list sized to the host's parallelism. Each render claims a
 * handle, navigates that window, prints to PDF, releases the handle.
 *
 * Protocol — defined in docs/plans/cross-browser-container-sweep.md
 * § "Daemon protocol", same shape as the chromium daemon (#31):
 *
 *   GET  /status   → 200 { engine, version, ready, pool }
 *   POST /render   → 200 { pdf_bytes_base64, ms, from_cache }
 *                  | 304 { from_cache: true }   (X-Cache-Probe: 1)
 *                  | 4xx { error } on bad input
 *                  | 408 { error } on timeout
 *                  | 502 { error } during a geckodriver-crash window
 *   POST /flush    → 204 (drain in-flight, restart geckodriver+session)
 *
 * Configuration via env:
 *
 *   PORT              — HTTP port (default 9102)
 *   POOL_SIZE         — window-pool size (default min(cores−2, 16))
 *   WPT_ROOT          — fixture sandbox root (default /wpt)
 *   CACHE_DIR         — shared PDF cache (default /var/cache/browser)
 *   RENDER_TIMEOUT_MS — per-render budget (default 60000)
 *   FIREFOX_BIN       — Firefox CLI binary (default /usr/local/bin/firefox-cli)
 *   GECKODRIVER_BIN   — geckodriver binary (default /usr/local/bin/geckodriver)
 *
 * Reference: docs/plans/cross-browser-container-sweep.md § Phase P1
 */

import { createServer } from 'node:http';
import { availableParallelism } from 'node:os';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { spawn } from 'node:child_process';
import { createServer as createNetServer } from 'node:net';
import process from 'node:process';
import { FIREFOX_PREFS, PRINT_OPTIONS } from './print-options.mjs';
import {
    cachedPathFor,
    createSemaphore,
    validateFixture,
    writeCacheAtomically,
} from './lib.mjs';

const ENGINE = 'firefox';
const PORT = Number(process.env.PORT ?? 9102);
const POOL_SIZE = Number(
    process.env.POOL_SIZE ?? Math.min(Math.max(availableParallelism() - 2, 1), 16),
);
const WPT_ROOT = resolve(process.env.WPT_ROOT ?? '/wpt');
const CACHE_DIR = resolve(process.env.CACHE_DIR ?? '/var/cache/browser');
const RENDER_TIMEOUT_MS = Number(process.env.RENDER_TIMEOUT_MS ?? 60000);
const FIREFOX_BIN = process.env.FIREFOX_BIN ?? '/usr/local/bin/firefox-cli';
const GECKODRIVER_BIN = process.env.GECKODRIVER_BIN ?? '/usr/local/bin/geckodriver';

/**
 * Daemon state. `windows` is a free-list of window handles claimed
 * one-per-render via the semaphore; `inFlight` tracks the renders
 * currently holding a handle for /status reporting.
 *
 * `generation` ticks every time we restart geckodriver — in-flight
 * renders compare against the generation they started under and bail
 * if the session was swapped out mid-render rather than returning
 * bytes from a half-dead window.
 */
const state = {
    geckoProc: null,
    geckoPort: null,
    sessionId: null,
    version: null,
    windows: [],   // available window handles (free-list)
    inFlight: 0,
    generation: 0,
    starting: null,
};

const semaphore = createSemaphore(POOL_SIZE);

// ---------------------------------------------------------------------
// Geckodriver lifecycle
// ---------------------------------------------------------------------

/**
 * Eagerly bring up geckodriver + Firefox session + window pool. Run
 * once at startup; concurrent callers during a restart await the
 * same in-flight promise. Resolves to the live sessionId.
 */
async function ensureSession() {
    if (state.sessionId !== null && state.geckoProc !== null) {
        return state.sessionId;
    }
    if (state.starting) {
        return state.starting;
    }
    state.starting = (async () => {
        if (!existsSync(FIREFOX_BIN)) {
            throw new Error(`firefox binary not found at ${FIREFOX_BIN}`);
        }
        if (!existsSync(GECKODRIVER_BIN)) {
            throw new Error(`geckodriver not found at ${GECKODRIVER_BIN}`);
        }
        const port = await pickEphemeralPort();
        state.geckoPort = port;
        const proc = spawn(GECKODRIVER_BIN, [
            `--port=${port}`,
            '--log=warn',
            '--allow-hosts=127.0.0.1',
        ], { stdio: ['ignore', 'pipe', 'pipe'] });
        let stderr = '';
        proc.stderr.on('data', (c) => { stderr += c.toString('utf8'); });
        proc.on('exit', (code) => {
            // Reset state so the next request re-ensures.
            if (state.geckoProc === proc) {
                state.geckoProc = null;
                state.sessionId = null;
                state.windows = [];
                state.generation++;
                process.stderr.write(
                    `geckodriver exited (code ${code}); will relaunch on next request\n`,
                );
            }
        });
        state.geckoProc = proc;

        try {
            await waitForGeckodriver(port, 10_000, proc);
            const sessionRes = await fetchJson(
                `http://127.0.0.1:${port}/session`,
                {
                    method: 'POST',
                    body: {
                        capabilities: {
                            alwaysMatch: {
                                browserName: 'firefox',
                                'moz:firefoxOptions': {
                                    binary: FIREFOX_BIN,
                                    args: ['--headless'],
                                    prefs: FIREFOX_PREFS,
                                },
                            },
                        },
                    },
                },
            );
            state.sessionId = sessionRes.value.sessionId;
            state.version = sessionRes.value.capabilities?.browserVersion ?? null;

            // Default window — comes free with the session.
            const handleRes = await fetchJson(
                `http://127.0.0.1:${port}/session/${state.sessionId}/window`,
                { method: 'GET' },
            );
            state.windows = [handleRes.value];

            // Pre-allocate the rest of the pool. POST /window/new can
            // be `type: "tab"` (same window) or `type: "window"` (new
            // top-level browser window). Tabs are cheaper. Tab handles
            // are reachable via `POST /session/:id/window` switch the
            // same way top-level windows are.
            for (let i = 1; i < POOL_SIZE; i++) {
                const newRes = await fetchJson(
                    `http://127.0.0.1:${port}/session/${state.sessionId}/window/new`,
                    {
                        method: 'POST',
                        body: { type: 'tab' },
                    },
                );
                state.windows.push(newRes.value.handle);
            }

            return state.sessionId;
        } catch (err) {
            // Tear down a half-built session so the next request gets a
            // clean slate.
            try { proc.kill('SIGTERM'); } catch { /* ignore */ }
            state.geckoProc = null;
            state.sessionId = null;
            state.windows = [];
            const detail = stderr.split('\n').slice(-5).join(' | ');
            throw new Error(`geckodriver/session bring-up failed: ${err.message}${detail ? ` (stderr: ${detail})` : ''}`);
        }
    })();
    try {
        return await state.starting;
    } finally {
        state.starting = null;
    }
}

function pickEphemeralPort() {
    return new Promise((resolve, reject) => {
        const srv = createNetServer();
        srv.listen(0, '127.0.0.1', () => {
            const { port } = srv.address();
            srv.close(() => resolve(port));
        });
        srv.on('error', reject);
    });
}

/**
 * Poll geckodriver's /status until it returns ready, or bail. Also
 * surfaces an early-exit from geckodriver (e.g. binary mismatch).
 */
async function waitForGeckodriver(port, timeoutMs, proc) {
    const deadline = Date.now() + timeoutMs;
    let lastErr = null;
    while (Date.now() < deadline) {
        if (proc.exitCode !== null) {
            throw new Error(`geckodriver exited early (code ${proc.exitCode})`);
        }
        try {
            const res = await fetch(`http://127.0.0.1:${port}/status`, {
                signal: AbortSignal.timeout(1000),
            });
            if (res.ok) {
                const body = await res.json();
                if (body.value?.ready === true) {
                    return;
                }
            }
        } catch (err) {
            lastErr = err;
        }
        await new Promise((r) => setTimeout(r, 100));
    }
    throw new Error(`geckodriver /status not ready within ${timeoutMs}ms: ${lastErr?.message ?? 'unknown'}`);
}

async function fetchJson(url, { method, body }) {
    const res = await fetch(url, {
        method,
        headers: { 'content-type': 'application/json' },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    const text = await res.text();
    if (!res.ok) {
        throw new Error(`${method} ${url} → HTTP ${res.status}: ${text.slice(0, 300)}`);
    }
    try {
        return JSON.parse(text);
    } catch {
        throw new Error(`${method} ${url} returned non-JSON: ${text.slice(0, 300)}`);
    }
}

// ---------------------------------------------------------------------
// Render path
// ---------------------------------------------------------------------

async function renderOne(fixturePath, options) {
    const { timeoutMs = RENDER_TIMEOUT_MS } = options;
    const sessionId = await ensureSession();
    const launchGen = state.generation;
    const port = state.geckoPort;

    // Claim a window from the free-list. Semaphore has already gated
    // us, so there's always a handle waiting — but we still defensive-
    // check because a crashed-and-respawned session re-allocates them.
    const handle = state.windows.pop();
    if (handle === undefined) {
        throw new Error('no window handles available (session restart in flight)');
    }
    try {
        await fetchJson(
            `http://127.0.0.1:${port}/session/${sessionId}/window`,
            { method: 'POST', body: { handle } },
        );
        await fetchJson(
            `http://127.0.0.1:${port}/session/${sessionId}/url`,
            {
                method: 'POST',
                body: { url: pathToFileURL(fixturePath).href },
            },
        );
        const printRes = await fetchJson(
            `http://127.0.0.1:${port}/session/${sessionId}/print`,
            { method: 'POST', body: PRINT_OPTIONS },
        );
        if (state.generation !== launchGen) {
            throw new Error('session restarted during render');
        }
        if (typeof printRes.value !== 'string' || printRes.value.length === 0) {
            throw new Error('geckodriver returned empty print payload');
        }
        return Buffer.from(printRes.value, 'base64');
    } finally {
        // Return the handle so the next claimer gets it. If the
        // session was restarted while we held it, the handle is stale
        // and we drop it on the floor — the restart already
        // re-allocated a fresh pool.
        if (state.generation === launchGen) {
            state.windows.push(handle);
        }
    }
}

// ---------------------------------------------------------------------
// HTTP handlers
// ---------------------------------------------------------------------

async function handleStatus(_req, res) {
    const ready = state.sessionId !== null && state.geckoProc !== null;
    return sendJson(res, 200, {
        engine: ENGINE,
        version: state.version,
        ready,
        pool: {
            size: semaphore.capacity,
            in_flight: state.inFlight,
            queued: semaphore.queued,
        },
    });
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
    state.inFlight++;
    const tStart = Date.now();
    try {
        const pdf = await Promise.race([
            renderOne(fixture, { timeoutMs: body.timeout_ms }),
            new Promise((_, reject) => setTimeout(
                () => reject(Object.assign(new Error('render timed out'), { status: 408 })),
                body.timeout_ms ?? RENDER_TIMEOUT_MS,
            )),
        ]);
        if (!pdf || pdf.length === 0) {
            return sendJson(res, 502, { error: 'empty PDF from firefox' });
        }
        if (cachePath) {
            try {
                writeCacheAtomically(cachePath, pdf);
            } catch (err) {
                process.stderr.write(`cache write failed: ${err.message}\n`);
            }
        }
        return sendJson(res, 200, {
            pdf_bytes_base64: pdf.toString('base64'),
            ms: Date.now() - tStart,
            from_cache: false,
        });
    } catch (err) {
        const status = err.status
            ?? (err.message?.includes('session restarted') ? 502 : 500);
        return sendJson(res, status, { error: err.message });
    } finally {
        state.inFlight--;
        semaphore.release();
    }
}

async function handleFlush(_req, res) {
    const proc = state.geckoProc;
    const sessionId = state.sessionId;
    const port = state.geckoPort;
    state.geckoProc = null;
    state.sessionId = null;
    state.windows = [];
    state.generation++;
    if (sessionId !== null && port !== null) {
        // Best-effort session deletion; if geckodriver is dying anyway
        // the DELETE may 500, which is fine.
        await fetch(`http://127.0.0.1:${port}/session/${sessionId}`, {
            method: 'DELETE',
        }).catch(() => {});
    }
    if (proc !== null) {
        proc.kill('SIGTERM');
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

// ---------------------------------------------------------------------
// HTTP server bring-up
// ---------------------------------------------------------------------

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
        `firefox daemon listening on :${PORT} (pool=${POOL_SIZE}, wpt=${WPT_ROOT}, cache=${CACHE_DIR})\n`,
    );
    // Eagerly bring up geckodriver + session so /status flips to
    // ready=true before the first /render. Failures during this
    // initial start are logged but non-fatal; /render attempts the
    // same bring-up lazily and surfaces the error to the orchestrator.
    ensureSession().catch((err) => {
        process.stderr.write(`eager session bring-up failed: ${err.message}\n`);
    });
});

for (const sig of ['SIGTERM', 'SIGINT']) {
    process.on(sig, async () => {
        server.close();
        try {
            if (state.sessionId !== null && state.geckoPort !== null) {
                await fetch(
                    `http://127.0.0.1:${state.geckoPort}/session/${state.sessionId}`,
                    { method: 'DELETE' },
                ).catch(() => {});
            }
            if (state.geckoProc !== null) {
                state.geckoProc.kill('SIGTERM');
            }
        } finally {
            process.exit(0);
        }
    });
}
