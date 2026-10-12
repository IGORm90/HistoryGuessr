#!/usr/bin/env bash
# Swaps current.versatiles and previous.versatiles back, e.g. after a bad update.
source "$(dirname "$0")/lib.sh"

current="$(readlink "$DATA_DIR/current.versatiles" 2>/dev/null || true)"
previous="$(readlink "$DATA_DIR/previous.versatiles" 2>/dev/null || true)"
[[ -n "$previous" && -f "$DATA_DIR/$previous" ]] || die "no previous tileset to roll back to"

exec "$(dirname "$0")/switch.sh" "$previous" "$@"
