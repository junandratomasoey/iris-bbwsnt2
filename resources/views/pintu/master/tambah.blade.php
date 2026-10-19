@php use App\Models\PintuAir; @endphp
<x-layouts.app title="Tambah pintu air">
<x-judul judul="Tambah pintu air" sub="D.I. {{ $di->nama }} — isi beberapa pintu sekaligus. Baris tanpa kode diabaikan."/>

@php
    $awal = old('pintu', [['kode' => '', 'nama' => '', 'jenis' => 'sadap', 'sub_daerah_irigasi_id' => '', 'saluran' => '', 'luas_layanan_ha' => '', 'debit_min_lps' => '', 'debit_maks_lps' => '', 'tma_min_cm' => '', 'tma_maks_cm' => '']]);
@endphp
<form method="POST" action="{{ route('master.pintu-air.store') }}" x-data="{ baris: @js(array_values($awal)), kirim: false,
        tambah() { const b = this.baris[this.baris.length - 1] || {}; this.baris.push({ kode: '', nama: '', jenis: b.jenis || 'sadap', sub_daerah_irigasi_id: b.sub_daerah_irigasi_id || '', saluran: b.saluran || '', luas_layanan_ha: '', debit_min_lps: '', debit_maks_lps: '', tma_min_cm: '', tma_maks_cm: '' }); this.$nextTick(() => [...this.$el.querySelectorAll('[data-kode]')].pop()?.focus()) } }"
      @submit="kirim = true">
    @csrf
    <input type="hidden" name="daerah_irigasi_id" value="{{ $di->id }}">

    @if($errors->any())
        <div class="mb-4 rounded-md bg-[#F6D9D7] px-3 py-2 text-sm text-[#7E1A14]">
            <p class="font-semibold">Periksa isian:</p>
            <ul class="list-disc pl-5">@foreach(collect($errors->all())->unique()->take(8) as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="panel mb-3 p-3 text-sm text-basalt-soft">
        <p><strong>Kode</strong> mengikuti nomenklatur skema jaringan (mis. <em>BOs.1 ki</em>, <em>BSa 2</em>). <strong>Rentang normal</strong> debit/TMA dipakai untuk menandai pembacaan kurang atau berlebih — isi dari kebutuhan air/rencana pembagian air; boleh dikosongkan dulu dan diisi kemudian.</p>
        @if($kodeAda->isNotEmpty())<p class="mt-1 text-xs">Kode yang sudah ada: {{ $kodeAda->implode(', ') }}</p>@endif
    </div>

    <div class="space-y-3">
        <template x-for="(b, i) in baris" :key="i">
            <fieldset class="panel grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-12">
                <legend class="sr-only" x-text="'Pintu ke-' + (i + 1)"></legend>
                <label class="lg:col-span-2"><span class="label">Kode <span class="text-status-kritis">*</span></span>
                    <input class="input" :name="`pintu[${i}][kode]`" x-model="b.kode" data-kode maxlength="30" placeholder="BOs.1 ki"></label>
                <label class="lg:col-span-3"><span class="label">Nama / petak</span>
                    <input class="input" :name="`pintu[${i}][nama]`" x-model="b.nama" maxlength="255" placeholder="Pintu sadap tersier Oesao 1 kiri"></label>
                <label class="lg:col-span-2"><span class="label">Jenis</span>
                    <select class="input" :name="`pintu[${i}][jenis]`" x-model="b.jenis">@foreach(PintuAir::JENIS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                <label class="lg:col-span-2"><span class="label">Sub-D.I.</span>
                    <select class="input" :name="`pintu[${i}][sub_daerah_irigasi_id]`" x-model="b.sub_daerah_irigasi_id"><option value="">Tingkat D.I.</option>@foreach($subList as $s)<option value="{{ $s->id }}">{{ $s->nama }}</option>@endforeach</select></label>
                <label class="lg:col-span-3"><span class="label">Saluran yang dialiri</span>
                    <input class="input" :name="`pintu[${i}][saluran]`" x-model="b.saluran" maxlength="255" placeholder="Saluran sekunder Oesao"></label>
                <label class="lg:col-span-2"><span class="label">Luas layanan (Ha)</span>
                    <input class="input" type="number" step="any" min="0" inputmode="decimal" :name="`pintu[${i}][luas_layanan_ha]`" x-model="b.luas_layanan_ha"></label>
                <div class="sm:col-span-2 lg:col-span-4"><span class="label">Debit normal (L/dt)</span>
                    <div class="flex items-center gap-2"><input class="input" type="number" step="any" min="0" inputmode="decimal" :name="`pintu[${i}][debit_min_lps]`" x-model="b.debit_min_lps" placeholder="min" aria-label="Debit minimum"><span>–</span><input class="input" type="number" step="any" min="0" inputmode="decimal" :name="`pintu[${i}][debit_maks_lps]`" x-model="b.debit_maks_lps" placeholder="maks" aria-label="Debit maksimum"></div></div>
                <div class="sm:col-span-2 lg:col-span-4"><span class="label">TMA normal (cm)</span>
                    <div class="flex items-center gap-2"><input class="input" type="number" step="any" min="0" inputmode="decimal" :name="`pintu[${i}][tma_min_cm]`" x-model="b.tma_min_cm" placeholder="min" aria-label="TMA minimum"><span>–</span><input class="input" type="number" step="any" min="0" inputmode="decimal" :name="`pintu[${i}][tma_maks_cm]`" x-model="b.tma_maks_cm" placeholder="maks" aria-label="TMA maksimum"></div></div>
                <div class="flex items-end justify-end lg:col-span-2">
                    <button type="button" class="text-sm font-medium text-status-kritis" x-show="baris.length > 1" @click="baris.splice(i, 1)">Hapus baris</button>
                </div>
            </fieldset>
        </template>
    </div>

    <div class="sticky bottom-0 mt-3 flex flex-wrap items-center gap-2 border-t border-savana-line bg-savana py-3">
        <button type="button" class="btn-ghost" @click="tambah()"><x-ikon n="tambah" class="h-4 w-4"/> Tambah baris</button>
        <button class="btn-primary px-8" :disabled="kirim" x-text="kirim ? 'Menyimpan…' : `Simpan ${baris.filter(b => b.kode.trim()).length} pintu`"></button>
        <a href="{{ route('pintu.index', ['di' => $di->id]) }}" class="btn-ghost">Batal</a>
    </div>
</form>
</x-layouts.app>
