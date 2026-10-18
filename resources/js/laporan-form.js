/**
 * Form laporan harian petugas: draf tersimpan di HP, GPS otomatis,
 * foto dikompres di browser sebelum dikirim (hemat kuota & sinyal).
 */
import L from 'leaflet';
import { kompres } from './foto';

const MAKS_FOTO = 5;

const ANGKA = /^-?\d+(\.\d+)?$/;
const dalamNtt = (lat, lng) => lat >= -12 && lat <= -7 && lng >= 118 && lng <= 126;

export function laporanForm(opsi) {
    // objek Leaflet di luar state Alpine (tidak perlu reaktif)
    let peta = null;
    let penanda = null;

    return {
        titikSub: opsi.titikSub || {},
        lokasi: opsi.awal.lat ? 'tersimpan' : null, // gps | peta | ketik | tersimpan
        petaBuka: false,
        baru: !!opsi.baru,
        sisaFoto: opsi.sisaFoto ?? MAKS_FOTO,
        luasSub: opsi.luasSub || {},
        f: opsi.awal,
        foto: [],          // {file, url, ukuran}
        memproses: false,
        online: navigator.onLine,
        gps: { status: 'idle', akurasi: null },
        adaDraf: false,
        kirim: false,
        pesanOffline: false,

        get kunciDraf() { return `iris-draf-laporan-${this.f.sub_daerah_irigasi_id || 'x'}`; },
        pakai: { padi: false, palawija: false, horti: false },
        /** total = padi + palawija + hortikultura yang dicentang */
        get luasTanam() {
            return Math.round(['padi', 'palawija', 'horti'].reduce((t, k) => t + (this.pakai[k] ? (+this.f[`luas_${k}_ha`] || 0) : 0), 0) * 100) / 100;
        },
        daftarJenis(k) { return String(this.f[`jenis_${k}`] || '').split(',').map((x) => x.trim()).filter(Boolean); },
        adaJenis(k, j) { return this.daftarJenis(k).some((x) => x.toLowerCase() === j.toLowerCase()); },
        pilihJenis(k, j) {
            const d = this.daftarJenis(k);
            this.f[`jenis_${k}`] = (this.adaJenis(k, j) ? d.filter((x) => x.toLowerCase() !== j.toLowerCase()) : [...d, j]).join(', ');
        },
        get statusPratinjau() {
            const t = this.luasTanam;
            const rk = t > 0 ? (+this.f.luas_kering_ha || 0) / t : 0;
            if ((+this.f.luas_puso_ha || 0) > 0 || rk >= 0.5) return 'kritis';
            let s = { penuh: 'aman', normal: 'aman', kurang: 'waspada', kering: 'siaga' }[this.f.kondisi_saluran] || 'kosong';
            const urut = { kosong: 0, aman: 1, waspada: 2, siaga: 3, kritis: 4 };
            const naik = (a, b) => (urut[a] >= urut[b] ? a : b);
            if (rk >= 0.25) s = naik(s, 'siaga'); else if (rk >= 0.1) s = naik(s, 'waspada');
            return s;
        },
        get latValid() { const v = String(this.f.lat ?? '').trim(); return !v || (ANGKA.test(v) && +v >= -12 && +v <= -7); },
        get lngValid() { const v = String(this.f.lng ?? '').trim(); return !v || (ANGKA.test(v) && +v >= 118 && +v <= 126); },
        get koordinatValid() { return this.latValid && this.lngValid && !!this.f.lat === !!this.f.lng; },
        get keteranganLokasi() {
            if (!this.latValid) return 'Lintang harus angka antara -12 dan -7 (pakai titik, bukan koma).';
            if (!this.lngValid) return 'Bujur harus angka antara 118 dan 126.';
            if (!!this.f.lat !== !!this.f.lng) return 'Isi lintang dan bujur.';
            if (this.gps.status === 'gagal' && !this.f.lat) return 'GPS tidak didapat. Ketik koordinat atau pilih di peta.';
            if (this.gps.status === 'mencari' && (!this.f.lat || this.lokasi === 'gps')) return 'Mencari lokasi GPS…';
            if (!this.f.lat) return 'Belum ada titik (boleh dikosongkan).';
            return { gps: `Dari GPS HP${this.gps.akurasi ? ` (±${this.gps.akurasi} m)` : ''}`, peta: 'Dipilih di peta', ketik: 'Diketik manual', tersimpan: 'Titik tersimpan' }[this.lokasi] || '';
        },
        get peringatanLuas() {
            const luas = this.luasSub[this.f.sub_daerah_irigasi_id];
            if (luas && this.luasTanam > luas * 1.05) return `Luas tanam melebihi luas Sub-D.I. (${luas.toLocaleString('id-ID')} Ha). Periksa kembali.`;
            return null;
        },

        init() {
            // komoditas aktif = yang luasnya terisi; laporan baru tanpa angka → padi
            ['padi', 'palawija', 'horti'].forEach((k) => { this.pakai[k] = (+this.f[`luas_${k}_ha`] || 0) > 0; });
            if (!this.pakai.palawija && !this.pakai.horti && !this.pakai.padi && (this.baru || +this.f.luas_tanam_ha > 0)) this.pakai.padi = true;
            if (this.pakai.padi && !(+this.f.luas_padi_ha) && +this.f.luas_tanam_ha > 0) this.f.luas_padi_ha = this.f.luas_tanam_ha;
            window.addEventListener('online', () => (this.online = true));
            window.addEventListener('offline', () => (this.online = false));
            if (opsi.hapusDraf) this.hapusDraf();
            if (this.baru) {
                try { this.adaDraf = !!localStorage.getItem(this.kunciDraf); } catch (e) {}
                this.$watch('f', () => this.simpanDraf(), { deep: true });
                this.$watch('f.sub_daerah_irigasi_id', () => { try { this.adaDraf = !!localStorage.getItem(this.kunciDraf); } catch (e) {} });
                if (!this.f.lat) this.ambilGps(true);
            }
            this.$watch('f.lat', () => this.pasangPenanda());
            this.$watch('f.lng', () => this.pasangPenanda());
        },

        simpanDraf() {
            if (this.kirim) return;
            try { localStorage.setItem(this.kunciDraf, JSON.stringify({ ...this.f, _waktu: Date.now() })); } catch (e) {}
        },
        pulihkanDraf() {
            try {
                const d = JSON.parse(localStorage.getItem(this.kunciDraf));
                if (d) {
                    delete d._waktu;
                    Object.assign(this.f, d);
                    ['padi', 'palawija', 'horti'].forEach((k) => { this.pakai[k] = (+this.f[`luas_${k}_ha`] || 0) > 0; });
                }
            } catch (e) {}
            this.adaDraf = false;
        },
        hapusDraf() {
            try {
                Object.keys(localStorage).filter((k) => k.startsWith('iris-draf-laporan-')).forEach((k) => localStorage.removeItem(k));
            } catch (e) {}
            this.adaDraf = false;
        },

        /** @param {boolean} otomatis  dipanggil saat form dibuka: jangan timpa titik yang sudah diisi manual */
        ambilGps(otomatis = false) {
            if (!navigator.geolocation) { this.gps.status = 'gagal'; return; }
            this.gps.status = 'mencari';
            navigator.geolocation.getCurrentPosition(
                (p) => {
                    this.gps = { status: 'ok', akurasi: Math.round(p.coords.accuracy) };
                    if (otomatis && this.f.lat && this.lokasi !== 'gps') return;
                    this.f.lat = p.coords.latitude.toFixed(7);
                    this.f.lng = p.coords.longitude.toFixed(7);
                    this.lokasi = 'gps';
                    if (peta) peta.setView([+this.f.lat, +this.f.lng], Math.max(peta.getZoom(), 16));
                },
                () => { this.gps.status = 'gagal'; },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
            );
        },

        /** Tempel "-10.123, 123.456" (format Google Maps) ke salah satu kolom → isi keduanya. */
        tempelKoordinat(e) {
            const t = (e.clipboardData || window.clipboardData)?.getData('text') || '';
            const m = t.match(/(-?\d{1,3}\.\d+)\s*[,;\s]\s*(-?\d{1,3}\.\d+)/);
            if (!m) return;
            e.preventDefault();
            let [lat, lng] = [m[1], m[2]];
            if (Math.abs(+lat) > 90) [lat, lng] = [lng, lat]; // tertukar
            this.f.lat = (+lat).toFixed(7);
            this.f.lng = (+lng).toFixed(7);
            this.lokasi = 'ketik';
            if (peta) peta.setView([+this.f.lat, +this.f.lng], Math.max(peta.getZoom(), 15));
        },

        togglePeta() {
            this.petaBuka = !this.petaBuka;
            if (!this.petaBuka) return;
            this.$nextTick(() => {
                if (!peta) {
                    const ada = this.f.lat && this.f.lng && dalamNtt(+this.f.lat, +this.f.lng);
                    const sub = this.titikSub[this.f.sub_daerah_irigasi_id];
                    const pusat = ada ? [+this.f.lat, +this.f.lng] : (sub ? [sub[0], sub[1]] : [-9.6, 122.0]);
                    peta = L.map(this.$refs.petaLokasi, { scrollWheelZoom: false }).setView(pusat, ada ? 16 : (sub ? 14 : 7));
                    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Esri' }).addTo(peta);
                    peta.on('click', (e) => {
                        this.f.lat = e.latlng.lat.toFixed(7);
                        this.f.lng = e.latlng.lng.toFixed(7);
                        this.lokasi = 'peta';
                    });
                    this.pasangPenanda();
                } else {
                    peta.invalidateSize();
                    if (penanda) peta.panTo(penanda.getLatLng());
                }
            });
        },

        pasangPenanda() {
            if (!peta) return;
            const lat = +this.f.lat;
            const lng = +this.f.lng;
            if (!this.f.lat || !this.f.lng || !dalamNtt(lat, lng)) {
                if (penanda) { peta.removeLayer(penanda); penanda = null; }
                return;
            }
            if (penanda) { penanda.setLatLng([lat, lng]); return; }
            penanda = L.marker([lat, lng], {
                draggable: true,
                icon: L.divIcon({ className: '', html: '<svg width="26" height="34" viewBox="0 0 26 34"><path d="M13 33s11-12.5 11-20A11 11 0 0 0 2 13c0 7.5 11 20 11 20Z" fill="#D9622B" stroke="#fff" stroke-width="2"/><circle cx="13" cy="13" r="4" fill="#fff"/></svg>', iconSize: [26, 34], iconAnchor: [13, 33] }),
            }).addTo(peta);
            penanda.on('dragend', () => {
                const p = penanda.getLatLng();
                this.f.lat = p.lat.toFixed(7);
                this.f.lng = p.lng.toFixed(7);
                this.lokasi = 'peta';
            });
        },

        async pilihFoto(e) {
            const files = [...e.target.files];
            e.target.value = '';
            this.memproses = true;
            for (const file of files) {
                if (this.foto.length >= this.sisaFoto) break;
                const hasil = await kompres(file);
                this.foto.push({ file: hasil, url: URL.createObjectURL(hasil), ukuran: Math.round(hasil.size / 1024) });
            }
            this.sinkronFoto();
            this.memproses = false;
        },
        hapusFotoBaru(i) {
            URL.revokeObjectURL(this.foto[i].url);
            this.foto.splice(i, 1);
            this.sinkronFoto();
        },
        sinkronFoto() {
            const dt = new DataTransfer();
            this.foto.forEach((x) => dt.items.add(x.file));
            this.$refs.fotoInput.files = dt.files;
        },

        /** "-10,1234" → "-10.1234" (keyboard HP berbahasa Indonesia sering memakai koma). */
        rapikan(k) {
            const v = String(this.f[k] ?? '').trim().replace(/\s+/g, '');
            if (/^-?\d+,\d+$/.test(v)) this.f[k] = v.replace(',', '.');
            else this.f[k] = v;
        },

        kirimForm(e) {
            this.rapikan('lat');
            this.rapikan('lng');
            if (!this.koordinatValid) {
                e.preventDefault();
                this.$root.querySelector('input[name=lat]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (!navigator.onLine) {
                e.preventDefault();
                this.simpanDraf();
                this.pesanOffline = true;
                return;
            }
            this.kirim = true;
        },
    };
}
