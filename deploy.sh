#!/usr/bin/env bash
set -uo pipefail
# Ensure TMPDIR exists (for mktemp etc.)
: "${TMPDIR:=$(mktemp -d)}"
# =====================================================
# PATH SETUP
# =====================================================
script_dir="$(cd -- "$(dirname -- "$0")" && pwd -P)"
config_file="$script_dir/deploy.cfg"

# =====================================================
# LOAD CONFIG (handles #/; comments, blank lines)
# =====================================================
if [[ ! -f "$config_file" ]]; then
  echo "[ERROR] Config file not found: $config_file"
  exit 1
fi

# Clear (so old env doesn’t leak)
unset PLUGIN_NAME PLUGIN_TAGS PLUGIN_SLUG HEADER_SCRIPT CHANGELOG_FILE STATIC_FILE DEST_DIR DEPLOY_TARGET
unset GITHUB_REPO TOKEN_FILE ZIP_NAME GENERATOR_SCRIPT GITHUB_TOKEN

# Parse KEY=VALUE (ignore comments and blanks)
while IFS= read -r line || [[ -n "$line" ]]; do
  line="${line//$'\r'/}"
  [[ -z "$line" || "${line:0:1}" == "#" || "${line:0:1}" == ";" ]] && continue
  key="${line%%=*}"
  val="${line#*=}"
  key="$(echo "$key" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
  val="$(echo "$val" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
  eval "$key=\"\$val\""
done < "$config_file"

# =====================================================
# CONSTANTS / SHARED TOOLS (same defaults as BAT)
# =====================================================
HEADER_SCRIPT="${HEADER_SCRIPT:-C:/Ignore By Avast/0. PATHED Items/Plugins/deployscripts/myplugin_headers.php}"
TOKEN_FILE="${TOKEN_FILE:-C:/Ignore By Avast/0. PATHED Items/Plugins/deployscripts/github_token.txt}"
GENERATOR_SCRIPT="${GENERATOR_SCRIPT:-C:/Ignore By Avast/0. PATHED Items/Plugins/deployscripts/generate_index.php}"

# =====================================================
# DEFAULTS / VALIDATION
# =====================================================
if [[ -z "${PLUGIN_SLUG:-}" ]]; then
  echo "[ERROR] PLUGIN_SLUG is not defined in deploy.cfg"
  exit 1
fi
if [[ -z "${GITHUB_REPO:-}" ]]; then
  echo "[ERROR] GITHUB_REPO is not defined in deploy.cfg"
  exit 1
fi

ZIP_NAME="${ZIP_NAME:-$PLUGIN_SLUG.zip}"
CHANGELOG_FILE="${CHANGELOG_FILE:-changelog.txt}"
STATIC_FILE="${STATIC_FILE:-static.txt}"
DEPLOY_TARGET="${DEPLOY_TARGET:-github}"
PLUGIN_NAME="${PLUGIN_NAME:-$PLUGIN_SLUG}"
PLUGIN_TAGS="${PLUGIN_TAGS:-}"

# Derived paths
plugin_dir="$script_dir/$PLUGIN_SLUG"
plugin_file="$plugin_dir/$PLUGIN_SLUG.php"
readme_file="$plugin_dir/readme.txt"
temp_readme="$plugin_dir/readme_temp.txt"
repo_root="$script_dir"

# STATIC_SUBFOLDER expects a Windows-escaped backslash path in your PHP generator.
# We’ll pass a normal path (the PHP can handle it), but if you truly need the double-backslash form:
static_subfolder="$repo_root/uupd"

# =====================================================
# VERIFY REQUIRED FILES
# =====================================================
[[ -f "$plugin_file" ]] || { echo "[ERROR] Plugin file not found: $plugin_file"; exit 1; }
[[ -f "$CHANGELOG_FILE" ]] || { echo "[ERROR] Changelog file not found: $CHANGELOG_FILE"; exit 1; }
[[ -f "$STATIC_FILE"   ]] || { echo "[ERROR] Static readme file not found: $STATIC_FILE"; exit 1; }

# =====================================================
# RUN HEADER SCRIPT (updates plugin headers if needed)
# =====================================================
php "$HEADER_SCRIPT" "$plugin_file"

