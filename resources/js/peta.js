import L from 'leaflet';
import { ASSET, BASE, GEOJSON_VERSI } from './base';

const BATAS_NTT = [[-11.2, 118.8], [-8.0, 125.3]];
const fmt = (v, d = 0) => (v === null || v === undefined || v === '') ? '–'
    : Number(v).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function baseLayers() {
    return {
        'Peta jalan': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; OpenStreetMap',
        }),
        'Citra satelit': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19, attribution: 'Tiles &copy; Esri',
        }),
        'Topografi': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19, attribution: 'Tiles &copy; Esri',
        }),
    };
}

function chip(label, warna) {
    return `<span style="display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:600;background:${warna}22;color:#1E2A2F"><span style="width:7px;height:7px;border-radius:50%;background:${warna}"></span>${esc(label)}</span>`;
}

function popupDi(d, internal) {
    if (!d) return '';
    return `<div style="min-width:220px">
        <div style="font-family:'Barlow Semi Condensed';font-size:18px;font-weight:600;line-height:1.1">D.I. ${esc(d.nama)}</div>
        <div style="font-size:12px;color:#4A585E;margin:2px 0 6px">${esc(d.kabupaten ?? '')}${d.satgas ? ' · Satgas ' + esc(d.satgas) : ''}</div>
        ${chip(d.status_label, d.warna)}
        <table style="margin-top:8px;font-size:12px;width:100%">
            <tr><td style="color:#4A585E">Luas fungsional</td><td style="text-align:right">${fmt(d.luas_ha)} Ha</td></tr>
            <tr><td style="color:#4A585E">Musim tanam</td><td style="text-align:right">${esc(d.mt ?? 'Belum dijadwalkan')}</td></tr>
            <tr><td style="color:#4A585E">Sub-D.I. melapor</td><td style="text-align:right">${d.sub_lapor} dari ${d.jumlah_sub}</td></tr>
            <tr><td style="color:#4A585E">Luas tanam</td><td style="text-align:right">${fmt(d.luas_tanam_ha, 1)} Ha</td></tr>
            <tr><td style="color:#4A585E">Tanaman kekeringan</td><td style="text-align:right">${fmt(d.luas_kering_ha, 1)} Ha</td></tr>
            <tr><td style="color:#4A585E">Puso</td><td style="text-align:right">${fmt(d.luas_puso_ha, 1)} Ha</td></tr>
            ${internal ? `<tr><td style="color:#4A585E">Total debit intake</td><td style="text-align:right">${fmt(d.debit_total_lps, 1)} L/dt</td></tr>` : ''}
        </table>
        <div style="font-size:11px;color:#7C898E;margin-top:6px">Laporan terakhir: ${esc(d.laporan_terakhir ?? 'belum ada')}</div>
        ${internal ? `<a href="${BASE}/master/daerah-irigasi/${d.id}" style="display:inline-block;margin-top:8px;font-size:12px;font-weight:600;color:#0F5E6E">Buka profil D.I.</a>` : ''}
    </div>`;
}

