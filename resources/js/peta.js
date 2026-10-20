import L from 'leaflet';
import { ASSET, BASE, GEOJSON_VERSI } from './base';

const BATAS_NTT = [[-11.2, 118.8], [-8.0, 125.3]];
const fmt = (v, d = 0) => (v === null || v === undefined || v === '') ? '–'
    : Number(v).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const WARNA_NTT = '#F2A65A'; // oranye muda: daratan wilayah NTT

function baseLayers() {
    return {
        // peta dasar terang agar wilayah NTT (oranye muda) dan warna status D.I. menonjol
        'Peta terang': L.layerGroup([
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 16, attribution: 'Tiles &copy; Esri',
            }),
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Reference/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 16, pane: 'shadowPane',
            }),
        ]),
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

/** Gabungkan semua foto satu tanggal agar penampil foto bisa digeser antar Sub-D.I. */
function siapkanGaleri(g) {
    const semua = [];
    g.laporan.forEach((l) => l.foto.forEach((f) => { f.n = semua.length; semua.push(f); }));
    return { ...g, semua, galeri: JSON.stringify(semua.map((f) => ({ besar: f.besar, label: f.label }))) };
}

const WARNA_PINTU = { kurang: '#D9622B', rendah: '#D9622B', berlebih: '#3D7DB3', tinggi: '#3D7DB3', normal: '#4E8A2E' };
const LABEL_PINTU = { kurang: 'kurang', rendah: 'rendah', berlebih: 'berlebih', tinggi: 'tinggi', normal: 'normal' };
const angkaId = (v) => (v === null || v === undefined ? '–' : Number(v).toLocaleString('id-ID', { maximumFractionDigits: 2 }));

/** Warna pintu: debit lebih menentukan; bila debit belum dinilai, pakai TMA. */
function warnaPintu(p) {
    return WARNA_PINTU[p.status_debit] || WARNA_PINTU[p.status_tma] || '#6B7A80';
}

function popupPintu(p) {
    const st = (s) => (s ? ` <b style="color:${WARNA_PINTU[s]}">${LABEL_PINTU[s]}</b>` : '');
    return `<div style="font-size:12px;line-height:1.45">
        <div style="font-weight:700;font-size:13px">Pintu ${esc(p.kode)}</div>
        <div style="color:#4A585E">${esc(p.tingkat_label)}${p.nama ? ' · ' + esc(p.nama) : ''}</div>
        ${p.tanggal ? `<div style="margin-top:4px">Debit ${angkaId(p.debit)} L/dt${st(p.status_debit)}<br>TMA ${angkaId(p.tma)} cm${st(p.status_tma)}<br><span style="color:#4A585E">dibaca ${esc(p.tanggal)}</span></div>` : '<div style="margin-top:4px;color:#8A3510">Belum ada pembacaan</div>'}
        ${p.petugas ? `<div style="color:#4A585E">Petugas: ${esc(p.petugas)}</div>` : ''}
        <a href="${p.url}" style="display:inline-block;margin-top:6px;font-weight:600;color:#0F5E6E">Riwayat pintu</a>
    </div>`;
}

function popupBangunan(b) {
    return `<div style="min-width:200px;max-width:240px;font-size:12px">
        <div style="font-weight:700;font-size:13px">${esc(b.nama)}</div>
        <div style="color:#4A585E;margin:2px 0 6px">${esc(b.jenis)}${b.sub ? ' · ' + esc(b.sub) : ''}</div>
        ${chip(b.kondisi_label, b.warna)}${b.kondisi_per ? `<span style="color:#7C898E;margin-left:4px">per ${esc(b.kondisi_per)}</span>` : ''}
        ${b.keterangan ? `<div style="margin-top:6px">${esc(b.keterangan)}</div>` : ''}
        ${b.foto.length ? galeriPopup(b.foto) : ''}
    </div>`;
}

const ikonInfra = (warna) => L.divIcon({
    className: '',
    html: `<svg width="18" height="18" viewBox="0 0 18 18"><rect x="2" y="2" width="14" height="14" rx="2" fill="${warna}" stroke="#fff" stroke-width="2"/><path d="M5 12h8M6 12V8l3-2 3 2v4" stroke="#fff" stroke-width="1.4" fill="none"/></svg>`,
    iconSize: [18, 18], iconAnchor: [9, 9],
});

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
/** Tanggal lokal (bukan UTC) dalam format YYYY-MM-DD. */
const hariIniLokal = () => { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); };

