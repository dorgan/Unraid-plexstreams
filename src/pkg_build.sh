#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
    echo "Usage: $(basename "$0") directory_name" >&2
    exit 1
fi

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
source_dir="$script_dir/$1"

if [[ ! -d "$source_dir" ]]; then
    echo "Plugin source directory not found: $source_dir" >&2
    exit 1
fi

plugin="$(basename "$source_dir")"
repo_root="$(dirname "$script_dir")"
archive_dir="$repo_root/archive"
plg_file="$repo_root/$plugin.plg"
version="$(date +"%Y.%m.%d")"
architecture="-x86_64-1"

if [[ ! -f "$plg_file" ]]; then
    echo "Plugin manifest not found: $plg_file" >&2
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

mkdir -p "$archive_dir"

tar -C "$source_dir" \
    --exclude='./pkg_build.sh' \
    --exclude='./sftp-config.json' \
    --exclude='./.DS_Store' \
    -cf - . | tar -C "$temp_dir" -xf -

tar -C "$temp_dir" --uid 0 --gid 0 -cJf "$package" usr
md5="$(md5 -q "$package")"

sed -i '' -E "s#(ENTITY[[:space:]]+version[^\"]*).*#\\1\"$version\">#" "$plg_file"
sed -i '' -E "s#(ENTITY[[:space:]]+md5[^\"]*).*#\\1\"$md5\">#" "$plg_file"
sed -i '' "/##&name;/a\\
###$version" "$plg_file"

echo "Created $package"
echo "MD5: $md5"
