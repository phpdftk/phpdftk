// WebKit driver for the cross-browser PDF oracle.
//
// Tiny CLI: load a file:// URL into a WKWebView, wait for navigation
// to finish, ask the view for a PDF, write it to a path. We can't
// drive WebKit from Node — there's no WKWebView equivalent of
// Playwright's `page.pdf()` outside Chromium — so this Swift binary
// is the engine-acquisition path for macOS hosts. CI runners that
// don't have Swift will skip the webkit engine (graceful fallback in
// render.mjs).
//
// Usage:
//   webkit-render <input-file> <output-pdf>
//
// Exit codes:
//   0 — PDF written
//   1 — bad argv
//   2 — file not found
//   3 — navigation failed
//   4 — pdf generation failed
//
// Build (manual / smoke):
//   swiftc -O webkit-render.swift -o /tmp/webkit-render
//
// Reference: docs/plans/cross-browser-oracle.md § Phase A3

import AppKit
import WebKit
import Foundation

let stderr = FileHandle.standardError

func die(_ code: Int32, _ message: String) -> Never {
    stderr.write("webkit-render: \(message)\n".data(using: .utf8)!)
    exit(code)
}

guard CommandLine.argc == 3 else {
    die(1, "usage: webkit-render <input-file> <output-pdf>")
}

let inputPath = CommandLine.arguments[1]
let outputPath = CommandLine.arguments[2]

guard FileManager.default.fileExists(atPath: inputPath) else {
    die(2, "input file not found: \(inputPath)")
}

let inputUrl = URL(fileURLWithPath: inputPath)
let parentDir = inputUrl.deletingLastPathComponent()

// Print-options contract — keep in lockstep with
// scripts/cross-browser/print-options.mjs.
//
// WKWebView's createPDF() writes the view frame as PDF points
// (no scale option), so view bounds == output PDF page extent.
// Setting view = 612×792 pt yields a Letter-sized PDF — same as
// Chromium's `format: 'Letter'` and our DefaultMediaBox.
//
// Caveat: the CSS layout viewport ends up at 612 CSS px wide
// (WKWebView treats 1 pt = 1 CSS px on macOS, and desktop WebKit
// doesn't honour `<meta name="viewport">` overrides). Chromium
// renders the same fixture at 816 CSS px wide. The viewport-meta
// injection below is best-effort — it matters for fixtures that
// inspect the meta directly, but doesn't move WebKit's layout
// viewport. Tracked as a known asymmetry in docs/plans/
// cross-browser-oracle.md § Phase B limitations.
let viewportSize = CGSize(width: 612, height: 792)
let layoutViewportCssPx: CGFloat = 816

final class Driver: NSObject, WKNavigationDelegate {
    let webView: WKWebView
    let outputPath: String
    var didFire = false

    init(outputPath: String) {
        self.outputPath = outputPath
        let configuration = WKWebViewConfiguration()
        configuration.suppressesIncrementalRendering = false

        // Two injections at document-start:
        //
        // 1. A `<meta name="viewport" content="width=N">` so WebKit's
        //    layout viewport is N CSS px wide regardless of the
        //    (smaller) view frame. This lets a Letter-sized
        //    (612 pt) WKWebView lay out content at the canonical
        //    816 CSS px (Letter at 96 px/in).
        // 2. A zero-margin `@page` rule + `body { margin: 0 }` to
        //    mirror Playwright's `preferCSSPageSize: true` + margin
        //    0 contract.
        let layoutWidth = Int(layoutViewportCssPx)
        let pageCss = "@page { size: 8.5in 11in; margin: 0; } "
            + "@media print { html, body { margin: 0; } }"
        let escaped = pageCss
            .replacingOccurrences(of: "\\", with: "\\\\")
            .replacingOccurrences(of: "\"", with: "\\\"")
        let injection = """
        (function () {
            var meta = document.createElement('meta');
            meta.name = 'viewport';
            meta.content = 'width=\(layoutWidth)';
            (document.head || document.documentElement).appendChild(meta);
            var s = document.createElement('style');
            s.appendChild(document.createTextNode("\(escaped)"));
            (document.head || document.documentElement).appendChild(s);
        })();
        """
        let script = WKUserScript(
            source: injection,
            injectionTime: .atDocumentStart,
            forMainFrameOnly: true
        )
        configuration.userContentController.addUserScript(script)

        self.webView = WKWebView(
            frame: CGRect(origin: .zero, size: viewportSize),
            configuration: configuration
        )
        super.init()
        self.webView.navigationDelegate = self
    }

    func load(url: URL, readAccessTo: URL) {
        webView.loadFileURL(url, allowingReadAccessTo: readAccessTo)
    }

    func webView(
        _ webView: WKWebView,
        didFinish navigation: WKNavigation!
    ) {
        guard !didFire else { return }
        didFire = true
        // Small delay lets late layout / web fonts settle. 200 ms is
        // empirical; mirrors what Playwright's page.pdf() waits for.
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.2) {
            self.emitPdf()
        }
    }

    func webView(
        _ webView: WKWebView,
        didFail navigation: WKNavigation!,
        withError error: Error
    ) {
        die(3, "navigation failed: \(error.localizedDescription)")
    }

    func webView(
        _ webView: WKWebView,
        didFailProvisionalNavigation navigation: WKNavigation!,
        withError error: Error
    ) {
        die(3, "provisional navigation failed: \(error.localizedDescription)")
    }

    func emitPdf() {
        let config = WKPDFConfiguration()
        // rect=nil → WebKit captures the full content extent, paginated.
        // Setting an explicit rect forces a single-page snapshot of that
        // rect; rect=nil is what we want for paginated print output.
        config.rect = nil
        webView.createPDF(configuration: config) { result in
            switch result {
            case .failure(let error):
                die(4, "createPDF failed: \(error.localizedDescription)")
            case .success(let pdfData):
                do {
                    try pdfData.write(to: URL(fileURLWithPath: self.outputPath))
                    exit(0)
                } catch {
                    die(4, "write failed: \(error.localizedDescription)")
                }
            }
        }
    }
}

// WKWebView requires a hosting NSApplication on macOS — without one
// the navigation delegate's callbacks never fire. We run a minimal
// in-process AppKit loop just long enough to satisfy that constraint.
let app = NSApplication.shared
app.setActivationPolicy(.accessory)

let driver = Driver(outputPath: outputPath)
driver.load(url: inputUrl, readAccessTo: parentDir)

// Safety net: hard-exit if WebKit never fires the navigation delegate.
DispatchQueue.main.asyncAfter(deadline: .now() + 30.0) {
    die(3, "navigation timed out after 30s")
}

app.run()
