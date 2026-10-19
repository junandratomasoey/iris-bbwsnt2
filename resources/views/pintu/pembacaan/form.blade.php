@php use App\Models\SesiPintu; use App\Models\PintuAir; $ubah = $sesi->exists; @endphp
<x-layouts.app title="{{ $ubah ? 'Ubah pembacaan' : 'Baca pintu air' }}">
<x-judul :judul="$ubah ? 'Ubah pembacaan pintu air' : 'Baca pintu air'" sub="Isi debit dan/atau TMA tiap pintu yang dikunjungi. Pintu yang tidak dibaca biarkan kosong."/>

@if(! $di)
    <form method="GET" class="panel max-w-xl p-4">
        @if($diList->isEmpty())
            <x-kosong judul="Belum ada pintu air di wilayah tugas Anda">Minta Manager D.I. atau Admin OP menambahkan pintu air beserta kodenya.</x-kosong>
        @else
            <x-field name="di" label="Pilih D.I."><select id="di" name="di" class="input" onchange="this.form.submit()"><option value="">Pilih…</option>@foreach($diList as $d)<option value="{{ $d->id }}">{{ $d->nama }}</option>@endforeach</select></x-field>
            <noscript><button class="btn-primary mt-3">Lanjut</button></noscript>
        @endif
    </form>
@else
@php
    $dataPintu = $pintu->mapWithKeys(fn ($p) => [$p->id => [
        'dmin' => $p->debit_min_lps, 'dmaks' => $p->debit_maks_lps, 'tmin' => $p->tma_min_cm, 'tmaks' => $p->tma_maks_cm,
    ]]);
