#!/usr/bin/env python3

"""
Persistent WebKit (webkit2gtk) PDF render daemon for the cross-browser
oracle. Real upstream WebKit, NOT Blink — Igalia maintains
webkit2gtk in-tree with Apple's WebKit, and this is the only durable
way to get a WebKit-rendered PDF on Linux. (See
docs/plans/cross-browser-container-sweep.md § Engine matrix for why
Opera / WPE / Epiphany / wkhtmltopdf were all ruled out.)

Protocol — defined in docs/plans/cross-browser-container-sweep.md
§ "Daemon protocol", same shape as #31 (chromium) and #32 (firefox):

  GET  /status   → 200 { engine, version, ready, pool }
  POST /render   → 200 { pdf_bytes_base64, ms, from_cache }
                 | 304 { from_cache: true }   (X-Cache-Probe: 1)
                 | 4xx { error } on bad input
                 | 408 { error } on render timeout
                 | 502 { error } during a webkit-crash window
  POST /flush    → 204 (drain in-flight)

Configuration via env:

  PORT              — HTTP port (default 9103)
  POOL_SIZE         — concurrent /render cap (default 1; see note)
  WPT_ROOT          — fixture sandbox root (default /wpt)
  CACHE_DIR         — shared PDF cache (default /var/cache/browser)
  RENDER_TIMEOUT_MS — per-render budget (default 60000)

POOL_SIZE note: webkit2gtk widgets (WebView, PrintOperation) have
strict main-thread affinity. v1 of this daemon runs a single
WebKitWebView with a render lock; concurrent requests serialize.
Multi-view pooling (parallel loads, serialised print) is a real
follow-up but is non-trivial because each WebView still has to be
created and driven from the GLib main thread. Documented in the PR
that introduces this file.

Reference: docs/plans/cross-browser-container-sweep.md § Phase P2
"""

from __future__ import annotations

import base64
import json
import multiprocessing
import os
import sys
import tempfile
import threading
import time
import uuid
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import PurePosixPath
from urllib.parse import urlparse

import gi
gi.require_version("WebKit2", "4.1")
gi.require_version("Gtk", "3.0")
from gi.repository import GLib, Gtk, WebKit2  # noqa: E402

from lib import (  # noqa: E402
    BadRequest,
    Semaphore,
    cached_path_for,
    validate_fixture,
    write_cache_atomically,
)
from print_options import (  # noqa: E402
    MARGIN_BOTTOM_INCHES,
    MARGIN_LEFT_INCHES,
    MARGIN_RIGHT_INCHES,
    MARGIN_TOP_INCHES,
    PAPER_HEIGHT_INCHES,
    PAPER_WIDTH_INCHES,
    PRINT_SETTING_OUTPUT_FILE_FORMAT,
    PRINT_SETTING_OUTPUT_FORMAT_VALUE_PDF,
    PRINT_SETTING_OUTPUT_URI,
    WINDOW_HEIGHT,
    WINDOW_WIDTH,
)


ENGINE = "webkit"
PORT = int(os.environ.get("PORT", "9103"))
POOL_SIZE = int(os.environ.get(
    "POOL_SIZE", str(max(1, min(multiprocessing.cpu_count() - 2, 16))),
))
WPT_ROOT = os.path.realpath(os.environ.get("WPT_ROOT", "/wpt"))
CACHE_DIR = os.path.realpath(os.environ.get("CACHE_DIR", "/var/cache/browser"))
RENDER_TIMEOUT_MS = int(os.environ.get("RENDER_TIMEOUT_MS", "60000"))


# ---------------------------------------------------------------------
# Daemon state — single WebKitWebView, all WebKit work serialised
# through the GLib main thread.
# ---------------------------------------------------------------------

class State:
    """Module-level container — easier than threading globals."""

    def __init__(self):
        self.view = None
        self.window = None  # GtkOffscreenWindow that owns the view
        self.version = None
        self.ready = False
        self.generation = 0
        self.in_flight = 0
        # Main-loop work submission lock — only one render builds its
        # PrintOperation at a time. Concurrent requests above the
        # semaphore are queued at the HTTP layer.
        self.render_lock = threading.Lock()

