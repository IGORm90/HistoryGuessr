# Shared helpers for the map maintenance scripts. Sourced, not executed.
set -euo pipefail

MAPS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_DIR="$(cd "$MAPS_DIR/../.." && pwd)"
DATA_DIR="$MAPS_DIR/data"
ASSETS_DIR="$MAPS_DIR/assets"

# shellcheck source=../versions.env
source "$MAPS_DIR/versions.env"

log() { printf '[maps] %s\n' "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }

# Runs the versatiles CLI from the image pinned in docker-compose.yml, with the data
# directory mounted writable at /work (the `maps` service itself mounts it read-only).
versatiles() {
    docker compose --project-directory "$REPO_DIR" run --rm --no-deps -T \
        -v "$DATA_DIR:/work" -w /work maps "$@"
}

sha256_of() {
    if command -v sha256sum >/dev/null; then sha256sum "$1" | cut -d' ' -f1; else shasum -a 256 "$1" | cut -d' ' -f1; fi
}

# Free space in GB on the filesystem that holds $1.
free_gb() { df -Pk "$1" | awk 'NR==2 { printf "%d", $4 / 1024 / 1024 }'; }

# Swaps a symlink atomically: create under a temporary name, then rename over the old one.
relink() {
    local target="$1" link="$2"
    ln -sfn "$target" "$link.new"
    mv -f "$link.new" "$link" 2>/dev/null || die "cannot replace $link"
}
