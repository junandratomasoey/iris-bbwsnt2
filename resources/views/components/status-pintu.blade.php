@props(['s', 'kosong' => null])
{{-- Status pembacaan pintu terhadap rentang normal yang diisi untuk pintu tsb. --}}
@php
    [$kelas, $teks] = match ($s) {
        'kurang' => ['chip-siaga', 'Kurang'],
        'rendah' => ['chip-siaga', 'Rendah'],
        'berlebih' => ['chip-lebih', 'Berlebih'],
        'tinggi' => ['chip-lebih', 'Tinggi'],
        'normal' => ['chip-aman', 'Normal'],
        default => [null, $kosong],
    };
@endphp
@if($kelas)<span {{ $attributes->merge(['class' => $kelas]) }}>{{ $teks }}</span>@elseif($teks)<span {{ $attributes->merge(['class' => 'text-[11px] text-basalt-mute']) }}>{{ $teks }}</span>@endif
