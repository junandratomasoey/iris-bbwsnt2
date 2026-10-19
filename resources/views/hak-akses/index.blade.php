@php use App\Support\Izin; use App\Support\Role; @endphp
<x-layouts.app title="Hak akses">
<x-judul judul="Hak akses" sub="Atur form dan fitur yang bisa dibuka setiap jabatan, lalu beri pengecualian untuk orang tertentu bila perlu."/>

<div class="panel mb-4 p-4 text-sm text-basalt-soft">
    <p>Izin hanya menentukan <strong>form/fitur apa</strong> yang boleh dipakai. <strong>Wilayah kerja</strong> tetap mengikuti penugasan akun:
        Koordinator → D.I. di wilayah Satgas-nya, Manager → D.I.-nya, Petugas/POB → Sub-D.I. yang ditugaskan.
        Super Admin selalu punya semua izin dan satu-satunya yang bisa membuka halaman ini.</p>
</div>

<form method="POST" action="{{ route('hak-akses.update') }}" class="panel mb-6" x-data="{ ubah: false }" @change="ubah = true">
    @csrf @method('PUT')
    <div class="overflow-x-auto">
        <table class="table-base min-w-[720px]">
            <thead class="sticky top-0">
            <tr>
                <th class="w-[34%]">Form / fitur</th>
                @foreach($roles as $r => $l)
                    <th class="text-center"><span class="block leading-tight">{{ $l }}</span><span class="text-[11px] font-normal text-basalt-mute">{{ $jumlah[$r] ?? 0 }} akun</span></th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach(Izin::GRUP as $g => $judulGrup)
                <tr class="bg-savana-deep/60"><td colspan="{{ count($roles) + 1 }}" class="py-1.5 text-xs font-semibold uppercase tracking-wide text-basalt-soft">{{ $judulGrup }}</td></tr>
                @foreach(collect(Izin::DAFTAR)->where('grup', $g) as $k => $iz)
                    <tr>
                        <td><span class="font-medium">{{ $iz['label'] }}</span><span class="block text-xs text-basalt-mute">{{ $iz['ket'] }}</span></td>
                        @foreach($roles as $r => $l)
                            <td class="text-center">
                                <label class="inline-flex cursor-pointer items-center justify-center p-2" title="{{ $l }} — {{ $iz['label'] }}{{ Izin::bawaan($r, $k) ? ' (bawaan: ya)' : ' (bawaan: tidak)' }}">
                                    <input type="checkbox" name="izin[{{ $r }}][{{ $k }}]" value="1" @checked($matriks[$r][$k]) class="h-5 w-5 rounded border-savana-line text-saluran-600 focus:ring-saluran-500">
                                    <span class="sr-only">{{ $l }}: {{ $iz['label'] }}</span>
                                </label>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="sticky bottom-0 flex flex-wrap items-center gap-3 border-t border-savana-line bg-white p-3">
        <button class="btn-primary px-8">Simpan hak akses</button>
        <span x-show="ubah" x-cloak class="text-sm text-status-siaga">Ada perubahan yang belum disimpan.</span>
        <button form="form-bawaan" class="ml-auto text-sm font-medium text-basalt-soft hover:text-status-kritis">Kembalikan ke bawaan</button>
    </div>
</form>
<form id="form-bawaan" method="POST" action="{{ route('hak-akses.bawaan') }}" onsubmit="return confirm('Kembalikan semua izin jabatan ke pengaturan bawaan? Pengecualian per orang tidak berubah.')">@csrf</form>

<section class="panel p-4">
    <h2 class="text-lg font-semibold">Pengecualian per orang</h2>
    <p class="mb-4 text-sm text-basalt-soft">Menimpa izin dari jabatannya. Contoh: satu PPA senior diberi izin verifikasi, atau satu POB dicabut izin laporan harian karena hanya bertugas membaca pintu air.</p>

    <form method="POST" action="{{ route('hak-akses.pengecualian.store') }}" class="mb-5 grid gap-3 rounded-md border border-savana-line p-3 sm:grid-cols-2 lg:grid-cols-12">
        @csrf
        <x-field name="user_id" label="Pengguna" class="lg:col-span-4">
            <select id="user_id" name="user_id" class="input" required>
                <option value="">Pilih…</option>
                @foreach($penggunaList->groupBy('role') as $r => $daftar)
                    <optgroup label="{{ Role::label($r) }}">
                        @foreach($daftar as $p)<option value="{{ $p->id }}" @selected(old('user_id') == $p->id)>{{ $p->name }} ({{ $p->username }})</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
        </x-field>
        <x-field name="izin" label="Form / fitur" class="lg:col-span-3">
            <select id="izin" name="izin" class="input" required>
                @foreach(Izin::DAFTAR as $k => $iz)<option value="{{ $k }}" @selected(old('izin') === $k)>{{ $iz['label'] }}</option>@endforeach
            </select>
        </x-field>
        <x-field name="boleh" label="Aksi" class="lg:col-span-2">
            <select id="boleh" name="boleh" class="input"><option value="1">Beri izin</option><option value="0" @selected(old('boleh') === '0')>Cabut izin</option></select>
        </x-field>
        <x-field name="alasan" label="Alasan (opsional)" class="lg:col-span-3">
            <input id="alasan" name="alasan" value="{{ old('alasan') }}" class="input" maxlength="255" placeholder="mis. SK penugasan 2026">
        </x-field>
        <div class="lg:col-span-12"><button class="btn-primary">Simpan pengecualian</button></div>
    </form>

    @if($pengecualian->isEmpty())
        <p class="text-sm text-basalt-mute">Belum ada pengecualian. Semua orang mengikuti izin jabatannya.</p>
    @else
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Pengguna</th><th>Jabatan</th><th>Form / fitur</th><th>Pengecualian</th><th>Alasan</th><th></th></tr></thead>
                <tbody>
                @foreach($pengecualian as $p)
                    <tr @class(['opacity-50' => ! $p->user->is_active])>
                        <td class="font-medium">{{ $p->user->name }}<span class="block text-xs font-normal text-basalt-mute">{{ $p->user->username }}</span></td>
                        <td class="text-xs">{{ $p->user->roleLabel() }}</td>
                        <td>{{ Izin::label($p->izin) }}</td>
                        <td>@if($p->boleh)<span class="chip-aman">Diberi</span>@else<span class="chip-kritis">Dicabut</span>@endif</td>
                        <td class="text-xs">{{ $p->alasan }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('hak-akses.pengecualian.destroy', $p) }}">@csrf @method('DELETE')<button class="text-xs font-medium text-saluran-600">Hapus</button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
</x-layouts.app>
