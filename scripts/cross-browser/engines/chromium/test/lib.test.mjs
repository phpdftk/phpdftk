/**
 * Pure-function tests for the daemon's primitives. These don't launch
 * Chromium — they exercise the path-validation / cache-key /
 * semaphore code paths that gate every request before any browser
 * work happens. Run via `node --test test/`.
 *
 * Bias is heavily toward negative cases — these are the boundary
 * functions that protect the fixture sandbox and the cache layout, so
 * we want every rejection path explicitly covered.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import {
    cachedPathFor,
    createSemaphore,
    validateFixture,
    writeCacheAtomically,
} from '../lib.mjs';

// ---------------------------------------------------------------------
// createSemaphore
// ---------------------------------------------------------------------

test('createSemaphore: rejects zero capacity', () => {
    assert.throws(() => createSemaphore(0), /positive integer/);
});

test('createSemaphore: rejects negative capacity', () => {
    assert.throws(() => createSemaphore(-1), /positive integer/);
});

test('createSemaphore: rejects non-integer capacity', () => {
    assert.throws(() => createSemaphore(1.5), /positive integer/);
});

test('createSemaphore: rejects string capacity', () => {
    assert.throws(() => createSemaphore('3'), /positive integer/);
});

test('createSemaphore: allows N concurrent acquires, blocks N+1th', async () => {
    const sem = createSemaphore(2);
    await sem.acquire();
    await sem.acquire();
    assert.equal(sem.available, 0);

    let thirdResolved = false;
    const third = sem.acquire().then(() => { thirdResolved = true; });
    // Yield twice to give the unrelated tick a chance to land.
    await new Promise((r) => setImmediate(r));
    await new Promise((r) => setImmediate(r));
    assert.equal(thirdResolved, false, 'third acquire must block while capacity is 0');
    assert.equal(sem.queued, 1);

    sem.release();
    await third;
    assert.equal(thirdResolved, true);
    assert.equal(sem.queued, 0);
});

test('createSemaphore: release with no waiters bumps available count', () => {
    const sem = createSemaphore(2);
    assert.equal(sem.available, 2);
    sem.release(); // over-release; intentional — daemon code never does this but we'd rather it not blow up if a bug regresses
    assert.equal(sem.available, 3);
});

// ---------------------------------------------------------------------
// validateFixture
// ---------------------------------------------------------------------

const tmpRoot = mkdtempSync(join(tmpdir(), 'chromium-daemon-test-'));
const wptRoot = join(tmpRoot, 'wpt');
const validFixture = join(wptRoot, 'css', 'flex.html');
const validDeep = join(wptRoot, 'a', 'b', 'c', 'd.html');
{
    const { mkdirSync } = await import('node:fs');
    mkdirSync(join(wptRoot, 'css'), { recursive: true });
    mkdirSync(join(wptRoot, 'a', 'b', 'c'), { recursive: true });
    writeFileSync(validFixture, '<html></html>');
    writeFileSync(validDeep, '<html></html>');
}

test('validateFixture: rejects non-string input', () => {
    assert.throws(
        () => validateFixture(null, wptRoot),
        (e) => e.status === 400 && /missing/.test(e.message),
    );
});

test('validateFixture: rejects undefined input', () => {
    assert.throws(
        () => validateFixture(undefined, wptRoot),
        (e) => e.status === 400 && /missing/.test(e.message),
    );
});

test('validateFixture: rejects empty string', () => {
    assert.throws(
        () => validateFixture('', wptRoot),
        (e) => e.status === 400,
    );
});

test('validateFixture: rejects number input', () => {
    assert.throws(
        () => validateFixture(42, wptRoot),
        (e) => e.status === 400,
    );
});

test('validateFixture: rejects path outside WPT root', () => {
    assert.throws(
        () => validateFixture('/etc/passwd', wptRoot),
        (e) => e.status === 400 && /must be inside/.test(e.message),
    );
});

test('validateFixture: rejects parent-directory escape via ..', () => {
    // normalize collapses /wpt/foo/../../etc/passwd into /etc/passwd
    const escape = join(wptRoot, '..', '..', 'etc', 'passwd');
    assert.throws(
        () => validateFixture(escape, wptRoot),
        (e) => e.status === 400,
    );
});

test('validateFixture: rejects path equal to WPT root', () => {
    assert.throws(
        () => validateFixture(wptRoot, wptRoot),
        (e) => e.status === 400 && /root/.test(e.message),
    );
});

test('validateFixture: rejects nonexistent file inside root', () => {
    assert.throws(
        () => validateFixture(join(wptRoot, 'no-such-file.html'), wptRoot),
        (e) => e.status === 400 && /not found/.test(e.message),
    );
});

test('validateFixture: rejects a path that is the WPT root with a trailing slash but no file', () => {
    assert.throws(
        () => validateFixture(wptRoot + '/', wptRoot),
        (e) => e.status === 400,
    );
});

test('validateFixture: accepts a real file under the root', () => {
    assert.equal(validateFixture(validFixture, wptRoot), validFixture);
});

test('validateFixture: accepts a deeply nested real file', () => {
    assert.equal(validateFixture(validDeep, wptRoot), validDeep);
});

test('validateFixture: accepts a normalised-equivalent path with redundant /./ segments', () => {
    const noisy = join(wptRoot, 'css', '.', 'flex.html');
    assert.equal(validateFixture(noisy, wptRoot), validFixture);
});

// ---------------------------------------------------------------------
// cachedPathFor
// ---------------------------------------------------------------------

const goodKey = 'a'.repeat(64);
const cacheRoot = join(tmpRoot, 'cache');

test('cachedPathFor: rejects non-string cache key', () => {
    assert.equal(cachedPathFor('chromium', null, cacheRoot), null);
});

test('cachedPathFor: rejects empty cache key', () => {
    assert.equal(cachedPathFor('chromium', '', cacheRoot), null);
});

test('cachedPathFor: rejects too-short hex key', () => {
    assert.equal(cachedPathFor('chromium', 'abc123', cacheRoot), null);
});

test('cachedPathFor: rejects key with non-hex characters', () => {
    assert.equal(
        cachedPathFor('chromium', goodKey.slice(0, 63) + 'z', cacheRoot),
        null,
    );
});

test('cachedPathFor: rejects shell-injection attempt in key', () => {
    assert.equal(cachedPathFor('chromium', '../../etc/passwd', cacheRoot), null);
});

test('cachedPathFor: rejects path-separator in key', () => {
    assert.equal(
        cachedPathFor('chromium', 'a'.repeat(32) + '/' + 'b'.repeat(32), cacheRoot),
        null,
    );
});

test('cachedPathFor: rejects engine name with dots', () => {
    assert.equal(cachedPathFor('../other', goodKey, cacheRoot), null);
});

test('cachedPathFor: rejects empty engine name', () => {
    assert.equal(cachedPathFor('', goodKey, cacheRoot), null);
});

test('cachedPathFor: rejects engine with hyphens or digits', () => {
    assert.equal(cachedPathFor('chromium-1', goodKey, cacheRoot), null);
});

test('cachedPathFor: returns joined path for a valid 64-hex key', () => {
    const got = cachedPathFor('chromium', goodKey, cacheRoot);
    assert.equal(got, join(cacheRoot, 'chromium', goodKey + '.pdf'));
});

test('cachedPathFor: accepts a longer (e.g. 96-char) hex key', () => {
    const longKey = 'b'.repeat(96);
    const got = cachedPathFor('webkit', longKey, cacheRoot);
    assert.equal(got, join(cacheRoot, 'webkit', longKey + '.pdf'));
});

// ---------------------------------------------------------------------
// writeCacheAtomically
// ---------------------------------------------------------------------

test('writeCacheAtomically: creates parent dir when missing', () => {
    const target = join(tmpRoot, 'fresh-dir', 'chromium', goodKey + '.pdf');
    writeCacheAtomically(target, Buffer.from('hello'));
    assert.equal(existsSync(target), true);
    assert.equal(readFileSync(target, 'utf8'), 'hello');
});

test('writeCacheAtomically: leaves no .tmp.* file behind on success', () => {
    const dir = join(tmpRoot, 'leftover-check');
    const target = join(dir, 'a' + '0'.repeat(63) + '.pdf');
    writeCacheAtomically(target, Buffer.from('x'));
    const entries = readdirSync(dir);
    const leftover = entries.filter((n) => n.includes('.tmp.'));
    assert.deepEqual(leftover, [], `expected no .tmp.* leftovers, got ${leftover.join(', ')}`);
});

test('writeCacheAtomically: overwrites an existing file in place', () => {
    const target = join(tmpRoot, 'overwrite-test', 'k.pdf');
    writeCacheAtomically(target, Buffer.from('first'));
    writeCacheAtomically(target, Buffer.from('second'));
    assert.equal(readFileSync(target, 'utf8'), 'second');
});

// Clean up the per-test tmp tree once all tests have queued.
test.after(() => {
    rmSync(tmpRoot, { recursive: true, force: true });
});
