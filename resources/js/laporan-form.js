/**
 * Form laporan harian petugas: draf tersimpan di HP, GPS otomatis,
 * foto dikompres di browser sebelum dikirim (hemat kuota & sinyal).
 */
import { kompres } from './foto';

const MAKS_FOTO = 5;

export function laporanForm(opsi) {
    return {
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
        get statusPratinjau() {
            const t = +this.f.luas_tanam_ha || 0;
            const rk = t > 0 ? (+this.f.luas_kering_ha || 0) / t : 0;
            if ((+this.f.luas_puso_ha || 0) > 0 || rk >= 0.5) return 'kritis';
            let s = { penuh: 'aman', normal: 'aman', kurang: 'waspada', kering: 'siaga' }[this.f.kondisi_saluran] || 'kosong';
            const urut = { kosong: 0, aman: 1, waspada: 2, siaga: 3, kritis: 4 };
            const naik = (a, b) => (urut[a] >= urut[b] ? a : b);
            if (rk >= 0.25) s = naik(s, 'siaga'); else if (rk >= 0.1) s = naik(s, 'waspada');
            return s;
        },
        get peringatanLuas() {
            const luas = this.luasSub[this.f.sub_daerah_irigasi_id];
            if (luas && +this.f.luas_tanam_ha > luas * 1.05) return `Luas tanam melebihi luas Sub-D.I. (${luas.toLocaleString('id-ID')} Ha). Periksa kembali.`;
            return null;
        },

        init() {
            window.addEventListener('online', () => (this.online = true));
            window.addEventListener('offline', () => (this.online = false));
            if (opsi.hapusDraf) this.hapusDraf();
            if (this.baru) {
                try { this.adaDraf = !!localStorage.getItem(this.kunciDraf); } catch (e) {}
                this.$watch('f', () => this.simpanDraf(), { deep: true });
                this.$watch('f.sub_daerah_irigasi_id', () => { try { this.adaDraf = !!localStorage.getItem(this.kunciDraf); } catch (e) {} });
                if (!this.f.lat) this.ambilGps();
            }
        },

        simpanDraf() {
            if (this.kirim) return;
            try { localStorage.setItem(this.kunciDraf, JSON.stringify({ ...this.f, _waktu: Date.now() })); } catch (e) {}
        },
        pulihkanDraf() {
            try {
                const d = JSON.parse(localStorage.getItem(this.kunciDraf));
                if (d) { delete d._waktu; Object.assign(this.f, d); }
            } catch (e) {}
            this.adaDraf = false;
        },
        hapusDraf() {
            try {
                Object.keys(localStorage).filter((k) => k.startsWith('iris-draf-laporan-')).forEach((k) => localStorage.removeItem(k));
            } catch (e) {}
            this.adaDraf = false;
        },

        ambilGps() {
            if (!navigator.geolocation) { this.gps.status = 'tidak-didukung'; return; }
            this.gps.status = 'mencari';
            navigator.geolocation.getCurrentPosition(
                (p) => {
                    this.f.lat = p.coords.latitude.toFixed(7);
                    this.f.lng = p.coords.longitude.toFixed(7);
                    this.gps = { status: 'ok', akurasi: Math.round(p.coords.accuracy) };
                },
                () => { this.gps.status = 'gagal'; },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
            );
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

        kirimForm(e) {
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
