"""
Pure-function tests for the WebKit daemon's primitives. These don't
launch WebKit — they exercise path-validation / cache-key / semaphore
code paths that gate every request before any browser work happens.

Run via:
  cd scripts/cross-browser/engines/webkit
  python3 -m unittest discover -s test -p 'test_*.py'

Bias is heavily toward negative cases — these are the boundary
functions that protect the fixture sandbox and the cache layout, so
we want every rejection path explicitly covered. Mirrors the Node-
side coverage in chromium/test/lib.test.mjs and
firefox/test/lib.test.mjs so the three engines stay in lockstep on
how the sandbox behaves.
"""

import os
import shutil
import sys
import tempfile
import threading
import time
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from lib import (  # noqa: E402
    BadRequest,
    Semaphore,
    cached_path_for,
    validate_fixture,
    write_cache_atomically,
)


GOOD_KEY = "a" * 64


class TestSemaphore(unittest.TestCase):
    def test_rejects_zero_capacity(self):
        with self.assertRaisesRegex(ValueError, "positive integer"):
            Semaphore(0)

    def test_rejects_negative_capacity(self):
        with self.assertRaisesRegex(ValueError, "positive integer"):
            Semaphore(-1)

    def test_rejects_float_capacity(self):
        with self.assertRaisesRegex(ValueError, "positive integer"):
            Semaphore(1.5)

    def test_rejects_string_capacity(self):
        with self.assertRaisesRegex(ValueError, "positive integer"):
            Semaphore("3")

    def test_allows_n_concurrent_acquires_blocks_nth_plus_one(self):
        sem = Semaphore(2)
        self.assertTrue(sem.acquire(timeout=0.1))
        self.assertTrue(sem.acquire(timeout=0.1))
        self.assertEqual(sem.available, 0)

        # Third acquire blocks until release.
        third_done = threading.Event()
        def third():
            sem.acquire()
            third_done.set()
        t = threading.Thread(target=third, daemon=True)
        t.start()
        # Wait briefly; the third must NOT complete yet.
        time.sleep(0.05)
        self.assertFalse(third_done.is_set(), "third acquire must block")
        self.assertEqual(sem.queued, 1)

        sem.release()
        self.assertTrue(third_done.wait(timeout=1.0))
        self.assertEqual(sem.queued, 0)

    def test_timeout_returns_false_when_blocked(self):
        sem = Semaphore(1)
        sem.acquire()
        # Timeout out should resolve to False, not raise.
        got = sem.acquire(timeout=0.05)
        self.assertFalse(got)

    def test_release_with_no_waiters_bumps_available(self):
        sem = Semaphore(2)
        self.assertEqual(sem.available, 2)
        # Over-release; intentional — daemon code never does this but
        # we'd rather it not deadlock if a bug regresses.
        sem.release()
        self.assertEqual(sem.available, 3)


