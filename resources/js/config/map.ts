/**
 * Single source of truth for the map tile layer.
 * Override via VITE_MAP_TILE_URL / VITE_MAP_TILE_ATTRIBUTION; components must not hardcode tile URLs.
 */
export const mapConfig = {
    tileUrl: import.meta.env.VITE_MAP_TILE_URL || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    attribution:
        import.meta.env.VITE_MAP_TILE_ATTRIBUTION ||
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 18,
} as const;