state = State()
# HTTP-layer concurrency cap. Even if POOL_SIZE > 1 the render_lock
# above serialises actual WebKit work; the semaphore just bounds the
# queue depth so we don't pile up unbounded handler threads.
semaphore = Semaphore(POOL_SIZE)


# ---------------------------------------------------------------------
# WebKit initialisation — runs on the GLib main thread
# ---------------------------------------------------------------------

def init_webkit():
    """Create the shared WebKitWebView. Called once from the main
    thread before the HTTP server starts.

    The WebView is parented to a GtkOffscreenWindow because WebKit
    expects its containing widget to be realised before navigation
    works — Gtk.Window would need an X server connection that xvfb
    provides, but we don't want a visible window. GtkOffscreenWindow
    gives us a realised parent without rendering to a display.
    """
    context = WebKit2.WebContext.get_default()
    state.view = WebKit2.WebView.new_with_context(context)
    state.window = Gtk.OffscreenWindow()
    state.window.set_default_size(WINDOW_WIDTH, WINDOW_HEIGHT)
    state.window.add(state.view)
    state.window.show_all()
    state.version = _detect_version()
    state.ready = True
    sys.stderr.write(f"webkit ready (version={state.version})\n")
    sys.stderr.flush()


def _detect_version():
    """webkit2gtk doesn't expose a canonical 'engine version' attr
    on the view — it lives on the JS runtime config. Easiest: ask
    WebKit2 module directly for its major+minor+micro."""
    try:
        return ".".join(str(x) for x in (
            WebKit2.get_major_version(),
            WebKit2.get_minor_version(),
            WebKit2.get_micro_version(),
        ))
    except Exception:
        return None


# ---------------------------------------------------------------------
# Render path — handler thread submits work, main thread does WebKit
# ---------------------------------------------------------------------

def render_blocking(fixture_path, timeout_s):
    """Drive a single render synchronously from a handler thread.

    Submits the WebKit calls to the GLib main loop via idle_add and
    blocks on a threading.Event until the main thread signals
    completion (success or failure).
    """
    with state.render_lock:
        gen_at_start = state.generation
        result = {"bytes": None, "error": None}
        done = threading.Event()
        output_path = os.path.join(
            tempfile.gettempdir(), f"wk-render-{uuid.uuid4().hex}.pdf",
        )

        def kick():
            try:
                _start_render(fixture_path, output_path, result, done)
            except Exception as exc:
                result["error"] = f"kick failed: {exc}"
                done.set()
            return GLib.SOURCE_REMOVE

        GLib.idle_add(kick)

        if not done.wait(timeout=timeout_s):
            try:
                state.view.stop_loading()
            except Exception:
                pass
            raise TimeoutError("render exceeded budget")
        if state.generation != gen_at_start:
            raise RuntimeError("session restarted during render")
        if result["error"]:
            raise RuntimeError(result["error"])
        if not result["bytes"]:
            raise RuntimeError("render produced no bytes")
        try:
            os.unlink(output_path)
        except FileNotFoundError:
            pass
        return result["bytes"]


def _start_render(fixture_path, output_path, result, done):
    """Run on the GLib main thread. Connects WebKit signal handlers
    and kicks off the page load. Subsequent steps (print build,
    signal hookup, output read) all run from inside the signal
    callbacks, also on the main thread."""
    view = state.view

    handlers = []

    def cleanup():
        for h in handlers:
            try:
                view.disconnect(h)
            except Exception:
                pass

    def on_load_failed(_view, _event, failing_uri, error):
        cleanup()
        result["error"] = f"load failed: {error.message} ({failing_uri})"
        done.set()
        return False  # propagate

    def on_load_changed(_view, load_event):
        if load_event == WebKit2.LoadEvent.FINISHED:
            try:
                _start_print(view, output_path, result, done, cleanup)
            except Exception as exc:
                cleanup()
                result["error"] = f"print build failed: {exc}"
                done.set()

    handlers.append(view.connect("load-changed", on_load_changed))
    handlers.append(view.connect("load-failed", on_load_failed))

    file_uri = f"file://{fixture_path}"
    view.load_uri(file_uri)


