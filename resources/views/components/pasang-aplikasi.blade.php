@props(['gaya' => 'tombol'])
{{-- Tombol pasang aplikasi ke layar utama HP (Android: dialog bawaan; iPhone: panduan Safari) --}}
<div x-data="pasangAplikasi" x-show="tampil" x-cloak {{ $attributes }}>
    @if($gaya === 'kartu')
        <div class="panel flex items-center gap-3 p-4">
            <img src="{{ asset('icon-192.png') }}" alt="" class="h-12 w-12 shrink-0 rounded-xl">
            <div class="min-w-0 flex-1">
                <p class="font-semibold">Pasang IRIS di HP</p>
                <p class="text-xs text-basalt-soft">Buka langsung dari layar utama seperti aplikasi, tanpa mengetik alamat.</p>
            </div>
            <button type="button" @click="pasang" class="btn-primary shrink-0">Pasang</button>
        </div>
    @elseif($gaya === 'menu')
        <button type="button" @click="pasang" class="nav-link w-full"><x-ikon n="unduh"/> Pasang aplikasi</button>
    @else
        <button type="button" @click="pasang" class="inline-flex items-center gap-1.5 rounded-md border border-white/40 px-3 py-1.5 text-sm font-semibold text-white hover:bg-white/10"><x-ikon n="unduh" class="h-4 w-4"/> <span class="sm:hidden">Pasang</span><span class="hidden sm:inline">Pasang aplikasi</span></button>
    @endif

    {{-- Panduan iPhone/iPad --}}
    <div x-show="panduan" x-cloak class="fixed inset-0 z-[1000] flex items-end justify-center bg-basalt/60 p-4 sm:items-center" @click.self="panduan = false" @keydown.escape.window="panduan = false" role="dialog" aria-modal="true" aria-labelledby="judul-pasang">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 text-basalt shadow-xl">
            <div class="flex items-center gap-3">
                <img src="{{ asset('icon-192.png') }}" alt="" class="h-12 w-12 rounded-xl">
                <div><p id="judul-pasang" class="font-gauge text-xl font-semibold">Pasang IRIS di iPhone</p><p class="text-xs text-basalt-soft">Lewat browser Safari</p></div>
            </div>
            <ol class="mt-4 space-y-3 text-sm">
                <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-saluran-600 text-xs font-bold text-white">1</span>
                    <span>Ketuk tombol <b>Bagikan</b>
                        <svg class="mx-0.5 inline h-5 w-5 align-text-bottom text-[#0A84FF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="ikon Bagikan"><path d="M12 3v12M8 7l4-4 4 4"/><path d="M6 11H5v10h14V11h-1"/></svg>
                        di bawah layar (atau di atas pada iPad).</span></li>
                <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-saluran-600 text-xs font-bold text-white">2</span>
                    <span>Gulir ke bawah, pilih <b>Tambah ke Layar Utama</b> (<i>Add to Home Screen</i>).</span></li>
                <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-saluran-600 text-xs font-bold text-white">3</span>
                    <span>Ketuk <b>Tambah</b>. Ikon IRIS akan muncul di layar utama.</span></li>
            </ol>
            <button type="button" class="btn-primary mt-5 w-full" @click="panduan = false">Mengerti</button>
        </div>
    </div>
</div>
