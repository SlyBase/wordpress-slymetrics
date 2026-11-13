#!/usr/bin/env bash
# Install gettext (msgfmt) on a runner, tolerating Azure apt mirror DNS failures.
set -euo pipefail

if command -v msgfmt >/dev/null 2>&1; then
  echo "msgfmt already installed; skipping."
  exit 0
fi

APT_OPTS=(-o Acquire::Retries=3 -o Acquire::http::Timeout=20 -o Acquire::https::Timeout=20)

install_gettext() {
  sudo apt-get "${APT_OPTS[@]}" update && sudo apt-get "${APT_OPTS[@]}" install --yes gettext
}

if install_gettext; then
  exit 0
fi

echo "apt failed; switching to archive.ubuntu.com and retrying."
for f in /etc/apt/sources.list /etc/apt/sources.list.d/*.sources /etc/apt/sources.list.d/*.list /etc/apt/apt-mirrors.txt; do
  [ -f "$f" ] || continue
  sudo sed -i -E 's#https?://(azure\.archive|security)\.ubuntu\.com#http://archive.ubuntu.com#g' "$f"
done
install_gettext
