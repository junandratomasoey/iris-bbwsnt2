@php use App\Models\PetaDi; @endphp
<x-layouts.app title="Import batas D.I.">
<div class="mx-auto max-w-2xl">
<x-judul judul="Import batas D.I." sub="Unggah GeoJSON hasil ekspor ArcGIS/QGIS. Poligon dengan nama D.I. yang sama digabung menjadi satu batas."/>
<form method="POST" action="{{ route('master.peta.import.store') }}" enctype="multipart/form-data" class="panel space-y-5 p-5" x-data="{ mode: '{{ old('mode', 'ganti_di') }}' }">
    @csrf
    <x-field name="lapisan" label="Lapisan tujuan" wajib>
        <select id="lapisan" name="lapisan" class="input">@foreach(PetaDi::LAPISAN as $k => $l)<option value="{{ $k }}" @selected(old('lapisan', $lapisan) === $k)>{{ $l }}</option>@endforeach</select>
    </x-field>
    <x-field name="berkas" label="File" :hint="'.geojson, .json, atau .zip berisi GeoJSON. Maks. '.$maksUpload.' MB (batas server).'" wajib>
        <input id="berkas" type="file" name="berkas" accept=".geojson,.json,.zip" required class="block w-full text-sm">
    </x-field>
    <fieldset>
        <legend class="label">Cara import</legend>
        <div class="space-y-2 text-sm">
            <label class="flex gap-2"><input type="radio" name="mode" value="ganti_di" x-model="mode" class="mt-1 text-saluran-600"><span><b>Ganti per D.I.</b> — batas D.I. yang ada di file menggantikan batas lamanya; D.I. lain tidak berubah. <span class="text-basalt-soft">(disarankan)</span></span></label>
            <label class="flex gap-2"><input type="radio" name="mode" value="tambah" x-model="mode" class="mt-1 text-saluran-600"><span><b>Tambahkan</b> — semua poligon ditambahkan sebagai batas baru.</span></label>
            <label class="flex gap-2"><input type="radio" name="mode" value="ganti_semua" x-model="mode" class="mt-1 text-saluran-600"><span><b>Ganti seluruh lapisan</b> — semua batas di lapisan ini dihapus lalu diisi dari file.</span></label>
        </div>
    </fieldset>
    <label x-show="mode === 'ganti_semua'" x-cloak class="flex items-center gap-2 rounded-md bg-[#FBECEA] px-3 py-2 text-sm text-[#7E1A14]">
        <input type="checkbox" name="konfirmasi" value="1" class="rounded border-savana-line text-status-kritis"> Saya paham semua batas lama di lapisan ini akan dihapus.
    </label>
    <x-field name="kolom_nama" label="Kolom nama D.I. di file" hint="Kosongkan untuk mendeteksi otomatis (Nm_Inf, nama, NAMA, name, Nama_DI). Nama dicocokkan ke D.I. tanpa memperhatikan awalan 'D.I.' dan tanda baca.">
        <input id="kolom_nama" name="kolom_nama" value="{{ old('kolom_nama') }}" class="input" placeholder="Nm_Inf">
    </x-field>
    <div class="flex gap-2"><button class="btn-primary px-8">Import</button><a href="{{ route('master.peta.index', ['lapisan' => $lapisan]) }}" class="btn-ghost">Batal</a></div>
</form>

<div class="panel mt-5 p-5 text-sm">
    <h2 class="text-lg font-semibold">Menyiapkan file dari ArcGIS / QGIS</h2>
    <p class="mt-1 text-basalt-soft">Aplikasi hanya menerima koordinat derajat <b>WGS84 (EPSG:4326)</b>. Shapefile Peta IGT masih memakai proyeksi meter (World Cylindrical Equal Area), jadi perlu diekspor dulu:</p>
    <ul class="mt-2 list-disc space-y-1 pl-5">
        <li><b>ArcGIS Pro</b>: Toolbox → <i>Features To JSON</i> → centang <i>Output to GeoJSON</i> dan <i>Project to WGS_1984</i>.</li>
        <li><b>ArcMap</b>: Project tool ke GCS_WGS_1984, lalu ekspor dengan <i>Features To JSON</i>.</li>
        <li><b>QGIS</b>: klik kanan layer → Export → Save Features As → Format <i>GeoJSON</i>, CRS <i>EPSG:4326</i>, Coordinate precision 7.</li>
    </ul>
    <p class="mt-2 text-basalt-soft">File besar boleh dikompres ke .zip. Bila tetap melebihi batas upload, ekspor per D.I. lalu import dengan cara "Ganti per D.I.".</p>
</div>
</div>
</x-layouts.app>
