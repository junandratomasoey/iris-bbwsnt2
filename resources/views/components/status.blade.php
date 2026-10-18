@props(['s', 'label' => null])
<span {{ $attributes->merge(['class' => 'chip-'.$s]) }}><span class="h-1.5 w-1.5 rounded-full" style="background: {{ \App\Support\StatusKondisi::WARNA[$s] ?? '#9AA4A8' }}"></span>{{ $label ?? \App\Support\StatusKondisi::label($s) }}</span>
