@php use App\Models\PintuAir; use App\Models\SesiPintu; use App\Services\PintuService; @endphp
<x-layouts.app title="Pintu air">
<x-judul judul="Pintu air{{ $di ? ' · D.I. '.$di->nama : '' }}" sub="Debit & TMA tiap pintu dibanding rentang normalnya. Jadwal baca rutin: setiap Senin.">
    @can('isi-pintu')<a href="{{ route('pembacaan-pintu.create', $di ? ['di' => $di->id] : []) }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Baca pintu air</a>@endcan
    @if($kelola)
        <a href="{{ route('master.pintu-air.create', ['di' => $di->id]) }}" class="btn-ghost"><x-ikon n="pintu" class="h-4 w-4"/> Tambah pintu</a>
        <a href="{{ route('master.pintu-air.penugasan', ['di' => $di->id]) }}" class="btn-ghost"><x-ikon n="petugas" class="h-4 w-4"/> Penugasan</a>
        <a href="{{ route('master.pintu-air.impor', ['di' => $di->id]) }}" class="btn-ghost"><x-ikon n="unduh" class="h-4 w-4"/> Impor Excel</a>
    @endif
</x-judul>

<form method="GET" class="panel mb-4 flex flex-wrap items-end gap-3 p-3">
    <label class="min-w-[14rem] flex-1"><span class="label">D.I.</span>
        <select name="di" class="input" onchange="this.form.submit()">
            <option value="">Semua D.I. (ringkasan)</option>
            @foreach($diList as $d)<option value="{{ $d->id }}" @selected($di?->id === $d->id)>{{ $d->nama }} ({{ $d->pintu_air_count }} pintu)</option>@endforeach
        </select>
    </label>
    @if($di)
        <label class="min-w-[12rem]"><span class="label">Tampilkan</span>
            <select name="saring" class="input" onchange="this.form.submit()">
                <option value="">Semua pintu</option>
                <option value="belum" @selected(request('saring') === 'belum')>Belum dibaca minggu ini</option>
                <option value="kurang" @selected(request('saring') === 'kurang')>Debit kurang / TMA rendah</option>
                <option value="lebih" @selected(request('saring') === 'lebih')>Debit berlebih / TMA tinggi</option>
            </select>
        </label>
    @endif
    <noscript><button class="btn-primary">Terapkan</button></noscript>
    <span class="ml-auto flex gap-4 text-sm font-medium">
        <a href="{{ route('pintu.neraca', $di ? ['di' => $di->id] : []) }}" class="text-saluran-600">Neraca & pemerataan</a>
        <a href="{{ route('pembacaan-pintu.index', $di ? ['di' => $di->id] : []) }}" class="text-saluran-600">Riwayat pembacaan</a>
    </span>
</form>

<div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="panel p-3"><p class="text-xs text-basalt-soft">Pintu aktif</p><p class="text-2xl font-semibold">{{ $hitung['total'] }}</p></div>
    <a href="{{ request()->fullUrlWithQuery(['saring' => 'belum']) }}" class="panel block p-3 hover:border-saluran-500 {{ $di ? '' : 'pointer-events-none' }}">
        <p class="text-xs text-basalt-soft">Belum dibaca sejak Senin {{ $senin->translatedFormat('d M') }}</p>
        <p class="text-2xl font-semibold {{ $hitung['belum'] ? 'text-[#8A3510]' : '' }}">{{ $hitung['belum'] }}</p>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['saring' => 'kurang']) }}" class="panel block p-3 hover:border-saluran-500 {{ $di ? '' : 'pointer-events-none' }}">
        <p class="text-xs text-basalt-soft">Debit kurang / TMA rendah</p><p class="text-2xl font-semibold {{ $hitung['kurang'] ? 'text-[#8A3510]' : '' }}">{{ $hitung['kurang'] }}</p>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['saring' => 'lebih']) }}" class="panel block p-3 hover:border-saluran-500 {{ $di ? '' : 'pointer-events-none' }}">
        <p class="text-xs text-basalt-soft">Debit berlebih / TMA tinggi</p><p class="text-2xl font-semibold {{ $hitung['lebih'] ? 'text-[#1B4F7A]' : '' }}">{{ $hitung['lebih'] }}</p>
    </a>
