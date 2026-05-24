# Shared test fixtures

Committed test data shared across packages. Subdirectories are organized by category:

| Directory | Contents | Consumed by |
|---|---|---|
| `fonts/` | OpenType / TrueType / WOFF font files plus their SIL OFL license texts | `phpdftk/font-parser`, `phpdftk/text`, `phpdftk/html-to-pdf`, `phpdftk/svg-to-pdf`, benchmarks |
| `html/` | HTML test documents (small / medium / large / pathological) | `phpdftk/html`, `phpdftk/html-to-pdf`, benchmarks |
| `css/` | CSS test stylesheets (bootstrap / tailwind / pathological) | `phpdftk/css`, benchmarks |
| `svg/` | SVG test documents (logo / illustration / map / inkscape) | `phpdftk/svg`, `phpdftk/svg-to-pdf`, benchmarks |

These fixtures are excluded from published Composer artefacts via the per-package `.gitattributes` `export-ignore` of `/tests`. They live at the repo root rather than inside any single package so multiple consumers can share them without duplication.

## Adding a fixture

1. Drop the file under the appropriate category subdirectory.
2. Note the license / source in this README (for fonts and any other third-party content).
3. Reference it from test code via an absolute path resolver — never hard-code paths from within a package's test (they break if the fixture moves).

## Licenses

### Fonts

All fonts are SIL Open Font License 1.1 (OFL-1.1). License texts ship alongside each font.

- **NotoSansMongolian-Regular.otf** — Google Noto Sans Mongolian. License: `NotoSansMongolian-OFL.txt`.
- **NotoSansTifinagh-Regular.otf** — Google Noto Sans Tifinagh. License: `NotoSansTifinagh-OFL.txt`.
