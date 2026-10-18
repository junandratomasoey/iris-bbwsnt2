@props(['foto', 'hapusRoute' => null, 'kelompok' => false])
{{-- Galeri foto; klik membuka layar penuh. $hapusRoute: closure(Foto): url untuk tombol hapus --}}
@php
    $data = $foto->values()->map(fn ($f) => ['besar' => $f->url, 'label' => $f->label ?? ''])->all();
    $indeks = $foto->values()->pluck('id')->flip();
    $grup = $kelompok
        ? $foto->groupBy(fn ($f) => $f->keterangan ?: 'lainnya')->sortBy(fn ($v, $k) => ['sebelum' => 0, 'sesudah' => 1][$k] ?? 2)
        : collect(['' => $foto]);
@endphp
<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
    @foreach($grup as $kunci => $daftar)
        <div>
            @if($kelompok)<p class="mb-2 text-sm font-semibold">{{ \App\Models\Foto::LABEL[$kunci] ?? 'Dokumentasi lain' }} <span class="font-normal text-basalt-mute">({{ $daftar->count() }})</span></p>@endif
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach($daftar as $f)
                    <div class="group relative">
                        <a href="{{ $f->url }}" data-galeri='@json($data)' data-indeks="{{ $indeks[$f->id] }}" class="block">
                            <img src="{{ $f->url_kecil }}" alt="{{ $f->label ?? 'Foto dokumentasi' }}" loading="lazy" class="aspect-[4/3] w-full rounded object-cover">
                        </a>
                        <span class="pointer-events-none absolute bottom-1 left-1 rounded bg-basalt/70 px-1.5 text-[10px] text-white">{{ $f->created_at->translatedFormat('d M Y') }}</span>
                        @if($hapusRoute)
                            <form method="POST" action="{{ $hapusRoute($f) }}" class="absolute right-1 top-1" onsubmit="return confirm('Hapus foto ini?')">@csrf @method('DELETE')
                                <button class="rounded-full bg-white/90 p-1 text-status-kritis shadow hover:bg-white" aria-label="Hapus foto"><x-ikon n="hapus" class="h-4 w-4"/></button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
