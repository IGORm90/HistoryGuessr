#!/usr/bin/env bash
# Downloads the pinned glyphs and sprites into assets/ and verifies their checksums.
source "$(dirname "$0")/lib.sh"

fetch() {
    local url="$1" sha="$2" dest="$3"
    local archive
    archive="$(mktemp)"
    trap 'rm -f "$archive"' RETURN
    log "downloading $url"
    curl -fsSL -o "$archive" "$url"
    [[ "$(sha256_of "$archive")" == "$sha" ]] || die "sha256 mismatch for $url"
    rm -rf "$dest.new" && mkdir -p "$dest.new"
    tar -xzf "$archive" -C "$dest.new"
    rm -rf "$dest" && mv "$dest.new" "$dest"
}

mkdir -p "$ASSETS_DIR"
fetch "$FONTS_URL" "$FONTS_SHA256" "$ASSETS_DIR/glyphs"
fetch "$SPRITES_URL" "$SPRITES_SHA256" "$ASSETS_DIR/sprites"
log "assets ready in $ASSETS_DIR"