# Extract metadata from plugin headers (Requires/Tested/Version/PHP)
requires_at_least="$(grep -m1 -E '^Requires at least:' "$plugin_file" | sed -E 's/^Requires at least:[[:space:]]*//')"
tested_up_to="$(grep -m1 -E '^Tested up to:' "$plugin_file" | sed -E 's/^Tested up to:[[:space:]]*//')"
version="$(grep -m1 -E '^Version:' "$plugin_file" | sed -E 's/^Version:[[:space:]]*//')"
requires_php="$(grep -m1 -E '^Requires PHP:' "$plugin_file" | sed -E 's/^Requires PHP:[[:space:]]*//')"

# Extract metadata from plugin headers (handles "Requires at least:" and "* Requires at least:")
requires_at_least="$(
  grep -m1 -E '^(Requires at least:|[[:space:]]*\*[[:space:]]*Requires at least:)' "$plugin_file" \
    | sed -E 's/.*Requires at least:[[:space:]]*//' || true
)"
tested_up_to="$(
  grep -m1 -E '^(Tested up to:|[[:space:]]*\*[[:space:]]*Tested up to:)' "$plugin_file" \
    | sed -E 's/.*Tested up to:[[:space:]]*//' || true
)"
requires_php="$(
  grep -m1 -E '^(Requires PHP:|[[:space:]]*\*[[:space:]]*Requires PHP:)' "$plugin_file" \
    | sed -E 's/.*Requires PHP:[[:space:]]*//' || true
)"
version="$(
  grep -m1 -E '^(Version:|[[:space:]]*\*[[:space:]]*Version)' "$plugin_file" \
    | sed -E 's/.*Version[: ]+[[:space:]]*//; s/\r//; s/[[:space:]]+$//' || true
)"

# Fallback if still empty
if [[ -z "$version" ]]; then
  version_line="$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version' "$plugin_file" || true)"
  version="$(sed -E 's/.*Version[: ]+[[:space:]]*//; s/\r//; s/[[:space:]]+$//' <<< "$version_line")"
fi

if [[ -z "$version" ]]; then
  echo "[ERROR] Could not extract Version from $plugin_file"
  exit 1
fi

[[ -n "$version" ]] || { echo "[ERROR] Could not extract Version from $plugin_file"; exit 1; }

# =====================================================
# GENERATE STATIC index.json FOR GITHUB DELIVERY
# =====================================================
echo "[INFO] Generating index.json for GitHub delivery..."
github_user="${GITHUB_REPO%%/*}"
repo_name="${GITHUB_REPO#*/}"
cdn_path="https://raw.githubusercontent.com/$github_user/$repo_name/main/uupd"

mkdir -p "$static_subfolder"

php "$GENERATOR_SCRIPT" \
  "$plugin_file" \
  "$CHANGELOG_FILE" \
  "$static_subfolder" \
  "$github_user" \
  "$cdn_path" \
  "$repo_name" \
  "$repo_name" \
  "$STATIC_FILE" \
  "$ZIP_NAME"

if [[ -f "$static_subfolder/index.json" ]]; then
  echo "[OK] index.json generated: $static_subfolder/index.json"
else
  echo "[ERROR] Failed to generate index.json"
fi

# =====================================================
# CREATE README.TXT
# =====================================================
{
  echo "=== $PLUGIN_NAME ==="
  echo "Contributors: reallyusefulplugins"
  echo "Donate link: https://reallyusefulplugins.com/donate"
  echo "Tags: $PLUGIN_TAGS"
  echo "Requires at least: $requires_at_least"
  echo "Tested up to: $tested_up_to"
  echo "Stable tag: $version"
  echo "Requires PHP: $requires_php"
  echo "License: GPL-2.0-or-later"
  echo "License URI: https://www.gnu.org/licenses/gpl-2.0.html"
  echo
} > "$temp_readme"

cat "$STATIC_FILE" >> "$temp_readme"
echo >> "$temp_readme"
echo "== Changelog ==" >> "$temp_readme"
cat "$CHANGELOG_FILE" >> "$temp_readme"

if [[ -f "$readme_file" ]]; then
  cp -f "$readme_file" "$readme_file.bak"
fi
mv -f "$temp_readme" "$readme_file"

# =====================================================
# GIT COMMIT AND PUSH CHANGES
# =====================================================
pushd "$plugin_dir" >/dev/null
git add -A

if ! git diff --cached --quiet; then
  git commit -m "Version $version Release"
  git push origin main
  echo "[OK] Git commit and push complete."
else
  echo "[INFO] No changes to commit."
fi
popd >/dev/null

# =====================================================
# ZIP PLUGIN FOLDER
# =====================================================
sevenzip_win="/c/Program Files/7-Zip/7z.exe"
zip_file="$script_dir/$ZIP_NAME"

