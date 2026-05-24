# Test fonts

Small redistributable OpenType/TrueType fonts checked in to give the test
suite **deterministic coverage** of font-format paths that depend on
system-installed fonts (which vary widely across macOS, Linux, and CI
runners).

These files are **excluded from the published Composer artifact** via
`packages/font-parser/.gitattributes` (the entire `/tests` directory is
marked `export-ignore`).

## Contents

| File | License | Why we ship it |
|---|---|---|
| `NotoSansMongolian-Regular.otf` (~144 KB) | SIL OFL 1.1 — see `NotoSansMongolian-OFL.txt` | OTF/CFF font with cmap format 12, vertical metrics (`vhea`/`vmtx`), and >1500 glyphs. Exercises `OpenTypeParser::parseCmapFormat12`, the vhea/vmtx branch of `OpenTypeParser::parse`, and `CffParser::parseCharset` format 1/2 paths. |
| `NotoSansTifinagh-Regular.otf` (~25 KB) | SIL OFL 1.1 — see `NotoSansTifinagh-OFL.txt` | Tiny OTF/CFF font for fast parse-path tests. |
| `NotoSans-Regular.otf` (~324 KB) | SIL OFL 1.1 — see `NotoSans-OFL.txt` | Latin / Greek / Cyrillic OTF/CFF with full ASCII coverage. The `html-to-pdf` example PDFs use it as their `defaultFont` so prose actually renders — without a Latin font, ASCII shapes to `.notdef` (visible as squares / tofu). |

## Refreshing

The `scripts/fetch-test-fonts.php` script downloads canonical copies from
the upstream Noto release pages. Run it if a font is missing or you want
to upgrade to a newer release:

    php scripts/fetch-test-fonts.php

The script verifies SHA-256 hashes against `fixtures.lock.json`. Update
that file (and bump the version pin in the script) when you intentionally
roll forward.

## License

All fonts here are SIL Open Font License 1.1, which permits redistribution
as part of larger software bundles. Each font ships with its OFL text.
