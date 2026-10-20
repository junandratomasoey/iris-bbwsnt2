<x-layouts.app title="Laporan harian">
<x-judul judul="Laporan harian" sub="Air sungai, intake & saluran diisi Manager per D.I.; kondisi tanaman diisi PPA per petak.">
    @can('unduh-rekap')<a href="{{ route('laporan.ekspor', request()->query()) }}" class="btn-ghost"><x-ikon n="unduh" class="h-4 w-4"/> Unduh CSV</a>@endcan
    @can('create', App\Models\LaporanHarian::class)
        <a href="{{ route('laporan.create') }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Isi laporan tanaman</a>
    @endcan
</x-judul>
<x-tab-laporan aktif="tanaman"/>

<form method="GET" class="panel mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
    <label class="lg:col-span-2"><span class="label">D.I.</span>
        <select name="di" class="input"><option value="">Semua D.I.</option>
            @foreach($diList as $d)<option value="{{ $d->id }}" @selected(request('di') == $d->id)>{{ $d->nama }}</option>@endforeach
        </select></label>
    <label><span class="label">Dari</span><input type="date" name="dari" value="{{ request('dari') }}" class="input"></label>
    <label><span class="label">Sampai</span><input type="date" name="sampai" value="{{ request('sampai') }}" class="input"></label>
    <label><span class="label">Verifikasi</span>
        <select name="verifikasi" class="input"><option value="">Semua</option>
            @foreach(App\Models\LaporanHarian::VERIFIKASI as $k => $l)<option value="{{ $k }}" @selected(request('verifikasi') === $k)>{{ $l }}</option>@endforeach
        </select></label>
    <div class="flex items-end gap-2">
        <button class="btn-primary flex-1">Terapkan</button>
        @if(request()->query())<a href="{{ route('laporan.index') }}" class="btn-ghost">Reset</a>@endif
    </div>
    <label class="flex items-center gap-2 text-sm sm:col-span-2 lg:col-span-6"><input type="checkbox" name="milik_saya" value="1" @checked(request('milik_saya')) class="rounded border-savana-line text-saluran-600"> Hanya laporan saya</label>
</form>

@php
    $bisaVerif = auth()->user()->can('verifikasi');
    $bolehSetujui = fn ($l) => $bisaVerif && $l->status_verifikasi === 'menunggu' && auth()->user()->can('verify', $l);
    $idSiap = $laporan->getCollection()->filter($bolehSetujui)->pluck('id')->values();
