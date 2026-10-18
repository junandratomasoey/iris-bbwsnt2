/**
 * Tombol "Pasang aplikasi" (PWA).
 * - Android/Chrome/Edge: memakai event beforeinstallprompt → dialog pasang bawaan browser.
 * - iPhone/iPad (Safari): tidak ada dialog otomatis, jadi tampilkan panduan "Tambah ke Layar Utama".
 * - Disembunyikan bila aplikasi sudah dibuka dari layar utama.
 */
let promptTertunda = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    promptTertunda = e;
    window.dispatchEvent(new CustomEvent('iris-bisa-dipasang'));
});
window.addEventListener('appinstalled', () => {
    promptTertunda = null;
    window.dispatchEvent(new CustomEvent('iris-terpasang'));
});

const sudahTerpasang = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const iOS = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

export function pasangAplikasi() {
    return {
        bisa: false,
        ios: false,
        panduan: false,
        get tampil() { return !sudahTerpasang() && (this.bisa || this.ios); },

        init() {
            this.ios = iOS();
            this.bisa = !!promptTertunda;
            window.addEventListener('iris-bisa-dipasang', () => { this.bisa = true; });
            window.addEventListener('iris-terpasang', () => { this.bisa = false; this.ios = false; });
        },

        async pasang() {
            if (promptTertunda) {
                promptTertunda.prompt();
                const { outcome } = await promptTertunda.userChoice;
                if (outcome === 'accepted') this.bisa = false;
                promptTertunda = null;
                return;
            }
            this.panduan = true;
        },
    };
}
