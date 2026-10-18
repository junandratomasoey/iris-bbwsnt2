/** Kompres foto di browser (maks 1600 px, JPEG ±72%) agar unggah cepat di sinyal lemah & lolos batas upload server. */
const MAKS_SISI = 1600;
const KUALITAS = 0.72;

export async function kompres(file) {
    if (!file.type.startsWith('image/')) return file;
    try {
        const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const skala = Math.min(1, MAKS_SISI / Math.max(bmp.width, bmp.height));
        const c = document.createElement('canvas');
        c.width = Math.round(bmp.width * skala);
        c.height = Math.round(bmp.height * skala);
        c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
        const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', KUALITAS));
        if (!blob || blob.size >= file.size) return file;
        return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
    } catch (e) {
        return file;
    }
}


/**
 * Komponen Alpine pemilih foto: pratinjau, kompres otomatis, hapus sebelum kirim.
 * Wajib ada <input type="file" x-ref="input" name="foto[]" multiple hidden> di dalamnya.
 */
export function unggahFoto(opsi = {}) {
    return {
        maks: opsi.maks ?? 5,
        foto: [],
        memproses: false,
        async pilih(e) {
            const files = [...e.target.files];
            e.target.value = '';
            this.memproses = true;
            for (const file of files) {
                if (this.foto.length >= this.maks) break;
                const hasil = await kompres(file);
                this.foto.push({ file: hasil, url: URL.createObjectURL(hasil), ukuran: Math.round(hasil.size / 1024) });
            }
            this.sinkron();
            this.memproses = false;
        },
        hapus(i) {
            URL.revokeObjectURL(this.foto[i].url);
            this.foto.splice(i, 1);
            this.sinkron();
        },
        sinkron() {
            const dt = new DataTransfer();
            this.foto.forEach((x) => dt.items.add(x.file));
            this.$refs.input.files = dt.files;
        },
    };
}