function popupSub(s) {
    const l = s.laporan;
    const petugas = (s.petugas || []).slice(0, 4).map((p) => `<div style="display:flex;justify-content:space-between;gap:8px;align-items:center;margin-top:3px">
        <span>${esc(p.nama)} <span style="color:#7C898E">${esc(p.jabatan ?? '')}</span></span>
        ${p.wa ? `<a href="${p.wa}" target="_blank" rel="noopener" style="background:#1f8f4e;color:#fff;border-radius:4px;padding:1px 6px;font-weight:600">WA</a>` : ''}</div>`).join('');
    const foto = l && l.foto.length ? galeriPopup(l.foto.map((f) => ({ ...f, label: l.tanggal })), 230) : '';
    return `<div style="min-width:230px;font-size:12px">
        <div style="font-family:'Barlow Semi Condensed';font-size:17px;font-weight:600;line-height:1.1">${esc(s.nama)}</div>
        <div style="color:#4A585E;margin:2px 0 6px">D.I. ${esc(s.di)} · ${fmt(s.luas_ha)} Ha${s.titik_pasti ? '' : ' · <i>titik belum diatur</i>'}</div>
        ${chip(s.status_label, s.warna)}
        ${l ? `<table style="margin-top:6px;width:100%">
            <tr><td style="color:#4A585E">Tanggal</td><td style="text-align:right">${esc(l.tanggal)}</td></tr>
            <tr><td style="color:#4A585E">Debit intake</td><td style="text-align:right">${fmt(l.debit, 1)} L/dt</td></tr>
            <tr><td style="color:#4A585E">TMA</td><td style="text-align:right">${fmt(l.tma, 1)} cm</td></tr>
            <tr><td style="color:#4A585E">Saluran</td><td style="text-align:right">${esc(l.kondisi)}</td></tr>
            <tr><td style="color:#4A585E">Tanam / kering / puso</td><td style="text-align:right">${fmt(l.tanam, 1)} / ${fmt(l.kering, 1)} / ${fmt(l.puso, 1)} Ha</td></tr>
        </table>${foto}
        <a href="${l.url}" style="display:inline-block;margin-top:6px;font-weight:600;color:#0F5E6E">Detail laporan</a>` : '<div style="margin-top:6px;color:#7C898E">Belum ada laporan 3 hari terakhir.</div>'}
        ${petugas ? `<div style="margin-top:8px;border-top:1px solid #DDE3DE;padding-top:6px"><div style="font-weight:600">Petugas</div>${petugas}</div>` : ''}
    </div>`;
}

/** Galeri foto ringkas untuk popup: foto pertama besar + thumbnail, klik membuka layar penuh. */
function galeriPopup(foto, lebar = 240) {
    if (!foto || !foto.length) return '';
    const data = esc(JSON.stringify(foto.map((f) => ({ besar: f.besar, label: f.label || '' }))));
    const [utama, ...lain] = foto;
    const label = utama.label ? `<span style="position:absolute;left:6px;bottom:6px;background:rgba(30,42,47,.75);color:#fff;font-size:10px;font-weight:600;padding:1px 6px;border-radius:999px">${esc(utama.label)}</span>` : '';
    return `<div style="margin-top:8px">
        <a href="${utama.besar}" data-galeri="${data}" data-indeks="0" style="position:relative;display:block;width:${lebar}px;height:${Math.round(lebar * 0.62)}px;border-radius:6px;overflow:hidden;background:#E7ECE8">
            <img src="${utama.kecil}" alt="Foto kejadian" loading="lazy" width="${lebar}" height="${Math.round(lebar * 0.62)}" style="width:100%;height:100%;object-fit:cover">${label}
        </a>
        ${lain.length ? `<div style="display:flex;gap:4px;margin-top:4px">${lain.slice(0, 4).map((f, n) => `<a href="${f.besar}" data-galeri="${data}" data-indeks="${n + 1}" style="position:relative;display:block;width:${Math.floor((lebar - 12) / 4)}px;height:40px;border-radius:4px;overflow:hidden"><img src="${f.kecil}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover">${n === 3 && lain.length > 4 ? `<span style="position:absolute;inset:0;background:rgba(30,42,47,.6);color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center">+${lain.length - 4}</span>` : ''}</a>`).join('')}</div>` : ''}
    </div>`;
}

function popupKejadian(k, internal) {
    return `<div style="min-width:220px;max-width:240px;font-size:12px">
        <div style="font-weight:700;font-size:13px">${esc(k.jenis)}</div>
        <div style="color:#4A585E;margin:2px 0 6px">${esc([k.desa, k.kecamatan, k.kabupaten].filter(Boolean).join(', '))}</div>
        ${chip(k.status, k.warna)}
        <div style="margin-top:6px">Luas terdampak: <b>${fmt(k.luas_terdampak_ha, 2)} Ha</b>${k.luas_tertangani_ha ? ` · tertangani <b>${fmt(k.luas_tertangani_ha, 2)} Ha</b>` : ''}</div>
        ${k.penyebab ? `<div>Penyebab: ${esc(k.penyebab)}</div>` : ''}
        ${k.foto && k.foto.length ? galeriPopup(k.foto) : '<div style="margin-top:6px;color:#7C898E">Belum ada foto dokumentasi.</div>'}
        ${internal && k.pelapor ? `<div style="margin-top:4px">Pelapor: ${esc(k.pelapor)} ${k.wa ? `<a href="${k.wa}" target="_blank" rel="noopener" style="background:#1f8f4e;color:#fff;border-radius:4px;padding:1px 6px;font-weight:600">WA</a>` : ''}</div>` : ''}
        ${internal ? `<a href="${k.url}" style="display:inline-block;margin-top:6px;font-weight:600;color:#0F5E6E">Detail kejadian</a>` : ''}
    </div>`;
}

