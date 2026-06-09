#!/usr/bin/env bash
# WebKit daemon entrypoint — wraps the Python server under xvfb-run
# so WebKitWebView gets a real display target. The bare `python3
# server.py` path fails with `cannot open display` on a clean
# container; that's why this wrapper exists.
set -e

exec xvfb-run -a \
    --server-args="-screen 0 1280x1024x24" \
    python3 /opt/daemon/server.py