</div>
@if($hitung['tanpa_rentang'])
    <p class="mb-4 rounded-md bg-[#FBF0CC] px-3 py-2 text-sm text-[#7A5A00]">{{ $hitung['tanpa_rentang'] }} pintu belum punya rentang normal debit/TMA, sehingga statusnya tidak bisa dinilai. Rentang diisi oleh pengelola pintu di halaman ubah pintu.</p>
@endif

@if(! $di)
    {{-- ringkasan per D.I. --}}
    @if($perDi->isEmpty())
        <div class="panel"><x-kosong judul="Belum ada pintu air">Pilih D.I. lalu tambahkan pintu air beserta kodenya, sesuai skema jaringan irigasi.</x-kosong></div>
    @else
        <div class="panel overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>D.I.</th><th class="text-right">Pintu</th><th class="text-right">Dibaca minggu ini</th><th class="text-right">Kurang/rendah</th><th class="text-right">Berlebih/tinggi</th><th>Pembacaan terakhir</th></tr></thead>
                <tbody>
                @foreach($perDi as $r)
                    <tr>
                        <td><a href="{{ route('pintu.index', ['di' => $r['di']->id]) }}" class="font-medium text-saluran-600">{{ $r['di']->nama }}</a></td>
                        <td class="text-right">{{ $r['total'] }}</td>
                        <td class="text-right"><span @class(['font-semibold text-[#8A3510]' => $r['belum']])>{{ $r['dibaca'] }}/{{ $r['total'] }}</span></td>
                        <td class="text-right">@if($r['kurang'])<span class="chip-siaga">{{ $r['kurang'] }}</span>@else 0 @endif</td>
                        <td class="text-right">@if($r['lebih'])<span class="chip-lebih">{{ $r['lebih'] }}</span>@else 0 @endif</td>
                        <td class="text-xs">{{ $r['terakhir']?->translatedFormat('l, d M Y') ?? 'Belum pernah' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-basalt-mute">D.I. yang belum punya pintu air tidak tampil. Pilih D.I. di atas untuk menambah pintu.</p>
    @endif
@else
    {{-- skema jaringan --}}
    <section class="panel mb-5 p-4" x-data="{ unggah: {{ $errors->has('file') ? 'true' : 'false' }} }">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">Skema jaringan irigasi</h2>
            @if($kelola)<button type="button" class="text-sm font-medium text-saluran-600" @click="unggah = ! unggah">Unggah skema</button>@endif
        </div>
        @if($kelola)
            <form x-show="unggah" x-cloak method="POST" action="{{ route('master.skema.store', $di) }}" enctype="multipart/form-data" class="mb-4 grid gap-3 rounded-md border border-savana-line p-3 sm:grid-cols-3">
                @csrf
                <x-field name="judul" label="Judul" class="sm:col-span-1"><input id="judul" name="judul" class="input" maxlength="150" placeholder="mis. Skema jaringan 2024"></x-field>
                <x-field name="file" label="File (JPG/PNG/PDF, maks. 20 MB)" class="sm:col-span-2"><input id="file" type="file" name="file" accept="image/*,application/pdf" class="block w-full text-sm" required></x-field>
                <div class="sm:col-span-3"><button class="btn-primary">Unggah</button></div>
            </form>
        @endif
        @if($di->skemaJaringan->isEmpty())
            <p class="text-sm text-basalt-mute">Belum ada skema. {{ $kelola ? 'Unggah gambar/PDF skema agar petugas mudah mencocokkan kode pintu.' : '' }}</p>
        @else
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($di->skemaJaringan as $s)
                    <li class="overflow-hidden rounded-md border border-savana-line">
                        @if($s->pdf)
                            <a href="{{ $s->url }}" target="_blank" rel="noopener" class="flex h-40 flex-col items-center justify-center gap-1 bg-savana text-basalt-soft hover:bg-savana-deep">
                                <x-ikon n="laporan" class="h-8 w-8"/><span class="text-xs font-semibold">PDF · {{ $s->ukuran_teks }}</span><span class="text-xs">Buka di tab baru</span>
                            </a>
                        @else
                            <a href="{{ $s->url }}" data-galeri='@json([['besar' => $s->url, 'label' => $s->judul]])' data-indeks="0" class="block">
                                <img src="{{ $s->url }}" alt="{{ $s->judul }}" loading="lazy" class="h-40 w-full bg-savana object-contain">
                            </a>
                        @endif
                        <div class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                            <span class="truncate font-medium" title="{{ $s->judul }}">{{ $s->judul }}</span>
                            <span class="flex shrink-0 gap-3 text-xs">
                                <a href="{{ $s->url }}" download class="font-medium text-saluran-600">Unduh</a>
                                @if($kelola)<form method="POST" action="{{ route('master.skema.destroy', $s) }}" onsubmit="return confirm('Hapus file skema ini?')">@csrf @method('DELETE')<button class="font-medium text-status-kritis">Hapus</button></form>@endif
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- daftar pintu per Sub-D.I. --}}
    @if($pintu->isEmpty())
        <div class="panel">
            <x-kosong judul="{{ request('saring') ? 'Tidak ada pintu yang cocok' : 'Belum ada pintu air di D.I. ini' }}" :aksi="$kelola && ! request('saring') ? 'Tambah pintu air' : null" :url="route('master.pintu-air.create', ['di' => $di->id])">
                {{ request('saring') ? 'Coba pilihan "Semua pintu".' : 'Tambahkan pintu beserta kodenya sesuai skema jaringan irigasi.' }}
            </x-kosong>
        </div>
    @else
        @foreach($pintu->groupBy(fn ($p) => $p->sub_daerah_irigasi_id ?? 0) as $subId => $grup)
            <section class="panel mb-4 overflow-hidden">
                <h2 class="border-b border-savana-line bg-savana px-3 py-2 text-sm font-semibold">{{ $subId ? 'Sub-D.I. '.$grup->first()->subDaerahIrigasi->nama : 'Tingkat D.I. (bendung / saluran induk)' }} <span class="font-normal text-basalt-mute">· {{ $grup->count() }} pintu</span></h2>
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr>
                            <th>Pintu</th><th class="text-right">Luas layanan</th>
                            <th>Debit terakhir</th><th>Perubahan</th><th>Tren</th>
                            <th>TMA terakhir</th><th>Dibaca</th>@if($kelola)<th></th>@endif
                        </tr></thead>
                        <tbody>
                        @foreach($grup as $p)
                            @php $t = $p->terakhir; @endphp
                            <tr>
                                <td class="min-w-[11rem]">
                                    <a href="{{ route('pintu.show', $p) }}" class="font-semibold text-saluran-600">{{ $p->kode }}</a>
                                    <span class="block text-xs text-basalt-soft">{{ $p->tingkat_label }}{{ $p->hulu ? ' · dari '.$p->hulu->kode : '' }}{{ $p->nama ? ' · '.$p->nama : '' }}</span>
                                    <span class="block text-[11px] text-basalt-mute">@if($p->petugas->isNotEmpty())<x-ikon n="petugas" class="inline h-3 w-3"/> {{ $p->petugas->pluck('name')->implode(', ') }}@else<span class="text-[#8A3510]">Belum ditugaskan</span>@endif · @if($p->lat)<x-ikon n="lokasi" class="inline h-3 w-3"/> titik ada @else<span class="text-[#8A3510]">titik belum</span>@endif</span>
                                </td>
                                <td class="whitespace-nowrap text-right">{{ $p->luas_layanan_ha ? angka_r($p->luas_layanan_ha).' Ha' : '–' }}@if($p->debit_rencana_lps)<span class="block text-[11px] text-basalt-mute">Q rencana {{ angka_r($p->debit_rencana_lps) }} L/dt</span>@endif</td>
                                <td class="whitespace-nowrap">
                                    @if($t?->debit_lps !== null)<span class="font-semibold">{{ angka_r($t->debit_lps) }}</span> <span class="text-xs">L/dt</span> <x-status-pintu :s="$p->status_debit"/>@else<span class="text-basalt-mute">–</span>@endif
                                    <span class="block text-[11px] text-basalt-mute">normal {{ PintuAir::rentangTeks($p->debit_min_lps, $p->debit_maks_lps, 'L/dt') }}</span>
                                </td>
                                <td><x-perubahan :d="PintuService::perubahan($t, $p->sebelum)" satuan="L/dt"/></td>
                                <td><x-tren-mini :nilai="$p->riwayat->pluck('debit_lps')" :min="$p->debit_min_lps" :maks="$p->debit_maks_lps"/></td>
                                <td class="whitespace-nowrap">
                                    @if($t?->tma_cm !== null)<span class="font-semibold">{{ angka_r($t->tma_cm) }}</span> <span class="text-xs">cm</span> <x-status-pintu :s="$p->status_tma"/>@else<span class="text-basalt-mute">–</span>@endif
                                    <span class="block text-[11px] text-basalt-mute">normal {{ PintuAir::rentangTeks($p->tma_min_cm, $p->tma_maks_cm, 'cm') }}</span>
                                </td>
                                <td class="whitespace-nowrap text-xs">
                                    @if($t)
                                        <span @class(['font-semibold text-[#8A3510]' => ! $p->minggu_ini])>{{ $t->tanggal->translatedFormat('D, d M') }}</span>
                                        <span class="block text-basalt-mute">{{ $t->sesi?->jenis === 'rutin' ? 'Rutin' : (SesiPintu::JENIS[$t->sesi?->jenis] ?? '') }}</span>
                                    @else<span class="font-semibold text-[#8A3510]">Belum pernah</span>@endif
                                </td>
                                @if($kelola)<td><a href="{{ route('master.pintu-air.edit', $p) }}" class="text-xs font-medium text-saluran-600">Ubah</a></td>@endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
        <p class="mb-5 text-xs text-basalt-mute">Status <span class="chip-siaga">Kurang</span> / <span class="chip-lebih">Berlebih</span> dihitung dari rentang normal yang diisi untuk masing-masing pintu. Perubahan ▼/▲ dibanding pembacaan sebelumnya pada pintu yang sama.</p>
    @endif

    @if($nonaktif->isNotEmpty())
        <details class="panel mb-5 p-3 text-sm">
            <summary class="cursor-pointer font-medium">{{ $nonaktif->count() }} pintu nonaktif</summary>
            <ul class="mt-2 space-y-1">@foreach($nonaktif as $p)<li><a href="{{ route('pintu.show', $p) }}" class="text-saluran-600">{{ $p->label }}</a>@if($kelola) · <a href="{{ route('master.pintu-air.edit', $p) }}" class="text-xs text-saluran-600">Ubah</a>@endif</li>@endforeach</ul>
        </details>
    @endif
@endif

@if($sesiTerbaru->isNotEmpty())
    <section class="mt-6">
        <div class="mb-2 flex items-baseline justify-between"><h2 class="text-lg font-semibold">Pembacaan terbaru</h2><a href="{{ route('pembacaan-pintu.index', $di ? ['di' => $di->id] : []) }}" class="text-sm font-medium text-saluran-600">Semua</a></div>
        <div class="panel divide-y divide-savana-line">
            @foreach($sesiTerbaru as $s)
                <a href="{{ route('pembacaan-pintu.show', $s) }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm hover:bg-savana">
                    <span><span class="font-semibold">{{ $s->tanggal->translatedFormat('l, d M Y') }}</span>@unless($di) · D.I. {{ $s->daerahIrigasi->nama }}@endunless
                        <span class="block text-xs text-basalt-soft">{{ $s->jenis_label }} · {{ $s->user->name }}</span></span>
                    <span class="text-xs text-basalt-soft">{{ $s->pembacaan_count }} pintu</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
</x-layouts.app>
