#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck source=plugin-config.sh
source "$(dirname "${BASH_SOURCE[0]}")/plugin-config.sh"
SRC_FILE="${1:-$ROOT_DIR/CHANGELOG.md}"
DST_FILE="${2:-$ROOT_DIR/${plugin_slug}/changelog.txt}"

if [[ ! -f "$SRC_FILE" ]]; then
    echo "Source file not found: $SRC_FILE" >&2
    exit 1
fi

tmp_file="$(mktemp)"
trap 'rm -f "$tmp_file"' EXIT

awk '
function cleanup_text(s,   link, label) {
    while (match(s, /\[[^][]+\]\([^)]+\)/)) {
        link = substr(s, RSTART, RLENGTH)
        label = link
        sub(/^\[/, "", label)
        sub(/\]\([^)]+\)$/, "", label)
        s = substr(s, 1, RSTART - 1) label substr(s, RSTART + RLENGTH)
    }

    gsub(/`/, "", s)
    gsub(/\*\*/, "", s)
    gsub(/__/, "", s)

    return s
}

function append_blank_line() {
    if (!last_was_blank && out_count > 0) {
        lines[++out_count] = ""
        last_was_blank = 1
    }
}

{
    line = $0
    sub(/\r$/, "", line)

    if (line ~ /^## \[[^]]+\]$/) {
        version = line
        sub(/^## \[/, "", version)
        sub(/\]$/, "", version)

        if (out_count > 0 && lines[out_count] != "") {
            lines[++out_count] = ""
        }

        lines[++out_count] = "= " version " ="
        last_was_blank = 0
        in_versions = 1
        next
    }

    if (!in_versions) {
        next
    }

    if (line ~ /^- /) {
        sub(/^- /, "* ", line)
        line = cleanup_text(line)
        lines[++out_count] = line
        last_was_blank = 0
        next
    }

    if (line ~ /^[[:space:]]*$/) {
        append_blank_line()
        next
    }
}

END {
    while (out_count > 0 && lines[out_count] == "") {
        out_count--
    }

    for (i = 1; i <= out_count; i++) {
        print lines[i]
    }
}
' "$SRC_FILE" > "$tmp_file"

mkdir -p "$(dirname "$DST_FILE")"
mv "$tmp_file" "$DST_FILE"
trap - EXIT

echo "Synced $DST_FILE from $SRC_FILE"