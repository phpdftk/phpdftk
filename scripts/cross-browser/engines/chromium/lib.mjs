/**
 * Pure-function helpers used by server.mjs. Pulled into their own
 * module so they can be exercised by `node --test` without booting
 * Playwright. Touching anything here means re-running the unit
 * suite — these primitives gate every request the daemon handles.
 */

import { existsSync, mkdirSync, renameSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, normalize } from 'node:path';
import { randomBytes } from 'node:crypto';

/**
 * Bounded-concurrency primitive. acquire() resolves when a slot is
 * free; release() returns the slot to the next waiter. We don't pool
 * BrowserContexts because Playwright creates+destroys them in ~5ms;
 * the bound that matters is concurrent renders (memory + CPU).
 */
export function createSemaphore(capacity) {
    if (!Number.isInteger(capacity) || capacity < 1) {
        throw new Error(`semaphore capacity must be a positive integer (got ${capacity})`);
    }
    let available = capacity;
    let queued = 0;
    const waiters = [];
    return {
        get capacity() { return capacity; },
        get available() { return available; },
        get queued() { return queued; },
        async acquire() {
            if (available > 0) {
                available--;
                return;
            }
            queued++;
            await new Promise((r) => waiters.push(r));
            queued--;
        },
        release() {
            const next = waiters.shift();
            if (next) {
                next();
            } else {
                available++;
            }
        },
    };
}

/**
 * Reject anything that isn't a real fixture under the WPT mount.
 * Returns the normalised path on success; throws `Error` with
 * `.status === 400` on rejection. The error message is included
 * verbatim in the daemon response so it's clear to the orchestrator.
 *
 * Rejection cases this catches:
 *   - Non-string / empty path
 *   - Path that doesn't start with the WPT root (after normalise, so
 *     `/wpt/../etc/passwd` collapses to `/etc/passwd` and is rejected)
 *   - Path that normalises to exactly the root (we need a file)
 *   - Path that doesn't exist on disk
 *
 * We do NOT resolve symlinks here — the daemon mounts the WPT corpus
 * read-only, so any symlink inside it is a corpus author's call. The
 * sandbox is the bind-mount itself, not symlink avoidance.
 */
export function validateFixture(rawPath, wptRoot) {
    if (typeof rawPath !== 'string' || rawPath.length === 0) {
        throw makeBadRequest('fixture path missing');
    }
    // Strip trailing slashes so `/wpt/` doesn't look like a different
    // path from `/wpt`. `normalize` preserves them; we don't want to.
    const root = stripTrailingSlash(normalize(wptRoot));
    const normalised = stripTrailingSlash(normalize(rawPath));
    if (normalised === root) {
        throw makeBadRequest('fixture path resolves to the WPT root, not a file');
    }
    if (!normalised.startsWith(root + '/')) {
        throw makeBadRequest(`fixture must be inside ${root}`);
    }
    if (!existsSync(normalised)) {
        throw makeBadRequest(`fixture not found: ${normalised}`);
    }
    // Reject directory POSTs explicitly — `/wpt/css/` is a real path
    // that exists but isn't a renderable fixture, and we don't want
    // it to silently 500 inside Playwright.
    if (!statSync(normalised).isFile()) {
        throw makeBadRequest(`fixture is not a regular file: ${normalised}`);
    }
    return normalised;
}

function stripTrailingSlash(p) {
    if (p.length > 1 && p.endsWith('/')) {
        return p.replace(/\/+$/, '');
    }
    return p;
}

function makeBadRequest(message) {
    const err = new Error(message);
    err.status = 400;
    return err;
}

/**
 * Compute the canonical cache file path for `(engine, cacheKey)`.
 * Returns null when the key isn't a plausible cache key — we treat a
 * malformed key as "no cache", not as an error, so a stray host shard
 * with a different scheme doesn't crash the daemon.
 *
 * The key shape is the first 64 hex chars of a SHA-256 hash, matching
 * the PHP-side `BrowserOracle::cacheKey()`.
 */
export function cachedPathFor(engine, cacheKey, cacheDir) {
    if (typeof cacheKey !== 'string' || !/^[a-f0-9]{32,128}$/i.test(cacheKey)) {
        return null;
    }
    if (typeof engine !== 'string' || !/^[a-z]+$/.test(engine)) {
        return null;
    }
    return join(cacheDir, engine, `${cacheKey}.pdf`);
}

/**
 * Write `bytes` to `finalPath` atomically via `tmp + rename`. Both
 * the daemon and the host PHP shards write to the same cache
 * directory; rename(2) is atomic on local FS for same-directory
 * targets so concurrent writers never observe a half-written file.
 */
export function writeCacheAtomically(finalPath, bytes) {
    const dir = dirname(finalPath);
    if (!existsSync(dir)) {
        mkdirSync(dir, { recursive: true });
    }
    const tmp = `${finalPath}.tmp.${randomBytes(4).toString('hex')}`;
    writeFileSync(tmp, bytes);
    renameSync(tmp, finalPath);
}
