/**
 * Fails when the production build (public/build) or the map style mention an external map service.
 * Run after `vite build` (`npm run check:maps`, part of `make build`).
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('../..', import.meta.url));
const FORBIDDEN = [
    'maptiler.com',
    'tile.openstreetmap.org',
    'tiles.versatiles.org',
    'download.versatiles.org',
    'api.mapbox.com',
    'tiles.mapbox.com',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'unpkg.com',
    'cdn.jsdelivr.net',
];

function files(dir) {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        return statSync(path).isDirectory() ? files(path) : [path];
    });
}

const targets = [
    ...files(join(ROOT, 'public/build')).filter((path) => /\.(js|css|json|html)$/.test(path)),
    join(ROOT, 'resources/js/config/map-style.json'),
];

const hits = targets.flatMap((path) => {
    const content = readFileSync(path, 'utf8');
    return FORBIDDEN.filter((host) => content.includes(host)).map((host) => `${path.replace(ROOT, '')}: ${host}`);
});

if (hits.length > 0) {
    console.error(`External map services referenced:\n  ${hits.join('\n  ')}`);
    process.exit(1);
}
console.log(`No external map services in ${targets.length} files.`);
