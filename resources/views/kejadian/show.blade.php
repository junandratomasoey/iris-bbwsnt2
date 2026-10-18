<x-layouts.app :title="'Kejadian '.($kejadian->desa ?? $kejadian->kecamatan)">
<x-judul :judul="$kejadian->jenis_kegagalan.' — '.($kejadian->desa ?? $kejadian->kecamatan ?? $kejadian->kabupaten?->nama)" :sub="collect([$kejadian->kecamatan, $kejadian->kabupaten?->nama])->filter()->implode(', ').' · dilaporkan '.$kejadian->tanggal_lapor->translatedFormat('d F Y')">
    @can('update', $kejadian)<a href="{{ route('kejadian.edit', $kejadian) }}" class="btn-ghost"><x-ikon n="edit" class="h-4 w-4"/> Ubah</a>@endcan
    @can('delete', $kejadian)<form method="POST" action="{{ route('kejadian.destroy', $kejadian) }}" onsubmit="return confirm('Hapus kejadian ini?')">@csrf @method('DELETE')<button class="btn-ghost text-status-kritis"><x-ikon n="hapus" class="h-4 w-4"/> Hapus</button></form>@endcan
</x-judul>
<div class="grid gap-5 lg:grid-cols-3">
    <section class="panel p-5 lg:col-span-2">
        <div class="flex flex-wrap gap-2"><x-kejadian-status :s="$kejadian->status_penanganan"/><span class="chip-kosong">{{ $kejadian->status_kegagalan }}</span>@if($kejadian->kabupaten?->wilayahSatgas)<span class="chip-kosong">Satgas Wilayah {{ $kejadian->kabupaten->wilayahSatgas->kode }}</span>@endif</div>
        @php $persen = $kejadian->luas_terdampak_ha > 0 ? min(100, $kejadian->luas_tertangani_ha / $kejadian->luas_terdampak_ha * 100) : 0; @endphp
        <div class="mt-4">
            <div class="flex justify-between text-sm"><span>Tertangani <b>{{ angka($kejadian->luas_tertangani_ha, 2) }}</b> dari <b>{{ angka($kejadian->luas_terdampak_ha, 2) }} Ha</b></span><span class="font-gauge text-lg font-semibold">{{ round($persen) }}%</span></div>
            <div class="mt-1 h-2 rounded-full bg-savana-deep"><div class="h-2 rounded-full bg-status-aman" style="width: {{ $persen }}%"></div></div>
        </div>
        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-basalt-soft">D.I. / Sub-D.I.</dt><dd class="font-medium">{{ $kejadian->daerahIrigasi ? 'D.I. '.$kejadian->daerahIrigasi->nama : 'Di luar D.I. kewenangan pusat' }}{{ $kejadian->subDaerahIrigasi ? ' · '.$kejadian->subDaerahIrigasi->nama : '' }}</dd></div>
            <div><dt class="text-basalt-soft">Penyebab</dt><dd class="font-medium">{{ $kejadian->penyebab ?? '–' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-basalt-soft">Sumber air</dt><dd class="whitespace-pre-line">{{ $kejadian->sumber_air ?? '–' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-basalt-soft">Penanganan</dt><dd class="whitespace-pre-line">{{ $kejadian->solusi ?? '–' }}</dd></div>
            <div><dt class="text-basalt-soft">P3A/Poktan</dt><dd>{{ $kejadian->p3a ?? '–' }}</dd></div>
            <div><dt class="text-basalt-soft">Sumber data</dt><dd>{{ $kejadian->sumber_data ?? '–' }}</dd></div>
            @if($kejadian->catatan)<div class="sm:col-span-2"><dt class="text-basalt-soft">Catatan</dt><dd class="whitespace-pre-line">{{ $kejadian->catatan }}</dd></div>@endif
        </dl>
        @if($kejadian->foto->isNotEmpty())
            <div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-3">@foreach($kejadian->foto as $f)<a href="{{ $f->url }}" target="_blank"><img src="{{ $f->url }}" alt="Foto kejadian" class="aspect-[4/3] w-full rounded object-cover" loading="lazy"></a>@endforeach</div>
        @endif
    </section>
    <div class="space-y-5">
        <section class="panel p-5 text-sm">
            <h2 class="mb-2 text-lg font-semibold">Pelapor</h2>
            <p class="font-semibold">{{ $kejadian->pelapor_nama ?? $kejadian->user?->name ?? '–' }}</p>
            @if(wa_link($kejadian->pelapor_hp))<a href="{{ wa_link($kejadian->pelapor_hp, 'Selamat siang, terkait laporan kejadian di '.($kejadian->desa ?? $kejadian->kecamatan).':') }}" target="_blank" rel="noopener" class="btn-wa mt-2"><x-ikon n="wa" class="h-3.5 w-3.5"/> {{ $kejadian->pelapor_hp }}</a>@endif
            @if($kejadian->lat)<p class="mt-3"><a class="text-saluran-600 underline" target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $kejadian->lat }},{{ $kejadian->lng }}">Buka lokasi di Google Maps</a></p>@endif
        </section>
        @can('tangani', $kejadian)
            <form method="POST" action="{{ route('kejadian.penanganan', $kejadian) }}" class="panel space-y-3 p-5 text-sm">
                @csrf @method('PATCH')
                <h2 class="text-lg font-semibold">Perbarui penanganan</h2>
                <x-field name="status_penanganan" label="Status"><select id="status_penanganan" name="status_penanganan" class="input">@foreach(array_keys(App\Models\Kejadian::STATUS) as $s)<option @selected($kejadian->status_penanganan === $s)>{{ $s }}</option>@endforeach</select></x-field>
                <x-field name="luas_tertangani_ha" label="Luas tertangani (Ha)"><input id="luas_tertangani_ha" type="number" step="0.01" min="0" name="luas_tertangani_ha" value="{{ old('luas_tertangani_ha', $kejadian->luas_tertangani_ha) }}" class="input"></x-field>
                <x-field name="solusi" label="Penanganan yang dilakukan"><textarea id="solusi" name="solusi" rows="3" class="input">{{ old('solusi', $kejadian->solusi) }}</textarea></x-field>
                <button class="btn-primary w-full">Simpan penanganan</button>
            </form>
        @endcan
    </div>
</div>
</x-layouts.app>
