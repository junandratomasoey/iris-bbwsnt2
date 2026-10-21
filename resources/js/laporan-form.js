/**
 * Form laporan PPA (format pengisian PPA): pembacaan pintu & kondisi tanaman per petak tersier.
 * Draf tersimpan di HP, GPS otomatis,
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
        infoSub: opsi.infoSub || {},
        lokasi: opsi.awal.lat ? 'tersimpan' : null, // gps | peta | ketik | tersimpan
        petaBuka: false,
        baru: !!opsi.baru,
        sisaFoto: opsi.sisaFoto ?? MAKS_FOTO,
        petakSub: opsi.petakSub || {},
        pintuSub: opsi.pintuSub || {},
        sebelumnya: opsi.sebelumnya || {},
        statusKecukupan: opsi.statusKecukupan || {},
        tampilPintuLain: false,
        tampilPetakLain: false,
        wajibBaca: opsi.wajibBaca !== false,

        get info() { return this.infoSub[this.f.sub_daerah_irigasi_id] || null; },
        get sebelumnyaSub() { return this.baru ? (this.sebelumnya[this.f.sub_daerah_irigasi_id] || null) : null; },
        get senin() { return !!this.f.tanggal && new Date(this.f.tanggal + 'T00:00:00').getDay() === 1; },

        // ---------- B. pintu ----------
        get semuaPintu() { return this.pintuSub[this.f.sub_daerah_irigasi_id] || []; },
        baca(id) {
            if (!this.f.baca[id]) this.f.baca[id] = { tma: '', bukaan: '', debit: '', kondisi_pintu: '', kondisi_saluran: '', catatan: '' };
            return this.f.baca[id];
        },
        terisiPintu(id) { const v = this.f.baca[id]; return !!v && Object.values(v).some((x) => x !== '' && x !== null); },
        /** Pintu tugas saya + yang sudah diisi; bila tidak ada tugas: semua pintu yang boleh diisi. */
        get daftarPintu() {
            const ada = this.semuaPintu.some((p) => p.saya);
            return this.semuaPintu.filter((p) => !ada || p.saya || this.tampilPintuLain || this.terisiPintu(p.id));
        },
        get pintuLain() { return this.semuaPintu.some((p) => p.saya) ? this.semuaPintu.filter((p) => !p.saya && !this.terisiPintu(p.id)).length : 0; },
        statusPintu(p, k) {
            const v = this.baca(p.id)[k];
            if (v === '' || v === null || isNaN(+v)) return null;
            const [min, maks] = k === 'debit' ? [p.dmin, p.dmaks] : [p.tmin, p.tmaks];
            if (min === null && maks === null) return null;
            if (min !== null && +v < min) return 'bawah';
            if (maks !== null && +v > maks) return 'atas';
            return 'normal';
        },
        teksPintu(s) { return { bawah: '▼ di bawah normal', atas: '▲ di atas normal', normal: '✓ normal' }[s] || ''; },
        warnaPintu(s) { return { bawah: 'text-[#8A3510]', atas: 'text-[#1B4F7A]', normal: 'text-[#2F5A19]' }[s] || ''; },

        // ---------- C. petak ----------
        get semuaPetak() { return this.petakSub[this.f.sub_daerah_irigasi_id]?.daftar || []; },
        petak(id) {
            if (!this.f.petak[id]) this.f.petak[id] = { luas_tanam_ha: '', jenis_tanaman: '', fase: '', umur_hst: '', kecukupan_air: '', luas_kering_ha: '', keterangan: '' };
            return this.f.petak[id];
        },
        terisiPetak(id) { return !!this.f.petak[id]?.jenis_tanaman; },
        get daftarPetak() {
            const ada = this.semuaPetak.some((p) => p.saya);
            return this.semuaPetak.filter((p) => !ada || p.saya || this.tampilPetakLain || this.terisiPetak(p.id));
        },
        get petakLain() { return this.semuaPetak.some((p) => p.saya) ? this.semuaPetak.filter((p) => !p.saya && !this.terisiPetak(p.id)).length : 0; },
        statusPetak(id) {
            const r = this.f.petak[id];
            if (!r || !r.jenis_tanaman) return null;
            if (r.kecukupan_air) return this.statusKecukupan[r.kecukupan_air] || null;
            return r.jenis_tanaman === 'B' ? 'aman' : null;
        },
        peringatanLuas(p) {
            const r = this.f.petak[p.id];
            if (!r) return null;
            if (p.luas && +r.luas_tanam_ha > p.luas * 1.05) return `Luas tanam melebihi luas baku petak (${p.luas.toLocaleString('id-ID')} Ha). Periksa kembali.`;
            if (+r.luas_kering_ha > +r.luas_tanam_ha) return 'Luas terdampak kering melebihi luas tanam.';
            return null;
        },
        get ringkas() {
            const isi = this.semuaPetak.map((p) => this.f.petak[p.id]).filter((r) => r && r.jenis_tanaman);
            const tanam = isi.filter((r) => r.jenis_tanaman !== 'B');
            return {
                jumlah: isi.length,
                tanam: tanam.reduce((t, r) => t + (+r.luas_tanam_ha || 0), 0),
                kering: tanam.reduce((t, r) => t + (+r.luas_kering_ha || 0), 0),
            };
        },
        get statusPratinjau() {
            const urut = { kosong: 0, aman: 1, waspada: 2, siaga: 3, kritis: 4 };
            return this.semuaPetak.map((p) => this.statusPetak(p.id)).filter(Boolean)
                .reduce((a, b) => (urut[b] > urut[a] ? b : a), 'kosong');
        },
        /** Laporan baru: salin jenis/fase/umur/luas dari laporan sebelumnya pelapor ini untuk petak yang masih kosong. */
        salinSebelumnya() {
            const s = this.sebelumnyaSub;
            if (!s) return;
            Object.entries(s.petak || {}).forEach(([id, r]) => {
                const t = this.petak(id);
                if (t.jenis_tanaman) return;
                t.jenis_tanaman = r.jenis_tanaman || '';
                t.fase = r.fase === null || r.fase === undefined ? '' : String(r.fase);
                t.umur_hst = r.umur_hst === null || r.umur_hst === undefined ? '' : String(r.umur_hst);
                t.luas_tanam_ha = r.luas_tanam_ha ? String(r.luas_tanam_ha) : '';
            });
            if (!this.f.tindakan_operasi && s.tindakan_operasi) this.f.tindakan_operasi = s.tindakan_operasi;
        },

        f: opsi.awal,
        foto: [],          // {file, url, ukuran}
        memproses: false,
        online: navigator.onLine,
        gps: { status: 'idle', akurasi: null },
        adaDraf: false,
        kirim: false,
        pesanOffline: false,

        get kunciDraf() { return `iris-draf-laporan-${this.f.sub_daerah_irigasi_id || 'x'}`; },
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

        init() {
            // siapkan isian untuk semua pintu & petak (agar x-model tidak mengubah state saat render)
            if (!this.f.baca || Array.isArray(this.f.baca)) this.f.baca = {};
            if (!this.f.petak || Array.isArray(this.f.petak)) this.f.petak = {};
            if (!Array.isArray(this.f.dokumentasi)) this.f.dokumentasi = [];
            Object.values(this.pintuSub).flat().forEach((p) => this.baca(p.id));
            Object.values(this.petakSub).forEach((s) => (s.daftar || []).forEach((p) => this.petak(p.id)));
            if (this.baru) {
                this.salinSebelumnya();
                this.$watch('f.sub_daerah_irigasi_id', () => this.salinSebelumnya());
            }
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
                    const pt = this.daftarPetak.find((p) => p.lat);
                    const sub = pt ? [pt.lat, pt.lng] : this.titikSub[this.f.sub_daerah_irigasi_id];
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