if [[ -x "$sevenzip_win" ]]; then
  pushd "$script_dir" >/dev/null
  "$sevenzip_win" a -tzip "$zip_file" "$PLUGIN_SLUG" >/dev/null
  popd >/dev/null
else
  # Fallback to tar -a (creates zip if extension is .zip)
  pushd "$script_dir" >/dev/null
  tar -a -c -f "$zip_file" "$PLUGIN_SLUG"
  popd >/dev/null
fi

if [[ -f "$zip_file" ]]; then
  echo "[OK] Zipped to: $zip_file"
else
  echo "[ERROR] Failed to create archive."
  exit 1
fi

# =====================================================
# DEPLOY LOGIC
# =====================================================
if [[ "${DEPLOY_TARGET,,}" == "private" ]]; then
  if [[ -z "${DEST_DIR:-}" ]]; then
    echo "[ERROR] DEST_DIR is not set for private deploy."
    exit 1
  fi
  mkdir -p "$DEST_DIR"
  cp -f "$zip_file" "$DEST_DIR/"
  echo "[OK] Copied to $DEST_DIR"
else
  echo "[INFO] Deploying to GitHub..."
  if [[ -z "${GITHUB_TOKEN:-}" && -f "$TOKEN_FILE" ]]; then
    # Strip CR/LF
    GITHUB_TOKEN="$(tr -d '\r\n' < "$TOKEN_FILE")"
  fi
  if [[ -z "${GITHUB_TOKEN:-}" ]]; then
    echo "[ERROR] GITHUB_TOKEN not available (set env var or provide TOKEN_FILE)"
    exit 1
  fi

  release_tag="v$version"
  body_file="$(mktemp)"
  changelog_body="$(sed ':a;N;$!ba;s/\r//g' "$CHANGELOG_FILE" \
    | sed 's/\\/\\\\/g; s/"/\\"/g; s/$/\\n/' \
    | tr -d '\n')"


  cat >"$body_file" <<JSON
{
  "tag_name": "$release_tag",
  "name": "$version",
  "body": "$changelog_body",
  "draft": false,
  "prerelease": false
}
JSON

  # Check existing release
  status=$(curl -sS -o "$TMPDIR/github_release_response.json" -w "%{http_code}" \
    -H "Authorization: token $GITHUB_TOKEN" \
    -H "Accept: application/vnd.github+json" \
    "https://api.github.com/repos/$GITHUB_REPO/releases/tags/$release_tag" || true)

  release_id=""
  if [[ "$status" == "200" ]]; then
    release_id="$(grep -m1 -E '"id":[[:space:]]*[0-9]+' "$TMPDIR/github_release_response.json" | head -1 | sed -E 's/.*"id":[[:space:]]*([0-9]+).*/\1/')"
    echo "[INFO] Release exists. Updating body (id=$release_id)..."
    curl -sS -X PATCH "https://api.github.com/repos/$GITHUB_REPO/releases/$release_id" \
      -H "Authorization: token $GITHUB_TOKEN" \
      -H "Accept: application/vnd.github+json" \
      -H "Content-Type: application/json" \
      --data-binary "@$body_file" >/dev/null
  else
    echo "[INFO] Creating new release..."
    curl -sS -X POST "https://api.github.com/repos/$GITHUB_REPO/releases" \
      -H "Authorization: token $GITHUB_TOKEN" \
      -H "Accept: application/vnd.github+json" \
      -H "Content-Type: application/json" \
      --data-binary "@$body_file" > "$TMPDIR/github_release_response.json"
    release_id="$(grep -m1 -E '"id":[[:space:]]*[0-9]+' "$TMPDIR/github_release_response.json" | head -1 | sed -E 's/.*"id":[[:space:]]*([0-9]+).*/\1/')"
  fi

  if [[ -z "$release_id" ]]; then
    echo "[ERROR] Could not determine release ID."
    cat "$TMPDIR/github_release_response.json" || true
    exit 1
  fi
  echo "[OK] Using Release ID: $release_id"

  # Upload asset (replace if exists)
  asset_name="$(basename "$zip_file")"
  curl -sS -X POST "https://uploads.github.com/repos/$GITHUB_REPO/releases/$release_id/assets?name=$asset_name" \
    -H "Authorization: token $GITHUB_TOKEN" \
    -H "Accept: application/vnd.github+json" \
    -H "Content-Type: application/zip" \
    --data-binary @"$zip_file" >/dev/null

  rm -f "$body_file"
fi

echo
echo "[OK] Deployment complete: $DEPLOY_TARGET"
sleep 4
