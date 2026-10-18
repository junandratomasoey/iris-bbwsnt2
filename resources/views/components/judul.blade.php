@props(['judul', 'sub' => null])
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold leading-tight sm:text-3xl">{{ $judul }}</h1>
        @if($sub)<p class="mt-0.5 text-sm text-basalt-soft">{{ $sub }}</p>@endif
    </div>
    @if($slot->isNotEmpty())<div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>@endif
</div>
