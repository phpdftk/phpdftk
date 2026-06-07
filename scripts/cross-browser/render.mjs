#!/usr/bin/env node

/**
 * One-shot HTML→PDF renderer for the cross-browser oracle.
 *
 * Usage:
 *   node render.mjs <engine> <test-file-path> [--output=path]
 *
 *   engine            — `chromium` | `firefox` | `webkit`
 *   test-file-path    — absolute path to an HTML / SVG / XHTML fixture.
 *   --output=path     — write the PDF here. When omitted, the PDF is
 *                       written to stdout.
 *
 * Engine acquisition matrix (see docs/plans/cross-browser-oracle.md):
 *
 *   chromium → Playwright `page.pdf()`. Works on macOS host and Linux.
 *   firefox  → Spawn the Playwright-bundled Firefox binary with
 *              `--headless --print-to-pdf=…`. Requires a real GL/Metal
 *              context; works in Linux Docker, fails on bare macOS.
 *   webkit   → Not implemented in Phase A2. Phase A3 ships either a
 *              Swift WKWebView wrapper (macOS) or a webkit2gtk binding
 *              (Linux).
 *
 * Exit codes:
 *   0 — PDF generated.
 *   1 — bad argv.
 *   2 — engine launch failed.
 *   3 — fixture render failed.
 *   4 — engine reported success but PDF was empty.
 *   5 — engine not implemented in this phase.
 *
 * Reference: docs/plans/cross-browser-oracle.md
 */

import { chromium } from 'playwright';
import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import process from 'node:process';
import { PDF_OPTIONS, VIEWPORT } from './print-options.mjs';

const ENGINES = new Set(['chromium', 'firefox', 'webkit']);

function parseArgs(argv) {
    if (argv.length < 4) {
        return null;
    }
    const engine = argv[2];
    const file = argv[3];
    let output = null;
    for (let i = 4; i < argv.length; i++) {
        if (argv[i].startsWith('--output=')) {
            output = argv[i].slice('--output='.length);
        }
    }
    if (!ENGINES.has(engine)) {
        return null;
    }
    return { engine, file, output };
}

function die(code, msg) {
    process.stderr.write(`render.mjs: ${msg}\n`);
    process.exit(code);
}

async function renderChromium(file) {
    let browser;
    try {
        browser = await chromium.launch();
    } catch (err) {
        die(2, `chromium launch failed: ${err.message}`);
    }
    try {
        const context = await browser.newContext({ viewport: VIEWPORT });
        const page = await context.newPage();
        await page.emulateMedia({ media: 'print' });
        await page.goto(pathToFileURL(file).href, { waitUntil: 'load' });
        const pdfBytes = await page.pdf(PDF_OPTIONS);
        await context.close();
        return pdfBytes;
    } catch (err) {
        die(3, `chromium render failed: ${err.message}`);
    } finally {
        await browser.close().catch(() => {});
    }
}

async function renderFirefox(file) {
    // Playwright's bundled Firefox is a custom patched build that strips
    // `--print-to-pdf` (and `page.pdf()` is Chromium-only anyway). We
    // shell out to upstream Firefox CLI installed alongside Playwright.
    // The image's Dockerfile installs Mozilla's official tarball at
    // /usr/local/bin/firefox-cli; on a host that runs Linux with
    // upstream Firefox in $PATH, that symlink resolves; on macOS host
    // this code path isn't reachable anyway (use render-docker.sh).
    const ffExe = process.env.FIREFOX_CLI ?? '/usr/local/bin/firefox-cli';
    if (!existsSync(ffExe)) {
        die(2, `firefox CLI not found at ${ffExe}. Run via scripts/cross-browser/render-docker.sh, or set FIREFOX_CLI to an upstream Firefox build.`);
    }
    const profileDir = mkdtempSync(join(tmpdir(), 'cross-browser-ff-'));
    const outPath = join(profileDir, 'out.pdf');
    // Firefox's headless renderer (SWGL) needs to attach to a display
    // target — on a true headless Linux container that means wrapping
    // with xvfb-run, which spins up a virtual X server in-process. The
    // wrapper is preinstalled in our Docker image; outside Docker we
    // expect FIREFOX_USE_XVFB=0 + a system that already has a display.
    const useXvfb = process.env.FIREFOX_USE_XVFB !== '0' && existsSync('/usr/bin/xvfb-run');
    const cmd = useXvfb ? '/usr/bin/xvfb-run' : ffExe;
    const cmdArgs = useXvfb
        ? ['--auto-servernum', '--server-args=-screen 0 1280x1024x24', ffExe,
            '--headless', '--no-remote', '-profile', profileDir,
            `--print-to-pdf=${outPath}`, pathToFileURL(file).href]
        : ['--headless', '--no-remote', '-profile', profileDir,
            `--print-to-pdf=${outPath}`, pathToFileURL(file).href];
    try {
        await spawnPromise(cmd, cmdArgs, { timeoutMs: 60000 });
        const pdfBytes = readFileSync(outPath);
        return pdfBytes;
    } catch (err) {
        die(3, `firefox render failed: ${err.message}`);
    } finally {
        rmSync(profileDir, { recursive: true, force: true });
    }
}

