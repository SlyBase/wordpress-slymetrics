#!/usr/bin/env bash
# Meant to be sourced, not executed directly.
# Detects the plugin slug automatically by looking for a subdirectory in the
# repository root that contains a same-named PHP file with a "Plugin Name:" header.
#
#   source "$(dirname "${BASH_SOURCE[0]}")/plugin-config.sh"

_pc_script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
_pc_repo_root="$(cd "$_pc_script_dir/../.." && pwd)"

plugin_slug=""
for _pc_dir in "$_pc_repo_root"/*/; do
	_pc_base="$(basename "$_pc_dir")"
	_pc_php="${_pc_dir}${_pc_base}.php"
	if [[ -f "$_pc_php" ]] && grep -q "Plugin Name:" "$_pc_php"; then
		plugin_slug="$_pc_base"
		break
	fi
done

if [[ -z "$plugin_slug" ]]; then
	echo "plugin-config.sh: Could not auto-detect plugin slug in '$_pc_repo_root'" >&2
	exit 1
fi

unset _pc_script_dir _pc_repo_root _pc_dir _pc_base _pc_php