def _start_print(view, output_path, result, done, parent_cleanup):
    """Build the WebKit PrintOperation and fire it. Runs on the GLib
    main thread, called from the load-finished signal handler."""
    print_op = WebKit2.PrintOperation.new(view)

    settings = Gtk.PrintSettings.new()
    settings.set(PRINT_SETTING_OUTPUT_URI, f"file://{output_path}")
    settings.set(
        PRINT_SETTING_OUTPUT_FILE_FORMAT,
        PRINT_SETTING_OUTPUT_FORMAT_VALUE_PDF,
    )
    # Pin the printer to the GTK file-print backend's printer name.
    # Without this, GTK tries to dispatch to a "default" printer —
    # which doesn't exist in a CUPS-less container — and the print
    # operation fails with "Printer not found". The string "Print to
    # File" is the file backend's well-known printer name (defined in
    # gtkprintbackendfile.c). GTK_PRINT_BACKENDS=file alone isn't
    # enough; we have to name the printer too.
    settings.set_printer("Print to File")
    print_op.set_print_settings(settings)

    page_setup = Gtk.PageSetup.new()
    paper = Gtk.PaperSize.new_custom(
        "phpdftk-letter", "Letter",
        PAPER_WIDTH_INCHES, PAPER_HEIGHT_INCHES, Gtk.Unit.INCH,
    )
    page_setup.set_paper_size(paper)
    page_setup.set_top_margin(MARGIN_TOP_INCHES, Gtk.Unit.INCH)
    page_setup.set_bottom_margin(MARGIN_BOTTOM_INCHES, Gtk.Unit.INCH)
    page_setup.set_left_margin(MARGIN_LEFT_INCHES, Gtk.Unit.INCH)
    page_setup.set_right_margin(MARGIN_RIGHT_INCHES, Gtk.Unit.INCH)
    print_op.set_page_setup(page_setup)

    def on_finished(_op):
        parent_cleanup()
        try:
            with open(output_path, "rb") as f:
                result["bytes"] = f.read()
        except Exception as exc:
            result["error"] = f"reading print output failed: {exc}"
        done.set()

    def on_failed(_op, error):
        parent_cleanup()
        result["error"] = f"print failed: {error.message}"
        done.set()

    print_op.connect("finished", on_finished)
    print_op.connect("failed", on_failed)
    # PyGObject renames `print` to `print_` because the unsuffixed
    # name collides with Python's builtin. `print_` (NOT run_dialog)
    # writes directly to output_uri and returns immediately; the
    # finished signal completes the cycle.
    print_op.print_()


# ---------------------------------------------------------------------
# HTTP server
# ---------------------------------------------------------------------

