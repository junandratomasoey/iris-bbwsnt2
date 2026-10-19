// Form pembacaan pintu air: status langsung terhadap rentang normal tiap pintu.
export function formPintu({ batas, nilai, rutin, tanggal, jenis }) {
    const angka = (v) => (v === null || v === undefined || v === '' ? null : Number(v));
    const banding = (v, min, maks, bawah, atas) => {
        v = angka(v);
        if (v === null || Number.isNaN(v) || (min === null && maks === null)) return null;
        if (min !== null && v < min) return bawah;
        if (maks !== null && v > maks) return atas;
        return 'normal';
    };
    const isi = {};
    for (const id of Object.keys(batas)) {
        const n = nilai[id] || {};
        isi[id] = { debit: n.debit ?? '', tma: n.tma ?? '', bukaan: n.bukaan ?? '', catatan: n.catatan ?? '' };
    }

    return {
        nilai: isi,
        tanggal,
        jenis: jenis ?? '',
        kirim: false,
        hariSenin() {
            if (!this.tanggal) return true;
            const [y, m, d] = this.tanggal.split('-').map(Number);
            return new Date(y, m - 1, d).getDay() === rutin % 7;
        },
        terisi(id) {
            const n = this.nilai[id];
            return [n.debit, n.tma, n.bukaan].some((v) => v !== '' && v !== null);
        },
        jumlah() {
            return Object.keys(this.nilai).filter((id) => {
                const n = this.nilai[id];
                return [n.debit, n.tma, n.bukaan].some((v) => v !== '' && v !== null);
            }).length;
        },
        statusDebit(id) {
            const b = batas[id];
            return banding(this.nilai[id].debit, b.dmin, b.dmaks, 'kurang', 'berlebih');
        },
        statusTma(id) {
            const b = batas[id];
            return banding(this.nilai[id].tma, b.tmin, b.tmaks, 'rendah', 'tinggi');
        },
        teks(s) {
            return { kurang: '▼ di bawah normal', rendah: '▼ di bawah normal', berlebih: '▲ di atas normal', tinggi: '▲ di atas normal', normal: '✓ normal' }[s] || '';
        },
        warna(s) {
            return { kurang: 'text-[#8A3510]', rendah: 'text-[#8A3510]', berlebih: 'text-[#1B4F7A]', tinggi: 'text-[#1B4F7A]', normal: 'text-[#2F5A19]' }[s] || '';
        },
    };
}
