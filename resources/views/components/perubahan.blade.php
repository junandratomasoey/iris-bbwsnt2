@props(['d', 'satuan' => ''])
{{-- Perubahan dari pembacaan sebelumnya: [selisih, persen|null] --}}
@if($d)
    @php [$selisih, $persen] = $d; @endphp
    @if(abs($selisih) < 0.005)
        <span {{ $attributes->merge(['class' => 'text-xs text-basalt-mute']) }}>tetap</span>
    @else
        <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5 whitespace-nowrap text-xs font-semibold '.($selisih < 0 ? 'text-[#8A3510]' : 'text-[#1B4F7A]')]) }}
              title="Dibanding pembacaan sebelumnya">
            <span aria-hidden="true">{{ $selisih < 0 ? '▼' : '▲' }}</span>{{ angka_r(abs($selisih)) }}{{ $satuan ? ' '.$satuan : '' }}@if($persen !== null) <span class="font-normal">({{ $selisih < 0 ? '−' : '+' }}{{ angka_r(round(abs($persen))) }}%)</span>@endif
            <span class="sr-only">{{ $selisih < 0 ? 'turun' : 'naik' }} dibanding pembacaan sebelumnya</span>
        </span>
    @endif
@else
    <span {{ $attributes->merge(['class' => 'text-xs text-basalt-mute']) }}>–</span>
@endif
