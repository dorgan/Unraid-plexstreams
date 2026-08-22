#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 0 ]]; then
    echo "Usage: $(basename "$0")" >&2
    exit 1
fi

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
plugin="plexstreams"
source_dir="$script_dir/$plugin"

if [[ ! -d "$source_dir" ]]; then
    echo "Plugin source directory not found: $source_dir" >&2
    exit 1
fi

repo_root="$(dirname "$script_dir")"
archive_dir="$repo_root/archive"
plg_file="$repo_root/$plugin.plg"
version="$(date +"%Y.%m.%d")"
architecture="-x86_64-1"

if [[ ! -f "$plg_file" ]]; then
    echo "Plugin manifest not found: $plg_file" >&2
    exit 1
fi

last_release_tag="${LAST_RELEASE_TAG:-$(git -C "$repo_root" describe --tags --abbrev=0 HEAD 2>/dev/null || true)}"

if [[ -z "$last_release_tag" ]]; then
    echo "No release tag found. Set LAST_RELEASE_TAG to the commit or tag before this release." >&2
    exit 1
fi

if ! git -C "$repo_root" rev-parse --verify --quiet "$last_release_tag^{commit}" >/dev/null; then
    echo "Invalid LAST_RELEASE_TAG: $last_release_tag" >&2
    exit 1
fi

for suffix in '' {a..z}; do
    package="$archive_dir/$plugin-$version$suffix$architecture.txz"
    if [[ ! -e "$package" ]]; then
        version="$version$suffix"
        break
    fi
done

if [[ -e "${package:-}" ]]; then
    echo "No available package suffixes for $version" >&2
    exit 1
fi

temp_dir="$(mktemp -d "${TMPDIR:-/tmp}/$plugin.XXXXXX")"
cleanup() {
    rm -rf "$temp_dir"
}
trap cleanup EXIT

release_notes="$temp_dir/release-notes.txt"
git -C "$repo_root" log --format='%s' "$last_release_tag..HEAD" | \
    awk 'NF { print "        - " $0 }' > "$release_notes"

if [[ ! -s "$release_notes" ]]; then
    echo "No commits found after $last_release_tag; refusing to create an empty release." >&2
    exit 1
fi

mkdir -p "$archive_dir"

tar -C "$source_dir" \
    --exclude='./pkg_build.sh' \
    --exclude='./sftp-config.json' \
    --exclude='./.DS_Store' \
    --exclude='./tests' \
    -cf - . | tar -C "$temp_dir" -xf -

tar -C "$temp_dir" --uid 0 --gid 0 -cJf "$package" usr
md5="$(md5 -q "$package")"

sed -i '' -E "s#(ENTITY[[:space:]]+version[^\"]*).*#\\1\"$version\">#" "$plg_file"
sed -i '' -E "s#(ENTITY[[:space:]]+md5[^\"]*).*#\\1\"$md5\">#" "$plg_file"
awk -v version="$version" -v release_notes="$release_notes" '
    /##&name;/ {
        print
        print "        ###" version
        while ((getline line < release_notes) > 0) {
            print line
        }
        close(release_notes)
        print ""
        next
    }
    { print }
' "$plg_file" > "$temp_dir/manifest.plg"
mv "$temp_dir/manifest.plg" "$plg_file"

echo "Created $package"
echo "MD5: $md5"
echo "Release notes generated from $last_release_tag..HEAD"