export function createMap(opsi) {
    const awal = new URLSearchParams(window.location.search);
    // Objek Leaflet disimpan di luar state Alpine agar tidak dibungkus Proxy reaktif.
    let map = null;
    let wilayah = null; // lapisan batas kabupaten NTT
    const geo = {};
    let layers = {};
    let aturTitikFn = null;
    const markerKejadian = new Map(); // id → marker, untuk membuka popup dari daftar
    const markerBangunan = new Map();
    const markerPintu = new Map();
    const URUT_KEJADIAN = { 'Perlu Penanganan': 0, 'Dalam Penanganan': 1, 'Selesai Ditangani': 2, 'Tidak Perlu Penanganan': 3 };
    const normal = (t) => String(t ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/^(d\.?\s*i\.?\s+)/, '');

    return {
        internal: !!opsi.internal,
        periode: awal.get('periode') || 'hari_ini',
        dari: awal.get('dari') || '',
        sampai: awal.get('sampai') || hariIniLokal(),
        warna: awal.get('warna') === 'tersering' ? 'tersering' : 'terberat',
        get modeRekap() { return this.periode !== 'hari_ini'; },
        /** parameter periode untuk API & URL halaman */
        get qs() {
            const q = new URLSearchParams({ periode: this.periode });
            if (this.periode === 'rentang') { q.set('dari', this.dari); q.set('sampai', this.sampai); }
            if (this.modeRekap && this.warna === 'tersering') q.set('warna', 'tersering');
            return q.toString();
        },
        kabupaten: '',
        satgas: '',
        status: { aman: true, waspada: true, siaga: true, kritis: true, kosong: true },
        lapis: { wilayah: true, fungsional: true, baku: false, potensial: false, sub: !!opsi.internal, kejadian: true, bangunan: false },
        data: null,
        memuat: true,
        galat: null,
        // layar lebar: ingat pilihan buka/tutup terakhir; HP: tertutup agar peta terlihat
        panel: window.innerWidth >= 1024 && (() => { try { return localStorage.getItem('iris-panel-peta') !== 'tutup'; } catch (e) { return true; } })(),
        tab: 'di',
        cari: '',
        statusKejadian: '',
        // panel detail D.I.
        detailId: null,
        detail: null,
        detailMemuat: false,
        detailGalat: null,
        detailTab: 'foto',
        memuatLama: false,

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
            map = L.map(this.$refs.peta, { zoomControl: false, preferCanvas: true, zoomSnap: 0.25 }).fitBounds(BATAS_NTT, { padding: [8, 8] });
            // ukuran kontainer baru final setelah panel samping tampil → hitung ulang agar seluruh NTT terlihat
            requestAnimationFrame(() => { map.invalidateSize(); map.fitBounds(BATAS_NTT, { padding: [8, 8] }); });
            L.control.zoom({ position: 'bottomright' }).addTo(map);
            L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);
            const base = baseLayers();
            base['Peta terang'].addTo(map);
            L.control.layers(base, null, { position: 'bottomright' }).addTo(map);
            // batas kabupaten NTT di bawah poligon D.I.
            map.createPane('wilayah').style.zIndex = 350;
            this.muatWilayah();

            await Promise.all(['fungsional', 'baku', 'potensial'].map(async (n) => {
                try {
                    const r = await fetch(`${ASSET}/geojson/${n}.geojson?v=${GEOJSON_VERSI}`);
                    geo[n] = await r.json();
                } catch (e) { geo[n] = null; }
            }));
            await this.muat();
            const muatUlang = async () => {
                if (this.periode === 'rentang' && (!this.dari || !this.sampai)) return;
                // simpan periode di URL agar tautan bisa dibagikan / dimuat ulang
                try { history.replaceState(null, '', `${location.pathname}${this.periode === 'hari_ini' ? '' : '?' + this.qs}`); } catch (e) {}
                await this.muat();
                if (this.detailId) this.bukaDetail(this.detailId, { terbang: false });
            };
            ['periode', 'dari', 'sampai', 'warna'].forEach((k) => this.$watch(k, muatUlang));
            // panel dibuka/ditutup → ukuran peta berubah (layar lebar), hitung ulang
            this.$watch('panel', (buka) => {
                if (window.innerWidth >= 1024) { try { localStorage.setItem('iris-panel-peta', buka ? 'buka' : 'tutup'); } catch (e) {} }
                this.$nextTick(() => map.invalidateSize({ pan: false }));
            });
            if (this.periode === 'rentang' && !this.dari) this.dari = this.sampai;
            ['kabupaten', 'satgas', 'status', 'lapis', 'statusKejadian'].forEach((k) => this.$watch(k, () => this.gambar(), { deep: true }));
            this.$watch('kabupaten', () => this.gayaWilayah());
            let jeda = null;
            this.$watch('cari', () => { clearTimeout(jeda); jeda = setTimeout(() => this.gambarKejadian(), 250); });
        },

        async muat() {
            this.memuat = true;
            this.galat = null;
            try {
                // cache: 'no-store' + penanda waktu agar foto/laporan baru langsung tampil (tidak terhalang cache browser/hosting)
                const r = await fetch(`${opsi.api}?${this.qs}&_=${Date.now()}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!r.ok) throw new Error(r.status);
                this.data = await r.json();
                this.gambar();
            } catch (e) {
                this.galat = 'Data peta gagal dimuat. Periksa koneksi lalu muat ulang.';
            }
            this.memuat = false;
        },

        /** Daratan NTT per kabupaten (SHP batas administrasi), diisi oranye muda; kabupaten terpilih lebih tegas. */
        async muatWilayah() {
            try {
                const r = await fetch(`${ASSET}/geojson/wilayah-ntt.geojson?v=${GEOJSON_VERSI}`);
                const data = await r.json();
                wilayah = L.geoJSON(data, { pane: 'wilayah', renderer: L.canvas({ pane: 'wilayah' }), interactive: false, style: () => ({}) });
                this.gayaWilayah();
                if (this.lapis.wilayah) wilayah.addTo(map);
            } catch (e) { wilayah = null; }
        },
        gayaWilayah() {
            if (!wilayah) return;
            const pilih = this.kabupaten;
            wilayah.setStyle((f) => {
                const nama = f.properties.nama.replace(/^Kabupaten\s+/, '');
                const aktif = !pilih || nama === pilih;
                return { color: '#ffffff', weight: aktif && pilih ? 1.6 : 0.8, fillColor: WARNA_NTT, fillOpacity: aktif ? 0.7 : 0.22 };
            });
        },

        gambar() {
            if (wilayah) { if (this.lapis.wilayah) wilayah.addTo(map); else map.removeLayer(wilayah); }
            Object.values(layers).forEach((l) => map.removeLayer(l));
            layers = {};
            if (!this.data) return;
            const diByKode = Object.fromEntries(this.data.daerah_irigasi.map((d) => [d.kode, d]));

            const gaya = {
                fungsional: { weight: 1.5, fillOpacity: 0.75 },
                baku: { weight: 1.5, fillOpacity: 0, dashArray: '5 4' },
                potensial: { weight: 1, fillOpacity: 0.12, dashArray: '2 4' },
            };
            // D.I. tanpa poligon areal fungsional di Peta IGT (mis. Wae Musur, Satar Beleng, Baing):
            // warna statusnya ditampilkan pada poligon luas baku agar tetap terlihat saat zoom dekat.
            const punyaFungsional = new Set((geo.fungsional?.features || []).map((f) => f.properties.kode));
            const bakuSebagaiStatus = (f) => this.lapis.fungsional && !punyaFungsional.has(f.properties.kode) && !!diByKode[f.properties.kode];
            ['potensial', 'baku', 'fungsional'].forEach((n) => {
                if (!geo[n]) return;
                if (!this.lapis[n] && !(n === 'baku' && this.lapis.fungsional)) return;
                layers[n] = L.geoJSON(geo[n], {
                    filter: (f) => {
                        if (n === 'baku' && !this.lapis.baku && !bakuSebagaiStatus(f)) return false;
                        const d = diByKode[f.properties.kode];
                        return d ? this.cocok(d) : (!this.kabupaten && !this.satgas);
                    },
                    style: (f) => {
                        const d = diByKode[f.properties.kode];
                        if (n === 'baku' && bakuSebagaiStatus(f)) {
                            return { color: d.warna, fillColor: d.warna, weight: 1.5, fillOpacity: 0.45, dashArray: '5 4' };
                        }
                        const warna = n === 'fungsional' ? (d ? d.warna : '#9AA4A8') : (n === 'baku' ? '#0B4955' : '#16798C');
                        return { color: warna, fillColor: warna, ...gaya[n] };
                    },
                    onEachFeature: (f, layer) => {
                        const d = diByKode[f.properties.kode];
                        if (d) layer.on('click', () => this.bukaDetail(d.id, { terbang: false }));
                        else layer.bindPopup(`<b>${esc(f.properties.nama)}</b><br><span style="font-size:12px">${fmt(f.properties.luas_ha)} Ha · belum terhubung ke data D.I.</span>`);
                        layer.bindTooltip(d ? `D.I. ${f.properties.nama} · klik untuk detail` : f.properties.nama, { sticky: true, direction: 'top' });
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
                }).on('click', () => this.bukaDetail(d.id)).bindTooltip('D.I. ' + d.nama, { direction: 'top' }).addTo(titikDi);
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
            this.gambarSorot();
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
            this.bukaDetail(d.id);
        },

        /** Ringkasan D.I. terpilih dari data peta (status, luas, MT). */
        get diPilih() {
            return this.data?.daerah_irigasi.find((d) => d.id === this.detailId) || null;
        },

        async bukaDetail(id, { terbang = true } = {}) {
            const ganti = this.detailId !== id;
            this.detailId = id;
            this.panel = true;
            if (ganti) { this.detail = null; this.detailTab = 'foto'; this.$refs.panel?.scrollTo({ top: 0 }); }
            this.detailMemuat = true;
            this.detailGalat = null;
            map.closePopup();
            this.gambarSorot(terbang);
            try {
                const r = await fetch(`${opsi.apiDetail.replace('__ID__', id)}?${this.qs}&_=${Date.now()}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!r.ok) throw new Error(r.status);
                const data = await r.json();
                if (this.detailId !== id) return; // pengguna sudah membuka D.I. lain
                const kode = this.data?.daerah_irigasi.find((x) => x.id === id)?.kode;
                this.detail = { ...data, foto_harian: data.foto_harian.map(siapkanGaleri),
                    ada_fungsional: (geo.fungsional?.features || []).some((f) => f.properties.kode === kode) };
                if (ganti && !this.detail.foto_harian.length) this.detailTab = 'sub';
                this.gambarBangunanDi();
                this.gambarPintuDi();
            } catch (e) {
                this.detailGalat = 'Detail D.I. gagal dimuat.';
            }
            this.detailMemuat = false;
        },

        tutupDetail() {
            this.detailId = null;
            this.detail = null;
            ['sorot', 'bangunanDi', 'pintuDi'].forEach((k) => { if (layers[k]) { map.removeLayer(layers[k]); layers[k] = null; } });
        },

        async muatFotoLama() {
            if (!this.detail?.foto_sebelum || this.memuatLama) return;
            this.memuatLama = true;
            try {
                const r = await fetch(`${opsi.apiDetail.replace('__ID__', this.detailId)}?sebelum=${this.detail.foto_sebelum}&_=${Date.now()}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                const data = await r.json();
                this.detail.foto_harian.push(...data.foto_harian.map(siapkanGaleri));
                this.detail.foto_sebelum = data.foto_sebelum;
            } catch (e) { /* biarkan tombol bisa dicoba lagi */ }
            this.memuatLama = false;
        },

        /** Klik kotak riwayat: buka tab foto & gulir ke tanggal itu. */
        async keTanggal(h) {
            if (!h.foto) return;
            this.detailTab = 'foto';
            await this.$nextTick();
            let el = document.getElementById(`foto-${h.tanggal}`);
            if (!el) {
                // tanggal di luar 30 hari terakhir: muat jendela foto yang berakhir di tanggal itu
                try {
                    const r = await fetch(`${opsi.apiDetail.replace('__ID__', this.detailId)}?sebelum=${h.tanggal}&_=${Date.now()}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                    const data = await r.json();
                    this.detail.foto_harian = data.foto_harian.map(siapkanGaleri);
                    this.detail.foto_sebelum = data.foto_sebelum;
                    await this.$nextTick();
                    el = document.getElementById(`foto-${h.tanggal}`);
                } catch (e) { /* abaikan */ }
            }
            el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        /** Garis tebal di batas D.I. terpilih, lalu peta diarahkan ke sana. */
        gambarSorot(terbang = false) {
            if (layers.sorot) { map.removeLayer(layers.sorot); layers.sorot = null; }
            const d = this.diPilih;
            if (!d) return;
            // luas baku = garis putus-putus (di bawah), areal fungsional = garis penuh (di atas)
            const GAYA_SOROT = {
                baku: { color: '#0F2A33', weight: 2, opacity: 0.85, fill: false, dashArray: '7 6' },
                fungsional: { color: '#0F2A33', weight: 3.5, opacity: 1, fill: false, dashArray: null },
            };
            const grup = L.featureGroup();
            ['baku', 'fungsional'].forEach((n) => {
                const fitur = (geo[n]?.features || []).filter((f) => f.properties.kode === d.kode);
                if (fitur.length) L.geoJSON({ type: 'FeatureCollection', features: fitur }, { interactive: false, style: GAYA_SOROT[n] }).addTo(grup);
            });
            if (grup.getLayers().length) layers.sorot = grup.addTo(map);
            if (!terbang) return;
            if (layers.sorot) map.flyToBounds(layers.sorot.getBounds(), { padding: [30, 30], maxZoom: 14, duration: 0.8 });
            else if (d.lat) map.flyTo([d.lat, d.lng], 12, { duration: 0.8 });
        },

        /** Titik infrastruktur D.I. terpilih (yang sudah punya koordinat). */
        gambarBangunanDi() {
            if (layers.bangunanDi) { map.removeLayer(layers.bangunanDi); layers.bangunanDi = null; }
            markerBangunan.clear();
            if (!this.detail) return;
            const lyr = L.layerGroup();
            this.detail.bangunan.filter((b) => b.lat && b.lng).forEach((b) => {
                const m = L.marker([b.lat, b.lng], { icon: ikonInfra(b.warna), zIndexOffset: 500 })
                    .bindPopup(popupBangunan(b), { maxWidth: 260 }).bindTooltip(b.nama, { direction: 'top' }).addTo(lyr);
                markerBangunan.set(b.id, m);
            });
            layers.bangunanDi = lyr.addTo(map);
        },

        /** Pintu air D.I. terpilih (internal): titik berwarna status + garis aliran dari pintu hulunya. */
        gambarPintuDi() {
            if (layers.pintuDi) { map.removeLayer(layers.pintuDi); layers.pintuDi = null; }
            markerPintu.clear();
            const daftar = this.detail?.pintu || [];
            if (!daftar.length) return;
            const lyr = L.layerGroup();
            const jari = { utama: 9, primer: 8, sekunder: 7, tersier: 6 };
            daftar.forEach((p) => {
                if (p.hulu) L.polyline([p.hulu, [p.lat, p.lng]], { color: '#0F5E6E', weight: p.tingkat === 'tersier' ? 1.5 : 2.5, opacity: 0.8, dashArray: p.tingkat === 'tersier' ? '3 4' : null, interactive: false }).addTo(lyr);
            });
            daftar.forEach((p) => {
                const m = L.circleMarker([p.lat, p.lng], { radius: jari[p.tingkat] || 6, color: '#fff', weight: 2, fillColor: warnaPintu(p), fillOpacity: 1, pane: 'markerPane' })
                    .bindPopup(popupPintu(p), { maxWidth: 260 }).bindTooltip('Pintu ' + p.kode, { direction: 'top' }).addTo(lyr);
                markerPintu.set(p.id, m);
            });
            layers.pintuDi = lyr.addTo(map);
        },

        fokusPintu(p) {
            if (!p.lat) return;
            if (window.innerWidth < 1024) this.panel = false;
            map.flyTo([p.lat, p.lng], Math.max(map.getZoom(), 16), { duration: 0.8 });
            setTimeout(() => markerPintu.get(p.id)?.openPopup(), 850);
        },

        warnaPintu,

        fokusBangunan(b) {
            if (!b.lat) return;
            if (window.innerWidth < 1024) this.panel = false;
            map.flyTo([b.lat, b.lng], Math.max(map.getZoom(), 16), { duration: 0.8 });
            setTimeout(() => markerBangunan.get(b.id)?.openPopup(), 850);
        },

        get jumlahFoto() {
            return (this.detail?.foto_harian || []).reduce((t, g) => t + g.semua.length, 0);
        },
        get rekapInfra() {
            const r = { total: 0, baik: 0, rusak_ringan: 0, rusak_sedang: 0, rusak_berat: 0, belum: 0, titik: 0 };
            (this.detail?.bangunan || []).forEach((b) => { r.total++; r[b.kondisi || 'belum']++; if (b.lat) r.titik++; });
            return r;
        },
        galeri(list) {
            return JSON.stringify(list.map((f) => ({ besar: f.besar, label: f.label || '' })));
        },
    };
}
