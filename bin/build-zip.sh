#!/usr/bin/env bash
#
# 配布用 ZIP をローカルでビルドする（CI の release.yml と同一ロジック）.
#
# 除外定義は .distignore を単一の正とし、このスクリプトと release.yml の
# 両方がそれを読む。

set -euo pipefail

cd "$(dirname "$0")/.."

SLUG="forced-auto-update-controller"
VERSION=$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' "$SLUG.php" \
  | head -n1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r')

STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$STAGE/$SLUG"

# rsync の --exclude-from に渡す前に、コメント行・空行を除去する
# （--exclude-from がコメント行をどう扱うかは未検証のため、依存しない）.
grep -vE '^[[:space:]]*(#|$)' .distignore > "$STAGE/excludes.txt"

rsync -a --exclude-from="$STAGE/excludes.txt" ./ "$STAGE/$SLUG/"

( cd "$STAGE" && zip -rq "$SLUG.$VERSION.zip" "$SLUG" )
mv "$STAGE/$SLUG.$VERSION.zip" "./$SLUG.$VERSION.zip"

echo "Built: $SLUG.$VERSION.zip"
