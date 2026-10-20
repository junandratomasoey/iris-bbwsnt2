@props(['aktif'])
<nav class="mb-4 flex gap-1 border-b border-savana-line" aria-label="Jenis laporan">
    @foreach(['tanaman' => ['laporan.index', 'Laporan tanaman (PPA)'], 'air' => ['laporan-air.index', 'Laporan air (Manager)']] as $k => [$r, $l])
        <a href="{{ route($r) }}" @class(['-mb-px border-b-2 px-4 py-2 text-sm font-semibold', 'border-saluran-600 text-saluran-700' => $aktif === $k, 'border-transparent text-basalt-soft hover:text-basalt' => $aktif !== $k]) @if($aktif === $k) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
