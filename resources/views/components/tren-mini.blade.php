@props(['nilai', 'min' => null, 'maks' => null])
{{-- Batang kecil pembacaan debit terakhir (lama → baru); garis tipis = batas rentang normal. --}}
@php
    $v = collect($nilai)->map(fn ($x) => $x === null ? null : (float) $x);
    $puncak = max(1, (float) $v->filter()->max(), (float) ($maks ?? 0), (float) ($min ?? 0)) * 1.1;
    $w = 8; $gap = 3; $h = 26; $n = max(1, $v->count());
@endphp
@if($v->filter(fn ($x) => $x !== null)->isNotEmpty())
<svg viewBox="0 0 {{ $n * ($w + $gap) }} {{ $h }}" width="{{ $n * ($w + $gap) }}" height="{{ $h }}" class="block" role="img" aria-label="Tren {{ $v->count() }} pembacaan terakhir">
    @foreach($v as $i => $x)
        @if($x !== null)
            @php $bh = max(1.5, $x / $puncak * $h); $luar = ($min !== null && $x < $min) || ($maks !== null && $x > $maks); @endphp
            <rect x="{{ $i * ($w + $gap) }}" y="{{ $h - $bh }}" width="{{ $w }}" height="{{ $bh }}" rx="1.5" fill="{{ $luar ? (($min !== null && $x < $min) ? '#D9622B' : '#3D7DB3') : '#7FA88A' }}" @if($loop->last) opacity="1" @else opacity=".75" @endif/>
        @endif
    @endforeach
    @foreach(array_filter([$min, $maks], fn ($b) => $b !== null) as $b)
        <line x1="0" x2="{{ $n * ($w + $gap) }}" y1="{{ $h - $b / $puncak * $h }}" y2="{{ $h - $b / $puncak * $h }}" stroke="#4A4A44" stroke-width=".8" stroke-dasharray="2 2"/>
    @endforeach
</svg>
@endif