class Handler(BaseHTTPRequestHandler):
    """Per-request handler. Runs on a worker thread spawned by
    ThreadingHTTPServer. WebKit work is dispatched to the GLib main
    thread; the handler thread blocks on completion."""

    server_version = "phpdftk-webkit-daemon/1.0"

    def log_message(self, fmt, *args):
        # Quieter default — only emit on warnings/errors.
        pass

    def do_GET(self):  # noqa: N802
        path = urlparse(self.path).path
        if path == "/status":
            return self._handle_status()
        self._send_json(404, {"error": "unknown route"})

    def do_POST(self):  # noqa: N802
        path = urlparse(self.path).path
        if path == "/render":
            return self._handle_render()
        if path == "/flush":
            return self._handle_flush()
        self._send_json(404, {"error": "unknown route"})

    # ---- routes ------------------------------------------------------

    def _handle_status(self):
        self._send_json(200, {
            "engine": ENGINE,
            "version": state.version,
            "ready": state.ready,
            "pool": {
                "size": semaphore.capacity,
                "in_flight": state.in_flight,
                "queued": semaphore.queued,
            },
        })

    def _handle_render(self):
        try:
            body = self._read_json()
        except Exception as exc:
            return self._send_json(400, {"error": f"bad JSON body: {exc}"})

        raw_fixture = body.get("fixture") if isinstance(body, dict) else None
        try:
            fixture = validate_fixture(raw_fixture, WPT_ROOT)
        except BadRequest as exc:
            return self._send_json(exc.status, {"error": str(exc)})

        cache_path = cached_path_for(ENGINE, body.get("cache_key"), CACHE_DIR)
        cache_probe = self.headers.get("X-Cache-Probe") == "1"
        if cache_path and os.path.isfile(cache_path):
            if cache_probe:
                return self._send_json(304, {"from_cache": True})
            with open(cache_path, "rb") as f:
                cached_bytes = f.read()
            return self._send_json(200, {
                "pdf_bytes_base64": base64.b64encode(cached_bytes).decode("ascii"),
                "ms": 0,
                "from_cache": True,
            })
        if cache_probe:
            return self._send_json(404, {"from_cache": False})

        timeout_ms = int(body.get("timeout_ms") or RENDER_TIMEOUT_MS)
        timeout_s = max(1.0, timeout_ms / 1000.0)

        if not semaphore.acquire(timeout=timeout_s + 5.0):
            return self._send_json(503, {"error": "semaphore busy"})
        state.in_flight += 1
        t_start = time.time()
        try:
            pdf_bytes = render_blocking(fixture, timeout_s)
            if cache_path:
                try:
                    write_cache_atomically(cache_path, pdf_bytes)
                except OSError as exc:
                    sys.stderr.write(f"cache write failed: {exc}\n")
            self._send_json(200, {
                "pdf_bytes_base64": base64.b64encode(pdf_bytes).decode("ascii"),
                "ms": int((time.time() - t_start) * 1000),
                "from_cache": False,
            })
        except TimeoutError as exc:
            self._send_json(408, {"error": str(exc)})
        except Exception as exc:
            status = 502 if "session restarted" in str(exc) else 500
            self._send_json(status, {"error": str(exc)})
        finally:
            state.in_flight -= 1
            semaphore.release()

    def _handle_flush(self):
        # Bumping the generation is enough — the next render takes the
        # render_lock, sees the bumped generation, and the WebView is
        # the same one as before. We don't actually have a browser
        # process to recycle (WebKit is in-process); flush is mostly
        # here for protocol parity with chromium/firefox.
        state.generation += 1
        self.send_response(204)
        self.end_headers()

    # ---- helpers -----------------------------------------------------

    def _read_json(self):
        length = int(self.headers.get("Content-Length", "0") or "0")
        if length == 0:
            return {}
        data = self.rfile.read(length)
        return json.loads(data.decode("utf-8"))

    def _send_json(self, status, body):
        # `separators=(',', ':')` emits compact JSON without the
        # post-colon space that json.dumps adds by default — matches
        # the Node daemons' output so client-side grep / regex tests
        # don't have to special-case the whitespace.
        payload = json.dumps(body, separators=(",", ":")).encode("utf-8")
        self.send_response(status)
        self.send_header("content-type", "application/json")
        self.send_header("content-length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)


def run_http_server():
    """Run the HTTP server on a daemon thread. The main thread owns
    the GLib main loop."""
    server = ThreadingHTTPServer(("0.0.0.0", PORT), Handler)
    sys.stderr.write(
        f"webkit daemon listening on :{PORT} "
        f"(pool={POOL_SIZE}, wpt={WPT_ROOT}, cache={CACHE_DIR})\n"
    )
    sys.stderr.flush()
    server.serve_forever()


def main():
    if not os.path.isdir(WPT_ROOT):
        sys.stderr.write(f"WPT_ROOT does not exist: {WPT_ROOT}\n")
        sys.exit(1)
    os.makedirs(os.path.join(CACHE_DIR, ENGINE), exist_ok=True)

    # GLib main loop drives WebKit. init_webkit runs first so the
    # view + window exist before any request lands.
    loop = GLib.MainLoop()

    def setup_then_listen():
        init_webkit()
        # Start HTTP server now that WebKit is ready.
        http_thread = threading.Thread(target=run_http_server, daemon=True)
        http_thread.start()
        return GLib.SOURCE_REMOVE

    GLib.idle_add(setup_then_listen)
    try:
        loop.run()
    except KeyboardInterrupt:
        sys.stderr.write("shutting down on SIGINT\n")
        loop.quit()


if __name__ == "__main__":
    main()
