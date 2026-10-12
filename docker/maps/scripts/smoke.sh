#!/usr/bin/env bash
# Checks that the public map endpoints answer through nginx.
#
#   smoke.sh [base-url]   (default http://localhost:${APP_PORT:-8080})
source "$(dirname "$0")/lib.sh"

base="${1:-http://localhost:${APP_PORT:-8080}}"
tilejson="$(curl -fsS "$base/maps/tiles/osm/tiles.json")" || die "no TileJSON at $base/maps/tiles/osm/tiles.json"
# The dataset's own maxzoom comes first; vector_layers repeat the key later.
max_zoom="$(grep -oE '"maxzoom": ?[0-9]+' <<<"$tilejson" | head -1 | grep -oE '[0-9]+$')"
[[ -n "$max_zoom" ]] || die "TileJSON has no maxzoom"
grep -q '"/maps/tiles/osm/{z}/{x}/{y}"' <<<"$tilejson" || die "TileJSON tile URLs are not rewritten to /maps/"

failures=0
check() {
    local path="$1" type="$2" encoding="${3:-}"
    local headers
    if ! headers="$(curl -fsS -o /dev/null -D - -H 'Accept-Encoding: br, gzip' "$base$path")"; then
        log "FAIL $path"; failures=$((failures + 1)); return
    fi
    headers="$(tr -d '\r' <<<"$headers" | tr '[:upper:]' '[:lower:]')"
    local problems=""
    grep -q "^content-type: .*$type" <<<"$headers" || problems+=" content-type"
    [[ -z "$encoding" ]] || grep -q "^content-encoding: $encoding" <<<"$headers" || problems+=" content-encoding"
    grep -q '^cache-control: ' <<<"$headers" || problems+=" cache-control"
    if [[ -n "$problems" ]]; then
        log "FAIL $path:$problems"; failures=$((failures + 1))
    else
        log "ok   $path"
    fi
}

# Tile coordinates: world, Europe, Moscow at the highest zoom the dataset has.
z=$max_zoom
x=$(( 154 * (1 << z) / 256 ))
y=$(( 79 * (1 << z) / 256 ))

check /maps/tiles/osm/tiles.json json
check /maps/tiles/osm/0/0/0 vector-tile 'br\|gzip'
check /maps/tiles/osm/5/17/10 vector-tile 'br\|gzip'
check "/maps/tiles/osm/$z/$x/$y" vector-tile 'br\|gzip'
check /maps/assets/glyphs/noto_sans_regular/0-255.pbf octet-stream
check /maps/assets/glyphs/noto_sans_bold/1024-1279.pbf octet-stream
check /maps/assets/sprites/base.json json
check /maps/assets/sprites/base@2x.png png

# Internal endpoints must stay closed.
code="$(curl -s -o /dev/null -w '%{http_code}' "$base/maps/status")"
[[ "$code" == 404 ]] || { log "FAIL /maps/status answered $code, expected 404"; failures=$((failures + 1)); }

(( failures == 0 )) || die "$failures map check(s) failed"
log "all map checks passed (dataset max zoom $max_zoom)"
