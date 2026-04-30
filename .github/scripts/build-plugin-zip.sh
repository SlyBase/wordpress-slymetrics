#!/usr/bin/env bash

set -euo pipefail

output_zip="${1:?Usage: build-plugin-zip.sh <output-zip>}"
caller_cwd="$(pwd)"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$script_dir/../.." && pwd)"
plugin_dir="$repo_root/slymetrics"
staging_root="$(mktemp -d)"
trap 'rm -rf "$staging_root"' EXIT

if [[ "$output_zip" = /* ]]; then
	output_zip_path="$output_zip"
else
	output_zip_path="$caller_cwd/$output_zip"
fi

mkdir -p "$(dirname "$output_zip_path")"
rm -f "$output_zip_path"

rsync -a \
	--exclude='.DS_Store' \
	--exclude='.gitignore' \
	"$plugin_dir/" "$staging_root/slymetrics/"

(
	cd "$staging_root"
	zip -rq "$output_zip_path" slymetrics
)

echo "Created plugin archive: $output_zip_path"
unzip -l "$output_zip_path"
