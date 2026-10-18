/**
 * Penampil foto layar penuh (lightbox) untuk popup peta dan galeri.
 * Pakai atribut pada elemen: data-galeri='[{"besar":"...","label":"..."}]' data-indeks="0"
 */
let el = null;
let daftar = [];
let i = 0;

function tampil() {
    const f = daftar[i];
    el.querySelector('img').src = f.besar;
    el.querySelector('[data-label]').textContent = [f.label, `${i + 1}/${daftar.length}`].filter(Boolean).join(' · ');
    el.querySelectorAll('[data-nav]').forEach((b) => { b.style.display = daftar.length < 2 ? 'none' : 'flex'; });
}

const terbuka = () => el && el.style.display !== 'none';

function tutup() {
    el.style.display = 'none';
    document.body.style.overflow = '';
}

function buat() {
    el = document.createElement('div');
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-label', 'Foto');
    el.style.cssText = 'position:fixed;inset:0;z-index:2000;background:rgba(15,20,24,.92);display:none;align-items:center;justify-content:center;flex-direction:column;padding:16px';
    const tombol = 'position:absolute;top:50%;transform:translateY(-50%);z-index:2;display:flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:999px;border:0;background:rgba(255,255,255,.18);color:#fff;font-size:30px;line-height:1;cursor:pointer';
    el.innerHTML = `
        <button type="button" data-tutup aria-label="Tutup" style="position:absolute;top:12px;right:12px;z-index:2;display:flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:999px;border:0;background:rgba(255,255,255,.18);color:#fff;font-size:30px;line-height:1;cursor:pointer">&times;</button>
        <button type="button" data-nav="-1" aria-label="Sebelumnya" style="${tombol};left:12px">&#8249;</button>
        <img alt="Foto dokumentasi" style="max-width:100%;max-height:calc(100vh - 90px);object-fit:contain;border-radius:6px">
        <p data-label style="margin-top:10px;color:#e8f3f4;font-size:14px;font-family:inherit"></p>
        <button type="button" data-nav="1" aria-label="Berikutnya" style="${tombol};right:12px">&#8250;</button>`;
    el.addEventListener('click', (e) => {
        e.stopPropagation();
        const nav = e.target.closest('[data-nav]');
        if (nav) { i = (i + Number(nav.dataset.nav) + daftar.length) % daftar.length; tampil(); return; }
        if (e.target === el || e.target.closest('[data-tutup]')) tutup();
    });
    document.addEventListener('keydown', (e) => {
        if (!terbuka()) return;
        if (e.key === 'Escape') tutup();
        if (e.key === 'ArrowRight') { i = (i + 1) % daftar.length; tampil(); }
        if (e.key === 'ArrowLeft') { i = (i - 1 + daftar.length) % daftar.length; tampil(); }
    });
    let x0 = null;
    el.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
    el.addEventListener('touchend', (e) => {
        if (x0 === null || daftar.length < 2) return;
        const dx = e.changedTouches[0].clientX - x0;
        if (Math.abs(dx) > 50) { i = (i + (dx < 0 ? 1 : -1) + daftar.length) % daftar.length; tampil(); }
        x0 = null;
    });
    document.body.appendChild(el);
}

export function lihatFoto(list, indeks = 0) {
    if (!list || !list.length) return;
    if (!el) buat();
    daftar = list;
    i = indeks;
    tampil();
    el.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    el.querySelector('[data-tutup]').focus();
}

// Klik pada elemen ber-data-galeri di mana pun (popup Leaflet dibuat sebagai string HTML)
document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-galeri]');
    if (!t) return;
    e.preventDefault();
    try { lihatFoto(JSON.parse(t.dataset.galeri), Number(t.dataset.indeks || 0)); } catch (err) { /* abaikan */ }
});
