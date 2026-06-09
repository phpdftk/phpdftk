"""
Pure-function helpers used by server.py. Pulled into their own
module so they can be exercised by `python3 -m unittest` without
booting WebKit. Touching anything here means re-running the unit
suite — these primitives gate every request the daemon handles.

These primitives mirror the Node-side lib.mjs in scripts/cross-browser/
engines/chromium/ and engines/firefox/ — same path-validation rules,
same cache-key shape, same atomic-write semantics. The PHP host
computes cache keys identically to all three, so cache writes from
any engine + the host land at exactly the same file path on disk.

The duplication across engines is deliberate; see the equivalent
note in the Node lib.mjs files for the rationale.
"""

from __future__ import annotations

import os
import re
import secrets
import threading
from pathlib import PurePosixPath


CACHE_KEY_RE = re.compile(r"^[A-Fa-f0-9]{32,128}$")
ENGINE_NAME_RE = re.compile(r"^[a-z]+$")


class BadRequest(Exception):
    """Raised when input doesn't satisfy a sandbox or shape rule.

    Server.py catches this and returns HTTP 400 with the message
    forwarded verbatim, so the orchestrator can tell apart bad input
    from a daemon-side bug.
    """

    status = 400


def validate_fixture(raw_path, wpt_root):
    """Reject anything that isn't a real fixture under the WPT mount.

    Returns the normalised path on success; raises BadRequest on
    rejection. Rejection cases this catches:
      - Non-string / empty path
      - Path that doesn't start with the WPT root (after normalisation,
        so /wpt/../etc/passwd collapses to /etc/passwd and is rejected)
      - Path equal to the root (we need a file)
      - Path that doesn't exist on disk
      - Path that exists but isn't a regular file
    """
    if not isinstance(raw_path, str) or raw_path == "":
        raise BadRequest("fixture path missing")
    root = _strip_trailing_slash(os.path.normpath(wpt_root))
    normalised = _strip_trailing_slash(os.path.normpath(raw_path))
    if normalised == root:
        raise BadRequest("fixture path resolves to the WPT root, not a file")
    if not normalised.startswith(root + os.sep):
        raise BadRequest(f"fixture must be inside {root}")
    if not os.path.exists(normalised):
        raise BadRequest(f"fixture not found: {normalised}")
    if not os.path.isfile(normalised):
        raise BadRequest(f"fixture is not a regular file: {normalised}")
    return normalised


def _strip_trailing_slash(p):
    if len(p) > 1 and p.endswith(os.sep):
        return p.rstrip(os.sep) or os.sep
    return p


def cached_path_for(engine, cache_key, cache_dir):
    """Compute the canonical cache file path for `(engine, cacheKey)`.

    Returns None when the key isn't a plausible cache key — we treat
    a malformed key as "no cache", not as an error, so a stray host
    shard with a different scheme doesn't crash the daemon.

    The key shape is the first 64 hex chars of a SHA-256 hash,
    matching the PHP-side `BrowserOracle::cacheKey()`. We accept up
    to 128 chars to be forgiving with future scheme tweaks.
    """
    if not isinstance(cache_key, str) or not CACHE_KEY_RE.match(cache_key):
        return None
    if not isinstance(engine, str) or not ENGINE_NAME_RE.match(engine):
        return None
    return os.path.join(cache_dir, engine, f"{cache_key}.pdf")


def write_cache_atomically(final_path, data):
    """Write `data` to `final_path` atomically via `tmp + rename`.

    Both the daemon and the host PHP shards write to the same cache
    directory; rename(2) is atomic on local FS for same-directory
    targets so concurrent writers never observe a half-written file.
    """
    dir_ = os.path.dirname(final_path)
    if not os.path.isdir(dir_):
        os.makedirs(dir_, exist_ok=True)
    tmp = f"{final_path}.tmp.{secrets.token_hex(4)}"
    with open(tmp, "wb") as f:
        f.write(data)
    os.replace(tmp, final_path)


class Semaphore:
    """Bounded-concurrency primitive matching the Node lib.mjs shape.

    For WebKit the semaphore bounds concurrent /render requests in the
    HTTP handler — the actual WebKit work runs single-threaded on the
    GLib main loop, so this is mostly a guard against memory blow-up
    from a burst of requests queueing inside the daemon.
    """

    def __init__(self, capacity):
        if not isinstance(capacity, int) or capacity < 1:
            raise ValueError(f"semaphore capacity must be a positive integer (got {capacity!r})")
        self._capacity = capacity
        self._cond = threading.Condition()
        self._available = capacity
        self._queued = 0

    @property
    def capacity(self):
        return self._capacity

    @property
    def available(self):
        with self._cond:
            return self._available

    @property
    def queued(self):
        with self._cond:
            return self._queued

    def acquire(self, timeout=None):
        with self._cond:
            if self._available > 0:
                self._available -= 1
                return True
            self._queued += 1
            try:
                while self._available <= 0:
                    if not self._cond.wait(timeout=timeout):
                        return False
                self._available -= 1
                return True
            finally:
                self._queued -= 1

    def release(self):
        with self._cond:
            self._available += 1
            self._cond.notify()
