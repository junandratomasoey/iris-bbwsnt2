@props(['terang' => true])
<span {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }}>
    <img src="{{ asset('logo-pu.png') }}" alt="Logo Kementerian PU" width="36" height="36" class="h-9 w-9 shrink-0 rounded-[3px] {{ $terang ? 'ring-1 ring-white/30' : '' }}">
    <span class="leading-none">
        <span class="block font-gauge text-xl font-bold tracking-wide">IRIS</span>
        <span class="block whitespace-nowrap text-[11px] font-medium opacity-80">BBWS Nusa Tenggara II</span>
    </span>
</span>
