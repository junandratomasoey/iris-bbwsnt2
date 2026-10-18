@props(['periode', 'rute', 'tambahan' => []])
{{-- Pemilih periode: tautan cepat + rentang tanggal sendiri. $tambahan = parameter lain yang dipertahankan (mis. mode warna) --}}
<div {{ $attributes->merge(['class' => 'panel flex flex-wrap items-center gap-2 p-2']) }} x-data="{ rentang: {{ $periode->kode === 'rentang' ? 'true' : 'false' }} }">
    <nav class="flex flex-wrap gap-1" aria-label="Periode">
        @foreach(\App\Support\Periode::PILIHAN as $k => $l)
            @if($k === 'rentang')
                <button type="button" @click="rentang = !rentang" :aria-expanded="rentang"
                        @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-saluran-600 text-white' => $periode->kode === $k, 'text-basalt-soft hover:bg-savana' => $periode->kode !== $k])>{{ $l }}</button>
            @else
                <a href="{{ route($rute, ['periode' => $k] + $tambahan) }}" @if($periode->kode === $k) aria-current="page" @endif
                   @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-saluran-600 text-white' => $periode->kode === $k, 'text-basalt-soft hover:bg-savana' => $periode->kode !== $k])>{{ $l }}</a>
            @endif
        @endforeach
    </nav>
    <form method="GET" action="{{ route($rute) }}" x-show="rentang" x-cloak class="flex flex-wrap items-end gap-2">
        <input type="hidden" name="periode" value="rentang">
        @foreach($tambahan as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <label class="text-xs"><span class="sr-only">Dari</span><input type="date" name="dari" value="{{ $periode->dari->toDateString() }}" max="{{ today()->toDateString() }}" class="input py-1.5 text-sm"></label>
        <span class="pb-2 text-sm text-basalt-mute">s.d.</span>
        <label class="text-xs"><span class="sr-only">Sampai</span><input type="date" name="sampai" value="{{ $periode->sampai->toDateString() }}" max="{{ today()->toDateString() }}" class="input py-1.5 text-sm"></label>
        <button class="btn-primary py-1.5">Tampilkan</button>
    </form>
</div>
