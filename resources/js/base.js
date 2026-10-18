// URL dasar aplikasi, mendukung instalasi di subfolder (mis. https://bbwsnt2.com/iris).
const meta = (n) => (document.querySelector(`meta[name="${n}"]`)?.content || '').replace(/\/$/, '');
/** Untuk halaman/route Laravel. */
export const BASE = meta('base-url');
/** Untuk file statis di folder public (geojson, sw.js). */
export const ASSET = meta('asset-url') || BASE;
/** Versi file GeoJSON (berubah setiap batas D.I. diedit) untuk memaksa browser memuat ulang. */
export const GEOJSON_VERSI = document.querySelector('meta[name="geojson-versi"]')?.content || '';
