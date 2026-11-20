// Offline "Save edition": stores the reader HTML, its page images and the loaded JS/CSS in the Cache API.
const CACHE = 'redstar-offline';
const KEY = 'redstar:saved';

export const offlineSupported = () => typeof window !== 'undefined' && 'caches' in window && 'serviceWorker' in navigator;
const list = () => JSON.parse(localStorage.getItem(KEY) || '{}');
export const isSaved = (slug) => Boolean(list()[slug]);

export async function saveEdition(slug, pageUrl, imageUrls, onProgress = () => {}) {
    const cache = await caches.open(CACHE);
    const assets = performance.getEntriesByType('resource').map((r) => r.name)
        .filter((u) => new URL(u).origin === location.origin && new URL(u).pathname.startsWith('/build/'));
    const urls = [pageUrl, ...assets, ...imageUrls];
    let done = 0;
    for (const u of urls) {
        try {
            // Inertia pages: a plain fetch returns full HTML (props incl. text + signed image URLs).
            const res = await fetch(u, { credentials: 'same-origin' });
            if (res.ok) await cache.put(u === pageUrl ? u.split('?')[0] : u, res);
        } catch (_) { /* skip failed item */ }
        onProgress(Math.round((++done / urls.length) * 100));
    }
    localStorage.setItem(KEY, JSON.stringify({ ...list(), [slug]: { pageUrl: pageUrl.split('?')[0], images: imageUrls } }));
}

export async function removeEdition(slug) {
    const saved = list()[slug];
    if (!saved) return;
    const cache = await caches.open(CACHE);
    await Promise.all([saved.pageUrl, ...saved.images].map((u) => cache.delete(u)));
    const all = list(); delete all[slug];
    localStorage.setItem(KEY, JSON.stringify(all));
}
