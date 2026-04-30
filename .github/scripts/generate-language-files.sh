#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=plugin-config.sh
source "$(dirname "${BASH_SOURCE[0]}")/plugin-config.sh"

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
languages_dir="$repo_root/$plugin_slug/languages"

if [[ ! -d "$languages_dir" ]]; then
	echo "Languages directory not found: $languages_dir" >&2
	exit 1
fi

compiled=0
for po_file in "$languages_dir"/*.po; do
	[[ -f "$po_file" ]] || continue
	mo_file="${po_file%.po}.mo"
	msgfmt -o "$mo_file" "$po_file"
	echo "Compiled: $(basename "$po_file") → $(basename "$mo_file")"
	((compiled++))
done

if [[ $compiled -eq 0 ]]; then
	echo "No .po files found in $languages_dir"
else
	echo "Compiled $compiled language file(s)"
fi
