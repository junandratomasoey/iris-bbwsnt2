@props(['judul', 'aksi' => null, 'url' => null])
<div class="px-6 py-12 text-center">
    <p class="font-gauge text-lg font-semibold">{{ $judul }}</p>
    @if($slot->isNotEmpty())<p class="mx-auto mt-1 max-w-md text-sm text-basalt-soft">{{ $slot }}</p>@endif
    @if($aksi && $url)<a href="{{ $url }}" class="btn-primary mt-4">{{ $aksi }}</a>@endif
</div>
