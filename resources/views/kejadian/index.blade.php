<x-layouts.app title="Kejadian kekeringan">
<x-judul judul="Kejadian kekeringan" sub="Lokasi gagal tanam, gagal panen, dan kerusakan jaringan beserta status penanganannya.">
    @can('create', App\Models\Kejadian::class)<a href="{{ route('kejadian.create') }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Laporkan kejadian</a>@endcan
</x-judul>

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach(App\Models\Kejadian::STATUS as $st => $w)
        @php $r = $rekap->get($st); @endphp
        <a href="{{ route('kejadian.index', ['status' => $st]) }}" @class(['panel border-l-4 p-3 hover:bg-savana', 'ring-2 ring-saluran-500' => request('status') === $st]) style="border-left-color: {{ $w }}">
            <p class="text-xs text-basalt-soft">{{ $st }}</p>
            <p class="font-gauge text-2xl font-semibold">{{ $r->jumlah ?? 0 }} <span class="text-sm font-medium text-basalt-soft">lokasi</span></p>
            <p class="text-xs text-basalt-mute">{{ angka($r->luas ?? 0, 1) }} Ha terdampak</p>
        </a>
    @endforeach
</div>

<form method="GET" class="panel mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
    <label class="lg:col-span-2"><span class="label">Cari desa, kecamatan, pelapor</span><input name="q" value="{{ request('q') }}" class="input"></label>
    <label><span class="label">Kabupaten</span><select name="kabupaten" class="input"><option value="">Semua</option>@foreach($kabupatenList as $k)<option value="{{ $k->id }}" @selected(request('kabupaten') == $k->id)>{{ $k->nama }}</option>@endforeach</select></label>
    <label><span class="label">Jenis</span><select name="jenis" class="input"><option value="">Semua</option>@foreach(App\Models\Kejadian::JENIS as $j)<option @selected(request('jenis') === $j)>{{ $j }}</option>@endforeach</select></label>
    <div class="flex items-end gap-2"><input type="hidden" name="status" value="{{ request('status') }}"><button class="btn-primary flex-1">Terapkan</button>@if(request()->query())<a href="{{ route('kejadian.index') }}" class="btn-ghost">Reset</a>@endif</div>
</form>

<div class="panel overflow-hidden">
    @if($kejadian->isEmpty())
        <x-kosong judul="Tidak ada kejadian yang cocok">Ubah filter atau laporkan kejadian baru.</x-kosong>
    @else
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Lokasi</th><th>Satgas</th><th>Jenis / penyebab</th><th class="text-right">Terdampak (Ha)</th><th class="text-right">Tertangani (Ha)</th><th>Status</th><th>Pelapor</th></tr></thead>
                <tbody>
                @foreach($kejadian as $k)
                    <tr class="hover:bg-savana">
                        <td><a href="{{ route('kejadian.show', $k) }}" class="font-semibold text-saluran-700 hover:underline">{{ $k->desa ?? $k->kecamatan ?? $k->kabupaten?->nama }}</a>
                            <div class="text-xs text-basalt-soft">{{ collect([$k->kecamatan, $k->kabupaten?->nama])->filter()->implode(', ') }}@if($k->daerahIrigasi) · D.I. {{ $k->daerahIrigasi->nama }}@endif</div></td>
                        <td class="whitespace-nowrap">{{ $k->kabupaten?->wilayahSatgas ? 'Wilayah '.$k->kabupaten->wilayahSatgas->kode : '–' }}</td>
                        <td>{{ $k->jenis_kegagalan }}<div class="text-xs text-basalt-soft">{{ $k->penyebab }}</div>@if($k->foto_count)<div class="mt-0.5 flex items-center gap-1 text-xs text-saluran-600"><x-ikon n="kamera" class="h-3.5 w-3.5"/> {{ $k->foto_count }} foto</div>@endif</td>
                        <td class="text-right tabular-nums">{{ angka($k->luas_terdampak_ha, 2) }}</td>
                        <td class="text-right tabular-nums">{{ angka($k->luas_tertangani_ha, 2) }}</td>
                        <td><x-kejadian-status :s="$k->status_penanganan"/></td>
                        <td class="text-xs">{{ $k->pelapor_nama }}@if(wa_link($k->pelapor_hp)) <a href="{{ wa_link($k->pelapor_hp) }}" target="_blank" rel="noopener" class="btn-wa ml-1">WA</a>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $kejadian->links() }}
    @endif
</div>
</x-layouts.app>
