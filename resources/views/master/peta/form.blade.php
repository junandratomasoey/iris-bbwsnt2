@php use App\Models\PetaDi; $baru = ! $peta->exists; @endphp
<x-layouts.app :title="$baru ? 'Gambar batas D.I.' : 'Ubah batas '.$peta->nama" lebar="mx-auto max-w-[1600px] px-4 py-6 sm:px-6">
<x-judul :judul="$baru ? 'Gambar batas D.I.' : 'Ubah batas: '.$peta->nama" :sub="$baru ? 'Gambar poligon di atas citra satelit, atau muat file GeoJSON lalu sesuaikan.' : 'Geser titik sudut, tambah atau hapus bagian, lalu simpan.'"/>

<form method="POST" action="{{ $baru ? route('master.peta.store') : route('master.peta.update', $peta) }}"
      x-data="petaEditor({ geometri: @js($peta->geometri), id: {{ $peta->id ?? 'null' }}, pusat: @js($pusat), acuanUrl: '{{ asset('geojson/'.$peta->lapisan.'.geojson') }}?v={{ \App\Support\GeojsonVersi::get() }}' })"
      x-on:galat-peta.window="alert($event.detail)"
      class="grid gap-5 lg:grid-cols-[1fr_22rem]">
    @csrf @if(! $baru) @method('PUT') @endif
    <input type="hidden" name="geometri" :value="geometri">

    <div class="panel overflow-hidden">
        <div x-ref="peta" class="h-[70vh] min-h-[420px]" role="application" aria-label="Editor batas D.I."></div>
    </div>

    <div class="space-y-4">
        <div class="panel space-y-4 p-5">
            <x-field name="lapisan" label="Lapisan" wajib>
                <select id="lapisan" name="lapisan" class="input">@foreach(PetaDi::LAPISAN as $k => $l)<option value="{{ $k }}" @selected(old('lapisan', $peta->lapisan) === $k)>{{ $l }}</option>@endforeach</select>
            </x-field>
            <x-field name="daerah_irigasi_id" label="Daerah Irigasi" hint="Luas dan titik tengah D.I. ikut diperbarui saat disimpan.">
                <select id="daerah_irigasi_id" name="daerah_irigasi_id" class="input"><option value="">— Belum terhubung —</option>@foreach($diList as $d)<option value="{{ $d->id }}" @selected(old('daerah_irigasi_id', $peta->daerah_irigasi_id) == $d->id)>{{ $d->nama }}</option>@endforeach</select>
            </x-field>
            <x-field name="nama" label="Nama batas" wajib>
                <input id="nama" name="nama" value="{{ old('nama', $peta->nama) }}" required class="input" placeholder="Mis. D.I. Oesao">
            </x-field>
            <x-field name="keterangan" label="Keterangan perubahan">
                <textarea id="keterangan" name="keterangan" rows="2" class="input" placeholder="Mis. penyesuaian batas hasil survei Okt 2026">{{ old('keterangan', $peta->keterangan) }}</textarea>
            </x-field>
            @error('geometri')<p class="text-sm font-medium text-status-kritis">{{ $message }}</p>@enderror

            <dl class="grid grid-cols-2 gap-3 rounded-md bg-savana p-3">
                <div><dt class="text-xs text-basalt-soft">Luas (geodesik)</dt><dd class="font-gauge text-2xl font-semibold"><span x-text="luasHa.toLocaleString('id-ID', { maximumFractionDigits: 2 })"></span> <span class="text-sm">Ha</span></dd></div>
                <div><dt class="text-xs text-basalt-soft">Bagian poligon</dt><dd class="font-gauge text-2xl font-semibold" x-text="jumlahBagian"></dd></div>
            </dl>
            <p x-show="berubah" x-cloak class="text-xs font-medium text-status-siaga">Ada perubahan yang belum disimpan.</p>

            <div class="flex gap-2">
                <button class="btn-primary flex-1" :disabled="!geometri">Simpan batas</button>
                <a href="{{ route('master.peta.index', ['lapisan' => $peta->lapisan]) }}" class="btn-ghost">Batal</a>
            </div>
        </div>

        <div class="panel space-y-3 p-5 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" x-model="tampilAcuan" class="rounded border-savana-line text-saluran-600"> Tampilkan batas D.I. lain (garis putih putus-putus)</label>
            <label class="btn-ghost w-full cursor-pointer">Muat poligon dari file GeoJSON
                <input type="file" accept=".geojson,.json,application/geo+json,application/json" class="sr-only" @change="muatBerkas">
            </label>
            <button type="button" class="w-full text-xs font-medium text-status-kritis" @click="if (confirm('Hapus semua bagian poligon di editor?')) hapusSemua()">Kosongkan editor</button>
        </div>

        <details class="panel p-5 text-sm">
            <summary class="cursor-pointer font-semibold">Cara memakai alat di peta</summary>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-basalt-soft">
                <li><b>Gambar poligon</b>: klik titik demi titik, klik titik pertama untuk menutup.</li>
                <li><b>Ubah titik</b>: geser titik sudut; klik kanan titik untuk menghapusnya; tarik titik tengah garis untuk menambah titik.</li>
                <li><b>Potong</b>: gambar poligon di dalam batas untuk membuat lubang atau memotong bagian.</li>
                <li><b>Hapus</b>: klik bagian poligon yang ingin dibuang.</li>
                <li>Titik menempel otomatis ke batas lain (snapping) agar tidak ada celah.</li>
            </ul>
        </details>
    </div>
</form>

@if(! $baru)
    <form method="POST" action="{{ route('master.peta.destroy', $peta) }}" class="mt-6" onsubmit="return confirm('Hapus batas ini dari peta?')">@csrf @method('DELETE')
        <button class="text-sm font-medium text-status-kritis">Hapus batas ini</button>
    </form>
@endif
</x-layouts.app>
