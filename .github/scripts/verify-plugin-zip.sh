#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=plugin-config.sh
source "$(dirname "${BASH_SOURCE[0]}")/plugin-config.sh"
zip_file="${1:?Usage: verify-plugin-zip.sh <zip-file>}"
zip_entries="$(unzip -Z1 "$zip_file")"

unzip -tq "$zip_file"

blacklist_patterns=(
	"^\${plugin_slug}/tests/"
	"^\${plugin_slug}/composer\\.lock\$"
	"^\${plugin_slug}/phpunit\\.xml\\.dist\$"
	"^\${plugin_slug}/\\.gitignore\$"
	"^\${plugin_slug}/\\.phpunit\\.result\\.cache\$"
	"^\${plugin_slug}(?:/.*)?\\/\\.DS_Store\$"
)

for pattern in "${blacklist_patterns[@]}"; do
	if printf '%s\n' "$zip_entries" | grep -Eq "$pattern"; then
		echo "::error::Archive contains excluded entry matching $pattern"
		exit 1
	fi
done

required_files=(
	"${plugin_slug}/${plugin_slug}.php"
	"${plugin_slug}/readme.txt"
	"${plugin_slug}/uninstall.php"
)

for required_file in "${required_files[@]}"; do
	if ! printf '%s\n' "$zip_entries" | grep -Fxq "$required_file"; then
		echo "::error::Archive is missing required file: $required_file"
		exit 1
	fi
done

required_prefixes=(
	"${plugin_slug}/languages/"
)

for required_prefix in "${required_prefixes[@]}"; do
	if ! printf '%s\n' "$zip_entries" | grep -Fq "$required_prefix"; then
		echo "::error::Archive is missing required path prefix: $required_prefix"
		exit 1
	fi
done

echo "Archive verification passed: $zip_file"