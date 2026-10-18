<x-layouts.app title="Log aktivitas">
<x-judul judul="Log aktivitas" sub="Jejak perubahan data oleh pengguna."/>
<form method="GET" class="panel mb-4 flex flex-wrap items-end gap-3 p-4">
    <label><span class="label">Aksi</span><select name="aksi" class="input"><option value="">Semua</option>@foreach(['dibuat', 'diubah', 'dihapus'] as $a)<option @selected(request('aksi') === $a)>{{ $a }}</option>@endforeach</select></label>
    <label><span class="label">Objek</span><select name="objek" class="input"><option value="">Semua</option>@foreach(['LaporanHarian' => 'Laporan harian', 'Kejadian' => 'Kejadian', 'User' => 'Pengguna', 'DaerahIrigasi' => 'D.I.', 'SubDaerahIrigasi' => 'Sub-D.I.', 'JadwalTanam' => 'Jadwal tanam', 'P3a' => 'P3A'] as $k => $l)<option value="{{ $k }}" @selected(request('objek') === $k)>{{ $l }}</option>@endforeach</select></label>
    <button class="btn-primary">Terapkan</button>
</form>
<div class="panel overflow-x-auto">
    <table class="table-base">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Perubahan</th></tr></thead>
        <tbody>
        @forelse($log as $a)
            <tr>
                <td class="whitespace-nowrap text-xs">{{ $a->created_at->translatedFormat('d M Y H:i') }}</td>
                <td class="text-xs">{{ $a->user?->name ?? 'Sistem' }}<div class="text-basalt-mute">{{ $a->ip_address }}</div></td>
                <td><span class="chip-kosong">{{ $a->action }}</span></td>
                <td class="text-xs">{{ class_basename($a->subject_type) }} #{{ $a->subject_id }}</td>
                <td class="max-w-md text-xs text-basalt-soft">
                    @if($a->action === 'diubah' && isset($a->changes['baru']))
                        @foreach($a->changes['baru'] as $k => $v)<div><b>{{ $k }}</b>: {{ \Illuminate\Support\Str::limit(is_scalar($a->changes['lama'][$k] ?? null) ? $a->changes['lama'][$k] : json_encode($a->changes['lama'][$k] ?? null), 40) }} → {{ \Illuminate\Support\Str::limit(is_scalar($v) ? $v : json_encode($v), 40) }}</div>@endforeach
                    @else
                        {{ \Illuminate\Support\Str::limit(collect($a->changes)->only(['nama', 'name', 'tanggal', 'desa', 'username'])->map(fn ($v, $k) => "$k: $v")->implode(', '), 120) }}
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-kosong judul="Belum ada aktivitas tercatat"/></td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $log->links() }}
</div>
</x-layouts.app>