const ikonKejadian = (warna) => L.divIcon({
    className: '',
    html: `<svg width="18" height="18" viewBox="0 0 18 18"><path d="M9 1.5 17 16H1z" fill="${warna}" stroke="#fff" stroke-width="1.5"/></svg>`,
    iconSize: [18, 18], iconAnchor: [9, 12],
});

const ikonBangunan = L.divIcon({
    className: '',
    html: '<svg width="14" height="14" viewBox="0 0 14 14"><rect x="1.5" y="1.5" width="11" height="11" fill="#0B4955" stroke="#fff" stroke-width="1.5"/></svg>',
    iconSize: [14, 14], iconAnchor: [7, 7],
});

/**
 * Halaman peta (Alpine component).
 * @param {{internal:boolean, api:string}} opsi
 */
export function createMap(opsi) {
    // Objek Leaflet disimpan di luar state Alpine agar tidak dibungkus Proxy reaktif.
    let map = null;
    const geo = {};
    let layers = {};
    let aturTitikFn = null;
    const markerKejadian = new Map(); // id → marker, untuk membuka popup dari daftar
    const URUT_KEJADIAN = { 'Perlu Penanganan': 0, 'Dalam Penanganan': 1, 'Selesai Ditangani': 2, 'Tidak Perlu Penanganan': 3 };
    const normal = (t) => String(t ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/^(d\.?\s*i\.?\s+)/, '');

    return {
        internal: !!opsi.internal,
        tanggal: new Date().toISOString().slice(0, 10),
        kabupaten: '',
        satgas: '',
        status: { aman: true, waspada: true, siaga: true, kritis: true, kosong: true },
        lapis: { fungsional: true, baku: false, potensial: false, sub: !!opsi.internal, kejadian: true, bangunan: false },
        data: null,
        memuat: true,
        galat: null,
        panel: window.innerWidth >= 1024,
        tab: 'di',
        cari: '',
        statusKejadian: '',

        get diTampil() {
            if (!this.data) return [];
            return this.data.daerah_irigasi.filter((d) => this.cocok(d));
        },
        /** Daftar D.I. di panel: ikut filter + kata pencarian. */
        get diDaftar() {
            const q = normal(this.cari).trim();
            return this.diTampil
                .filter((d) => !q || normal(`${d.nama} ${d.kabupaten ?? ''}`).includes(q))
                .sort((a, b) => ({ kritis: 0, siaga: 1, waspada: 2, aman: 3, kosong: 4 })[a.status] - ({ kritis: 0, siaga: 1, waspada: 2, aman: 3, kosong: 4 })[b.status] || a.nama.localeCompare(b.nama));
        },
        get kejadianTampil() {
            if (!this.data) return [];
            const kata = normal(this.cari).trim().split(/\s+/).filter(Boolean);
            return this.data.kejadian
                .filter((k) => !this.kabupaten || k.kabupaten === this.kabupaten)
                .filter((k) => !this.statusKejadian || k.status === this.statusKejadian)
                .filter((k) => {
                    if (!kata.length) return true;
                    const teks = normal([k.desa, k.kecamatan, k.kabupaten, k.jenis, k.penyebab, k.status, k.di].join(' '));
                    return kata.every((w) => teks.includes(w));
                })
                .sort((a, b) => (URUT_KEJADIAN[a.status] ?? 9) - (URUT_KEJADIAN[b.status] ?? 9) || b.luas_terdampak_ha - a.luas_terdampak_ha);
        },
        get luasKejadian() {
            return this.kejadianTampil.reduce((t, k) => t + (k.luas_terdampak_ha || 0), 0);
        },
        get rekap() {
            const r = { aman: 0, waspada: 0, siaga: 0, kritis: 0, kosong: 0, luas: 0, kering: 0, puso: 0 };
            this.diTampil.forEach((d) => { r[d.status]++; r.luas += d.luas_ha || 0; r.kering += d.luas_kering_ha || 0; r.puso += d.luas_puso_ha || 0; });
            return r;
        },
        fmt,

        cocok(d) {
            if (!d) return false;
            if (this.kabupaten && d.kabupaten !== this.kabupaten) return false;
            if (this.satgas && d.satgas !== this.satgas) return false;
            return !!this.status[d.status];
        },

        async init() {
            map = L.map(this.$refs.peta, { zoomControl: false, preferCanvas: true }).fitBounds(BATAS_NTT);
            L.control.zoom({ position: 'bottomright' }).addTo(map);
            L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);
            const base = baseLayers();
            base['Peta jalan'].addTo(map);
            L.control.layers(base, null, { position: 'bottomright' }).addTo(map);

            await Promise.all(['fungsional', 'baku', 'potensial'].map(async (n) => {
                try {
                    const r = await fetch(`${ASSET}/geojson/${n}.geojson?v=${GEOJSON_VERSI}`);
                    geo[n] = await r.json();
                } catch (e) { geo[n] = null; }
            }));
            await this.muat();
            ['tanggal'].forEach((k) => this.$watch(k, () => this.muat()));
            ['kabupaten', 'satgas', 'status', 'lapis', 'statusKejadian'].forEach((k) => this.$watch(k, () => this.gambar(), { deep: true }));
            let jeda = null;
            this.$watch('cari', () => { clearTimeout(jeda); jeda = setTimeout(() => this.gambarKejadian(), 250); });
        },

        async muat() {
            this.memuat = true;
            this.galat = null;
            try {
                const r = await fetch(`${opsi.api}?tanggal=${this.tanggal}`, { headers: { Accept: 'application/json' } });
                if (!r.ok) throw new Error(r.status);
                this.data = await r.json();
                this.gambar();
            } catch (e) {
                this.galat = 'Data peta gagal dimuat. Periksa koneksi lalu muat ulang.';
            }
            this.memuat = false;
        },

        gambar() {
            Object.values(layers).forEach((l) => map.removeLayer(l));
            layers = {};
            if (!this.data) return;
            const diByKode = Object.fromEntries(this.data.daerah_irigasi.map((d) => [d.kode, d]));

            const gaya = {
                fungsional: { weight: 1.5, fillOpacity: 0.45 },
                baku: { weight: 1.5, fillOpacity: 0, dashArray: '5 4' },
                potensial: { weight: 1, fillOpacity: 0.12, dashArray: '2 4' },
            };
            ['potensial', 'baku', 'fungsional'].forEach((n) => {
                if (!this.lapis[n] || !geo[n]) return;
                layers[n] = L.geoJSON(geo[n], {
                    filter: (f) => {
                        const d = diByKode[f.properties.kode];
                        return d ? this.cocok(d) : (!this.kabupaten && !this.satgas);
                    },
                    style: (f) => {
                        const d = diByKode[f.properties.kode];
                        const warna = n === 'fungsional' ? (d ? d.warna : '#9AA4A8') : (n === 'baku' ? '#0B4955' : '#16798C');
                        return { color: warna, fillColor: warna, ...gaya[n] };
                    },
                    onEachFeature: (f, layer) => {
                        const d = diByKode[f.properties.kode];
                        layer.bindPopup(d ? popupDi(d, this.internal)
                            : `<b>${esc(f.properties.nama)}</b><br><span style="font-size:12px">${fmt(f.properties.luas_ha)} Ha · belum terhubung ke data D.I.</span>`);
                        layer.bindTooltip(f.properties.nama, { sticky: true, direction: 'top' });
                    },
                }).addTo(map);
            });

            // Titik D.I. berukuran sesuai luas: terlihat saat peta di-zoom jauh (poligon terlalu kecil).
            const titikDi = L.layerGroup();
            this.data.daerah_irigasi.forEach((d) => {
                if (!d.lat || !this.cocok(d)) return;
                L.circleMarker([d.lat, d.lng], {
                    radius: Math.max(7, Math.min(18, Math.sqrt(d.luas_ha || 100) / 3.2)),
                    weight: 2, color: '#fff', fillColor: d.warna, fillOpacity: 0.9,
                }).bindPopup(popupDi(d, this.internal)).bindTooltip('D.I. ' + d.nama, { direction: 'top' }).addTo(titikDi);
            });
            layers.titikDi = titikDi;
            const aturTitik = () => {
                if (!layers.titikDi) return;
                const jauh = map.getZoom() < 11;
                if (jauh) map.addLayer(layers.titikDi); else map.removeLayer(layers.titikDi);
                // Sub-D.I. baru ditampilkan saat zoom dekat agar tidak menumpuk di titik D.I.
                if (layers.sub) { if (map.getZoom() >= 10) map.addLayer(layers.sub); else map.removeLayer(layers.sub); }
            };
            if (aturTitikFn) map.off('zoomend', aturTitikFn);
            aturTitikFn = aturTitik;
            map.on('zoomend', aturTitik);
            aturTitik();

            if (this.lapis.sub && this.data.sub) {
                // Sub-D.I. yang belum punya koordinat menumpuk di titik tengah D.I.: sebar melingkar agar bisa diklik.
                const grup = {};
                this.data.sub.forEach((s) => { const k = `${s.lat},${s.lng}`; (grup[k] = grup[k] || []).push(s); });
                const lyr = L.layerGroup();
                Object.values(grup).forEach((arr) => arr.forEach((s, i) => {
                    const d = this.data.daerah_irigasi.find((x) => x.id === s.di_id);
                    if (d && !this.cocok(d)) return;
                    if (!this.status[s.status]) return;
                    let { lat, lng } = s;
                    if (arr.length > 1) {
                        const sudut = (2 * Math.PI * i) / arr.length;
                        const r = 0.006 + 0.0008 * arr.length;
                        lat += r * Math.sin(sudut); lng += r * Math.cos(sudut);
                    }
                    L.circleMarker([lat, lng], {
                        radius: 6, weight: 2, color: '#fff', fillColor: s.warna, fillOpacity: 1,
                        dashArray: s.titik_pasti ? null : '2 2',
                    }).bindPopup(popupSub(s)).bindTooltip(s.nama, { direction: 'top' }).addTo(lyr);
                }));
                layers.sub = lyr;
            }

            this.gambarKejadian();

            if (this.lapis.bangunan && this.data.bangunan) {
                const lyr = L.layerGroup();
                this.data.bangunan.forEach((b) => L.marker([b.lat, b.lng], { icon: ikonBangunan }).bindTooltip(`${b.nama} (${b.jenis})`).addTo(lyr));
                layers.bangunan = lyr.addTo(map);
            }
            if (aturTitikFn) aturTitikFn();
        },

        /** Titik kejadian di peta mengikuti filter kabupaten, status, dan kata pencarian. */
        gambarKejadian() {
            if (layers.kejadian) map.removeLayer(layers.kejadian);
            layers.kejadian = null;
            markerKejadian.clear();
            if (!this.lapis.kejadian || !this.data) return;
            const lyr = L.layerGroup();
            this.kejadianTampil.forEach((k) => {
                const m = L.marker([k.lat, k.lng], { icon: ikonKejadian(k.warna) }).bindPopup(popupKejadian(k, this.internal), { maxWidth: 260 }).addTo(lyr);
                markerKejadian.set(k.id, m);
            });
            layers.kejadian = lyr.addTo(map);
        },

        fokusKejadian(k) {
            if (!this.lapis.kejadian) this.lapis.kejadian = true; // memicu gambar ulang
            if (window.innerWidth < 1024) this.panel = false;
            setTimeout(() => {
                map.flyTo([k.lat, k.lng], Math.max(map.getZoom(), 13), { duration: 0.8 });
                setTimeout(() => markerKejadian.get(k.id)?.openPopup(), 850);
            }, 50);
        },

        fokus(d) {
            if (d.lat && d.lng) map.flyTo([d.lat, d.lng], 12, { duration: 0.8 });
            if (window.innerWidth < 1024) this.panel = false;
            const layer = layers.fungsional;
            if (layer) {
                layer.eachLayer((l) => {
                    if (l.feature.properties.kode === d.kode) {
                        setTimeout(() => l.openPopup(), 850);
                    }
                });
            }
        },
    };
}
