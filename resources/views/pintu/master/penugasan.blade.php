@php use App\Models\PintuAir; @endphp
<x-layouts.app title="Penugasan pintu air">
<x-judul judul="Penugasan pintu air" sub="D.I. {{ $di->nama }} — siapa membaca pintu mana. Manager/POB umumnya bendung & primer, PPA sekunder & tersier.">
    <a href="{{ route('pintu.index', ['di' => $di->id]) }}" class="btn-ghost">Kembali ke pemantauan</a>
</x-judul>

@if($pintu->isEmpty())
    <div class="panel"><x-kosong judul="Belum ada pintu air" aksi="Tambah pintu" :url="route('master.pintu-air.create', ['di' => $di->id])"/></div>
@elseif($petugasList->isEmpty())
    <div class="panel"><x-kosong judul="Belum ada petugas di D.I. ini" aksi="Tambah kontak petugas" :url="route('petugas.create', ['di' => $di->id])">Tambahkan Manager, POB, atau PPA untuk D.I. ini terlebih dahulu.</x-kosong></div>
@else
<form method="POST" action="{{ route('master.pintu-air.penugasan.simpan') }}" x-data="{ kirim: false }" @submit="kirim = true">
    @csrf
    <input type="hidden" name="di" value="{{ $di->id }}">
    @if($errors->any())<p class="mb-3 rounded-md bg-[#F6D9D7] px-3 py-2 text-sm text-[#7E1A14]">{{ $errors->first() }}</p>@endif
    <div class="panel overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Pintu</th><th>Tingkat</th><th>Sub-D.I.</th><th class="min-w-[13rem]">Penanggung jawab</th><th class="min-w-[13rem]">Pengganti</th></tr></thead>
            <tbody>
            @foreach($pintu as $p)
                @php $utama = $p->petugas->firstWhere('pivot.peran', 'utama')?->id; $ganti = $p->petugas->firstWhere('pivot.peran', 'pengganti')?->id; @endphp
                <tr>
                    <td><span class="font-semibold">{{ $p->kode }}</span>@if($p->hulu)<span class="block text-xs text-basalt-mute">dari {{ $p->hulu->kode }}</span>@endif</td>
                    <td class="text-xs">{{ $p->tingkat_label }}</td>
                    <td class="text-xs">{{ $p->subDaerahIrigasi?->nama ?? 'Tingkat D.I.' }}</td>
                    <td><select name="tugas[{{ $p->id }}][utama]" class="input"><option value="">— Belum —</option>@foreach($petugasList as $u)<option value="{{ $u->id }}" @selected($utama === $u->id)>{{ $u->name }} · {{ $u->jabatan ?: $u->roleLabel() }}</option>@endforeach</select></td>
                    <td><select name="tugas[{{ $p->id }}][pengganti]" class="input"><option value="">—</option>@foreach($petugasList as $u)<option value="{{ $u->id }}" @selected($ganti === $u->id)>{{ $u->name }} · {{ $u->jabatan ?: $u->roleLabel() }}</option>@endforeach</select></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="sticky bottom-0 mt-3 flex items-center gap-3 border-t border-savana-line bg-savana py-3">
        <button class="btn-primary px-8" :disabled="kirim" x-text="kirim ? 'Menyimpan…' : 'Simpan penugasan'"></button>
        <p class="text-xs text-basalt-mute">Pintu yang belum ditugaskan tetap bisa diisi semua petugas yang wilayahnya mencakup pintu itu.</p>
    </div>
</form>
@endif
</x-layouts.app>