@endphp
<form method="POST" action="{{ $ubah ? route('pembacaan-pintu.update', $sesi) : route('pembacaan-pintu.store') }}" enctype="multipart/form-data"
      x-data="formPintu({ batas: @js($dataPintu), nilai: @js($nilai), rutin: {{ SesiPintu::HARI_RUTIN }}, tanggal: @js(old('tanggal', $sesi->tanggal?->toDateString())), jenis: @js(old('jenis', $sesi->jenis)) })" @submit="kirim = true">
    @csrf @if($ubah) @method('PUT') @endif
    <input type="hidden" name="daerah_irigasi_id" value="{{ $di->id }}">

    <section class="panel mb-4 grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2 lg:col-span-4 flex flex-wrap items-baseline justify-between gap-2">
            <p class="text-lg font-semibold">D.I. {{ $di->nama }}</p>
            @if(! $ubah && $diList->count() > 1)<a href="{{ route('pembacaan-pintu.create') }}" class="text-sm font-medium text-saluran-600">Ganti D.I.</a>@endif
        </div>
        <x-field name="tanggal" label="Tanggal" wajib>
            <input id="tanggal" type="date" name="tanggal" x-model="tanggal" max="{{ today()->toDateString() }}" min="{{ today()->subDays(30)->toDateString() }}" class="input" required>
        </x-field>
        <x-field name="jam" label="Jam"><input id="jam" type="time" name="jam" value="{{ old('jam', $sesi->jam ? substr($sesi->jam, 0, 5) : null) }}" class="input"></x-field>
        <x-field name="jenis" label="Alasan pembacaan" wajib class="sm:col-span-2">
            <select id="jenis" name="jenis" x-model="jenis" class="input" required>
                <option value="">Pilih…</option>
                @foreach(SesiPintu::JENIS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
            <p x-show="jenis === 'rutin' && !hariSenin()" x-cloak class="mt-1 text-xs text-status-siaga">Tanggal ini bukan hari Senin — tetap tercatat sebagai pembacaan rutin (terlambat).</p>
        </x-field>
        <x-field name="catatan" label="Catatan" class="sm:col-span-2 lg:col-span-4" hint="Wajib bila alasan 'Lainnya'. Mis. panen petak tersier 2, pembersihan saluran, pintu macet.">
            <textarea id="catatan" name="catatan" rows="2" class="input" :required="jenis === 'lainnya'">{{ old('catatan', $sesi->catatan) }}</textarea>
        </x-field>
    </section>

    @error('baca')<p class="mb-3 rounded-md bg-[#F6D9D7] px-3 py-2 text-sm font-medium text-[#7E1A14]">{{ $message }}</p>@enderror
    @if($errors->has('baca.*'))<p class="mb-3 rounded-md bg-[#F6D9D7] px-3 py-2 text-sm font-medium text-[#7E1A14]">{{ collect($errors->get('baca.*'))->flatten()->first() }}</p>@endif

    @if($pintu->isEmpty())
        <div class="panel"><x-kosong judul="Tidak ada pintu yang bisa Anda isi di D.I. ini"/></div>
    @endif
    @foreach($pintu->groupBy(fn ($p) => $p->sub_daerah_irigasi_id ?? 0) as $subId => $grup)
        <section class="panel mb-4 overflow-hidden">
            <h2 class="border-b border-savana-line bg-savana px-3 py-2 text-sm font-semibold">{{ $subId ? 'Sub-D.I. '.$grup->first()->subDaerahIrigasi->nama : 'Tingkat D.I.' }}</h2>
            <ul class="divide-y divide-savana-line">
                @foreach($grup as $p)
                    @php $t = $p->pembacaanTerakhir; @endphp
                    <li class="grid grid-cols-2 gap-x-3 gap-y-2 p-3 lg:grid-cols-12 lg:gap-3" :class="terisi({{ $p->id }}) && 'bg-[#F4F8F1]'">
                        <div class="col-span-2 lg:col-span-3">
                            <p class="font-semibold">{{ $p->kode }} @unless($p->aktif)<span class="chip-kosong">nonaktif</span>@endunless</p>
                            <p class="text-xs text-basalt-soft">{{ $p->nama ?: $p->jenis_label }}</p>
                            <p class="mt-1 text-[11px] text-basalt-mute">@if($p->punyaRentangDebit() || $p->tma_min_cm !== null || $p->tma_maks_cm !== null)Normal: {{ PintuAir::rentangTeks($p->debit_min_lps, $p->debit_maks_lps, 'L/dt') }} · {{ PintuAir::rentangTeks($p->tma_min_cm, $p->tma_maks_cm, 'cm') }}@else Rentang normal belum diisi @endif</p>
                            @if($t && (! $ubah || $t->sesi_pintu_id !== $sesi->id))<p class="text-[11px] text-basalt-mute">Terakhir {{ $t->tanggal->translatedFormat('d M') }}: {{ angka_r($t->debit_lps) }} L/dt · {{ angka_r($t->tma_cm) }} cm</p>@endif
                        </div>
                        <label class="lg:col-span-2"><span class="label">Debit (L/dt)</span>
                            <input type="number" step="any" min="0" inputmode="decimal" name="baca[{{ $p->id }}][debit]" x-model="nilai[{{ $p->id }}].debit" class="input">
                            <span class="mt-1 block h-4 text-xs font-semibold" :class="warna(statusDebit({{ $p->id }}))" x-text="teks(statusDebit({{ $p->id }}))"></span></label>
                        <label class="lg:col-span-2"><span class="label">TMA (cm)</span>
                            <input type="number" step="any" min="0" inputmode="decimal" name="baca[{{ $p->id }}][tma]" x-model="nilai[{{ $p->id }}].tma" class="input">
                            <span class="mt-1 block h-4 text-xs font-semibold" :class="warna(statusTma({{ $p->id }}))" x-text="teks(statusTma({{ $p->id }}))"></span></label>
                        <label class="lg:col-span-2"><span class="label">Bukaan (cm)</span>
                            <input type="number" step="any" min="0" inputmode="decimal" name="baca[{{ $p->id }}][bukaan]" x-model="nilai[{{ $p->id }}].bukaan" class="input"></label>
                        <label class="lg:col-span-3"><span class="label">Catatan</span>
                            <input name="baca[{{ $p->id }}][catatan]" x-model="nilai[{{ $p->id }}].catatan" maxlength="255" class="input" placeholder="mis. pintu macet, sampah"></label>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <section class="panel mb-4 p-4">
        <p class="label">Foto (opsional)</p>
        <x-unggah-foto :maks="max(0, SesiPintu::MAKS_FOTO - ($ubah ? $sesi->foto->count() : 0))"/>
        @if($ubah && $sesi->foto->isNotEmpty())
            <x-galeri-foto :foto="$sesi->foto" class="mt-3" :hapus-route="fn ($f) => route('pembacaan-pintu.foto.destroy', [$sesi, $f])"/>
        @endif
    </section>

    <div class="sticky bottom-0 flex flex-wrap items-center gap-3 border-t border-savana-line bg-savana py-3">
        <button class="btn-primary px-8" :disabled="kirim || jumlah() === 0" x-text="kirim ? 'Menyimpan…' : `Simpan ${jumlah()} pintu`"></button>
        <a href="{{ $ubah ? route('pembacaan-pintu.show', $sesi) : route('pintu.index', ['di' => $di->id]) }}" class="btn-ghost">Batal</a>
        <span class="text-sm text-basalt-soft" x-show="jumlah() === 0">Isi debit atau TMA minimal satu pintu.</span>
    </div>
</form>
@endif
</x-layouts.app>