@endphp
@if($menunggu)
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-[#EBD9A3] bg-[#FDF6E3] px-4 py-3 text-sm">
        <p><b>{{ angka($menunggu) }} laporan</b> menunggu verifikasi Anda. Setujui dari daftar, atau buka laporan untuk memeriksa foto dan meminta perbaikan.</p>
        @if(request('verifikasi') !== 'menunggu')<a href="{{ route('laporan.index', ['verifikasi' => 'menunggu']) }}" class="btn-primary py-1.5">Tampilkan yang menunggu</a>@endif
    </div>
@endif

<div class="panel overflow-hidden" x-data="{ pilih: [], siap: @js($idSiap) }">
    @if($idSiap->isNotEmpty())
        <form method="POST" action="{{ route('laporan.verifikasi-massal') }}" x-ref="massal" class="flex flex-wrap items-center gap-3 border-b border-savana-line bg-savana px-4 py-2 text-sm"
              @submit="if (!pilih.length) { $event.preventDefault(); return; } if (!confirm(`Setujui ${pilih.length} laporan?`)) $event.preventDefault()">
            @csrf
            <template x-for="id in pilih" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
            <label class="flex items-center gap-2"><input type="checkbox" class="rounded border-savana-line text-saluran-600" :checked="pilih.length && pilih.length === siap.length" @change="pilih = $event.target.checked ? [...siap] : []"> Pilih semua yang menunggu di halaman ini</label>
            <button class="btn-primary py-1.5" :disabled="!pilih.length"><x-ikon n="cek" class="h-4 w-4"/> <span x-text="pilih.length ? `Setujui ${pilih.length} laporan` : 'Setujui terpilih'"></span></button>
            <span class="text-xs text-basalt-mute">Untuk meminta perbaikan, buka laporannya.</span>
        </form>
    @endif
    @if($laporan->isEmpty())
        <x-kosong judul="Belum ada laporan" :aksi="auth()->user()->can('create', App\Models\LaporanHarian::class) ? 'Isi laporan pertama' : null" :url="route('laporan.create')">Laporan harian dari petugas akan muncul di sini.</x-kosong>
    @else
        {{-- tabel untuk layar lebar --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="table-base">
                <thead><tr>@if($idSiap->isNotEmpty())<th class="w-8"></th>@endif<th>Tanggal</th><th>Sub-D.I.</th><th>Fase / komoditas</th><th class="text-right">Tanam / kering / puso (Ha)</th><th>Status</th><th>Pelapor</th><th>Verifikasi</th></tr></thead>
                <tbody>
                @foreach($laporan as $l)
                    <tr class="hover:bg-savana" :class="pilih.includes({{ $l->id }}) && 'bg-saluran-50'">
                        @if($idSiap->isNotEmpty())<td>@if($bolehSetujui($l))<input type="checkbox" value="{{ $l->id }}" x-model.number="pilih" class="rounded border-savana-line text-saluran-600" aria-label="Pilih laporan {{ $l->subDaerahIrigasi->nama }} {{ $l->tanggal->format('d/m') }}">@endif</td>@endif
                        <td class="whitespace-nowrap"><a href="{{ route('laporan.show', $l) }}" class="font-semibold text-saluran-700 hover:underline">{{ $l->tanggal->translatedFormat('d M Y') }}</a></td>
                        <td>{{ $l->subDaerahIrigasi->nama }}@if($l->petak)<span class="ml-1 rounded bg-savana-deep px-1.5 text-xs font-semibold">Petak {{ $l->petak->kode }}</span>@endif<div class="text-xs text-basalt-soft">D.I. {{ $l->subDaerahIrigasi->daerahIrigasi->nama }}</div></td>
                        <td class="text-xs">{{ App\Models\LaporanHarian::FASE[$l->fase_tanaman] ?? '–' }}<div class="text-basalt-soft">{{ $l->ringkasKomoditas() }}</div></td>
                        <td class="whitespace-nowrap text-right tabular-nums">{{ angka($l->luas_tanam_ha, 1) }} / {{ angka($l->luas_kering_ha, 1) }} / {{ angka($l->luas_puso_ha, 1) }}</td>
                        <td><x-status :s="$l->status()"/></td>
                        <td class="text-xs">{{ $l->user->name }}@if($l->foto_count)<div class="text-basalt-mute">{{ $l->foto_count }} foto</div>@endif</td>
                        <td class="whitespace-nowrap"><x-verifikasi :s="$l->status_verifikasi"/>
                            @if($bolehSetujui($l))<div class="mt-1 flex gap-2 text-xs font-semibold"><button type="button" class="text-status-aman hover:underline" @click="pilih = [{{ $l->id }}]; $nextTick(() => $refs.massal.requestSubmit())">Setujui</button><a href="{{ route('laporan.show', $l) }}" class="text-saluran-600 hover:underline">Periksa</a></div>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{-- kartu untuk HP --}}
        <ul class="divide-y divide-savana-line md:hidden">
            @foreach($laporan as $l)
                <li class="flex items-start gap-1" :class="pilih.includes({{ $l->id }}) && 'bg-saluran-50'">
                    @if($bolehSetujui($l))<label class="py-3.5 pl-4"><input type="checkbox" value="{{ $l->id }}" x-model.number="pilih" class="rounded border-savana-line text-saluran-600" aria-label="Pilih laporan"></label>@endif
                    <a href="{{ route('laporan.show', $l) }}" class="block min-w-0 flex-1 px-4 py-3">
                    <div class="flex items-start justify-between gap-2">
                        <div><p class="font-semibold">{{ $l->subDaerahIrigasi->nama }}@if($l->petak) · Petak {{ $l->petak->kode }}@endif</p><p class="text-xs text-basalt-soft">{{ $l->tanggal->translatedFormat('d M Y') }} · {{ $l->user->name }}</p></div>
                        <x-status :s="$l->status()"/>
                    </div>
                    <p class="mt-1 text-xs text-basalt-soft">Tanam {{ angka($l->luas_tanam_ha, 1) }} · kering {{ angka($l->luas_kering_ha, 1) }} · puso {{ angka($l->luas_puso_ha, 1) }} Ha</p>
                    <div class="mt-1"><x-verifikasi :s="$l->status_verifikasi"/></div>
                </a></li>
            @endforeach
        </ul>
        {{ $laporan->links() }}
    @endif
</div>
</x-layouts.app>
