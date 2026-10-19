@php use App\Models\SesiPintu; @endphp
<x-layouts.app title="Riwayat pembacaan pintu air">
<x-judul judul="Riwayat pembacaan pintu air" sub="Setiap kunjungan pembacaan: rutin Senin dan kejadian khusus.">
    <a href="{{ route('pintu.index', request()->only('di')) }}" class="btn-ghost"><x-ikon n="pintu" class="h-4 w-4"/> Pemantauan pintu</a>
    @can('isi-pintu')<a href="{{ route('pembacaan-pintu.create', request()->only('di')) }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Baca pintu air</a>@endcan
</x-judul>
<form method="GET" class="panel mb-4 grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-4">
    <label><span class="label">D.I.</span><select name="di" class="input"><option value="">Semua</option>@foreach($diList as $d)<option value="{{ $d->id }}" @selected(request('di') == $d->id)>{{ $d->nama }}</option>@endforeach</select></label>
    <label><span class="label">Alasan</span><select name="jenis" class="input"><option value="">Semua</option>@foreach(SesiPintu::JENIS as $k => $l)<option value="{{ $k }}" @selected(request('jenis') === $k)>{{ $l }}</option>@endforeach</select></label>
    <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" name="milik_saya" value="1" @checked(request()->boolean('milik_saya')) class="rounded border-savana-line text-saluran-600"> Hanya isian saya</label>
    <div class="flex items-end"><button class="btn-primary w-full">Terapkan</button></div>
</form>
<div class="panel overflow-x-auto">
    @if($sesi->isEmpty())
        <x-kosong judul="Belum ada pembacaan"/>
    @else
        <table class="table-base">
            <thead><tr><th>Tanggal</th><th>D.I.</th><th>Alasan</th><th class="text-right">Pintu</th><th class="text-right">Foto</th><th>Petugas</th></tr></thead>
            <tbody>
            @foreach($sesi as $s)
                <tr>
                    <td class="whitespace-nowrap"><a href="{{ route('pembacaan-pintu.show', $s) }}" class="font-medium text-saluran-600">{{ $s->tanggal->translatedFormat('D, d M Y') }}</a>@if($s->jam)<span class="block text-xs text-basalt-mute">{{ substr($s->jam, 0, 5) }}</span>@endif</td>
                    <td>{{ $s->daerahIrigasi->nama }}</td>
                    <td class="text-xs">@if($s->jenis === 'rutin')<span class="chip-aman">Rutin</span>@else<span class="chip-waspada">{{ $s->jenis_label }}</span>@endif</td>
                    <td class="text-right">{{ $s->pembacaan_count }}</td>
                    <td class="text-right">{{ $s->foto_count ?: '–' }}</td>
                    <td class="text-xs">{{ $s->user->name }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{ $sesi->links() }}
    @endif
</div>
</x-layouts.app>
