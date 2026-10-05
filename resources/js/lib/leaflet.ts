import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { mapConfig } from '@/config/map';

// Leaflet prefixes icon URLs with a path sniffed from its CSS, which breaks bundled asset URLs.
// Drop the sniffing and point the default icon at the bundled files.
delete (L.Icon.Default.prototype as unknown as { _getIconUrl?: unknown })._getIconUrl;
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl });

export function createTileLayer(): L.TileLayer {
    return L.tileLayer(mapConfig.tileUrl, {
        attribution: mapConfig.attribution,
        maxZoom: mapConfig.maxZoom,
    });
}

export { L };
