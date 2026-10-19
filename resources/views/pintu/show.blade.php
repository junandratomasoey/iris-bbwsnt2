@php use App\Models\PintuAir; @endphp
<x-layouts.app title="Pintu {{ $p->kode }}">
<x-judul judul="Pintu {{ $p->kode }}{{ $p->nama ? ' · '.$p->nama : '' }}" sub="D.I. {{ $p->daerahIrigasi->nama }}{{ $p->subDaerahIrigasi ? ' · Sub-D.I. '.$p->subDaerahIrigasi->nama : '' }}">
    <a href="{{ route('pintu.index', ['di' => $p->daerah_irigasi_id]) }}" class="btn-ghost">Semua pintu D.I.</a>
    @can('isi-pintu')<a href="{{ route('pembacaan-pintu.create', ['di' => $p->daerah_irigasi_id]) }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Baca pintu air</a>@endcan
    @if($kelola)<a href="{{ route('master.pintu-air.edit', $p) }}" class="btn-ghost"><x-ikon n="edit" class="h-4 w-4"/> Ubah</a>@endif
</x-judul>

@unless($p->aktif)<p class="mb-4 rounded-md bg-savana-deep px-3 py-2 text-sm">Pintu ini nonaktif dan tidak muncul di form pembacaan.</p>@endunless

<div class="grid gap-5 lg:grid-cols-3">
    <section class="panel p-4 lg:col-span-1">
        <h2 class="mb-3 text-lg font-semibold">Data pintu</h2>
        <dl class="grid grid-cols-[auto,1fr] gap-x-4 gap-y-2 text-sm">
            <dt class="text-basalt-soft">Jenis</dt><dd>{{ $p->jenis_label }}</dd>
            @if($p->saluran)<dt class="text-basalt-soft">Saluran</dt><dd>{{ $p->saluran }}</dd>@endif
            @if($p->bangunan)<dt class="text-basalt-soft">Bangunan</dt><dd>{{ $p->bangunan->nama }}</dd>@endif
            <dt class="text-basalt-soft">Luas layanan</dt><dd>{{ $p->luas_layanan_ha ? angka_r($p->luas_layanan_ha).' Ha' : '–' }}</dd>
            <dt class="text-basalt-soft">Debit normal</dt><dd>{{ PintuAir::rentangTeks($p->debit_min_lps, $p->debit_maks_lps, 'L/dt') }}</dd>
            <dt class="text-basalt-soft">TMA normal</dt><dd>{{ PintuAir::rentangTeks($p->tma_min_cm, $p->tma_maks_cm, 'cm') }}</dd>
            @if($p->lat)<dt class="text-basalt-soft">Titik</dt><dd><a href="https://www.google.com/maps?q={{ $p->lat }},{{ $p->lng }}" target="_blank" rel="noopener" class="text-saluran-600">{{ number_format($p->lat, 5) }}, {{ number_format($p->lng, 5) }}</a></dd>@endif
        </dl>
        @if($p->keterangan)<p class="mt-3 whitespace-pre-line text-sm text-basalt-soft">{{ $p->keterangan }}</p>@endif
        @if(! $p->punyaRentangDebit() && $p->tma_min_cm === null && $p->tma_maks_cm === null)
            <p class="mt-3 rounded-md bg-[#FBF0CC] px-3 py-2 text-xs text-[#7A5A00]">Rentang normal belum diisi, jadi status kurang/berlebih belum bisa dinilai.</p>
        @endif
    </section>
    <section class="panel space-y-6 p-4 lg:col-span-2">
        <x-grafik-pintu :data="$grafik" kolom="debit_lps" :min="$p->debit_min_lps" :maks="$p->debit_maks_lps" satuan="L/dt" judul="Debit"/>
        <x-grafik-pintu :data="$grafik" kolom="tma_cm" :min="$p->tma_min_cm" :maks="$p->tma_maks_cm" satuan="cm" judul="Tinggi muka air (TMA)"/>
        <p class="text-xs text-basalt-mute">{{ $grafik->count() }} pembacaan terakhir. Pita hijau = rentang normal; titik oranye = di bawah, biru = di atas rentang.</p>
    </section>
</div>

<section class="panel mt-5 overflow-x-auto">
    <h2 class="px-4 pt-4 text-lg font-semibold">Riwayat pembacaan</h2>
    @if($riwayat->isEmpty())
        <x-kosong judul="Belum ada pembacaan"/>
    @else
        <table class="table-base mt-2">
            <thead><tr><th>Tanggal</th><th>Alasan</th><th class="text-right">Debit (L/dt)</th><th class="text-right">TMA (cm)</th><th class="text-right">Bukaan (cm)</th><th>Catatan</th><th>Petugas</th></tr></thead>
            <tbody>
            @foreach($riwayat as $b)
                <tr>
                    <td class="whitespace-nowrap"><a href="{{ route('pembacaan-pintu.show', $b->sesi_pintu_id) }}" class="text-saluran-600">{{ $b->tanggal->translatedFormat('D, d M Y') }}</a>@if($b->sesi?->jam)<span class="block text-xs text-basalt-mute">{{ substr($b->sesi->jam, 0, 5) }}</span>@endif</td>
                    <td class="text-xs">{{ $b->sesi?->jenis_label }}</td>
                    <td class="whitespace-nowrap text-right">{{ angka_r($b->debit_lps) }} <x-status-pintu :s="$p->statusDebit($b->debit_lps)"/></td>
                    <td class="whitespace-nowrap text-right">{{ angka_r($b->tma_cm) }} <x-status-pintu :s="$p->statusTma($b->tma_cm)"/></td>
                    <td class="text-right">{{ angka_r($b->bukaan_cm) }}</td>
                    <td class="text-xs">{{ $b->catatan }}</td>
                    <td class="text-xs">{{ $b->sesi?->user?->name }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</section>
</x-layouts.app>
