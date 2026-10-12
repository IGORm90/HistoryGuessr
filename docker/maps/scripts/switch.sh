#!/usr/bin/env bash
# Activates a tileset: current.versatiles -> <file>, keeping the old one as previous.versatiles.
#
#   switch.sh <file in data/> [base-url]
#
# Restarts the `maps` service (it keeps the old file open), drops nginx's map cache and runs
# the smoke test. If the smoke test fails, the previous tileset is restored automatically.
source "$(dirname "$0")/lib.sh"

file="${1:-}"
base_url="${2:-}"
[[ -n "$file" && -f "$DATA_DIR/$file" ]] || die "usage: switch.sh <file in $DATA_DIR> [base-url]"
[[ "$file" == */* ]] && die "pass a file name inside data/, not a path"

old="$(readlink "$DATA_DIR/current.versatiles" 2>/dev/null || true)"
[[ "$old" == "$file" ]] && { log "$file is already current"; exit 0; }

wait_healthy() {
    local container status
    container="$(docker compose --project-directory "$REPO_DIR" ps -q maps)"
    for _ in $(seq 1 30); do
        status="$(docker inspect -f '{{.State.Health.Status}}' "$container" 2>/dev/null || true)"
        [[ "$status" == healthy ]] && return 0
        sleep 2
    done
    log "maps service is not healthy (status: ${status:-unknown})"
}

restart() {
    docker compose --project-directory "$REPO_DIR" up -d maps >/dev/null 2>&1
    docker compose --project-directory "$REPO_DIR" restart maps >/dev/null 2>&1
    wait_healthy
    # Proxy-cached tiles of the old dataset share the new dataset's URLs.
    docker compose --project-directory "$REPO_DIR" exec -T nginx sh -c 'rm -rf /var/cache/nginx/maps/*' || true
}

relink "$file" "$DATA_DIR/current.versatiles"
[[ -n "$old" ]] && relink "$old" "$DATA_DIR/previous.versatiles"
log "current -> $file${old:+ (previous -> $old)}"
restart

if ! "$(dirname "$0")/smoke.sh" ${base_url:+"$base_url"}; then
    if [[ -n "$old" ]]; then
        log "smoke test failed, restoring $old"
        relink "$old" "$DATA_DIR/current.versatiles"
        restart
    fi
    die "smoke test failed for $file"
fi
log "switched to $file. Older tilesets in data/ can be deleted by hand; never delete current or previous."
