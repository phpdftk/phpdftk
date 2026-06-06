#!/usr/bin/env node

/**
 * One-shot HTML→PDF renderer for the cross-browser oracle.
 *
 * Usage:
 *   node render.mjs <engine> <test-file-path> [--output=path]
 *
 *   engine            — `webkit` | `chromium` | `firefox`
 *   test-file-path    — absolute path to an HTML / SVG / XHTML fixture.
 *   --output=path     — write the PDF here. When omitted, the PDF is
 *                       written to stdout.
 *
 * Exit codes:
 *   0 — PDF generated.
 *   1 — bad argv.
 *   2 — engine launch failed.
 *   3 — fixture render failed (page navigation error, pdf() rejection).
 *   4 — engine reported success but PDF was empty.
 *
 * Reference: docs/plans/cross-browser-oracle.md
 */

import { chromium, firefox, webkit } from 'playwright';
import { writeFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import process from 'node:process';
import { PDF_OPTIONS, VIEWPORT } from './print-options.mjs';

const ENGINES = { webkit, chromium, firefox };

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
    if (!(engine in ENGINES)) {
        return null;
    }
    return { engine, file, output };
}

function die(code, msg) {
    process.stderr.write(`render.mjs: ${msg}\n`);
    process.exit(code);
}

const args = parseArgs(process.argv);
if (args === null) {
    die(1, 'usage: node render.mjs <webkit|chromium|firefox> <file> [--output=path]');
}

const driver = ENGINES[args.engine];
let browser;
try {
    browser = await driver.launch();
} catch (err) {
    die(2, `${args.engine} launch failed: ${err.message}`);
}

let pdfBytes;
try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    const page = await context.newPage();
    // Force-evaluate the print stylesheet, then issue pdf(). Playwright's
    // pdf() already runs media `print`, but emulateMedia makes layout /
    // intrinsic-size resolution happen against print first.
    await page.emulateMedia({ media: 'print' });
    const url = pathToFileURL(args.file).href;
    await page.goto(url, { waitUntil: 'load' });
    pdfBytes = await page.pdf(PDF_OPTIONS);
    await context.close();
} catch (err) {
    await browser.close().catch(() => {});
    die(3, `${args.engine} render failed: ${err.message}`);
}

await browser.close().catch(() => {});

if (!pdfBytes || pdfBytes.length === 0) {
    die(4, `${args.engine} produced an empty PDF`);
}

if (args.output) {
    writeFileSync(args.output, pdfBytes);
} else {
    process.stdout.write(pdfBytes);
}
