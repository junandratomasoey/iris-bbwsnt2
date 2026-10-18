@php
    use App\Support\Role;
    $baru = ! $pengguna->exists;
    $terpilih = collect(old('sub_ids', $pengguna->exists ? $pengguna->subDaerahIrigasi->pluck('id')->all() : []))->map(fn ($v) => (int) $v)->all();
@endphp
<x-layouts.app :title="$baru ? 'Tambah pengguna' : 'Ubah '.$pengguna->name">
<div class="mx-auto max-w-3xl">
<x-judul :judul="$baru ? 'Tambah pengguna' : 'Ubah pengguna'" :sub="$baru ? 'Password awal dibuat otomatis bila dikosongkan, dan wajib diganti saat login pertama.' : $pengguna->username"/>
<form method="POST" action="{{ $baru ? route('pengguna.store') : route('pengguna.update', $pengguna) }}" class="space-y-5"
      x-data="{ role: '{{ old('role', $pengguna->role) }}', cari: '', terpilih: @js($terpilih) }">
    @csrf @if(! $baru) @method('PUT') @endif
    <fieldset class="panel grid gap-4 p-5 sm:grid-cols-2">
        <x-field name="name" label="Nama lengkap" wajib><input id="name" name="name" value="{{ old('name', $pengguna->name) }}" required class="input"></x-field>
        <x-field name="no_hp" label="No. HP (WhatsApp)"><input id="no_hp" name="no_hp" inputmode="tel" value="{{ old('no_hp', $pengguna->no_hp) }}" class="input"></x-field>
        <x-field name="username" label="Username" hint="Untuk petugas, pakai nomor HP agar mudah diingat." wajib><input id="username" name="username" value="{{ old('username', $pengguna->username) }}" required class="input"></x-field>
        <x-field name="email" label="Email"><input id="email" type="email" name="email" value="{{ old('email', $pengguna->email) }}" class="input"></x-field>
        <x-field name="role" label="Role" wajib><select id="role" name="role" x-model="role" class="input">@foreach($roles as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></x-field>
        <x-field name="jabatan" label="Jabatan" hint="Mis. PPA, POB, Manager, Kabid OP"><input id="jabatan" name="jabatan" value="{{ old('jabatan', $pengguna->jabatan) }}" class="input"></x-field>
        <x-field name="wilayah_satgas_id" label="Wilayah Satgas" x-show="role === 'koordinator'"><select id="wilayah_satgas_id" name="wilayah_satgas_id" class="input"><option value="">–</option>@foreach($satgasList as $s)<option value="{{ $s->id }}" @selected(old('wilayah_satgas_id', $pengguna->wilayah_satgas_id) == $s->id)>{{ $s->label }}</option>@endforeach</select></x-field>
        <x-field name="daerah_irigasi_id" label="D.I. utama"><select id="daerah_irigasi_id" name="daerah_irigasi_id" class="input"><option value="">–</option>@foreach($diList as $d)<option value="{{ $d->id }}" @selected(old('daerah_irigasi_id', $pengguna->daerah_irigasi_id) == $d->id)>{{ $d->nama }}</option>@endforeach</select></x-field>
        <x-field name="password" :label="$baru ? 'Password awal' : 'Password baru'" :hint="$baru ? 'Kosongkan untuk dibuat otomatis.' : 'Kosongkan bila tidak diganti.'"><input id="password" name="password" type="text" autocomplete="new-password" class="input"></x-field>
        <x-field name="status_kontak" label="Status kontak"><select id="status_kontak" name="status_kontak" class="input">@foreach(['', 'Belum Dihubungi', 'Sudah Dihubungi', 'Tidak Aktif'] as $s)<option value="{{ $s }}" @selected(old('status_kontak', $pengguna->status_kontak) === $s)>{{ $s ?: '–' }}</option>@endforeach</select></x-field>
        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $pengguna->is_active)) class="rounded border-savana-line text-saluran-600"> Akun aktif</label>
    </fieldset>
    <fieldset class="panel p-5" x-show="['petugas','pob','manager'].includes(role)">
        <legend class="float-left font-gauge text-lg font-semibold">Penugasan Sub-D.I.</legend>
        <p class="clear-left text-sm text-basalt-soft">Petugas hanya bisa melapor untuk Sub-D.I. yang dicentang. <span x-text="terpilih.length"></span> dipilih.</p>
        <input type="search" x-model="cari" placeholder="Cari Sub-D.I. atau D.I." class="input mt-3">
        <div class="mt-3 max-h-80 overflow-y-auto rounded border border-savana-line">
            @foreach($subList->groupBy(fn ($s) => $s->daerahIrigasi->nama) as $di => $grup)
                <div x-show="!cari || '{{ strtolower(addslashes($di)) }} {{ strtolower(addslashes($grup->pluck('nama')->implode(' '))) }}'.includes(cari.toLowerCase())">
                    <p class="sticky top-0 bg-savana px-3 py-1 text-xs font-semibold text-basalt-soft">D.I. {{ $di }}</p>
                    @foreach($grup as $s)
                        <label class="flex items-center gap-2 px-3 py-1 text-sm hover:bg-savana" x-show="!cari || '{{ strtolower(addslashes($di.' '.$s->nama)) }}'.includes(cari.toLowerCase())">
                            <input type="checkbox" name="sub_ids[]" value="{{ $s->id }}" x-model.number="terpilih" class="rounded border-savana-line text-saluran-600"> {{ $s->nama }}
                        </label>
                    @endforeach
                </div>
            @endforeach
        </div>
    </fieldset>
    <div class="flex gap-2"><button class="btn-primary px-8">Simpan</button><a href="{{ route('pengguna.index') }}" class="btn-ghost">Batal</a></div>
</form>
@if(! $baru && $pengguna->id !== auth()->id())
    <form method="POST" action="{{ route('pengguna.destroy', $pengguna) }}" class="mt-8 border-t border-savana-line pt-4" onsubmit="return confirm('Hapus akun ini? Akun yang sudah punya laporan akan dinonaktifkan.')">@csrf @method('DELETE')<button class="text-sm font-medium text-status-kritis">Hapus akun</button></form>
@endif
</div>
</x-layouts.app>
