<x-layouts.app :title="'Impor data pintu · '.$di->nama">
<x-judul :judul="'Impor data pintu air · D.I. '.$di->nama" sub="Isi atau perbarui data pintu (tingkat, pintu hulu, luas layanan petak, debit rencana, rentang normal, penugasan petugas) sekaligus lewat Excel.">
    <a href="{{ route('pintu.index', ['di' => $di->id]) }}" class="btn-ghost"><x-ikon n="pintu" class="h-4 w-4"/> Kembali ke pintu air</a>
</x-judul>

@if(! $hasil)
    <div class="grid gap-4 md:grid-cols-2">
        <section class="panel p-5">
            <h2 class="text-lg font-semibold"><span class="bagian-huruf">1</span>Unduh template</h2>
            <p class="mt-2 text-sm">Template berisi <b>{{ $jumlahPintu }} pintu</b> yang sudah ada di D.I. ini, lengkap dengan daftar pilihan tingkat, jenis, Sub-D.I., dan No. HP petugas. Lembar <b>Petunjuk</b> menjelaskan setiap kolom.</p>
            <a href="{{ route('master.pintu-air.impor.template', $di) }}" class="btn-primary mt-3"><x-ikon n="unduh" class="h-4 w-4"/> Unduh template Excel</a>
            <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-basalt-soft">
                <li>Baris dengan <b>kode</b> yang sudah ada akan mengubah pintu itu; kode baru menambah pintu.</li>
                <li>Pintu yang tidak ada di file <b>tidak dihapus</b>.</li>
                <li>Kosongkan sel petugas untuk menghapus penugasan pintu itu.</li>
            </ul>
        </section>
        <section class="panel p-5">
            <h2 class="text-lg font-semibold"><span class="bagian-huruf">2</span>Unggah hasil isian</h2>
            <form method="POST" action="{{ route('master.pintu-air.impor.periksa', $di) }}" enctype="multipart/form-data" class="mt-3 space-y-3" x-data="{ kirim: false }" @submit="kirim = true">
                @csrf
                <input type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-saluran-600 file:px-3 file:py-2 file:font-semibold file:text-white">
                @error('file')<p class="text-sm font-medium text-status-kritis">{{ $message }}</p>@enderror
                <button class="btn-primary" :disabled="kirim" x-text="kirim ? 'Memeriksa…' : 'Periksa file'"></button>
                <p class="text-xs text-basalt-mute">Belum ada yang disimpan pada langkah ini. Perubahan ditampilkan dulu untuk diperiksa.</p>
            </form>
        </section>
    </div>
@else
    @php $aksiLabel = ['baru' => ['Baru', 'chip-aman'], 'ubah' => ['Diubah', 'chip-waspada'], 'sama' => ['Tetap', 'chip-kosong']]; @endphp
    <div class="mb-4 grid gap-3 sm:grid-cols-4">
        <div class="panel p-4"><p class="text-sm text-basalt-soft">Pintu baru</p><p class="font-gauge text-3xl font-semibold">{{ $hasil['baru'] }}</p></div>
        <div class="panel p-4"><p class="text-sm text-basalt-soft">Pintu diubah</p><p class="font-gauge text-3xl font-semibold">{{ $hasil['ubah'] }}</p></div>
        <div class="panel p-4"><p class="text-sm text-basalt-soft">Tidak berubah</p><p class="font-gauge text-3xl font-semibold">{{ $hasil['sama'] }}</p></div>
        <div class="panel p-4"><p class="text-sm text-basalt-soft">Baris bergalat</p><p class="font-gauge text-3xl font-semibold {{ $hasil['galat'] ? 'text-status-kritis' : '' }}">{{ $hasil['galat'] }}</p></div>
    </div>

    @if($hasil['galat'])
        <div class="mb-4 rounded-md border border-[#E9B7B3] bg-[#FBECEA] px-4 py-3 text-sm text-[#7E1A14]">
            <p class="font-semibold">Ada {{ $hasil['galat'] }} baris yang perlu diperbaiki.</p>
            <p>Perbaiki di file Excel (lihat nomor baris), lalu unggah ulang. Tidak ada data yang disimpan.</p>
        </div>
    @endif

    <section class="panel mb-4 overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Baris</th><th>Kode</th><th>Tingkat · jenis</th><th>Hulu</th><th class="text-right">Luas (Ha)</th><th class="text-right">Debit rencana</th>@if($hasil['kolom_tugas'])<th>Petugas</th>@endif<th>Hasil</th></tr></thead>
            <tbody>
            @foreach($hasil['baris'] as $b)
                @php $d = $b['data']; @endphp
                <tr @class(['bg-[#FBECEA]' => $b['galat']])>
                    <td class="text-basalt-soft">{{ $b['no'] }}</td>
                    <td class="whitespace-nowrap font-semibold">{{ $d['kode'] ?: '–' }}@if($d['nama'])<span class="block text-xs font-normal text-basalt-soft">{{ $d['nama'] }}</span>@endif</td>
                    <td class="whitespace-nowrap text-xs">{{ $d['tingkat'] ?? '?' }} · {{ $d['jenis'] ?? '?' }}@unless($d['aktif'])<span class="block text-basalt-mute">nonaktif</span>@endunless</td>
                    <td class="text-xs">{{ $d['hulu_kode'] ?? '–' }}</td>
                    <td class="text-right">{{ $d['luas_layanan_ha'] !== null ? angka_r($d['luas_layanan_ha']) : '–' }}</td>
                    <td class="text-right">{{ $d['debit_rencana_lps'] !== null ? angka_r($d['debit_rencana_lps']) : '–' }}</td>
                    @if($hasil['kolom_tugas'])<td class="text-xs">{{ collect($b['tugas'] ?? [])->map(fn ($u, $peran) => $u['nama'].($peran === 'pengganti' ? ' (pengganti)' : ''))->implode(', ') ?: '–' }}</td>@endif
                    <td class="text-xs">
                        @if($b['galat'])
                            <ul class="list-disc pl-4 font-medium text-[#7E1A14]">@foreach($b['galat'] as $g)<li>{{ $g }}</li>@endforeach</ul>
                        @else
                            <span class="{{ $aksiLabel[$b['aksi']][1] }}">{{ $aksiLabel[$b['aksi']][0] }}</span>
                            @if($b['ubah'])<span class="mt-0.5 block text-basalt-soft">{{ implode(', ', $b['ubah']) }}</span>@endif
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <div class="flex flex-wrap gap-2">
        @if(! $hasil['galat'] && ($hasil['baru'] || $hasil['ubah']))
            <form method="POST" action="{{ route('master.pintu-air.impor.terapkan', $di) }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
                <button class="btn-primary"><x-ikon n="cek" class="h-4 w-4"/> Simpan {{ $hasil['baru'] + $hasil['ubah'] }} perubahan</button></form>
        @elseif(! $hasil['galat'])
            <p class="text-sm text-basalt-soft">Tidak ada perubahan untuk disimpan.</p>
        @endif
        <a href="{{ route('master.pintu-air.impor', ['di' => $di->id]) }}" class="btn-ghost">Unggah file lain</a>
    </div>
@endif
</x-layouts.app>
