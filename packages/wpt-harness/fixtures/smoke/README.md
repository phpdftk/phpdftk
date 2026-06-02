# Smoke fixtures

Bundled mini-WPT for verifying the harness without needing the full WPT
corpus checkout. Each test sits next to its reference (`-ref.html` or
`-ref.svg`); the harness renders both, rasterises via Ghostscript, and
asserts the perceptual diff falls inside the pass threshold.

Run via:

```bash
./packages/wpt-harness/bin/wpt run --root=packages/wpt-harness/fixtures/smoke
```

Or, equivalently, `composer wpt:smoke` from the repo root.

These are **not** WPT tests; they exist to exercise the harness pipeline
end-to-end in CI even when `vendor-data/wpt/` isn't populated. The real
pass-rate number comes from running against the populated WPT submodule.
