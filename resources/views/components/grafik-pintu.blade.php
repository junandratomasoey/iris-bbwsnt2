@props(['data', 'kolom', 'min' => null, 'maks' => null, 'satuan', 'judul'])
{{-- Garis pembacaan (lama → baru) dengan pita rentang normal. $data: koleksi PembacaanPintu. --}}
@php
    $titik = collect($data)->filter(fn ($b) => $b->$kolom !== null)->values();
    $W = 640; $H = 180; $kiri = 44; $kanan = 12; $atas = 12; $bawah = 26;
    $nilai = $titik->pluck($kolom)->map(fn ($v) => (float) $v);
    $lo = min($nilai->min() ?? 0, $min ?? INF, 0);
    $hi = max($nilai->max() ?? 1, $maks ?? 0, $min ?? 0);
    $hi = $hi <= $lo ? $lo + 1 : $hi * 1.08;
    $y = fn ($v) => $atas + ($H - $atas - $bawah) * (1 - ($v - $lo) / ($hi - $lo));
    $n = $titik->count();
    $x = fn ($i) => $n <= 1 ? ($kiri + $W - $kanan) / 2 : $kiri + ($W - $kiri - $kanan) * $i / ($n - 1);
    $garis = $titik->map(fn ($b, $i) => round($x($i), 1).','.round($y((float) $b->$kolom), 1))->implode(' ');
    $grid = collect(range(0, 4))->map(fn ($k) => $lo + ($hi - $lo) * $k / 4);
    $warna = fn ($v) => ($min !== null && $v < $min) ? '#D9622B' : (($maks !== null && $v > $maks) ? '#3D7DB3' : '#2E6B4A');
@endphp
<figure>
    <figcaption class="mb-1 text-sm font-semibold">{{ $judul }} <span class="font-normal text-basalt-mute">({{ $satuan }})</span></figcaption>
    @if($n === 0)
        <p class="rounded-md bg-savana px-3 py-6 text-center text-sm text-basalt-mute">Belum ada pembacaan.</p>
    @else
    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="h-auto w-full" role="img" aria-label="{{ $judul }}, {{ $n }} pembacaan terakhir">
        @foreach($grid as $g)
            <line x1="{{ $kiri }}" x2="{{ $W - $kanan }}" y1="{{ $y($g) }}" y2="{{ $y($g) }}" stroke="#E4DED2" stroke-width="1"/>
            <text x="{{ $kiri - 6 }}" y="{{ $y($g) + 4 }}" text-anchor="end" font-size="11" fill="#6B675E">{{ angka_r(round($g, $hi - $lo < 10 ? 1 : 0)) }}</text>
        @endforeach
        @if($min !== null || $maks !== null)
            @php $yAtas = $y($maks ?? $hi); $yBawah = $y($min ?? $lo); @endphp
            <rect x="{{ $kiri }}" y="{{ $yAtas }}" width="{{ $W - $kiri - $kanan }}" height="{{ max(0, $yBawah - $yAtas) }}" fill="#7FA88A" opacity=".16"/>
            @foreach(array_filter([$min, $maks], fn ($b) => $b !== null) as $b)
                <line x1="{{ $kiri }}" x2="{{ $W - $kanan }}" y1="{{ $y($b) }}" y2="{{ $y($b) }}" stroke="#4E8A2E" stroke-width="1" stroke-dasharray="4 3"/>
            @endforeach
        @endif
        <polyline points="{{ $garis }}" fill="none" stroke="#2E6B4A" stroke-width="2" stroke-linejoin="round"/>
        @foreach($titik as $i => $b)
            @php $v = (float) $b->$kolom; @endphp
            <circle cx="{{ $x($i) }}" cy="{{ $y($v) }}" r="4" fill="{{ $warna($v) }}" stroke="#fff" stroke-width="1.5"><title>{{ $b->tanggal->translatedFormat('d M Y') }}: {{ angka_r($v) }} {{ $satuan }}</title></circle>
            @if($n <= 8 || $i % (int) ceil($n / 8) === 0 || $i === $n - 1)
                <text x="{{ $x($i) }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="11" fill="#6B675E">{{ $b->tanggal->translatedFormat('d/m') }}</text>
            @endif
        @endforeach
    </svg>
    @endif
</figure>
