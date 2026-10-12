#!/usr/bin/env bash
# Builds a zoom-limited planet tileset from the pinned dated OSM snapshot.
#
#   fetch-tiles.sh <max-zoom> [--verify-full]
#
# Writes data/<snapshot>.z0-<N>.versatiles plus a .manifest (source, zoom, sha256, size).
# Never touches current.versatiles / previous.versatiles; activate with switch.sh.
#
# The published SHA-256 covers only the full planet file. By default the zoom range is
# extracted straight from the HTTPS source (range requests, no full download) and our own
# checksum of the result is recorded. --verify-full downloads the whole planet first
# (~60+ GB), verifies the published checksum, then extracts locally and deletes it.
source "$(dirname "$0")/lib.sh"

max_zoom="${1:-}"
verify_full="${2:-}"
[[ "$max_zoom" =~ ^[0-9]+$ ]] || die "usage: fetch-tiles.sh <max-zoom> [--verify-full]"

mkdir -p "$DATA_DIR"
name="$OSM_SNAPSHOT.z0-$max_zoom.versatiles"
final="$DATA_DIR/$name"
tmp="$name.tmp.versatiles"

[[ -e "$final" ]] && { log "$name already exists, nothing to do"; exit 0; }

# Room for the new file next to the current and previous ones. Override when measured.
min_free="${MIN_FREE_GB:-$(( max_zoom <= 8 ? 5 : max_zoom <= 10 ? 30 : 80 ))}"
[[ "$verify_full" == "--verify-full" ]] && min_free=$(( min_free + 80 ))
available="$(free_gb "$DATA_DIR")"
(( available >= min_free )) || die "need ${min_free} GB free in $DATA_DIR, have ${available} GB (set MIN_FREE_GB to override)"

source_ref="$OSM_SOURCE_URL"
full=""
if [[ "$verify_full" == "--verify-full" ]]; then
    full="$OSM_SNAPSHOT.versatiles"
    log "downloading full snapshot $OSM_SOURCE_URL"
    curl -fL -C - -o "$DATA_DIR/$full" "$OSM_SOURCE_URL"
    expected="$(curl -fsSL "$OSM_SOURCE_URL.sha256" | cut -d' ' -f1)"
    actual="$(sha256_of "$DATA_DIR/$full")"
    [[ "$expected" == "$actual" ]] || die "published sha256 mismatch: expected $expected, got $actual"
    log "published sha256 verified"
    source_ref="/work/$full"
fi

trap 'rm -f "$DATA_DIR/$tmp"' EXIT
log "extracting zoom 0-$max_zoom from $source_ref"
versatiles convert --max-zoom "$max_zoom" "$source_ref" "$tmp"
versatiles probe "$tmp" >/dev/null || die "probe failed for $tmp"

checksum="$(sha256_of "$DATA_DIR/$tmp")"
size_bytes="$(wc -c < "$DATA_DIR/$tmp" | tr -d ' ')"
mv "$DATA_DIR/$tmp" "$final"
trap - EXIT
[[ -n "$full" ]] && rm -f "$DATA_DIR/$full"

cat > "$final.manifest" <<MANIFEST
source=$OSM_SOURCE_URL
snapshot=$OSM_SNAPSHOT
min_zoom=0
max_zoom=$max_zoom
published_checksum_verified=$([[ -n "$full" ]] && echo yes || echo no)
sha256=$checksum
size_bytes=$size_bytes
created_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)
MANIFEST

log "ready: $name ($(( size_bytes / 1024 / 1024 )) MB). Activate with: docker/maps/scripts/switch.sh $name"
