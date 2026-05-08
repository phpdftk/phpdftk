## Summary
<!-- 1–3 bullets on what changed and why -->

## Affected packages
<!-- e.g. pdf/core, pdf/writer, conformance -->

## Test plan
- [ ] `composer test` passes
- [ ] `composer analyse` passes
- [ ] `composer lint` passes

## New-feature checklist (if applicable)
<!-- Per AGENTS.md: a feature isn't done until all four exist. -->
- [ ] Unit tests added under `packages/<pkg>/tests/`
- [ ] Integration test produces a real PDF beginning with `%PDF-`
- [ ] Benchmark added under `benchmarks/` (if public writer/reader/toolkit API or hot path)
- [ ] Docs updated under `docs/site/src/content/docs/`

## Spec / version impact
- Minimum PDF version required:
- `#[RequiresPdfVersion]` annotation added (if new spec feature):
- Conformance profile(s) affected (PDF/A, UA, X, VT, E, R, ZUGFeRD, mail):

---
- [ ] Commits are DCO-signed (`git commit -s`)
