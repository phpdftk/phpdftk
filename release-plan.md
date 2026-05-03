# Production Readiness Release Plan

## Context

phpdftk is a PHP 8.4 PDF monorepo with 13+ packages. CI/CD, tests (217 files), benchmarks, compliance validation (275+ external tests), static analysis (PHPStan level 6), code style (PER-CS2.0), documentation site, and release-please versioning are all in place. This plan identifies remaining gaps to reach production readiness, ordered by priority.

---

## Must-have for production

### 1. Packagist publishing
- **Why:** Nobody can `composer require` the packages yet — release-please is configured but the monorepo split is commented out in `.github/workflows/release.yml` (needs `SPLIT_TOKEN` secret)
- **Files:** `.github/workflows/release.yml`
- **Tasks:**
  - Create split repos on GitHub for each package
  - Register each package on Packagist
  - Generate a `SPLIT_TOKEN` personal access token with repo scope
  - Add `SPLIT_TOKEN` as a repository secret
  - Uncomment the split/publish step in `release.yml`
  - Test with a dry-run release

### 2. Dependency vulnerability scanning
- **Why:** No `composer audit` in CI, no Dependabot — zero automated dependency security coverage
- **Files:** `.github/workflows/ci.yml`, `.github/dependabot.yml` (new)
- **Tasks:**
  - Add `composer audit` step to `ci.yml`
  - Create `.github/dependabot.yml` with composer ecosystem config, weekly schedule
  - Consider adding `composer audit --locked` as a pre-commit hook

### 3. Multi-PHP version testing
- **Why:** CI only tests PHP 8.4; `composer.json` declares `^8.4` which includes future 8.5+
- **Files:** `.github/workflows/ci.yml`
- **Tasks:**
  - Add PHP 8.4 lowest-patch to the CI matrix
  - Add PHP 8.5 (or nightly) as an allow-failure entry for forward compatibility

---

## Should-have

### 4. README badges
- **Why:** Quick credibility signal for potential users; coverage badge already exists but isn't shown
- **Files:** `README.md`
- **Tasks:**
  - Add CI status badge
  - Add code coverage badge (from `docs/generated/coverage-badge.svg`)
  - Add latest version / Packagist badge (once published)
  - Add PHP version badge
  - Add license badge

### 5. `@api` / `@internal` annotation consistency
- **Why:** `@internal` is used on ~9 classes, but no `@api` annotations mark the public contract — consumers and tooling can't distinguish stable from unstable API surface
- **Files:** Key public facades across packages
- **Tasks:**
  - Add `@api` to primary entry points: `Pdf`, `PdfWriter`, `PdfReader`, `PdfFileWriter`
  - Add `@api` to main toolkit classes: `FormFiller`, `PdfStamper`, `PdfMerger`, etc.
  - Audit remaining `@internal` usage for consistency

### 6. Secrets scanning
- **Why:** The crypt package handles real keys/certificates; no protection against accidental credential commits
- **Files:** `.github/secret_scanning.yml` (new), or pre-commit hook config
- **Tasks:**
  - Enable GitHub secret scanning on the repository
  - Consider adding a `.gitleaks.toml` or similar pre-commit scan

---

## Nice-to-have

### 7. Docker dev environment
- **Why:** `.mise.toml` handles local dev, but a Dockerfile lowers the barrier for contributors without mise
- **Files:** `Dockerfile` (new), `docker-compose.yml` (new or extend existing)
- **Tasks:**
  - Create a dev Dockerfile with PHP 8.4 + required extensions
  - Add docker-compose service for running tests/analysis

### 8. SBOM generation
- **Why:** Supply chain security expectation is growing, especially for libraries handling encryption/signatures
- **Tasks:**
  - Add `composer audit` SBOM output or integrate CycloneDX/SPDX generation in CI

### 9. Contributor License Agreement
- **Why:** MIT is clean, but a CLA bot (or DCO sign-off) protects against IP issues from external contributors
- **Tasks:**
  - Evaluate CLA bot vs DCO sign-off requirement
  - Add to CONTRIBUTING.md and PR template if adopted

---

## Already solid (no action needed)

- Test suite: 217 test files, comprehensive coverage across all packages
- Benchmarks: 3 benchmark classes comparing against FPDF/TCPDF/mPDF/Dompdf
- Compliance validation: 275+ external tests (QPDF, veraPDF, JHOVE, PDFBox, Arlington)
- Static analysis: PHPStan level 6, PHP-CS-Fixer PER-CS2.0
- Documentation: Astro site, spec-coverage.md, version-coverage.md, iso-standards-coverage.md
- Security policy: SECURITY.md with responsible disclosure, 48h SLA
- Type safety: `declare(strict_types=1)` in ~100% of source files
- Release automation: release-please with per-package changelogs
- Error handling: Typed exception hierarchy, version gating with attributes

---

## Verification

- [ ] `composer require apprlabs/pdf` installs from Packagist
- [ ] CI passes on PHP 8.4 with `composer audit` step green
- [ ] Dependabot opens a PR within one week of enablement
- [ ] README renders badges correctly on GitHub
- [ ] `release-please` successfully creates a release PR and publishes split packages