async function renderWebKit(file) {
    // WebKit's `WKWebView.createPDF()` is the only durable way to get
    // a PDF out of WebKit; Playwright's `page.pdf()` is Chromium-only,
    // and no Linux WebKit CLI ships `--print-to-pdf`. The Swift
    // wrapper at scripts/cross-browser/webkit-render.swift exposes it
    // as a one-shot CLI; compile once with
    //   swiftc -O webkit-render.swift -o /usr/local/bin/webkit-render
    // (or set WEBKIT_CLI to point at an existing build).
    //
    // macOS-only — Linux runners skip the WebKit engine and the
    // ConsensusScorer treats them as two-of-two.
    const wkExe = process.env.WEBKIT_CLI ?? '/usr/local/bin/webkit-render';
    if (!existsSync(wkExe)) {
        die(2, `webkit CLI not found at ${wkExe}. Build with \`swiftc -O scripts/cross-browser/webkit-render.swift -o /usr/local/bin/webkit-render\` (macOS) or set WEBKIT_CLI=path.`);
    }
    const profileDir = mkdtempSync(join(tmpdir(), 'cross-browser-wk-'));
    const outPath = join(profileDir, 'out.pdf');
    try {
        await spawnPromise(wkExe, [file, outPath], { timeoutMs: 60000 });
        return readFileSync(outPath);
    } catch (err) {
        die(3, `webkit render failed: ${err.message}`);
    } finally {
        rmSync(profileDir, { recursive: true, force: true });
    }
}

function spawnPromise(cmd, args, { timeoutMs }) {
    return new Promise((resolve, reject) => {
        const proc = spawn(cmd, args, { stdio: ['ignore', 'pipe', 'pipe'] });
        let stderr = '';
        proc.stderr.on('data', (chunk) => { stderr += chunk.toString('utf8'); });
        const timeout = setTimeout(() => {
            proc.kill('SIGKILL');
            reject(new Error(`spawn timeout after ${timeoutMs}ms`));
        }, timeoutMs);
        proc.on('error', (err) => {
            clearTimeout(timeout);
            reject(err);
        });
        proc.on('exit', (code, signal) => {
            clearTimeout(timeout);
            if (code === 0) {
                resolve();
                return;
            }
            const reason = signal ? `signal ${signal}` : `exit ${code}`;
            reject(new Error(`${reason}: ${stderr.trim() || '(no stderr)'}`));
        });
    });
}

const args = parseArgs(process.argv);
if (args === null) {
    die(1, 'usage: node render.mjs <chromium|firefox|webkit> <file> [--output=path]');
}

let pdfBytes;
switch (args.engine) {
    case 'chromium':
        pdfBytes = await renderChromium(args.file);
        break;
    case 'firefox':
        pdfBytes = await renderFirefox(args.file);
        break;
    case 'webkit':
        pdfBytes = await renderWebKit(args.file);
        break;
}

if (!pdfBytes || pdfBytes.length === 0) {
    die(4, `${args.engine} produced an empty PDF`);
}

if (args.output) {
    writeFileSync(args.output, pdfBytes);
} else {
    process.stdout.write(pdfBytes);
}
