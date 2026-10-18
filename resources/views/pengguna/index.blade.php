<x-layouts.app title="Pengguna">
<x-judul judul="Pengguna" sub="Akun petugas lapangan, koordinator, dan pimpinan.">
    <a href="{{ route('pengguna.create') }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Tambah pengguna</a>
</x-judul>
<form method="GET" class="panel mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
    <label class="lg:col-span-2"><span class="label">Cari nama, username, no. HP</span><input name="q" value="{{ request('q') }}" class="input"></label>
    <label><span class="label">Role</span><select name="role" class="input"><option value="">Semua</option>@foreach(App\Support\Role::LABELS as $k => $l)<option value="{{ $k }}" @selected(request('role') === $k)>{{ $l }}</option>@endforeach</select></label>
    <label><span class="label">Status</span><select name="status" class="input"><option value="">Semua</option><option value="belum_login" @selected(request('status') === 'belum_login')>Belum pernah login</option><option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option></select></label>
    <div class="flex items-end"><button class="btn-primary w-full">Terapkan</button></div>
</form>
<div class="panel overflow-x-auto">
    <table class="table-base">
        <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Wilayah</th><th class="text-right">Sub-D.I.</th><th>Login terakhir</th><th></th></tr></thead>
        <tbody>
        @foreach($pengguna as $p)
            <tr @class(['opacity-50' => ! $p->is_active])>
                <td class="font-medium">{{ $p->name }}<div class="text-xs text-basalt-mute">{{ $p->jabatan }}</div></td>
                <td class="text-xs">{{ $p->username }}@if($p->no_hp && $p->no_hp !== $p->username)<div class="text-basalt-mute">{{ $p->no_hp }}</div>@endif</td>
                <td class="text-xs">{{ $p->roleLabel() }}</td>
                <td class="text-xs">{{ $p->wilayahSatgas ? 'Satgas '.$p->wilayahSatgas->kode : '' }}{{ $p->daerahIrigasi ? 'D.I. '.$p->daerahIrigasi->nama : '' }}</td>
                <td class="text-right">{{ $p->sub_daerah_irigasi_count ?: '–' }}</td>
                <td class="text-xs">{{ $p->last_login_at?->diffForHumans() ?? 'Belum pernah' }}@if($p->must_change_password)<div class="text-status-siaga">Password awal</div>@endif</td>
                <td class="whitespace-nowrap text-right text-xs">
                    <a href="{{ route('pengguna.edit', $p) }}" class="font-medium text-saluran-600">Ubah</a>
                    <form method="POST" action="{{ route('pengguna.reset', $p) }}" class="inline" onsubmit="return confirm('Buat password baru untuk akun ini?')">@csrf<button class="ml-2 font-medium text-saluran-600">Reset password</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $pengguna->links() }}
</div>
</x-layouts.app>
