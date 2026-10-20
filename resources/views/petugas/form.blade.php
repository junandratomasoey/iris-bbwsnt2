@php
    $baru = ! $p->exists;
    $terpilih = collect(old('sub_ids', $baru ? [] : $p->subDaerahIrigasi->pluck('id')->all()))->map(fn ($v) => (int) $v)->values()->all();
    $diAwal = old('daerah_irigasi_id', $p->daerah_irigasi_id);
    $lain = $baru ? collect() : $p->subDaerahIrigasi->where('daerah_irigasi_id', '!=', $p->daerah_irigasi_id);
@endphp
<x-layouts.app :title="$baru ? 'Tambah petugas' : 'Ubah '.$p->name">
<div class="mx-auto max-w-3xl">
<x-judul :judul="$baru ? 'Tambah petugas' : $p->name" :sub="$baru ? 'Akun dibuat otomatis: login memakai nomor HP, password awal tampil sekali setelah disimpan.' : 'Login: '.($p->no_hp ?: $p->username).($p->last_login_at ? ' · terakhir masuk '.$p->last_login_at->diffForHumans() : ' · belum pernah login')"/>

<form method="POST" action="{{ $baru ? route('petugas.store') : route('petugas.update', $p) }}" class="space-y-5"
      x-data="{ di: '{{ $diAwal }}', subs: @js($subList), terpilih: @js($terpilih), kirim: false,
                get subDi() { return this.subs.filter((s) => s.daerah_irigasi_id == this.di) },
                semua(v) { const ids = this.subDi.map((s) => s.id); this.terpilih = v ? [...new Set([...this.terpilih, ...ids])] : this.terpilih.filter((i) => !ids.includes(i)) } }"
      @submit="kirim = true">
    @csrf @if(! $baru) @method('PUT') @endif
    <fieldset class="panel grid gap-4 p-5 sm:grid-cols-2">
        <x-field name="name" label="Nama lengkap" wajib><input id="name" name="name" value="{{ old('name', $p->name) }}" required autocomplete="off" class="input"></x-field>
        <x-field name="no_hp" label="No. HP / WhatsApp" wajib hint="Dipakai untuk login dan tombol WhatsApp."><input id="no_hp" name="no_hp" inputmode="tel" value="{{ old('no_hp', $p->no_hp) }}" required placeholder="08…" class="input"></x-field>
        <x-field name="jabatan" label="Jabatan"><input id="jabatan" name="jabatan" list="jabatan-saran" value="{{ old('jabatan', $p->jabatan) }}" class="input">
            <datalist id="jabatan-saran">@foreach($jabatanSaran as $j)<option value="{{ $j }}">@endforeach</datalist></x-field>
        <x-field name="role" label="Hak akses" wajib hint="PPA mengisi laporan tanaman di Sub-D.I. yang dicentang. Manager mengakses Sub-D.I. yang dicentang; bila tidak ada yang dicentang, seluruh Sub-D.I. di D.I.-nya."><select id="role" name="role" class="input">@foreach($roles as $k => $l)<option value="{{ $k }}" @selected(old('role', $p->role) === $k)>{{ $l }}</option>@endforeach</select></x-field>
        <x-field name="status_kontak" label="Status kontak"><select id="status_kontak" name="status_kontak" class="input"><option value="">–</option>@foreach($statusKontak as $s)<option @selected(old('status_kontak', $p->status_kontak) === $s)>{{ $s }}</option>@endforeach</select></x-field>
        <div class="flex items-end pb-2"><label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $p->is_active)) class="rounded border-savana-line text-saluran-600"> Aktif (bisa login & tampil di direktori)</label></div>
    </fieldset>

    <fieldset class="panel space-y-3 p-5">
        <legend class="float-left font-gauge text-lg font-semibold">Tempat bertugas</legend>
        <div class="clear-left"></div>
        <x-field name="daerah_irigasi_id" label="D.I." wajib><select id="daerah_irigasi_id" name="daerah_irigasi_id" x-model="di" required class="input sm:w-1/2"><option value="">Pilih D.I.</option>@foreach($diList as $d)<option value="{{ $d->id }}">{{ $d->nama }}</option>@endforeach</select></x-field>
        <div x-show="di" x-cloak>
            <div class="flex items-baseline justify-between gap-2">
                <p class="label mb-0">Sub-D.I. yang ditangani <span class="font-normal text-basalt-mute" x-text="'(' + terpilih.filter((i) => subDi.some((s) => s.id === i)).length + ' dipilih)'"></span></p>
                <span class="flex shrink-0 gap-3 whitespace-nowrap text-xs font-medium"><button type="button" class="text-saluran-600" @click="semua(true)">Pilih semua</button><button type="button" class="text-saluran-600" @click="semua(false)">Kosongkan</button></span>
            </div>
            <div class="mt-2 grid max-h-72 gap-x-4 overflow-y-auto rounded border border-savana-line p-2 sm:grid-cols-2">
                <template x-for="s in subDi" :key="s.id">
                    <label class="flex items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-savana">
                        <input type="checkbox" name="sub_ids[]" :value="s.id" x-model.number="terpilih" class="rounded border-savana-line text-saluran-600">
                        <span><span x-text="s.nama"></span> <span class="text-xs text-basalt-mute" x-text="s.lokasi || ''"></span></span>
                    </label>
                </template>
                <p x-show="!subDi.length" class="px-2 py-1.5 text-sm text-basalt-soft">D.I. ini belum punya Sub-D.I.</p>
            </div>
            @error('sub_ids.*')<p class="mt-1 text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
            @if($lain->isNotEmpty())<p class="mt-2 text-xs text-basalt-soft">Juga ditugaskan di D.I. lain: {{ $lain->map(fn ($s) => $s->nama.' ('.$s->daerahIrigasi?->nama.')')->implode(', ') }}. Penugasan ini tetap tersimpan.</p>@endif
        </div>
    </fieldset>

    <div class="flex gap-2"><button class="btn-primary px-8" :disabled="kirim" x-text="kirim ? 'Menyimpan…' : 'Simpan'">Simpan</button><a href="{{ route('petugas.index', ['di' => $p->daerah_irigasi_id]) }}" class="btn-ghost">Batal</a></div>
</form>

@unless($baru)
    <section class="panel mt-6 space-y-3 p-5">
        <h2 class="font-gauge text-lg font-semibold">Akun</h2>
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p class="text-basalt-soft">Lupa password? Buat password baru lalu kirim lewat WhatsApp.</p>
            <form method="POST" action="{{ route('petugas.reset', $p) }}" onsubmit="return confirm('Buat password baru untuk {{ addslashes($p->name) }}?')">@csrf<button class="btn-ghost"><x-ikon n="kunci" class="h-4 w-4"/> Reset password</button></form>
        </div>
        @can('kelola-pengguna')
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-savana-line pt-3 text-sm">
                <p class="text-basalt-soft">Kontak yang sudah punya laporan akan dinonaktifkan, bukan dihapus.</p>
                <form method="POST" action="{{ route('petugas.destroy', $p) }}" onsubmit="return confirm('Hapus kontak {{ addslashes($p->name) }}?')">@csrf @method('DELETE')<button class="btn-ghost text-status-kritis"><x-ikon n="hapus" class="h-4 w-4"/> Hapus kontak</button></form>
            </div>
        @endcan
    </section>
@endunless
</div>
</x-layouts.app>
