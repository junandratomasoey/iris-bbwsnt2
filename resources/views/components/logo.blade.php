@props(['terang' => true])
<span {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }}>
    <svg viewBox="0 0 32 32" class="h-8 w-8 shrink-0" aria-hidden="true">
        <rect width="32" height="32" rx="6" fill="{{ $terang ? '#E8F3F4' : '#0F5E6E' }}"/>
        <path d="M9 6v20" stroke="{{ $terang ? '#0F5E6E' : '#E8F3F4' }}" stroke-width="2"/>
        <path d="M9 9h4M9 13h6M9 17h4M9 21h6" stroke="{{ $terang ? '#0F5E6E' : '#E8F3F4' }}" stroke-width="1.6"/>
        <path d="M17 19c2-1.6 3.6-1.6 5.5 0S26 20.6 27 19" stroke="#16798C" stroke-width="2" fill="none" stroke-linecap="round"/>
    </svg>
    <span class="leading-none">
        <span class="block font-gauge text-xl font-bold tracking-wide">IRIS</span>
        <span class="block text-[11px] font-medium opacity-80">BBWS Nusa Tenggara II</span>
    </span>
</span>
