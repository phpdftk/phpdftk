#!/usr/bin/env bash
# Emit a tiny static HTML redirect to the URL given as $1 (an absolute path
# beginning with /). Used to wire phpdftk.dev's `/` and `/latest/` to the
# highest-stable-tag deploy without rebuilding the whole Astro site twice.
set -euo pipefail

if [ $# -ne 1 ]; then
    echo "usage: $0 <target-url>" >&2
    exit 2
fi

target="$1"
cat <<EOF
<!DOCTYPE html>
<meta charset="utf-8">
<title>Redirecting…</title>
<meta http-equiv="refresh" content="0; url=${target}">
<link rel="canonical" href="${target}">
<script>location.replace("${target}");</script>
<p>Redirecting to <a href="${target}">${target}</a>…</p>
EOF