class TestValidateFixture(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp_root = tempfile.mkdtemp(prefix="webkit-daemon-test-")
        cls.wpt_root = os.path.join(cls.tmp_root, "wpt")
        cls.valid_fixture = os.path.join(cls.wpt_root, "css", "flex.html")
        cls.valid_deep = os.path.join(cls.wpt_root, "a", "b", "c", "d.html")
        os.makedirs(os.path.join(cls.wpt_root, "css"))
        os.makedirs(os.path.join(cls.wpt_root, "a", "b", "c"))
        with open(cls.valid_fixture, "w") as f:
            f.write("<html></html>")
        with open(cls.valid_deep, "w") as f:
            f.write("<html></html>")

    @classmethod
    def tearDownClass(cls):
        shutil.rmtree(cls.tmp_root, ignore_errors=True)

    def test_rejects_none(self):
        with self.assertRaises(BadRequest) as ctx:
            validate_fixture(None, self.wpt_root)
        self.assertEqual(ctx.exception.status, 400)
        self.assertIn("missing", str(ctx.exception))

    def test_rejects_empty_string(self):
        with self.assertRaises(BadRequest):
            validate_fixture("", self.wpt_root)

    def test_rejects_int(self):
        with self.assertRaises(BadRequest):
            validate_fixture(42, self.wpt_root)

    def test_rejects_dict(self):
        with self.assertRaises(BadRequest):
            validate_fixture({"path": "/wpt/foo"}, self.wpt_root)

    def test_rejects_path_outside_root(self):
        with self.assertRaises(BadRequest) as ctx:
            validate_fixture("/etc/passwd", self.wpt_root)
        self.assertIn("must be inside", str(ctx.exception))

    def test_rejects_parent_dir_escape(self):
        # normpath collapses /wpt/../../etc/passwd to /etc/passwd
        escape = os.path.join(self.wpt_root, "..", "..", "etc", "passwd")
        with self.assertRaises(BadRequest):
            validate_fixture(escape, self.wpt_root)

    def test_rejects_path_equal_to_root(self):
        with self.assertRaises(BadRequest) as ctx:
            validate_fixture(self.wpt_root, self.wpt_root)
        self.assertIn("root", str(ctx.exception))

    def test_rejects_nonexistent_file_inside_root(self):
        with self.assertRaises(BadRequest) as ctx:
            validate_fixture(os.path.join(self.wpt_root, "no-such.html"), self.wpt_root)
        self.assertIn("not found", str(ctx.exception))

    def test_rejects_trailing_slash_root(self):
        with self.assertRaises(BadRequest):
            validate_fixture(self.wpt_root + os.sep, self.wpt_root)

    def test_rejects_directory(self):
        # css/ exists but isn't a renderable fixture.
        with self.assertRaises(BadRequest) as ctx:
            validate_fixture(os.path.join(self.wpt_root, "css"), self.wpt_root)
        self.assertIn("not a regular file", str(ctx.exception))

    def test_accepts_real_file(self):
        self.assertEqual(
            validate_fixture(self.valid_fixture, self.wpt_root),
            self.valid_fixture,
        )

    def test_accepts_deep_file(self):
        self.assertEqual(
            validate_fixture(self.valid_deep, self.wpt_root),
            self.valid_deep,
        )

    def test_accepts_normalised_equivalent_with_dot_segments(self):
        noisy = os.path.join(self.wpt_root, "css", ".", "flex.html")
        self.assertEqual(
            validate_fixture(noisy, self.wpt_root),
            self.valid_fixture,
        )


class TestCachedPathFor(unittest.TestCase):
    def setUp(self):
        self.cache_dir = "/tmp/webkit-cache-test"

    def test_rejects_none_key(self):
        self.assertIsNone(cached_path_for("webkit", None, self.cache_dir))

    def test_rejects_empty_key(self):
        self.assertIsNone(cached_path_for("webkit", "", self.cache_dir))

    def test_rejects_too_short_key(self):
        self.assertIsNone(cached_path_for("webkit", "abc123", self.cache_dir))

    def test_rejects_non_hex_key(self):
        self.assertIsNone(
            cached_path_for("webkit", GOOD_KEY[:63] + "z", self.cache_dir),
        )

    def test_rejects_shell_injection(self):
        self.assertIsNone(
            cached_path_for("webkit", "../../etc/passwd", self.cache_dir),
        )

    def test_rejects_path_separator_in_key(self):
        self.assertIsNone(
            cached_path_for("webkit", "a" * 32 + "/" + "b" * 32, self.cache_dir),
        )

    def test_rejects_engine_with_dots(self):
        self.assertIsNone(cached_path_for("../other", GOOD_KEY, self.cache_dir))

    def test_rejects_empty_engine(self):
        self.assertIsNone(cached_path_for("", GOOD_KEY, self.cache_dir))

    def test_rejects_engine_with_hyphen(self):
        self.assertIsNone(cached_path_for("webkit-1", GOOD_KEY, self.cache_dir))

    def test_accepts_64_hex_key(self):
        got = cached_path_for("webkit", GOOD_KEY, self.cache_dir)
        self.assertEqual(got, os.path.join(self.cache_dir, "webkit", GOOD_KEY + ".pdf"))

    def test_accepts_longer_hex_key(self):
        long_key = "b" * 96
        got = cached_path_for("webkit", long_key, self.cache_dir)
        self.assertEqual(got, os.path.join(self.cache_dir, "webkit", long_key + ".pdf"))


class TestWriteCacheAtomically(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp_root = tempfile.mkdtemp(prefix="webkit-cache-write-test-")

    @classmethod
    def tearDownClass(cls):
        shutil.rmtree(cls.tmp_root, ignore_errors=True)

    def test_creates_parent_dir(self):
        target = os.path.join(self.tmp_root, "fresh-dir", "webkit", GOOD_KEY + ".pdf")
        write_cache_atomically(target, b"hello")
        self.assertTrue(os.path.isfile(target))
        with open(target, "rb") as f:
            self.assertEqual(f.read(), b"hello")

    def test_no_tmp_leftover(self):
        d = os.path.join(self.tmp_root, "leftover-check")
        target = os.path.join(d, "a" + "0" * 63 + ".pdf")
        write_cache_atomically(target, b"x")
        leftovers = [n for n in os.listdir(d) if ".tmp." in n]
        self.assertEqual(leftovers, [], f"unexpected .tmp.* leftovers: {leftovers}")

    def test_overwrites_in_place(self):
        target = os.path.join(self.tmp_root, "overwrite", "k.pdf")
        write_cache_atomically(target, b"first")
        write_cache_atomically(target, b"second")
        with open(target, "rb") as f:
            self.assertEqual(f.read(), b"second")


if __name__ == "__main__":
    unittest.main()
