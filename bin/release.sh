#!/bin/bash

# Script to automate the new version release process.
#
# Usage:
#   ./bin/release.sh patch        (e.g., 1.0.0 -> 1.0.1)
#   ./bin/release.sh minor        (e.g., 1.0.1 -> 1.1.0)
#   ./bin/release.sh major        (e.g., 1.1.0 -> 2.0.0)
#   ./bin/release.sh 1.2.3        (specific version)
#

set -e # Exit immediately if a command fails.

# --- Configuration Files ---
COMPOSER_JSON="composer.json"
ROLEMANAGER_PHP="src/RoleManager.php"
CHANGELOG="CHANGELOG.md"

# --- Helper Functions ---

function get_current_version() {
  # Use jq to extract the version from composer.json.
  # This is more robust and avoids potential quoting issues.
  local version
  version=$(jq -r .version "$COMPOSER_JSON")

  if [ -z "$version" ] || [ "$version" = "null" ]; then
    echo "Error: Could not extract version from composer.json using jq." >&2
    exit 1
  fi

  echo "$version"
}

function update_json_version() {
    local new_version=$1
    php -r '$f = getcwd() . "/'${COMPOSER_JSON}'"; $c = json_decode(file_get_contents($f), true); $c["version"] = "'$new_version'"; file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");'
    echo "Updated ${COMPOSER_JSON} to version $new_version"
}

function update_php_version() {
    local new_version=$1
    # sed -i for GNU sed. May require 'sed -i ""' on macOS.
    sed -i.bak -E "s/(public const VERSION = ')[^']+'/\1$new_version'/" "$ROLEMANAGER_PHP" && rm "${ROLEMANAGER_PHP}.bak"
    echo "Updated ${ROLEMANAGER_PHP} to version $new_version"
}

function update_changelog() {
    local new_version=$1
    local current_version=$2
    local today
    today=$(date +%F)
    local temp_changelog
    temp_changelog=$(mktemp)

    echo "Updating $CHANGELOG for v$new_version..."

    # Check if there are changes to be released
    local unreleased_content
    unreleased_content=$(awk '/^## \[Unreleased\]/{f=1;next} /^## \[/{f=0} f' "$CHANGELOG" | grep -v '^### ' | grep -v '^\s*$' || true)
    if [ -z "$unreleased_content" ]; then
        echo "WARNING: No new entries found under [Unreleased] in $CHANGELOG."
        read -p "Continue anyway? (y/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[yY]$ ]]; then
            rm "$temp_changelog"
            exit 1
        fi
    fi

    # 1. Crea una nuova sezione [Unreleased] vuota
    {
        echo "## [Unreleased]"
        echo
        echo "### Added"
        echo
        echo "### Changed"
        echo
        echo "### Deprecated"
        echo
        echo "### Removed"
        echo
        echo "### Fixed"
        echo
        echo "### Security"
        echo
    } > "$temp_changelog"

    # 2. Add the new version header
    echo "## [$new_version] - $today" >> "$temp_changelog"

    # 3. Move the content from the old [Unreleased] section
    awk '/^## \[Unreleased\]/{f=1;next} /^## \[/{f=0} f' "$CHANGELOG" >> "$temp_changelog"

    # 4. Append the rest of the changelog (previous versions)
    awk '/^## \[Unreleased\]/{f=1} /^## \[/ && f{f=0; print; next} !flag && !/^\[Unreleased\]:/' "$CHANGELOG" >> "$temp_changelog"

    # 5. Add the links at the bottom
    echo "" >> "$temp_changelog"
    echo "[Unreleased]: https://github.com/sebastiani/rolemanager/compare/v$new_version...HEAD" >> "$temp_changelog"
    echo "[$new_version]: https://github.com/sebastiani/rolemanager/compare/v$current_version...v$new_version" >> "$temp_changelog"
    awk '/^\[[0-9]+\.[0-9]+\.[0-9]+\]:/' "$CHANGELOG" >> "$temp_changelog"

    # Replace the old file with the new one
    mv "$temp_changelog" "$CHANGELOG"
}


# --- Main Script ---

# Go to the project's root directory (where composer.json is located).
# This allows the script to be run from any location and handles cases where
# the script might be in the root or in a subdirectory like 'bin/'.
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &>/dev/null && pwd)
cd "$SCRIPT_DIR"

# If composer.json is not here, we are likely in a subdir. Go up.
if [ ! -f "composer.json" ]; then
    cd ..
fi

# Final check to ensure we are in the correct directory
if [ ! -f "composer.json" ]; then
    echo "Error: Could not find project root (composer.json)." >&2
    exit 1
fi

if [ -z "$1" ]; then
    echo "Error: Please specify a full version string (e.g., 1.0.1) or an increment type (patch, minor, major)."
    echo "Usage: $0 <new_version> | patch | minor | major"
    exit 1
fi

CURRENT_VERSION=$(get_current_version)
echo "Current version: $CURRENT_VERSION"

if [[ "$1" =~ ^(patch|minor|major)$ ]]; then
    IFS='.' read -r -a v_parts <<< "$CURRENT_VERSION"
    major=${v_parts[0]}
    minor=${v_parts[1]}
    patch=${v_parts[2]}

    case "$1" in
        patch) patch=$((patch + 1));;
        minor) minor=$((minor + 1)); patch=0;;
        major) major=$((major + 1)); minor=0; patch=0;;
    esac
    NEW_VERSION="$major.$minor.$patch"
else
    if ! [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.-]+)?$ ]]; then
        echo "Error: Invalid version format. Use X.Y.Z (e.g., 1.0.1)"
        exit 1
    fi
    NEW_VERSION=$1
fi

echo "New version will be: $NEW_VERSION"
read -p "Are you sure you want to proceed? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[yY]$ ]]; then
    exit 1
fi

# 1. Update files
update_json_version "$NEW_VERSION"
update_php_version "$NEW_VERSION"
update_changelog "$NEW_VERSION" "$CURRENT_VERSION"

git status

# 2. Git commit and tag
echo "Committing changes..."
read -p "Ready to execute 'git' command, press ENTER to continue..." -n 1 -r
echo
git add "$COMPOSER_JSON" "$ROLEMANAGER_PHP" "$CHANGELOG"
git commit -m "chore(release): version $NEW_VERSION"

echo "Creating git tag..."
git tag -a "v$NEW_VERSION" -m "Version $NEW_VERSION"

echo "Release v$NEW_VERSION prepared locally."
read -p "Do you want to push to origin? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[yY]$ ]]; then
    echo "Pushing commit and tags..."
    git push && git push --tags
    echo "Release complete."
else
    echo "Action cancelled. Run 'git push && git push --tags' to publish."
fi